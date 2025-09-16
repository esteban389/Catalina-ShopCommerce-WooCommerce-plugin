<?php

/**
 * Order Functions for ShopCommerce Sync Plugin
 *
 * Handles order-related functionality including external provider product detection and logging.
 *
 * @package ShopCommerce_Sync
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get external provider products from an order
 *
 * @param int|WC_Order $order Order ID or order object
 * @return array Array of external provider products with their details
 */
function shopcommerce_get_external_provider_products_from_order($order) {
    // Get order object if ID was passed
    if (is_numeric($order)) {
        $order = wc_get_order($order);
    }

    if (!$order) {
        return [];
    }

    $external_provider_products = [];
    $items = $order->get_items();

    foreach ($items as $item_id => $item) {
        $product_id = $item->get_product_id();
        $product = wc_get_product($product_id);

        if (!$product) {
            continue;
        }

        // Check if product is from external provider
        $external_provider = get_post_meta($product_id, '_external_provider', true);

        if ($external_provider === 'shopcommerce') {
            $external_provider_brand = get_post_meta($product_id, '_external_provider_brand', true);
            $external_provider_sync_date = get_post_meta($product_id, '_external_provider_sync_date', true);

            $product_data = [
                'item_id' => $item_id,
                'product_id' => $product_id,
                'product_name' => $product->get_name(),
                'product_sku' => $product->get_sku(),
                'quantity' => $item->get_quantity(),
                'line_total' => $item->get_total(),
                'line_tax' => $item->get_total_tax(),
                'external_provider' => $external_provider,
                'external_provider_brand' => $external_provider_brand,
                'external_provider_sync_date' => $external_provider_sync_date,
            ];

            $external_provider_products[] = $product_data;
        }
    }

    return $external_provider_products;
}

/**
 * Check if order contains external provider products
 *
 * @param int|WC_Order $order Order ID or order object
 * @return bool True if order contains external provider products
 */
function shopcommerce_order_has_external_provider_products($order) {
    $external_products = shopcommerce_get_external_provider_products_from_order($order);
    return !empty($external_products);
}

/**
 * Get order external provider statistics
 *
 * @param int|WC_Order $order Order ID or order object
 * @return array Statistics about external provider products in the order
 */
function shopcommerce_get_order_external_provider_stats($order) {
    $external_products = shopcommerce_get_external_provider_products_from_order($order);

    if (empty($external_products)) {
        return [
            'has_external_products' => false,
            'total_products' => 0,
            'total_quantity' => 0,
            'total_value' => 0,
            'brands' => [],
            'product_summary' => [],
        ];
    }

    $brands = array_unique(array_column($external_products, 'external_provider_brand'));
    $product_summary = [];

    foreach ($external_products as $product) {
        $brand = $product['external_provider_brand'];
        if (!isset($product_summary[$brand])) {
            $product_summary[$brand] = [
                'count' => 0,
                'quantity' => 0,
                'value' => 0,
            ];
        }
        $product_summary[$brand]['count']++;
        $product_summary[$brand]['quantity'] += $product['quantity'];
        $product_summary[$brand]['value'] += $product['line_total'];
    }

    return [
        'has_external_products' => true,
        'total_products' => count($external_products),
        'total_quantity' => array_sum(array_column($external_products, 'quantity')),
        'total_value' => array_sum(array_column($external_products, 'line_total')),
        'brands' => $brands,
        'product_summary' => $product_summary,
    ];
}

/**
 * Log order completion with external provider products
 *
 * @param int|WC_Order $order Order ID or order object
 * @param ShopCommerce_Logger|null $logger Logger instance (optional)
 * @return bool True if logging was successful
 */
function shopcommerce_log_order_external_products($order, $logger = null) {
    // Get order object if ID was passed
    if (is_numeric($order)) {
        $order = wc_get_order($order);
    }

    if (!$order) {
        return false;
    }

    // Get external provider products
    $external_products = shopcommerce_get_external_provider_products_from_order($order);

    if (empty($external_products)) {
        return false;
    }

    // Get logger if not provided
    if (!$logger) {
        $logger = isset($GLOBALS['shopcommerce_logger']) ? $GLOBALS['shopcommerce_logger'] : null;
    }

    // Prepare log message
    $log_message = sprintf(
        'Order %d completed with %d external provider product(s)',
        $order->get_id(),
        count($external_products)
    );

    // Prepare log context
    $log_context = [
        'order_id' => $order->get_id(),
        'order_number' => $order->get_order_number(),
        'order_total' => $order->get_total(),
        'order_status' => $order->get_status(),
        'customer_id' => $order->get_customer_id(),
        'external_provider_products' => $external_products,
    ];

    // Log to logger if available
    if ($logger) {
        $logger->info($log_message, $log_context);

        // Additional activity logging
        $stats = shopcommerce_get_order_external_provider_stats($order);
        $logger->log_activity(
            'order_completed_with_external_products',
            sprintf(
                'Order %s completed with %d external provider products from %d brand(s)',
                $order->get_order_number(),
                $stats['total_products'],
                count($stats['brands'])
            ),
            [
                'order_id' => $order->get_id(),
                'product_count' => $stats['total_products'],
                'brands' => $stats['brands'],
                'total_value' => $stats['total_value'],
                'total_quantity' => $stats['total_quantity'],
            ]
        );

        return true;
    }

    // Fallback to WordPress error log
    error_log('[ShopCommerce] ' . $log_message . ' | Context: ' . json_encode($log_context));
    return true;
}

/**
 * Handle order completion event
 *
 * This function is hooked to woocommerce_order_status_completed and woocommerce_order_status_processing
 *
 * @param int $order_id The order ID
 */
function shopcommerce_handle_order_completion($order_id) {
    // Check if WooCommerce is available
    if (!class_exists('WC_Order')) {
        return;
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    // Check if order has external provider products
    if (shopcommerce_order_has_external_provider_products($order)) {
        // Add metadata to identify order with external provider products
        $order->add_meta_data('_has_shopcommerce_products', 'yes', true);
        $order->add_meta_data('_shopcommerce_order_processed', current_time('mysql'), true);

        // Get statistics and add brand information
        $stats = shopcommerce_get_order_external_provider_stats($order);
        $order->add_meta_data('_shopcommerce_product_count', $stats['total_products'], true);
        $order->add_meta_data('_shopcommerce_brands', implode(', ', $stats['brands']), true);
        $order->add_meta_data('_shopcommerce_total_value', $stats['total_value'], true);
        $order->add_meta_data('_shopcommerce_total_quantity', $stats['total_quantity'], true);

        // Save the metadata
        $order->save();
    }

    // Log external provider products
    shopcommerce_log_order_external_products($order);
}

/**
 * Handle order creation event
 *
 * This function is hooked to woocommerce_new_order
 *
 * @param int $order_id The new order ID
 */
function shopcommerce_handle_order_creation($order_id) {
    // Check if WooCommerce is available
    if (!class_exists('WC_Order')) {
        return;
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    // Check if order has external provider products
    if (shopcommerce_order_has_external_provider_products($order)) {
        $stats = shopcommerce_get_order_external_provider_stats($order);

        // Add metadata to identify order with external provider products
        $order->add_meta_data('_has_shopcommerce_products', 'yes', true);
        $order->add_meta_data('_shopcommerce_order_created', current_time('mysql'), true);
        $order->add_meta_data('_shopcommerce_product_count', $stats['total_products'], true);
        $order->add_meta_data('_shopcommerce_brands', implode(', ', $stats['brands']), true);
        $order->add_meta_data('_shopcommerce_total_value', $stats['total_value'], true);
        $order->add_meta_data('_shopcommerce_total_quantity', $stats['total_quantity'], true);

        // Save the metadata
        $order->save();

        $logger = isset($GLOBALS['shopcommerce_logger']) ? $GLOBALS['shopcommerce_logger'] : null;

        $log_message = sprintf(
            'Order %d created with %d external provider product(s)',
            $order_id,
            $stats['total_products']
        );

        $log_context = [
            'order_id' => $order_id,
            'order_number' => $order->get_order_number(),
            'order_status' => $order->get_status(),
            'customer_id' => $order->get_customer_id(),
            'external_stats' => $stats,
        ];

        if ($logger) {
            $logger->info($log_message, $log_context);

            $logger->log_activity(
                'order_created_with_external_products',
                sprintf(
                    'Order %s created with %d external provider products',
                    $order->get_order_number(),
                    $stats['total_products']
                ),
                [
                    'order_id' => $order_id,
                    'product_count' => $stats['total_products'],
                    'brands' => $stats['brands'],
                    'total_value' => $stats['total_value'],
                    'total_quantity' => $stats['total_quantity'],
                ]
            );
        } else {
            error_log('[ShopCommerce] ' . $log_message . ' | Context: ' . json_encode($log_context));
        }
    }
}

/**
 * Get orders with external provider products
 *
 * @param array $args Query arguments for WP_Query
 * @return array Array of order objects with external provider products
 */
function shopcommerce_get_orders_with_external_products($args = []) {
    $default_args = [
        'status' => ['completed', 'processing'],
        'limit' => -1,
        'return' => 'objects',
    ];

    $args = wp_parse_args($args, $default_args);

    $orders = wc_get_orders($args);
    $orders_with_external = [];

    foreach ($orders as $order) {
        if (shopcommerce_order_has_external_provider_products($order)) {
            $orders_with_external[] = $order;
        }
    }

    return $orders_with_external;
}

/**
 * Check if order has been marked as containing ShopCommerce products
 *
 * @param int|WC_Order $order Order ID or order object
 * @return bool True if order has ShopCommerce products metadata
 */
function shopcommerce_order_has_shopcommerce_metadata($order) {
    if (is_numeric($order)) {
        $order = wc_get_order($order);
    }

    if (!$order) {
        return false;
    }

    return $order->get_meta('_has_shopcommerce_products') === 'yes';
}

/**
 * Get ShopCommerce metadata from order
 *
 * @param int|WC_Order $order Order ID or order object
 * @return array ShopCommerce metadata
 */
function shopcommerce_get_order_shopcommerce_metadata($order) {
    if (is_numeric($order)) {
        $order = wc_get_order($order);
    }

    if (!$order) {
        return [];
    }

    $metadata = [
        'has_shopcommerce_products' => $order->get_meta('_has_shopcommerce_products') === 'yes',
        'created_timestamp' => $order->get_meta('_shopcommerce_order_created'),
        'processed_timestamp' => $order->get_meta('_shopcommerce_order_processed'),
        'product_count' => intval($order->get_meta('_shopcommerce_product_count', 0)),
        'brands' => $order->get_meta('_shopcommerce_brands', ''),
        'total_value' => floatval($order->get_meta('_shopcommerce_total_value', 0)),
        'total_quantity' => intval($order->get_meta('_shopcommerce_total_quantity', 0)),
    ];

    // Parse brands string into array
    if (!empty($metadata['brands'])) {
        $metadata['brands_array'] = array_map('trim', explode(',', $metadata['brands']));
    } else {
        $metadata['brands_array'] = [];
    }

    return $metadata;
}

/**
 * Get orders with ShopCommerce metadata
 *
 * @param array $args Query arguments for WP_Query
 * @return array Array of order objects with ShopCommerce metadata
 */
function shopcommerce_get_orders_with_shopcommerce_metadata($args = []) {
    $default_args = [
        'status' => ['completed', 'processing', 'pending', 'on-hold'],
        'limit' => -1,
        'return' => 'objects',
        'meta_key' => '_has_shopcommerce_products',
        'meta_value' => 'yes',
    ];

    $args = wp_parse_args($args, $default_args);

    // Remove meta query from args and add it properly
    $meta_query = [
        [
            'key' => '_has_shopcommerce_products',
            'value' => 'yes',
            'compare' => '=',
        ]
    ];

    $args['meta_query'] = $meta_query;

    return wc_get_orders($args);
}

/**
 * Update existing orders to add ShopCommerce metadata
 * This function can be used to backfill metadata for existing orders
 *
 * @param array $args Order query arguments
 * @return array Results of the update operation
 */
function shopcommerce_update_existing_orders_metadata($args = []) {
    $default_args = [
        'status' => ['completed', 'processing'],
        'limit' => 100,
        'return' => 'objects',
    ];

    $args = wp_parse_args($args, $default_args);

    $orders = wc_get_orders($args);
    $results = [
        'total_orders' => count($orders),
        'updated_orders' => 0,
        'skipped_orders' => 0,
        'errors' => [],
    ];

    foreach ($orders as $order) {
        try {
            // Skip if already has metadata
            if (shopcommerce_order_has_shopcommerce_metadata($order)) {
                $results['skipped_orders']++;
                continue;
            }

            // Check if order has external provider products
            if (shopcommerce_order_has_external_provider_products($order)) {
                $stats = shopcommerce_get_order_external_provider_stats($order);

                // Add metadata
                $order->add_meta_data('_has_shopcommerce_products', 'yes', true);
                $order->add_meta_data('_shopcommerce_order_processed', current_time('mysql'), true);
                $order->add_meta_data('_shopcommerce_product_count', $stats['total_products'], true);
                $order->add_meta_data('_shopcommerce_brands', implode(', ', $stats['brands']), true);
                $order->add_meta_data('_shopcommerce_total_value', $stats['total_value'], true);
                $order->add_meta_data('_shopcommerce_total_quantity', $stats['total_quantity'], true);

                $order->save();
                $results['updated_orders']++;
            } else {
                $results['skipped_orders']++;
            }
        } catch (Exception $e) {
            $results['errors'][] = [
                'order_id' => $order->get_id(),
                'error' => $e->getMessage()
            ];
        }
    }

    return $results;
}

/**
 * Test function to manually trigger order processing
 * This is useful for testing the implementation
 *
 * @param int $order_id Order ID to test
 * @return array Test results
 */
function shopcommerce_test_order_processing($order_id) {
    $results = [
        'success' => false,
        'order_id' => $order_id,
        'has_external_products' => false,
        'external_products' => [],
        'stats' => [],
        'metadata' => [],
        'logs' => [],
    ];

    $order = wc_get_order($order_id);
    if (!$order) {
        $results['error'] = 'Order not found';
        return $results;
    }

    // Test external product detection
    $external_products = shopcommerce_get_external_provider_products_from_order($order);
    $results['has_external_products'] = !empty($external_products);
    $results['external_products'] = $external_products;

    if ($results['has_external_products']) {
        $stats = shopcommerce_get_order_external_provider_stats($order);
        $results['stats'] = $stats;
    }

    // Test metadata functions
    $results['metadata']['has_metadata'] = shopcommerce_order_has_shopcommerce_metadata($order);
    $results['metadata']['shopcommerce_data'] = shopcommerce_get_order_shopcommerce_metadata($order);

    // Test metadata update
    if (!$results['metadata']['has_metadata'] && $results['has_external_products']) {
        // Trigger metadata update
        shopcommerce_handle_order_completion($order_id);

        // Check if metadata was added
        $order = wc_get_order($order_id); // Refresh order object
        $results['metadata']['updated_metadata'] = shopcommerce_get_order_shopcommerce_metadata($order);
    }

    // Test logging
    $logger = isset($GLOBALS['shopcommerce_logger']) ? $GLOBALS['shopcommerce_logger'] : null;
    if ($logger) {
        $log_result = shopcommerce_log_order_external_products($order, $logger);
        $results['logs'][] = [
            'type' => 'logger',
            'success' => $log_result,
            'message' => $log_result ? 'Logged successfully' : 'Failed to log'
        ];
    }

    // Test fallback logging
    ob_start();
    $fallback_result = shopcommerce_log_order_external_products($order, null);
    ob_end_clean();
    $results['logs'][] = [
        'type' => 'fallback',
        'success' => $fallback_result,
        'message' => $fallback_result ? 'Fallback logged successfully' : 'Fallback failed'
    ];

    $results['success'] = true;
    return $results;
}