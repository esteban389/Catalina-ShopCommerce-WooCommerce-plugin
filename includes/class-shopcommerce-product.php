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

            // Enhanced duplicate prevention
            $duplicate_check = $this->check_for_duplicates($sku);
            if ($duplicate_check['found']) {
                $this->logger->info('Duplicate product detected, will update', [
                    'sku' => $sku,
                    'existing_product_id' => $duplicate_check['product_id'],
                    'method' => $duplicate_check['method']
                ]);

                // Update existing product
                $existing_product = wc_get_product($duplicate_check['product_id']);
                if ($existing_product) {
                    $results = $this->update_product($existing_product, $sanitized_data, $brand);
                    $results['action'] = 'updated';
                } else {
                    throw new Exception('Found duplicate reference but product object is invalid');
                }
            } else {
                // Create new product with additional safety checks
                $this->logger->info('No duplicate product detected, will create', [
                    'sku' => $sku,
                    'name' => $sanitized_data['Name'],
                    'brand' => $brand
                ]);

                $results = $this->create_product_safely($sanitized_data, $brand);
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
     * Enhanced duplicate detection for products
     *
     * @param string $sku Product SKU to check
     * @return array Duplicate check results
     */
    private function check_for_duplicates($sku) {
        if (empty($sku)) {
            return ['found' => false, 'product_id' => null, 'method' => null];
        }

        // Try multiple methods to find duplicates
        $methods = [
            'wp_query' => function($sku) {
                // Normalize SKU (trim, uppercase for comparison)
                $normalized_sku = trim(strtoupper($sku));

                // Method 1: Direct SKU match
                $args = [
                    'post_type' => 'product',
                    'post_status' => 'any',
                    'meta_query' => [
                        [
                            'key' => '_sku',
                            'value' => $normalized_sku,
                            'compare' => '='
                        ]
                    ],
                    'posts_per_page' => 1
                ];

                $query = new WP_Query($args);

                if ($query->have_posts()) {
                    $post = $query->posts[0];
                    return $post->ID;
                }
            },
            'wc_sku' => function($sku) {
                return wc_get_product_id_by_sku($sku);
            },
            'db_sku' => function($sku) {
                global $wpdb;
                $normalized_sku = trim(strtoupper($sku));
                return $wpdb->get_var($wpdb->prepare(
                    "SELECT post_id FROM {$wpdb->postmeta}
                     WHERE meta_key = '_sku' AND UPPER(TRIM(meta_value)) = %s
                     LIMIT 1",
                    $normalized_sku
                ));
            },
            'shopcommerce_sku' => function($sku) {
                global $wpdb;
                $normalized_sku = trim(strtoupper($sku));
                return $wpdb->get_var($wpdb->prepare(
                    "SELECT post_id FROM {$wpdb->postmeta}
                     WHERE meta_key = '_shopcommerce_sku' AND UPPER(TRIM(meta_value)) = %s
                     LIMIT 1",
                    $normalized_sku
                ));
            },
            'external_provider_sku' => function($sku) {
                global $wpdb;
                $normalized_sku = trim(strtoupper($sku));
                return $wpdb->get_var($wpdb->prepare(
                    "SELECT pm.post_id
                     FROM {$wpdb->postmeta} pm
                     INNER JOIN {$wpdb->postmeta} pm2 ON pm.post_id = pm2.post_id
                     WHERE pm.meta_key = '_sku' AND UPPER(TRIM(pm.meta_value)) = %s
                     AND pm2.meta_key = '_external_provider' AND pm2.meta_value = 'shopcommerce'
                     LIMIT 1",
                    $normalized_sku
                ));
            }
        ];

        foreach ($methods as $method_name => $method) {
            try {
                $product_id = $method($sku);
                if ($product_id && $product_id > 0) {
                    // Verify the product still exists and is a valid product
                    $product = wc_get_product($product_id);
                    if ($product && !is_wp_error($product)) {
                        $this->logger->debug('Duplicate found using method', [
                            'method' => $method_name,
                            'sku' => $sku,
                            'product_id' => $product_id
                        ]);
                        return [
                            'found' => true,
                            'product_id' => $product_id,
                            'method' => $method_name
                        ];
                    }
                }
            } catch (Exception $e) {
                $this->logger->warning('Error checking duplicates with method', [
                    'method' => $method_name,
                    'sku' => $sku,
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }

        return ['found' => false, 'product_id' => null, 'method' => null];
    }

    /**
     * Create product with enhanced duplicate prevention
     *
     * @param array $product_data Sanitized product data
     * @param string $brand Brand name
     * @return array Creation results
     */
    private function create_product_safely($product_data, $brand) {
        $results = [
            'success' => false,
            'product_id' => null,
            'error' => null,
        ];

        // Double-check for duplicates one more time before creation
        $sku = $product_data['Sku'];
        if (!empty($sku)) {
            $final_check = $this->check_for_duplicates($sku);
            if ($final_check['found']) {
                $this->logger->warning('Duplicate detected during final pre-creation check', [
                    'sku' => $sku,
                    'existing_product_id' => $final_check['product_id']
                ]);

                // Update the existing product instead
                $existing_product = wc_get_product($final_check['product_id']);
                if ($existing_product) {
                    $update_result = $this->update_product($existing_product, $product_data, $brand);
                    $update_result['action'] = 'updated';
                    return $update_result;
                }
            }
        }

        return $this->create_product($product_data, $brand);
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

        if (!$existing_product || !is_a($existing_product, 'WC_Product')) {
            throw new Exception('Invalid product object provided for update');
        }

        $product_id = $existing_product->get_id();
        $sku = $product_data['Sku'];

        // Log update attempt
        $this->logger->info('Starting product update', [
            'product_id' => $product_id,
            'sku' => $sku,
            'current_sku' => $existing_product->get_sku(),
            'name' => $product_data['Name'],
            'brand' => $brand
        ]);

        // Get mapped data
        $mapped_data = $this->map_product_data($product_data, $brand, $sku);

        // Verify this is the right product by double-checking SKUs
        $current_sku = $existing_product->get_sku();
        $external_sku = get_post_meta($product_id, '_shopcommerce_sku', true);

        if (!empty($sku)) {
            $skus_match = (
                $current_sku === $sku ||
                $external_sku === $sku ||
                trim(strtoupper($current_sku)) === trim(strtoupper($sku)) ||
                trim(strtoupper($external_sku)) === trim(strtoupper($sku))
            );

            if (!$skus_match) {
                $this->logger->warning('SKU mismatch during product update', [
                    'product_id' => $product_id,
                    'new_sku' => $sku,
                    'current_sku' => $current_sku,
                    'external_sku' => $external_sku
                ]);
            }
        }

        // Apply the mapped data to existing product
        $this->apply_product_data($existing_product, $mapped_data, $product_data, $brand);

        // Save the updated product
        $save_result = $existing_product->save();

        if ($save_result) {
            $this->logger->info('Successfully updated product', [
                'product_id' => $product_id,
                'sku' => $sku,
                'name' => $product_data['Name'],
                'brand' => $brand
            ]);
        } else {
            $this->logger->warning('Product save returned false during update', [
                'product_id' => $product_id,
                'sku' => $sku
            ]);
        }

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
        // Build enhanced description with XML attributes
        $description = $product_data['Description'];

        // Parse and add XML attributes if available
        if (!empty($product_data['xmlAttributes'])) {
            $xml_attributes = $this->helpers->parse_xml_attributes($product_data['xmlAttributes']);
            if (!empty($xml_attributes)) {
                $attributes_html = $this->helpers->format_xml_attributes_html($xml_attributes);
                $description .= $attributes_html;
            }
        }

        $mapped_data = [
            'name' => $product_data['Name'],
            'description' => $description,
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

        if (!empty($product_data['PartNum'])) {
            $mapped_data['meta_data']['_shopcommerce_part_num'] = $product_data['PartNum'];
        }

        if (!empty($product_data['Categoria'])) {
            $mapped_data['meta_data']['_shopcommerce_categoria'] = $product_data['Categoria'];
        }

        // Store ListaProductosBodega as JSON in metadata
        if (!empty($product_data['ListaProductosBodega']) && is_array($product_data['ListaProductosBodega'])) {
            $mapped_data['meta_data']['_shopcommerce_lista_productos_bodega'] = json_encode($product_data['ListaProductosBodega']);
        }

        // Store raw XML attributes JSON for future reference
        if (!empty($product_data['xmlAttributes'])) {
            $mapped_data['meta_data']['_shopcommerce_xml_attributes'] = $product_data['xmlAttributes'];
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

    /**
     * Find and merge duplicate products by SKU
     *
     * @param bool $dry_run If true, only report duplicates without merging
     * @return array Results of duplicate detection and merging
     */
    public function cleanup_duplicate_products($dry_run = true) {
        $this->logger->info('Starting duplicate product cleanup', ['dry_run' => $dry_run]);

        global $wpdb;

        // Find potential duplicates by SKU
        $duplicate_skus = $wpdb->get_col(
            "SELECT meta_value
             FROM {$wpdb->postmeta}
             WHERE meta_key = '_sku'
             AND meta_value != ''
             GROUP BY meta_value
             HAVING COUNT(DISTINCT post_id) > 1"
        );

        $results = [
            'total_duplicate_skus' => count($duplicate_skus),
            'duplicates_found' => [],
            'products_merged' => 0,
            'products_removed' => 0,
            'errors' => []
        ];

        foreach ($duplicate_skus as $sku) {
            try {
                // Get all products with this SKU
                $product_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT post_id
                     FROM {$wpdb->postmeta}
                     WHERE meta_key = '_sku' AND meta_value = %s
                     ORDER BY post_id ASC",
                    $sku
                ));

                // Find the most recently synced product (keep this one)
                $keep_product_id = null;
                $remove_product_ids = [];

                foreach ($product_ids as $product_id) {
                    $sync_date = get_post_meta($product_id, '_external_provider_sync_date', true);
                    if (!$sync_date || $sync_date > ($keep_product_id ? get_post_meta($keep_product_id, '_external_provider_sync_date', true) : '')) {
                        if ($keep_product_id) {
                            $remove_product_ids[] = $keep_product_id;
                        }
                        $keep_product_id = $product_id;
                    } else {
                        $remove_product_ids[] = $product_id;
                    }
                }

                if ($keep_product_id && !empty($remove_product_ids)) {
                    $duplicate_info = [
                        'sku' => $sku,
                        'keep_product_id' => $keep_product_id,
                        'remove_product_ids' => $remove_product_ids,
                        'keep_product_name' => get_the_title($keep_product_id),
                        'remove_product_names' => array_map('get_the_title', $remove_product_ids)
                    ];

                    $results['duplicates_found'][] = $duplicate_info;

                    $this->logger->warning('Found duplicate products', $duplicate_info);

                    if (!$dry_run) {
                        // Move to trash or permanently delete duplicate products
                        foreach ($remove_product_ids as $remove_id) {
                            $product = wc_get_product($remove_id);
                            if ($product) {
                                $result = $product->delete(true); // Force delete
                                if ($result) {
                                    $results['products_removed']++;
                                    $this->logger->info('Removed duplicate product', [
                                        'sku' => $sku,
                                        'removed_id' => $remove_id,
                                        'kept_id' => $keep_product_id
                                    ]);
                                } else {
                                    $results['errors'][] = "Failed to delete product ID {$remove_id} for SKU {$sku}";
                                }
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                $results['errors'][] = "Error processing SKU {$sku}: " . $e->getMessage();
                $this->logger->error('Error during duplicate cleanup', [
                    'sku' => $sku,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->logger->info('Duplicate cleanup completed', [
            'total_duplicates' => $results['total_duplicate_skus'],
            'products_removed' => $results['products_removed'],
            'errors_count' => count($results['errors'])
        ]);

        return $results;
    }
}