<?php
/**
 * Batches management template for ShopCommerce Sync
 *
 * @package ShopCommerce_Sync
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$brand_filter = isset($_GET['brand']) ? sanitize_text_field($_GET['brand']) : '';
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 20;

// Get batches with filters
$batches = [];
$total_batches = 0;
$total_pages = 1;

if ($jobs_store) {
    $batches = $jobs_store->get_batches($per_page, ($page - 1) * $per_page, $status_filter, $brand_filter, $search);
    $total_batches = $jobs_store->get_batches_count($status_filter, $brand_filter, $search);
    $total_pages = ceil($total_batches / $per_page);
}

// Get available brands for filter
$available_brands = [];
if ($jobs_store) {
    $queue_stats = $jobs_store->get_queue_stats();
    $available_brands = array_keys($queue_stats['by_brand'] ?? []);
}

// Get status options
$status_options = [
    '' => 'All Status',
    'pending' => 'Pending',
    'processing' => 'Processing',
    'completed' => 'Completed',
    'failed' => 'Failed'
];
?>

<div class="wrap shopcommerce-admin">
    <h1 class="wp-heading-inline">Batch Management</h1>
    <a href="<?php echo admin_url('admin.php?page=shopcommerce-sync-control'); ?>" class="page-title-action">Back to Sync Control</a>
    <hr class="wp-header-end">

    <?php if (isset($_GET['message'])): ?>
        <div class="notice notice-<?php echo esc_attr($_GET['type']); ?> is-dismissible">
            <p><?php echo esc_html($_GET['message']); ?></p>
        </div>
    <?php endif; ?>

    <!-- Filters and Search -->
    <div class="tablenav top">
        <div class="alignleft actions">
            <select name="status_filter" id="status-filter">
                <?php foreach ($status_options as $value => $label): ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($status_filter, $value); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="brand_filter" id="brand-filter">
                <option value="">All Brands</option>
                <?php foreach ($available_brands as $brand): ?>
                    <option value="<?php echo esc_attr($brand); ?>" <?php selected($brand_filter, $brand); ?>>
                        <?php echo esc_html($brand); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="text" name="s" id="search-input" placeholder="Search batches..." value="<?php echo esc_attr($search); ?>">
            <button type="button" class="button" id="filter-batches-btn">Filter</button>
            <button type="button" class="button" id="clear-filters-btn">Clear</button>
        </div>

        <div class="alignright actions">
            <?php if ($batch_processor): ?>
                <button type="button" class="button button-secondary" id="process-next-batch-btn">
                    Process Next Batch
                </button>
                <button type="button" class="button button-secondary" id="reset-all-failed-btn">
                    Reset All Failed
                </button>
            <?php endif; ?>
        </div>

        <div class="tablenav-pages">
            <span class="displaying-num">
                <?php echo sprintf(
                    _n(
                        '%s batch',
                        '%s batches',
                        $total_batches,
                        'shopcommerce-sync'
                    ),
                    number_format($total_batches)
                ); ?>
            </span>
            <span class="pagination-links">
                <button type="button" class="button page-numbers" id="prev-page" <?php echo $page <= 1 ? 'disabled' : ''; ?>>
                    &laquo;
                </button>
                <span class="paging-input">
                    <label for="current-page" class="screen-reader-text">Current Page</label>
                    <input type="text" id="current-page" class="current-page" value="<?php echo $page; ?>" size="1" min="1" max="<?php echo $total_pages; ?>">
                    of <span class="total-pages"><?php echo $total_pages; ?></span>
                </span>
                <button type="button" class="button page-numbers" id="next-page" <?php echo $page >= $total_pages ? 'disabled' : ''; ?>>
                    &raquo;
                </button>
            </span>
        </div>
    </div>

    <!-- Batches Table -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col" class="manage-column column-id">ID</th>
                <th scope="col" class="manage-column column-brand">Brand</th>
                <th scope="col" class="manage-column column-status">Status</th>
                <th scope="col" class="manage-column column-progress">Progress</th>
                <th scope="col" class="manage-column column-created">Created</th>
                <th scope="col" class="manage-column column-attempts">Attempts</th>
                <th scope="col" class="manage-column column-actions">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($batches)): ?>
                <?php foreach ($batches as $batch): ?>
                    <tr>
                        <td><?php echo esc_html($batch->id); ?></td>
                        <td>
                            <strong><?php echo esc_html($batch->brand); ?></strong>
                            <div class="row-actions">
                                <span class="batch-info">
                                    Batch <?php echo esc_html($batch->batch_index); ?> of <?php echo esc_html($batch->total_batches); ?>
                                </span>
                            </div>
                        </td>
                        <td>
                            <span class="batch-status status-<?php echo esc_attr($batch->status); ?>">
                                <?php echo esc_html(ucfirst($batch->status)); ?>
                            </span>
                            <?php if ($batch->error_message): ?>
                                <div class="error-message" title="<?php echo esc_attr($batch->error_message); ?>">
                                    <span class="dashicons dashicons-warning"></span>
                                    Error
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $batch_data = json_decode($batch->batch_data, true);
                            $product_count = is_array($batch_data) ? count($batch_data) : 0;
                            echo esc_html($product_count) . ' products';
                            ?>
                        </td>
                        <td>
                            <?php echo date_i18n('M j, Y g:i A', strtotime($batch->created_at)); ?>
                            <?php if ($batch->started_at): ?>
                                <br><small>Started: <?php echo date_i18n('M j, g:i A', strtotime($batch->started_at)); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo esc_html($batch->attempts); ?> / <?php echo esc_html($batch->max_attempts); ?>
                        </td>
                        <td>
                            <div class="batch-actions">
                                <?php if ($batch->status === 'pending'): ?>
                                    <a href="<?php echo wp_nonce_url(
                                        add_query_arg([
                                            'page' => 'shopcommerce-sync-batches',
                                            'action' => 'execute',
                                            'batch_id' => $batch->id
                                        ], admin_url('admin.php')),
                                        'batch_action_' . $batch->id
                                    ); ?>" class="button button-small" title="Execute batch now">
                                        <span class="dashicons dashicons-play"></span> Execute
                                    </a>
                                <?php endif; ?>

                                <?php if ($batch->status === 'failed'): ?>
                                    <a href="<?php echo wp_nonce_url(
                                        add_query_arg([
                                            'page' => 'shopcommerce-sync-batches',
                                            'action' => 'retry',
                                            'batch_id' => $batch->id
                                        ], admin_url('admin.php')),
                                        'batch_action_' . $batch->id
                                    ); ?>" class="button button-small" title="Retry failed batch">
                                        <span class="dashicons dashicons-update"></span> Retry
                                    </a>
                                <?php endif; ?>

                                <?php if ($batch->status === 'processing'): ?>
                                    <button type="button" class="button button-small" disabled title="Batch is processing">
                                        <span class="dashicons dashicons-controls-play"></span> Processing
                                    </button>
                                <?php endif; ?>

                                <?php if ($batch->status === 'completed'): ?>
                                    <button type="button" class="button button-small" disabled title="Batch completed">
                                        <span class="dashicons dashicons-yes-alt"></span> Done
                                    </button>
                                <?php endif; ?>

                                <?php if (in_array($batch->status, ['pending', 'failed'])): ?>
                                    <a href="<?php echo wp_nonce_url(
                                        add_query_arg([
                                            'page' => 'shopcommerce-sync-batches',
                                            'action' => 'delete',
                                            'batch_id' => $batch->id
                                        ], admin_url('admin.php')),
                                        'batch_action_' . $batch->id
                                    ); ?>" class="button button-small" title="Delete batch" onclick="return confirm('Are you sure you want to delete this batch?');">
                                        <span class="dashicons dashicons-trash"></span>
                                    </a>
                                <?php endif; ?>

                                <button type="button" class="button button-small view-details-btn"
                                        data-batch-id="<?php echo esc_attr($batch->id); ?>"
                                        title="View batch details">
                                    <span class="dashicons dashicons-visibility"></span>
                                </button>

                                <?php if ($batch->error_message): ?>
                                    <button type="button" class="button button-small view-error-btn"
                                            data-error="<?php echo esc_attr($batch->error_message); ?>"
                                            title="View error details">
                                        <span class="dashicons dashicons-warning"></span>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7">
                        <p>No batches found matching your criteria.</p>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Batch Details Modal -->
    <div id="batch-details-modal" class="shopcommerce-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Batch Details</h3>
                <button type="button" class="button-link modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div id="batch-details-content"></div>
            </div>
        </div>
    </div>

    <!-- Error Details Modal -->
    <div id="error-details-modal" class="shopcommerce-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Error Details</h3>
                <button type="button" class="button-link modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div id="error-details-content"></div>
            </div>
        </div>
    </div>
</div>