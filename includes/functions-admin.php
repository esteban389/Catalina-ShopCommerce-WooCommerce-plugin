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
        'Products',
        'Products',
        'manage_options',
        'shopcommerce-sync-products',
        'shopcommerce_products_page'
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
        'Logs',
        'Logs',
        'manage_options',
        'shopcommerce-sync-logs',
        'shopcommerce_logs_page'
    );

    add_submenu_page(
        'shopcommerce-sync',
        'Brands & Categories',
        'Brands & Categories',
        'manage_options',
        'shopcommerce-sync-brands',
        'shopcommerce_brands_page'
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
    if (strpos($hook, 'shopcommerce-sync') === false && $hook !== 'post.php' && $hook !== 'post-new.php') {
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

    // Enqueue product edit tracking scripts on product edit pages
    if ($hook === 'post.php' || $hook === 'post-new.php') {
        wp_enqueue_script(
            'shopcommerce-product-edit',
            SHOPCOMMERCE_SYNC_ASSETS_DIR . 'js/product-edit.js',
            ['jquery'],
            SHOPCOMMERCE_SYNC_VERSION,
            true
        );

        wp_localize_script('shopcommerce-product-edit', 'shopcommerce_product_edit', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('shopcommerce_product_edit_nonce')
        ]);
    }
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

    // Get pagination parameters
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $activity_filter = isset($_GET['activity_filter']) ? sanitize_text_field($_GET['activity_filter']) : '';
    $per_page = 20;
    $offset = ($current_page - 1) * $per_page;

    // Get activity log with pagination
    $activity_log = $logger ? $logger->get_activity_log($per_page, $activity_filter ?: null, $offset) : [];
    $total_activities = $logger ? $logger->get_activity_count($activity_filter ?: null) : 0;
    $total_pages = max(1, ceil($total_activities / $per_page));

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
 * Products management page
 */
function shopcommerce_products_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    include SHOPCOMMERCE_SYNC_PLUGIN_DIR . 'admin/templates/products.php';
}

/**
 * Logs viewer page
 */
function shopcommerce_logs_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    include SHOPCOMMERCE_SYNC_PLUGIN_DIR . 'admin/templates/logs.php';
}

/**
 * Brands and Categories management page
 */
function shopcommerce_brands_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    include SHOPCOMMERCE_SYNC_PLUGIN_DIR . 'admin/templates/brands.php';
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

    // Load activity log with pagination
    add_action('wp_ajax_shopcommerce_load_activity_log', 'shopcommerce_ajax_load_activity_log');

    // Clear log file
    add_action('wp_ajax_shopcommerce_clear_log_file', 'shopcommerce_ajax_clear_log_file');

    // Reset jobs
    add_action('wp_ajax_shopcommerce_reset_jobs', 'shopcommerce_ajax_reset_jobs');

    // Test XML attributes parsing
    add_action('wp_ajax_shopcommerce_test_xml_attributes', 'shopcommerce_ajax_test_xml_attributes');

    // Brand management
    add_action('wp_ajax_shopcommerce_create_brand', 'shopcommerce_ajax_create_brand');
    add_action('wp_ajax_shopcommerce_update_brand', 'shopcommerce_ajax_update_brand');
    add_action('wp_ajax_shopcommerce_delete_brand', 'shopcommerce_ajax_delete_brand');
    add_action('wp_ajax_shopcommerce_toggle_brand', 'shopcommerce_ajax_toggle_brand');

    // Category management
    add_action('wp_ajax_shopcommerce_create_category', 'shopcommerce_ajax_create_category');
    add_action('wp_ajax_shopcommerce_update_category', 'shopcommerce_ajax_update_category');
    add_action('wp_ajax_shopcommerce_delete_category', 'shopcommerce_ajax_delete_category');
    add_action('wp_ajax_shopcommerce_toggle_category', 'shopcommerce_ajax_toggle_category');

    // Brand-Category relationship management
    add_action('wp_ajax_shopcommerce_update_brand_categories', 'shopcommerce_ajax_update_brand_categories');

    // Job management
    add_action('wp_ajax_shopcommerce_rebuild_jobs', 'shopcommerce_ajax_rebuild_jobs');
    add_action('wp_ajax_shopcommerce_get_sync_jobs', 'shopcommerce_ajax_get_sync_jobs');

    // Manage products
    add_action('wp_ajax_shopcommerce_manage_products', 'shopcommerce_ajax_manage_products');
    add_action('wp_ajax_shopcommerce_bulk_products', 'shopcommerce_ajax_bulk_products');

    // Product edit tracking
    add_action('wp_ajax_shopcommerce_get_product_edit_history', 'shopcommerce_ajax_get_product_edit_history');
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
 * AJAX handler for loading activity log with pagination
 */
function shopcommerce_ajax_load_activity_log() {
    check_ajax_referer('shopcommerce_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $logger = isset($GLOBALS['shopcommerce_logger']) ? $GLOBALS['shopcommerce_logger'] : null;
    if (!$logger) {
        wp_send_json_error(['message' => 'Logger not available']);
    }

    $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
    $filter = isset($_POST['filter']) ? sanitize_text_field($_POST['filter']) : '';
    $per_page = isset($_POST['per_page']) ? min(100, max(1, intval($_POST['per_page']))) : 20;

    $offset = ($page - 1) * $per_page;

    // Get activity log with pagination
    $activity_log = $logger->get_activity_log($per_page, $filter ?: null, $offset);
    $total_activities = $logger->get_activity_count($filter ?: null);
    $total_pages = max(1, ceil($total_activities / $per_page));

    // Format activities for display
    $formatted_activities = [];
    foreach ($activity_log as $activity) {
        $formatted_activities[] = [
            'id' => $activity['id'] ?? null,
            'timestamp' => $activity['timestamp'],
            'formatted_time' => date_i18n('M j, Y g:i A', strtotime($activity['timestamp'])),
            'type' => $activity['type'],
            'type_label' => ucfirst(str_replace('_', ' ', $activity['type'])),
            'description' => $activity['description'],
            'has_data' => !empty($activity['data']),
            'raw_data' => $activity
        ];
    }

    // Calculate displaying text
    $start_item = $total_activities > 0 ? $offset + 1 : 0;
    $end_item = min($offset + $per_page, $total_activities);
    $displaying_text = sprintf(
        _n(
            '%s item',
            '%s items',
            $total_activities,
            'shopcommerce-sync'
        ),
        number_format($total_activities)
    );

    wp_send_json_success([
        'activities' => $formatted_activities,
        'current_page' => $page,
        'total_pages' => $total_pages,
        'total_items' => $total_activities,
        'per_page' => $per_page,
        'displaying_text' => $displaying_text,
        'showing_items' => $total_activities > 0 ? sprintf(
            'Showing %d-%d of %d items',
            $start_item,
            $end_item,
            $total_activities
        ) : 'No items to display'
    ]);
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
 * AJAX handler for clearing log file
 */
function shopcommerce_ajax_clear_log_file() {
    check_ajax_referer('shopcommerce_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $logger = isset($GLOBALS['shopcommerce_logger']) ? $GLOBALS['shopcommerce_logger'] : null;
    if (!$logger) {
        wp_send_json_error(['message' => 'Logger not available']);
    }

    $logger->clear_log_file();
    wp_send_json_success(['message' => 'Log file cleared successfully']);
}

/**
 * AJAX handler for testing XML attributes parsing
 */
function shopcommerce_ajax_test_xml_attributes() {
    check_ajax_referer('shopcommerce_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $helpers = isset($GLOBALS['shopcommerce_helpers']) ? $GLOBALS['shopcommerce_helpers'] : null;
    if (!$helpers) {
        wp_send_json_error(['message' => 'Helpers not available']);
    }

    $test_json = isset($_POST['test_json']) ? stripslashes($_POST['test_json']) : null;

    if ($test_json) {
        // Test with provided JSON
        $parsed = $helpers->parse_xml_attributes($test_json);
        $formatted = $helpers->format_xml_attributes_html($parsed);

        wp_send_json_success([
            'test_type' => 'custom',
            'original_json' => $test_json,
            'parsed_attributes' => $parsed,
            'formatted_html' => $formatted,
            'parse_success' => !empty($parsed),
            'format_success' => !empty($formatted)
        ]);
    } else {
        // Test with sample data
        $results = $helpers->test_xml_attributes_parsing();
        wp_send_json_success($results);
    }
}

/**
 * AJAX handler for managing individual products
 */
function shopcommerce_ajax_manage_products() {
    check_ajax_referer('shopcommerce_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $product_action = isset($_POST['product_action']) ? sanitize_text_field($_POST['product_action']) : '';
    $product_ids = isset($_POST['product_ids']) ? array_map('intval', (array) $_POST['product_ids']) : [];

    if (empty($product_action) || empty($product_ids)) {
        wp_send_json_error(['message' => 'Invalid action or product IDs']);
    }

    $results = [
        'success' => 0,
        'failed' => 0,
        'errors' => []
    ];

    foreach ($product_ids as $product_id) {
        try {
            switch ($product_action) {
                case 'trash':
                    $result = wp_trash_post($product_id);
                    break;
                case 'delete':
                    $result = wp_delete_post($product_id, true);
                    break;
                case 'publish':
                    $result = wp_publish_post($product_id);
                    break;
                case 'draft':
                    $post = get_post($product_id);
                    $post->post_status = 'draft';
                    $result = wp_update_post($post);
                    break;
                default:
                    $result = false;
                    $results['errors'][] = "Invalid action: $product_action";
                    break;
            }

            if ($result !== false && !is_wp_error($result)) {
                $results['success']++;
            } else {
                $results['failed']++;
                if (is_wp_error($result)) {
                    $results['errors'][] = $result->get_error_message();
                }
            }
        } catch (Exception $e) {
            $results['failed']++;
            $results['errors'][] = $e->getMessage();
        }
    }

    $message = sprintf(
        'Action completed: %d products processed successfully, %d failed.',
        $results['success'],
        $results['failed']
    );

    if (!empty($results['errors'])) {
        $message .= ' Errors: ' . implode(', ', array_slice($results['errors'], 0, 3));
    }

    wp_send_json_success([
        'message' => $message,
        'results' => $results
    ]);
}

/**
 * AJAX handler for bulk product actions
 */
function shopcommerce_ajax_bulk_products() {
    check_ajax_referer('shopcommerce_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $bulk_action = isset($_POST['bulk_action']) ? sanitize_text_field($_POST['bulk_action']) : '';
    $product_ids = isset($_POST['product_ids']) ? array_map('intval', (array) $_POST['product_ids']) : [];

    if (empty($bulk_action) || empty($product_ids) || $bulk_action === '-1') {
        wp_send_json_error(['message' => 'Invalid bulk action or no products selected']);
    }

    $results = [
        'success' => 0,
        'failed' => 0,
        'errors' => []
    ];

    foreach ($product_ids as $product_id) {
        try {
            switch ($bulk_action) {
                case 'trash':
                    $result = wp_trash_post($product_id);
                    break;
                case 'delete':
                    $result = wp_delete_post($product_id, true);
                    break;
                case 'publish':
                    $result = wp_publish_post($product_id);
                    break;
                case 'draft':
                    $post = get_post($product_id);
                    $post->post_status = 'draft';
                    $result = wp_update_post($post);
                    break;
                default:
                    $result = false;
                    $results['errors'][] = "Invalid bulk action: $bulk_action";
                    break;
            }

            if ($result !== false && !is_wp_error($result)) {
                $results['success']++;
            } else {
                $results['failed']++;
                if (is_wp_error($result)) {
                    $results['errors'][] = $result->get_error_message();
                }
            }
        } catch (Exception $e) {
            $results['failed']++;
            $results['errors'][] = $e->getMessage();
        }
    }

    $message = sprintf(
        'Bulk action completed: %d products processed successfully, %d failed.',
        $results['success'],
        $results['failed']
    );

    if (!empty($results['errors'])) {
        $message .= ' Errors: ' . implode(', ', array_slice($results['errors'], 0, 3));
    }

    wp_send_json_success([
        'message' => $message,
        'results' => $results
    ]);
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

/**
 * Track product edits for ShopCommerce products
 */
function shopcommerce_track_product_edits($post_id, $post, $update) {
    // Only track product post types
    if ($post->post_type !== 'product') {
        return;
    }

    // Check if this is a ShopCommerce product
    $external_provider = get_post_meta($post_id, '_external_provider', true);
    if ($external_provider !== 'shopcommerce') {
        return;
    }

    // Get current product data
    $product = wc_get_product($post_id);
    if (!$product) {
        return;
    }

    // Get previous values
    $previous_values = get_post_meta($post_id, '_shopcommerce_previous_values', true);
    if (empty($previous_values)) {
        $previous_values = [];
    }

    $changed_fields = [];
    $current_values = [
        'name' => $product->get_name(),
        'description' => $product->get_description(),
        'price' => $product->get_price(),
        'stock_quantity' => $product->get_stock_quantity(),
        'stock_status' => $product->get_stock_status(),
        'sku' => $product->get_sku(),
        'status' => $product->get_status(),
    ];

    // Compare each field
    foreach ($current_values as $field => $current_value) {
        if (isset($previous_values[$field])) {
            $previous_value = $previous_values[$field];
            if ($previous_value !== $current_value) {
                $changed_fields[] = [
                    'field' => $field,
                    'previous_value' => $previous_value,
                    'new_value' => $current_value,
                    'changed_at' => current_time('mysql')
                ];
            }
        }
    }

    // Save changed fields
    if (!empty($changed_fields)) {
        $existing_changes = get_post_meta($post_id, '_shopcommerce_edited_fields', true);
        if (empty($existing_changes)) {
            $existing_changes = [];
        }

        $existing_changes = array_merge($existing_changes, $changed_fields);
        update_post_meta($post_id, '_shopcommerce_edited_fields', $existing_changes);
        update_post_meta($post_id, '_shopcommerce_last_edited', current_time('mysql'));
        update_post_meta($post_id, '_shopcommerce_edited_by', get_current_user_id());
    }

    // Store current values for next comparison
    update_post_meta($post_id, '_shopcommerce_previous_values', $current_values);
}
add_action('save_post', 'shopcommerce_track_product_edits', 10, 3);

/**
 * AJAX handler for getting product edit history
 */
function shopcommerce_ajax_get_product_edit_history() {
    check_ajax_referer('shopcommerce_product_edit_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    if (!$product_id) {
        wp_send_json_error(['message' => 'Invalid product ID']);
    }

    $edited_fields = get_post_meta($product_id, '_shopcommerce_edited_fields', true);
    $last_edited = get_post_meta($product_id, '_shopcommerce_last_edited', true);
    $edited_by = get_post_meta($product_id, '_shopcommerce_edited_by', true);

    $editor_name = '';
    if ($edited_by) {
        $editor = get_user_by('id', $edited_by);
        $editor_name = $editor ? $editor->display_name : 'Unknown';
    }

    wp_send_json_success([
        'edited_fields' => $edited_fields ?: [],
        'last_edited' => $last_edited,
        'edited_by' => $editor_name,
        'total_changes' => is_array($edited_fields) ? count($edited_fields) : 0
    ]);
}

/**
 * AJAX handler for creating a brand
 */
function shopcommerce_ajax_create_brand() {
    check_ajax_referer('shopcommerce_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $config = isset($GLOBALS['shopcommerce_config']) ? $GLOBALS['shopcommerce_config'] : null;
    if (!$config) {
        wp_send_json_error(['message' => 'Configuration manager not available']);
    }

    $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
    $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';

    if (empty($name)) {
        wp_send_json_error(['message' => 'Brand name is required']);
    }

    $brand_id = $config->create_brand($name, $description);

    if ($brand_id) {
        // Rebuild jobs after brand creation
        if (isset($GLOBALS['shopcommerce_cron'])) {
            $GLOBALS['shopcommerce_cron']->rebuild_jobs();
        }

        wp_send_json_success([
            'message' => 'Brand created successfully',
            'brand_id' => $brand_id,
            'brand' => [
                'id' => $brand_id,
                'name' => $name,
                'description' => $description,
                'is_active' => 1,
            ]
        ]);
    } else {
        wp_send_json_error(['message' => 'Failed to create brand']);
    }
}

/**
 * AJAX handler for updating brand categories
 */
function shopcommerce_ajax_update_brand_categories() {
    check_ajax_referer('shopcommerce_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $config = isset($GLOBALS['shopcommerce_config']) ? $GLOBALS['shopcommerce_config'] : null;
    if (!$config) {
        wp_send_json_error(['message' => 'Configuration manager not available']);
    }

    $brand_id = isset($_POST['brand_id']) ? intval($_POST['brand_id']) : 0;
    $category_ids = isset($_POST['category_ids']) ? array_map('intval', (array) $_POST['category_ids']) : [];

    if (!$brand_id) {
        wp_send_json_error(['message' => 'Invalid brand ID']);
    }

    $success = $config->update_brand_categories($brand_id, $category_ids);

    if ($success) {
        // Rebuild jobs after brand categories update
        if (isset($GLOBALS['shopcommerce_cron'])) {
            $GLOBALS['shopcommerce_cron']->rebuild_jobs();
        }

        wp_send_json_success(['message' => 'Brand categories updated successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to update brand categories']);
    }
}

/**
 * AJAX handler for deleting a brand
 */
function shopcommerce_ajax_delete_brand() {
    check_ajax_referer('shopcommerce_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $config = isset($GLOBALS['shopcommerce_config']) ? $GLOBALS['shopcommerce_config'] : null;
    if (!$config) {
        wp_send_json_error(['message' => 'Configuration manager not available']);
    }

    $brand_id = isset($_POST['brand_id']) ? intval($_POST['brand_id']) : 0;

    if (!$brand_id) {
        wp_send_json_error(['message' => 'Invalid brand ID']);
    }

    $success = $config->delete_brand($brand_id);

    if ($success) {
        // Rebuild jobs after brand deletion
        if (isset($GLOBALS['shopcommerce_cron'])) {
            $GLOBALS['shopcommerce_cron']->rebuild_jobs();
        }

        wp_send_json_success(['message' => 'Brand deleted successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to delete brand']);
    }
}

/**
 * AJAX handler for toggling brand active status
 */
function shopcommerce_ajax_toggle_brand() {
    check_ajax_referer('shopcommerce_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $config = isset($GLOBALS['shopcommerce_config']) ? $GLOBALS['shopcommerce_config'] : null;
    if (!$config) {
        wp_send_json_error(['message' => 'Configuration manager not available']);
    }

    $brand_id = isset($_POST['brand_id']) ? intval($_POST['brand_id']) : 0;
    $active = isset($_POST['active']) ? filter_var($_POST['active'], FILTER_VALIDATE_BOOLEAN) : false;

    if (!$brand_id) {
        wp_send_json_error(['message' => 'Invalid brand ID']);
    }

    $success = $config->toggle_brand_active($brand_id, $active);

    if ($success) {
        wp_send_json_success(['message' => 'Brand status updated successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to update brand status']);
    }
}

/**
 * AJAX handler for creating a category
 */
function shopcommerce_ajax_create_category() {
    check_ajax_referer('shopcommerce_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $config = isset($GLOBALS['shopcommerce_config']) ? $GLOBALS['shopcommerce_config'] : null;
    if (!$config) {
        wp_send_json_error(['message' => 'Configuration manager not available']);
    }

    $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
    $code = isset($_POST['code']) ? intval($_POST['code']) : 0;
    $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';

    if (empty($name) || empty($code)) {
        wp_send_json_error(['message' => 'Category name and code are required']);
    }

    $category_id = $config->create_category($name, $code, $description);

    if ($category_id) {
        // Rebuild jobs after category creation
        if (isset($GLOBALS['shopcommerce_cron'])) {
            $GLOBALS['shopcommerce_cron']->rebuild_jobs();
        }

        wp_send_json_success([
            'message' => 'Category created successfully',
            'category_id' => $category_id,
            'category' => [
                'id' => $category_id,
                'name' => $name,
                'code' => $code,
                'description' => $description,
                'is_active' => 1,
            ]
        ]);
    } else {
        wp_send_json_error(['message' => 'Failed to create category']);
    }
}

/**
 * AJAX handler for deleting a category
 */
function shopcommerce_ajax_delete_category() {
    check_ajax_referer('shopcommerce_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $config = isset($GLOBALS['shopcommerce_config']) ? $GLOBALS['shopcommerce_config'] : null;
    if (!$config) {
        wp_send_json_error(['message' => 'Configuration manager not available']);
    }

    $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;

    if (!$category_id) {
        wp_send_json_error(['message' => 'Invalid category ID']);
    }

    $success = $config->delete_category($category_id);

    if ($success) {
        // Rebuild jobs after category deletion
        if (isset($GLOBALS['shopcommerce_cron'])) {
            $GLOBALS['shopcommerce_cron']->rebuild_jobs();
        }

        wp_send_json_success(['message' => 'Category deleted successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to delete category']);
    }
}

/**
 * AJAX handler for toggling category active status
 */
function shopcommerce_ajax_toggle_category() {
    check_ajax_referer('shopcommerce_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $config = isset($GLOBALS['shopcommerce_config']) ? $GLOBALS['shopcommerce_config'] : null;
    if (!$config) {
        wp_send_json_error(['message' => 'Configuration manager not available']);
    }

    $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
    $active = isset($_POST['active']) ? filter_var($_POST['active'], FILTER_VALIDATE_BOOLEAN) : false;

    if (!$category_id) {
        wp_send_json_error(['message' => 'Invalid category ID']);
    }

    $success = $config->toggle_category_active($category_id, $active);

    if ($success) {
        wp_send_json_success(['message' => 'Category status updated successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to update category status']);
    }
}

/**
 * AJAX handler for rebuilding sync jobs from dynamic configuration
 */
function shopcommerce_ajax_rebuild_jobs() {
    check_ajax_referer('shopcommerce_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $cron_scheduler = isset($GLOBALS['shopcommerce_cron']) ? $GLOBALS['shopcommerce_cron'] : null;
    if (!$cron_scheduler) {
        wp_send_json_error(['message' => 'Cron scheduler not available']);
    }

    $success = $cron_scheduler->rebuild_jobs();

    if ($success) {
        wp_send_json_success(['message' => 'Sync jobs rebuilt successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to rebuild sync jobs']);
    }
}

/**
 * AJAX handler for getting current sync jobs
 */
function shopcommerce_ajax_get_sync_jobs() {
    check_ajax_referer('shopcommerce_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $cron_scheduler = isset($GLOBALS['shopcommerce_cron']) ? $GLOBALS['shopcommerce_cron'] : null;
    if (!$cron_scheduler) {
        wp_send_json_error(['message' => 'Cron scheduler not available']);
    }

    $jobs = $cron_scheduler->get_jobs();
    $queue_status = $cron_scheduler->get_queue_status();

    wp_send_json_success([
        'jobs' => $jobs,
        'queue_status' => $queue_status
    ]);
}