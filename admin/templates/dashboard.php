<?php
/**
 * Dashboard template for ShopCommerce Sync
 *
 * @package ShopCommerce_Sync
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap shopcommerce-admin">
    <h1 class="wp-heading-inline">ShopCommerce Sync Dashboard</h1>
    <a href="<?php echo admin_url('admin.php?page=shopcommerce-sync-control'); ?>" class="page-title-action">Run Sync</a>
    <hr class="wp-header-end">

    <?php if (isset($_GET['sync_success'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html($_GET['sync_success']); ?></p>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['sync_error'])): ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html($_GET['sync_error']); ?></p>
        </div>
    <?php endif; ?>

    <!-- Status Cards -->
    <div class="shopcommerce-status-cards">
        <div class="card">
            <h3>Total Products</h3>
            <div class="stat-number"><?php echo number_format($statistics['products']['total_synced_products'] ?? 0); ?></div>
            <div class="stat-label">Products Synced</div>
        </div>

        <div class="card">
            <h3>Last Sync</h3>
            <div class="stat-number">
                <?php
                $last_sync = $statistics['activity']['last_sync_time'] ?? null;
                echo $last_sync ? date_i18n('M j, Y g:i A', strtotime($last_sync)) : 'Never';
                ?>
            </div>
            <div class="stat-label">Sync Time</div>
        </div>

        <div class="card">
            <h3>Queue Status</h3>
            <div class="stat-number"><?php echo esc_html($statistics['queue']['current_job']['brand'] ?? 'Idle'); ?></div>
            <div class="stat-label">Current Job</div>
        </div>

        <div class="card">
            <h3>API Status</h3>
            <div class="stat-number <?php echo $statistics['api']['token_cached'] ? 'status-good' : 'status-warning'; ?>">
                <?php echo $statistics['api']['token_cached'] ? 'Connected' : 'Disconnected'; ?>
            </div>
            <div class="stat-label">Connection</div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="shopcommerce-quick-actions">
        <h2>Quick Actions</h2>
        <div class="actions-grid">
            <button type="button" class="button button-primary" id="test-connection-btn">
                <span class="dashicons dashicons-networking"></span> Test Connection
            </button>
            <button type="button" class="button button-primary" id="run-sync-btn">
                <span class="dashicons dashicons-update"></span> Run Sync
            </button>
            <button type="button" class="button button-secondary" id="view-queue-btn">
                <span class="dashicons dashicons-list-view"></span> View Queue
            </button>
            <button type="button" class="button button-secondary" id="clear-cache-btn">
                <span class="dashicons dashicons-trash"></span> Clear Cache
            </button>
        </div>
    </div>

    <!-- Statistics Details -->
    <div class="shopcommerce-stats-section">
        <h2>Sync Statistics</h2>
        <div class="stats-grid">
            <div class="stat-item">
                <span class="stat-label">Products Created</span>
                <span class="stat-value"><?php echo number_format($statistics['activity']['total_products_synced'] ?? 0); ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Sync Errors</span>
                <span class="stat-value"><?php echo number_format($statistics['activity']['total_errors'] ?? 0); ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Without Images</span>
                <span class="stat-value"><?php echo number_format($statistics['products']['products_without_images'] ?? 0); ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Out of Stock</span>
                <span class="stat-value"><?php echo number_format($statistics['products']['products_out_of_stock'] ?? 0); ?></span>
            </div>
        </div>
    </div>

    <!-- Activity Log -->
    <div class="shopcommerce-activity-section">
        <h2>Recent Activity</h2>
        <div class="tablenav top">
            <div class="alignleft actions">
                <select id="activity-filter">
                    <option value="">All Activities</option>
                    <option value="sync_complete">Sync Complete</option>
                    <option value="sync_error">Sync Errors</option>
                    <option value="product_created">Products Created</option>
                    <option value="product_updated">Products Updated</option>
                </select>
                <button type="button" class="button" id="refresh-activity-btn">Refresh</button>
            </div>
        </div>

        <div class="shopcommerce-activity-log">
            <?php if (empty($activity_log)): ?>
                <p>No recent activity found.</p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activity_log as $activity): ?>
                            <tr>
                                <td><?php echo date_i18n('M j, Y g:i A', strtotime($activity['timestamp'])); ?></td>
                                <td>
                                    <span class="activity-type activity-<?php echo esc_attr($activity['type']); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $activity['type'])); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($activity['description']); ?></td>
                                <td>
                                    <?php if (!empty($activity['data'])): ?>
                                        <button type="button" class="button button-small view-details-btn"
                                                data-activity='<?php echo json_encode($activity); ?>'>
                                            View Details
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- System Status -->
    <div class="shopcommerce-system-section">
        <h2>System Status</h2>
        <div class="system-status-grid">
            <div class="status-item">
                <span class="status-label">WooCommerce</span>
                <span class="status-value <?php echo class_exists('WooCommerce') ? 'status-good' : 'status-error'; ?>">
                    <?php echo class_exists('WooCommerce') ? 'Active' : 'Not Active'; ?>
                </span>
            </div>
            <div class="status-item">
                <span class="status-label">PHP Version</span>
                <span class="status-value <?php echo version_compare(PHP_VERSION, '7.2', '>=') ? 'status-good' : 'status-error'; ?>">
                    <?php echo PHP_VERSION; ?>
                </span>
            </div>
            <div class="status-item">
                <span class="status-label">WordPress Version</span>
                <span class="status-value <?php echo version_compare(get_bloginfo('version'), '5.0', '>=') ? 'status-good' : 'status-error'; ?>">
                    <?php echo get_bloginfo('version'); ?>
                </span>
            </div>
            <div class="status-item">
                <span class="status-label">Cron Status</span>
                <span class="status-value <?php echo $statistics['queue']['cron_info']['is_scheduled'] ? 'status-good' : 'status-warning'; ?>">
                    <?php echo $statistics['queue']['cron_info']['is_scheduled'] ? 'Scheduled' : 'Not Scheduled'; ?>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Activity Details Modal -->
<div id="activity-details-modal" class="shopcommerce-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Activity Details</h3>
            <button type="button" class="close-modal">&times;</button>
        </div>
        <div class="modal-body">
            <pre id="activity-details-content"></pre>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Test connection
    $('#test-connection-btn').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Testing...');

        $.ajax({
            url: shopcommerce_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'shopcommerce_test_connection',
                nonce: shopcommerce_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Connection test successful!');
                } else {
                    alert('Connection test failed: ' + response.data.error);
                }
            },
            error: function() {
                alert('Connection test failed due to network error.');
            },
            complete: function() {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-networking"></span> Test Connection');
            }
        });
    });

    // Run sync
    $('#run-sync-btn').on('click', function() {
        if (confirm('Are you sure you want to run the sync now?')) {
            var $btn = $(this);
            $btn.prop('disabled', true).text('Running...');

            $.ajax({
                url: shopcommerce_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'shopcommerce_run_sync',
                    nonce: shopcommerce_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Sync failed: ' + response.data.error);
                    }
                },
                error: function() {
                    alert('Sync failed due to network error.');
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Run Sync');
                }
            });
        }
    });

    // Clear cache
    $('#clear-cache-btn').on('click', function() {
        if (confirm('Are you sure you want to clear the cache?')) {
            var $btn = $(this);
            $btn.prop('disabled', true).text('Clearing...');

            $.ajax({
                url: shopcommerce_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'shopcommerce_clear_cache',
                    nonce: shopcommerce_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('Cache cleared successfully!');
                        location.reload();
                    } else {
                        alert('Cache clear failed: ' + response.data.error);
                    }
                },
                error: function() {
                    alert('Cache clear failed due to network error.');
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Clear Cache');
                }
            });
        }
    });

    // View activity details
    $('.view-details-btn').on('click', function() {
        var activity = $(this).data('activity');
        $('#activity-details-content').text(JSON.stringify(activity, null, 2));
        $('#activity-details-modal').show();
    });

    // Close modal
    $('.close-modal').on('click', function() {
        $('#activity-details-modal').hide();
    });

    // Filter activity
    $('#activity-filter').on('change', function() {
        var filter = $(this).val();
        // Reload page with filter parameter
        window.location.href = window.location.pathname + window.location.search + '&activity_filter=' + filter;
    });
});
</script>