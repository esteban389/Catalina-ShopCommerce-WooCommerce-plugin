<?php
/**
 * Products Management template for ShopCommerce Sync
 *
 * @package ShopCommerce_Sync
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get URL parameters
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$product_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
$brand = isset($_GET['brand']) ? sanitize_text_field($_GET['brand']) : '';

// Get page size from URL or user preference
$default_page_sizes = [10, 20, 50, 100, 200, 500, 1000];
$products_per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 20;

// Validate page size
if (!in_array($products_per_page, $default_page_sizes)) {
    $products_per_page = 20;
}

// Get helpers
$helpers = isset($GLOBALS['shopcommerce_helpers']) ? $GLOBALS['shopcommerce_helpers'] : null;
$logger = isset($GLOBALS['shopcommerce_logger']) ? $GLOBALS['shopcommerce_logger'] : null;
$offset = ($current_page - 1) * $products_per_page;

$products_data = $helpers ? $helpers->get_external_provider_products([
    'search' => $search,
    'status' => $product_status,
    'brand' => $brand,
    'limit' => $products_per_page,
    'offset' => $offset
]) : [];

$total_products = $helpers ? $helpers->get_external_provider_products_count([
    'search' => $search,
    'status' => $product_status,
    'brand' => $brand
]) : 0;

$total_pages = max(1, ceil($total_products / $products_per_page));

// Get available brands
$brands = $helpers ? $helpers->get_external_provider_brands() : [];
?>

<div class="wrap shopcommerce-admin">
    <h1 class="wp-heading-inline">ShopCommerce Products</h1>
    <a href="<?php echo admin_url('admin.php?page=shopcommerce-sync'); ?>" class="page-title-action">Back to Dashboard</a>
    <hr class="wp-header-end">

    <!-- Search and Filters -->
    <div class="tablenav top">
        <div class="alignleft actions bulkactions">
            <form method="get" action="">
                <input type="hidden" name="page" value="shopcommerce-sync-products">
                <input type="hidden" name="paged" value="<?php echo $current_page; ?>">
                <input type="hidden" name="status" value="<?php echo esc_attr($product_status); ?>">
                <input type="hidden" name="brand" value="<?php echo esc_attr($brand); ?>">
                <label class="screen-reader-text" for="post-search-input">Search products:</label>
                <input type="search" id="post-search-input" name="s" value="<?php echo esc_attr($search); ?>">
                <input type="submit" id="search-submit" class="button" value="Search Products">
            </form>
        </div>

        <div class="alignleft actions">
            <form method="get" action="" class="filter-form">
                <input type="hidden" name="page" value="shopcommerce-sync-products">
                <input type="hidden" name="s" value="<?php echo esc_attr($search); ?>">
                <input type="hidden" name="paged" value="1">

                <select name="status">
                    <option value="all" <?php selected($product_status, 'all'); ?>>All Statuses</option>
                    <option value="publish" <?php selected($product_status, 'publish'); ?>>Published</option>
                    <option value="draft" <?php selected($product_status, 'draft'); ?>>Draft</option>
                    <option value="trash" <?php selected($product_status, 'trash'); ?>>Trash</option>
                </select>

                <?php if (!empty($brands)): ?>
                    <select name="brand">
                        <option value="">All Brands</option>
                        <?php foreach ($brands as $brand_option): ?>
                            <option value="<?php echo esc_attr($brand_option); ?>" <?php selected($brand, $brand_option); ?>>
                                <?php echo esc_html($brand_option); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>

                <input type="submit" class="button action" value="Filter">
            </form>
        </div>

        <div class="alignleft actions">
            <form method="get" action="" class="page-size-form">
                <input type="hidden" name="page" value="shopcommerce-sync-products">
                <input type="hidden" name="s" value="<?php echo esc_attr($search); ?>">
                <input type="hidden" name="status" value="<?php echo esc_attr($product_status); ?>">
                <input type="hidden" name="brand" value="<?php echo esc_attr($brand); ?>">
                <input type="hidden" name="paged" value="1">

                <label for="per-page" class="screen-reader-text">Items per page:</label>
                <select id="per-page" name="per_page" class="page-size-select">
                    <?php foreach ($default_page_sizes as $size): ?>
                        <option value="<?php echo $size; ?>" <?php selected($products_per_page, $size); ?>>
                            <?php echo $size; ?> per page
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <div class="tablenav-pages">
            <span class="displaying-num">
                <?php echo sprintf(
                    _n(
                        '%s item',
                        '%s items',
                        $total_products,
                        'shopcommerce-sync'
                    ),
                    number_format($total_products)
                ); ?>
            </span>
            <span class="pagination-links">
                <?php
                $pagination_args = [
                    'page' => 'shopcommerce-sync-products',
                    's' => $search,
                    'status' => $product_status,
                    'brand' => $brand,
                    'per_page' => $products_per_page
                ];
                echo paginate_links([
                    'base' => add_query_arg($pagination_args, admin_url('admin.php')),
                    'format' => '&paged=%#%',
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'total' => $total_pages,
                    'current' => $current_page,
                    'before_page_number' => '<span class="screen-reader-text">Page </span>',
                ]);
                ?>
            </span>
        </div>
    </div>

    <!-- Products Table -->
    <div class="shopcommerce-products-section">
        <form method="post" action="" id="products-form">
            <?php wp_nonce_field('shopcommerce_products_management', 'products_nonce'); ?>
            <input type="hidden" name="action" value="shopcommerce_bulk_products">

            <table class="wp-list-table widefat fixed striped table-view-list">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column">
                            <input type="checkbox" id="cb-select-all-1">
                        </td>
                        <th scope="col" class="manage-column column-id sortable">
                            <span>ID</span>
                        </th>
                        <th scope="col" class="manage-column column-name column-primary">
                            <span>Product</span>
                        </th>
                        <th scope="col" class="manage-column column-sku">
                            <span>SKU</span>
                        </th>
                        <th scope="col" class="manage-column column-brand">
                            <span>Brand</span>
                        </th>
                        <th scope="col" class="manage-column column-price sortable">
                            <span>Price</span>
                        </th>
                        <th scope="col" class="manage-column column-stock">
                            <span>Stock</span>
                        </th>
                        <th scope="col" class="manage-column column-status">
                            <span>Status</span>
                        </th>
                        <th scope="col" class="manage-column column-sync-date">
                            <span>Last Sync</span>
                        </th>
                    </tr>
                </thead>
                <tbody id="the-list">
                    <?php if (empty($products_data)): ?>
                        <tr>
                            <td colspan="9">
                                <?php if ($total_products === 0 && empty($search) && empty($brand) && $product_status === 'all'): ?>
                                    <div class="notice notice-info inline">
                                        <p>
                                            <strong>No ShopCommerce products found.</strong>
                                        </p>
                                        <p>
                                            This appears to be because no products have been synchronized yet. You can:
                                        </p>
                                        <ul>
                                            <li><a href="<?php echo admin_url('admin.php?page=shopcommerce-sync-control'); ?>">Run a manual sync</a> to import products from ShopCommerce</li>
                                            <li>Check the <a href="<?php echo admin_url('admin.php?page=shopcommerce-sync'); ?>">dashboard</a> for sync status and errors</li>
                                            <li>Verify your API connection in <a href="<?php echo admin_url('admin.php?page=shopcommerce-sync-settings'); ?>">settings</a></li>
                                        </ul>
                                    </div>
                                <?php else: ?>
                                    No products found matching your current filters.
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products_data as $product): ?>
                            <tr>
                                <th scope="row" class="check-column">
                                    <input type="checkbox" name="products[]" value="<?php echo $product['id']; ?>" class="product-cb">
                                </td>
                                <td class="column-id">
                                    <?php echo $product['id']; ?>
                                </td>
                                <td class="column-name column-primary">
                                    <strong>
                                        <a href="<?php echo get_edit_post_link($product['id']); ?>" class="row-title">
                                            <?php echo esc_html($product['name']); ?>
                                        </a>
                                    </strong>
                                    <div class="row-actions">
                                        <span class="edit">
                                            <a href="<?php echo get_edit_post_link($product['id']); ?>">Edit</a> |
                                        </span>
                                        <span class="view">
                                            <a href="<?php echo get_permalink($product['id']); ?>" target="_blank">View</a> |
                                        </span>
                                        <?php if ($product['status'] !== 'trash'): ?>
                                            <span class="trash">
                                                <a href="#" class="trash-product" data-id="<?php echo $product['id']; ?>">Trash</a>
                                            </span>
                                        <?php else: ?>
                                            <span class="delete">
                                                <a href="#" class="delete-product" data-id="<?php echo $product['id']; ?>">Delete Permanently</a>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="column-sku">
                                    <?php echo esc_html($product['sku']); ?>
                                </td>
                                <td class="column-brand">
                                    <?php echo esc_html($product['brand']); ?>
                                </td>
                                <td class="column-price">
                                    <?php echo wc_price($product['price']); ?>
                                </td>
                                <td class="column-stock">
                                    <?php if ($product['stock_status'] === 'instock'): ?>
                                        <span class="stock-in-stock"><?php echo $product['stock_quantity']; ?> in stock</span>
                                    <?php elseif ($product['stock_status'] === 'outofstock'): ?>
                                        <span class="stock-out-of-stock">Out of stock</span>
                                    <?php else: ?>
                                        <span class="stock-on-backorder">On backorder</span>
                                    <?php endif; ?>
                                </td>
                                <td class="column-status">
                                    <?php if ($product['status'] === 'publish'): ?>
                                        <span class="status-publish">Published</span>
                                    <?php elseif ($product['status'] === 'draft'): ?>
                                        <span class="status-draft">Draft</span>
                                    <?php elseif ($product['status'] === 'trash'): ?>
                                        <span class="status-trash">Trashed</span>
                                    <?php endif; ?>
                                </td>
                                <td class="column-sync-date">
                                    <?php if ($product['sync_date']): ?>
                                        <?php echo date_i18n('M j, Y g:i A', strtotime($product['sync_date'])); ?>
                                    <?php else: ?>
                                        <em>Never</em>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td class="manage-column column-cb check-column">
                            <input type="checkbox" id="cb-select-all-2">
                        </td>
                        <th scope="col" class="manage-column column-id sortable">
                            <span>ID</span>
                        </th>
                        <th scope="col" class="manage-column column-name column-primary">
                            <span>Product</span>
                        </th>
                        <th scope="col" class="manage-column column-sku">
                            <span>SKU</span>
                        </th>
                        <th scope="col" class="manage-column column-brand">
                            <span>Brand</span>
                        </th>
                        <th scope="col" class="manage-column column-price sortable">
                            <span>Price</span>
                        </th>
                        <th scope="col" class="manage-column column-stock">
                            <span>Stock</span>
                        </th>
                        <th scope="col" class="manage-column column-status">
                            <span>Status</span>
                        </th>
                        <th scope="col" class="manage-column column-sync-date">
                            <span>Last Sync</span>
                        </th>
                    </tr>
                </tfoot>
            </table>

            <div class="tablenav bottom">
                <div class="alignleft actions bulkactions">
                    <select name="bulk_action">
                        <option value="-1">Bulk Actions</option>
                        <option value="trash">Move to Trash</option>
                        <option value="delete">Delete Permanently</option>
                        <option value="publish">Publish</option>
                        <option value="draft">Set to Draft</option>
                    </select>
                    <input type="submit" class="button action" value="Apply">
                </div>
                <div class="tablenav-pages">
                    <span class="displaying-num">
                        <?php echo sprintf(
                            _n(
                                '%s item',
                                '%s items',
                                $total_products,
                                'shopcommerce-sync'
                            ),
                            number_format($total_products)
                        ); ?>
                    </span>
                    <span class="pagination-links">
                        <?php echo paginate_links([
                            'base' => add_query_arg($pagination_args, admin_url('admin.php')),
                            'format' => '&paged=%#%',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total' => $total_pages,
                            'current' => $current_page,
                            'before_page_number' => '<span class="screen-reader-text">Page </span>',
                        ]); ?>
                    </span>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Product Actions Modal -->
<div id="product-action-modal" class="shopcommerce-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modal-title">Confirm Action</h3>
            <button type="button" class="close-modal">&times;</button>
        </div>
        <div class="modal-body">
            <p id="modal-message">Are you sure you want to perform this action?</p>
            <div class="modal-actions">
                <button type="button" class="button button-primary" id="modal-confirm">Confirm</button>
                <button type="button" class="button" id="modal-cancel">Cancel</button>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Handle product trash/delete actions
    $('.trash-product, .delete-product').on('click', function(e) {
        e.preventDefault();

        var $btn = $(this);
        var productId = $btn.data('id');
        var isDelete = $btn.hasClass('delete-product');
        var action = isDelete ? 'delete' : 'trash';

        $('#modal-title').text(isDelete ? 'Delete Product Permanently' : 'Move to Trash');
        $('#modal-message').text('Are you sure you want to ' + action + ' this product? This action cannot be undone.');
        $('#product-action-modal').data('product-id', productId).data('action', action).show();
    });

    // Confirm modal action
    $('#modal-confirm').on('click', function() {
        var $modal = $('#product-action-modal');
        var productId = $modal.data('product-id');
        var action = $modal.data('action');

        $.ajax({
            url: shopcommerce_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'shopcommerce_manage_products',
                nonce: shopcommerce_admin.nonce,
                product_action: action,
                product_ids: [productId]
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Action failed: ' + response.data.error);
                }
            },
            error: function() {
                alert('Action failed due to network error.');
            }
        });
    });

    // Cancel modal
    $('#modal-cancel, .close-modal').on('click', function() {
        $('#product-action-modal').hide();
    });

    // Handle bulk actions
    $('#products-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var bulkAction = $form.find('select[name="bulk_action"]').val();
        var selectedProducts = $form.find('input[name="products[]"]:checked').map(function() {
            return this.value;
        }).get();

        if (bulkAction === '-1' || selectedProducts.length === 0) {
            return;
        }

        if (confirm('Are you sure you want to ' + bulkAction + ' ' + selectedProducts.length + ' products?')) {
            $.ajax({
                url: shopcommerce_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'shopcommerce_bulk_products',
                    nonce: shopcommerce_admin.nonce,
                    bulk_action: bulkAction,
                    product_ids: selectedProducts
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Bulk action failed: ' + response.data.error);
                    }
                },
                error: function() {
                    alert('Bulk action failed due to network error.');
                }
            });
        }
    });

    // Select all checkboxes
    $('#cb-select-all-1, #cb-select-all-2').on('change', function() {
        $('.product-cb').prop('checked', $(this).prop('checked'));
    });

    // Handle page size change
    $('.page-size-select').on('change', function() {
        $('.page-size-form').submit();
    });
});
</script>

<style>
.shopcommerce-admin .column-id {
    width: 60px;
}
.shopcommerce-admin .column-name {
    width: 25%;
}
.shopcommerce-admin .column-sku,
.shopcommerce-admin .column-brand,
.shopcommerce-admin .column-price,
.shopcommerce-admin .column-stock,
.shopcommerce-admin .column-status,
.shopcommerce-admin .column-sync-date {
    width: 10%;
}
.stock-in-stock {
    color: #46b450;
}
.stock-out-of-stock {
    color: #dc3232;
}
.stock-on-backorder {
    color: #ffb900;
}
.status-publish {
    color: #46b450;
}
.status-draft {
    color: #ffb900;
}
.status-trash {
    color: #dc3232;
}
.filter-form {
    display: inline-block;
    margin-left: 10px;
}
.filter-form select {
    margin-right: 5px;
}
.page-size-form select {
    margin-right: 5px;
    min-width: 120px;
}
</style>