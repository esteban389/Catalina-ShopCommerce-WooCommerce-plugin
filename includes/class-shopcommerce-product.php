<?php

/**
 * ShopCommerce Product Handler Class
 *
 * Handles WooCommerce product creation, updating, and mapping
 * from ShopCommerce API data.
 *
 * @package ShopCommerce_Sync
 */

class ShopCommerce_Product {

    /**
     * Logger instance
     */
    private $logger;

    /**
     * Helpers instance
     */
    private $helpers;

    /**
     * Constructor
     *
     * @param ShopCommerce_Logger $logger Logger instance
     * @param ShopCommerce_Helpers $helpers Helpers instance
     */
    public function __construct($logger, $helpers) {
        $this->logger = $logger;
        $this->helpers = $helpers;
    }

    /**
     * Process product data and create/update WooCommerce product
     *
     * @param array $product_data Product data from ShopCommerce API
     * @param string $brand Brand name
     * @return array Processing results
     */
    public function process_product($product_data, $brand) {
        $results = [
            'success' => false,
            'product_id' => null,
            'action' => 'none', // 'created', 'updated', 'skipped'
            'error' => null,
        ];

        try {
            // Sanitize product data
            $sanitized_data = $this->helpers->sanitize_product_data($product_data);
            if (empty($sanitized_data)) {
                throw new Exception('Invalid product data');
            }

            // Extract SKU
            $sku = $sanitized_data['Sku'];
            $cache_key = $this->helpers->generate_cache_key($sanitized_data, $sku);

            $this->logger->debug('Processing product', [
                'sku' => $sku,
                'name' => $sanitized_data['Name'],
                'brand' => $brand
            ]);

            // Check if product exists
            $existing_product = $this->helpers->get_product_by_sku($sku);

            if ($existing_product) {
                // Update existing product
                $results = $this->update_product($existing_product, $sanitized_data, $brand);
                $results['action'] = 'updated';
            } else {
                // Create new product
                $results = $this->create_product($sanitized_data, $brand);
                $results['action'] = 'created';
            }

            $results['success'] = true;

        } catch (Exception $e) {
            $error_message = $e->getMessage();
            $this->logger->error('Error processing product', [
                'error' => $error_message,
                'product_data' => $product_data,
                'brand' => $brand
            ]);
            $results['error'] = $error_message;
        }

        return $results;
    }

    /**
     * Create new WooCommerce product
     *
     * @param array $product_data Sanitized product data
     * @param string $brand Brand name
     * @return array Creation results
     */
    private function create_product($product_data, $brand) {
        $results = [
            'success' => false,
            'product_id' => null,
            'error' => null,
        ];

        // Create new WooCommerce simple product object
        $wc_product = new WC_Product_Simple();

        // Get mapped data
        $mapped_data = $this->map_product_data($product_data, $brand, $product_data['Sku']);

        // Check SKU uniqueness before creating product
        $final_sku = $product_data['Sku'];
        if (!empty($final_sku)) {
            $existing_sku_id = wc_get_product_id_by_sku($final_sku);
            if ($existing_sku_id) {
                // SKU conflict, use empty SKU and store original in meta
                $final_sku = '';
                $mapped_data['_shopcommerce_sku'] = $product_data['Sku'];
                $this->logger->warning('SKU conflict detected for new product', [
                    'sku' => $product_data['Sku'],
                    'action' => 'stored_in_meta'
                ]);
            }
        }

        // Apply mapped data
        $this->apply_product_data($wc_product, $mapped_data, $product_data, $brand);

        // Set the final SKU (safe now)
        if (!empty($final_sku)) {
            $wc_product->set_sku($final_sku);
        }

        // Save the product
        $wc_product->save();
        $product_id = $wc_product->get_id();

        $this->logger->info('Created new product', [
            'product_id' => $product_id,
            'sku' => $final_sku ?: '(no SKU)',
            'name' => $product_data['Name'],
            'brand' => $brand
        ]);

        $results['success'] = true;
        $results['product_id'] = $product_id;

        return $results;
    }

    /**
     * Update existing WooCommerce product
     *
     * @param WC_Product $existing_product Existing product object
     * @param array $product_data Sanitized product data
     * @param string $brand Brand name
     * @return array Update results
     */
    private function update_product($existing_product, $product_data, $brand) {
        $results = [
            'success' => false,
            'product_id' => null,
            'error' => null,
        ];

        $product_id = $existing_product->get_id();

        // Get mapped data
        $mapped_data = $this->map_product_data($product_data, $brand, $product_data['Sku']);

        // Apply the mapped data to existing product
        $this->apply_product_data($existing_product, $mapped_data, $product_data, $brand);

        // Save the updated product
        $existing_product->save();

        $this->logger->info('Updated product', [
            'product_id' => $product_id,
            'sku' => $product_data['Sku'],
            'name' => $product_data['Name'],
            'brand' => $brand
        ]);

        $results['success'] = true;
        $results['product_id'] = $product_id;

        return $results;
    }

    /**
     * Map ShopCommerce product data to WooCommerce format
     *
     * @param array $product_data Sanitized product data
     * @param string $brand Brand name
     * @param string|null $sku Product SKU
     * @return array Mapped product data
     */
    private function map_product_data($product_data, $brand, $sku = null) {
        $mapped_data = [
            'name' => $product_data['Name'],
            'description' => $product_data['Description'],
            'regular_price' => $product_data['precio'],
            'stock_quantity' => $product_data['Quantity'],
            'stock_status' => $product_data['Quantity'] > 0 ? 'instock' : 'outofstock',
            'status' => 'publish',
            'meta_data' => [
                '_external_provider' => 'shopcommerce',
                '_external_provider_brand' => $brand,
                '_external_provider_sync_date' => current_time('mysql'),
                '_shopcommerce_sku' => $sku, // Store original SKU
            ],
            'category_name' => null,
            'image_url' => null,
        ];

        // Add additional metadata from ShopCommerce if available
        if (!empty($product_data['Marks'])) {
            $mapped_data['meta_data']['_shopcommerce_marca'] = $product_data['Marks'];
        }

        if (!empty($product_data['Categoria'])) {
            $mapped_data['meta_data']['_shopcommerce_categoria'] = $product_data['Categoria'];
        }

        // Handle categories
        if (!empty($product_data['CategoriaDescripcion'])) {
            $mapped_data['category_name'] = $product_data['CategoriaDescripcion'];
        } elseif (!empty($product_data['Categoria'])) {
            $mapped_data['category_name'] = $product_data['Categoria'];
        }

        // Handle product image
        if (!empty($product_data['Imagenes']) && is_array($product_data['Imagenes'])) {
            $mapped_data['image_url'] = $product_data['Imagenes'][0];
        }

        return $mapped_data;
    }

    /**
     * Apply mapped product data to a WooCommerce product object
     *
     * @param WC_Product $wc_product WooCommerce product object
     * @param array $mapped_data Mapped product data
     * @param array $original_data Original product data from API
     * @param string $brand Brand name
     */
    private function apply_product_data($wc_product, $mapped_data, $original_data, $brand) {
        // Set basic fields
        $wc_product->set_name($mapped_data['name']);
        $wc_product->set_description($mapped_data['description']);
        $wc_product->set_status($mapped_data['status']);
        $wc_product->set_stock_status($mapped_data['stock_status']);

        // Set price if available
        if ($mapped_data['regular_price'] !== null) {
            $wc_product->set_regular_price($mapped_data['regular_price']);
            $wc_product->set_price($mapped_data['regular_price']);
        }

        // Set stock quantity if available
        if ($mapped_data['stock_quantity'] !== null) {
            $wc_product->set_stock_quantity($mapped_data['stock_quantity']);
        }

        // Apply metadata
        foreach ($mapped_data['meta_data'] as $key => $value) {
            if ($value !== null) {
                $wc_product->update_meta_data($key, $value);
            }
        }

        // Handle categories
        if ($mapped_data['category_name']) {
            $category_id = $this->helpers->get_or_create_category($mapped_data['category_name']);
            if ($category_id) {
                $wc_product->set_category_ids([$category_id]);
            }
        }

        // Handle image updates with timestamp checking
        if ($mapped_data['image_url'] && method_exists($wc_product, 'get_id')) {
            $product_id = $wc_product->get_id();
            if ($product_id) {
                $this->handle_image_update($wc_product, $mapped_data['image_url'], $mapped_data['name']);
            }
        }
    }

    /**
     * Handle image updates with timestamp checking
     *
     * @param WC_Product $wc_product WooCommerce product object
     * @param string $image_url Image URL
     * @param string $product_name Product name
     */
    private function handle_image_update($wc_product, $image_url, $product_name) {
        $current_image_id = $wc_product->get_image_id();
        $last_image_update = $wc_product->get_meta('_external_image_last_updated');

        // Only update image if URL has changed or hasn't been updated in 24 hours
        $update_image = false;
        if (!$current_image_id) {
            $update_image = true; // No image set
        } else {
            $current_image_url = get_post_meta($current_image_id, '_external_image_url', true);
            if ($current_image_url !== $image_url) {
                $update_image = true; // URL has changed
            } elseif (!$last_image_update || (time() - strtotime($last_image_update)) > 24 * 60 * 60) {
                $update_image = true; // Haven't updated in 24 hours
            }
        }

        if ($update_image) {
            $new_image_id = $this->helpers->attach_product_image($image_url, $product_name);
            if ($new_image_id) {
                $wc_product->set_image_id($new_image_id);
                $wc_product->update_meta_data('_external_image_last_updated', current_time('mysql'));
                $this->logger->debug('Updated product image', [
                    'product_id' => $wc_product->get_id(),
                    'image_url' => $image_url
                ]);
            }
        }
    }

    /**
     * Process batch of products
     *
     * @param array $products Array of product data
     * @param string $brand Brand name
     * @return array Batch processing results
     */
    public function process_batch($products, $brand) {
        $results = [
            'total' => count($products),
            'created' => 0,
            'updated' => 0,
            'errors' => 0,
            'skipped' => 0,
            'details' => [],
        ];

        foreach ($products as $index => $product_data) {
            $this->logger->debug('Processing batch product', [
                'index' => $index,
                'total' => $results['total'],
                'brand' => $brand
            ]);

            $process_result = $this->process_product($product_data, $brand);

            if ($process_result['success']) {
                switch ($process_result['action']) {
                    case 'created':
                        $results['created']++;
                        break;
                    case 'updated':
                        $results['updated']++;
                        break;
                    default:
                        $results['skipped']++;
                        break;
                }
            } else {
                $results['errors']++;
            }

            $results['details'][] = $process_result;

            // Log progress every 10 products
            if (($index + 1) % 10 === 0) {
                $this->logger->info('Batch processing progress', [
                    'processed' => $index + 1,
                    'total' => $results['total'],
                    'created' => $results['created'],
                    'updated' => $results['updated'],
                    'errors' => $results['errors'],
                    'brand' => $brand
                ]);
            }
        }

        return $results;
    }

    /**
     * Get product statistics
     *
     * @return array Product statistics
     */
    public function get_statistics() {
        $stats = $this->helpers->get_sync_statistics();

        // Add additional product-specific statistics
        $stats['products_without_images'] = $this->count_products_without_images();
        $stats['products_out_of_stock'] = $this->count_products_out_of_stock();
        $stats['products_needing_update'] = $this->count_products_needing_update();

        return $stats;
    }

    /**
     * Count products without images
     *
     * @return int Number of products without images
     */
    private function count_products_without_images() {
        global $wpdb;

        return intval($wpdb->get_var(
            "SELECT COUNT(DISTINCT p.ID)
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'product'
             AND p.post_status != 'trash'
             AND pm.meta_key = '_external_provider'
             AND pm.meta_value = 'shopcommerce'
             AND NOT EXISTS (
                 SELECT 1 FROM {$wpdb->postmeta} pm2
                 WHERE pm2.post_id = p.ID
                 AND pm2.meta_key = '_thumbnail_id'
             )"
        ));
    }

    /**
     * Count products out of stock
     *
     * @return int Number of products out of stock
     */
    private function count_products_out_of_stock() {
        global $wpdb;

        return intval($wpdb->get_var(
            "SELECT COUNT(DISTINCT p.ID)
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'product'
             AND p.post_status != 'trash'
             AND pm.meta_key = '_external_provider'
             AND pm.meta_value = 'shopcommerce'
             AND EXISTS (
                 SELECT 1 FROM {$wpdb->postmeta} pm2
                 WHERE pm2.post_id = p.ID
                 AND pm2.meta_key = '_stock_status'
                 AND pm2.meta_value = 'outofstock'
             )"
        ));
    }

    /**
     * Count products needing update (older than 7 days)
     *
     * @return int Number of products needing update
     */
    private function count_products_needing_update() {
        global $wpdb;

        $seven_days_ago = date('Y-m-d H:i:s', strtotime('-7 days'));

        return intval($wpdb->get_var(
            "SELECT COUNT(DISTINCT p.ID)
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'product'
             AND p.post_status != 'trash'
             AND pm.meta_key = '_external_provider'
             AND pm.meta_value = 'shopcommerce'
             AND EXISTS (
                 SELECT 1 FROM {$wpdb->postmeta} pm2
                 WHERE pm2.post_id = p.ID
                 AND pm2.meta_key = '_external_provider_sync_date'
                 AND pm2.meta_value < '{$seven_days_ago}'
             )"
        ));
    }
}