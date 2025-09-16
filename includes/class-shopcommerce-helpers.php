<?php

/**
 * ShopCommerce Helpers Class
 *
 * Contains helper functions for WooCommerce operations, category management,
 * image handling, and other utility functions.
 *
 * @package ShopCommerce_Sync
 */

class ShopCommerce_Helpers {

    /**
     * Logger instance
     */
    private $logger;

    /**
     * Constructor
     *
     * @param ShopCommerce_Logger $logger Logger instance
     */
    public function __construct($logger) {
        $this->logger = $logger;
    }

    /**
     * Find existing WooCommerce product by SKU with multiple lookup methods
     *
     * @param string $sku Product SKU to search for
     * @return WC_Product|null Found product object or null
     */
    public function get_product_by_sku($sku) {
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
    public function get_or_create_category($category_name) {
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
     * Attach product image from URL
     *
     * @param string $image_url Image URL
     * @param string $product_name Product name for image title
     * @return int|null Attachment ID or null on failure
     */
    public function attach_product_image($image_url, $product_name) {
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
        $file_info = wp_check_filetype_and_ext(basename($image_url), 'image');
        if (!$file_info['type'] || !in_array($file_info['type'], ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'])) {
            $this->logger->error('Invalid image type', [
                'image_url' => $image_url,
                'file_info' => $file_info
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
        require_once(ABSPATH . 'wp-admin/includes/image.php');
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
    public function get_image_by_url($image_url) {
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
    public function extract_sku($product_data) {
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
    public function generate_cache_key($product_data, $sku = null) {
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
    public function sanitize_product_data($product_data) {
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
        $sanitized['Categoria'] = isset($product_data['Categoria']) ? sanitize_text_field($product_data['Categoria']) : '';
        $sanitized['CategoriaDescripcion'] = isset($product_data['CategoriaDescripcion']) ? sanitize_text_field($product_data['CategoriaDescripcion']) : '';

        // Images
        if (isset($product_data['Imagenes']) && is_array($product_data['Imagenes']) && !empty($product_data['Imagenes'])) {
            $sanitized['Imagenes'] = array_filter($product_data['Imagenes'], 'filter_var');
            $sanitized['Imagenes'] = array_map('sanitize_url', $sanitized['Imagenes']);
        } else {
            $sanitized['Imagenes'] = [];
        }

        return $sanitized;
    }

    /**
     * Check if WooCommerce is active
     *
     * @return bool True if WooCommerce is active
     */
    public function is_woocommerce_active() {
        return in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')));
    }

    /**
     * Get sync configuration
     *
     * @return array Sync configuration
     */
    public function get_sync_config() {
        return [
            // Category Codes reference
            'CATEGORIA_ACCESORIOS' => 1,
            'CATEGORIA_COMPUTADORES' => 7,  // PCs, Portátiles, Workstations
            'CATEGORIA_IMPRESION' => 12,
            'CATEGORIA_VIDEO' => 14,  // Monitores
            'CATEGORIA_SERVIDORES' => 18,

            'common_corp_categories' => [
                7,  // pcs, portátiles, wkst
                18, // servidores volumen/valor
                1,  // accesorios
                14, // monitores
                12, // impresión
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
    public function build_jobs_list() {
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
    public function get_sync_statistics() {
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
}