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
    error_log('[ShopCommerce Sync] Error retrieving catalog for brand ' . $brand . ': ' . $response->get_error_message());
    return;
  }

  $body_str = wp_remote_retrieve_body($response);
  $json = json_decode($body_str, true);

  if (!is_array($json)) {
    error_log('[ShopCommerce Sync] Invalid JSON response for brand ' . $brand . '. Raw: ' . substr($body_str, 0, 500));
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
  error_log('[ShopCommerce Sync] Current job index: ' . get_option(shopcommerce_job_index_option_key()));
  error_log('[ShopCommerce Sync] Current job: ' . print_r($job, true));
  $jobs = get_option(shopcommerce_jobs_option_key());
  error_log('[ShopCommerce Sync] jobs: ' . print_r($jobs, true));
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
