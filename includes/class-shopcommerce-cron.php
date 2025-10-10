<?php

/**
 * ShopCommerce Cron Scheduler Class
 *
 * Handles cron job scheduling, job management, and activation/deactivation hooks.
 *
 * @package ShopCommerce_Sync
 */

class ShopCommerce_Cron {

    /**
     * Cron hook name
     */
    const HOOK_NAME = 'shopcommerce_product_sync_hook';

    /**
     * Default cron interval
     */
    const DEFAULT_INTERVAL = 'every_5_minutes';

    /**
     * Option keys
     */
    const JOBS_OPTION_KEY = 'shopcommerce_sync_jobs';
    const JOB_INDEX_OPTION_KEY = 'shopcommerce_sync_jobs_index';

    /**
     * Logger instance
     */
    private $logger;

    /**
     * Jobs store instance
     */
    private $jobs_store;

    /**
     * Constructor
     *
     * @param ShopCommerce_Logger $logger Logger instance
     * @param ShopCommerce_Jobs_Store $jobs_store Jobs store instance
     */
    public function __construct($logger, $jobs_store = null) {
        $this->logger = $logger;
        $this->jobs_store = $jobs_store;

        // Register custom schedules IMMEDIATELY - this is crucial
        add_filter('cron_schedules', [$this, 'register_cron_schedules']);

// Clear existing schedule
wp_clear_scheduled_hook(SYNC_HOOK_NAME);

// Schedule new clearing
if (!wp_next_scheduled(SYNC_HOOK_NAME)) {
    wp_schedule_event(time(), "every_5_minutes", SYNC_HOOK_NAME);
}

        wp_clear_scheduled_hook(BATCH_PROCESS_HOOK_NAME);

            // Schedule batch processing hook (every 5 minutes)
            if (!wp_next_scheduled(BATCH_PROCESS_HOOK_NAME)) {
                $batch_scheduled = wp_schedule_event(time(), 'every_5_minutes', BATCH_PROCESS_HOOK_NAME);
                $this->logger->info('Batch processing cron scheduled', [
                    'scheduled' => $batch_scheduled
                ]);
            }
    }

    /**
     * Plugin activation hook
     */
    public function activate() {
        $this->logger->info('Activating ShopCommerce sync plugin');

        try {
            // Ensure schedules are registered before scheduling
            $this->force_register_schedules();
            
            // Initialize jobs first
            $this->initialize_jobs();

            // Clear any existing schedules to ensure clean state
            $this->clear_cron_event();
            
            // Schedule the main sync hook
            $scheduled = $this->schedule_cron_event(self::DEFAULT_INTERVAL);

            if (!$scheduled) {
                throw new Exception('Failed to schedule initial cron event');
            }

            // Verify the schedule was created
            $next_run = wp_next_scheduled(self::HOOK_NAME);
            if (!$next_run) {
                throw new Exception('Cron event was not actually scheduled');
            }

            // Schedule batch processing hook (every 5 minutes)
            if (!wp_next_scheduled(BATCH_PROCESS_HOOK_NAME)) {
                $batch_scheduled = wp_schedule_event(time(), 'every_5_minutes', BATCH_PROCESS_HOOK_NAME);
                $this->logger->info('Batch processing cron scheduled', [
                    'scheduled' => $batch_scheduled
                ]);
            }
            
            $this->logger->info('Activation completed successfully', [
                'next_run' => date('Y-m-d H:i:s', $next_run)
            ]);
            
        } catch (Exception $e) {
            $this->logger->error('Activation failed', ['error' => $e->getMessage()]);
            
            // Store activation error for admin display
            update_option('shopcommerce_activation_error', $e->getMessage(), false);
        }
    }

    private function force_register_schedules() {
        // Manually trigger the cron_schedules filter to ensure our schedules are registered
        $schedules = wp_get_schedules();
        $schedules = $this->register_cron_schedules($schedules);
        
        // This is a bit hacky but ensures schedules are available immediately
        global $wp_filter;
        if (isset($wp_filter['cron_schedules'])) {
            // Force WordPress to re-evaluate schedules
            wp_clear_scheduled_hook('__fake_hook_to_refresh_schedules__');
        }
    }

    /**
     * Plugin deactivation hook
     */
    public function deactivate() {
        $this->logger->info('Deactivating ShopCommerce sync plugin');

        // Clear scheduled hooks using the centralized method
        $this->clear_cron_event();

        // Clear batch processing hook
        wp_clear_scheduled_hook(BATCH_PROCESS_HOOK_NAME);
    }

    /**
     * Centralized method to schedule cron events
     * This is the ONLY method that should directly interface with WordPress cron functions
     *
     * @param string $interval Schedule interval (e.g., 'hourly', 'every_minute')
     * @param int|null $timestamp Optional timestamp to schedule at, defaults to current time
     * @return bool True if scheduling was successful, false otherwise
     */
    private function schedule_cron_event($interval, $timestamp = null) {
        if ($timestamp === null) {
            $timestamp = time();
        }

        // Force refresh schedules
        $available_schedules = wp_get_schedules();
        
        // If our custom schedule isn't available, register it now
        if (!isset($available_schedules[$interval])) {
            $available_schedules = $this->register_cron_schedules($available_schedules);
        }
        
        // Final validation
        if (!isset($available_schedules[$interval])) {
            $this->logger->error('Invalid cron interval specified', [
                'hook' => self::HOOK_NAME,
                'interval' => $interval,
                'available_intervals' => array_keys($available_schedules)
            ]);
            return false;
        }

        // Clear any existing schedule first
        $this->clear_cron_event();

        // Schedule the event
        $scheduled = wp_schedule_event($timestamp, $interval, self::HOOK_NAME);

        if ($scheduled === false) {
            $this->logger->error('wp_schedule_event returned false', [
                'hook' => self::HOOK_NAME,
                'interval' => $interval,
                'timestamp' => $timestamp,
                'current_time' => time(),
                'cron_disabled' => defined('DISABLE_WP_CRON') ? DISABLE_WP_CRON : 'not defined'
            ]);
            return false;
        }

        // Verify the event was actually scheduled
        $next_run = wp_next_scheduled(self::HOOK_NAME);
        if (!$next_run) {
            $this->logger->error('Event was not actually scheduled despite wp_schedule_event success');
            return false;
        }

        $this->logger->info('Successfully scheduled cron event', [
            'hook' => self::HOOK_NAME,
            'interval' => $interval,
            'timestamp' => $timestamp,
            'next_run' => date('Y-m-d H:i:s', $next_run),
            'schedule_interval' => $available_schedules[$interval]['interval'] ?? 'unknown'
        ]);

        return true;
    }

    /**
     * Centralized method to clear cron events
     * This is the ONLY method that should directly interface with WordPress cron unscheduling functions
     *
     * @return bool True if clearing was successful, false otherwise
     */
    private function clear_cron_event() {
        $next_scheduled = wp_next_scheduled(self::HOOK_NAME);
        
        if ($next_scheduled) {
            $cleared = wp_clear_scheduled_hook(self::HOOK_NAME);
            
            if ($cleared !== false) {
                $this->logger->info('Successfully cleared cron event', [
                    'hook' => self::HOOK_NAME,
                    'was_scheduled_for' => date('Y-m-d H:i:s', $next_scheduled)
                ]);
            } else {
                $this->logger->error('Failed to clear cron event', ['hook' => self::HOOK_NAME]);
                return false;
            }
        } else {
            $this->logger->info('No cron event to clear', ['hook' => self::HOOK_NAME]);
        }
        
        return true;
    }


    /**
     * Centralized method to get cron information
     * This is the ONLY method that should directly interface with WordPress cron info functions
     *
     * @return array Cron information
     */
    private function get_cron_info_internal() {
        $cron_schedule = wp_get_schedules();
        $current_schedule = wp_get_schedule(self::HOOK_NAME);
        $next_scheduled = wp_next_scheduled(self::HOOK_NAME);

        return [
            'hook_name' => self::HOOK_NAME,
            'current_schedule' => $current_schedule,
            'next_scheduled' => $next_scheduled ? date('Y-m-d H:i:s', $next_scheduled) : null,
            'next_scheduled_timestamp' => $next_scheduled,
            'available_schedules' => array_keys($cron_schedule),
            'is_scheduled' => $next_scheduled !== false,
            'all_schedules' => $cron_schedule,
        ];
    }

    /**
     * Register custom cron schedules
     *
     * @param array $schedules Existing schedules
     * @return array Modified schedules
     */
    public function register_cron_schedules($schedules) {
        // Only add if not already present to avoid conflicts
        if (!isset($schedules['every_minute'])) {
            $schedules['every_minute'] = [
                'interval' => 60,
                'display' => __('Every Minute', 'shopcommerce-product-sync-plugin')
            ];
        }

        if (!isset($schedules['every_5_minutes'])) {
            $schedules['every_5_minutes'] = [
                'interval' => 300,
                'display' => __('Every 5 Minutes', 'shopcommerce-product-sync-plugin')
            ];
        }

        if (!isset($schedules['every_15_minutes'])) {
            $schedules['every_15_minutes'] = [
                'interval' => 900,
                'display' => __('Every 15 Minutes', 'shopcommerce-product-sync-plugin')
            ];
        }

        if (!isset($schedules['every_30_minutes'])) {
            $schedules['every_30_minutes'] = [
                'interval' => 1800,
                'display' => __('Every 30 Minutes', 'shopcommerce-product-sync-plugin')
            ];
        }

        return $schedules;
    }

    /**
     * Execute the main sync hook
     *
     * This method is called by WordPress cron and will delegate to the sync handler
     */
    public function execute_sync_hook() {
        $this->logger->info('Executing sync cron hook');

        try {
            if (isset($GLOBALS['shopcommerce_sync'])) {
                $sync_handler = $GLOBALS['shopcommerce_sync'];
                $sync_handler->execute_sync();
            } else {
                $this->logger->error('Sync handler not available for cron execution');
            }
        } catch (Exception $e) {
            $this->logger->error('Error in sync cron execution', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Initialize jobs in WordPress options
     */
    public function initialize_jobs() {
        $jobs = get_option(self::JOBS_OPTION_KEY);
        if (!is_array($jobs) || empty($jobs)) {
            $jobs = $this->build_jobs_list();
            update_option(self::JOBS_OPTION_KEY, $jobs, false);
            $this->logger->info('Initialized sync jobs', ['job_count' => count($jobs)]);
        }

        if (get_option(self::JOB_INDEX_OPTION_KEY, null) === null) {
            update_option(self::JOB_INDEX_OPTION_KEY, 0, false);
        }
    }

    /**
     * Build jobs list from configuration
     *
     * @return array List of sync jobs
     */
    public function build_jobs_list() {
        // Use jobs store if available
        if ($this->jobs_store) {
            return $this->jobs_store->get_jobs();
        }

        // Fallback to dynamic configuration from config manager
        if (isset($GLOBALS['shopcommerce_config'])) {
            $config = $GLOBALS['shopcommerce_config'];
            return $config->build_jobs_list();
        }

        // Fallback to helpers if available
        if (isset($GLOBALS['shopcommerce_helpers'])) {
            $helpers = $GLOBALS['shopcommerce_helpers'];
            return $helpers->build_jobs_list();
        }

        // Fallback configuration
        return [
            [
                'brand' => 'HP INC',
                'categories' => [1, 7, 12, 14, 18],
            ],
            [
                'brand' => 'DELL',
                'categories' => [1, 7, 12, 14, 18],
            ],
            [
                'brand' => 'LENOVO',
                'categories' => [1, 7, 12, 14, 18],
            ],
            [
                'brand' => 'APPLE',
                'categories' => [1, 7],
            ],
            [
                'brand' => 'ASUS',
                'categories' => [7],
            ],
            [
                'brand' => 'BOSE',
                'categories' => [1, 7, 12, 14, 18], // All categories
            ],
            [
                'brand' => 'EPSON',
                'categories' => [1, 7, 12, 14, 18], // All categories
            ],
            [
                'brand' => 'JBL',
                'categories' => [1, 7, 12, 14, 18], // All categories
            ],
        ];
    }

    /**
     * Get next job and advance pointer (circular)
     *
     * @return array|null Next job or null if no jobs available
     */
    public function get_next_job() {
        $this->initialize_jobs();

        $jobs = get_option(self::JOBS_OPTION_KEY);
        if (!is_array($jobs) || empty($jobs)) {
            $this->logger->warning('No jobs configured');
            return null;
        }

        $index = intval(get_option(self::JOB_INDEX_OPTION_KEY, 0));
        if ($index < 0 || $index >= count($jobs)) {
            $index = 0;
        }

        $job = $jobs[$index];
        $next_index = ($index + 1) % count($jobs);
        update_option(self::JOB_INDEX_OPTION_KEY, $next_index, false);

        $this->logger->debug('Retrieved next job', [
            'current_index' => $index,
            'next_index' => $next_index,
            'total_jobs' => count($jobs),
            'job' => $job
        ]);

        return $job;
    }

    /**
     * Reset jobs to initial state
     */
    public function reset_jobs() {
        delete_option(self::JOBS_OPTION_KEY);
        delete_option(self::JOB_INDEX_OPTION_KEY);
        $this->logger->info('Reset sync jobs');
    }

    /**
     * Rebuild jobs from dynamic configuration
     *
     * @return bool True if rebuild was successful
     */
    public function rebuild_jobs() {
        try {
            $this->logger->info('Rebuilding jobs from dynamic configuration');

            // Build new jobs list
            $new_jobs = $this->build_jobs_list();

            // Update jobs configuration
            $result = $this->update_jobs($new_jobs);

            if ($result) {
                // Reset job index to start from beginning
                update_option(self::JOB_INDEX_OPTION_KEY, 0, false);
                $this->logger->info('Jobs rebuilt successfully', ['job_count' => count($new_jobs)]);
                return true;
            }

            return false;

        } catch (Exception $e) {
            $this->logger->error('Error rebuilding jobs', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get current jobs configuration
     *
     * @return array Current jobs
     */
    public function get_jobs() {
        $this->initialize_jobs();
        return get_option(self::JOBS_OPTION_KEY, []);
    }

    /**
     * Get current job index
     *
     * @return int Current job index
     */
    public function get_job_index() {
        return intval(get_option(self::JOB_INDEX_OPTION_KEY, 0));
    }

    /**
     * Set current job index
     *
     * @param int $index Job index to set
     */
    public function set_job_index($index) {
        $jobs = $this->get_jobs();
        if (is_array($jobs) && $index >= 0 && $index < count($jobs)) {
            update_option(self::JOB_INDEX_OPTION_KEY, $index, false);
            $this->logger->info('Set job index', ['index' => $index]);
            return true;
        }
        return false;
    }

    /**
     * Update job configuration
     *
     * @param array $jobs New jobs configuration
     * @return bool True if update was successful
     */
    public function update_jobs($jobs) {
        if (is_array($jobs) && !empty($jobs)) {
            update_option(self::JOBS_OPTION_KEY, $jobs, false);
            $this->logger->info('Updated jobs configuration', ['job_count' => count($jobs)]);
            return true;
        }
        return false;
    }

    /**
     * Get cron schedule information
     *
     * @return array Cron schedule information
     */
    public function get_cron_info() {
        $info = $this->get_cron_info_internal();

        // Return a subset of information for public consumption
        return [
            'hook_name' => $info['hook_name'],
            'current_schedule' => $info['current_schedule'],
            'next_scheduled' => $info['next_scheduled'],
            'available_schedules' => $info['available_schedules'],
            'is_scheduled' => $info['is_scheduled'],
        ];
    }

    /**
     * Reschedule cron job with new interval
     *
     * @param string $interval New interval (e.g., 'hourly', 'every_minute')
     * @return bool True if rescheduling was successful
     */
    public function reschedule($interval) {
        // Clear existing schedule using centralized method
        $this->clear_cron_event();

        // Schedule with new interval using centralized method
        $scheduled = $this->schedule_cron_event($interval);

        if ($scheduled) {
            $this->logger->info('Rescheduled sync hook', ['interval' => $interval]);
            return true;
        }

        $this->logger->error('Failed to reschedule sync hook', ['interval' => $interval]);
        return false;
    }

    /**
     * Force reschedule cron job - aggressive cleanup and rescheduling
     * Use this when normal rescheduling fails
     *
     * @param string $interval New interval (e.g., 'hourly', 'every_minute')
     * @return bool True if rescheduling was successful
     */
    public function force_reschedule($interval) {
        $this->logger->info('Force rescheduling sync hook', ['interval' => $interval]);

        // Get current state before cleanup
        $before_debug = $this->debug_cron_system();

        // Aggressive cleanup - clear all instances of our hook
        $this->clear_cron_event();

        // Double-check and clear again if needed
        if (wp_next_scheduled(self::HOOK_NAME)) {
            $this->logger->warning('Cron event still exists after clear, forcing removal');
            wp_clear_scheduled_hook(self::HOOK_NAME);
        }

        // Ensure custom schedules are registered
        add_filter('cron_schedules', [$this, 'register_cron_schedules']);

        // Try to schedule with a small delay to ensure cleanup is complete
        $scheduled = $this->schedule_cron_event($interval, time() + 5);

        if ($scheduled) {
            $after_debug = $this->debug_cron_system();
            $this->logger->info('Force reschedule successful', [
                'interval' => $interval,
                'before_events' => count($before_debug['cron_events_for_hook']),
                'after_events' => count($after_debug['cron_events_for_hook'])
            ]);
            return true;
        }

        $this->logger->error('Force reschedule failed', [
            'interval' => $interval,
            'debug_info' => $this->debug_cron_system()
        ]);
        return false;
    }

    /**
     * Run sync manually for specific job
     *
     * @param array $job Job to run manually
     * @return array Sync results
     */
    public function run_manual_sync($job = null) {
        $this->logger->info('Starting manual sync');

        if ($job === null) {
            $job = $this->get_next_job();
        }

        if (!$job) {
            return [
                'success' => false,
                'error' => 'No jobs available'
            ];
        }

        try {
            // Get the global sync handler
            if (isset($GLOBALS['shopcommerce_sync'])) {
                $sync_handler = $GLOBALS['shopcommerce_sync'];
                $results = $sync_handler->execute_sync_for_job($job);

                $this->logger->info('Manual sync completed', [
                    'brand' => $job['brand'],
                    'results' => $results
                ]);

                return [
                    'success' => true,
                    'job' => $job,
                    'results' => $results
                ];
            } else {
                throw new Exception('Sync handler not available');
            }
        } catch (Exception $e) {
            $this->logger->error('Manual sync failed', [
                'job' => $job,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'job' => $job,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Debug cron system - returns detailed information about cron state
     *
     * @return array Debug information
     */
 
     public function debug_cron_system() {
        $this->logger->info('Debugging cron system');

        // Check WordPress cron status
        $wp_cron_disabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        
        // Get all cron events
        $cron = _get_cron_array();
        $our_cron_events = [];

        if (is_array($cron)) {
            foreach ($cron as $timestamp => $hooks) {
                foreach ($hooks as $hook => $events) {
                    if ($hook === self::HOOK_NAME) {
                        $our_cron_events[] = [
                            'timestamp' => $timestamp,
                            'datetime' => date('Y-m-d H:i:s', $timestamp),
                            'events' => $events
                        ];
                    }
                }
            }
        }

        // Check if hook is registered
        $hook_registered = has_action(self::HOOK_NAME);

        // Get available schedules
        $schedules = wp_get_schedules();
        $our_schedules = [];
        foreach (['every_minute', 'every_15_minutes', 'every_30_minutes'] as $schedule) {
            if (isset($schedules[$schedule])) {
                $our_schedules[$schedule] = $schedules[$schedule];
            }
        }

        return [
            'hook_name' => self::HOOK_NAME,
            'wp_cron_disabled' => $wp_cron_disabled,
            'hook_registered_as_action' => $hook_registered,
            'cron_events_for_hook' => $our_cron_events,
            'next_scheduled' => wp_next_scheduled(self::HOOK_NAME),
            'next_scheduled_formatted' => wp_next_scheduled(self::HOOK_NAME) ? 
                date('Y-m-d H:i:s', wp_next_scheduled(self::HOOK_NAME)) : null,
            'available_schedules' => array_keys($schedules),
            'our_custom_schedules' => $our_schedules,
            'total_cron_events' => is_array($cron) ? count($cron) : 0,
            'jobs_initialized' => is_array(get_option(self::JOBS_OPTION_KEY)),
            'current_time' => time(),
            'current_time_formatted' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Get plugin file path
     *
     * @return string Plugin file path
     */
    private function get_plugin_file() {
        return dirname(dirname(__FILE__)) . '/index.php';
    }

    /**
     * Get job queue status
     *
     * @return array Job queue status information
     */
    public function get_queue_status() {
        $jobs = $this->get_jobs();
        $current_index = $this->get_job_index();
        $cron_info = $this->get_cron_info_internal(); // Use internal method for complete info

        $current_job = null;
        $next_job = null;

        if (is_array($jobs) && !empty($jobs)) {
            $current_job = $jobs[$current_index] ?? null;
            $next_index = ($current_index + 1) % count($jobs);
            $next_job = $jobs[$next_index] ?? null;
        }

        return [
            'total_jobs' => count($jobs),
            'current_index' => $current_index,
            'current_job' => $current_job,
            'next_job' => $next_job,
            'cron_info' => $cron_info,
            'jobs_list' => $jobs,
        ];
    }

    public function fix_cron_issues() {
        $this->logger->info('Manually fixing cron issues');
        
        try {
            // Re-run activation process
            $this->activate();
            
            $next_run = wp_next_scheduled(self::HOOK_NAME);
            if ($next_run) {
                return [
                    'success' => true,
                    'message' => 'Cron job fixed successfully. Next run: ' . date('Y-m-d H:i:s', $next_run)
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to fix cron job. Check WordPress cron configuration.'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error fixing cron: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Process pending batches automatically
     * This method is called by a separate cron hook for batch processing
     *
     * @return array Processing results
     */
    public function process_pending_batches() {
        $this->logger->info('Starting automatic batch processing');

        try {
            global $shopcommerce_batch_processor;
            if (!$shopcommerce_batch_processor) {
                throw new Exception('Batch processor not available');
            }

            // Process up to 3 batches per cron run to prevent timeouts
            $results = $shopcommerce_batch_processor->process_pending_batches(3);

            // Check for brand completion after processing
            if (isset($results['results']) && !empty($results['results'])) {
                global $shopcommerce_sync;
                if ($shopcommerce_sync) {
                    // Get unique brands from processed batches
                    $processed_brands = [];
                    foreach ($results['results'] as $result) {
                        if (isset($result['brand']) && !in_array($result['brand'], $processed_brands)) {
                            $processed_brands[] = $result['brand'];
                        }
                    }

                    // Check completion for each brand
                    foreach ($processed_brands as $brand) {
                        $shopcommerce_sync->check_and_handle_brand_completion($brand);
                    }
                }
            }

            $this->logger->info('Automatic batch processing completed', $results);
            return $results;

        } catch (Exception $e) {
            $this->logger->error('Automatic batch processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

}