<?php
/**
 * Brands and Categories Management template for ShopCommerce Sync
 *
 * @package ShopCommerce_Sync
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get jobs store, config manager and helpers
$jobs_store = isset($GLOBALS['shopcommerce_jobs_store']) ? $GLOBALS['shopcommerce_jobs_store'] : null;
$config = isset($GLOBALS['shopcommerce_config']) ? $GLOBALS['shopcommerce_config'] : null;
$helpers = isset($GLOBALS['shopcommerce_helpers']) ? $GLOBALS['shopcommerce_helpers'] : null;
$logger = isset($GLOBALS['shopcommerce_logger']) ? $GLOBALS['shopcommerce_logger'] : null;

// Get data - use jobs store if available, fallback to config
$brands = $jobs_store ? $jobs_store->get_brands(false) : ($config ? $config->get_brands(false) : []);
$categories = $jobs_store ? $jobs_store->get_categories(false) : ($config ? $config->get_categories(false) : []);
$active_brands = $jobs_store ? $jobs_store->get_brands(true) : ($config ? $config->get_brands(true) : []);
$active_categories = $jobs_store ? $jobs_store->get_categories(true) : ($config ? $config->get_categories(true) : []);

// Get current tab
$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'brands';
?>

<div class="wrap shopcommerce-admin">
    <h1 class="wp-heading-inline">ShopCommerce Brands & Categories</h1>
    <a href="<?php echo admin_url('admin.php?page=shopcommerce-sync'); ?>" class="page-title-action">Back to Dashboard</a>
    <hr class="wp-header-end">

    <!-- Tabs -->
    <nav class="nav-tab-wrapper">
        <a href="<?php echo esc_url(add_query_arg('tab', 'brands')); ?>" class="nav-tab <?php echo $current_tab === 'brands' ? 'nav-tab-active' : ''; ?>">
            Brands
        </a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'categories')); ?>" class="nav-tab <?php echo $current_tab === 'categories' ? 'nav-tab-active' : ''; ?>">
            Categories
        </a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'jobs')); ?>" class="nav-tab <?php echo $current_tab === 'jobs' ? 'nav-tab-active' : ''; ?>">
            Sync Jobs
        </a>
    </nav>

    <div class="tab-content">
        <?php if ($current_tab === 'brands'): ?>
            <!-- Brands Tab -->
            <div class="brands-section">
                <div class="section-header">
                    <h2>Manage Brands</h2>
                    <div class="header-actions">
                        <button type="button" class="button" id="sync-api-brands-btn" title="Sync brands from ShopCommerce API">
                            <span class="dashicons dashicons-update"></span>
                            Sync API Brands
                        </button>
                        <button type="button" class="button button-primary" id="add-brand-btn">
                            Add New Brand
                        </button>
                    </div>
                </div>

                <!-- Brands Table -->
                <div class="table-container">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th scope="col" class="manage-column column-id">ID</th>
                                <th scope="col" class="manage-column column-name">Name</th>
                                <th scope="col" class="manage-column column-description">Description</th>
                                <th scope="col" class="manage-column column-categories">Categories</th>
                                <th scope="col" class="manage-column column-status">Status</th>
                                <th scope="col" class="manage-column column-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($brands as $brand): ?>
                                <?php
                                // Use jobs store if available, fallback to config
                                if ($jobs_store) {
                                    $brand_categories = $jobs_store->get_brand_categories($brand->id);
                                    $has_all_categories = $jobs_store->brand_has_all_categories($brand->id);
                                } else {
                                    $brand_categories = $config->get_brand_categories($brand->id);
                                    $has_all_categories = $config->brand_has_all_categories($brand->id);
                                }
                                $category_count = count($brand_categories);
                                $total_categories = count($active_categories);
                                ?>
                                <tr>
                                    <td><?php echo $brand->id; ?></td>
                                    <td>
                                        <strong><?php echo esc_html($brand->name); ?></strong>
                                    </td>
                                    <td><?php echo esc_html($brand->description); ?></td>
                                    <td>
                                        <?php if ($has_all_categories): ?>
                                            <span class="badge badge-success">All Categories (<?php echo $total_categories; ?>)</span>
                                        <?php else: ?>
                                            <span class="badge badge-info"><?php echo $category_count; ?> Categories</span>
                                                                                    <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $brand->is_active ? 'status-active' : 'status-inactive'; ?>">
                                            <?php echo $brand->is_active ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="row-actions visible">
                                            <?php if ($brand->is_active): ?>
                                                <button type="button" class="button-link sync-brand-btn" data-brand-id="<?php echo $brand->id; ?>" data-brand-name="<?php echo esc_attr($brand->name); ?>" title="Synchronize all products for this brand immediately">
                                                    <span class="dashicons dashicons-update"></span>
                                                    Sync Now
                                                </button>
                                                |
                                            <?php endif; ?>
                                            <button type="button" class="button-link toggle-brand-btn" data-brand-id="<?php echo $brand->id; ?>" data-active="<?php echo $brand->is_active; ?>">
                                                <?php echo $brand->is_active ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                            |
                                            <button type="button" class="button-link delete-brand-btn" data-brand-id="<?php echo $brand->id; ?>" data-brand-name="<?php echo esc_attr($brand->name); ?>">
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (empty($brands)): ?>
                    <div class="notice notice-info inline">
                        <p>No brands found. Click "Add New Brand" to create your first brand.</p>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($current_tab === 'categories'): ?>
            <!-- Categories Tab -->
            <div class="categories-section">
                <div class="section-header">
                    <h2>Manage Categories</h2>
                    <div class="header-actions">
                        <button type="button" class="button button-secondary" id="sync-categories-btn">
                            <span class="dashicons dashicons-update"></span>
                            Sync Categories from API
                        </button>
                                            </div>
                </div>

                <!-- Categories Table -->
                <div class="table-container">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th scope="col" class="manage-column column-id">ID</th>
                                <th scope="col" class="manage-column column-code">Code</th>
                                <th scope="col" class="manage-column column-name">Name</th>
                                <th scope="col" class="manage-column column-description">Description</th>
                                <th scope="col" class="manage-column column-status">Status</th>
                                <th scope="col" class="manage-column column-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td><?php echo $category->id; ?></td>
                                    <td>
                                        <code><?php echo $category->code; ?></code>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html($category->name); ?></strong>
                                    </td>
                                    <td><?php echo esc_html($category->description); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $category->is_active ? 'status-active' : 'status-inactive'; ?>">
                                            <?php echo $category->is_active ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="row-actions visible">
                                            <button type="button" class="button-link toggle-category-btn" data-category-id="<?php echo $category->id; ?>" data-active="<?php echo $category->is_active; ?>">
                                                <?php echo $category->is_active ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                            |
                                            <button type="button" class="button-link delete-category-btn" data-category-id="<?php echo $category->id; ?>" data-category-name="<?php echo esc_attr($category->name); ?>">
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (empty($categories)): ?>
                    <div class="notice notice-info inline">
                        <p>No categories found. Click "Add New Category" to create your first category.</p>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($current_tab === 'jobs'): ?>
            <!-- Sync Jobs Tab -->
            <div class="jobs-section">
                <div class="section-header">
                    <h2>Sync Jobs Configuration</h2>
                    <p>
                        <strong>Active Brands:</strong> <?php echo count($active_brands); ?> |
                        <strong>Active Categories:</strong> <?php echo count($active_categories); ?>
                    </p>
                    <div class="header-actions">
                        <button type="button" id="rebuild-jobs-btn" class="button button-primary">
                            <span class="dashicons dashicons-update"></span>
                            Rebuild Sync Jobs
                        </button>
                        <span class="spinner" style="display: none;"></span>
                    </div>
                </div>

                <!-- Jobs List -->
                <div class="jobs-list">
                    <?php if ($jobs_store || $config): ?>
                        <?php
                        // Use jobs store if available, fallback to config
                        if ($jobs_store) {
                            $jobs = $jobs_store->get_jobs();
                        } else {
                            $jobs = $config->build_jobs_list();
                        }
                        ?>
                        <?php if (!empty($jobs)): ?>
                            <div class="job-cards">
                                <?php foreach ($jobs as $job): ?>
                                    <?php
                                    // Use jobs store if available, fallback to config
                                    if ($jobs_store) {
                                        $brand_categories = $jobs_store->get_brand_categories($job['brand_id']);
                                        $has_all_categories = $jobs_store->brand_has_all_categories($job['brand_id']);
                                    } else {
                                        $brand_categories = $config->get_brand_categories($job['brand_id']);
                                        $has_all_categories = $config->brand_has_all_categories($job['brand_id']);
                                    }
                                    ?>
                                    <div class="job-card">
                                        <div class="job-header">
                                            <h3><?php echo esc_html($job['brand']); ?></h3>
                                            <span class="job-status status-active">Active</span>
                                        </div>
                                        <div class="job-details">
                                            <div class="job-categories">
                                                <strong>Categories:</strong>
                                                <?php if ($has_all_categories): ?>
                                                    <span class="badge badge-success">All Categories</span>
                                                <?php else: ?>
                                                    <?php foreach ($brand_categories as $category): ?>
                                                        <span class="badge badge-info"><?php echo esc_html($category->name); ?></span>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                                                            </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="notice notice-warning inline">
                                <p>No active sync jobs found. Make sure you have active brands and categories configured.</p>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="notice notice-error inline">
                            <p>Neither jobs store nor configuration manager available.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Brand Modal -->
<div id="brand-modal" class="shopcommerce-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="brand-modal-title">Add New Brand</h3>
            <button type="button" class="close-modal">&times;</button>
        </div>
        <div class="modal-body">
            <form id="brand-form">
                <input type="hidden" name="brand_id" id="brand-id" value="">
                <div class="form-row">
                    <label for="brand-name">Brand Name *</label>
                    <input type="text" name="name" id="brand-name" class="regular-text" required>
                </div>
                <div class="form-row">
                    <label for="brand-description">Description</label>
                    <textarea name="description" id="brand-description" class="large-text" rows="3"></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="button button-primary">Save Brand</button>
                    <button type="button" class="button close-modal-btn">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div id="confirm-modal" class="shopcommerce-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="confirm-modal-title">Confirm Action</h3>
            <button type="button" class="close-modal">&times;</button>
        </div>
        <div class="modal-body">
            <p id="confirm-modal-message">Are you sure you want to perform this action?</p>
            <div class="modal-actions">
                <button type="button" class="button button-primary" id="confirm-modal-confirm">Confirm</button>
                <button type="button" class="button" id="confirm-modal-cancel">Cancel</button>
            </div>
        </div>
    </div>
</div>

<style>
/* Brands and Categories Page Styles */
.nav-tab-wrapper {
    margin: 20px 0 30px 0;
}

.tab-content {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.section-header h2 {
    margin: 0;
}

.table-container {
    margin: 20px 0;
}

.status-badge {
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-active {
    background: #d4edda;
    color: #155724;
}

.status-inactive {
    background: #f8d7da;
    color: #721c24;
}

.badge {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
    margin-right: 5px;
}

.badge-success {
    background: #d4edda;
    color: #155724;
}

.badge-info {
    background: #cce5ff;
    color: #004085;
}

.job-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.job-card {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 4px;
    padding: 20px;
}

.job-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.job-header h3 {
    margin: 0;
}

.job-details {
    margin-bottom: 15px;
}

.job-actions {
    text-align: right;
}

/* Modal Forms */
.form-row {
    margin-bottom: 15px;
}

.form-row label {
    display: block;
    font-weight: 600;
    margin-bottom: 5px;
}

.form-row input,
.form-row textarea {
    width: 100%;
}

.form-row .description {
    margin: 5px 0 0 0;
    font-style: italic;
    color: #666;
}

.form-actions {
    margin-top: 20px;
    text-align: right;
}

.form-actions .button {
    margin-left: 10px;
}

.categories-selection {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 4px;
    padding: 15px;
}

.categories-selection label {
    display: block;
    margin-bottom: 10px;
    font-weight: normal;
}

.categories-selection h4 {
    margin: 15px 0 10px 0;
}

.individual-categories label {
    margin-left: 20px;
}

.button-link {
    background: none;
    border: none;
    color: #0073aa;
    text-decoration: underline;
    padding: 0;
    cursor: pointer;
    font-size: 13px;
}

.button-link:hover {
    color: #005a87;
}

/* Responsive */
@media (max-width: 782px) {
    .section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }

    .job-cards {
        grid-template-columns: 1fr;
    }

    .categories-selection {
        padding: 10px;
    }
}

.header-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

.header-actions .button {
    display: flex;
    align-items: center;
    gap: 5px;
}

/* Spinning animation for dashicons */
.spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Modal handling
    function openModal(modalId) {
        $('#' + modalId).show();
    }

    function closeModal(modalId) {
        $('#' + modalId).hide();
    }

    // Close modal buttons
    $('.close-modal, .close-modal-btn').on('click', function() {
        $(this).closest('.shopcommerce-modal').hide();
    });

    // Brand management
    $('#add-brand-btn').on('click', function() {
        $('#brand-modal-title').text('Add New Brand');
        $('#brand-form')[0].reset();
        $('#brand-id').val('');
        openModal('brand-modal');
    });

    
    
    // Handle synchronous brand sync
    $(document).on('click', '.sync-brand-btn', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var brandId = $btn.data('brand-id');
        var brandName = $btn.data('brand-name');
        var $row = $btn.closest('tr');

        // Show confirmation dialog
        if (!confirm('Are you sure you want to synchronously sync all products for "' + brandName + '"? This will process all products immediately without batching.')) {
            return;
        }

        // Disable button and show loading state
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-spinner dashicons-spin"></span> Syncing...');

        // Remove any existing status messages
        $row.find('.sync-status').remove();

        // Show syncing status
        $row.find('td:last').append('<div class="sync-status notice notice-info"><p>Starting synchronous sync for ' + brandName + '...</p></div>');

        // Make AJAX request
        $.ajax({
            url: shopcommerce_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'shopcommerce_sync_brand_synchronously',
                nonce: shopcommerce_admin.nonce,
                brand_id: brandId
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    var statusHtml = '<div class="sync-status notice notice-success"><p>';
                    statusHtml += '<strong>Sync completed for ' + brandName + '</strong><br>';
                    statusHtml += 'Products processed: ' + data.results.total + '<br>';
                    statusHtml += 'Created: ' + data.results.created + ', Updated: ' + data.results.updated + '<br>';
                    if (data.results.errors > 0) {
                        statusHtml += 'Errors: ' + data.results.errors;
                    }
                    statusHtml += '<br>Duration: ' + data.duration + ' seconds';
                    statusHtml += '</p></div>';

                    $row.find('td:last').append(statusHtml);

                    // Refresh brands list to show updated sync times
                    setTimeout(function() {
                        location.reload();
                    }, 3000);
                } else {
                    $row.find('td:last').append('<div class="sync-status notice notice-error"><p>Error: ' + response.data + '</p></div>');
                }
            },
            error: function(xhr, status, error) {
                var errorMessage = 'Network error occurred';
                if (xhr.responseJSON && xhr.responseJSON.data) {
                    errorMessage = xhr.responseJSON.data;
                }
                $row.find('td:last').append('<div class="sync-status notice notice-error"><p>' + errorMessage + '</p></div>');
            },
            complete: function() {
                // Re-enable button
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Sync Now');
            }
        });
    });

    // Toggle functions
    $('.toggle-brand-btn').on('click', function() {
        var brandId = $(this).data('brand-id');
        var currentActive = $(this).data('active');
        var newActive = !currentActive;

        $('#confirm-modal-title').text(newActive ? 'Activate Brand' : 'Deactivate Brand');
        $('#confirm-modal-message').text('Are you sure you want to ' + (newActive ? 'activate' : 'deactivate') + ' this brand?');
        $('#confirm-modal-confirm').data('action', 'toggle-brand');
        $('#confirm-modal-confirm').data('brand-id', brandId);
        $('#confirm-modal-confirm').data('active', newActive);

        openModal('confirm-modal');
    });

    $('.toggle-category-btn').on('click', function() {
        var categoryId = $(this).data('category-id');
        var currentActive = $(this).data('active');
        var newActive = !currentActive;

        $('#confirm-modal-title').text(newActive ? 'Activate Category' : 'Deactivate Category');
        $('#confirm-modal-message').text('Are you sure you want to ' + (newActive ? 'activate' : 'deactivate') + ' this category?');
        $('#confirm-modal-confirm').data('action', 'toggle-category');
        $('#confirm-modal-confirm').data('category-id', categoryId);
        $('#confirm-modal-confirm').data('active', newActive);

        openModal('confirm-modal');
    });

    // Delete functions
    $('.delete-brand-btn').on('click', function() {
        var brandId = $(this).data('brand-id');
        var brandName = $(this).data('brand-name');

        $('#confirm-modal-title').text('Delete Brand');
        $('#confirm-modal-message').text('Are you sure you want to delete "' + brandName + '"? This action cannot be undone.');
        $('#confirm-modal-confirm').data('action', 'delete-brand');
        $('#confirm-modal-confirm').data('brand-id', brandId);

        openModal('confirm-modal');
    });

    $('.delete-category-btn').on('click', function() {
        var categoryId = $(this).data('category-id');
        var categoryName = $(this).data('category-name');

        $('#confirm-modal-title').text('Delete Category');
        $('#confirm-modal-message').text('Are you sure you want to delete "' + categoryName + '"? This action cannot be undone.');
        $('#confirm-modal-confirm').data('action', 'delete-category');
        $('#confirm-modal-confirm').data('category-id', categoryId);

        openModal('confirm-modal');
    });

    // Confirm modal actions
    $('#confirm-modal-confirm').on('click', function() {
        var action = $(this).data('action');
        var brandId = $(this).data('brand-id');
        var categoryId = $(this).data('category-id');
        var active = $(this).data('active');

        var ajaxAction = '';
        var data = {
            nonce: shopcommerce_admin.nonce
        };

        switch(action) {
            case 'toggle-brand':
                ajaxAction = 'shopcommerce_toggle_brand';
                data.brand_id = brandId;
                data.active = active;
                break;
            case 'toggle-category':
                ajaxAction = 'shopcommerce_toggle_category';
                data.category_id = categoryId;
                data.active = active;
                break;
            case 'delete-brand':
                ajaxAction = 'shopcommerce_delete_brand';
                data.brand_id = brandId;
                break;
            case 'delete-category':
                ajaxAction = 'shopcommerce_delete_category';
                data.category_id = categoryId;
                break;
        }

        data.action = ajaxAction;

        $.ajax({
            url: shopcommerce_admin.ajax_url,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.data.error);
                    closeModal('confirm-modal');
                }
            },
            error: function() {
                alert('Network error occurred.');
                closeModal('confirm-modal');
            }
        });
    });

    $('#confirm-modal-cancel').on('click', function() {
        closeModal('confirm-modal');
    });

    // Sync Categories from API functionality
    $('#sync-categories-btn').on('click', function() {
        var $btn = $(this);
        var $spinner = $btn.next('.spinner');

        if (confirm('Are you sure you want to sync categories from the ShopCommerce API? This will create new plugin categories that don\'t already exist.')) {
            $btn.prop('disabled', true);
            $spinner.show();

            $.ajax({
                url: shopcommerce_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'shopcommerce_sync_categories',
                    nonce: shopcommerce_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('Network error occurred while syncing categories.');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                    $spinner.hide();
                }
            });
        }
    });

    // Sync API Brands functionality
    $('#sync-api-brands-btn').on('click', function() {
        var $btn = $(this);

        if (confirm('Are you sure you want to sync brands from the ShopCommerce API? This will create new brands that don\'t already exist in the plugin. Default brands will be active, others will be inactive.')) {
            $btn.prop('disabled', true);
            $btn.find('.dashicons').addClass('spin');

            $.ajax({
                url: shopcommerce_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'shopcommerce_fetch_api_brands',
                    nonce: shopcommerce_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    alert('Network error occurred while syncing brands from API: ' + error);
                },
                complete: function() {
                    $btn.prop('disabled', false);
                    $btn.find('.dashicons').removeClass('spin');
                }
            });
        }
    });

    // Rebuild Jobs functionality
    $('#rebuild-jobs-btn').on('click', function() {
        var $btn = $(this);
        var $spinner = $btn.next('.spinner');

        if (confirm('Are you sure you want to rebuild sync jobs from the current brand and category configuration? This will update the sync job queue.')) {
            $btn.prop('disabled', true);
            $spinner.show();

            $.ajax({
                url: shopcommerce_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'shopcommerce_rebuild_jobs',
                    nonce: shopcommerce_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('Sync jobs rebuilt successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('Network error occurred while rebuilding jobs.');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                    $spinner.hide();
                }
            });
        }
    });
});
</script>