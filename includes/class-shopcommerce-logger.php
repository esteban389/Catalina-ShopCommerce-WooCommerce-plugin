<?php

/**
 * ShopCommerce Logger Class
 *
 * Handles all logging functionality for the plugin including error logging,
 * activity tracking, and log management.
 *
 * @package ShopCommerce_Sync
 */

class ShopCommerce_Logger {

    /**
     * Log levels
     */
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    const LEVEL_CRITICAL = 'critical';

    /**
     * Activity log option key
     */
    const ACTIVITY_LOG_KEY = 'shopcommerce_activity_log';

    /**
     * Maximum number of activity log entries to keep
     */
    const MAX_ACTIVITY_ENTRIES = 1000;

    /**
     * Constructor
     */
    public function __construct() {
        // Create logs directory if it doesn't exist
        if (!file_exists(SHOPCOMMERCE_SYNC_LOGS_DIR)) {
            wp_mkdir_p(SHOPCOMMERCE_SYNC_LOGS_DIR);
        }
    }

    /**
     * Log a message with the specified level
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context data
     * @param bool $to_file Whether to also log to file
     */
    public function log($level, $message, $context = [], $to_file = true) {
        $timestamp = current_time('mysql');
        $log_entry = [
            'timestamp' => $timestamp,
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];

        // Log to WordPress error log
        if ($to_file) {
            $log_message = sprintf('[ShopCommerce %s] %s', strtoupper($level), $message);
            if (!empty($context)) {
                $log_message .= ' | Context: ' . json_encode($context);
            }
            error_log($log_message);
        }

        // Log to file for persistent storage
        $this->log_to_file($log_entry);

        // Log critical errors to WordPress debug log
        if ($level === self::LEVEL_ERROR || $level === self::LEVEL_CRITICAL) {
            $this->log_to_debug_log($log_entry);
        }
    }

    /**
     * Log debug message
     *
     * @param string $message Debug message
     * @param array $context Additional context data
     */
    public function debug($message, $context = []) {
        $this->log(self::LEVEL_DEBUG, $message, $context, WP_DEBUG);
    }

    /**
     * Log info message
     *
     * @param string $message Info message
     * @param array $context Additional context data
     */
    public function info($message, $context = []) {
        $this->log(self::LEVEL_INFO, $message, $context);
    }

    /**
     * Log warning message
     *
     * @param string $message Warning message
     * @param array $context Additional context data
     */
    public function warning($message, $context = []) {
        $this->log(self::LEVEL_WARNING, $message, $context);
    }

    /**
     * Log error message
     *
     * @param string $message Error message
     * @param array $context Additional context data
     */
    public function error($message, $context = []) {
        $this->log(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * Log critical message
     *
     * @param string $message Critical message
     * @param array $context Additional context data
     */
    public function critical($message, $context = []) {
        $this->log(self::LEVEL_CRITICAL, $message, $context);
    }

    /**
     * Log activity for management interface
     *
     * @param string $activity_type Type of activity
     * @param string $description Activity description
     * @param array $data Additional activity data
     */
    public function log_activity($activity_type, $description, $data = []) {
        $activity = [
            'timestamp' => current_time('mysql'),
            'type' => $activity_type,
            'description' => $description,
            'data' => $data,
        ];

        // Get existing activities
        $activities = get_option(self::ACTIVITY_LOG_KEY, []);

        // Add new activity
        array_unshift($activities, $activity);

        // Limit the number of activities
        if (count($activities) > self::MAX_ACTIVITY_ENTRIES) {
            $activities = array_slice($activities, 0, self::MAX_ACTIVITY_ENTRIES);
        }

        // Save activities
        update_option(self::ACTIVITY_LOG_KEY, $activities, false);

        // Also log as info
        $this->info("Activity: $activity_type - $description", $data);
    }

    /**
     * Get activity log entries
     *
     * @param int $limit Number of entries to retrieve
     * @param string $activity_type Filter by activity type
     * @param int $offset Offset for pagination
     * @return array Activity log entries
     */
    public function get_activity_log($limit = 50, $activity_type = null, $offset = 0) {
        $activities = get_option(self::ACTIVITY_LOG_KEY, []);

        // Filter by activity type if specified
        if ($activity_type !== null) {
            $activities = array_filter($activities, function($activity) use ($activity_type) {
                return $activity['type'] === $activity_type;
            });
        }

        // Reset array keys to ensure proper offset
        $activities = array_values($activities);

        // Apply offset and limit
        return array_slice($activities, $offset, $limit);
    }

    /**
     * Get activity log count
     *
     * @param string $activity_type Filter by activity type
     * @return int Number of activities
     */
    public function get_activity_count($activity_type = null) {
        $activities = get_option(self::ACTIVITY_LOG_KEY, []);

        // Filter by activity type if specified
        if ($activity_type !== null) {
            $activities = array_filter($activities, function($activity) use ($activity_type) {
                return $activity['type'] === $activity_type;
            });
        }

        return count($activities);
    }

    /**
     * Clear activity log
     */
    public function clear_activity_log() {
        delete_option(self::ACTIVITY_LOG_KEY);
        $this->info('Activity log cleared');
    }

    /**
     * Log to file
     *
     * @param array $log_entry Log entry to write to file
     */
    private function log_to_file($log_entry) {
        $log_file = SHOPCOMMERCE_SYNC_LOGS_DIR . 'shopcommerce-sync.log';
        $log_line = json_encode($log_entry) . PHP_EOL;

        // Append to log file
        file_put_contents($log_file, $log_line, FILE_APPEND);
    }

    /**
     * Log to WordPress debug log
     *
     * @param array $log_entry Log entry to write to debug log
     */
    private function log_to_debug_log($log_entry) {
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            $debug_log_file = WP_CONTENT_DIR . '/debug.log';
            $log_line = json_encode($log_entry) . PHP_EOL;
            file_put_contents($debug_log_file, $log_line, FILE_APPEND);
        }
    }

    /**
     * Get log file contents
     *
     * @param int $lines Number of lines to retrieve
     * @return array Log file lines
     */
    public function get_log_file_contents($lines = 100) {
        $log_file = SHOPCOMMERCE_SYNC_LOGS_DIR . 'shopcommerce-sync.log';

        if (!file_exists($log_file)) {
            return [];
        }

        $file_contents = file_get_contents($log_file);
        $log_lines = array_filter(explode(PHP_EOL, $file_contents));

        // Return the last N lines
        return array_slice($log_lines, -$lines);
    }

    /**
     * Clear log file
     */
    public function clear_log_file() {
        $log_file = SHOPCOMMERCE_SYNC_LOGS_DIR . 'shopcommerce-sync.log';
        if (file_exists($log_file)) {
            file_put_contents($log_file, '');
            $this->info('Log file cleared');
        }
    }

    /**
     * Get plugin statistics
     *
     * @return array Plugin statistics
     */
    public function get_statistics() {
        $activities = get_option(self::ACTIVITY_LOG_KEY, []);

        $stats = [
            'total_activities' => count($activities),
            'activities_by_type' => [],
            'recent_activities' => array_slice($activities, 0, 10),
            'last_sync_time' => null,
            'total_products_synced' => 0,
            'total_errors' => 0,
        ];

        // Analyze activities
        foreach ($activities as $activity) {
            $type = $activity['type'];
            if (!isset($stats['activities_by_type'][$type])) {
                $stats['activities_by_type'][$type] = 0;
            }
            $stats['activities_by_type'][$type]++;

            // Extract sync statistics
            if ($type === 'sync_complete' && isset($activity['data'])) {
                $data = $activity['data'];
                if (isset($data['created'])) {
                    $stats['total_products_synced'] += $data['created'];
                }
                if (isset($data['updated'])) {
                    $stats['total_products_synced'] += $data['updated'];
                }
                if (isset($data['errors'])) {
                    $stats['total_errors'] += $data['errors'];
                }
                $stats['last_sync_time'] = $activity['timestamp'];
            }
        }

        return $stats;
    }
}