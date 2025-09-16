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
     * Default batch size for processing products
     */
    const DEFAULT_BATCH_SIZE = 100;

    /**
     * Constructor
     *
     * @param ShopCommerce_Logger $logger Logger instance
     * @param ShopCommerce_API $api_client API client instance
     * @param ShopCommerce_Product $product_handler Product handler instance
     * @param ShopCommerce_Cron $cron_scheduler Cron scheduler instance
     */
    public function __construct($logger, $api_client, $product_handler, $cron_scheduler) {
        $this->logger = $logger;
        $this->api_client = $api_client;
        $this->product_handler = $product_handler;
        $this->cron_scheduler = $cron_scheduler;
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

            // Process catalog in batches
            $results = $this->process_catalog($catalog, $brand);

            // Log sync completion
            $this->logger->log_activity('sync_complete', 'Sync completed for brand: ' . $brand, [
                'brand' => $brand,
                'categories' => $categories,
                'products_processed' => $results['processed_count'],
                'created' => $results['created'],
                'updated' => $results['updated'],
                'errors' => $results['errors']
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

        return [
            'products' => $product_stats,
            'activity' => $activity_stats,
            'queue' => $queue_status,
            'api' => $api_status,
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
}