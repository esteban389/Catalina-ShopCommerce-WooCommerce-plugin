<?php
/**
 * Orders template for ShopCommerce Sync
 *
 * @package ShopCommerce_Sync
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap shopcommerce-admin">
    <h1>ShopCommerce Orders</h1>

    <div class="orders-overview">
        <div class="overview-card">
            <h3>Total Orders with ShopCommerce Products</h3>
            <p class="big-number" id="total-orders-count">-</p>
        </div>
        <div class="overview-card">
            <h3>Completed Orders</h3>
            <p class="big-number" id="completed-orders-count">-</p>
        </div>
        <div class="overview-card">
            <h3>Processing Orders</h3>
            <p class="big-number" id="processing-orders-count">-</p>
        </div>
        <div class="overview-card">
            <h3>Total Value</h3>
            <p class="big-number" id="total-value">-</p>
        </div>
    </div>

    <div class="orders-section">
        <div class="section-header">
            <h2>Incomplete Orders with External Products</h2>
            <div class="header-actions">
                <button type="button" class="button button-secondary" id="refresh-incomplete-orders-btn">
                    <span class="dashicons dashicons-update"></span> Refresh
                </button>
            </div>
        </div>

        <!-- Incomplete Orders Filters -->
        <div class="filters-container">
            <div class="filter-group">
                <label for="search-incomplete-orders">Search:</label>
                <input type="text" id="search-incomplete-orders" class="regular-text" placeholder="Search by order # or customer...">
            </div>
            <div class="filter-group">
                <label for="filter-incomplete-status">Status:</label>
                <select id="filter-incomplete-status">
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="processing">Processing</option>
                    <option value="on-hold">On Hold</option>
                    <option value="failed">Failed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="filter-incomplete-brand">Brand:</label>
                <select id="filter-incomplete-brand">
                    <option value="">All Brands</option>
                    <option value="HP">HP</option>
                    <option value="DELL">DELL</option>
                    <option value="LENOVO">LENOVO</option>
                    <option value="APPLE">APPLE</option>
                    <option value="ASUS">ASUS</option>
                    <option value="BOSE">BOSE</option>
                    <option value="EPSON">EPSON</option>
                    <option value="JBL">JBL</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="incomplete-per-page">Per Page:</label>
                <select id="incomplete-per-page">
                    <option value="10">10</option>
                    <option value="20" selected>20</option>
                    <option value="50">50</option>
                </select>
            </div>
            <div class="filter-group">
                <button type="button" class="button" id="apply-incomplete-filters-btn">Apply Filters</button>
                <button type="button" class="button button-secondary" id="clear-incomplete-filters-btn">Clear</button>
            </div>
        </div>

        <!-- Incomplete Orders Table -->
        <div class="orders-table-container">
            <table class="wp-list-table widefat fixed striped incomplete-orders-table">
                <thead>
                    <tr>
                        <th scope="col" class="order-number">Order #</th>
                        <th scope="col" class="customer">Customer</th>
                        <th scope="col" class="date">Date</th>
                        <th scope="col" class="status">Status</th>
                        <th scope="col" class="brands">Brands</th>
                        <th scope="col" class="products">Products</th>
                        <th scope="col" class="total">Total</th>
                        <th scope="col" class="actions">Actions</th>
                    </tr>
                </thead>
                <tbody id="incomplete-orders-tbody">
                    <tr>
                        <td colspan="8" class="text-center">
                            <div class="loading-orders">
                                <span class="spinner is-active"></span>
                                <p>Loading incomplete orders...</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Incomplete Orders Pagination -->
        <div class="pagination-container">
            <div class="pagination-info" id="incomplete-pagination-info">-</div>
            <div class="pagination-links" id="incomplete-pagination-links"></div>
        </div>
    </div>

    <div class="orders-section">
        <div class="section-header">
            <h2>Orders with ShopCommerce Products</h2>
            <div class="header-actions">
                <button type="button" class="button button-secondary" id="refresh-orders-btn">
                    <span class="dashicons dashicons-update"></span> Refresh
                </button>
                <button type="button" class="button button-primary" id="update-metadata-btn">
                    <span class="dashicons dashicons-database-add"></span> Update Existing Orders
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-container">
            <div class="filter-group">
                <label for="search-orders">Search:</label>
                <input type="text" id="search-orders" class="regular-text" placeholder="Search by order #, customer, or brand...">
            </div>
            <div class="filter-group">
                <label for="filter-status">Status:</label>
                <select id="filter-status">
                    <option value="">All Statuses</option>
                    <option value="completed">Completed</option>
                    <option value="processing">Processing</option>
                    <option value="pending">Pending</option>
                    <option value="on-hold">On Hold</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="refunded">Refunded</option>
                    <option value="failed">Failed</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="filter-brand">Brand:</label>
                <select id="filter-brand">
                    <option value="">All Brands</option>
                    <option value="HP">HP</option>
                    <option value="DELL">DELL</option>
                    <option value="LENOVO">LENOVO</option>
                    <option value="APPLE">APPLE</option>
                    <option value="ASUS">ASUS</option>
                    <option value="BOSE">BOSE</option>
                    <option value="EPSON">EPSON</option>
                    <option value="JBL">JBL</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="per-page">Per Page:</label>
                <select id="per-page">
                    <option value="10">10</option>
                    <option value="20" selected>20</option>
                    <option value="50">50</option>
                </select>
            </div>
            <div class="filter-group">
                <button type="button" class="button" id="apply-filters-btn">Apply Filters</button>
                <button type="button" class="button button-secondary" id="clear-filters-btn">Clear</button>
            </div>
        </div>

        <!-- Orders Table -->
        <div class="orders-table-container">
            <table class="wp-list-table widefat fixed striped orders-table">
                <thead>
                    <tr>
                        <th scope="col" class="order-number">Order #</th>
                        <th scope="col" class="customer">Customer</th>
                        <th scope="col" class="date">Date</th>
                        <th scope="col" class="status">Status</th>
                        <th scope="col" class="brands">Brands</th>
                        <th scope="col" class="products">Products</th>
                        <th scope="col" class="total">Total</th>
                        <th scope="col" class="actions">Actions</th>
                    </tr>
                </thead>
                <tbody id="orders-tbody">
                    <tr>
                        <td colspan="8" class="text-center">
                            <div class="loading-orders">
                                <span class="spinner is-active"></span>
                                <p>Loading orders...</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="pagination-container">
            <div class="pagination-info" id="pagination-info">-</div>
            <div class="pagination-links" id="pagination-links"></div>
        </div>
    </div>

    <!-- Order Details Modal -->
    <div id="order-details-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Order Details</h3>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body" id="order-details-content">
                <div class="loading">
                    <span class="spinner is-active"></span>
                    <p>Loading order details...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Metadata Modal -->
    <div id="update-metadata-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Update Existing Orders Metadata</h3>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <p>This will scan existing orders and add ShopCommerce metadata to orders that contain external provider products but don't have the metadata yet.</p>

                <div class="update-metadata-options">
                    <h4>Options:</h4>
                    <label>
                        <input type="number" id="update-limit" value="100" min="10" max="500">
                        Orders to process (limit)
                    </label>
                    <p class="description">Set a reasonable limit to avoid timeouts. Start with 100 and increase if needed.</p>
                </div>

                <div class="update-metadata-actions">
                    <button type="button" class="button button-secondary" id="dry-run-btn">
                        <span class="dashicons dashicons-visibility"></span> Test Run (Dry Run)
                    </button>
                    <button type="button" class="button button-primary" id="run-update-btn">
                        <span class="dashicons dashicons-database-add"></span> Update Orders
                    </button>
                </div>

                <div id="update-metadata-results" style="display: none;">
                    <h4>Results:</h4>
                    <div id="update-results-content"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.shopcommerce-admin .orders-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.shopcommerce-admin .overview-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    text-align: center;
}

.shopcommerce-admin .overview-card h3 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #666;
}

.shopcommerce-admin .big-number {
    font-size: 32px;
    font-weight: bold;
    color: #0073aa;
    margin: 0;
}

.shopcommerce-admin .section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.shopcommerce-admin .header-actions {
    display: flex;
    gap: 10px;
}

.shopcommerce-admin .filters-container {
    background: #f8f9fa;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    align-items: end;
}

.shopcommerce-admin .filter-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
}

.shopcommerce-admin .filter-group input,
.shopcommerce-admin .filter-group select {
    width: 100%;
}

.shopcommerce-admin .orders-table-container {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    overflow: hidden;
}

.shopcommerce-admin .orders-table {
    margin: 0;
}

.shopcommerce-admin .orders-table th {
    background: #f8f9fa;
    border-bottom: 1px solid #ccd0d4;
}

.shopcommerce-admin .orders-table .order-number {
    width: 100px;
}

.shopcommerce-admin .orders-table .customer {
    width: 200px;
}

.shopcommerce-admin .orders-table .date {
    width: 150px;
}

.shopcommerce-admin .orders-table .status {
    width: 100px;
}

.shopcommerce-admin .orders-table .brands {
    width: 150px;
}

.shopcommerce-admin .orders-table .products {
    width: 80px;
}

.shopcommerce-admin .orders-table .total {
    width: 100px;
    text-align: right;
}

.shopcommerce-admin .orders-table .actions {
    width: 100px;
    text-align: center;
}

.shopcommerce-admin .pagination-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 20px;
    padding: 15px;
    background: #f8f9fa;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
}

.shopcommerce-admin .pagination-links {
    display: flex;
    gap: 5px;
}

.shopcommerce-admin .pagination-links button {
    padding: 5px 10px;
    min-width: auto;
}

.shopcommerce-admin .modal {
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.shopcommerce-admin .modal-content {
    background-color: #fff;
    margin: 5% auto;
    padding: 0;
    border-radius: 4px;
    width: 80%;
    max-width: 800px;
    max-height: 80vh;
    overflow-y: auto;
}

.shopcommerce-admin .modal-header {
    padding: 20px;
    border-bottom: 1px solid #ccd0d4;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.shopcommerce-admin .modal-header h3 {
    margin: 0;
}

.shopcommerce-admin .close-modal {
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    color: #666;
}

.shopcommerce-admin .close-modal:hover {
    color: #000;
}

.shopcommerce-admin .modal-body {
    padding: 20px;
}

.shopcommerce-admin .loading-orders,
.shopcommerce-admin .loading {
    text-align: center;
    padding: 40px;
}

.shopcommerce-admin .spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid #f3f3f3;
    border-top: 3px solid #0073aa;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

.shopcommerce-admin .spinner.is-active {
    display: inline-block;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.shopcommerce-admin .status-badge {
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
    text-transform: uppercase;
}

.shopcommerce-admin .status-completed {
    background: #d4edda;
    color: #155724;
}

.shopcommerce-admin .status-processing {
    background: #cce5ff;
    color: #004085;
}

.shopcommerce-admin .status-pending {
    background: #fff3cd;
    color: #856404;
}

.shopcommerce-admin .status-on-hold {
    background: #f8d7da;
    color: #721c24;
}

.shopcommerce-admin .update-metadata-options {
    margin: 20px 0;
}

.shopcommerce-admin .update-metadata-options label {
    display: block;
    margin-bottom: 10px;
}

.shopcommerce-admin .update-metadata-options input {
    margin-right: 10px;
    width: 100px;
}

.shopcommerce-admin .description {
    font-size: 13px;
    color: #666;
    margin-top: 5px;
}

.shopcommerce-admin .update-metadata-actions {
    display: flex;
    gap: 10px;
    margin: 20px 0;
}

.shopcommerce-admin .order-details-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.shopcommerce-admin .order-section {
    margin-bottom: 20px;
}

.shopcommerce-admin .order-section h4 {
    margin: 0 0 10px 0;
    border-bottom: 1px solid #eee;
    padding-bottom: 5px;
}

.shopcommerce-admin .order-meta-item {
    display: flex;
    justify-content: space-between;
    padding: 5px 0;
    border-bottom: 1px solid #f0f0f0;
}

.shopcommerce-admin .order-meta-label {
    font-weight: 500;
    color: #666;
}

.shopcommerce-admin .order-meta-value {
    text-align: right;
}

.shopcommerce-admin .external-products-list {
    margin-top: 10px;
}

.shopcommerce-admin .external-product-item {
    background: #f8f9fa;
    border: 1px solid #eee;
    border-radius: 3px;
    padding: 10px;
    margin-bottom: 10px;
}

.shopcommerce-admin .external-product-item h5 {
    margin: 0 0 5px 0;
    color: #0073aa;
}

.shopcommerce-admin .external-product-meta {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    font-size: 13px;
    color: #666;
}

.shopcommerce-admin .order-items-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

.shopcommerce-admin .order-items-table th,
.shopcommerce-admin .order-items-table td {
    padding: 8px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.shopcommerce-admin .order-items-table th {
    background: #f8f9fa;
    font-weight: 500;
}

.shopcommerce-admin .external-provider-badge {
    background: #0073aa;
    color: white;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 500;
}

.text-center {
    text-align: center;
}

.text-muted {
    color: #666;
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    let currentPage = 1;
    let currentFilters = {
        search: '',
        status: '',
        brand: '',
        per_page: 20
    };

    let currentIncompletePage = 1;
    let currentIncompleteFilters = {
        search: '',
        status: '',
        brand: '',
        per_page: 20
    };

    // Load orders on page load
    loadOrders();
    loadIncompleteOrders();

    // Load incomplete orders function
    function loadIncompleteOrders(page = 1) {
        currentIncompletePage = page;

        $('#incomplete-orders-tbody').html(`
            <tr>
                <td colspan="8" class="text-center">
                    <div class="loading-orders">
                        <span class="spinner is-active"></span>
                        <p>Loading incomplete orders...</p>
                    </div>
                </td>
            </tr>
        `);

        $.ajax({
            url: shopcommerce_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'shopcommerce_get_incomplete_orders',
                nonce: shopcommerce_admin.nonce,
                page: currentIncompletePage,
                search: currentIncompleteFilters.search,
                status: currentIncompleteFilters.status,
                brand: currentIncompleteFilters.brand,
                per_page: currentIncompleteFilters.per_page
            },
            success: function(response) {
                if (response.success) {
                    displayIncompleteOrders(response.data.orders);
                    displayIncompletePagination(response.data);
                } else {
                    displayIncompleteError('Failed to load incomplete orders');
                }
            },
            error: function() {
                displayIncompleteError('Network error while loading incomplete orders');
            }
        });
    }

    // Display incomplete orders in table
    function displayIncompleteOrders(orders) {
        let tbody = $('#incomplete-orders-tbody');

        if (orders.length === 0) {
            tbody.html(`
                <tr>
                    <td colspan="8" class="text-center">
                        <p>No incomplete orders with external products found.</p>
                    </td>
                </tr>
            `);
            return;
        }

        let html = '';
        orders.forEach(function(order) {
            let statusClass = 'status-' + order.status;
            let brands = order.brands || 'N/A';

            html += `
                <tr>
                    <td class="order-number">
                        <a href="#" class="view-incomplete-order-details" data-order-id="${order.id}">
                            #${order.order_number}
                        </a>
                    </td>
                    <td class="customer">${order.customer}</td>
                    <td class="date">${order.formatted_date}</td>
                    <td class="status">
                        <span class="status-badge ${statusClass}">${order.status_label}</span>
                    </td>
                    <td class="brands">${brands}</td>
                    <td class="products">${order.product_count || 0}</td>
                    <td class="total">${order.formatted_total}</td>
                    <td class="actions">
                        <button type="button" class="button button-small view-incomplete-order-details" data-order-id="${order.id}">
                            View
                        </button>
                    </td>
                </tr>
            `;
        });

        tbody.html(html);

        // Attach click handlers
        $('.view-incomplete-order-details').on('click', function(e) {
            e.preventDefault();
            let orderId = $(this).data('order-id');
            viewOrderDetails(orderId);
        });
    }

    // Display incomplete orders pagination
    function displayIncompletePagination(data) {
        $('#incomplete-pagination-info').text(data.showing_items);

        let links = $('#incomplete-pagination-links');
        links.html('');

        // Previous button
        if (data.current_page > 1) {
            links.append(`<button type="button" class="button" data-page="${data.current_page - 1}">‹ Previous</button>`);
        }

        // Page numbers
        for (let i = 1; i <= data.total_pages; i++) {
            let active = i === data.current_page ? 'button-primary' : '';
            links.append(`<button type="button" class="button ${active}" data-page="${i}">${i}</button>`);
        }

        // Next button
        if (data.current_page < data.total_pages) {
            links.append(`<button type="button" class="button" data-page="${data.current_page + 1}">Next ›</button>`);
        }

        // Attach click handlers
        $('#incomplete-pagination-links button').on('click', function() {
            let page = $(this).data('page');
            loadIncompleteOrders(page);
        });
    }

    // Display incomplete orders error
    function displayIncompleteError(message) {
        $('#incomplete-orders-tbody').html(`
            <tr>
                <td colspan="8" class="text-center">
                    <p class="text-muted">${message}</p>
                </td>
            </tr>
        `);
    }

    // Load orders function
    function loadOrders(page = 1) {
        currentPage = page;

        $('#orders-tbody').html(`
            <tr>
                <td colspan="8" class="text-center">
                    <div class="loading-orders">
                        <span class="spinner is-active"></span>
                        <p>Loading orders...</p>
                    </div>
                </td>
            </tr>
        `);

        $.ajax({
            url: shopcommerce_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'shopcommerce_get_orders',
                nonce: shopcommerce_admin.nonce,
                page: currentPage,
                search: currentFilters.search,
                status: currentFilters.status,
                brand: currentFilters.brand,
                per_page: currentFilters.per_page
            },
            success: function(response) {
                if (response.success) {
                    displayOrders(response.data.orders);
                    displayPagination(response.data);
                    updateOverview(response.data.orders);
                } else {
                    displayError('Failed to load orders');
                }
            },
            error: function() {
                displayError('Network error while loading orders');
            }
        });
    }

    // Display orders in table
    function displayOrders(orders) {
        let tbody = $('#orders-tbody');

        if (orders.length === 0) {
            tbody.html(`
                <tr>
                    <td colspan="8" class="text-center">
                        <p>No orders found matching your criteria.</p>
                    </td>
                </tr>
            `);
            return;
        }

        let html = '';
        orders.forEach(function(order) {
            let statusClass = 'status-' + order.status;
            let brands = order.metadata.brands || 'N/A';

            html += `
                <tr>
                    <td class="order-number">
                        <a href="#" class="view-order-details" data-order-id="${order.id}">
                            #${order.order_number}
                        </a>
                    </td>
                    <td class="customer">${order.customer}</td>
                    <td class="date">${order.formatted_date}</td>
                    <td class="status">
                        <span class="status-badge ${statusClass}">${order.status_label}</span>
                    </td>
                    <td class="brands">${brands}</td>
                    <td class="products">${order.metadata.product_count || 0}</td>
                    <td class="total">${order.formatted_total}</td>
                    <td class="actions">
                        <button type="button" class="button button-small view-order-details" data-order-id="${order.id}">
                            View
                        </button>
                    </td>
                </tr>
            `;
        });

        tbody.html(html);

        // Attach click handlers
        $('.view-order-details').on('click', function(e) {
            e.preventDefault();
            let orderId = $(this).data('order-id');
            viewOrderDetails(orderId);
        });
    }

    // Display pagination
    function displayPagination(data) {
        $('#pagination-info').text(data.showing_items);

        let links = $('#pagination-links');
        links.html('');

        // Previous button
        if (data.current_page > 1) {
            links.append(`<button type="button" class="button" data-page="${data.current_page - 1}">‹ Previous</button>`);
        }

        // Page numbers
        for (let i = 1; i <= data.total_pages; i++) {
            let active = i === data.current_page ? 'button-primary' : '';
            links.append(`<button type="button" class="button ${active}" data-page="${i}">${i}</button>`);
        }

        // Next button
        if (data.current_page < data.total_pages) {
            links.append(`<button type="button" class="button" data-page="${data.current_page + 1}">Next ›</button>`);
        }

        // Attach click handlers
        $('.pagination-links button').on('click', function() {
            let page = $(this).data('page');
            loadOrders(page);
        });
    }

    // Update overview cards
    function updateOverview(orders) {
        let totalCount = orders.length;
        let completedCount = orders.filter(o => o.status === 'completed').length;
        let processingCount = orders.filter(o => o.status === 'processing').length;
        let totalValue = orders.reduce((sum, o) => sum + parseFloat(o.total), 0);

        $('#total-orders-count').text(totalCount);
        $('#completed-orders-count').text(completedCount);
        $('#processing-orders-count').text(processingCount);
        $('#total-value').text('$' + totalValue.toFixed(2));
    }

    // View order details
    function viewOrderDetails(orderId) {
        $('#order-details-modal').show();
        $('#order-details-content').html(`
            <div class="loading">
                <span class="spinner is-active"></span>
                <p>Loading order details...</p>
            </div>
        `);

        $.ajax({
            url: shopcommerce_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'shopcommerce_get_order_details',
                nonce: shopcommerce_admin.nonce,
                order_id: orderId
            },
            success: function(response) {
                if (response.success) {
                    displayOrderDetails(response.data);
                } else {
                    $('#order-details-content').html('<p>Error loading order details.</p>');
                }
            },
            error: function() {
                $('#order-details-content').html('<p>Network error loading order details.</p>');
            }
        });
    }

    // Display order details
    function displayOrderDetails(data) {
        let html = `
            <div class="order-details-grid">
                <div class="order-section">
                    <h4>Order Information</h4>
                    <div class="order-meta-item">
                        <span class="order-meta-label">Order #:</span>
                        <span class="order-meta-value">#${data.order.order_number}</span>
                    </div>
                    <div class="order-meta-item">
                        <span class="order-meta-label">Status:</span>
                        <span class="order-meta-value">
                            <span class="status-badge status-${data.order.status}">${data.order.status}</span>
                        </span>
                    </div>
                    <div class="order-meta-item">
                        <span class="order-meta-label">Date:</span>
                        <span class="order-meta-value">${data.order.formatted_date}</span>
                    </div>
                    <div class="order-meta-item">
                        <span class="order-meta-label">Total:</span>
                        <span class="order-meta-value">${data.order.formatted_total}</span>
                    </div>
                </div>

                <div class="order-section">
                    <h4>Customer Information</h4>
                    <div class="order-meta-item">
                        <span class="order-meta-label">Name:</span>
                        <span class="order-meta-value">${data.customer.name || 'N/A'}</span>
                    </div>
                    <div class="order-meta-item">
                        <span class="order-meta-label">Email:</span>
                        <span class="order-meta-value">${data.customer.email || 'N/A'}</span>
                    </div>
                    <div class="order-meta-item">
                        <span class="order-meta-label">Phone:</span>
                        <span class="order-meta-value">${data.customer.phone || 'N/A'}</span>
                    </div>
                    <div class="order-meta-item">
                        <span class="order-meta-label">Company:</span>
                        <span class="order-meta-value">${data.customer.company || 'N/A'}</span>
                    </div>
                </div>
            </div>

            <div class="order-section">
                <h4>ShopCommerce Metadata</h4>
                <div class="order-meta-item">
                    <span class="order-meta-label">Has ShopCommerce Products:</span>
                    <span class="order-meta-value">${data.metadata.has_shopcommerce_products ? 'Yes' : 'No'}</span>
                </div>
                <div class="order-meta-item">
                    <span class="order-meta-label">Product Count:</span>
                    <span class="order-meta-value">${data.metadata.product_count || 0}</span>
                </div>
                <div class="order-meta-item">
                    <span class="order-meta-label">Brands:</span>
                    <span class="order-meta-value">${data.metadata.brands || 'N/A'}</span>
                </div>
                <div class="order-meta-item">
                    <span class="order-meta-label">Total Quantity:</span>
                    <span class="order-meta-value">${data.metadata.total_quantity || 0}</span>
                </div>
                <div class="order-meta-item">
                    <span class="order-meta-label">ShopCommerce Value:</span>
                    <span class="order-meta-value">$${data.metadata.total_value ? data.metadata.total_value.toFixed(2) : '0.00'}</span>
                </div>
                <div class="order-meta-item">
                    <span class="order-meta-label">Created:</span>
                    <span class="order-meta-value">${data.metadata.created_timestamp || 'N/A'}</span>
                </div>
                <div class="order-meta-item">
                    <span class="order-meta-label">Processed:</span>
                    <span class="order-meta-value">${data.metadata.processed_timestamp || 'N/A'}</span>
                </div>
            </div>
        `;

        // Add external products section if any
        if (data.external_products && data.external_products.length > 0) {
            html += `
                <div class="order-section">
                    <h4>External Provider Products (${data.external_products.length})</h4>
                    <div class="external-products-list">
            `;

            data.external_products.forEach(function(product) {
                html += `
                    <div class="external-product-item">
                        <h5>${product.product_name}</h5>
                        <div class="external-product-meta">
                            <div><strong>SKU:</strong> ${product.product_sku || 'N/A'}</div>
                            <div><strong>Brand:</strong> ${product.external_provider_brand || 'N/A'}</div>
                            <div><strong>Quantity:</strong> ${product.quantity}</div>
                            <div><strong>Total:</strong> $${parseFloat(product.line_total).toFixed(2)}</div>
                        </div>
                    </div>
                `;
            });

            html += '</div></div>';
        }

        // Add all order items
        html += `
            <div class="order-section">
                <h4>All Order Items</h4>
                <table class="order-items-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Quantity</th>
                            <th>Total</th>
                            <th>Provider</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        data.items.forEach(function(item) {
            html += `
                <tr>
                    <td>${item.name}</td>
                    <td>${item.sku || 'N/A'}</td>
                    <td>${item.quantity}</td>
                    <td>${item.formatted_total}</td>
                    <td>
                        ${item.external_provider ?
                            '<span class="external-provider-badge">ShopCommerce</span>' :
                            '<span class="text-muted">Local</span>'}
                    </td>
                </tr>
            `;
        });

        html += `
                    </tbody>
                </table>
            </div>
        `;

        $('#order-details-content').html(html);
    }

    // Display error
    function displayError(message) {
        $('#orders-tbody').html(`
            <tr>
                <td colspan="8" class="text-center">
                    <p class="text-muted">${message}</p>
                </td>
            </tr>
        `);
    }

    // Event handlers
    $('#refresh-incomplete-orders-btn').on('click', function() {
        loadIncompleteOrders(currentIncompletePage);
    });

    $('#refresh-orders-btn').on('click', function() {
        loadOrders(currentPage);
    });

    $('#apply-filters-btn').on('click', function() {
        currentFilters.search = $('#search-orders').val();
        currentFilters.status = $('#filter-status').val();
        currentFilters.brand = $('#filter-brand').val();
        currentFilters.per_page = parseInt($('#per-page').val());
        loadOrders(1);
    });

    $('#clear-filters-btn').on('click', function() {
        $('#search-orders').val('');
        $('#filter-status').val('');
        $('#filter-brand').val('');
        $('#per-page').val('20');
        currentFilters = {
            search: '',
            status: '',
            brand: '',
            per_page: 20
        };
        loadOrders(1);
    });

    // Incomplete orders filter handlers
    $('#apply-incomplete-filters-btn').on('click', function() {
        currentIncompleteFilters.search = $('#search-incomplete-orders').val();
        currentIncompleteFilters.status = $('#filter-incomplete-status').val();
        currentIncompleteFilters.brand = $('#filter-incomplete-brand').val();
        currentIncompleteFilters.per_page = parseInt($('#incomplete-per-page').val());
        loadIncompleteOrders(1);
    });

    $('#clear-incomplete-filters-btn').on('click', function() {
        $('#search-incomplete-orders').val('');
        $('#filter-incomplete-status').val('');
        $('#filter-incomplete-brand').val('');
        $('#incomplete-per-page').val('20');
        currentIncompleteFilters = {
            search: '',
            status: '',
            brand: '',
            per_page: 20
        };
        loadIncompleteOrders(1);
    });

    // Enter key for search
    $('#search-orders').on('keypress', function(e) {
        if (e.which === 13) {
            $('#apply-filters-btn').click();
        }
    });

    // Enter key for incomplete orders search
    $('#search-incomplete-orders').on('keypress', function(e) {
        if (e.which === 13) {
            $('#apply-incomplete-filters-btn').click();
        }
    });

    // Update metadata button
    $('#update-metadata-btn').on('click', function() {
        $('#update-metadata-modal').show();
        $('#update-metadata-results').hide();
        $('#update-results-content').html('');
    });

    // Dry run button
    $('#dry-run-btn').on('click', function() {
        let limit = parseInt($('#update-limit').val());

        $(this).prop('disabled', true).html('<span class="spinner is-active"></span> Testing...');

        $.ajax({
            url: shopcommerce_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'shopcommerce_update_existing_orders_metadata',
                nonce: shopcommerce_admin.nonce,
                limit: limit,
                dry_run: true
            },
            success: function(response) {
                if (response.success) {
                    $('#update-metadata-results').show();
                    $('#update-results-content').html(`
                        <div class="notice notice-info">
                            <p>${response.data.message}</p>
                        </div>
                    `);
                } else {
                    $('#update-metadata-results').show();
                    $('#update-results-content').html(`
                        <div class="notice notice-error">
                            <p>Error: ${response.data.message}</p>
                        </div>
                    `);
                }
            },
            error: function() {
                $('#update-metadata-results').show();
                $('#update-results-content').html(`
                    <div class="notice notice-error">
                        <p>Network error during dry run.</p>
                    </div>
                `);
            },
            complete: function() {
                $('#dry-run-btn').prop('disabled', false).html('<span class="dashicons dashicons-visibility"></span> Test Run (Dry Run)');
            }
        });
    });

    // Run update button
    $('#run-update-btn').on('click', function() {
        if (!confirm('Are you sure you want to update existing orders? This action cannot be undone.')) {
            return;
        }

        let limit = parseInt($('#update-limit').val());

        $(this).prop('disabled', true).html('<span class="spinner is-active"></span> Updating...');
        $('#dry-run-btn').prop('disabled', true);

        $.ajax({
            url: shopcommerce_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'shopcommerce_update_existing_orders_metadata',
                nonce: shopcommerce_admin.nonce,
                limit: limit,
                dry_run: false
            },
            success: function(response) {
                if (response.success) {
                    $('#update-metadata-results').show();
                    let results = response.data.results;
                    let html = `
                        <div class="notice notice-success">
                            <p>${response.data.message}</p>
                        </div>
                        <ul>
                            <li>Updated: ${results.updated_orders} orders</li>
                            <li>Skipped: ${results.skipped_orders} orders</li>
                            <li>Errors: ${results.errors.length}</li>
                        </ul>
                    `;

                    if (results.errors.length > 0) {
                        html += '<div class="notice notice-error"><p>Errors:</p><ul>';
                        results.errors.forEach(function(error) {
                            html += `<li>Order ${error.order_id}: ${error.error}</li>`;
                        });
                        html += '</ul></div>';
                    }

                    $('#update-results-content').html(html);

                    // Refresh orders list
                    loadOrders(currentPage);
                } else {
                    $('#update-metadata-results').show();
                    $('#update-results-content').html(`
                        <div class="notice notice-error">
                            <p>Error: ${response.data.message}</p>
                        </div>
                    `);
                }
            },
            error: function() {
                $('#update-metadata-results').show();
                $('#update-results-content').html(`
                    <div class="notice notice-error">
                        <p>Network error during update.</p>
                    </div>
                `);
            },
            complete: function() {
                $('#run-update-btn').prop('disabled', false).html('<span class="dashicons dashicons-database-add"></span> Update Orders');
                $('#dry-run-btn').prop('disabled', false);
            }
        });
    });

    // Modal close handlers
    $('.close-modal').on('click', function() {
        $(this).closest('.modal').hide();
    });

    $(window).on('click', function(e) {
        if ($(e.target).hasClass('modal')) {
            $(e.target).hide();
        }
    });
});
</script>