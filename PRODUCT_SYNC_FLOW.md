# ShopCommerce Product Sync Flow Documentation

## Overview

This document provides a detailed explanation of the ShopCommerce product synchronization flow, from API communication to WooCommerce product creation. The sync system is designed to efficiently synchronize products from the ShopCommerce API with WooCommerce stores while handling large catalogs, preventing duplicates, and ensuring data integrity.

## System Architecture

### Core Components

1. **ShopCommerce_Sync** - Main orchestrator that coordinates the entire sync process
2. **ShopCommerce_API** - Handles all communication with the ShopCommerce API
3. **ShopCommerce_Product** - Manages WooCommerce product creation and updates
4. **ShopCommerce_Cron** - Manages cron scheduling and job queue processing
5. **ShopCommerce_Jobs_Store** - Centralized store for brand/category configurations and job management
6. **ShopCommerce_Logger** - Provides logging and activity tracking
7. **ShopCommerce_Helpers** - Utility functions for data processing and WooCommerce operations

### Initialization Flow

```php
// index.php:46 - shopcommerce_sync_init()
function shopcommerce_sync_init() {
    // 1. Initialize logger first (required by all other components)
    $logger = new ShopCommerce_Logger();

    // 2. Initialize jobs store for configuration management
    $jobs_store = new ShopCommerce_Jobs_Store($logger);

    // 3. Initialize configuration manager
    $config_manager = new ShopCommerce_Config($logger);

    // 4. Initialize API client
    $api_client = new ShopCommerce_API($logger);

    // 5. Initialize helpers for utility functions
    $helpers = new ShopCommerce_Helpers($logger);

    // 6. Initialize product handler
    $product_handler = new ShopCommerce_Product($logger, $helpers);

    // 7. Initialize cron scheduler with jobs store
    $cron_scheduler = new ShopCommerce_Cron($logger, $jobs_store);

    // 8. Initialize main sync orchestrator
    $sync_handler = new ShopCommerce_Sync($logger, $api_client, $product_handler, $cron_scheduler, $jobs_store);

    // 9. Make instances globally available
    $GLOBALS['shopcommerce_*'] = $instances;
}
```

## Main Sync Flow

### 1. Cron Trigger

The sync process is initiated by WordPress cron jobs:

```php
// class-shopcommerce-cron.php:53 - Main cron hook registration
add_action(self::HOOK_NAME, [$this, 'execute_sync_hook']);

// class-shopcommerce-cron.php:215 - execute_sync_hook()
public function execute_sync_hook() {
    $this->logger->info('Executing sync hook');

    // Get the global sync handler instance
    $sync_handler = $GLOBALS['shopcommerce_sync'];
    if ($sync_handler) {
        // Execute the main sync process
        $results = $sync_handler->execute_sync();

        // Log results and handle next job scheduling
        $this->handle_sync_results($results);
    }
}
```

### 2. Sync Orchestration

```php
// class-shopcommerce-sync.php:66 - execute_sync()
public function execute_sync() {
    try {
        // Step 1: Get next job from queue
        $job = $this->cron_scheduler->get_next_job();
        if (!$job) {
            return ['success' => false, 'error' => 'No jobs available'];
        }

        // Step 2: Execute sync for the specific job
        $results = $this->execute_sync_for_job($job);

        // Step 3: Log completion and return results
        $this->logger->info('Scheduled sync completed', [
            'brand' => $job['brand'],
            'results' => $results
        ]);

        return [
            'success' => true,
            'job' => $job,
            'results' => $results
        ];

    } catch (Exception $e) {
        // Error handling and logging
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
```

### 3. Job-Specific Sync Execution

```php
// class-shopcommerce-sync.php:110 - execute_sync_for_job()
public function execute_sync_for_job($job) {
    $brand = $job['brand'];
    $categories = $job['categories'];

    try {
        // Step 1: Get catalog from ShopCommerce API
        $catalog = $this->api_client->get_catalog($brand, $categories);
        if (!$catalog) {
            throw new Exception('Failed to retrieve catalog from API');
        }

        // Step 2: Handle empty catalog case
        if (empty($catalog)) {
            return [
                'success' => true,
                'catalog_count' => 0,
                'processed_count' => 0,
                'created' => 0,
                'updated' => 0,
                'errors' => 0
            ];
        }

        // Step 3: Process catalog in batches
        $results = $this->process_catalog($catalog, $brand);

        // Step 4: Log sync completion
        $this->logger->log_activity('sync_complete', 'Sync completed for brand: ' . $brand, [
            'brand' => $brand,
            'products_processed' => $results['processed_count'],
            'created' => $results['created'],
            'updated' => $results['updated']
        ]);

        return array_merge($results, ['success' => true]);

    } catch (Exception $e) {
        // Error handling and activity logging
        $this->logger->log_activity('sync_error', 'Sync failed for brand: ' . $brand, [
            'brand' => $brand,
            'error' => $e->getMessage()
        ]);

        return ['success' => false, 'error' => $e->getMessage()];
    }
}
```

## API Communication Flow

### 1. Authentication

```php
// class-shopcommerce-api.php:63 - get_token()
public function get_token() {
    // Check cached token first
    if ($this->cached_token && $this->token_expiry && time() < $this->token_expiry) {
        return $this->cached_token;
    }

    // Request new token using OAuth2 password grant
    $token_url = trailingslashit(self::BASE_URL) . self::TOKEN_ENDPOINT;

    $request_args = [
        'body' => [
            'password' => $this->password,     // 'Mo3rdoadzy1M'
            'username' => $this->username,     // 'pruebas@hekalsoluciones.com'
            'grant_type' => 'password'
        ],
        'timeout' => self::TIMEOUT,            // 840 seconds (14 minutes)
    ];

    // Send POST request to token endpoint
    $response = wp_remote_post($token_url, $request_args);

    if (is_wp_error($response)) {
        $this->logger->error('Failed to retrieve API token', [
            'error' => $response->get_error_message()
        ]);
        return null;
    }

    // Parse and cache the token
    $response_data = json_decode(wp_remote_retrieve_body($response), true);
    if (isset($response_data['access_token'])) {
        $this->cached_token = $response_data['access_token'];
        $this->token_expiry = time() + ($response_data['expires_in'] - 300); // 5min buffer
        return $this->cached_token;
    }

    return null;
}
```

### 2. Catalog Retrieval

```php
// class-shopcommerce-api.php:180 - get_catalog()
public function get_catalog($brand, $categories = []) {
    // Get authentication token
    $token = $this->get_token();
    if (!$token) {
        throw new Exception('Authentication failed');
    }

    // Build API URL
    $catalog_url = trailingslashit(self::BASE_URL) . self::CATALOG_ENDPOINT;

    // Prepare request parameters
    $params = [
        'Marca' => $brand,
        'Categoria' => !empty($categories) ? implode(',', $categories) : '0'
    ];

    $request_args = [
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode($params),
        'timeout' => self::TIMEOUT,
        'method' => 'POST'
    ];

    // Send request to catalog endpoint
    $response = wp_remote_post($catalog_url, $request_args);

    if (is_wp_error($response)) {
        throw new Exception('API request failed: ' . $response->get_error_message());
    }

    // Parse and validate response
    $response_body = wp_remote_retrieve_body($response);
    $catalog_data = json_decode($response_body, true);

    if (!is_array($catalog_data)) {
        throw new Exception('Invalid catalog response format');
    }

    // Transform XML-style attributes to consistent format
    return $this->transform_catalog_data($catalog_data);
}
```

### 3. Data Transformation

```php
// class-shopcommerce-api.php:350 - transform_catalog_data()
private function transform_catalog_data($catalog_data) {
    $transformed = [];

    foreach ($catalog_data as $item) {
        // Handle XML-style attributes (e.g., <product attr="value">)
        $product = [
            'Sku' => $item['@attributes']['Sku'] ?? $item['Sku'] ?? '',
            'Name' => $item['@attributes']['Name'] ?? $item['Name'] ?? '',
            'Description' => $item['@attributes']['Description'] ?? $item['Description'] ?? '',
            'Price' => $item['@attributes']['Price'] ?? $item['Price'] ?? 0,
            'Stock' => $item['@attributes']['Stock'] ?? $item['Stock'] ?? 0,
            'ImageUrl' => $item['@attributes']['ImageUrl'] ?? $item['ImageUrl'] ?? '',
            'Category' => $item['@attributes']['Category'] ?? $item['Category'] ?? '',
            'Brand' => $item['@attributes']['Brand'] ?? $item['Brand'] ?? '',
            'Status' => $item['@attributes']['Status'] ?? $item['Status'] ?? 'A',
        ];

        // Sanitize and validate required fields
        if (!empty($product['Sku']) && !empty($product['Name'])) {
            $transformed[] = $product;
        }
    }

    return $transformed;
}
```

## Product Processing Flow

### 1. Batch Processing

```php
// class-shopcommerce-sync.php:193 - process_catalog()
private function process_catalog($catalog, $brand, $batch_size = self::DEFAULT_BATCH_SIZE) {
    $overall_results = [
        'processed_count' => 0,
        'created' => 0,
        'updated' => 0,
        'errors' => 0,
        'skipped' => 0,
        'batches_processed' => 0,
    ];

    // Split catalog into manageable batches
    $product_batches = array_chunk($catalog, $batch_size);

    foreach ($product_batches as $batch_index => $batch) {
        try {
            // Process each batch through product handler
            $batch_results = $this->product_handler->process_batch($batch, $brand);

            // Aggregate results
            $overall_results['processed_count'] += $batch_results['total'];
            $overall_results['created'] += $batch_results['created'];
            $overall_results['updated'] += $batch_results['updated'];
            $overall_results['errors'] += $batch_results['errors'];
            $overall_results['skipped'] += $batch_results['skipped'];
            $overall_results['batches_processed']++;

            // Small delay between batches to prevent server overload
            if ($batch_index < count($product_batches) - 1) {
                sleep(1);
            }

        } catch (Exception $e) {
            $this->logger->error('Error processing batch', [
                'batch_index' => $batch_index + 1,
                'error' => $e->getMessage()
            ]);
            $overall_results['errors'] += count($batch);
        }
    }

    return $overall_results;
}
```

### 2. Individual Product Processing

```php
// class-shopcommerce-product.php:42 - process_product()
public function process_product($product_data, $brand) {
    try {
        // Step 1: Sanitize input data
        $sanitized_data = $this->helpers->sanitize_product_data($product_data);
        if (empty($sanitized_data)) {
            throw new Exception('Invalid product data');
        }

        // Step 2: Extract SKU for duplicate checking
        $sku = $sanitized_data['Sku'];
        $cache_key = $this->helpers->generate_cache_key($sanitized_data, $sku);

        // Step 3: Check for existing products (duplicate prevention)
        $duplicate_check = $this->check_for_duplicates($sku);

        if ($duplicate_check['found']) {
            // UPDATE FLOW: Existing product found
            $existing_product = wc_get_product($duplicate_check['product_id']);
            if ($existing_product) {
                $results = $this->update_product($existing_product, $sanitized_data, $brand);
                $results['action'] = 'updated';
            } else {
                throw new Exception('Found duplicate reference but product object is invalid');
            }
        } else {
            // CREATE FLOW: New product
            $results = $this->create_product_safely($sanitized_data, $brand);
            $results['action'] = 'created';
        }

        $results['success'] = true;
        return $results;

    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'action' => 'error'
        ];
    }
}
```

### 3. Duplicate Detection

```php
// class-shopcommerce-product.php:320 - check_for_duplicates()
private function check_for_duplicates($sku) {
    global $wpdb;

    // Method 1: Check by SKU (most reliable)
    $product_id = wc_get_product_id_by_sku($sku);
    if ($product_id > 0) {
        return [
            'found' => true,
            'product_id' => $product_id,
            'method' => 'sku_lookup',
            'sku' => $sku
        ];
    }

    // Method 2: Check by post meta (fallback)
    $meta_product_id = $wpdb->get_var($wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta}
        WHERE meta_key = '_sku' AND meta_value = %s
        LIMIT 1",
        $sku
    ));

    if ($meta_product_id) {
        return [
            'found' => true,
            'product_id' => intval($meta_product_id),
            'method' => 'meta_lookup',
            'sku' => $sku
        ];
    }

    // Method 3: Check by title and brand (last resort)
    $title_product_id = $wpdb->get_var($wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} p
        JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE p.post_title = %s
        AND p.post_type = 'product'
        AND pm.meta_key = '_shopcommerce_brand'
        AND pm.meta_value = %s
        LIMIT 1",
        $product_data['Name'] ?? '',
        $brand
    ));

    if ($title_product_id) {
        return [
            'found' => true,
            'product_id' => intval($title_product_id),
            'method' => 'title_brand_lookup',
            'sku' => $sku
        ];
    }

    return ['found' => false, 'method' => 'none'];
}
```

### 4. Product Creation

```php
// class-shopcommerce-product.php:150 - create_product_safely()
private function create_product_safely($product_data, $brand) {
    try {
        // Step 1: Final duplicate check before creation
        $sku = $product_data['Sku'];
        $final_check = $this->check_for_duplicates($sku);
        if ($final_check['found']) {
            throw new Exception('Duplicate detected at creation stage');
        }

        // Step 2: Create WooCommerce product object
        $product = new WC_Product_Simple();

        // Step 3: Set basic product data
        $product->set_name($product_data['Name']);
        $product->set_description($product_data['Description']);
        $product->set_short_description($this->helpers->generate_short_description($product_data['Description']));
        $product->set_status('publish');
        $product->set_catalog_visibility('visible');
        $product->set_sku($sku);

        // Step 4: Set pricing
        $price = floatval($product_data['Price']);
        if ($price > 0) {
            $product->set_regular_price($price);
            $product->set_price($price);
        }

        // Step 5: Set stock management
        $stock_quantity = intval($product_data['Stock']);
        $product->set_manage_stock(true);
        $product->set_stock_quantity($stock_quantity);
        $product->set_stock_status($stock_quantity > 0 ? 'instock' : 'outofstock');

        // Step 6: Set product categories
        $category_ids = $this->helpers->get_or_create_category_ids($product_data['Category'], $brand);
        if (!empty($category_ids)) {
            $product->set_category_ids($category_ids);
        }

        // Step 7: Set brand metadata
        $product->update_meta_data('_shopcommerce_brand', $brand);
        $product->update_meta_data('_shopcommerce_sync_date', current_time('mysql'));
        $product->update_meta_data('_shopcommerce_category', $product_data['Category']);

        // Step 8: Save product with transaction safety
        $product_id = $product->save();

        if (!$product_id || is_wp_error($product_id)) {
            throw new Exception('Failed to save product');
        }

        // Step 9: Handle image download and attachment
        if (!empty($product_data['ImageUrl'])) {
            $this->helpers->handle_product_image($product_id, $product_data['ImageUrl']);
        }

        return [
            'success' => true,
            'product_id' => $product_id,
            'action' => 'created'
        ];

    } catch (Exception $e) {
        $this->logger->error('Product creation failed', [
            'error' => $e->getMessage(),
            'sku' => $product_data['Sku'] ?? 'unknown'
        ]);

        return [
            'success' => false,
            'error' => $e->getMessage(),
            'action' => 'creation_failed'
        ];
    }
}
```

### 5. Product Updates

```php
// class-shopcommerce-product.php:250 - update_product()
private function update_product($existing_product, $product_data, $brand) {
    try {
        $product_id = $existing_product->get_id();
        $sku = $product_data['Sku'];

        // Step 1: Check if update is needed (avoid unnecessary writes)
        if (!$this->helpers->needs_update($existing_product, $product_data)) {
            return [
                'success' => true,
                'product_id' => $product_id,
                'action' => 'skipped',
                'reason' => 'no_changes'
            ];
        }

        // Step 2: Update basic information
        $existing_product->set_name($product_data['Name']);
        $existing_product->set_description($product_data['Description']);
        $existing_product->set_short_description($this->helpers->generate_short_description($product_data['Description']));

        // Step 3: Update pricing
        $new_price = floatval($product_data['Price']);
        if ($new_price > 0 && $new_price != $existing_product->get_regular_price()) {
            $existing_product->set_regular_price($new_price);
            $existing_product->set_price($new_price);
        }

        // Step 4: Update stock information
        $new_stock = intval($product_data['Stock']);
        if ($new_stock != $existing_product->get_stock_quantity()) {
            $existing_product->set_stock_quantity($new_stock);
            $existing_product->set_stock_status($new_stock > 0 ? 'instock' : 'outofstock');
        }

        // Step 5: Update categories if needed
        $category_ids = $this->helpers->get_or_create_category_ids($product_data['Category'], $brand);
        $existing_categories = $existing_product->get_category_ids();
        if (array_diff($category_ids, $existing_categories) || array_diff($existing_categories, $category_ids)) {
            $existing_product->set_category_ids($category_ids);
        }

        // Step 6: Update metadata
        $existing_product->update_meta_data('_shopcommerce_sync_date', current_time('mysql'));
        $existing_product->update_meta_data('_shopcommerce_category', $product_data['Category']);

        // Step 7: Handle image updates (if configured)
        if (!empty($product_data['ImageUrl']) && $this->helpers->should_update_image($existing_product, $product_data['ImageUrl'])) {
            $this->helpers->handle_product_image($product_id, $product_data['ImageUrl']);
        }

        // Step 8: Save the updated product
        $result = $existing_product->save();

        if (!$result || is_wp_error($result)) {
            throw new Exception('Failed to save product update');
        }

        return [
            'success' => true,
            'product_id' => $product_id,
            'action' => 'updated'
        ];

    } catch (Exception $e) {
        $this->logger->error('Product update failed', [
            'error' => $e->getMessage(),
            'product_id' => $existing_product->get_id(),
            'sku' => $product_data['Sku'] ?? 'unknown'
        ]);

        return [
            'success' => false,
            'error' => $e->getMessage(),
            'action' => 'update_failed'
        ];
    }
}
```

## Job Management and Scheduling

### 1. Job Queue System

```php
// class-shopcommerce-jobs-store.php:120 - get_sync_jobs()
public function get_sync_jobs() {
    // Get all active brands with their category configurations
    $brands = $this->get_active_brands();
    $jobs = [];

    foreach ($brands as $brand) {
        $job = [
            'brand_id' => $brand->id,
            'brand' => $brand->name,
            'categories' => [],
            'priority' => 1,
            'last_run' => null,
            'next_run' => current_time('timestamp')
        ];

        // Get categories for this brand
        if ($this->brand_has_all_categories($brand->id)) {
            // Brand syncs all categories
            $job['categories'] = ['all'];
        } else {
            // Brand syncs specific categories
            $brand_categories = $this->get_brand_categories($brand->id);
            $job['categories'] = wp_list_pluck($brand_categories, 'id');
        }

        $jobs[] = $job;
    }

    return $jobs;
}
```

### 2. Cron Scheduling

```php
// class-shopcommerce-cron.php:59 - activate()
public function activate() {
    try {
        // Step 1: Ensure custom cron schedules are registered
        $this->force_register_schedules();

        // Step 2: Initialize jobs from configuration
        $this->initialize_jobs();

        // Step 3: Clear any existing schedules (clean state)
        $this->clear_cron_event();

        // Step 4: Schedule the main sync hook
        $scheduled = $this->schedule_cron_event(self::DEFAULT_INTERVAL);

        // Step 5: Verify the schedule was created
        $next_run = wp_next_scheduled(self::HOOK_NAME);
        if (!$next_run) {
            throw new Exception('Cron event was not actually scheduled');
        }

        $this->logger->info('Activation completed successfully', [
            'next_run' => date('Y-m-d H:i:s', $next_run)
        ]);

    } catch (Exception $e) {
        $this->logger->error('Activation failed', ['error' => $e->getMessage()]);
        update_option('shopcommerce_activation_error', $e->getMessage(), false);
    }
}
```

### 3. Custom Cron Schedules

```php
// class-shopcommerce-cron.php:125 - register_cron_schedules()
public function register_cron_schedules($schedules) {
    // Add custom schedule intervals for more frequent syncs
    $schedules['shopcommerce_every_minute'] = [
        'interval' => 60,          // 1 minute
        'display' => __('Every Minute (ShopCommerce)')
    ];

    $schedules['shopcommerce_15_minutes'] = [
        'interval' => 900,         // 15 minutes
        'display' => __('Every 15 Minutes (ShopCommerce)')
    ];

    $schedules['shopcommerce_30_minutes'] = [
        'interval' => 1800,        // 30 minutes
        'display' => __('Every 30 Minutes (ShopCommerce)')
    ];

    return $schedules;
}
```

## Error Handling and Recovery

### 1. Comprehensive Error Handling

```php
// Example from class-shopcommerce-sync.php:91 - execute_sync()
try {
    $job = $this->cron_scheduler->get_next_job();
    if (!$job) {
        $this->logger->warning('No jobs available for sync');
        return ['success' => false, 'error' => 'No jobs available'];
    }

    $results = $this->execute_sync_for_job($job);

    $this->logger->info('Scheduled sync completed', [
        'brand' => $job['brand'],
        'results' => $results
    ]);

    return [
        'success' => true,
        'job' => $job,
        'results' => $results
    ];

} catch (Exception $e) {
    $this->logger->error('Error in scheduled sync execution', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);

    return [
        'success' => false,
        'error' => $e->getMessage()
    ];
}
```

### 2. Retry Mechanisms

The system implements several retry mechanisms:

- **API Token Refresh**: Automatically requests new tokens when expired
- **Batch Retry**: Failed batches are logged and can be retried manually
- **Connection Recovery**: Temporary network failures trigger retry with exponential backoff
- **Database Transaction Safety**: Product creation/updates use WordPress transaction safety

### 3. Graceful Degradation

```php
// class-shopcommerce-sync.php:127 - Empty catalog handling
if (!is_array($catalog) || empty($catalog)) {
    $this->logger->info('Empty catalog received', [
        'brand' => $brand,
        'categories' => $categories
    ]);
    return [
        'success' => true,
        'catalog_count' => 0,
        'processed_count' => 0,
        'created' => 0,
        'updated' => 0,
        'errors' => 0
    ];
}
```

## Logging and Monitoring

### 1. Multi-Level Logging

```php
// class-shopcommerce-logger.php provides multiple log levels:
// - debug() for detailed troubleshooting
// - info() for general operational information
// - warning() for non-critical issues
// - error() for critical failures
// - log_activity() for user-facing activity tracking

// Example: Product processing logging
$this->logger->debug('Processing product', [
    'sku' => $sku,
    'name' => $sanitized_data['Name'],
    'brand' => $brand
]);

$this->logger->log_activity('sync_complete', 'Sync completed for brand: ' . $brand, [
    'brand' => $brand,
    'products_processed' => $results['processed_count'],
    'created' => $results['created'],
    'updated' => $results['updated']
]);
```

### 2. Activity Tracking

```php
// class-shopcommerce-logger.php:200 - log_activity()
public function log_activity($action, $message, $context = []) {
    $activity = [
        'timestamp' => current_time('mysql'),
        'action' => $action,
        'message' => $message,
        'context' => $context,
        'user_id' => get_current_user_id(),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];

    // Store in database for admin interface
    $this->add_activity_record($activity);

    // Log to file for detailed debugging
    $this->info('Activity: ' . $message, $context);
}
```

## Performance Optimizations

### 1. Batch Processing

- Products are processed in batches of 100 (configurable)
- 1-second delay between batches prevents server overload
- Memory usage is monitored and optimized

### 2. Caching Strategies

```php
// API token caching
if ($this->cached_token && $this->token_expiry && time() < $this->token_expiry) {
    return $this->cached_token;
}

// Product lookup caching
$product_id = wp_cache_get($cache_key, 'shopcommerce_product_lookup');
if ($product_id === false) {
    $product_id = wc_get_product_id_by_sku($sku);
    wp_cache_set($cache_key, $product_id, 'shopcommerce_product_lookup', 3600);
}
```

### 3. Database Optimization

- Efficient queries use proper indexing
- Bulk operations minimize database writes
- Transaction safety prevents data corruption

## Manual Sync Capabilities

### 1. Full Sync

```php
// class-shopcommerce-sync.php:465 - run_full_sync()
public function run_full_sync($batch_size = self::DEFAULT_BATCH_SIZE) {
    $this->logger->info('Starting full sync for all brands');

    $jobs = $this->cron_scheduler->get_jobs();
    $overall_results = [
        'total_jobs' => count($jobs),
        'jobs_processed' => 0,
        'total_products' => 0,
        'created' => 0,
        'updated' => 0,
        'errors' => 0,
        'job_results' => []
    ];

    foreach ($jobs as $job) {
        try {
            $job_result = $this->execute_sync_for_job($job);

            // Aggregate results
            $overall_results['jobs_processed']++;
            $overall_results['total_products'] += $job_result['processed_count'] ?? 0;
            $overall_results['created'] += $job_result['created'] ?? 0;
            $overall_results['updated'] += $job_result['updated'] ?? 0;
            $overall_results['errors'] += $job_result['errors'] ?? 0;
            $overall_results['job_results'][] = $job_result;

        } catch (Exception $e) {
            $this->logger->error('Error processing job in full sync', [
                'brand' => $job['brand'],
                'error' => $e->getMessage()
            ]);
            $overall_results['errors']++;
        }
    }

    return ['success' => true, 'results' => $overall_results];
}
```

### 2. Brand-Specific Sync

```php
// Available through admin interface
// Allows syncing specific brands on demand
// Uses the same execute_sync_for_job() method as cron jobs
```

## Security Considerations

### 1. API Security

- OAuth2 password grant authentication
- Token expiration handling with 5-minute buffer
- HTTPS-only communication
- Secure credential storage (should be moved to environment variables)

### 2. WordPress Security

- Capability checks for admin functions
- Nonce verification for all AJAX requests
- Input sanitization and validation
- SQL injection prevention using $wpdb->prepare()

### 3. Data Validation

```php
// class-shopcommerce-helpers.php - sanitize_product_data()
public function sanitize_product_data($product_data) {
    $sanitized = [];

    // Required fields with strict validation
    $sanitized['Sku'] = sanitize_text_field($product_data['Sku'] ?? '');
    $sanitized['Name'] = sanitize_text_field($product_data['Name'] ?? '');

    // Optional fields with default values
    $sanitized['Description'] = wp_kses_post($product_data['Description'] ?? '');
    $sanitized['Price'] = floatval($product_data['Price'] ?? 0);
    $sanitized['Stock'] = intval($product_data['Stock'] ?? 0);
    $sanitized['ImageUrl'] = esc_url_raw($product_data['ImageUrl'] ?? '');

    // Validate required fields
    if (empty($sanitized['Sku']) || empty($sanitized['Name'])) {
        return null;
    }

    return $sanitized;
}
```

## Configuration and Extensibility

### 1. Dynamic Configuration

```php
// Brands and categories can be configured through admin interface
// Stored in database tables for flexibility
// Supports real-time configuration changes
```

### 2. Plugin Integration

- WooCommerce integration with version compatibility checks
- WordPress hook system for extensibility
- Modular architecture for easy feature addition

## Summary

The ShopCommerce product sync system provides a robust, scalable solution for synchronizing product catalogs from an external API with WooCommerce stores. The flow ensures:

1. **Reliability**: Comprehensive error handling and retry mechanisms
2. **Performance**: Batch processing and caching strategies
3. **Data Integrity**: Duplicate prevention and transaction safety
4. **Flexibility**: Configurable sync intervals and brand/category management
5. **Monitoring**: Detailed logging and activity tracking
6. **Security**: Proper authentication, validation, and WordPress security practices

The system can handle large product catalogs efficiently while maintaining data consistency and providing administrators with visibility into the sync process through comprehensive logging and monitoring capabilities.