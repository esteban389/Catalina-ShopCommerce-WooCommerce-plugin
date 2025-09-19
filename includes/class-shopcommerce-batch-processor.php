<?php

/**
 * ShopCommerce Batch Processor Class
 *
 * Handles asynchronous processing of product batches with proper
 * timeout handling, retry logic, and error recovery.
 *
 * @package ShopCommerce_Sync
 */

class ShopCommerce_Batch_Processor {

    /**
     * Logger instance
     */
    private $logger;

    /**
     * Jobs store instance
     */
    private $jobs_store;

    /**
     * Product handler instance
     */
    private $product_handler;

    /**
     * Default batch size
     */
    const DEFAULT_BATCH_SIZE = 500;

    /**
     * Maximum execution time per batch (seconds)
     */
    const MAX_EXECUTION_TIME = 60;

    /**
     * Constructor
     *
     * @param ShopCommerce_Logger $logger Logger instance
     * @param ShopCommerce_Jobs_Store $jobs_store Jobs store instance
     * @param ShopCommerce_Product $product_handler Product handler instance
     */
    public function __construct($logger, $jobs_store, $product_handler) {
        $this->logger = $logger;
        $this->jobs_store = $jobs_store;
        $this->product_handler = $product_handler;
    }

    /**
     * Process a single batch
     *
     * @param int $batch_id Batch ID to process
     * @return array Processing results
     */
    public function process_batch($batch_id) {
        $this->logger->info('Starting batch processing', ['batch_id' => $batch_id]);

        try {
            // Set execution limits
            $this->set_execution_limits();

            // Get batch from queue
            $batch = $this->jobs_store->get_batch($batch_id);
            if (!$batch) {
                throw new Exception("Batch not found: {$batch_id}");
            }

            // Check if batch is still in a processable state
            if (!in_array($batch->status, ['pending', 'failed'])) {
                throw new Exception("Batch {$batch_id} is not in a processable state (current status: {$batch->status})");
            }

            // Update status to processing and increment attempts
            $this->jobs_store->update_batch_status($batch_id, 'processing');
            $this->jobs_store->increment_batch_attempts($batch_id);

            // Decode batch data with error handling
            $batch_data = json_decode($batch->batch_data, true);
            $categories = json_decode($batch->categories, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("JSON decode error for batch {$batch_id}: " . json_last_error_msg());
            }

            if (!is_array($batch_data)) {
                throw new Exception("Invalid batch data format for batch {$batch_id}");
            }

            $this->logger->info('Processing batch data', [
                'batch_id' => $batch_id,
                'brand' => $batch->brand,
                'batch_index' => $batch->batch_index,
                'total_batches' => $batch->total_batches,
                'products_count' => count($batch_data)
            ]);

            // Process the batch
            $results = $this->process_batch_data($batch_data, $batch->brand, $categories);

            // Update status to completed
            $this->jobs_store->update_batch_status($batch_id, 'completed');

            // Update progress tracking
            $this->update_brand_progress($batch->brand, $batch->batch_index, $batch->total_batches, $results);

            $this->logger->info('Batch processing completed', [
                'batch_id' => $batch_id,
                'results' => $results
            ]);

            return [
                'success' => true,
                'batch_id' => $batch_id,
                'results' => $results,
                'brand' => $batch->brand,
                'batch_index' => $batch->batch_index,
                'total_batches' => $batch->total_batches
            ];

        } catch (Exception $e) {
            $error_message = $e->getMessage();
            $error_type = $this->categorize_error($e);

            // Get current batch info for retry logic
            $batch_info = $this->jobs_store->get_batch($batch_id);
            $should_retry = $this->should_retry_batch($batch_info, $error_type);

            $this->logger->error('Batch processing failed', [
                'batch_id' => $batch_id,
                'error' => $error_message,
                'error_type' => $error_type,
                'attempts' => $batch_info ? $batch_info->attempts : 'unknown',
                'should_retry' => $should_retry,
                'trace' => $e->getTraceAsString()
            ]);

            if ($should_retry) {
                // Update status to failed for retry (will be picked up by retry mechanism)
                $this->jobs_store->update_batch_status($batch_id, 'failed', $error_message);

                // Schedule retry if possible
                $this->schedule_retry_if_needed($batch_id, $batch_info);
            } else {
                // Permanently failed
                $final_error_message = $error_message . ' (Max attempts reached or non-retryable error)';
                $this->jobs_store->update_batch_status($batch_id, 'failed', $final_error_message);
            }

            return [
                'success' => false,
                'batch_id' => $batch_id,
                'error' => $error_message,
                'error_type' => $error_type,
                'retryable' => $should_retry,
                'attempts' => $batch_info ? $batch_info->attempts : 0
            ];
        }
    }

    /**
     * Process batch data with product handler
     *
     * @param array $batch_data Product data for the batch
     * @param string $brand Brand name
     * @param array $categories Category codes
     * @return array Processing results
     */
    private function process_batch_data($batch_data, $brand, $categories) {
        $this->logger->debug('Processing batch data with product handler', [
            'products_count' => count($batch_data),
            'brand' => $brand
        ]);

        return $this->product_handler->process_batch($batch_data, $brand);
    }

    /**
     * Set execution limits for batch processing
     */
    private function set_execution_limits() {
        // Set time limit to prevent timeouts
        if (function_exists('set_time_limit')) {
            @set_time_limit(self::MAX_EXECUTION_TIME);
        }

        // Increase memory limit if needed
        if (function_exists('ini_set')) {
            @ini_set('memory_limit', '256M');
        }

        // Ignore user abort to allow completion
        @ignore_user_abort(true);
    }

    /**
     * Update brand progress tracking
     *
     * @param string $brand Brand name
     * @param int $batch_index Current batch index
     * @param int $total_batches Total number of batches
     * @param array $results Batch processing results
     */
    private function update_brand_progress($brand, $batch_index, $total_batches, $results) {
        $progress_key = "shopcommerce_progress_{$brand}";
        $progress_data = get_transient($progress_key);

        if (!$progress_data) {
            $progress_data = [
                'brand' => $brand,
                'total_batches' => $total_batches,
                'completed_batches' => 0,
                'total_products' => 0,
                'processed_products' => 0,
                'created' => 0,
                'updated' => 0,
                'errors' => 0,
                'started_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ];
        }

        // Update progress
        $progress_data['completed_batches'] = max($progress_data['completed_batches'], $batch_index);
        $progress_data['processed_products'] += $results['total'] ?? 0;
        $progress_data['created'] += $results['created'] ?? 0;
        $progress_data['updated'] += $results['updated'] ?? 0;
        $progress_data['errors'] += $results['errors'] ?? 0;
        $progress_data['updated_at'] = current_time('mysql');

        // Set total products from first batch
        if ($progress_data['total_products'] === 0 && isset($results['total'])) {
            // Estimate total products based on batches completed
            $progress_data['total_products'] = $results['total'] * $total_batches;
        }

        // Store progress with 1 hour expiry
        set_transient($progress_key, $progress_data, HOUR_IN_SECONDS);

        $this->logger->debug('Updated brand progress', [
            'brand' => $brand,
            'batch_index' => $batch_index,
            'total_batches' => $total_batches,
            'completed_batches' => $progress_data['completed_batches'],
            'processed_products' => $progress_data['processed_products']
        ]);
    }

    /**
     * Get brand progress
     *
     * @param string $brand Brand name
     * @return array|false Progress data or false if not found
     */
    public function get_brand_progress($brand) {
        $progress_key = "shopcommerce_progress_{$brand}";
        $progress = get_transient($progress_key);

        if ($progress) {
            // Calculate completion percentage
            if ($progress['total_batches'] > 0) {
                $progress['completion_percentage'] = round(($progress['completed_batches'] / $progress['total_batches']) * 100, 2);
            } else {
                $progress['completion_percentage'] = 0;
            }

            // Calculate time elapsed
            $started = strtotime($progress['started_at']);
            $updated = strtotime($progress['updated_at']);
            $progress['time_elapsed'] = $updated - $started;

            return $progress;
        }

        return false;
    }

    /**
     * Clear brand progress tracking
     *
     * @param string $brand Brand name
     */
    public function clear_brand_progress($brand) {
        $progress_key = "shopcommerce_progress_{$brand}";
        delete_transient($progress_key);

        $this->logger->info('Cleared brand progress', ['brand' => $brand]);
    }

    /**
     * Process pending batches from queue
     *
     * @param int $limit Maximum number of batches to process
     * @return array Processing results
     */
    public function process_pending_batches($limit = 1) {
        $this->logger->info('Processing pending batches', ['limit' => $limit]);

        $pending_batches = $this->jobs_store->get_pending_batches($limit);

        if (empty($pending_batches)) {
            $this->logger->info('No pending batches to process');
            return [
                'success' => true,
                'processed' => 0,
                'results' => []
            ];
        }

        $results = [];
        $processed = 0;

        foreach ($pending_batches as $batch) {
            try {
                $result = $this->process_batch($batch->id);
                $results[] = $result;
                $processed++;

                $this->logger->info('Processed batch from queue', [
                    'batch_id' => $batch->id,
                    'brand' => $batch->brand,
                    'result' => $result['success'] ? 'success' : 'failed'
                ]);

            } catch (Exception $e) {
                $this->logger->error('Failed to process batch from queue', [
                    'batch_id' => $batch->id,
                    'brand' => $batch->brand,
                    'error' => $e->getMessage()
                ]);

                $results[] = [
                    'success' => false,
                    'batch_id' => $batch->id,
                    'error' => $e->getMessage()
                ];
            }
        }

        $this->logger->info('Completed processing pending batches', [
            'limit' => $limit,
            'processed' => $processed,
            'total_results' => count($results)
        ]);

        return [
            'success' => true,
            'processed' => $processed,
            'results' => $results
        ];
    }

    /**
     * Get processing statistics
     *
     * @return array Processing statistics
     */
    public function get_processing_stats() {
        $queue_stats = $this->jobs_store->get_queue_stats();

        // Get active brand progress
        global $wpdb;
        $transient_prefix = '_transient_shopcommerce_progress_';
        $active_brands = $wpdb->get_col(
            "SELECT option_name FROM {$wpdb->options}
             WHERE option_name LIKE '{$transient_prefix}%'
             AND option_value IS NOT NULL"
        );

        $brand_progress = [];
        foreach ($active_brands as $transient_name) {
            $brand = str_replace($transient_prefix, '', $transient_name);
            $progress = $this->get_brand_progress($brand);
            if ($progress) {
                $brand_progress[$brand] = $progress;
            }
        }

        return [
            'queue_stats' => $queue_stats,
            'active_brands' => $brand_progress,
            'total_active_brands' => count($brand_progress)
        ];
    }

    /**
     * Clean up old progress data
     *
     * @param int $hours_old Delete progress older than this many hours
     * @return int Number of progress entries cleaned
     */
    public function cleanup_old_progress($hours_old = 24) {
        global $wpdb;

        $cutoff_time = time() - ($hours_old * 3600);
        $transient_prefix = '_transient_shopcommerce_progress_';

        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE %s
             AND option_value < %d",
            $transient_prefix . '%',
            $cutoff_time
        ));

        if ($result) {
            $this->logger->info('Cleaned up old progress data', [
                'hours_old' => $hours_old,
                'deleted_count' => $result
            ]);
        }

        return $result;
    }

    /**
     * Categorize error type for retry logic
     *
     * @param Exception $e The exception
     * @return string Error type category
     */
    private function categorize_error($e) {
        $message = strtolower($e->getMessage());

        // Network/timeout errors - retryable
        if (strpos($message, 'timeout') !== false ||
            strpos($message, 'connection') !== false ||
            strpos($message, 'network') !== false ||
            strpos($message, 'curl') !== false ||
            $e instanceof RuntimeException) {
            return 'network';
        }

        // Memory errors - may be retryable with smaller batches
        if (strpos($message, 'memory') !== false ||
            strpos($message, 'allowed memory') !== false) {
            return 'memory';
        }

        // Database errors - may be retryable
        if (strpos($message, 'database') !== false ||
            strpos($message, 'mysql') !== false ||
            strpos($message, 'wpdb') !== false) {
            return 'database';
        }

        // API errors - may be retryable
        if (strpos($message, 'api') !== false ||
            strpos($message, 'http') !== false ||
            strpos($message, 'request') !== false) {
            return 'api';
        }

        // Data validation errors - not retryable
        if (strpos($message, 'invalid') !== false ||
            strpos($message, 'validation') !== false ||
            strpos($message, 'format') !== false) {
            return 'validation';
        }

        // Default - treat as potentially retryable
        return 'general';
    }

    /**
     * Determine if batch should be retried
     *
     * @param object $batch_info Batch information
     * @param string $error_type Error type category
     * @return bool True if should retry
     */
    private function should_retry_batch($batch_info, $error_type) {
        if (!$batch_info) {
            return false;
        }

        $max_attempts = $batch_info->max_attempts ?? 3;
        $current_attempts = $batch_info->attempts ?? 0;

        // Don't retry if max attempts reached
        if ($current_attempts >= $max_attempts) {
            return false;
        }

        // Non-retryable error types
        $non_retryable_types = ['validation'];
        if (in_array($error_type, $non_retryable_types)) {
            return false;
        }

        // All other errors are retryable within attempt limits
        return true;
    }

    /**
     * Schedule retry if needed (placeholder for future implementation)
     *
     * @param int $batch_id Batch ID to retry
     * @param object $batch_info Batch information
     */
    private function schedule_retry_if_needed($batch_id, $batch_info) {
        // For now, just log the retry intent
        // In a more sophisticated implementation, this could:
        // - Schedule a WP cron job for retry
        // - Add to a retry queue with exponential backoff
        // - Send notification for manual intervention

        $this->logger->info('Batch marked for retry', [
            'batch_id' => $batch_id,
            'current_attempts' => $batch_info ? $batch_info->attempts : 'unknown',
            'max_attempts' => $batch_info ? $batch_info->max_attempts : 'unknown'
        ]);
    }
}