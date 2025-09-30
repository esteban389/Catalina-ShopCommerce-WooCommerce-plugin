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
$all_brands = $jobs_store ? $jobs_store->get_brands(false) : ($config ? $config->get_brands(false) : []);
$categories = $jobs_store ? $jobs_store->get_categories(false) : ($config ? $config->get_categories(false) : []);
$active_brands = $jobs_store ? $jobs_store->get_brands(true) : ($config ? $config->get_brands(true) : []);
$active_categories = $jobs_store ? $jobs_store->get_categories(true) : ($config ? $config->get_categories(true) : []);

// Organize brands by active status first
$brands = [];
$inactive_brands = [];

foreach ($all_brands as $brand) {
    if ($brand->is_active) {
        $brands[] = $brand;
    } else {
        $inactive_brands[] = $brand;
    }
}

// Merge active brands first, then inactive
$brands = array_merge($brands, $inactive_brands);

// Organize categories by active status first
$organized_categories = [];
$inactive_categories = [];

foreach ($categories as $category) {
    if ($category->is_active) {
        $organized_categories[] = $category;
    } else {
        $inactive_categories[] = $category;
    }
}

// Merge active categories first, then inactive
$categories = array_merge($organized_categories, $inactive_categories);

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
                                        <div class="brand-categories-cell" data-brand-id="<?php echo $brand->id; ?>" data-brand-name="<?php echo esc_attr($brand->name); ?>" data-all-categories="<?php echo $has_all_categories ? 'true' : 'false'; ?>">
                                            <?php if ($has_all_categories): ?>
                                                <span class="badge badge-success category-clickable">All Categories (<?php echo $total_categories; ?>)</span>
                                            <?php else: ?>
                                                <span class="badge badge-info category-clickable"><?php echo $category_count; ?> Categories</span>
                                            <?php endif; ?>
                                            <div class="category-edit-hint">
                                                <small>Click to edit categories</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $brand->is_active ? 'status-active' : 'status-inactive'; ?>">
                                            <?php echo $brand->is_active ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="row-actions visible">
                                            <?php if ($brand->is_active): ?>
                                                <button type="button" class="button sync-brand-btn" data-brand-id="<?php echo $brand->id; ?>" data-brand-name="<?php echo esc_attr($brand->name); ?>" title="Synchronize all products for this brand immediately">
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
                                <th scope="col" class="manage-column column-markup">Markup %</th>
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
                                        <div class="markup-cell" data-category-id="<?php echo $category->id; ?>" data-category-code="<?php echo esc_attr($category->code); ?>">
                                            <span class="markup-value">
                                                <?php
                                                // Get markup percentage for this category
                                                $markup_percentage = 0;

                                                if ($config && method_exists($config, 'get_category_markup')) {
                                                    $markup_percentage = $config->get_category_markup($category->code);
                                                }

                                                echo $markup_percentage > 0 ? number_format($markup_percentage, 1) . '%' : 'Default';
                                                ?>
                                            </span>
                                            <div class="markup-edit" style="display: none;">
                                                <input type="number" class="markup-input" step="0.1" min="0" max="100" placeholder="0.0">
                                                <span class="markup-percent">%</span>
                                                <div class="markup-actions">
                                                    <button type="button" class="button button-small button-primary save-markup-btn">
                                                        <span class="dashicons dashicons-yes"></span>
                                                    </button>
                                                    <button type="button" class="button button-small cancel-markup-btn">
                                                        <span class="dashicons dashicons-no"></span>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
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

<!-- Brand Categories Modal -->
<div id="brand-categories-modal" class="shopcommerce-modal" style="display: none;">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3 id="brand-categories-modal-title">Edit Brand Categories</h3>
            <button type="button" class="close-modal">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="brand-categories-brand-id" value="">
            <input type="hidden" id="brand-categories-brand-name" value="">

            <div class="categories-selection-container">
                <div class="categories-header">
                    <h4>Categories for <strong id="brand-categories-display-name"></strong></h4>
                    <div class="category-controls">
                        <button type="button" class="button" id="select-all-categories-btn">Select All</button>
                        <button type="button" class="button" id="deselect-all-categories-btn">Deselect All</button>
                    </div>
                </div>

                <div class="categories-grid" id="brand-categories-grid">
                    <!-- Categories will be loaded here via JavaScript -->
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <div class="modal-actions">
                <button type="button" class="button button-primary" id="save-brand-categories-btn">Save Categories</button>
                <button type="button" class="button" id="cancel-brand-categories-btn">Cancel</button>
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

.sync-brand-btn.button {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    height: auto;
    padding: 2px 8px;
    line-height: 1.4;
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

/* Markup Editing Styles */
.markup-cell {
    position: relative;
}

.markup-value {
    cursor: pointer;
    padding: 6px 12px;
    border-radius: 4px;
    transition: all 0.2s ease;
    display: inline-block;
    min-width: 70px;
    text-align: center;
    font-weight: 600;
    border: 1px solid #ddd;
    background: #f9f9f9;
    position: relative;
}

.markup-value:hover {
    background-color: #e7f3ff;
    color: #0073aa;
    border-color: #0073aa;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0, 115, 170, 0.2);
}

.markup-value::after {
    content: "✏️";
    position: absolute;
    right: 4px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 11px;
    opacity: 0;
    transition: opacity 0.2s ease;
}

.markup-value:hover::after {
    opacity: 1;
}

.markup-edit {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-wrap: wrap;
    max-width: 150px;
}

.markup-input {
    width: 60px !important;
    padding: 2px 4px !important;
    border: 1px solid #8c8f94 !important;
    border-radius: 3px !important;
    font-size: 12px !important;
    text-align: center;
}

.markup-input:focus {
    border-color: #0073aa !important;
    box-shadow: 0 0 0 2px rgba(0, 115, 170, 0.25) !important;
    outline: none;
}

.markup-percent {
    font-size: 12px;
    color: #50575e;
    font-weight: 600;
}

.markup-actions {
    display: flex;
    gap: 2px;
    margin-left: 4px;
}

.markup-actions .button {
    padding: 2px 6px !important;
    height: auto !important;
    line-height: 1 !important;
    font-size: 12px !important;
    min-width: auto !important;
    border-radius: 3px !important;
}

.markup-actions .button .dashicons {
    font-size: 14px !important;
    line-height: 14px !important;
    width: 14px !important;
    height: 14px !important;
}

.markup-actions .button-primary {
    background: #0073aa;
    border-color: #0073aa;
}

.markup-actions .button-primary:hover {
    background: #005a87;
    border-color: #005a87;
}

.markup-actions .button-small {
    background: #f0f0f1;
    border-color: #8c8f94;
    color: #50575e;
}

.markup-actions .button-small:hover {
    background: #e0e0e1;
    border-color: #666;
    color: #32373c;
}

/* Brand Categories Cell Styles */
.brand-categories-cell {
    cursor: pointer;
    position: relative;
}

.category-clickable {
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
}

.category-clickable:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0, 115, 170, 0.2);
    background-color: #e7f3ff;
}

.category-edit-hint {
    color: #666;
    font-size: 10px;
    margin-top: 2px;
    opacity: 0;
    transition: opacity 0.2s ease;
}

.brand-categories-cell:hover .category-edit-hint {
    opacity: 1;
}

/* Brand Categories Modal Styles */
.modal-large {
    max-width: 700px;
    width: 90vw;
}

.categories-selection-container {
    max-height: 60vh;
    overflow-y: auto;
}

.categories-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #ddd;
}

.categories-header h4 {
    margin: 0;
    font-size: 16px;
    color: #23282d;
}

.category-controls {
    display: flex;
    gap: 8px;
}

.categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 12px;
    margin-top: 15px;
}

.category-item {
    display: flex;
    align-items: center;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: #fff;
    transition: all 0.2s ease;
    cursor: pointer;
}

.category-item:hover {
    border-color: #0073aa;
    background-color: #f9f9f9;
}

.category-item.selected {
    border-color: #0073aa;
    background-color: #e7f3ff;
}

.category-checkbox {
    margin-right: 8px;
}

.category-info {
    flex: 1;
}

.category-name {
    font-weight: 600;
    color: #23282d;
    display: block;
    margin-bottom: 2px;
}

.category-code {
    font-size: 12px;
    color: #666;
    background: #f0f0f1;
    padding: 2px 6px;
    border-radius: 3px;
    display: inline-block;
}

.category-description {
    font-size: 11px;
    color: #666;
    margin-top: 4px;
    line-height: 1.3;
}

.modal-footer {
    padding: 20px;
    border-top: 1px solid #ddd;
    background: #f9f9f9;
    border-radius: 0 0 4px 4px;
}

.modal-actions {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
}
</style>

<script type="text/javascript">
var shopcommerce_admin_nonce = '<?php echo wp_create_nonce("shopcommerce_admin_nonce"); ?>';

jQuery(document).ready(function($) {
    // Modal handling
    function openModal(modalId) {
        $('#' + modalId).show();
    }

    function closeModal(modalId) {
        $('#' + modalId).hide();
    }

    // Notice handling
    function showNotice(message, type) {
        type = type || 'info';

        // Remove any existing notices
        $('.shopcommerce-notice').remove();

        // Create notice element
        var $notice = $('<div class="notice shopcommerce-notice notice-' + type + ' is-dismissible">')
            .html('<p>' + message + '</p>')
            .append('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>');

        // Add notice to top of the page
        $('.wrap').prepend($notice);

        // Handle dismiss button
        $notice.on('click', '.notice-dismiss', function() {
            $notice.fadeOut(300, function() {
                $(this).remove();
            });
        });

        // Auto-hide success notices after 5 seconds
        if (type === 'success') {
            setTimeout(function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        }

        // Scroll to top to show notice
        $('html, body').animate({ scrollTop: 0 }, 200);
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
                    statusHtml += 'Products processed: ' + data.products_processed + '<br>';
                    statusHtml += 'Created: ' + data.products_created + ', Updated: ' + data.products_updated + '<br>';
                    if (data.errors > 0) {
                        statusHtml += 'Errors: ' + data.errors;
                    }
                    statusHtml += '<br>Duration: ' + data.processing_time + ' seconds';
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

    // Markup percentage inline editing
    $('.markup-value').on('click', function() {
        var $cell = $(this).closest('.markup-cell');
        var $valueSpan = $(this);
        var $editDiv = $cell.find('.markup-edit');
        var $input = $cell.find('.markup-input');

        // Get current value
        var currentValue = $valueSpan.text().trim();
        var numericValue = currentValue === 'Default' ? '' : parseFloat(currentValue.replace('%', ''));

        // Set input value
        $input.val(numericValue);

        // Toggle display
        $valueSpan.hide();
        $editDiv.show();

        // Focus and select input
        $input.focus();
        $input.select();
    });

    $('.save-markup-btn').on('click', function() {
        var $cell = $(this).closest('.markup-cell');
        var $input = $cell.find('.markup-input');
        var $valueSpan = $cell.find('.markup-value');
        var categoryId = $cell.data('category-id');
        var categoryCode = $cell.data('category-code');
        var newMarkup = parseFloat($input.val()) || 0;

        // Validate input
        if (newMarkup < 0 || newMarkup > 100) {
            alert('Markup percentage must be between 0 and 100');
            $input.focus();
            return;
        }

        var $btn = $(this);
        var originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-spinner"></span>');

        // Send AJAX request to update markup
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'shopcommerce_update_category_markup',
                nonce: shopcommerce_admin.nonce,
                category_id: categoryId,
                category_code: categoryCode,
                markup_percentage: newMarkup
            },
            success: function(response) {
                if (response.success) {
                    // Update display
                    var displayValue = newMarkup > 0 ? newMarkup.toFixed(1) + '%' : 'Default';
                    $valueSpan.text(displayValue);

                    // Hide edit, show value
                    $cell.find('.markup-edit').hide();
                    $valueSpan.show();

                    // Show success message
                    showNotice('Markup percentage updated successfully', 'success');
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function() {
                alert('Network error occurred while updating markup.');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });

    $('.cancel-markup-btn').on('click', function() {
        var $cell = $(this).closest('.markup-cell');
        var $valueSpan = $cell.find('.markup-value');
        var $editDiv = $cell.find('.markup-edit');

        // Hide edit, show value
        $editDiv.hide();
        $valueSpan.show();
    });

    // Handle Enter key in input
    $(document).on('keypress', '.markup-input', function(e) {
        if (e.which === 13) { // Enter key
            $(this).closest('.markup-cell').find('.save-markup-btn').click();
            e.preventDefault();
        }
    });

    // Handle Escape key in input
    $(document).on('keydown', '.markup-input', function(e) {
        if (e.which === 27) { // Escape key
            $(this).closest('.markup-cell').find('.cancel-markup-btn').click();
            e.preventDefault();
        }
    });

    // Brand Categories Modal Functionality
    var allCategories = <?php echo json_encode($active_categories); ?>;
    var currentBrandCategories = [];

    // Open brand categories modal when clicking on categories cell
    $('.brand-categories-cell').on('click', function(e) {
        e.preventDefault();

        var brandId = $(this).data('brand-id');
        var brandName = $(this).data('brand-name');
        var allCategoriesFlag = $(this).data('all-categories') === 'true';

        // Set modal data
        $('#brand-categories-brand-id').val(brandId);
        $('#brand-categories-brand-name').val(brandName);
        $('#brand-categories-display-name').text(brandName);

        // Load current brand categories
        loadBrandCategories(brandId, allCategoriesFlag);

        // Open modal
        openModal('brand-categories-modal');
    });

    function loadBrandCategories(brandId, hasAllCategories) {
        currentBrandCategories = [];

        // Get current brand categories via AJAX
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'shopcommerce_get_brand_categories',
                nonce: shopcommerce_admin_nonce,
                brand_id: brandId
            },
            success: function(response) {
                if (response.success) {
                    currentBrandCategories = response.data.categories || [];
                    renderCategoriesGrid(hasAllCategories);
                } else {
                    showNotice('Error loading brand categories', 'error');
                }
            },
            error: function() {
                showNotice('Network error occurred while loading categories', 'error');
            }
        });
    }

    function renderCategoriesGrid(hasAllCategories) {
        var $grid = $('#brand-categories-grid');
        $grid.empty();

        // Show "All Categories" message if applicable
        if (hasAllCategories) {
            $grid.html('<div class="notice notice-info"><p>This brand is configured for all categories. Click "Deselect All" to customize.</p></div>');
            return;
        }

        // Render category items
        allCategories.forEach(function(category) {
            var isSelected = currentBrandCategories.includes(category.id);
            var $categoryItem = $('<div class="category-item' + (isSelected ? ' selected' : '') + '" data-category-id="' + category.id + '">');

            $categoryItem.html(`
                <input type="checkbox" class="category-checkbox" ${isSelected ? 'checked' : ''}>
                <div class="category-info">
                    <span class="category-name">${category.name}</span>
                    <span class="category-code">${category.code}</span>
                    ${category.description ? '<div class="category-description">' + category.description + '</div>' : ''}
                </div>
            `);

            // Handle category item click
            $categoryItem.on('click', function(e) {
                if (e.target.type !== 'checkbox') {
                    var $checkbox = $(this).find('.category-checkbox');
                    $checkbox.prop('checked', !$checkbox.prop('checked'));
                }
                $(this).toggleClass('selected');
            });

            // Handle checkbox change
            $categoryItem.find('.category-checkbox').on('change', function() {
                $(this).closest('.category-item').toggleClass('selected', $(this).prop('checked'));
            });

            $grid.append($categoryItem);
        });
    }

    // Select all categories
    $('#select-all-categories-btn').on('click', function() {
        $('.category-item').addClass('selected');
        $('.category-checkbox').prop('checked', true);
    });

    // Deselect all categories
    $('#deselect-all-categories-btn').on('click', function() {
        $('.category-item').removeClass('selected');
        $('.category-checkbox').prop('checked', false);
    });

    // Save brand categories
    $('#save-brand-categories-btn').on('click', function() {
        var brandId = $('#brand-categories-brand-id').val();
        var selectedCategories = [];

        $('.category-item.selected').each(function() {
            selectedCategories.push(parseInt($(this).data('category-id')));
        });

        var $btn = $(this);
        var originalText = $btn.text();
        $btn.prop('disabled', true).text('Saving...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'shopcommerce_update_brand_categories',
                nonce: shopcommerce_admin_nonce,
                brand_id: brandId,
                categories: selectedCategories
            },
            success: function(response) {
                if (response.success) {
                    showNotice('Brand categories updated successfully', 'success');
                    closeModal('brand-categories-modal');
                    // Reload page to update the table
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showNotice('Error: ' + response.data.message, 'error');
                }
            },
            error: function() {
                showNotice('Network error occurred while updating categories', 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).text(originalText);
            }
        });
    });

    // Cancel brand categories
    $('#cancel-brand-categories-btn').on('click', function() {
        closeModal('brand-categories-modal');
    });
});
</script>