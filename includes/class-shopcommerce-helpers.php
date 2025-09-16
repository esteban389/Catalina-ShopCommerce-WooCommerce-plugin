<?php

/**
 * ShopCommerce Helpers Class
 *
 * Contains helper functions for WooCommerce operations, category management,
 * image handling, and other utility functions.
 *
 * @package ShopCommerce_Sync
 */
class ShopCommerce_Helpers
{
    /**
     * Logger instance
     */
    private $logger;

    /**
     * Constructor
     *
     * @param ShopCommerce_Logger $logger Logger instance
     */
    public function __construct($logger)
    {
        $this->logger = $logger;
    }

    /**
     * Find existing WooCommerce product by SKU with multiple lookup methods
     *
     * @param string $sku Product SKU to search for
     * @return WC_Product|null Found product object or null
     */
    public function get_product_by_sku($sku)
    {
        if (empty($sku)) {
            return null;
        }

        // Normalize SKU (trim, uppercase for comparison)
        $normalized_sku = trim(strtoupper($sku));

        // Method 1: Use WooCommerce's built-in function (fastest)
        $product_id = wc_get_product_id_by_sku($sku);
        if ($product_id) {
            $this->logger->debug('Found product by SKU using wc_get_product_id_by_sku', [
                'sku' => $sku,
                'product_id' => $product_id
            ]);
            return wc_get_product($product_id);
        }

        // Method 2: Case-insensitive match using direct DB query
        global $wpdb;
        $product_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM $wpdb->postmeta
             WHERE meta_key = '_sku' AND UPPER(TRIM(meta_value)) = %s
             LIMIT 1",
            $normalized_sku
        ));

        if ($product_id) {
            $this->logger->debug('Found product by SKU using case-insensitive query', [
                'sku' => $sku,
                'product_id' => $product_id
            ]);
            return wc_get_product($product_id);
        }

        // Method 3: Check external provider meta if available
        $product_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM $wpdb->postmeta
             WHERE meta_key = '_shopcommerce_sku' AND UPPER(TRIM(meta_value)) = %s
             LIMIT 1",
            $normalized_sku
        ));

        if ($product_id) {
            $this->logger->debug('Found product by external SKU meta', [
                'sku' => $sku,
                'product_id' => $product_id
            ]);
            return wc_get_product($product_id);
        }

        $this->logger->debug('Product not found by SKU', ['sku' => $sku]);
        return null;
    }

    /**
     * Get or create WooCommerce category by name
     *
     * @param string $category_name Category name
     * @return int|null Category ID or null on failure
     */
    public function get_or_create_category($category_name)
    {
        if (empty($category_name)) {
            return null;
        }

        // Check if category exists
        $term = get_term_by('name', $category_name, 'product_cat');

        if ($term && !is_wp_error($term)) {
            $this->logger->debug('Found existing category', ['category_name' => $category_name, 'term_id' => $term->term_id]);
            return $term->term_id;
        }

        // Create new category
        $result = wp_insert_term($category_name, 'product_cat');

        if (!is_wp_error($result)) {
            $this->logger->info('Created new category', [
                'category_name' => $category_name,
                'term_id' => $result['term_id']
            ]);
            return $result['term_id'];
        }

        $this->logger->error('Failed to create category', [
            'category_name' => $category_name,
            'error' => $result->get_error_message()
        ]);
        return null;
    }

    /**
     * Sync plugin categories from ShopCommerce API response
     *
     * @param array $api_categories Categories from API response
     * @return array Results of category sync operation
     */
    public function sync_categories_from_api($api_categories) {
        if (!is_array($api_categories)) {
            $this->logger->error('Invalid categories data from API', ['data_type' => gettype($api_categories)]);
            return [
                'success' => false,
                'created' => 0,
                'skipped' => 0,
                'errors' => ['Invalid categories data from API']
            ];
        }

        // Get config manager to access category functions
        $config = isset($GLOBALS['shopcommerce_config']) ? $GLOBALS['shopcommerce_config'] : null;
        if (!$config) {
            $this->logger->error('Config manager not available for category sync');
            return [
                'success' => false,
                'created' => 0,
                'skipped' => 0,
                'errors' => ['Config manager not available']
            ];
        }

        $results = [
            'success' => true,
            'created' => 0,
            'skipped' => 0,
            'errors' => []
        ];

        $this->logger->info('Starting category sync from API', ['category_count' => count($api_categories)]);

        foreach ($api_categories as $category) {
            if (!is_array($category) || !isset($category['Categoria'])) {
                $this->logger->warning('Skipping invalid category data', ['category' => $category]);
                $results['skipped']++;
                continue;
            }

            $category_name = trim($category['Categoria']);
            $category_slug = isset($category['slugcategory']) ? trim($category['slugcategory']) : '';
            $category_code = isset($category['CodigoCategoria']) ? intval($category['CodigoCategoria']) : 0;

            if (empty($category_name)) {
                $this->logger->warning('Skipping category with empty name', ['category' => $category]);
                $results['skipped']++;
                continue;
            }

            if ($category_code <= 0) {
                $this->logger->warning('Skipping category with invalid code', ['category' => $category]);
                $results['skipped']++;
                continue;
            }

            // Check if category already exists by code or name
            global $wpdb;
            $categories_table = $wpdb->prefix . 'shopcommerce_categories';

            $existing_category = $wpdb->get_row($wpdb->prepare(
                "SELECT id, name, code FROM {$categories_table} WHERE code = %d OR name = %s",
                $category_code,
                $category_name
            ));

            if ($existing_category) {
                $this->logger->debug('Category already exists, skipping', [
                    'category_name' => $category_name,
                    'category_code' => $category_code,
                    'existing_id' => $existing_category->id,
                    'existing_name' => $existing_category->name,
                    'existing_code' => $existing_category->code
                ]);
                $results['skipped']++;
                continue;
            }

            // Use config manager's create_category method
            $description = sprintf('Category synced from API: %s (Slug: %s)', $category_name, $category_slug);
            $category_id = $config->create_category($category_name, $category_code, $description);

            if ($category_id) {
                $this->logger->info('Created new plugin category from API', [
                    'category_name' => $category_name,
                    'category_slug' => $category_slug,
                    'category_code' => $category_code,
                    'category_id' => $category_id
                ]);
                $results['created']++;

                // Store additional metadata as term meta for future reference
                if (!empty($category_slug)) {
                    update_term_meta($category_id, 'shopcommerce_category_slug', $category_slug);
                }
            } else {
                $this->logger->error('Failed to create plugin category from API', [
                    'category_name' => $category_name,
                    'category_slug' => $category_slug,
                    'category_code' => $category_code
                ]);
                $results['errors'][] = "Failed to create '{$category_name}' (Code: {$category_code})";
            }
        }

        $this->logger->info('Plugin category sync completed', [
            'created' => $results['created'],
            'skipped' => $results['skipped'],
            'errors' => count($results['errors'])
        ]);

        return $results;
    }

    /**
     * Attach product image from URL
     *
     * @param string $image_url Image URL
     * @param string $product_name Product name for image title
     * @return int|null Attachment ID or null on failure
     */
    public function attach_product_image($image_url, $product_name)
    {
        if (empty($image_url) || !filter_var($image_url, FILTER_VALIDATE_URL)) {
            $this->logger->warning('Invalid image URL', ['image_url' => $image_url]);
            return null;
        }

        // Check if image already exists
        $existing_image = $this->get_image_by_url($image_url);
        if ($existing_image) {
            $this->logger->debug('Using existing image attachment', ['image_url' => $image_url, 'attachment_id' => $existing_image]);
            return $existing_image;
        }

        // Download image
        $this->logger->debug('Downloading product image', ['image_url' => $image_url]);
        $image_data = wp_remote_get($image_url);

        if (is_wp_error($image_data)) {
            $this->logger->error('Failed to download image', [
                'image_url' => $image_url,
                'error' => $image_data->get_error_message()
            ]);
            return null;
        }

        $image_body = wp_remote_retrieve_body($image_data);
        if (empty($image_body)) {
            $this->logger->error('Empty image body', ['image_url' => $image_url]);
            return null;
        }

        // Get file info
        $file_info = wp_check_filetype(basename($image_url), [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
        ]);

        if (empty($file_info['type'])) {
            $this->logger->error('Invalid image type', [
                'image_url' => $image_url,
                'file_info' => $file_info,
            ]);
            return null;
        }

        // Upload image
        $upload = wp_upload_bits(basename($image_url), null, $image_body);

        if (is_wp_error($upload)) {
            $this->logger->error('Failed to upload image', [
                'image_url' => $image_url,
                'error' => $upload->get_error_message()
            ]);
            return null;
        }

        // Now check file type properly
        $file_info = wp_check_filetype_and_ext($upload['file'], basename($upload['file']));
        if (empty($file_info['type']) || !in_array($file_info['type'], ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'])) {
            $this->logger->error('Invalid image type after upload', [
                'image_url' => $image_url,
                'file_info' => $file_info,
                'upload' => $upload,
            ]);
            @unlink($upload['file']);  // Clean up invalid file
            return null;
        }

        // Create attachment
        $attachment = [
            'post_mime_type' => $file_info['type'],
            'post_title' => sanitize_file_name($product_name),
            'post_content' => '',
            'post_status' => 'inherit'
        ];

        $attachment_id = wp_insert_attachment($attachment, $upload['file']);

        if (is_wp_error($attachment_id)) {
            $this->logger->error('Failed to create attachment', [
                'image_url' => $image_url,
                'error' => $attachment_id->get_error_message()
            ]);
            return null;
        }

        // Generate attachment metadata
        require_once (ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        wp_update_attachment_metadata($attachment_id, $attach_data);

        // Store image URL for future reference
        update_post_meta($attachment_id, '_external_image_url', $image_url);

        $this->logger->info('Attached product image', [
            'image_url' => $image_url,
            'product_name' => $product_name,
            'attachment_id' => $attachment_id
        ]);

        return $attachment_id;
    }

    /**
     * Get existing image attachment by URL
     *
     * @param string $image_url Image URL to search for
     * @return int|null Attachment ID or null if not found
     */
    public function get_image_by_url($image_url)
    {
        global $wpdb;

        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_external_image_url' AND meta_value = %s",
            $image_url
        ));

        return $attachment_id ? intval($attachment_id) : null;
    }

    /**
     * Extract SKU from product data (supports multiple field names)
     *
     * @param array $product_data Product data from API
     * @return string|null Extracted SKU or null if not found
     */
    public function extract_sku($product_data)
    {
        if (!is_array($product_data)) {
            return null;
        }

        // Try multiple possible SKU field names
        $sku_fields = ['Sku', 'SKU', 'PartNum', 'Codigo', 'ProductCode'];

        foreach ($sku_fields as $field) {
            if (isset($product_data[$field]) && !empty(trim($product_data[$field]))) {
                $sku = trim($product_data[$field]);
                $this->logger->debug('Extracted SKU from field', [
                    'field' => $field,
                    'sku' => $sku
                ]);
                return $sku;
            }
        }

        $this->logger->debug('No SKU found in product data', ['product_data' => array_keys($product_data)]);
        return null;
    }

    /**
     * Generate cache key for product
     *
     * @param array $product_data Product data
     * @param string|null $sku Product SKU
     * @return string Cache key
     */
    public function generate_cache_key($product_data, $sku = null)
    {
        if (!empty($sku)) {
            return $sku;
        }

        // Use product name as fallback if no SKU
        if (isset($product_data['Name']) && !empty($product_data['Name'])) {
            return 'name_' . md5($product_data['Name']);
        }

        // Use unique ID as last resort
        return 'unique_' . uniqid('no_sku_', true);
    }

    /**
     * Sanitize and validate product data
     *
     * @param array $product_data Raw product data
     * @return array Sanitized product data
     */
    public function sanitize_product_data($product_data)
    {
        if (!is_array($product_data)) {
            return [];
        }

        $sanitized = [];

        // Basic fields
        $sanitized['Name'] = isset($product_data['Name']) ? sanitize_text_field($product_data['Name']) : '';
        $sanitized['Description'] = isset($product_data['Description']) ? wp_kses_post($product_data['Description']) : '';

        // Price
        if (isset($product_data['precio']) && is_numeric($product_data['precio'])) {
            $sanitized['precio'] = floatval($product_data['precio']);
        } else {
            $sanitized['precio'] = null;
        }

        // Quantity
        if (isset($product_data['Quantity']) && is_numeric($product_data['Quantity'])) {
            $sanitized['Quantity'] = intval($product_data['Quantity']);
        } else {
            $sanitized['Quantity'] = 0;
        }

        // SKU
        $sanitized['Sku'] = $this->extract_sku($product_data);

        // Other fields
        $sanitized['Marks'] = isset($product_data['Marks']) ? sanitize_text_field($product_data['Marks']) : '';
        $sanitized['PartNum'] = isset($product_data['PartNum']) ? sanitize_text_field($product_data['PartNum']) : '';
        $sanitized['Categoria'] = isset($product_data['Categoria']) ? sanitize_text_field($product_data['Categoria']) : '';
        $sanitized['CategoriaDescripcion'] = isset($product_data['CategoriaDescripcion']) ? sanitize_text_field($product_data['CategoriaDescripcion']) : '';

        // Handle ListaProductosBodega (array of objects)
        if (isset($product_data['ListaProductosBodega']) && is_array($product_data['ListaProductosBodega'])) {
            $sanitized['ListaProductosBodega'] = [];
            foreach ($product_data['ListaProductosBodega'] as $bodega_item) {
                if (is_array($bodega_item)) {
                    $sanitized_bodega_item = [];
                    foreach ($bodega_item as $key => $value) {
                        // Sanitize keys and values appropriately
                        $sanitized_key = sanitize_key($key);
                        if (is_numeric($value)) {
                            $sanitized_bodega_item[$sanitized_key] = floatval($value);
                        } elseif (is_string($value)) {
                            $sanitized_bodega_item[$sanitized_key] = sanitize_text_field($value);
                        } elseif (is_bool($value)) {
                            $sanitized_bodega_item[$sanitized_key] = (bool)$value;
                        } else {
                            $sanitized_bodega_item[$sanitized_key] = $value;
                        }
                    }
                    $sanitized['ListaProductosBodega'][] = $sanitized_bodega_item;
                }
            }
        } else {
            $sanitized['ListaProductosBodega'] = [];
        }

        // Images
        if (isset($product_data['Imagenes']) && is_array($product_data['Imagenes']) && !empty($product_data['Imagenes'])) {
            $sanitized['Imagenes'] = array_filter($product_data['Imagenes'], 'filter_var');
            $sanitized['Imagenes'] = array_map('sanitize_url', $sanitized['Imagenes']);
        } else {
            $sanitized['Imagenes'] = [];
        }

        // XML Attributes (JSON string)
        if (isset($product_data['xmlAttributes']) && !empty($product_data['xmlAttributes'])) {
            $sanitized['xmlAttributes'] = $product_data['xmlAttributes'];
        } else {
            $sanitized['xmlAttributes'] = null;
        }

        return $sanitized;
    }

    /**
     * Get ShopCommerce PartNum from product
     *
     * @param int|WC_Product $product Product ID or product object
     * @return string PartNum value
     */
    public function get_product_part_num($product) {
        if (is_numeric($product)) {
            $product = wc_get_product($product);
        }

        if (!$product) {
            return '';
        }

        return $product->get_meta('_shopcommerce_part_num', true);
    }

    /**
     * Get ShopCommerce ListaProductosBodega from product
     *
     * @param int|WC_Product $product Product ID or product object
     * @return array Decoded ListaProductosBodega array
     */
    public function get_product_lista_productos_bodega($product) {
        if (is_numeric($product)) {
            $product = wc_get_product($product);
        }

        if (!$product) {
            return [];
        }

        $bodega_json = $product->get_meta('_shopcommerce_lista_productos_bodega', true);
        if (empty($bodega_json)) {
            return [];
        }

        $bodega_data = json_decode($bodega_json, true);
        return is_array($bodega_data) ? $bodega_data : [];
    }

    /**
     * Get product warehouse stock information
     *
     * @param int|WC_Product $product Product ID or product object
     * @return array Warehouse stock information
     */
    public function get_product_warehouse_stock($product) {
        $bodega_data = $this->get_product_lista_productos_bodega($product);
        $warehouse_info = [];

        foreach ($bodega_data as $bodega_item) {
            if (isset($bodega_item['Bodega']) && isset($bodega_item['Stock'])) {
                $warehouse_info[] = [
                    'warehouse' => $bodega_item['Bodega'],
                    'stock' => intval($bodega_item['Stock']),
                    'location' => $bodega_item['Ubicacion'] ?? '',
                    'available' => ($bodega_item['Disponible'] ?? true) === true,
                ];
            }
        }

        return $warehouse_info;
    }

    /**
     * Get total stock across all warehouses
     *
     * @param int|WC_Product $product Product ID or product object
     * @return int Total stock across all warehouses
     */
    public function get_total_warehouse_stock($product) {
        $warehouse_info = $this->get_product_warehouse_stock($product);
        $total_stock = 0;

        foreach ($warehouse_info as $warehouse) {
            $total_stock += $warehouse['stock'];
        }

        return $total_stock;
    }

    /**
     * Parse XML attributes JSON and extract attributes list
     *
     * @param string $xml_attributes_json JSON string containing XML attributes
     * @return array List of formatted attributes [name => value]
     */
    public function parse_xml_attributes($xml_attributes_json)
    {
        if (empty($xml_attributes_json)) {
            return [];
        }

        $attributes = [];

        try {
            // Decode JSON
            $decoded = json_decode($xml_attributes_json, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->warning('Failed to decode XML attributes JSON', [
                    'error' => json_last_error_msg(),
                    'json' => $xml_attributes_json
                ]);
                return [];
            }

            // Navigate through the expected structure
            if (isset($decoded['ListaAtributos']['Atributos']['attributecs'])) {
                $attributecs = $decoded['ListaAtributos']['Atributos']['attributecs'];

                // Handle single attribute or array of attributes
                if (isset($attributecs['AttributeName'])) {
                    // Single attribute
                    $attribute_name = $attributecs['AttributeName'];
                    $attribute_value = $attributecs['AttributeValue'];

                    if (!empty($attribute_name)) {
                        $attributes[$attribute_name] = $attribute_value;
                    }
                } elseif (is_array($attributecs)) {
                    // Multiple attributes
                    foreach ($attributecs as $attribute) {
                        if (isset($attribute['AttributeName']) && !empty($attribute['AttributeName'])) {
                            $attributes[$attribute['AttributeName']] = $attribute['AttributeValue'] ?? null;
                        }
                    }
                }
            }

            $this->logger->debug('Parsed XML attributes', [
                'original_json' => $xml_attributes_json,
                'parsed_attributes' => $attributes
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error parsing XML attributes', [
                'error' => $e->getMessage(),
                'json' => $xml_attributes_json
            ]);
            return [];
        }

        return $attributes;
    }

    /**
     * Format XML attributes as HTML list for product description
     *
     * @param array $attributes Parsed attributes from parse_xml_attributes()
     * @return string Formatted HTML list
     */
    public function format_xml_attributes_html($attributes)
    {
        if (empty($attributes)) {
            return '';
        }

        $html = '<div class="shopcommerce-product-attributes">';
        $html .= '<h4>Especificaciones</h4>';
        $html .= '<ul class="product-specs">';

        foreach ($attributes as $name => $value) {
            $html .= '<li>';
            $html .= '<strong>' . esc_html($name) . ':</strong> ';
            $html .= !empty($value) ? esc_html($value) : '<em>N/A</em>';
            $html .= '</li>';
        }

        $html .= '</ul>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Check if WooCommerce is active
     *
     * @return bool True if WooCommerce is active
     */
    public function is_woocommerce_active()
    {
        return in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')));
    }

    /**
     * Test XML attributes parsing with sample data
     *
     * @return array Test results
     */
    public function test_xml_attributes_parsing()
    {
        $test_json = '{"ListaAtributos":{"Atributos":{"attributecs":{"AttributeName":"ALTURA DEL PAQUETE DE ENVÍO","AttributeValue":null}}}}';

        $this->logger->info('Testing XML attributes parsing', ['test_json' => $test_json]);

        $parsed = $this->parse_xml_attributes($test_json);
        $formatted = $this->format_xml_attributes_html($parsed);

        $results = [
            'original_json' => $test_json,
            'parsed_attributes' => $parsed,
            'formatted_html' => $formatted,
            'parse_success' => !empty($parsed),
            'format_success' => !empty($formatted)
        ];

        $this->logger->info('XML attributes parsing test results', $results);

        return $results;
    }

    /**
     * Get sync configuration (now uses dynamic config)
     *
     * @return array Sync configuration
     */
    public function get_sync_config()
    {
        // Use dynamic configuration if available, fallback to hardcoded for backward compatibility
        if (isset($GLOBALS['shopcommerce_config'])) {
            return $GLOBALS['shopcommerce_config']->get_sync_config();
        }

        // Fallback to hardcoded configuration
        return [
            // Category Codes reference
            'CATEGORIA_ACCESORIOS' => 1,
            'CATEGORIA_COMPUTADORES' => 7,  // PCs, Portátiles, Workstations
            'CATEGORIA_IMPRESION' => 12,
            'CATEGORIA_VIDEO' => 14,  // Monitores
            'CATEGORIA_SERVIDORES' => 18,
            'common_corp_categories' => [
                7,  // pcs, portátiles, wkst
                18,  // servidores volumen/valor
                1,  // accesorios
                14,  // monitores
                12,  // impresión
            ],
            'brand_config' => [
                'HP INC' => [1, 7, 12, 14, 18],
                'DELL' => [1, 7, 12, 14, 18],
                'LENOVO' => [1, 7, 12, 14, 18],
                'APPLE' => [1, 7],  // accesorios y portátiles
                'ASUS' => [7],  // portátiles corporativo
                'BOSE' => [],  // todas las categorías de BOSE
                'EPSON' => [],  // todas las categorías de EPSON
                'JBL' => [],  // include if provider exposes JBL; ignored if not found
            ]
        ];
    }

    /**
     * Build jobs list from configuration
     *
     * @return array List of sync jobs
     */
    public function build_jobs_list()
    {
        // Use dynamic configuration if available, fallback to hardcoded
        if (isset($GLOBALS['shopcommerce_config'])) {
            return $GLOBALS['shopcommerce_config']->build_jobs_list();
        }

        // Fallback to hardcoded configuration
        $config = $this->get_sync_config();
        $jobs = [];

        foreach ($config['brand_config'] as $brand => $categories) {
            $jobs[] = [
                'brand' => $brand,
                'categories' => $categories,  // empty array means all categories
            ];
        }

        return $jobs;
    }

    /**
     * Get product sync statistics
     *
     * @return array Product sync statistics
     */
    public function get_sync_statistics()
    {
        global $wpdb;

        // Count products synced by this plugin (excluding trashed)
        $synced_products = $wpdb->get_var(
            "SELECT COUNT(DISTINCT pm.post_id)
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = '_external_provider'
             AND pm.meta_value = 'shopcommerce'
             AND p.post_type = 'product'
             AND p.post_status != 'trash'"
        );

        // Count products by brand (excluding trashed)
        $products_by_brand = $wpdb->get_results(
            "SELECT pm.meta_value as brand, COUNT(*) as count
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = '_external_provider_brand'
             AND pm.meta_value != ''
             AND p.post_type = 'product'
             AND p.post_status != 'trash'
             GROUP BY pm.meta_value
             ORDER BY count DESC"
        );

        // Get recent sync activity (excluding trashed)
        $recent_syncs = $wpdb->get_results(
            "SELECT pm.post_id, pm.meta_value as sync_date
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = '_external_provider_sync_date'
             AND p.post_type = 'product'
             AND p.post_status != 'trash'
             ORDER BY pm.meta_value DESC
             LIMIT 10"
        );

        return [
            'total_synced_products' => intval($synced_products),
            'products_by_brand' => $products_by_brand,
            'recent_syncs' => $recent_syncs,
        ];
    }

    /**
     * Get external provider products with filtering
     *
     * @param array $args Filter arguments
     * @return array Products data
     */
    public function get_external_provider_products($args = [])
    {
        global $wpdb;

        $defaults = [
            'search' => '',
            'status' => 'all',
            'brand' => '',
            'limit' => 20,
            'offset' => 0,
            'orderby' => 'date',
            'order' => 'DESC'
        ];

        $args = wp_parse_args($args, $defaults);

        $where_clauses = ["pm.meta_key = '_external_provider'", "pm.meta_value = 'shopcommerce'"];
        $join_clauses = ["INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id"];

        // Status filter
        if ($args['status'] !== 'all') {
            $where_clauses[] = $wpdb->prepare('p.post_status = %s', $args['status']);
        } else {
            // Exclude trash by default when showing 'all'
            $where_clauses[] = "p.post_status != 'trash'";
        }

        // Search filter
        if (!empty($args['search'])) {
            $search_like = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_clauses[] = $wpdb->prepare('(p.post_title LIKE %s OR p.post_content LIKE %s)', $search_like, $search_like);
        }

        // Join with postmeta for additional product data
        $join_clauses[] = "LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_external_provider_brand'";
        $join_clauses[] = "LEFT JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = '_shopcommerce_sku'";
        $join_clauses[] = "LEFT JOIN {$wpdb->postmeta} pm4 ON p.ID = pm4.post_id AND pm4.meta_key = '_external_provider_sync_date'";
        $join_clauses[] = "LEFT JOIN {$wpdb->postmeta} pm5 ON p.ID = pm5.post_id AND pm5.meta_key = '_price'";
        $join_clauses[] = "LEFT JOIN {$wpdb->postmeta} pm6 ON p.ID = pm6.post_id AND pm6.meta_key = '_stock'";
        $join_clauses[] = "LEFT JOIN {$wpdb->postmeta} pm7 ON p.ID = pm7.post_id AND pm7.meta_key = '_stock_status'";

        // Brand filter
        if (!empty($args['brand'])) {
            $where_clauses[] = $wpdb->prepare('pm2.meta_value = %s', $args['brand']);
        }

        // Build query
        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        $join_sql = implode(' ', $join_clauses);

        // Order by
        $orderby = 'p.post_' . $args['orderby'];
        if ($args['orderby'] === 'price') {
            $orderby = 'pm7.meta_value';
        } elseif ($args['orderby'] === 'sync_date') {
            $orderby = 'pm4.meta_value';
        }
        $order = $args['order'];

        $sql = "SELECT DISTINCT p.ID,
                        p.post_title as name,
                        p.post_status as status,
                        pm3.meta_value as sku,
                        pm2.meta_value as brand,
                        pm5.meta_value as price,
                        pm6.meta_value as stock_quantity,
                        pm7.meta_value as stock_status,
                        pm4.meta_value as sync_date
                 FROM {$wpdb->posts} p
                 $join_sql
                 $where_sql
                 ORDER BY $orderby $order
                 LIMIT %d OFFSET %d";

        $this->logger->debug('Products query SQL', ['sql' => $sql, 'args' => $args]);

        $results = $wpdb->get_results($wpdb->prepare($sql, $args['limit'], $args['offset']));

        $this->logger->debug('Products query results', ['count' => count($results), 'results' => $results]);

        $products = [];
        foreach ($results as $row) {
            $products[] = [
                'id' => intval($row->ID),
                'name' => $row->name,
                'status' => $row->status,
                'sku' => $row->sku,
                'brand' => $row->brand,
                'price' => floatval($row->price) ?: 0,
                'stock_quantity' => intval($row->stock_quantity) ?: 0,
                'stock_status' => $row->stock_status ?: 'instock',
                'sync_date' => $row->sync_date
            ];
        }

        return $products;
    }

    /**
     * Get external provider products count
     *
     * @param array $args Filter arguments
     * @return int Number of products
     */
    public function get_external_provider_products_count($args = [])
    {
        global $wpdb;

        $defaults = [
            'search' => '',
            'status' => 'all',
            'brand' => ''
        ];

        $args = wp_parse_args($args, $defaults);

        $where_clauses = ["pm.meta_key = '_external_provider'", "pm.meta_value = 'shopcommerce'"];
        $join_clauses = ["INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id"];

        // Status filter
        if ($args['status'] !== 'all') {
            $where_clauses[] = $wpdb->prepare('p.post_status = %s', $args['status']);
        } else {
            // Exclude trash by default when showing 'all'
            $where_clauses[] = "p.post_status != 'trash'";
        }

        // Search filter
        if (!empty($args['search'])) {
            $search_like = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_clauses[] = $wpdb->prepare('(p.post_title LIKE %s OR p.post_content LIKE %s)', $search_like, $search_like);
        }

        // Join with postmeta for brand data (always include for consistency)
        $join_clauses[] = "LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_external_provider_brand'";

        // Brand filter
        if (!empty($args['brand'])) {
            $where_clauses[] = $wpdb->prepare('pm2.meta_value = %s', $args['brand']);
        }

        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        $join_sql = implode(' ', $join_clauses);

        $sql = "SELECT COUNT(DISTINCT p.ID)
                 FROM {$wpdb->posts} p
                 $join_sql
                 $where_sql";

        $this->logger->debug('Products count query SQL', ['sql' => $sql]);

        $count = intval($wpdb->get_var($sql));

        $this->logger->debug('Products count query result', ['count' => $count]);

        return $count;
    }

    /**
     * Get available brands from external provider products
     *
     * @return array List of brands
     */
    public function get_external_provider_brands()
    {
        global $wpdb;

        $brands = $wpdb->get_col(
            "SELECT DISTINCT pm.meta_value as brand
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = '_external_provider_brand'
             AND pm.meta_value != ''
             AND p.post_status != 'trash'
             ORDER BY pm.meta_value"
        );

        return $brands;
    }
}
