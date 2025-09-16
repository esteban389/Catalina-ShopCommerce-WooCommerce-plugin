<?php

// Hook and settings
$produc_sync_hook_name = 'provider_product_sync_hook';
$produc_sync_hook_interval = 'hourly';  // can be changed to 'every_minute' for testing in wp-cron settings below
$timeout = 14 * 60;  // 14 minutes to allow heavy API responses if needed

// API base
$base_url = 'https://shopcommerce.mps.com.co:7965/';

// Brand/Category configuration based on client requirements.
// We use MarcaHomologada values from provider and top-level category codes provided.
// If a brand has an empty categories array, it means import all categories for that brand.
function shopcommerce_sync_jobs_config()
{
  // Category Codes reference (from client list):
  // 1 Accesorios Y Perifericos, 7 Computadores, 12 Impresión, 14 Video, 18 Servidores Y Almacenamiento
  // Note: "Monitores" likely falls under Video (14) in the provided top-level categories.
  $CATEGORIA_ACCESORIOS = 1;
  $CATEGORIA_COMPUTADORES = 7;  // PCs, Portátiles, Workstations
  $CATEGORIA_IMPRESION = 12;
  $CATEGORIA_VIDEO = 14;  // Monitores
  $CATEGORIA_SERVIDORES = 18;

  $common_corp_categories = [
    $CATEGORIA_COMPUTADORES,  // pcs, portátiles, wkst
    $CATEGORIA_SERVIDORES,  // servidores volumen/valor
    $CATEGORIA_ACCESORIOS,  // accesorios
    $CATEGORIA_VIDEO,  // monitores
    $CATEGORIA_IMPRESION,  // impresión
  ];

  return [
    // MarcaHomologada => [CategoriaCodes]
    'HP INC' => $common_corp_categories,
    'DELL' => $common_corp_categories,
    'LENOVO' => $common_corp_categories,
    'APPLE' => [$CATEGORIA_ACCESORIOS, $CATEGORIA_COMPUTADORES],  // accesorios y portátiles
    'ASUS' => [$CATEGORIA_COMPUTADORES],  // portátiles corporativo
    'BOSE' => [],  // todas las categorías de BOSE
    'EPSON' => [],  // todas las categorías de EPSON
    'JBL' => [],  // include if provider exposes JBL; ignored if not found
  ];
}

// Build a flat job list from the config. Each job processes one brand.
function shopcommerce_build_jobs_list()
{
  $config = shopcommerce_sync_jobs_config();
  $jobs = [];
  foreach ($config as $brand => $categories) {
    $jobs[] = [
      'brand' => $brand,
      'categories' => $categories,  // empty array means all categories
    ];
  }
  return $jobs;
}

// Option keys
function shopcommerce_jobs_option_key()
{
  return 'shopcommerce_sync_jobs';
}

function shopcommerce_job_index_option_key()
{
  return 'shopcommerce_sync_jobs_index';
}

// Ensure jobs are stored in options so we can iterate them across cron invocations
function shopcommerce_ensure_jobs_initialized()
{
  $jobs = get_option(shopcommerce_jobs_option_key());
  if (!is_array($jobs) || empty($jobs)) {
    $jobs = shopcommerce_build_jobs_list();
    update_option(shopcommerce_jobs_option_key(), $jobs, false);
  }
  if (get_option(shopcommerce_job_index_option_key(), null) === null) {
    update_option(shopcommerce_job_index_option_key(), 0, false);
  }
}

// Get next job and advance pointer (circular)
function shopcommerce_get_next_job()
{
  shopcommerce_ensure_jobs_initialized();
  $jobs = get_option(shopcommerce_jobs_option_key());
  if (!is_array($jobs) || empty($jobs)) {
    return null;
  }
  $index = intval(get_option(shopcommerce_job_index_option_key(), 0));
  if ($index < 0 || $index >= count($jobs)) {
    $index = 0;
  }
  $job = $jobs[$index];
  $next_index = ($index + 1) % count($jobs);
  update_option(shopcommerce_job_index_option_key(), $next_index, false);
  return $job;
}

// Allow manual reset from admin
function shopcommerce_reset_jobs()
{
  delete_option(shopcommerce_jobs_option_key());
  delete_option(shopcommerce_job_index_option_key());
}

// Register main cron action
add_action($produc_sync_hook_name, 'provider_product_sync_hook');

function product_sync_plugin_activate()
{
  global $produc_sync_hook_name;
  global $produc_sync_hook_interval;
  // Initialize the jobs pointer on activation
  shopcommerce_ensure_jobs_initialized();

  if (!wp_next_scheduled($produc_sync_hook_name)) {
    add_filter('cron_schedules', function ($schedules) {
      $schedules['every_minute'] = [
        'interval' => 60,
        'display' => __('Every Minute', 'shopcommerce-product-sync-plugin')
      ];
      return $schedules;
    });
    wp_schedule_event(time(), $produc_sync_hook_interval, $produc_sync_hook_name);
  }
}

function product_sync_plugin_deactivate()
{
  global $produc_sync_hook_name;
  wp_clear_scheduled_hook($produc_sync_hook_name);
}

// Main cron callback: process a single brand job per run
function provider_product_sync_hook()
{
  global $base_url;
  global $timeout;

  $job = shopcommerce_get_next_job();
  if (!$job) {
    error_log('[ShopCommerce Sync] No jobs configured.');
    return;
  }

  $brand = $job['brand'];
  $categories = isset($job['categories']) && is_array($job['categories']) ? $job['categories'] : [];

  $token = get_api_token();
  if (!is_string($token) || empty($token)) {
    error_log('[ShopCommerce Sync] Token request failed.');
    return;
  }

  $api_url = trailingslashit($base_url) . 'api/Webapi/VerCatalogo';

  $headers = [
    'Authorization' => 'Bearer ' . $token,
    'Content-Type' => 'application/json',  // uncomment if API expects JSON
    'X-MARKS' => $brand,
  ];
  if (!empty($categories)) {
    // Convert categories to a string of comma separated integers
    $headers['X-CATEGORIA'] = implode(',', $categories);
  }

  $args = [
    'headers' => $headers,
    'timeout' => $timeout,
  ];

  $response = wp_remote_post($api_url, $args);

  if (is_wp_error($response)) {
    $error_message = $response->get_error_message();
    error_log('[ShopCommerce Sync] Error retrieving catalog for brand ' . $brand . ': ' . $error_message);

    // Log API error
    if (function_exists('shopcommerce_log_activity')) {
      shopcommerce_log_activity('sync_error', 'API error retrieving catalog', [
        'brand' => $brand,
        'error' => $error_message,
        'api_url' => $api_url
      ]);
    }
    return;
  }

  $body_str = wp_remote_retrieve_body($response);
  $json = json_decode($body_str, true);

  if (!is_array($json)) {
    error_log('[ShopCommerce Sync] Invalid JSON response for brand ' . $brand . '. Raw: ' . substr($body_str, 0, 500));

    // Log JSON decode error
    if (function_exists('shopcommerce_log_activity')) {
      shopcommerce_log_activity('sync_error', 'Invalid JSON response', [
        'brand' => $brand,
        'response_preview' => substr($body_str, 0, 500)
      ]);
    }
    return;
  }

  // Optional client-side filter if API didn’t apply filters
  $items = $json;
  // Detect if shape is wrapped or direct list
  if (isset($json['listaproductos']) && is_array($json['listaproductos'])) {
    $items = $json['listaproductos'];
  }

  // For now we only log summary; product creation/update will be implemented next.
  $count = count($items);
  error_log(sprintf('[ShopCommerce Sync] Brand: %s | Categories: %s | Received: %d | Filtered: %d',
    $brand,
    empty($categories) ? 'ALL' : implode(',', $categories),
    is_array($items) ? count($items) : 0,
    $count));

  // Example of chunking within brand if needed (process only first N per run)
  $batch_size = 100;  // adjust based on execution time constraints
  $batch = array_slice($items, 0, $batch_size);

  // Process the batch of products for WooCommerce
  if (!empty($batch)) {
    $results = shopcommerce_mass_insert_update_products($batch, $brand);

    // Log the sync activity for management page
    if (function_exists('shopcommerce_log_activity')) {
      shopcommerce_log_activity('sync_complete', 'Sync completed for brand: ' . $brand, [
        'brand' => $brand,
        'products_processed' => count($batch),
        'created' => $results['created'],
        'updated' => $results['updated'],
        'errors' => $results['errors'],
        'categories' => $categories
      ]);
    }
  }
}

function get_api_token()
{
  global $base_url;
  global $timeout;
  $username = 'pruebas@hekalsoluciones.com';
  $password = 'Mo3rdoadzy1M';

  $api_url = trailingslashit($base_url) . 'Token';
  $response = wp_remote_post($api_url, array(
    'body' => array(
      'password' => $password,
      'username' => $username,
      'grant_type' => 'password'
    ),
    'timeout' => $timeout,
  ));

  if (is_wp_error($response)) {
    error_log('[ShopCommerce Sync] Error retrieving API token: ' . $response->get_error_message());
    return null;
  }

  $body = wp_remote_retrieve_body($response);
  $jsonResponse = json_decode($body, true);

  if (!is_array($jsonResponse) || !array_key_exists('access_token', $jsonResponse)) {
    error_log('[ShopCommerce Sync] Invalid token response: ' . substr($body, 0, 500));
    return null;
  }
  return $jsonResponse['access_token'];
}

/**
 * Mass insert or update WooCommerce products from ShopCommerce API data
 *
 * This function processes a batch of products from the ShopCommerce API and creates/updates
 * them in WooCommerce. It uses bulk operations for better performance and adds metadata
 * to identify products synced from the external provider.
 *
 * @param array $products Array of product data from ShopCommerce API
 * @param string $brand Brand name for metadata and logging
 * @return array Results summary with counts of created/updated products
 */
function shopcommerce_mass_insert_update_products($products, $brand)
{
  if (!is_array($products) || empty($products)) {
    error_log('[ShopCommerce Sync] Empty products array provided to mass insert/update function');
    return ['created' => 0, 'updated' => 0, 'errors' => 0];
  }

  // Initialize counters
  $results = [
    'created' => 0,
    'updated' => 0,
    'errors' => 0
  ];

  // Cache for existing products by SKU to minimize database queries
  $existing_products_cache = [];

  // Process products in batches for better performance
  $batch_size = 50;  // Process 50 products at a time
  $product_batches = array_chunk($products, $batch_size);

  foreach ($product_batches as $batch_index => $batch) {
    error_log(sprintf('[ShopCommerce Sync] Processing batch %d of %d for brand %s (%d products)',
      $batch_index + 1,
      count($product_batches),
      $brand,
      count($batch)));

    foreach ($batch as $product_data) {
      try {
        // Extract SKU first from multiple possible fields
        $sku = null;
        foreach (['Sku', 'SKU', 'PartNum', 'Codigo', 'ProductCode'] as $sku_field) {
          if (isset($product_data[$sku_field]) && !empty(trim($product_data[$sku_field]))) {
            $sku = trim($product_data[$sku_field]);
            break;
          }
        }

        // Generate a cache key (use product name as fallback if no SKU)
        $cache_key = !empty($sku) ? $sku : (isset($product_data['Name']) ? md5($product_data['Name']) : uniqid('no_sku_', true));

        // Check if product exists by SKU first
        $existing_product = null;
        if (!empty($sku)) {
          if (!isset($existing_products_cache[$sku])) {
            $existing_products_cache[$sku] = shopcommerce_get_product_by_sku($sku);
          }
          $existing_product = $existing_products_cache[$sku];
        }

        // If no SKU or not found, check by cache key
        if (!$existing_product && !isset($existing_products_cache[$cache_key])) {
          $existing_products_cache[$cache_key] = null;
        }

        if ($existing_product) {
          // Update existing product
          $product_id = $existing_product->get_id();

          // Get mapped data as array first
          $mapped_data = shopcommerce_map_product_data($product_data, $brand, $sku);

          // Apply the mapped data to existing product
          shopcommerce_apply_product_data($existing_product, $mapped_data, $product_data, $brand);

          $existing_product->save();
          $results['updated']++;

          error_log(sprintf('[ShopCommerce Sync] Updated product ID %d (SKU: %s)',
            $product_id,
            $sku));

          // Log individual product update (only log every 10th product to avoid spam)
          if ($results['updated'] % 10 === 0 && function_exists('shopcommerce_log_activity')) {
            shopcommerce_log_activity('product_updated', 'Batch product update progress', [
              'brand' => $brand,
              'product_id' => $product_id,
              'sku' => $sku,
              'total_updated' => $results['updated']
            ]);
          }
        } else {
          // Create new product with proper SKU conflict handling
          $wc_product = new WC_Product_Simple();

          // Get mapped data as array
          $mapped_data = shopcommerce_map_product_data($product_data, $brand, $sku);

          // Check SKU uniqueness before creating product
          $final_sku = $sku;
          if (!empty($sku)) {
            $existing_sku_id = wc_get_product_id_by_sku($sku);
            if ($existing_sku_id) {
              // SKU conflict, use empty SKU and store original in meta
              $final_sku = '';
              $mapped_data['_shopcommerce_sku'] = $sku;
              error_log('[ShopCommerce Sync] SKU conflict detected for new product, stored in meta: ' . $sku);
            }
          }

          // Apply mapped data
          shopcommerce_apply_product_data($wc_product, $mapped_data, $product_data, $brand);

          // Set the final SKU (safe now)
          if (!empty($final_sku)) {
            $wc_product->set_sku($final_sku);
          }

          $wc_product->save();
          $product_id = $wc_product->get_id();
          $results['created']++;

          error_log(sprintf('[ShopCommerce Sync] Created new product ID %d (SKU: %s)',
            $product_id,
            $final_sku ?: '(no SKU)'));

          // Log individual product creation
          if (function_exists('shopcommerce_log_activity')) {
            shopcommerce_log_activity('product_created', 'New product created', [
              'brand' => $brand,
              'product_id' => $product_id,
              'sku' => $final_sku ?: '(no SKU)',
              'product_name' => $wc_product->get_name()
            ]);
          }
        }
      } catch (Exception $e) {
        $results['errors']++;
        error_log(sprintf('[ShopCommerce Sync] Error processing product: %s',
            $e->getMessage()));

        // Log sync errors
        if (function_exists('shopcommerce_log_activity')) {
          shopcommerce_log_activity('sync_error', 'Product processing error', [
            'brand' => $brand,
            'error' => $e->getMessage(),
            'product_data' => $product_data,
            'total_errors' => $results['errors']
          ]);
        }
      }
    }
  }

  // Log final results
  error_log(sprintf('[ShopCommerce Sync] Mass insert/update completed for brand %s | Created: %d | Updated: %d | Errors: %d',
    $brand,
    $results['created'],
    $results['updated'],
    $results['errors']));

  return $results;
}

/**
 * Find existing WooCommerce product by SKU with multiple lookup methods
 *
 * @param string $sku Product SKU to search for
 * @return WC_Product|null Found product object or null
 */
function shopcommerce_get_product_by_sku($sku)
{
  if (empty($sku)) {
    return null;
  }

  // Normalize SKU (trim, uppercase for comparison)
  $normalized_sku = trim(strtoupper($sku));

  // Method 1: Direct SKU match
  $args = [
    'post_type' => 'product',
    'post_status' => 'any',
    'meta_query' => [
      [
        'key' => '_sku',
        'value' => $sku,
        'compare' => '='
      ]
    ],
    'posts_per_page' => 1
  ];

  $query = new WP_Query($args);

  if ($query->have_posts()) {
    $post = $query->posts[0];
    error_log('[ShopCommerce Sync] Found product ID: ' . $post->ID);
    return wc_get_product($post->ID);
  }

  // Method 2: Try case-insensitive match using direct DB query
  global $wpdb;
  $product_id = $wpdb->get_var($wpdb->prepare(
    "SELECT post_id FROM $wpdb->postmeta
     WHERE meta_key = '_sku' AND UPPER(TRIM(meta_value)) = %s
     LIMIT 1",
    $normalized_sku
  ));

  if ($product_id) {
    error_log('[ShopCommerce Sync] Found product ID: ' . $product_id);
    return wc_get_product($product_id);
  }

  // Method 3: Check external provider meta if available
  $product_id = $wpdb->get_var($wpdb->prepare(
    "SELECT post_id FROM $wpdb->postmeta
     WHERE meta_key = '_shopcommerce_sku' AND UPPER(TRIM(meta_value)) = %s
     LIMIT 1",
    $normalized_sku
  ));

  if ($product_id) {
    error_log('[ShopCommerce Sync] Found product ID: ' . $product_id);
    return wc_get_product($product_id);
  }

  return null;
}


/**
 * Get or create WooCommerce category by name
 *
 * @param string $category_name Category name
 * @return int|null Category ID or null on failure
 */
function shopcommerce_get_or_create_category($category_name)
{
  if (empty($category_name)) {
    return null;
  }

  // Check if category exists
  $term = get_term_by('name', $category_name, 'product_cat');

  if ($term && !is_wp_error($term)) {
    return $term->term_id;
  }

  // Create new category
  $result = wp_insert_term($category_name, 'product_cat');

  if (!is_wp_error($result)) {
    error_log(sprintf('[ShopCommerce Sync] Created new category: %s (ID: %d)',
      $category_name,
      $result['term_id']));
    return $result['term_id'];
  }

  error_log('[ShopCommerce Sync] Failed to create category: ' . $category_name . ' | Error: ' . $result->get_error_message());
  return null;
}

/**
 * Attach product image from URL
 *
 * @param string $image_url Image URL
 * @param string $product_name Product name for image title
 * @return int|null Attachment ID or null on failure
 */
function shopcommerce_attach_product_image($image_url, $product_name)
{
  if (empty($image_url) || !filter_var($image_url, FILTER_VALIDATE_URL)) {
    return null;
  }

  // Check if image already exists
  $existing_image = shopcommerce_get_image_by_url($image_url);
  if ($existing_image) {
    return $existing_image;
  }

  // Download image
  $image_data = wp_remote_get($image_url);

  if (is_wp_error($image_data)) {
    error_log('[ShopCommerce Sync] Failed to download image: ' . $image_url . ' | Error: ' . $image_data->get_error_message());
    return null;
  }

  $image_body = wp_remote_retrieve_body($image_data);
  if (empty($image_body)) {
    error_log('[ShopCommerce Sync] Empty image body for URL: ' . $image_url);
    return null;
  }

  // Get file info
  $file_info = wp_check_filetype_and_ext(basename($image_url), 'image');
  if (!$file_info['type'] || !in_array($file_info['type'], ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'])) {
    error_log('[ShopCommerce Sync] Invalid image type for URL: ' . $image_url, ' | File info: ' . print_r($file_info, true));
    return null;
  }

  // Upload image
  $upload = wp_upload_bits(basename($image_url), null, $image_body);

  if (is_wp_error($upload)) {
    error_log('[ShopCommerce Sync] Failed to upload image: ' . $image_url . ' | Error: ' . $upload->get_error_message());
    return null;
  }

  // Create attachment
  $attachment = [
    'post_mime_type' => $file_info['type'],
    'post_title' => sanitize_file_name($product_name),
    'post_content' => '',
    'post_status' => 'inherit'
  ];

  $attachment_id = wp_insert_attachment($attachment, $upload['file']);

  if (is_wp_error($attachment_id)) {
    error_log('[ShopCommerce Sync] Failed to create attachment: ' . $attachment_id->get_error_message());
    return null;
  }

  // Generate attachment metadata
  require_once (ABSPATH . 'wp-admin/includes/image.php');
  $attach_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
  wp_update_attachment_metadata($attachment_id, $attach_data);

  // Store image URL for future reference
  update_post_meta($attachment_id, '_external_image_url', $image_url);

  error_log(sprintf('[ShopCommerce Sync] Attached image %s to product %s (ID: %d)',
    $image_url,
    $product_name,
    $attachment_id));

  return $attachment_id;
}

/**
 * Get existing image attachment by URL
 *
 * @param string $image_url Image URL to search for
 * @return int|null Attachment ID or null if not found
 */
function shopcommerce_get_image_by_url($image_url)
{
  global $wpdb;

  $attachment_id = $wpdb->get_var($wpdb->prepare(
    "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_external_image_url' AND meta_value = %s",
    $image_url
  ));

  return $attachment_id ? intval($attachment_id) : null;
}

/**
 * Map ShopCommerce product data to a simple array format
 *
 * @param array $product_data Raw product data from ShopCommerce API
 * @param string $brand Brand name for metadata
 * @param string|null $sku Product SKU
 * @return array Mapped product data
 */
function shopcommerce_map_product_data($product_data, $brand, $sku = null)
{
  $mapped_data = [
    'name' => isset($product_data['Name']) ? sanitize_text_field($product_data['Name']) : '',
    'description' => isset($product_data['Description']) ? wp_kses_post($product_data['Description']) : '',
    'regular_price' => isset($product_data['precio']) && is_numeric($product_data['precio']) ? floatval($product_data['precio']) : null,
    'stock_quantity' => isset($product_data['Quantity']) && is_numeric($product_data['Quantity']) ? intval($product_data['Quantity']) : null,
    'stock_status' => isset($product_data['Quantity']) && $product_data['Quantity'] > 0 ? 'instock' : 'outofstock',
    'status' => 'publish',
    'meta_data' => [
      '_external_provider' => 'shopcommerce',
      '_external_provider_brand' => $brand,
      '_external_provider_sync_date' => current_time('mysql'),
      '_shopcommerce_sku' => $sku, // Store original SKU
    ],
    'category_name' => null,
    'image_url' => null,
  ];

  // Add additional metadata from ShopCommerce if available
  if (isset($product_data['Marks'])) {
    $mapped_data['meta_data']['_shopcommerce_marca'] = sanitize_text_field($product_data['Marks']);
  }

  if (isset($product_data['Categoria'])) {
    $mapped_data['meta_data']['_shopcommerce_categoria'] = sanitize_text_field($product_data['Categoria']);
  }

  // Handle categories
  if (isset($product_data['CategoriaDescripcion'])) {
    $mapped_data['category_name'] = sanitize_text_field($product_data['CategoriaDescripcion']);
  } elseif (isset($product_data['Categoria'])) {
    $mapped_data['category_name'] = sanitize_text_field($product_data['Categoria']);
  }

  // Handle product image
  if (isset($product_data['Imagenes']) && !empty($product_data['Imagenes'])) {
    $mapped_data['image_url'] = $product_data['Imagenes'][0];
  }

  return $mapped_data;
}

/**
 * Apply mapped product data to a WooCommerce product object
 *
 * @param WC_Product $wc_product WooCommerce product object
 * @param array $mapped_data Mapped product data
 * @param array $original_data Original product data from API
 * @param string $brand Brand name
 */
function shopcommerce_apply_product_data($wc_product, $mapped_data, $original_data, $brand)
{
  // Set basic fields
  $wc_product->set_name($mapped_data['name']);
  $wc_product->set_description($mapped_data['description']);
  $wc_product->set_status($mapped_data['status']);
  $wc_product->set_stock_status($mapped_data['stock_status']);

  // Set price if available
  if ($mapped_data['regular_price'] !== null) {
    $wc_product->set_regular_price($mapped_data['regular_price']);
    $wc_product->set_price($mapped_data['regular_price']);
  }

  // Set stock quantity if available
  if ($mapped_data['stock_quantity'] !== null) {
    $wc_product->set_stock_quantity($mapped_data['stock_quantity']);
  }

  // Apply metadata
  foreach ($mapped_data['meta_data'] as $key => $value) {
    if ($value !== null) {
      $wc_product->update_meta_data($key, $value);
    }
  }

  // Handle categories
  if ($mapped_data['category_name']) {
    $category_id = shopcommerce_get_or_create_category($mapped_data['category_name']);
    if ($category_id) {
      $wc_product->set_category_ids([$category_id]);
    }
  }

  // Handle image updates with timestamp checking (only for existing products)
  if ($mapped_data['image_url'] && method_exists($wc_product, 'get_id')) {
    $product_id = $wc_product->get_id();
    if ($product_id) {
      $current_image_id = $wc_product->get_image_id();
      $last_image_update = $wc_product->get_meta('_external_image_last_updated');

      // Only update image if URL has changed or hasn't been updated in 24 hours
      $update_image = false;
      if (!$current_image_id) {
        $update_image = true; // No image set
      } else {
        $current_image_url = get_post_meta($current_image_id, '_external_image_url', true);
        if ($current_image_url !== $mapped_data['image_url']) {
          $update_image = true; // URL has changed
        } elseif (!$last_image_update || (time() - strtotime($last_image_update)) > 24 * 60 * 60) {
          $update_image = true; // Haven't updated in 24 hours
        }
      }

      if ($update_image) {
        $new_image_id = shopcommerce_attach_product_image($mapped_data['image_url'], $wc_product->get_name());
        if ($new_image_id) {
          $wc_product->set_image_id($new_image_id);
          $wc_product->update_meta_data('_external_image_last_updated', current_time('mysql'));
        }
      }
    }
  }
}
