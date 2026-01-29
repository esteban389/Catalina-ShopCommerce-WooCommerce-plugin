<?php
/**
 * ShopCommerce Immediate Sync Script
 * 
 * This script executes the next sync job immediately (synchronously) without batching.
 * Can be executed via WP-CLI or as a standalone PHP script.
 * 
 * Usage:
 *   WP-CLI: wp eval-file sync-immediate.php
 *   PHP:    php sync-immediate.php
 * 
 * IMPORTANT: This script cannot be executed via web browser for security reasons.
 */

// Prevent web execution
if (isset($_SERVER['REQUEST_METHOD'])) {
    die('This script can only be executed from command line.');
}

// Check if running via WP-CLI
$is_wp_cli = defined('WP_CLI') && WP_CLI;

if ($is_wp_cli) {
    // WP-CLI execution - set execution limits
    @set_time_limit(0);
    ini_set('memory_limit', '512M');
    
    // WordPress is already loaded by WP-CLI
    // Plugin should be initialized
} else {
    // Standalone PHP script execution - load WordPress
    // Find WordPress installation (assuming script is in plugin root)
    $wp_load_paths = [
        dirname(__FILE__) . '/../../../../wp-load.php',  // Standard WordPress structure
        dirname(__FILE__) . '/../../../wp-load.php',      // Alternative structure
        dirname(__FILE__) . '/../../wp-load.php',         // Another alternative
        '/Users/esteban389/Local Sites/cata-wp/app/public/wp-load.php'
    ];
    
    $wp_loaded = false;
    foreach ($wp_load_paths as $wp_path) {
        if (file_exists($wp_path)) {
            require_once $wp_path;
            $wp_loaded = true;
            break;
        }
    }
    
    if (!$wp_loaded) {
        die("Error: Could not find wp-load.php. Please ensure WordPress is installed.\n");
    }
    
    // Set execution limits for standalone execution
    @set_time_limit(0);
    ini_set('memory_limit', '512M');
}

// Ensure plugin is loaded
if (!defined('SHOPCOMMERCE_SYNC_PLUGIN_DIR')) {
    die("Error: ShopCommerce plugin is not active or not found.\n");
}

// Check if required classes exist
if (!class_exists('ShopCommerce_Sync') || !class_exists('ShopCommerce_Cron')) {
    die("Error: ShopCommerce plugin classes not found. Please ensure the plugin is active.\n");
}

// Initialize plugin if not already initialized (for standalone execution)
if (!isset($GLOBALS['shopcommerce_logger'])) {
    // Trigger plugin initialization
    do_action('init');
}

// Get plugin instances
$logger = isset($GLOBALS['shopcommerce_logger']) ? $GLOBALS['shopcommerce_logger'] : null;
$sync_handler = isset($GLOBALS['shopcommerce_sync']) ? $GLOBALS['shopcommerce_sync'] : null;
$cron_scheduler = isset($GLOBALS['shopcommerce_cron']) ? $GLOBALS['shopcommerce_cron'] : null;

if (!$logger || !$sync_handler || !$cron_scheduler) {
    // Try to manually initialize if globals are not set
    if (function_exists('shopcommerce_sync_init')) {
        shopcommerce_sync_init();
        $logger = isset($GLOBALS['shopcommerce_logger']) ? $GLOBALS['shopcommerce_logger'] : null;
        $sync_handler = isset($GLOBALS['shopcommerce_sync']) ? $GLOBALS['shopcommerce_sync'] : null;
        $cron_scheduler = isset($GLOBALS['shopcommerce_cron']) ? $GLOBALS['shopcommerce_cron'] : null;
    }
    
    if (!$logger || !$sync_handler || !$cron_scheduler) {
        die("Error: ShopCommerce plugin instances not initialized. Please ensure the plugin is properly activated.\n");
    }
}

// Get next job
$job = $cron_scheduler->get_next_job();

if (!$job) {
    echo "No jobs available for sync.\n";
    exit(1);
}

echo "Starting immediate sync for brand: {$job['brand']}\n";
if (!empty($job['categories'])) {
    echo "Categories: " . implode(', ', $job['categories']) . "\n";
}
echo "Processing mode: Immediate (synchronous)\n";
echo str_repeat('-', 50) . "\n";

// Execute immediate sync
$start_time = microtime(true);
$results = $sync_handler->execute_sync_for_job_immediate($job);
$execution_time = round(microtime(true) - $start_time, 2);

// Display results
echo "\n" . str_repeat('=', 50) . "\n";
echo "Sync Results:\n";
echo str_repeat('=', 50) . "\n";
echo "Brand: {$job['brand']}\n";
echo "Success: " . ($results['success'] ? 'Yes' : 'No') . "\n";

if ($results['success']) {
    echo "Catalog Count: " . ($results['catalog_count'] ?? 0) . "\n";
    echo "Processed: " . ($results['processed_count'] ?? 0) . "\n";
    echo "Created: " . ($results['created'] ?? 0) . "\n";
    echo "Updated: " . ($results['updated'] ?? 0) . "\n";
    echo "Errors: " . ($results['errors'] ?? 0) . "\n";
    echo "Skipped: " . ($results['skipped'] ?? 0) . "\n";
} else {
    echo "Error: " . ($results['error'] ?? 'Unknown error') . "\n";
}

echo "Execution Time: {$execution_time} seconds\n";
echo str_repeat('=', 50) . "\n";

// Exit with appropriate code
exit($results['success'] ? 0 : 1);

