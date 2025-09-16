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
    const DEFAULT_INTERVAL = 'hourly';

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
     * Constructor
     *
     * @param ShopCommerce_Logger $logger Logger instance
     */
    public function __construct($logger) {
        $this->logger = $logger;

        // Register the main cron action
        add_action(self::HOOK_NAME, [$this, 'execute_sync_hook']);
    }

    /**
     * Plugin activation hook
     */
    public function activate() {
        $this->logger->info('Activating ShopCommerce sync plugin');

        // Initialize jobs
        $this->initialize_jobs();

        // Register custom cron schedules
        add_filter('cron_schedules', [$this, 'register_cron_schedules']);

        // Schedule the main sync hook
        if (!wp_next_scheduled(self::HOOK_NAME)) {
            wp_schedule_event(time(), self::DEFAULT_INTERVAL, self::HOOK_NAME);
            $this->logger->info('Scheduled main sync hook', [
                'hook' => self::HOOK_NAME,
                'interval' => self::DEFAULT_INTERVAL
            ]);
        }
    }

    /**
     * Plugin deactivation hook
     */
    public function deactivate() {
        $this->logger->info('Deactivating ShopCommerce sync plugin');

        // Clear scheduled hooks
        wp_clear_scheduled_hook(self::HOOK_NAME);
        $this->logger->info('Cleared scheduled sync hook');
    }

    /**
     * Register custom cron schedules
     *
     * @param array $schedules Existing schedules
     * @return array Modified schedules
     */
    public function register_cron_schedules($schedules) {
        $schedules['every_minute'] = [
            'interval' => 60,
            'display' => __('Every Minute', 'shopcommerce-product-sync-plugin')
        ];

        $schedules['every_15_minutes'] = [
            'interval' => 900,
            'display' => __('Every 15 Minutes', 'shopcommerce-product-sync-plugin')
        ];

        $schedules['every_30_minutes'] = [
            'interval' => 1800,
            'display' => __('Every 30 Minutes', 'shopcommerce-product-sync-plugin')
        ];

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
            // Get the global sync handler
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
        // Use dynamic configuration from config manager
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
        $cron_schedule = wp_get_schedules();
        $current_schedule = wp_get_schedule(self::HOOK_NAME);
        $next_scheduled = wp_next_scheduled(self::HOOK_NAME);

        return [
            'hook_name' => self::HOOK_NAME,
            'current_schedule' => $current_schedule,
            'next_scheduled' => $next_scheduled ? date('Y-m-d H:i:s', $next_scheduled) : null,
            'available_schedules' => array_keys($cron_schedule),
            'is_scheduled' => $next_scheduled !== false,
        ];
    }

    /**
     * Reschedule cron job with new interval
     *
     * @param string $interval New interval (e.g., 'hourly', 'every_minute')
     * @return bool True if rescheduling was successful
     */
    public function reschedule($interval) {
        // Clear existing schedule
        wp_clear_scheduled_hook(self::HOOK_NAME);

        // Schedule with new interval
        $scheduled = wp_schedule_event(time(), $interval, self::HOOK_NAME);

        if ($scheduled) {
            $this->logger->info('Rescheduled sync hook', [
                'interval' => $interval,
                'next_run' => date('Y-m-d H:i:s', wp_next_scheduled(self::HOOK_NAME))
            ]);
            return true;
        }

        $this->logger->error('Failed to reschedule sync hook', ['interval' => $interval]);
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
     * Get job queue status
     *
     * @return array Job queue status information
     */
    public function get_queue_status() {
        $jobs = $this->get_jobs();
        $current_index = $this->get_job_index();
        $cron_info = $this->get_cron_info();

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
}