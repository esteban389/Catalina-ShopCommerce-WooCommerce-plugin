<?php

/**
 * Admin Functions for ShopCommerce Sync Plugin
 *
 * Handles admin interface, menu pages, and management functionality.
 *
 * @package ShopCommerce_Sync
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add admin menu items
 */
function shopcommerce_admin_menu() {
    add_menu_page(
        'ShopCommerce Sync',
        'ShopCommerce Sync',
        'manage_options',
        'shopcommerce-sync',
        'shopcommerce_management_page',
        'dashicons-sync',
        30
    );

    add_submenu_page(
        'shopcommerce-sync',
        'Dashboard',
        'Dashboard',
        'manage_options',
        'shopcommerce-sync',
        'shopcommerce_management_page'
    );

    add_submenu_page(
        'shopcommerce-sync',
        'Sync Control',
        'Sync Control',
        'manage_options',
        'shopcommerce-sync-control',
        'shopcommerce_sync_control_page'
    );

    add_submenu_page(
        'shopcommerce-sync',
        'Settings',
        'Settings',
        'manage_options',
        'shopcommerce-sync-settings',
        'shopcommerce_settings_page'
    );
}
add_action('admin_menu', 'shopcommerce_admin_menu');

/**
 * Enqueue admin styles and scripts
 */
function shopcommerce_admin_enqueue_scripts($hook) {
    // Only load on our plugin pages
    if (strpos($hook, 'shopcommerce-sync') === false) {
        return;
    }

    // Enqueue admin styles
    wp_enqueue_style(
        'shopcommerce-admin',
        SHOPCOMMERCE_SYNC_ASSETS_DIR . 'css/admin.css',
        [],
        SHOPCOMMERCE_SYNC_VERSION
    );

    // Enqueue admin scripts
    wp_enqueue_script(
        'shopcommerce-admin',
        SHOPCOMMERCE_SYNC_ASSETS_DIR . 'js/admin.js',
        ['jquery'],
        SHOPCOMMERCE_SYNC_VERSION,
        true
    );

    // Localize script with AJAX URL and nonce
    wp_localize_script('shopcommerce-admin', 'shopcommerce_admin', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('shopcommerce_admin_nonce'),
        'plugin_url' => SHOPCOMMERCE_SYNC_PLUGIN_URL
    ]);
}
add_action('admin_enqueue_scripts', 'shopcommerce_admin_enqueue_scripts');

/**
 * Main management page
 */
function shopcommerce_management_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Get sync handler
    $sync_handler = isset($GLOBALS['shopcommerce_sync']) ? $GLOBALS['shopcommerce_sync'] : null;
    $logger = isset($GLOBALS['shopcommerce_logger']) ? $GLOBALS['shopcommerce_logger'] : null;

    // Get statistics
    $statistics = $sync_handler ? $sync_handler->get_sync_statistics() : [];
    $activity_log = $logger ? $logger->get_activity_log(20) : [];

    include SHOPCOMMERCE_SYNC_PLUGIN_DIR . 'admin/templates/dashboard.php';
}

/**
 * Sync control page
 */
function shopcommerce_sync_control_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Get handlers
    $sync_handler = isset($GLOBALS['shopcommerce_sync']) ? $GLOBALS['shopcommerce_sync'] : null;
    $cron_scheduler = isset($GLOBALS['shopcommerce_cron']) ? $GLOBALS['shopcommerce_cron'] : null;
    $api_client = isset($GLOBALS['shopcommerce_api']) ? $GLOBALS['shopcommerce_api'] : null;

    include SHOPCOMMERCE_SYNC_PLUGIN_DIR . 'admin/templates/sync-control.php';
}

/**
 * Settings page
 */
function shopcommerce_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    include SHOPCOMMERCE_SYNC_PLUGIN_DIR . 'admin/templates/settings.php';
}

/**
 * Register AJAX handlers
 */
function shopcommerce_register_ajax_handlers() {
    // Test connection
    add_action('wp_ajax_shopcommerce_test_connection', 'shopcommerce_ajax_test_connection');

    // Run manual sync
    add_action('wp_ajax_shopcommerce_run_sync', 'shopcommerce_ajax_run_sync');

    // Get queue status
    add_action('wp_ajax_shopcommerce_queue_status', 'shopcommerce_ajax_queue_status');

    // Clear cache
    add_action('wp_ajax_shopcommerce_clear_cache', 'shopcommerce_ajax_clear_cache');

    // Update settings
    add_action('wp_ajax_shopcommerce_update_settings', 'shopcommerce_ajax_update_settings');

    // Get activity log
    add_action('wp_ajax_shopcommerce_activity_log', 'shopcommerce_ajax_activity_log');

    // Reset jobs
    add_action('wp_ajax_shopcommerce_reset_jobs', 'shopcommerce_ajax_reset_jobs');
}
add_action('admin_init', 'shopcommerce_register_ajax_handlers');

/**
 * AJAX handler for testing connection
 */
function shopcommerce_ajax_test_connection() {
    check_ajax_referer('shopcommerce_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $sync_handler = isset($GLOBALS['shopcommerce_sync']) ? $GLOBALS['shopcommerce_sync'] : null;
    if (!$sync_handler) {
        wp_send_json_error(['message' => 'Sync handler not available']);
    }

    $results = $sync_handler->test_connection();
    wp_send_json_success($results);
}

/**
 * AJAX handler for running manual sync
 */
function shopcommerce_ajax_run_sync() {
    check_ajax_referer('shopcommerce_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $sync_handler = isset($GLOBALS['shopcommerce_sync']) ? $GLOBALS['shopcommerce_sync'] : null;
    if (!$sync_handler) {
        wp_send_json_error(['message' => 'Sync handler not available']);
    }

    $cron_scheduler = isset($GLOBALS['shopcommerce_cron']) ? $GLOBALS['shopcommerce_cron'] : null;
    if (!$cron_scheduler) {
        wp_send_json_error(['message' => 'Cron scheduler not available']);
    }

    $full_sync = isset($_POST['full_sync']) && filter_var($_POST['full_sync'], FILTER_VALIDATE_BOOLEAN);
    $batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 100;

    if ($full_sync) {
        $results = $sync_handler->run_full_sync($batch_size);
    } else {
        $results = $cron_scheduler->run_manual_sync();
    }

    wp_send_json_success($results);
}

/**
 * AJAX handler for getting queue status
 */
function shopcommerce_ajax_queue_status() {
    check_ajax_referer('shopcommerce_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $cron_scheduler = isset($GLOBALS['shopcommerce_cron']) ? $GLOBALS['shopcommerce_cron'] : null;
    if (!$cron_scheduler) {
        wp_send_json_error(['message' => 'Cron scheduler not available']);
    }

    $queue_status = $cron_scheduler->get_queue_status();
    wp_send_json_success($queue_status);
}

/**
 * AJAX handler for clearing cache
 */
function shopcommerce_ajax_clear_cache() {
    check_ajax_referer('shopcommerce_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $sync_handler = isset($GLOBALS['shopcommerce_sync']) ? $GLOBALS['shopcommerce_sync'] : null;
    if (!$sync_handler) {
        wp_send_json_error(['message' => 'Sync handler not available']);
    }

    $results = $sync_handler->clear_cache();
    wp_send_json_success($results);
}

/**
 * AJAX handler for updating settings
 */
function shopcommerce_ajax_update_settings() {
    check_ajax_referer('shopcommerce_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $settings = isset($_POST['settings']) ? $_POST['settings'] : [];
    $settings = array_map('sanitize_text_field', $settings);

    // Update WordPress options
    foreach ($settings as $key => $value) {
        update_option('shopcommerce_' . $key, $value);
    }

    wp_send_json_success(['message' => 'Settings updated successfully']);
}

/**
 * AJAX handler for getting activity log
 */
function shopcommerce_ajax_activity_log() {
    check_ajax_referer('shopcommerce_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $logger = isset($GLOBALS['shopcommerce_logger']) ? $GLOBALS['shopcommerce_logger'] : null;
    if (!$logger) {
        wp_send_json_error(['message' => 'Logger not available']);
    }

    $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 50;
    $activity_type = isset($_POST['activity_type']) ? sanitize_text_field($_POST['activity_type']) : null;

    $activity_log = $logger->get_activity_log($limit, $activity_type);
    wp_send_json_success($activity_log);
}

/**
 * AJAX handler for resetting jobs
 */
function shopcommerce_ajax_reset_jobs() {
    check_ajax_referer('shopcommerce_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $cron_scheduler = isset($GLOBALS['shopcommerce_cron']) ? $GLOBALS['shopcommerce_cron'] : null;
    if (!$cron_scheduler) {
        wp_send_json_error(['message' => 'Cron scheduler not available']);
    }

    $cron_scheduler->reset_jobs();
    wp_send_json_success(['message' => 'Jobs reset successfully']);
}

/**
 * Add admin dashboard widget
 */
function shopcommerce_add_dashboard_widget() {
    if (current_user_can('manage_options')) {
        wp_add_dashboard_widget(
            'shopcommerce_dashboard_widget',
            'ShopCommerce Sync Status',
            'shopcommerce_dashboard_widget_content'
        );
    }
}
add_action('wp_dashboard_setup', 'shopcommerce_add_dashboard_widget');

/**
 * Dashboard widget content
 */
function shopcommerce_dashboard_widget_content() {
    $sync_handler = isset($GLOBALS['shopcommerce_sync']) ? $GLOBALS['shopcommerce_sync'] : null;
    $logger = isset($GLOBALS['shopcommerce_logger']) ? $GLOBALS['shopcommerce_logger'] : null;

    if (!$sync_handler || !$logger) {
        echo '<p>ShopCommerce Sync plugin not properly initialized.</p>';
        return;
    }

    $stats = $sync_handler->get_sync_statistics();
    $recent_activities = $logger->get_activity_log(5);

    include SHOPCOMMERCE_SYNC_PLUGIN_DIR . 'admin/templates/dashboard-widget.php';
}

/**
 * Add plugin action links
 */
function shopcommerce_add_action_links($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=shopcommerce-sync-settings') . '">Settings</a>';
    $dashboard_link = '<a href="' . admin_url('admin.php?page=shopcommerce-sync') . '">Dashboard</a>';
    array_unshift($links, $settings_link, $dashboard_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(SHOPCOMMERCE_SYNC_PLUGIN_DIR . 'shopcommerce-sync.php'), 'shopcommerce_add_action_links');

/**
 * Add plugin meta links
 */
function shopcommerce_add_plugin_meta_links($links, $file) {
    if ($file === plugin_basename(SHOPCOMMERCE_SYNC_PLUGIN_DIR . 'shopcommerce-sync.php')) {
        $links[] = '<a href="https://github.com/your-repo/shopcommerce-sync" target="_blank">GitHub</a>';
        $links[] = '<a href="https://wordpress.org/support/plugin/shopcommerce-sync/" target="_blank">Support</a>';
    }
    return $links;
}
add_filter('plugin_row_meta', 'shopcommerce_add_plugin_meta_links', 10, 2);

/**
 * Admin notice for plugin requirements
 */
function shopcommerce_admin_notices() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p><strong>ShopCommerce Sync:</strong> WooCommerce plugin is not active. Please activate WooCommerce to use this plugin.</p>';
        echo '</div>';
        return;
    }

    // Check if plugin is properly initialized
    if (!isset($GLOBALS['shopcommerce_sync'])) {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p><strong>ShopCommerce Sync:</strong> Plugin not properly initialized. Please check plugin requirements.</p>';
        echo '</div>';
        return;
    }

    // Show success notice if plugin was just activated
    if (get_transient('shopcommerce_activated')) {
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>ShopCommerce Sync:</strong> Plugin activated successfully! <a href="' . admin_url('admin.php?page=shopcommerce-sync') . '">Go to Dashboard</a></p>';
        echo '</div>';
        delete_transient('shopcommerce_activated');
    }
}
add_action('admin_notices', 'shopcommerce_admin_notices');

/**
 * Set activation transient
 */
function shopcommerce_set_activation_transient() {
    set_transient('shopcommerce_activated', true, 30);
}
add_action('shopcommerce_sync_activate', 'shopcommerce_set_activation_transient');