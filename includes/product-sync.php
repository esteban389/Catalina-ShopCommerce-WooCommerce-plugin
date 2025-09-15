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
    'X-MARK' => $brand,
  ];
  if (!empty($categories)) {
    // Convert categories to a string of comma separated integers
    $headers['Categorias'] = implode(',', $categories);
  }

  $args = [
    'headers' => $headers,
    'timeout' => $timeout,
    'body' => $body,
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

  // TODO: Map $batch to WooCommerce products (create/update). For now, just log SKUs if present.
  $skus = [];
  foreach ($batch as $row) {
    if (isset($row['Sku'])) {
      $skus[] = $row['Sku'];
    }
  }
  if (!empty($skus)) {
    error_log('[ShopCommerce Sync] Example SKUs processed: ' . implode(',', array_slice($skus, 0, 20)) . (count($skus) > 20 ? '...' : ''));
  }

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
function shopcommerce_mass_insert_update_products($products, $brand) {
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
  $batch_size = 50; // Process 50 products at a time
  $product_batches = array_chunk($products, $batch_size);

  foreach ($product_batches as $batch_index => $batch) {
    error_log(sprintf('[ShopCommerce Sync] Processing batch %d of %d for brand %s (%d products)',
      $batch_index + 1,
      count($product_batches),
      $brand,
      count($batch)
    ));

    foreach ($batch as $product_data) {
      try {
        // Map ShopCommerce product data to WooCommerce format
        $wc_product = shopcommerce_map_to_woocommerce_product($product_data, $brand);

        if (!$wc_product) {
          $results['errors']++;
          error_log('[ShopCommerce Sync] Failed to map product data: ' . json_encode($product_data));
          continue;
        }

        // Check if product exists by SKU
        $sku = $wc_product->get_sku();
        if (!isset($existing_products_cache[$sku])) {
          $existing_products_cache[$sku] = shopcommerce_get_product_by_sku($sku);
        }

        $existing_product = $existing_products_cache[$sku];

        if ($existing_product) {
          // Update existing product
          $wc_product->set_id($existing_product->get_id());
          $wc_product->save();
          $results['updated']++;

          error_log(sprintf('[ShopCommerce Sync] Updated product ID %d (SKU: %s)',
            $existing_product->get_id(),
            $sku
          ));

          // Log individual product update (only log every 10th product to avoid spam)
          if ($results['updated'] % 10 === 0 && function_exists('shopcommerce_log_activity')) {
            shopcommerce_log_activity('product_updated', 'Batch product update progress', [
              'brand' => $brand,
              'product_id' => $existing_product->get_id(),
              'sku' => $sku,
              'total_updated' => $results['updated']
            ]);
          }
        } else {
          // Create new product
          $wc_product->save();
          $product_id = $wc_product->get_id();
          $results['created']++;

          error_log(sprintf('[ShopCommerce Sync] Created new product ID %d (SKU: %s)',
            $product_id,
            $sku
          ));

          // Log individual product creation
          if (function_exists('shopcommerce_log_activity')) {
            shopcommerce_log_activity('product_created', 'New product created', [
              'brand' => $brand,
              'product_id' => $product_id,
              'sku' => $sku,
              'product_name' => $wc_product->get_name()
            ]);
          }
        }

      } catch (Exception $e) {
        $results['errors']++;
        error_log(sprintf('[ShopCommerce Sync] Error processing product: %s | Data: %s',
          $e->getMessage(),
          json_encode($product_data)
        ));

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
    $results['errors']
  ));

  return $results;
}

/**
 * Find existing WooCommerce product by SKU
 *
 * @param string $sku Product SKU to search for
 * @return WC_Product|null Found product object or null
 */
function shopcommerce_get_product_by_sku($sku) {
  if (empty($sku)) {
    return null;
  }

  // Use WooCommerce's built-in product query
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
    return wc_get_product($post->ID);
  }

  return null;
}

/**
 * Map ShopCommerce product data to WooCommerce product format
 *
 * This function transforms the product data from the ShopCommerce API into
 * a format compatible with WooCommerce products. It handles field mapping,
 * category assignment, and metadata setup.
 *
 * @param array $product_data Raw product data from ShopCommerce API
 * @param string $brand Brand name for metadata
 * @return WC_Product|null Mapped WooCommerce product or null on failure
 */
function shopcommerce_map_to_woocommerce_product($product_data, $brand) {
  if (!is_array($product_data) || empty($product_data)) {
    return null;
  }

  // Create new WooCommerce simple product object
  $wc_product = new WC_Product_Simple();

  // Map basic product fields
  $wc_product->set_sku(isset($product_data['Sku']) ? sanitize_text_field($product_data['Sku']) : '');
  $wc_product->set_name(isset($product_data['Name']) ? sanitize_text_field($product_data['Name']) : '');
  $wc_product->set_description(isset($product_data['Description']) ? wp_kses_post($product_data['Description']) : '');

  // Set price if available
  if (isset($product_data['precio']) && is_numeric($product_data['precio'])) {
    $wc_product->set_regular_price(floatval($product_data['precio']));
    $wc_product->set_price(floatval($product_data['precio']));
  }

  // Set stock status (default to instock if not specified)
  $wc_product->set_stock_status(isset($product_data['Quantity']) && $product_data['Quantity'] > 0 ? 'instock' : 'outofstock');

  // Set stock quantity if available
  if (isset($product_data['Quantity']) && is_numeric($product_data['Quantity'])) {
    $wc_product->set_stock_quantity(intval($product_data['Quantity']));
  }

  // Product type is already set as simple by using WC_Product_Simple constructor

  // Set product status to publish
  $wc_product->set_status('publish');

  // Add external provider metadata for identification
  $wc_product->update_meta_data('_external_provider', 'shopcommerce');
  $wc_product->update_meta_data('_external_provider_brand', $brand);
  $wc_product->update_meta_data('_external_provider_sync_date', current_time('mysql'));

  // Add additional metadata from ShopCommerce if available
  if (isset($product_data['Marks'])) {
    $wc_product->update_meta_data('_shopcommerce_marca', sanitize_text_field($product_data['Marks']));
  }

  if (isset($product_data['Categoria'])) {
    $wc_product->update_meta_data('_shopcommerce_categoria', sanitize_text_field($product_data['Categoria']));
  }

  // Handle categories - map to WooCommerce categories
  if (isset($product_data['Categoria']) || isset($product_data['CategoriaDescripcion'])) {
    $category_name = isset($product_data['CategoriaDescripcion']) ?
      sanitize_text_field($product_data['CategoriaDescripcion']) :
      sanitize_text_field($product_data['Categoria']);

    if (!empty($category_name)) {
      $category_id = shopcommerce_get_or_create_category($category_name);
      if ($category_id) {
        $wc_product->set_category_ids([$category_id]);
      }
    }
  }

  // Handle product image if available
  if (isset($product_data['Imagenes']) && !empty($product_data['Imagenes'])) {
    $image_id = shopcommerce_attach_product_image($product_data['Imagenes'][0], $wc_product->get_name());
    if ($image_id) {
      $wc_product->set_image_id($image_id);
    }
  }

  return $wc_product;
}

/**
 * Get or create WooCommerce category by name
 *
 * @param string $category_name Category name
 * @return int|null Category ID or null on failure
 */
function shopcommerce_get_or_create_category($category_name) {
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
      $result['term_id']
    ));
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
function shopcommerce_attach_product_image($image_url, $product_name) {
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
    error_log('[ShopCommerce Sync] Invalid image type for URL: ' . $image_url);
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
    'post_title'     => sanitize_file_name($product_name),
    'post_content'   => '',
    'post_status'    => 'inherit'
  ];

  $attachment_id = wp_insert_attachment($attachment, $upload['file']);

  if (is_wp_error($attachment_id)) {
    error_log('[ShopCommerce Sync] Failed to create attachment: ' . $attachment_id->get_error_message());
    return null;
  }

  // Generate attachment metadata
  require_once(ABSPATH . 'wp-admin/includes/image.php');
  $attach_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
  wp_update_attachment_metadata($attachment_id, $attach_data);

  // Store image URL for future reference
  update_post_meta($attachment_id, '_external_image_url', $image_url);

  error_log(sprintf('[ShopCommerce Sync] Attached image %s to product %s (ID: %d)',
    $image_url,
    $product_name,
    $attachment_id
  ));

  return $attachment_id;
}

/**
 * Get existing image attachment by URL
 *
 * @param string $image_url Image URL to search for
 * @return int|null Attachment ID or null if not found
 */
function shopcommerce_get_image_by_url($image_url) {
  global $wpdb;

  $attachment_id = $wpdb->get_var($wpdb->prepare(
    "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_external_image_url' AND meta_value = %s",
    $image_url
  ));

  return $attachment_id ? intval($attachment_id) : null;
}
