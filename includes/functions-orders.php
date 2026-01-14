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
 * Format address array into a readable string
 *
 * @param array $address Address array
 * @return string Formatted address
 */
function shopcommerce_format_address($address) {
    if (empty($address)) {
        return '';
    }

    $formatted_parts = [];

    if (!empty($address['first_name'])) {
        $formatted_parts[] = trim($address['first_name'] . ' ' . $address['last_name']);
    }

    if (!empty($address['company'])) {
        $formatted_parts[] = $address['company'];
    }

    if (!empty($address['address_1'])) {
        $formatted_parts[] = $address['address_1'];
    }

    if (!empty($address['address_2'])) {
        $formatted_parts[] = $address['address_2'];
    }

    $city_line = [];
    if (!empty($address['city'])) {
        $city_line[] = $address['city'];
    }

    if (!empty($address['state'])) {
        $city_line[] = $address['state'];
    }

    if (!empty($address['postcode'])) {
        $city_line[] = $address['postcode'];
    }

    if (!empty($city_line)) {
        $formatted_parts[] = implode(', ', $city_line);
    }

    if (!empty($address['country'])) {
        $formatted_parts[] = $address['country'];
    }

    return implode("\n", array_filter($formatted_parts));
}

/**
 * Get ShopCommerce metadata from a product
 *
 * @param int|WC_Product $product Product ID or product object
 * @return array ShopCommerce metadata
 */
function shopcommerce_get_product_shopcommerce_metadata($product) {
    if (is_numeric($product)) {
        $product = wc_get_product($product);
    }

    if (!$product) {
        return [];
    }

    $metadata = [
        'part_num' => $product->get_meta('_shopcommerce_part_num', true),
        'marks' => $product->get_meta('_shopcommerce_marca', true),
        'lista_productos_bodega_json' => $product->get_meta('_shopcommerce_lista_productos_bodega', true),
        'xml_attributes' => $product->get_meta('_shopcommerce_xml_attributes', true),
        'external_provider' => $product->get_meta('_external_provider', true),
        'external_provider_brand' => $product->get_meta('_external_provider_brand', true),
        'sync_date' => $product->get_meta('_external_provider_sync_date', true),
        'shopcommerce_sku' => $product->get_meta('_shopcommerce_sku', true),
    ];

    // Decode JSON fields
    if (!empty($metadata['lista_productos_bodega_json'])) {
        $bodega_data = json_decode($metadata['lista_productos_bodega_json'], true);
        $metadata['lista_productos_bodega'] = is_array($bodega_data) ? $bodega_data : [];
    } else {
        $metadata['lista_productos_bodega'] = [];
    }

    return $metadata;
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
            // Get comprehensive ShopCommerce metadata
            $shopcommerce_metadata = shopcommerce_get_product_shopcommerce_metadata($product);

            $product_data = [
                'item_id' => $item_id,
                'product_id' => $product_id,
                'product_name' => $product->get_name(),
                'product_sku' => $product->get_sku(),
                'quantity' => $item->get_quantity(),
                'line_total' => $item->get_total(),
                'line_tax' => $item->get_total_tax(),
                'external_provider' => $external_provider,
                'external_provider_brand' => $shopcommerce_metadata['external_provider_brand'],
                'external_provider_sync_date' => $shopcommerce_metadata['sync_date'],
                'shopcommerce_sku' => $shopcommerce_metadata['shopcommerce_sku'],
                'part_num' => $shopcommerce_metadata['part_num'],
                'marks' => $shopcommerce_metadata['marks'],
                'lista_productos_bodega' => $shopcommerce_metadata['lista_productos_bodega'],
                'xml_attributes' => $shopcommerce_metadata['xml_attributes'],
            ];

            $external_provider_products[] = $product_data;
        }
    }

    return $external_provider_products;
}

/**
 * Get warehouse information for order products
 *
 * @param int|WC_Order $order Order ID or order object
 * @return array Warehouse information for products in the order
 */
function shopcommerce_get_order_warehouse_info($order) {
    if (is_numeric($order)) {
        $order = wc_get_order($order);
    }

    if (!$order) {
        return [];
    }

    $external_products = shopcommerce_get_external_provider_products_from_order($order);
    $warehouse_info = [];

    foreach ($external_products as $product) {
        if (!empty($product['lista_productos_bodega'])) {
            $product_warehouse_info = [
                'product_id' => $product['product_id'],
                'product_name' => $product['product_name'],
                'product_sku' => $product['product_sku'],
                'part_num' => $product['part_num'],
                'quantity_ordered' => $product['quantity'],
                'warehouses' => [],
            ];

            foreach ($product['lista_productos_bodega'] as $bodega) {
                if (isset($bodega['Bodega']) && isset($bodega['Stock'])) {
                    $product_warehouse_info['warehouses'][] = [
                        'warehouse' => $bodega['Bodega'],
                        'stock' => intval($bodega['Stock']),
                        'location' => $bodega['Ubicacion'] ?? '',
                        'available' => ($bodega['Disponible'] ?? true) === true,
                        'can_fulfill' => intval($bodega['Stock']) >= $product['quantity'],
                    ];
                }
            }

            $warehouse_info[] = $product_warehouse_info;
        }
    }

    return $warehouse_info;
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
 * Get comprehensive shipping information from an order
 *
 * @param int|WC_Order $order Order ID or order object
 * @return array Complete shipping information
 */
function shopcommerce_get_order_shipping_info($order) {
    // Get order object if ID was passed
    if (is_numeric($order)) {
        $order = wc_get_order($order);
    }

    if (!$order) {
        return [];
    }

    $shipping_info = [
        'shipping_method' => $order->get_shipping_method(),
        'shipping_method_title' => $order->get_shipping_method(),
        'shipping_total' => $order->get_shipping_total(),
        'shipping_tax' => $order->get_shipping_tax(),
        'formatted_shipping_total' => $order->get_shipping_total() > 0 ? wc_price($order->get_shipping_total()) : '',
    ];

    // Get shipping address
    $shipping_address = $order->get_address('shipping');
    if (!empty($shipping_address)) {
        $shipping_info['shipping_address'] = [
            'first_name' => $shipping_address['first_name'] ?? '',
            'last_name' => $shipping_address['last_name'] ?? '',
            'company' => $shipping_address['company'] ?? '',
            'address_1' => $shipping_address['address_1'] ?? '',
            'address_2' => $shipping_address['address_2'] ?? '',
            'city' => $shipping_address['city'] ?? '',
            'state' => $shipping_address['state'] ?? '',
            'postcode' => $shipping_address['postcode'] ?? '',
            'country' => $shipping_address['country'] ?? '',
            'phone' => $shipping_address['phone'] ?? '',
            'formatted_address' => method_exists($order, 'get_formatted_shipping_address') ? $order->get_formatted_shipping_address() : shopcommerce_format_address($shipping_address),
            'state_id' => $order->get_meta('_shipping_state_id') ?? '',
            'county_id' => $order->get_meta('_shipping_county_id') ?? '',
            'phone_number' => $order->get_meta('_shipping_phone_number') ?? '',
            // In the wordpress page this doesn't have a proper meta field, so we need to get it using the default name, this mapps to the field with label CEDULA/NIT
            'cc/nit' => $order->get_meta('_shipping_') ?? '',
        ];
    }

    // Get billing address as fallback for shipping
    if (empty($shipping_info['shipping_address'])) {
        $billing_address = $order->get_address('billing');
        if (!empty($billing_address)) {
            $shipping_info['billing_address_used_for_shipping'] = true;
            $shipping_info['shipping_address'] = [
                'first_name' => $billing_address['first_name'] ?? '',
                'last_name' => $billing_address['last_name'] ?? '',
                'company' => $billing_address['company'] ?? '',
                'address_1' => $billing_address['address_1'] ?? '',
                'address_2' => $billing_address['address_2'] ?? '',
                'city' => $billing_address['city'] ?? '',
                'state' => $billing_address['state'] ?? '',
                'postcode' => $billing_address['postcode'] ?? '',
                'country' => $billing_address['country'] ?? '',
                'phone' => $billing_address['phone'] ?? '',
                'formatted_address' => method_exists($order, 'get_formatted_billing_address') ? $order->get_formatted_billing_address() : shopcommerce_format_address($billing_address),
            ];
        }
    }

    return $shipping_info;
}

/**
 * Get comprehensive client information from an order
 *
 * @param int|WC_Order $order Order ID or order object
 * @return array Complete client information
 */
function shopcommerce_get_order_client_info($order) {
    // Get order object if ID was passed
    if (is_numeric($order)) {
        $order = wc_get_order($order);
    }

    if (!$order) {
        return [];
    }

    $client_info = [
        'customer_id' => $order->get_customer_id(),
        'user_id' => $order->get_user_id(),
        'customer_ip_address' => $order->get_customer_ip_address(),
        'customer_user_agent' => $order->get_customer_user_agent(),
        'customer_note' => $order->get_customer_note(),
        'payment_method' => $order->get_payment_method(),
        'payment_method_title' => $order->get_payment_method_title(),
        'transaction_id' => $order->get_transaction_id(),
        'date_created' => $order->get_date_created() ? $order->get_date_created()->format('Y-m-d H:i:s') : null,
        'date_completed' => $order->get_date_completed() ? $order->get_date_completed()->format('Y-m-d H:i:s') : null,
    ];

    // Get billing information
    $billing_address = $order->get_address('billing');
    if (!empty($billing_address)) {
        $client_info['billing_address'] = [
            'first_name' => $billing_address['first_name'] ?? '',
            'last_name' => $billing_address['last_name'] ?? '',
            'company' => $billing_address['company'] ?? '',
            'address_1' => $billing_address['address_1'] ?? '',
            'address_2' => $billing_address['address_2'] ?? '',
            'city' => $billing_address['city'] ?? '',
            'state' => $billing_address['state'] ?? '',
            'postcode' => $billing_address['postcode'] ?? '',
            'country' => $billing_address['country'] ?? '',
            'email' => $billing_address['email'] ?? '',
            'phone' => $billing_address['phone'] ?? '',
            'formatted_address' => method_exists($order, 'get_formatted_billing_address') ? $order->get_formatted_billing_address() : shopcommerce_format_address($billing_address),
        ];
    }

    // Get customer object for additional details
    if ($order->get_customer_id()) {
        $customer = new WC_Customer($order->get_customer_id());
        $client_info['customer_details'] = [
            'username' => $customer->get_username(),
            'email' => $customer->get_email(),
            'first_name' => $customer->get_first_name(),
            'last_name' => $customer->get_last_name(),
            'display_name' => $customer->get_display_name(),
            'role' => $customer->get_role(),
            'is_paying_customer' => $customer->is_paying_customer(),
            'date_registered' => method_exists($customer, 'get_date_registered') ? ($customer->get_date_registered() ? $customer->get_date_registered()->format('Y-m-d H:i:s') : null) : null,
            'order_count' => $customer->get_order_count(),
            'total_spent' => $customer->get_total_spent(),
        ];
    }

    return $client_info;
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
    $part_nums = array_filter(array_column($external_products, 'part_num'));
    $product_summary = [];

    foreach ($external_products as $product) {
        $brand = $product['external_provider_brand'];
        if (!isset($product_summary[$brand])) {
            $product_summary[$brand] = [
                'count' => 0,
                'quantity' => 0,
                'value' => 0,
                'part_nums' => [],
                'has_warehouse_info' => false,
            ];
        }
        $product_summary[$brand]['count']++;
        $product_summary[$brand]['quantity'] += $product['quantity'];
        $product_summary[$brand]['value'] += $product['line_total'];

        if (!empty($product['part_num'])) {
            $product_summary[$brand]['part_nums'][] = $product['part_num'];
        }

        if (!empty($product['lista_productos_bodega'])) {
            $product_summary[$brand]['has_warehouse_info'] = true;
        }
    }

    // Remove duplicate part numbers
    foreach ($product_summary as &$brand_data) {
        $brand_data['part_nums'] = array_unique($brand_data['part_nums']);
    }

    return [
        'has_external_products' => true,
        'total_products' => count($external_products),
        'total_quantity' => array_sum(array_column($external_products, 'quantity')),
        'total_value' => array_sum(array_column($external_products, 'line_total')),
        'brands' => $brands,
        'part_nums' => $part_nums,
        'has_warehouse_info' => !empty(array_filter(array_column($external_products, 'lista_productos_bodega'))),
        'product_summary' => $product_summary,
    ];
}

/**
 * Sends a request to create an external order from a local WooCommerce order
 *
 * @param int|WC_Order $order Order ID or order object
 * @param ShopCommerce_Logger|null $logger Logger instance (optional)
 * @return bool True if logging was successful
 */
function shopcommerce_create_external_order_from_local_order($order, $logger = null) {
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

    $shipping_info = shopcommerce_get_order_shipping_info($order);
    // Log to logger if available
    if ($logger) {
        // Additional detailed activity logging
        $logger->log_activity(
            'order_completed_with_external_products_detailed',
            sprintf(
                'Order %s completed with comprehensive data: %d external provider products from %d brand(s), total value $%.2f, shipped to %s, %s',
                $order->get_order_number(),
                !empty($shipping_info['shipping_address']) ?
                    ($shipping_info['shipping_address']['city'] . ', ' . $shipping_info['shipping_address']['state']) :
                    'no shipping address',
                !empty($client_info['billing_address']) ?
                    ($client_info['billing_address']['first_name'] . ' ' . $client_info['billing_address']['last_name']) :
                    'guest customer'
            ),
            [
                'order_id' => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'shipping_city' => $shipping_info['shipping_address']['city'] ?? null,
                'shipping_state' => $shipping_info['shipping_address']['state'] ?? null,
                'customer_name' => $client_info['billing_address']['first_name'] . ' ' . $client_info['billing_address']['last_name'] ?? 'Guest',
                'customer_email' => $client_info['billing_address']['email'] ?? '',
                'has_shipping_address' => !empty($shipping_info['shipping_address']),
                'has_billing_address' => !empty($client_info['billing_address']),
            ]
        );

        // Add comprehensive metadata to order
        $order->add_meta_data('_shopcommerce_shipping_info', json_encode($shipping_info), true);
        $order->add_meta_data('_shopcommerce_client_info', json_encode($client_info), true);
        $order->add_meta_data('_shopcommerce_products_detailed', json_encode($external_products), true);
        $order->add_meta_data('_shopcommerce_data_collection_date', current_time('mysql'), true);
        $order->save();

        if ($logger) {
            $logger->debug('Added comprehensive ShopCommerce metadata to order', [
                'order_id' => $order->get_id(),
                'metadata_added' => ['shipping_info', 'client_info', 'products_detailed', 'data_collection_date']
            ]);
        }

        return true;
    }

    try {
        $listaPedidosDetalle = map_product_to_request_body($external_products);

        $requestBody = [
                'listaPedido' => [
                        'AccountNum' => $shipping_info['shipping_address']['cc/nit'], // TODO: this is the company's nit,
                        'ClienteEntrega' => $shipping_info['shipping_address']['cc/nit'],
                        'NombreCLienteEntrega'=> $shipping_info['shipping_address']['first_name'] . ' ' . $shipping_info['shipping_address']['last_name'],
                        'TelefonoEntrega' => $shipping_info['shipping_address']['phone_number'],
                        'DireccionEntrega' => $shipping_info['shipping_address']['address_1'] . ' ' . $shipping_info['shipping_address']['address_2'],
                        'StateId' => $shipping_info['shipping_address']['state_id'],
                        'CountyId' => $shipping_info['shipping_address']['county_id'],
                        'RecogerEnSitio' => 0,
                        'EntregaUsuarioFinal' => 1,
                        "listaPedidoDetalle" => $listaPedidosDetalle
                ]
        ];

        if ($logger) {
            $logger->debug('Mapped product data for external order creation', [
                'order_id' => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'external_products_count' => count($external_products),
                'request_body' => $requestBody,
                'function' => 'shopcommerce_create_external_order_from_local_order'
            ]);
        }

        $apiHandler = $GLOBALS['shopcommerce_api'];
        if(!$apiHandler) {
            if ($logger) {
                $logger->error('API handler not available for external order creation', [
                    'order_id' => $order->get_id(),
                    'function' => 'shopcommerce_create_external_order_from_local_order'
                ]);
                $apiHandler = new ShopCommerce_API($logger);
            }
        }
        $response = $apiHandler->realizarPedido($requestBody);

        if(!$response['success']) {
            if ($logger) {
                $logger->error('Failed to create external order via API', [
                    'order_id' => $order->get_id(),
                    'order_number' => $order->get_order_number(),
                    'response' => $response,
                    'function' => 'shopcommerce_create_external_order_from_local_order'
                ]);
            }
            return false;
        } else {
            if ($logger) {
                $logger->info('Successfully created external order via API', [
                    'order_id' => $order->get_id(),
                    'order_number' => $order->get_order_number(),
                    'external_order_id' => $response['data']['OrderId'] ?? null,
                    'response' => $response,
                    'function' => 'shopcommerce_create_external_order_from_local_order'
                ]);
            }
        }

    }catch (Exception $e) {
        if ($logger) {
            $logger->error('Error mapping product data for external order creation', [
                'order_id' => $order->get_id(),
                'error_message' => $e->getMessage(),
                'function' => 'shopcommerce_create_external_order_from_local_order',
                '$product_data' => $external_products
            ]);
        }
        return false;
    }

    return true;
}

/**
 *  Map product data to request body format for the RealizarPedido API call
 * @param array $product Product data array
 * @return array Mapped product data for request body
 * @throws Exception if the product data is invalid, like when there is no stock in any bodega
 */
function map_product_to_request_body($product) {
    if(empty($product)) {
        return [];
    }

    $bodegas = $product['lista_productos_bodega'];

    if(empty($bodegas) || !is_array($bodegas)) {
        throw new Exception('No bodegas data available for product ' . $product['part_num']);
    }

    //Map bodegas until getting the first one with quantity > 0
    $seletedBodega = null;
    foreach($bodegas as $bodega) {
        if(isset($bodega['Cantidad'] ) && $bodega['Cantidad'] > 0) {
            $seletedBodega = $bodega;
            break;
        }
    }

    if($seletedBodega === null) {
        throw new Exception('No stock available in any bodega for product ' . $product['part_num']);
    }
    return [
        'PartNum' => $product['part_num'],
        'Cantidad' => $product['quantity'],
        'Marks' => $product['marks'],
        'Bodega' => $seletedBodega['Bodega'],
    ];
}

/**
 * Handle order completion event
 *
 * This function is hooked to woocommerce_order_status_completed and woocommerce_order_status_processing
 * It adds metadata and logs activity for orders with external provider products
 *
 * @param int $order_id The order ID
 */
function shopcommerce_handle_order_completion($order_id) {
    // Get logger if available
    $logger = isset($GLOBALS['shopcommerce_logger']) ? $GLOBALS['shopcommerce_logger'] : null;

    // Check if WooCommerce is available
    if (!class_exists('WC_Order')) {
        if ($logger) {
            $logger->error('WooCommerce not available for order completion', [
                'order_id' => $order_id,
                'function' => 'shopcommerce_handle_order_completion'
            ]);
        }
        return;
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        if ($logger) {
            $logger->error('Order not found for completion processing', [
                'order_id' => $order_id,
                'function' => 'shopcommerce_handle_order_completion'
            ]);
        }
        return;
    }

    // Log order completion processing
    if ($logger) {
        $logger->info('Processing order completion', [
            'order_id' => $order_id,
            'order_number' => $order->get_order_number(),
            'order_status' => $order->get_status(),
            'function' => 'shopcommerce_handle_order_completion'
        ]);
    }

    // Check if order has external provider products
    if (shopcommerce_order_has_external_provider_products($order)) {
        // Get statistics before adding metadata
        $stats = shopcommerce_get_order_external_provider_stats($order);

        // Add metadata to identify order with external provider products
        $order->add_meta_data('_has_shopcommerce_products', 'yes', true);
        $order->add_meta_data('_shopcommerce_order_processed', current_time('mysql'), true);
        $order->add_meta_data('_shopcommerce_product_count', $stats['total_products'], true);
        $order->add_meta_data('_shopcommerce_brands', implode(', ', $stats['brands']), true);
        $order->add_meta_data('_shopcommerce_total_value', $stats['total_value'], true);
        $order->add_meta_data('_shopcommerce_total_quantity', $stats['total_quantity'], true);

        // Save the metadata
        $order->save();

        // Log metadata addition
        if ($logger) {
            $logger->info('Added ShopCommerce metadata to completed order', [
                'order_id' => $order_id,
                'order_number' => $order->get_order_number(),
                'product_count' => $stats['total_products'],
                'brands' => $stats['brands'],
                'total_value' => $stats['total_value'],
                'total_quantity' => $stats['total_quantity']
            ]);

            $logger->log_activity(
                'order_metadata_added',
                sprintf(
                    'Order %s completed with ShopCommerce metadata (%d products, %d brands, $%.2f)',
                    $order->get_order_number(),
                    $stats['total_products'],
                    count($stats['brands']),
                    $stats['total_value']
                ),
                [
                    'order_id' => $order_id,
                    'order_number' => $order->get_order_number(),
                    'product_count' => $stats['total_products'],
                    'brands' => $stats['brands'],
                    'total_value' => $stats['total_value'],
                    'total_quantity' => $stats['total_quantity']
                ]
            );
        }
    }

    // Log external provider products
    shopcommerce_create_external_order_from_local_order($order, $logger);
}

/**
 * Handle order creation event
 *
 * This function is hooked to woocommerce_new_order
 *
 * @param int $order_id The new order ID
 */
function shopcommerce_handle_order_creation($order_id) {
    // Get logger if available
    $logger = isset($GLOBALS['shopcommerce_logger']) ? $GLOBALS['shopcommerce_logger'] : null;

    // Check if WooCommerce is available
    if (!class_exists('WC_Order')) {
        if ($logger) {
            $logger->error('WooCommerce not available for order creation', [
                'order_id' => $order_id,
                'function' => 'shopcommerce_handle_order_creation'
            ]);
        }
        return;
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        if ($logger) {
            $logger->error('Order not found for creation processing', [
                'order_id' => $order_id,
                'function' => 'shopcommerce_handle_order_creation'
            ]);
        }
        return;
    }

    // Log order creation processing
    if ($logger) {
        $logger->info('Processing order creation', [
            'order_id' => $order_id,
            'order_number' => $order->get_order_number(),
            'order_status' => $order->get_status(),
            'customer_id' => $order->get_customer_id(),
            'function' => 'shopcommerce_handle_order_creation'
        ]);
    }

    // Check if order has external provider products and log if it does
    if (shopcommerce_order_has_external_provider_products($order)) {
        $stats = shopcommerce_get_order_external_provider_stats($order);

        if ($logger) {
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
        }
    } else {
        // Log that order was created but doesn't have external products
        if ($logger) {
            $logger->debug('Order created without external provider products', [
                'order_id' => $order_id,
                'order_number' => $order->get_order_number(),
                'order_status' => $order->get_status()
            ]);
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
 * Get orders with external provider products that are not completed
 *
 * @param array $args Query arguments for WP_Query
 * @return array Array of order objects with external provider products that are not completed
 */
function shopcommerce_get_incomplete_orders_with_external_products($args = []) {
    $default_args = [
        'status' => ['pending', 'processing', 'on-hold', 'failed', 'cancelled'],
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
    // Get logger if available
    $logger = isset($GLOBALS['shopcommerce_logger']) ? $GLOBALS['shopcommerce_logger'] : null;

    $default_args = [
        'status' => ['completed', 'processing'],
        'limit' => 100,
        'return' => 'objects',
    ];

    $args = wp_parse_args($args, $default_args);

    // Log the start of metadata update process
    if ($logger) {
        $logger->info('Starting existing orders metadata update', [
            'args' => $args,
            'function' => 'shopcommerce_update_existing_orders_metadata'
        ]);
    }

    $orders = wc_get_orders($args);
    $results = [
        'total_orders' => count($orders),
        'updated_orders' => 0,
        'skipped_orders' => 0,
        'errors' => [],
    ];

    if ($logger) {
        $logger->info('Retrieved orders for metadata update', [
            'total_orders_retrieved' => count($orders),
            'query_args' => $args
        ]);
    }

    foreach ($orders as $order) {
        try {
            // Skip if already has metadata
            if (shopcommerce_order_has_shopcommerce_metadata($order)) {
                $results['skipped_orders']++;
                if ($logger) {
                    $logger->debug('Skipping order - already has ShopCommerce metadata', [
                        'order_id' => $order->get_id(),
                        'order_number' => $order->get_order_number()
                    ]);
                }
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

                if ($logger) {
                    $logger->info('Added ShopCommerce metadata to existing order', [
                        'order_id' => $order->get_id(),
                        'order_number' => $order->get_order_number(),
                        'product_count' => $stats['total_products'],
                        'brands' => $stats['brands'],
                        'total_value' => $stats['total_value']
                    ]);
                }
            } else {
                $results['skipped_orders']++;
                if ($logger) {
                    $logger->debug('Skipping order - no external provider products', [
                        'order_id' => $order->get_id(),
                        'order_number' => $order->get_order_number()
                    ]);
                }
            }
        } catch (Exception $e) {
            $results['errors'][] = [
                'order_id' => $order->get_id(),
                'error' => $e->getMessage()
            ];
            if ($logger) {
                $logger->error('Error updating order metadata', [
                    'order_id' => $order->get_id(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
    }

    // Log completion of update process
    if ($logger) {
        $logger->info('Completed existing orders metadata update', [
            'total_orders' => $results['total_orders'],
            'updated_orders' => $results['updated_orders'],
            'skipped_orders' => $results['skipped_orders'],
            'errors_count' => count($results['errors'])
        ]);

        if ($results['updated_orders'] > 0) {
            $logger->log_activity(
                'existing_orders_metadata_updated',
                sprintf(
                    'Updated metadata for %d existing orders with ShopCommerce products',
                    $results['updated_orders']
                ),
                [
                    'total_orders' => $results['total_orders'],
                    'updated_orders' => $results['updated_orders'],
                    'skipped_orders' => $results['skipped_orders'],
                    'errors_count' => count($results['errors'])
                ]
            );
        }
    }

    return $results;
}

/**
 * Add custom shipping fields to WooCommerce checkout
 *
 * This function hooks into WooCommerce checkout fields to add required fields:
 * - phoneNumber (phone number for shipping)
 * - stateId (state ID for shipping)
 * - countyId (county ID for shipping)
 *
 * @param array $fields Default checkout fields
 * @return array Modified checkout fields with custom shipping fields
 */
function shopcommerce_add_custom_shipping_fields($fields) {
    // Add custom shipping fields
    $fields['shipping']['shipping_phone_number'] = [
        'label'        => __('Número de teléfono', 'shopcommerce-product-sync-plugin'),
        'placeholder'  => __('Ingrese el número de teléfono', 'shopcommerce-product-sync-plugin'),
        'required'     => true,
        'class'        => ['form-row-wide'],
        'priority'     => 25,
        'clear'        => true,
    ];

    $fields['shipping']['shipping_state_id'] = [
        'label'        => __('Departamento', 'shopcommerce-product-sync-plugin'),
        'placeholder'  => __('Ingrese el departamento', 'shopcommerce-product-sync-plugin'),
        'required'     => true,
        'class'        => ['form-row-wide'],
        'priority'     => 30,
        'clear'        => true,
        'type'         => 'select',
        'options'      => [''=>'Seleccione...'],
    ];

    $fields['shipping']['shipping_county_id'] = [
        'label'        => __('Municipio', 'shopcommerce-product-sync-plugin'),
        'placeholder'  => __('Ingrese el municipio', 'shopcommerce-product-sync-plugin'),
        'required'     => true,
        'class'        => ['form-row-wide'],
        'priority'     => 35,
        'clear'        => true,
        'type'         => 'select',
        'options'      => [''=>'Seleccione...'],
    ];

    return $fields;
}

add_action('wp_enqueue_scripts', 'enqueue_checkout_scripts');
function enqueue_checkout_scripts() {
    if (is_checkout()) {
        wp_enqueue_script(
            'checkout-selects',
            SHOPCOMMERCE_SYNC_ASSETS_DIR . 'js/checkout-selects.js',
            array('jquery'),
            '1.0',
            true
        );

        $departamentos_data = file_get_contents(SHOPCOMMERCE_SYNC_ASSETS_DATA_DIR . 'dane-codes.json');
        wp_localize_script('checkout-selects', 'DANE_DATA', json_decode($departamentos_data, true));
    }
}


/**
 * Validate custom shipping fields during checkout
 *
 * This function validates the custom shipping fields when checkout is processed
 */
function shopcommerce_validate_custom_shipping_fields() {
    // Check if shipping phone number is required and empty
    if (isset($_POST['shipping_phone_number']) && empty($_POST['shipping_phone_number'])) {
        wc_add_notice(__('Phone number is a required shipping field.', 'shopcommerce-product-sync-plugin'), 'error');
    }

    // Check if shipping state ID is required and empty
    if (isset($_POST['shipping_state_id']) && empty($_POST['shipping_state_id'])) {
        wc_add_notice(__('State ID is a required shipping field.', 'shopcommerce-product-sync-plugin'), 'error');
    }

    // Check if shipping county ID is required and empty
    if (isset($_POST['shipping_county_id']) && empty($_POST['shipping_county_id'])) {
        wc_add_notice(__('County ID is a required shipping field.', 'shopcommerce-product-sync-plugin'), 'error');
    }
}

/**
 * Save custom shipping fields to order meta
 *
 * This function saves the custom shipping field values when an order is created
 *
 * @param int $order_id Order ID
 */
function shopcommerce_save_custom_shipping_fields($order_id) {
    if (!empty($_POST['shipping_phone_number'])) {
        update_post_meta($order_id, '_shipping_phone_number', sanitize_text_field($_POST['shipping_phone_number']));
    }

    if (!empty($_POST['shipping_state_id'])) {
        update_post_meta($order_id, '_shipping_state_id', sanitize_text_field($_POST['shipping_state_id']));
    }

    if (!empty($_POST['shipping_county_id'])) {
        update_post_meta($order_id, '_shipping_county_id', sanitize_text_field($_POST['shipping_county_id']));
    }
}

/**
 * Display custom shipping fields in order admin
 *
 * This function displays the custom shipping field values in the WordPress admin order page
 *
 * @param WC_Order $order Order object
 */
function shopcommerce_display_custom_shipping_fields_admin($order) {
    $phone_number = $order->get_meta('_shipping_phone_number');
    $state_id = $order->get_meta('_shipping_state_id');
    $county_id = $order->get_meta('_shipping_county_id');

    if (!empty($phone_number) || !empty($state_id) || !empty($county_id)) {
        echo '<div class="address">';
        echo '<p><strong>' . __('Additional Shipping Information', 'shopcommerce-product-sync-plugin') . ':</strong></p>';

        if (!empty($phone_number)) {
            echo '<p>' . __('Phone Number:', 'shopcommerce-product-sync-plugin') . ' ' . esc_html($phone_number) . '</p>';
        }

        if (!empty($state_id)) {
            echo '<p>' . __('State ID:', 'shopcommerce-product-sync-plugin') . ' ' . esc_html($state_id) . '</p>';
        }

        if (!empty($county_id)) {
            echo '<p>' . __('County ID:', 'shopcommerce-product-sync-plugin') . ' ' . esc_html($county_id) . '</p>';
        }

        echo '</div>';
    }
}

/**
 * Display custom shipping fields in customer order details
 *
 * This function displays the custom shipping field values in customer-facing order pages
 *
 * @param WC_Order $order Order object
 */
function shopcommerce_display_custom_shipping_fields_customer($order) {
    $phone_number = $order->get_meta('_shipping_phone_number');
    $state_id = $order->get_meta('_shipping_state_id');
    $county_id = $order->get_meta('_shipping_county_id');

    if (!empty($phone_number) || !empty($state_id) || !empty($county_id)) {
        echo '<h3>' . __('Additional Shipping Information', 'shopcommerce-product-sync-plugin') . '</h3>';
        echo '<ul class="woocommerce-order-overview__additional-info">';

        if (!empty($phone_number)) {
            echo '<li class="woocommerce-order-overview__additional-info-phone">';
            echo '<span class="woocommerce-order-overview__additional-info-label">' . __('Phone Number:', 'shopcommerce-product-sync-plugin') . '</span> ';
            echo '<span class="woocommerce-order-overview__additional-info-value">' . esc_html($phone_number) . '</span>';
            echo '</li>';
        }

        if (!empty($state_id)) {
            echo '<li class="woocommerce-order-overview__additional-info-state">';
            echo '<span class="woocommerce-order-overview__additional-info-label">' . __('State ID:', 'shopcommerce-product-sync-plugin') . '</span> ';
            echo '<span class="woocommerce-order-overview__additional-info-value">' . esc_html($state_id) . '</span>';
            echo '</li>';
        }

        if (!empty($county_id)) {
            echo '<li class="woocommerce-order-overview__additional-info-county">';
            echo '<span class="woocommerce-order-overview__additional-info-label">' . __('County ID:', 'shopcommerce-product-sync-plugin') . '</span> ';
            echo '<span class="woocommerce-order-overview__additional-info-value">' . esc_html($county_id) . '</span>';
            echo '</li>';
        }

        echo '</ul>';
    }
}

/**
 * Include custom shipping fields in order emails
 *
 * This function adds the custom shipping fields to WooCommerce order emails
 *
 * @param array $fields Existing email fields
 * @param bool $sent_to_admin Whether the email is sent to admin
 * @param WC_Order $order Order object
 * @return array Modified email fields
 */
function shopcommerce_include_custom_shipping_fields_in_emails($fields, $sent_to_admin, $order) {
    $phone_number = $order->get_meta('_shipping_phone_number');
    $state_id = $order->get_meta('_shipping_state_id');
    $county_id = $order->get_meta('_shipping_county_id');

    $additional_fields = [];

    if (!empty($phone_number)) {
        $additional_fields['shipping_phone_number'] = [
            'label' => __('Phone Number', 'shopcommerce-product-sync-plugin'),
            'value' => $phone_number,
        ];
    }

    if (!empty($state_id)) {
        $additional_fields['shipping_state_id'] = [
            'label' => __('State ID', 'shopcommerce-product-sync-plugin'),
            'value' => $state_id,
        ];
    }

    if (!empty($county_id)) {
        $additional_fields['shipping_county_id'] = [
            'label' => __('County ID', 'shopcommerce-product-sync-plugin'),
            'value' => $county_id,
        ];
    }

    // Add our fields after existing shipping fields
    if (!empty($additional_fields)) {
        $new_fields = [];
        foreach ($fields as $key => $field) {
            $new_fields[$key] = $field;
            if ($key === 'shipping_address') {
                $new_fields['additional_shipping_info'] = [
                    'label' => __('Additional Shipping Information', 'shopcommerce-product-sync-plugin'),
                    'value' => '',
                    'fields' => $additional_fields,
                ];
            }
        }
        $fields = $new_fields;
    }

    return $fields;
}

/**
 * Initialize custom shipping fields functionality
 */
function shopcommerce_init_custom_shipping_fields() {
    // Add custom fields to checkout
    add_filter('woocommerce_checkout_fields', 'shopcommerce_add_custom_shipping_fields');

    // Validate custom fields during checkout
    add_action('woocommerce_checkout_process', 'shopcommerce_validate_custom_shipping_fields');

    // Save custom fields to order meta
    add_action('woocommerce_checkout_update_order_meta', 'shopcommerce_save_custom_shipping_fields');

    // Display custom fields in admin order page
    add_action('woocommerce_admin_order_data_after_shipping_address', 'shopcommerce_display_custom_shipping_fields_admin', 10, 1);

    // Display custom fields in customer order pages
    add_action('woocommerce_order_details_after_customer_details', 'shopcommerce_display_custom_shipping_fields_customer', 10, 1);

    // Include custom fields in emails
    add_filter('woocommerce_email_order_meta_fields', 'shopcommerce_include_custom_shipping_fields_in_emails', 10, 3);
}

// Initialize the custom shipping fields functionality
add_action('woocommerce_init', 'shopcommerce_init_custom_shipping_fields');

/**
 * Add custom CSS for additional shipping info display
 */
function shopcommerce_custom_shipping_fields_css() {
    ?>
    <style>
        .woocommerce-order-overview__additional-info {
            list-style: none;
            padding: 0;
            margin: 1em 0;
        }

        .woocommerce-order-overview__additional-info li {
            margin-bottom: 0.5em;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .woocommerce-order-overview__additional-info-label {
            font-weight: 600;
            margin-right: 1em;
        }

        .woocommerce-order-overview__additional-info-value {
            text-align: right;
        }

        @media (max-width: 768px) {
            .woocommerce-order-overview__additional-info li {
                flex-direction: column;
                align-items: flex-start;
            }

            .woocommerce-order-overview__additional-info-value {
                text-align: left;
                margin-top: 0.25em;
            }
        }
    </style>
    <?php
}
add_action('wp_head', 'shopcommerce_custom_shipping_fields_css');

/**
 * Scheduled log clearing function
 * This function is called by the WordPress cron job to clear logs based on the configured interval
 */
function shopcommerce_clear_logs_scheduled() {
    $logger = isset($GLOBALS['shopcommerce_logger']) ? $GLOBALS['shopcommerce_logger'] : null;

    if ($logger) {
        $logger->clear_logs_by_interval();
    }
}