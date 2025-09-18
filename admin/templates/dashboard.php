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
            <h3>Batch Queue</h3>
            <div class="stat-number">
                <?php
                $batch_stats = $statistics['batch_processing']['queue_stats'] ?? [];
                $pending_batches = $batch_stats['pending'] ?? 0;
                $processing_batches = $batch_stats['processing'] ?? 0;
                echo $pending_batches + $processing_batches;
                ?>
            </div>
            <div class="stat-label">Batches Pending</div>
        </div>

        <div class="card">
            <h3>Active Brands</h3>
            <div class="stat-number">
                <?php
                $active_brands = $statistics['batch_processing']['active_brands'] ?? [];
                echo count($active_brands);
                ?>
            </div>
            <div class="stat-label">Processing</div>
        </div>
    </div>

    <!-- Batch Processing Status -->
    <?php if (!empty($statistics['batch_processing']['active_brands'])): ?>
    <div class="shopcommerce-batch-status">
        <h2>Batch Processing Status</h2>
        <div class="batch-progress-container">
            <?php foreach ($statistics['batch_processing']['active_brands'] as $brand => $progress): ?>
            <div class="batch-progress-item">
                <div class="batch-progress-header">
                    <span class="brand-name"><?php echo esc_html($brand); ?></span>
                    <span class="progress-percentage"><?php echo esc_html($progress['completion_percentage'] ?? 0); ?>%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo esc_html($progress['completion_percentage'] ?? 0); ?>%"></div>
                </div>
                <div class="batch-progress-details">
                    <span class="batches-completed">
                        <?php echo esc_html($progress['completed_batches'] ?? 0); ?> / <?php echo esc_html($progress['total_batches'] ?? 0); ?> batches
                    </span>
                    <span class="products-processed">
                        <?php echo esc_html($progress['processed_products'] ?? 0); ?> products
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

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
                    <option value="sync_complete" <?php selected($activity_filter, 'sync_complete'); ?>>Sync Complete</option>
                    <option value="sync_error" <?php selected($activity_filter, 'sync_error'); ?>>Sync Errors</option>
                    <option value="product_created" <?php selected($activity_filter, 'product_created'); ?>>Products Created</option>
                    <option value="product_updated" <?php selected($activity_filter, 'product_updated'); ?>>Products Updated</option>
                </select>
                <button type="button" class="button" id="refresh-activity-btn">Refresh</button>
            </div>
            <div class="tablenav-pages">
                <span class="displaying-num">
                    <?php echo sprintf(
                        _n(
                            '%s item',
                            '%s items',
                            $total_activities ?? count($activity_log),
                            'shopcommerce-sync'
                        ),
                        number_format($total_activities ?? count($activity_log))
                    ); ?>
                </span>
                <span class="pagination-links">
                    <button type="button" class="button page-numbers" id="activity-prev-page"
                            <?php echo ($current_page ?? 1) <= 1 ? 'disabled' : ''; ?>>
                        &laquo;
                    </button>
                    <span class="paging-input">
                        <label for="activity-current-page" class="screen-reader-text">Current Page</label>
                        <input type="text" id="activity-current-page" class="current-page"
                               value="<?php echo $current_page ?? 1; ?>" size="1"
                               min="1" max="<?php echo $total_pages ?? 1; ?>">
                        of <span class="total-pages"><?php echo $total_pages ?? 1; ?></span>
                    </span>
                    <button type="button" class="button page-numbers" id="activity-next-page"
                            <?php echo ($current_page ?? 1) >= ($total_pages ?? 1) ? 'disabled' : ''; ?>>
                        &raquo;
                    </button>
                </span>
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
                    <tbody id="activity-log-tbody">
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
        loadActivityLog(1, $(this).val());
    });

    // Refresh activity
    $('#refresh-activity-btn').on('click', function() {
        loadActivityLog($('#activity-current-page').val(), $('#activity-filter').val());
    });

    // Pagination
    $('#activity-prev-page').on('click', function() {
        var currentPage = parseInt($('#activity-current-page').val());
        if (currentPage > 1) {
            loadActivityLog(currentPage - 1, $('#activity-filter').val());
        }
    });

    $('#activity-next-page').on('click', function() {
        var currentPage = parseInt($('#activity-current-page').val());
        var totalPages = parseInt($('.total-pages').text());
        if (currentPage < totalPages) {
            loadActivityLog(currentPage + 1, $('#activity-filter').val());
        }
    });

    // Manual page input
    $('#activity-current-page').on('change', function() {
        var page = parseInt($(this).val());
        var totalPages = parseInt($('.total-pages').text());
        if (page >= 1 && page <= totalPages) {
            loadActivityLog(page, $('#activity-filter').val());
        } else {
            $(this).val($('#activity-current-page').data('current') || 1);
        }
    });

    // Load activity log via AJAX
    function loadActivityLog(page, filter) {
        var $tbody = $('#activity-log-tbody');
        var $section = $('.shopcommerce-activity-section');

        $section.addClass('loading');

        $.ajax({
            url: shopcommerce_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'shopcommerce_load_activity_log',
                nonce: shopcommerce_admin.nonce,
                page: page || 1,
                filter: filter || '',
                per_page: 20
            },
            success: function(response) {
                if (response.success) {
                    updateActivityLog(response.data);
                } else {
                    alert('Failed to load activity log: ' + response.data.error);
                }
            },
            error: function() {
                alert('Failed to load activity log due to network error.');
            },
            complete: function() {
                $section.removeClass('loading');
            }
        });
    }

    // Update activity log with new data
    function updateActivityLog(data) {
        var $tbody = $('#activity-log-tbody');
        var $displayingNum = $('.displaying-num');
        var $totalPages = $('.total-pages');
        var $currentPage = $('#activity-current-page');

        // Update tbody
        $tbody.empty();

        if (data.activities.length === 0) {
            $tbody.append('<tr><td colspan="4">No recent activity found.</td></tr>');
        } else {
            data.activities.forEach(function(activity) {
                var row = '<tr>' +
                    '<td>' + activity.formatted_time + '</td>' +
                    '<td><span class="activity-type activity-' + activity.type + '">' +
                    activity.type_label + '</span></td>' +
                    '<td>' + activity.description + '</td>' +
                    '<td>' +
                    (activity.has_data ?
                        '<button type="button" class="button button-small view-details-btn" ' +
                        'data-activity=\'' + JSON.stringify(activity.raw_data) + '\'>View Details</button>' :
                        '') +
                    '</td>' +
                    '</tr>';
                $tbody.append(row);
            });
        }

        // Update pagination info
        $displayingNum.text(data.displaying_text);
        $totalPages.text(data.total_pages);
        $currentPage.val(data.current_page);

        // Update pagination buttons
        $('#activity-prev-page').prop('disabled', data.current_page <= 1);
        $('#activity-next-page').prop('disabled', data.current_page >= data.total_pages);

        // Store current page for input validation
        $currentPage.data('current', data.current_page);

        // Re-attach view details handlers
        attachViewDetailsHandlers();
    }

    // Re-attach view details handlers for dynamically loaded content
    function attachViewDetailsHandlers() {
        $('.view-details-btn').off('click').on('click', function() {
            var activity = $(this).data('activity');
            $('#activity-details-content').text(JSON.stringify(activity, null, 2));
            $('#activity-details-modal').show();
        });
    }
});
</script>