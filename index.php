<?php
/**
 * Plugin Name:       ShopCommerce Product Sync Plugin
 * Description:       A plugin to sync products from ShopCommerce with WooCommerce, specially for Hekalsoluciones.
 * Version:           1.1.0
 * Author:            Esteban Andres Murcia Acuña
 * Author URI:        https://estebanmurcia.dev/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       shopcommerce-product-sync-plugin
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Include the files containing the main classes
require_once plugin_dir_path( __FILE__ ) . 'includes/product-sync.php';
//require_once plugin_dir_path( __FILE__ ) . 'includes/class-order-handler.php';
//require_once plugin_dir_path( __FILE__ ) . 'includes/class-checkout-customizer.php';

// Instantiate the classes to set up the hooks
//new My_Provider_Order_Handler();
//new My_Provider_Checkout_Customizer();

register_activation_hook( __FILE__, 'product_sync_plugin_activate' );
register_deactivation_hook( __FILE__, 'product_sync_plugin_deactivate' );

add_action( 'admin_menu', function() {
    add_management_page(
        'ShopCommerce Product Sync Management',
        'ShopCommerce Sync',
        'manage_options',
        'shopcommerce-product-sync-debug',
        'shopcommerce_product_sync_management_page'
    );
});

/**
 * Main management page for ShopCommerce Product Sync
 *
 * Provides comprehensive interface for managing sync jobs, viewing statistics,
 * monitoring activity, and controlling the sync process.
 */
function shopcommerce_product_sync_management_page() {
    // Handle form submissions
    $messages = shopcommerce_handle_management_actions();

    // Get current sync status and statistics
    $stats = shopcommerce_get_sync_statistics();
    $jobs = shopcommerce_get_jobs_status();
    $recent_activity = shopcommerce_get_recent_activity();

    // Include CSS styles
    shopcommerce_enqueue_management_styles();

    echo '<div class="wrap shopcommerce-sync-management">';
    echo '<h1><span class="dashicons dashicons-update"></span> ShopCommerce Product Sync Management</h1>';

    // Display messages
    if (!empty($messages)) {
        foreach ($messages as $message) {
            echo $message;
        }
    }

    // Display overview cards
    shopcommerce_display_overview_cards($stats);

    // Display tabs for different sections
    echo '<div class="shopcommerce-tabs">';
    echo '<div class="nav-tab-wrapper">';
    echo '<a href="#" class="nav-tab nav-tab-active" data-tab="jobs">Job Queue</a>';
    echo '<a href="#" class="nav-tab" data-tab="sync">Sync Control</a>';
    echo '<a href="#" class="nav-tab" data-tab="products">Products</a>';
    echo '<a href="#" class="nav-tab" data-tab="logs">Activity Log</a>';
    echo '</div>';

    // Tab content
    echo '<div class="tab-content">';

    // Jobs tab
    echo '<div id="jobs-tab" class="tab-pane active">';
    shopcommerce_display_jobs_queue($jobs);
    echo '</div>';

    // Sync Control tab
    echo '<div id="sync-tab" class="tab-pane">';
    shopcommerce_display_sync_control($stats);
    echo '</div>';

    // Products tab
    echo '<div id="products-tab" class="tab-pane">';
    shopcommerce_display_products_info($stats);
    echo '</div>';

    // Activity Log tab
    echo '<div id="logs-tab" class="tab-pane">';
    shopcommerce_display_activity_log($recent_activity);
    echo '</div>';

    echo '</div>'; // .tab-content
    echo '</div>'; // .shopcommerce-tabs
    echo '</div>'; // .shopcommerce-sync-management

    // Add JavaScript for tab functionality
    shopcommerce_add_management_javascript();
}

/**
 * Handle management page actions (form submissions)
 */
function shopcommerce_handle_management_actions() {
    $messages = [];

    if (isset($_POST['run_sync'])) {
        provider_product_sync_hook();
        $messages[] = '<div class="notice notice-success is-dismissible"><p>✓ Sync executed successfully. Check the Activity Log for details.</p></div>';
    }

    if (isset($_POST['reset_jobs'])) {
        shopcommerce_reset_jobs();
        $messages[] = '<div class="notice notice-warning is-dismissible"><p>✓ Job queue has been reset. It will reinitialize on the next sync run.</p></div>';
    }

    if (isset($_POST['run_all_jobs'])) {
        $jobs = get_option(shopcommerce_jobs_option_key());
        if (is_array($jobs)) {
            $count = count($jobs);
            for ($i = 0; $i < $count; $i++) {
                provider_product_sync_hook();
            }
            $messages[] = '<div class="notice notice-success is-dismissible"><p>✓ All ' . $count . ' jobs have been executed. Check the Activity Log for details.</p></div>';
        }
    }

    if (isset($_POST['clear_log'])) {
        update_option('shopcommerce_activity_log', []);
        $messages[] = '<div class="notice notice-warning is-dismissible"><p>✓ Activity log has been cleared.</p></div>';
    }

    if (isset($_POST['force_reindex'])) {
        // Force reindex of all external provider products
        $force_reindex_result = shopcommerce_force_reindex_products();
        $messages[] = '<div class="notice notice-info is-dismissible"><p>' . $force_reindex_result . '</p></div>';
    }

    return $messages;
}

/**
 * Get comprehensive sync statistics
 */
function shopcommerce_get_sync_statistics() {
    global $produc_sync_hook_name;

    // Get job queue status
    $jobs = get_option(shopcommerce_jobs_option_key());
    $current_index = get_option(shopcommerce_job_index_option_key(), 0);
    $next_job = shopcommerce_get_next_job();

    // Get external provider products count
    $external_products_count = shopcommerce_get_external_products_count();

    // Get cron schedule info
    $next_scheduled = wp_next_scheduled($produc_sync_hook_name);
    $cron_schedule = wp_get_schedules();
    $current_schedule = wp_get_schedule($produc_sync_hook_name);

    // Get recent sync results from activity log
    $activity_log = get_option('shopcommerce_activity_log', []);
    $recent_syncs = array_filter($activity_log, function($entry) {
        return isset($entry['type']) && $entry['type'] === 'sync_complete';
    });

    $total_products_created = 0;
    $total_products_updated = 0;

    foreach ($recent_syncs as $sync) {
        if (isset($sync['data']['created'])) {
            $total_products_created += $sync['data']['created'];
        }
        if (isset($sync['data']['updated'])) {
            $total_products_updated += $sync['data']['updated'];
        }
    }

    return [
        'total_jobs' => is_array($jobs) ? count($jobs) : 0,
        'current_job_index' => $current_index,
        'next_job' => $next_job,
        'external_products_count' => $external_products_count,
        'next_scheduled' => $next_scheduled,
        'current_schedule' => $current_schedule,
        'total_products_created' => $total_products_created,
        'total_products_updated' => $total_products_updated,
        'recent_syncs_count' => count($recent_syncs)
    ];
}

/**
 * Get job queue status
 */
function shopcommerce_get_jobs_status() {
    $jobs = get_option(shopcommerce_jobs_option_key());
    $current_index = get_option(shopcommerce_job_index_option_key(), 0);

    if (!is_array($jobs)) {
        return [];
    }

    $job_status = [];
    foreach ($jobs as $index => $job) {
        $job_status[] = [
            'index' => $index,
            'brand' => $job['brand'],
            'categories' => $job['categories'],
            'is_next' => $index === $current_index,
            'is_processed' => $index < $current_index
        ];
    }

    return $job_status;
}

/**
 * Get recent activity from log
 */
function shopcommerce_get_recent_activity() {
    $activity_log = get_option('shopcommerce_activity_log', []);
    return array_slice($activity_log, -50); // Last 50 entries
}

/**
 * Count external provider products
 */
function shopcommerce_get_external_products_count() {
    global $wpdb;

    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $wpdb->postmeta
         WHERE meta_key = %s",
        '_external_provider'
    ));

    return intval($count);
}

/**
 * Display overview cards
 */
function shopcommerce_display_overview_cards($stats) {
    echo '<div class="shopcommerce-overview">';

    // Total Jobs card
    echo '<div class="card">';
    echo '<div class="card-icon"><span class="dashicons dashicons-list-view"></span></div>';
    echo '<div class="card-content">';
    echo '<h3>' . $stats['total_jobs'] . '</h3>';
    echo '<p>Total Jobs</p>';
    echo '</div>';
    echo '</div>';

    // External Products card
    echo '<div class="card">';
    echo '<div class="card-icon"><span class="dashicons dashicons-products"></span></div>';
    echo '<div class="card-content">';
    echo '<h3>' . number_format($stats['external_products_count']) . '</h3>';
    echo '<p>External Products</p>';
    echo '</div>';
    echo '</div>';

    // Products Created card
    echo '<div class="card">';
    echo '<div class="card-icon"><span class="dashicons dashicons-plus-alt"></span></div>';
    echo '<div class="card-content">';
    echo '<h3>' . number_format($stats['total_products_created']) . '</h3>';
    echo '<p>Products Created</p>';
    echo '</div>';
    echo '</div>';

    // Products Updated card
    echo '<div class="card">';
    echo '<div class="card-icon"><span class="dashicons dashicons-update"></span></div>';
    echo '<div class="card-content">';
    echo '<h3>' . number_format($stats['total_products_updated']) . '</h3>';
    echo '<p>Products Updated</p>';
    echo '</div>';
    echo '</div>';

    echo '</div>';
}

/**
 * Display jobs queue
 */
function shopcommerce_display_jobs_queue($jobs) {
    echo '<div class="jobs-queue">';
    echo '<h3>Job Queue Status</h3>';

    if (empty($jobs)) {
        echo '<p>No jobs configured. The queue will be initialized on the next sync run.</p>';
        return;
    }

    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Status</th>';
    echo '<th>Brand</th>';
    echo '<th>Categories</th>';
    echo '<th>Position</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    foreach ($jobs as $job) {
        echo '<tr>';
        echo '<td>';
        if ($job['is_next']) {
            echo '<span class="status-badge next">Next</span>';
        } elseif ($job['is_processed']) {
            echo '<span class="status-badge processed">Processed</span>';
        } else {
            echo '<span class="status-badge pending">Pending</span>';
        }
        echo '</td>';
        echo '<td><strong>' . esc_html($job['brand']) . '</strong></td>';
        echo '<td>';
        if (empty($job['categories'])) {
            echo 'All Categories';
        } else {
            echo implode(', ', $job['categories']);
        }
        echo '</td>';
        echo '<td>#' . ($job['index'] + 1) . '</td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';

    echo '<div class="job-actions">';
    echo '<form method="post" style="display:inline;">';
    echo '<button type="submit" name="reset_jobs" class="button button-secondary" onclick="return confirm(\'Are you sure you want to reset the job queue?\');">';
        echo '<span class="dashicons dashicons-update"></span> Reset Job Queue';
    echo '</button>';
    echo '</form>';
    echo '</div>';

    echo '</div>';
}

/**
 * Display sync control panel
 */
function shopcommerce_display_sync_control($stats) {
    global $produc_sync_hook_name;

    echo '<div class="sync-control">';
    echo '<h3>Sync Control</h3>';

    // Next scheduled run
    echo '<div class="schedule-info">';
    echo '<h4>Schedule Information</h4>';
    echo '<p><strong>Next Scheduled Run:</strong> ' . ($stats['next_scheduled'] ? date('Y-m-d H:i:s', $stats['next_scheduled']) : 'Not scheduled') . '</p>';
    echo '<p><strong>Current Interval:</strong> ' . ($stats['current_schedule'] ? esc_html($stats['current_schedule']) : 'Not set') . '</p>';

    if ($stats['next_job']) {
        echo '<p><strong>Next Job:</strong> ' . esc_html($stats['next_job']['brand']) . '</p>';
    }
    echo '</div>';

    // Manual sync controls
    echo '<div class="manual-sync">';
    echo '<h4>Manual Sync</h4>';

    echo '<div class="sync-buttons">';
    echo '<form method="post" style="display:inline;">';
    echo '<button type="submit" name="run_sync" class="button button-primary">';
        echo '<span class="dashicons dashicons-update"></span> Run Next Job';
    echo '</button>';
    echo '</form>';

    echo '<form method="post" style="display:inline;">';
    echo '<button type="submit" name="run_all_jobs" class="button button-secondary" onclick="return confirm(\'This will run all jobs sequentially. This may take a while. Continue?\');">';
        echo '<span class="dashicons dashicons-controls-repeat"></span> Run All Jobs';
    echo '</button>';
    echo '</form>';
    echo '</div>';

    // Force reindex button
    echo '<form method="post" style="margin-top: 15px;">';
    echo '<button type="submit" name="force_reindex" class="button button-secondary" onclick="return confirm(\'This will trigger a reindex of all external products. Continue?\');">';
        echo '<span class="dashicons dashicons-database-import"></span> Force Product Reindex';
    echo '</button>';
    echo '</form>';

    echo '</div>';

    echo '</div>';
}

/**
 * Display products information
 */
function shopcommerce_display_products_info($stats) {
    echo '<div class="products-info">';
    echo '<h3>Products Overview</h3>';

    // Product statistics
    echo '<div class="product-stats">';
    echo '<h4>Sync Statistics</h4>';
    echo '<ul>';
    echo '<li><strong>Total External Products:</strong> ' . number_format($stats['external_products_count']) . '</li>';
    echo '<li><strong>Total Products Created:</strong> ' . number_format($stats['total_products_created']) . '</li>';
    echo '<li><strong>Total Products Updated:</strong> ' . number_format($stats['total_products_updated']) . '</li>';
    echo '<li><strong>Recent Sync Runs:</strong> ' . $stats['recent_syncs_count'] . '</li>';
    echo '</ul>';
    echo '</div>';

    // Brand breakdown
    echo '<div class="brand-breakdown">';
    echo '<h4>Brand Breakdown</h4>';
    $brand_counts = shopcommerce_get_products_by_brand();

    if (!empty($brand_counts)) {
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Brand</th>';
        echo '<th>Product Count</th>';
        echo '<th>Last Sync</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($brand_counts as $brand => $count) {
            $last_sync = shopcommerce_get_last_sync_for_brand($brand);
            echo '<tr>';
            echo '<td><strong>' . esc_html($brand) . '</strong></td>';
            echo '<td>' . number_format($count) . '</td>';
            echo '<td>' . ($last_sync ? date('Y-m-d H:i:s', $last_sync) : 'Never') . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
    } else {
        echo '<p>No products found from external providers.</p>';
    }

    echo '</div>';

    echo '</div>';
}

/**
 * Display activity log
 */
function shopcommerce_display_activity_log($activity) {
    echo '<div class="activity-log">';
    echo '<h3>Activity Log</h3>';

    echo '<div class="log-actions">';
    echo '<form method="post" style="display:inline;">';
    echo '<button type="submit" name="clear_log" class="button button-secondary" onclick="return confirm(\'Are you sure you want to clear the activity log?\');">';
        echo '<span class="dashicons dashicons-trash"></span> Clear Log';
    echo '</button>';
    echo '</form>';
    echo '</div>';

    if (empty($activity)) {
        echo '<p>No activity recorded yet.</p>';
        return;
    }

    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Time</th>';
    echo '<th>Type</th>';
    echo '<th>Message</th>';
    echo '<th>Details</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    foreach (array_reverse($activity) as $entry) {
        echo '<tr>';
        echo '<td>' . date('Y-m-d H:i:s', $entry['timestamp']) . '</td>';
        echo '<td><span class="log-type ' . esc_attr($entry['type']) . '">' . esc_html($entry['type']) . '</span></td>';
        echo '<td>' . esc_html($entry['message']) . '</td>';
        echo '<td>';
        if (isset($entry['data']) && !empty($entry['data'])) {
            echo '<details>';
            echo '<summary>View Details</summary>';
            echo '<pre>' . json_encode($entry['data'], JSON_PRETTY_PRINT) . '</pre>';
            echo '</details>';
        }
        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';

    echo '</div>';
}

/**
 * Get product counts by brand
 */
function shopcommerce_get_products_by_brand() {
    global $wpdb;

    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT meta_value as brand, COUNT(*) as count
         FROM $wpdb->postmeta
         WHERE meta_key = %s
         GROUP BY meta_value
         ORDER BY count DESC",
        '_external_provider_brand'
    ));

    $brand_counts = [];
    foreach ($results as $row) {
        $brand_counts[$row->brand] = $row->count;
    }

    return $brand_counts;
}

/**
 * Get last sync time for a brand
 */
function shopcommerce_get_last_sync_for_brand($brand) {
    $activity_log = get_option('shopcommerce_activity_log', []);

    foreach (array_reverse($activity_log) as $entry) {
        if (isset($entry['type']) && $entry['type'] === 'sync_complete' &&
            isset($entry['data']['brand']) && $entry['data']['brand'] === $brand) {
            return $entry['timestamp'];
        }
    }

    return null;
}

/**
 * Force reindex of all external products
 */
function shopcommerce_force_reindex_products() {
    global $wpdb;

    // Update all external provider products sync timestamp
    $updated = $wpdb->update(
        $wpdb->postmeta,
        ['meta_value' => current_time('mysql')],
        ['meta_key' => '_external_provider_sync_date'],
        ['%s'],
        ['%s']
    );

    return "Updated {$updated} products with new sync timestamp.";
}

/**
 * Enqueue management page styles
 */
function shopcommerce_enqueue_management_styles() {
    echo '<style>
    .shopcommerce-sync-management {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
    }

    .shopcommerce-overview {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .card {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        padding: 20px;
        box-shadow: 0 1px 1px rgba(0,0,0,0.04);
    }

    .card-icon {
        font-size: 24px;
        margin-bottom: 10px;
    }

    .card-icon .dashicons {
        color: #0073aa;
    }

    .card-content h3 {
        margin: 0 0 5px 0;
        font-size: 24px;
        color: #23282d;
    }

    .card-content p {
        margin: 0;
        color: #666;
        font-size: 14px;
    }

    .shopcommerce-tabs {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        box-shadow: 0 1px 1px rgba(0,0,0,0.04);
    }

    .nav-tab-wrapper {
        border-bottom: 1px solid #ccd0d4;
        background: #f8f9fa;
        padding: 0 10px;
    }

    .nav-tab {
        display: inline-block;
        padding: 10px 15px;
        margin: 0;
        border: none;
        border-bottom: 2px solid transparent;
        background: transparent;
        color: #666;
        text-decoration: none;
        font-weight: 400;
    }

    .nav-tab:hover {
        color: #0073aa;
        background: transparent;
    }

    .nav-tab.nav-tab-active {
        color: #0073aa;
        border-bottom-color: #0073aa;
        background: transparent;
    }

    .tab-content {
        padding: 20px;
    }

    .tab-pane {
        display: none;
    }

    .tab-pane.active {
        display: block;
    }

    .status-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 3px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .status-badge.next {
        background: #dff0d8;
        color: #3c763d;
    }

    .status-badge.processed {
        background: #d9edf7;
        color: #31708f;
    }

    .status-badge.pending {
        background: #fcf8e3;
        color: #8a6d3b;
    }

    .log-type {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 3px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .log-type.sync_complete {
        background: #dff0d8;
        color: #3c763d;
    }

    .log-type.sync_error {
        background: #f2dede;
        color: #a94442;
    }

    .log-type.product_created {
        background: #d9edf7;
        color: #31708f;
    }

    .log-type.product_updated {
        background: #fcf8e3;
        color: #8a6d3b;
    }

    .job-actions, .sync-buttons, .log-actions {
        margin-top: 20px;
        padding-top: 15px;
        border-top: 1px solid #eee;
    }

    .schedule-info, .manual-sync, .product-stats, .brand-breakdown {
        margin-bottom: 25px;
        padding-bottom: 20px;
        border-bottom: 1px solid #eee;
    }

    .schedule-info h4, .manual-sync h4, .product-stats h4, .brand-breakdown h4 {
        margin: 0 0 10px 0;
        color: #23282d;
    }

    .sync-buttons button {
        margin-right: 10px;
    }

    .activity-log details {
        background: #f8f9fa;
        border: 1px solid #ddd;
        border-radius: 3px;
        padding: 10px;
        margin-top: 5px;
    }

    .activity-log summary {
        cursor: pointer;
        font-weight: 600;
        color: #0073aa;
    }

    .activity-log pre {
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 3px;
        padding: 10px;
        margin: 5px 0 0 0;
        font-size: 12px;
        max-height: 200px;
        overflow: auto;
    }

    .dashicons {
        vertical-align: middle;
        margin-right: 5px;
    }

    @media (max-width: 782px) {
        .shopcommerce-overview {
            grid-template-columns: 1fr;
        }

        .nav-tab-wrapper {
            padding: 0 5px;
        }

        .nav-tab {
            padding: 10px 8px;
            font-size: 13px;
        }
    }
    </style>';
}

/**
 * Add JavaScript for management page
 */
function shopcommerce_add_management_javascript() {
    echo '<script>
    jQuery(document).ready(function($) {
        // Tab functionality
        $(".nav-tab").on("click", function(e) {
            e.preventDefault();

            var tabId = $(this).data("tab");

            // Update active tab
            $(".nav-tab").removeClass("nav-tab-active");
            $(this).addClass("nav-tab-active");

            // Show corresponding tab content
            $(".tab-pane").removeClass("active");
            $("#" + tabId + "-tab").addClass("active");
        });

        // Auto-refresh functionality (optional)
        var autoRefresh = false;
        var refreshInterval;

        function startAutoRefresh() {
            refreshInterval = setInterval(function() {
                location.reload();
            }, 30000); // Refresh every 30 seconds
        }

        function stopAutoRefresh() {
            clearInterval(refreshInterval);
        }

        // Toggle auto-refresh
        $(document).on("keydown", function(e) {
            if (e.ctrlKey && e.key === "r") {
                e.preventDefault();
                autoRefresh = !autoRefresh;
                if (autoRefresh) {
                    startAutoRefresh();
                    alert("Auto-refresh enabled (Ctrl+R to disable)");
                } else {
                    stopAutoRefresh();
                    alert("Auto-refresh disabled");
                }
            }
        });
    });
    </script>';
}

/**
 * Log sync activity for management page
 * This should be called in the sync process
 */
function shopcommerce_log_activity($type, $message, $data = []) {
    $activity_log = get_option('shopcommerce_activity_log', []);

    $entry = [
        'timestamp' => time(),
        'type' => $type,
        'message' => $message,
        'data' => $data
    ];

    $activity_log[] = $entry;

    // Keep only last 1000 entries
    if (count($activity_log) > 1000) {
        $activity_log = array_slice($activity_log, -1000);
    }

    update_option('shopcommerce_activity_log', $activity_log);
}
