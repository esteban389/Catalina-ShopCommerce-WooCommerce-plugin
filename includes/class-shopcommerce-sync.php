<?php

/**
 * ShopCommerce Sync Handler Class
 *
 * Main sync logic that coordinates API calls, product processing,
 * and manages the overall sync workflow.
 *
 * @package ShopCommerce_Sync
 */

class ShopCommerce_Sync {

    /**
     * Logger instance
     */
    private $logger;

    /**
     * API client instance
     */
    private $api_client;

    /**
     * Product handler instance
     */
    private $product_handler;

    /**
     * Cron scheduler instance
     */
    private $cron_scheduler;

    /**
     * Jobs store instance
     */
    private $jobs_store;

    /**
     * Default batch size for processing products
     */
    const DEFAULT_BATCH_SIZE = 500;

    /**
     * Constructor
     *
     * @param ShopCommerce_Logger $logger Logger instance
     * @param ShopCommerce_API $api_client API client instance
     * @param ShopCommerce_Product $product_handler Product handler instance
     * @param ShopCommerce_Cron $cron_scheduler Cron scheduler instance
     * @param ShopCommerce_Jobs_Store $jobs_store Jobs store instance
     */
    public function __construct($logger, $api_client, $product_handler, $cron_scheduler, $jobs_store = null) {
        $this->logger = $logger;
        $this->api_client = $api_client;
        $this->product_handler = $product_handler;
        $this->cron_scheduler = $cron_scheduler;
        $this->jobs_store = $jobs_store;
    }

    /**
     * Execute the main sync hook (called by cron)
     *
     * @return array Sync results
     */
    public function execute_sync() {
        $this->logger->info('Starting scheduled sync execution');

        try {
            // Get next job to process
            $job = $this->cron_scheduler->get_next_job();
            if (!$job) {
                $this->logger->warning('No jobs available for sync');
                return ['success' => false, 'error' => 'No jobs available'];
            }

            // Execute sync for the job
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
    }

    /**
     * Execute sync for a specific job
     *
     * @param array $job Job configuration
     * @return array Sync results
     */
    public function execute_sync_for_job($job) {
        $brand = $job['brand'];
        $categories = isset($job['categories']) && is_array($job['categories']) ? $job['categories'] : [];

        $this->logger->info('Starting sync for job', [
            'brand' => $brand,
            'categories' => $categories,
            'categories_count' => count($categories)
        ]);

        try {
            // Get catalog from API
            $catalog = $this->api_client->get_catalog($brand, $categories);
            if (!$catalog) {
                throw new Exception('Failed to retrieve catalog from API');
            }

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

            $this->logger->info('Catalog retrieved successfully', [
                'brand' => $brand,
                'categories' => $categories,
                'catalog_count' => count($catalog)
            ]);

            // Queue catalog for batch processing
            $results = $this->queue_catalog_for_processing($catalog, $brand, $categories);

            // Log sync queuing completion
            $this->logger->log_activity('sync_queued', 'Sync queued for brand: ' . $brand, [
                'brand' => $brand,
                'categories' => $categories,
                'products_count' => count($catalog),
                'batches_queued' => $results['batches_queued']
            ]);

            return array_merge($results, ['success' => true]);

        } catch (Exception $e) {
            $this->logger->error('Sync failed for job', [
                'brand' => $brand,
                'categories' => $categories,
                'error' => $e->getMessage()
            ]);

            $this->logger->log_activity('sync_error', 'Sync failed for brand: ' . $brand, [
                'brand' => $brand,
                'categories' => $categories,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'brand' => $brand,
                'categories' => $categories
            ];
        }
    }

    /**
     * Queue catalog for asynchronous batch processing
     *
     * @param array $catalog Product catalog data
     * @param string $brand Brand name
     * @param array $categories Category codes
     * @return array Queuing results
     */
    private function queue_catalog_for_processing($catalog, $brand, $categories) {
        $this->logger->info('Queueing catalog for batch processing', [
            'total_products' => count($catalog),
            'brand' => $brand,
            'categories_count' => count($categories)
        ]);

        if (!$this->jobs_store) {
            throw new Exception('Jobs store not available for batch processing');
        }

        // Use batch processor batch size for consistency
        $batch_size = ShopCommerce_Batch_Processor::DEFAULT_BATCH_SIZE;
        $product_batches = array_chunk($catalog, $batch_size);
        $total_batches = count($product_batches);

        $this->logger->info('Creating batches for queue', [
            'total_products' => count($catalog),
            'batch_size' => $batch_size,
            'total_batches' => $total_batches,
            'brand' => $brand
        ]);

        $queued_batches = 0;
        $batch_ids = [];

        foreach ($product_batches as $batch_index => $batch) {
            try {
                // Create batch in queue
                $batch_id = $this->jobs_store->add_batch_to_queue(
                    $brand,
                    $categories,
                    $batch,
                    $batch_index + 1, // 1-based index
                    $total_batches
                );

                if ($batch_id) {
                    $batch_ids[] = $batch_id;
                    $queued_batches++;

                    $this->logger->debug('Batch queued successfully', [
                        'batch_id' => $batch_id,
                        'batch_index' => $batch_index + 1,
                        'total_batches' => $total_batches,
                        'products_in_batch' => count($batch),
                        'brand' => $brand
                    ]);
                } else {
                    $this->logger->error('Failed to queue batch', [
                        'batch_index' => $batch_index + 1,
                        'brand' => $brand
                    ]);
                }

            } catch (Exception $e) {
                $this->logger->error('Error queuing batch', [
                    'batch_index' => $batch_index + 1,
                    'error' => $e->getMessage(),
                    'brand' => $brand
                ]);
            }
        }

        $this->logger->info('Catalog queuing completed', [
            'brand' => $brand,
            'total_batches' => $total_batches,
            'queued_batches' => $queued_batches,
            'failed_batches' => $total_batches - $queued_batches
        ]);

        return [
            'catalog_count' => count($catalog),
            'batches_queued' => $queued_batches,
            'total_batches' => $total_batches,
            'failed_batches' => $total_batches - $queued_batches,
            'batch_ids' => $batch_ids
        ];
    }

    /**
     * Process catalog in batches
     *
     * @param array $catalog Product catalog data
     * @param string $brand Brand name
     * @param int $batch_size Batch size for processing
     * @return array Processing results
     */
    private function process_catalog($catalog, $brand, $batch_size = self::DEFAULT_BATCH_SIZE) {
        $this->logger->info('Processing catalog in batches', [
            'total_products' => count($catalog),
            'batch_size' => $batch_size,
            'brand' => $brand
        ]);

        $overall_results = [
            'processed_count' => 0,
            'created' => 0,
            'updated' => 0,
            'errors' => 0,
            'skipped' => 0,
            'batches_processed' => 0,
        ];

        $product_batches = array_chunk($catalog, $batch_size);

        foreach ($product_batches as $batch_index => $batch) {
            $this->logger->info('Processing batch', [
                'batch_index' => $batch_index + 1,
                'total_batches' => count($product_batches),
                'batch_size' => count($batch),
                'brand' => $brand
            ]);

            try {
                // Process batch using product handler
                $batch_results = $this->product_handler->process_batch($batch, $brand);

                // Aggregate results
                $overall_results['processed_count'] += $batch_results['total'];
                $overall_results['created'] += $batch_results['created'];
                $overall_results['updated'] += $batch_results['updated'];
                $overall_results['errors'] += $batch_results['errors'];
                $overall_results['skipped'] += $batch_results['skipped'];
                $overall_results['batches_processed']++;

                $this->logger->info('Batch completed', [
                    'batch_index' => $batch_index + 1,
                    'results' => $batch_results,
                    'overall_progress' => $overall_results
                ]);

                // Add a small delay between batches to prevent server overload
                if ($batch_index < count($product_batches) - 1) {
                    sleep(1);
                }

            } catch (Exception $e) {
                $this->logger->error('Error processing batch', [
                    'batch_index' => $batch_index + 1,
                    'error' => $e->getMessage(),
                    'brand' => $brand
                ]);
                $overall_results['errors'] += count($batch);
            }
        }

        $this->logger->info('Catalog processing completed', [
            'brand' => $brand,
            'results' => $overall_results
        ]);

        return $overall_results;
    }

    /**
     * Test API connection and sync setup
     *
     * @return array Test results
     */
    public function test_connection() {
        $this->logger->info('Testing API connection and sync setup');

        try {
            // Test API connection
            $api_test = $this->api_client->test_connection();
            if (!$api_test) {
                return [
                    'success' => false,
                    'error' => 'API connection failed',
                    'details' => 'Could not authenticate with ShopCommerce API'
                ];
            }

            // Get job queue status
            $queue_status = $this->cron_scheduler->get_queue_status();

            // Get cron information
            $cron_info = $this->cron_scheduler->get_cron_info();

            // Test WooCommerce integration
            $woocommerce_test = $this->test_woocommerce_integration();

            return [
                'success' => true,
                'api_connection' => true,
                'queue_status' => $queue_status,
                'cron_info' => $cron_info,
                'woocommerce_test' => $woocommerce_test,
                'message' => 'All systems operational'
            ];

        } catch (Exception $e) {
            $this->logger->error('Connection test failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Test WooCommerce integration
     *
     * @return array WooCommerce test results
     */
    private function test_woocommerce_integration() {
        try {
            if (!class_exists('WooCommerce')) {
                return [
                    'status' => 'error',
                    'message' => 'WooCommerce plugin not activated'
                ];
            }

            // Test product creation capability
            if (!function_exists('wc_get_product') || !class_exists('WC_Product_Simple')) {
                return [
                    'status' => 'error',
                    'message' => 'WooCommerce functions not available'
                ];
            }

            // Test database access
            global $wpdb;
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}posts'");
            if (!$table_exists) {
                return [
                    'status' => 'error',
                    'message' => 'Database tables not accessible'
                ];
            }

            // Check plugin requirements
            $requirements_met = $this->check_plugin_requirements();

            return [
                'status' => 'success',
                'message' => 'WooCommerce integration ready',
                'requirements' => $requirements_met
            ];

        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'WooCommerce test failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check plugin requirements
     *
     * @return array Requirements check results
     */
    private function check_plugin_requirements() {
        $requirements = [
            'wordpress_version' => [
                'required' => '5.0',
                'current' => get_bloginfo('version'),
                'met' => version_compare(get_bloginfo('version'), '5.0', '>=')
            ],
            'php_version' => [
                'required' => '7.2',
                'current' => PHP_VERSION,
                'met' => version_compare(PHP_VERSION, '7.2', '>=')
            ],
            'woocommerce_version' => [
                'required' => '3.0',
                'current' => defined('WC_VERSION') ? WC_VERSION : 'not installed',
                'met' => defined('WC_VERSION') && version_compare(WC_VERSION, '3.0', '>=')
            ],
            'memory_limit' => [
                'required' => '128M',
                'current' => ini_get('memory_limit'),
                'met' => $this->check_memory_limit()
            ],
            'max_execution_time' => [
                'required' => '300',
                'current' => ini_get('max_execution_time'),
                'met' => $this->check_execution_time()
            ]
        ];

        $all_met = true;
        foreach ($requirements as $requirement) {
            if (!$requirement['met']) {
                $all_met = false;
                break;
            }
        }

        return [
            'requirements' => $requirements,
            'all_met' => $all_met
        ];
    }

    /**
     * Check if memory limit is sufficient
     *
     * @return bool True if memory limit is sufficient
     */
    private function check_memory_limit() {
        $memory_limit = ini_get('memory_limit');
        if ($memory_limit == -1) {
            return true; // Unlimited
        }
        return intval($memory_limit) >= 128;
    }

    /**
     * Check if execution time is sufficient
     *
     * @return bool True if execution time is sufficient
     */
    private function check_execution_time() {
        $max_execution_time = ini_get('max_execution_time');
        if ($max_execution_time == 0) {
            return true; // Unlimited
        }
        return intval($max_execution_time) >= 300;
    }

    /**
     * Check and handle brand completion when all batches are processed
     *
     * @param string $brand Brand name to check
     * @return array Completion status
     */
    public function check_and_handle_brand_completion($brand) {
        $this->logger->info('Checking brand completion', ['brand' => $brand]);

        if (!$this->jobs_store) {
            return [
                'success' => false,
                'error' => 'Jobs store not available'
            ];
        }

        try {
            // Get queue stats for the brand
            $queue_stats = $this->jobs_store->get_queue_stats();
            $brand_stats = isset($queue_stats['by_brand'][$brand]) ? $queue_stats['by_brand'][$brand] : null;

            if (!$brand_stats) {
                return [
                    'success' => false,
                    'error' => 'No stats found for brand: ' . $brand
                ];
            }

            $is_completed = ($brand_stats['pending'] === 0 && $brand_stats['processing'] === 0);

            if ($is_completed) {
                $this->logger->info('All batches completed for brand', [
                    'brand' => $brand,
                    'total_batches' => $brand_stats['total'],
                    'completed' => $brand_stats['completed'],
                    'failed' => $brand_stats['failed']
                ]);

                // Get batch processor for progress tracking
                global $shopcommerce_batch_processor;
                if ($shopcommerce_batch_processor) {
                    $progress = $shopcommerce_batch_processor->get_brand_progress($brand);

                    if ($progress) {
                        // Log brand completion activity
                        $this->logger->log_activity('brand_sync_complete', 'Brand sync completed: ' . $brand, [
                            'brand' => $brand,
                            'total_products' => $progress['total_products'],
                            'processed_products' => $progress['processed_products'],
                            'created' => $progress['created'],
                            'updated' => $progress['updated'],
                            'errors' => $progress['errors'],
                            'completion_percentage' => $progress['completion_percentage'],
                            'time_elapsed' => $progress['time_elapsed']
                        ]);

                        // Clear brand progress tracking
                        $shopcommerce_batch_processor->clear_brand_progress($brand);
                    }
                }

                return [
                    'success' => true,
                    'completed' => true,
                    'brand' => $brand,
                    'stats' => $brand_stats
                ];
            } else {
                return [
                    'success' => true,
                    'completed' => false,
                    'brand' => $brand,
                    'stats' => $brand_stats
                ];
            }

        } catch (Exception $e) {
            $this->logger->error('Error checking brand completion', [
                'brand' => $brand,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'brand' => $brand
            ];
        }
    }

    /**
     * Get sync statistics and status
     *
     * @return array Sync statistics
     */
    public function get_sync_statistics() {
        // Get product statistics
        $product_stats = $this->product_handler->get_statistics();

        // Get activity log statistics
        $activity_stats = $this->logger->get_statistics();

        // Get queue status
        $queue_status = $this->cron_scheduler->get_queue_status();

        // Get API status
        $api_status = $this->api_client->get_status();

        // Get batch processing statistics
        $batch_stats = [];
        if ($this->jobs_store) {
            $batch_stats = $this->jobs_store->get_queue_stats();
        }

        // Get active brand progress
        $brand_progress = [];
        global $shopcommerce_batch_processor;
        if ($shopcommerce_batch_processor) {
            $processing_stats = $shopcommerce_batch_processor->get_processing_stats();
            $brand_progress = $processing_stats['active_brands'];
        }

        return [
            'products' => $product_stats,
            'activity' => $activity_stats,
            'queue' => $queue_status,
            'api' => $api_status,
            'batch_processing' => [
                'queue_stats' => $batch_stats,
                'active_brands' => $brand_progress,
                'total_active_brands' => count($brand_progress)
            ],
            'last_check' => current_time('mysql')
        ];
    }

    /**
     * Run full sync for all configured brands
     *
     * @param int $batch_size Batch size for processing
     * @return array Full sync results
     */
    public function run_full_sync($batch_size = self::DEFAULT_BATCH_SIZE) {
        $this->logger->info('Starting full sync for all brands');

        $jobs = $this->cron_scheduler->get_jobs();
        if (empty($jobs)) {
            return [
                'success' => false,
                'error' => 'No jobs configured'
            ];
        }

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
                $this->logger->info('Processing job in full sync', [
                    'brand' => $job['brand'],
                    'categories' => $job['categories']
                ]);

                $job_result = $this->execute_sync_for_job($job);

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

        $this->logger->info('Full sync completed', [
            'results' => $overall_results
        ]);

        return [
            'success' => true,
            'results' => $overall_results
        ];
    }

    /**
     * Clear cache and reset sync state
     *
     * @return array Clear results
     */
    public function clear_cache() {
        $this->logger->info('Clearing sync cache and resetting state');

        try {
            // Clear API cache
            $this->api_client->clear_cache();

            // Reset jobs
            $this->cron_scheduler->reset_jobs();

            // Clear activity log
            $this->logger->clear_activity_log();

            // Clear log file
            $this->logger->clear_log_file();

            return [
                'success' => true,
                'message' => 'Cache cleared and state reset'
            ];

        } catch (Exception $e) {
            $this->logger->error('Error clearing cache', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Synchronize products for a specific brand synchronously
     *
     * This method processes all products for a brand immediately without batch processing,
     * making it suitable for manual sync operations where immediate results are needed.
     *
     * @param int $brand_id The ID of the brand to synchronize
     * @return array Sync results with comprehensive statistics
     * @throws Exception If brand is not found or sync fails
     */
    public function sync_brand_synchronously($brand_id) {
        $this->logger->info('Starting synchronous brand sync', ['brand_id' => $brand_id]);

        $start_time = microtime(true);

        try {
            // Validate brand ID and get brand configuration
            $brand = $this->jobs_store->get_brand($brand_id);
            if (!$brand) {
                throw new Exception("Brand not found: {$brand_id}");
            }

            $this->logger->info('Brand configuration found', [
                'brand_id' => $brand_id,
                'brand_name' => $brand->name
            ]);

            // Get brand categories
            $categories = $this->jobs_store->get_brand_categories($brand_id);

            // Handle 'all' categories logic - same as regular sync
            if (empty($categories) || in_array('all', $categories)) {
                $categories = [1, 7, 12, 14, 18]; // All available categories
            }

            $this->logger->info('Brand categories retrieved', [
                'brand_id' => $brand_id,
                'categories' => $categories,
                'categories_count' => count($categories)
            ]);

            // Authenticate with API
            $token = $this->api_client->get_token();
            if (!$token) {
                throw new Exception("API authentication failed");
            }

            $this->logger->info('API authentication successful');

            // Fetch products from API for brand
            $this->logger->info('Fetching products from API', [
                'brand' => $brand->name,
                'categories' => $categories
            ]);

            $categories = array_column($categories, 'code');
            $products = $this->api_client->get_catalog($brand->name, $categories);
            if (!$products) {
                throw new Exception("Failed to fetch products from API for brand: {$brand->name}");
            }

            $this->logger->info('API response received', [
                'brand' => $brand->name,
                'products_count' => count($products)
            ]);

            $this->logger->info('Products parsed from API response', [
                'brand' => $brand->name,
                'products_count' => count($products)
            ]);

            if (empty($products)) {
                $this->logger->info('No products found for brand', [
                    'brand' => $brand->name
                ]);

                return [
                    'success' => true,
                    'brand' => $brand->name,
                    'products_processed' => 0,
                    'products_created' => 0,
                    'products_updated' => 0,
                    'errors' => 0,
                    'processing_time' => microtime(true) - $start_time,
                    'timestamp' => current_time('mysql')
                ];
            }

            // Set higher execution limits for synchronous processing
            set_time_limit(900); // 15 minutes
            ini_set('memory_limit', '512M');

            $this->logger->info('Starting synchronous product processing', [
                'brand' => $brand->name,
                'products_count' => count($products)
            ]);

            // Process products synchronously (no batches)
            $results = [
                'total' => count($products),
                'created' => 0,
                'updated' => 0,
                'errors' => 0,
                'error_details' => []
            ];

            foreach ($products as $index => $product_data) {
                try {
                    // Process product directly without batching
                    $result = $this->product_handler->process_product($product_data, $brand->name);

                    if (isset($result['created']) && $result['created']) {
                        $results['created']++;
                    } else {
                        $results['updated']++;
                    }

                    // Log progress every 50 products
                    if (($index + 1) % 50 === 0) {
                        $this->logger->info('Synchronous sync progress', [
                            'brand' => $brand->name,
                            'processed' => $index + 1,
                            'total' => count($products),
                            'created' => $results['created'],
                            'updated' => $results['updated'],
                            'errors' => $results['errors']
                        ]);
                    }

                } catch (Exception $e) {
                    $results['errors']++;
                    $results['error_details'][] = [
                        'sku' => $product_data['sku'],
                        'name' => $product_data['name'],
                        'error' => $e->getMessage()
                    ];

                    $this->logger->warning('Failed to process product in sync', [
                        'brand' => $brand->name,
                        'sku' => $product_data['sku'],
                        'error' => $e->getMessage()
                    ]);

                    // Continue processing other products even if some fail
                    continue;
                }
            }

            $processing_time = microtime(true) - $start_time;

            $sync_results = [
                'success' => true,
                'brand' => $brand->name,
                'brand_id' => $brand_id,
                'products_processed' => $results['total'],
                'products_created' => $results['created'],
                'products_updated' => $results['updated'],
                'errors' => $results['errors'],
                'error_details' => $results['error_details'],
                'processing_time' => $processing_time,
                'timestamp' => current_time('mysql'),
                'sync_type' => 'synchronous'
            ];

            $this->logger->info('Synchronous brand sync completed successfully', $sync_results);

            return $sync_results;

        } catch (Exception $e) {
            $processing_time = microtime(true) - $start_time;

            $this->logger->error('Synchronous brand sync failed', [
                'brand_id' => $brand_id,
                'error' => $e->getMessage(),
                'processing_time' => $processing_time,
                'trace' => $e->getTraceAsString()
            ]);

            throw $e; // Re-throw exception for caller to handle
        }
    }

    /**
     * Extract images from API response
     * Helper method for parsing product images from API response
     *
     * @param array $images_array Array of image URLs from API
     * @return array Array of valid image URLs
     */
    private function extract_images_from_api($images_array) {
        $images = [];

        if (is_array($images_array)) {
            foreach ($images_array as $image_url) {
                if (!empty($image_url) && is_string($image_url) && filter_var($image_url, FILTER_VALIDATE_URL)) {
                    $images[] = $image_url;
                }
            }
        }

        return $images;
    }

}