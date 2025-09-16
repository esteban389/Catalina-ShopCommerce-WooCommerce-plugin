<?php

/**
 * Plugin Name:       ShopCommerce Product Sync Plugin
 * Description:       A plugin to sync products from ShopCommerce with WooCommerce, specially for Hekalsoluciones.
 * Version:           2.4.0
 * Author:            Esteban Andres Murcia Acuña
 * Author URI:        https://estebanmurcia.dev/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       shopcommerce-product-sync-plugin
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SHOPCOMMERCE_SYNC_VERSION', '2.0.0');
define('SHOPCOMMERCE_SYNC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SHOPCOMMERCE_SYNC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SHOPCOMMERCE_SYNC_INCLUDES_DIR', SHOPCOMMERCE_SYNC_PLUGIN_DIR . 'includes/');
define('SHOPCOMMERCE_SYNC_ASSETS_DIR', SHOPCOMMERCE_SYNC_PLUGIN_URL . 'assets/');
define('SHOPCOMMERCE_SYNC_LOGS_DIR', SHOPCOMMERCE_SYNC_PLUGIN_DIR . 'logs/');

// Include the main classes
require_once SHOPCOMMERCE_SYNC_INCLUDES_DIR . 'class-shopcommerce-logger.php';
require_once SHOPCOMMERCE_SYNC_INCLUDES_DIR . 'class-shopcommerce-api.php';
require_once SHOPCOMMERCE_SYNC_INCLUDES_DIR . 'class-shopcommerce-helpers.php';
require_once SHOPCOMMERCE_SYNC_INCLUDES_DIR . 'class-shopcommerce-product.php';
require_once SHOPCOMMERCE_SYNC_INCLUDES_DIR . 'class-shopcommerce-cron.php';
require_once SHOPCOMMERCE_SYNC_INCLUDES_DIR . 'class-shopcommerce-sync.php';
require_once SHOPCOMMERCE_SYNC_INCLUDES_DIR . 'class-shopcommerce-config.php';

// Include admin functions
require_once SHOPCOMMERCE_SYNC_INCLUDES_DIR . 'functions-admin.php';

// Include order functions
require_once SHOPCOMMERCE_SYNC_INCLUDES_DIR . 'functions-orders.php';

// Initialize the plugin
function shopcommerce_sync_init()
{
    // Initialize the logger first
    $logger = new ShopCommerce_Logger();

    // Initialize configuration manager
    $config_manager = new ShopCommerce_Config($logger);

    // Initialize the API client
    $api_client = new ShopCommerce_API($logger);

    // Initialize helpers
    $helpers = new ShopCommerce_Helpers($logger);

    // Initialize product handler
    $product_handler = new ShopCommerce_Product($logger, $helpers);

    // Initialize cron scheduler
    $cron_scheduler = new ShopCommerce_Cron($logger);

    // Initialize main sync logic
    $sync_handler = new ShopCommerce_Sync($logger, $api_client, $product_handler, $cron_scheduler);

    // Make instances available globally if needed
    $GLOBALS['shopcommerce_logger'] = $logger;
    $GLOBALS['shopcommerce_config'] = $config_manager;
    $GLOBALS['shopcommerce_api'] = $api_client;
    $GLOBALS['shopcommerce_helpers'] = $helpers;
    $GLOBALS['shopcommerce_product'] = $product_handler;
    $GLOBALS['shopcommerce_cron'] = $cron_scheduler;
    $GLOBALS['shopcommerce_sync'] = $sync_handler;
}

// Initialize plugin on WordPress 'init' hook
add_action('init', 'shopcommerce_sync_init');

// Plugin activation hook
register_activation_hook(__FILE__, 'shopcommerce_sync_activate');

function shopcommerce_sync_activate()
{
    // Create logs directory if it doesn't exist
    if (!file_exists(SHOPCOMMERCE_SYNC_LOGS_DIR)) {
        wp_mkdir_p(SHOPCOMMERCE_SYNC_LOGS_DIR);
    }

    // Initialize cron scheduler
    if (class_exists('ShopCommerce_Cron')) {
        $logger = new ShopCommerce_Logger();
        $cron_scheduler = new ShopCommerce_Cron($logger);
        $cron_scheduler->activate();
    }
}

// Hook into WooCommerce order completion for metadata and logging
add_action('woocommerce_order_status_completed', 'shopcommerce_handle_order_completion');

// Also hook into order processing for partial fulfillment tracking
add_action('woocommerce_order_status_processing', 'shopcommerce_handle_order_completion');

// Hook into order creation for logging
add_action('woocommerce_new_order', 'shopcommerce_handle_order_creation');

// Plugin deactivation hook
register_deactivation_hook(__FILE__, 'shopcommerce_sync_deactivate');

function shopcommerce_sync_deactivate()
{
    // Deinitialize cron scheduler
    if (class_exists('ShopCommerce_Cron')) {
        $logger = new ShopCommerce_Logger();
        $cron_scheduler = new ShopCommerce_Cron($logger);
        $cron_scheduler->deactivate();
    }
}
