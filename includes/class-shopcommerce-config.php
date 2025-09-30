<?php

/**
 * ShopCommerce Configuration Manager Class
 *
 * Handles dynamic brand and category configuration with database storage.
 *
 * @package ShopCommerce_Sync
 */

class ShopCommerce_Config {

    /**
     * Logger instance
     */
    private $logger;

    /**
     * Database table names
     */
    private $brands_table;
    private $categories_table;
    private $brand_categories_table;

    /**
     * Constructor
     *
     * @param ShopCommerce_Logger $logger Logger instance
     */
    public function __construct($logger) {
        global $wpdb;

        $this->logger = $logger;
        $this->brands_table = $wpdb->prefix . 'shopcommerce_brands';
        $this->categories_table = $wpdb->prefix . 'shopcommerce_categories';
        $this->brand_categories_table = $wpdb->prefix . 'shopcommerce_brand_categories';

        // Tables are now created by the central migrator
    }

  
  
    /**
     * Get all brands
     *
     * @param bool $active_only Only return active brands
     * @return array List of brands
     */
    public function get_brands($active_only = true) {
        global $wpdb;

        $where = $active_only ? "WHERE is_active = 1" : "";

        return $wpdb->get_results("SELECT * FROM {$this->brands_table} $where ORDER BY name ASC");
    }

    /**
     * Get a single brand by ID
     *
     * @param int $brand_id Brand ID
     * @return object|null Brand object or null if not found
     */
    public function get_brand($brand_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->brands_table} WHERE id = %d",
            $brand_id
        ));
    }

    /**
     * Get all categories
     *
     * @param bool $active_only Only return active categories
     * @return array List of categories
     */
    public function get_categories($active_only = true) {
        global $wpdb;

        $where = $active_only ? "WHERE is_active = 1" : "";

        return $wpdb->get_results("SELECT * FROM {$this->categories_table} $where ORDER BY code ASC");
    }

    /**
     * Get categories for a specific brand
     *
     * @param int $brand_id Brand ID
     * @return array List of categories for the brand
     */
    public function get_brand_categories($brand_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT c.* FROM {$this->categories_table} c
             INNER JOIN {$this->brand_categories_table} bc ON c.id = bc.category_id
             WHERE bc.brand_id = %d AND c.is_active = 1
             ORDER BY c.code ASC",
            $brand_id
        ));
    }

    /**
     * Check if a brand has all categories (empty array means all)
     *
     * @param int $brand_id Brand ID
     * @return bool True if brand has all categories
     */
    public function brand_has_all_categories($brand_id) {
        global $wpdb;

        $total_categories = $wpdb->get_var("SELECT COUNT(*) FROM {$this->categories_table} WHERE is_active = 1");
        $brand_category_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->brand_categories_table} bc
             INNER JOIN {$this->categories_table} c ON bc.category_id = c.id
             WHERE bc.brand_id = %d AND c.is_active = 1",
            $brand_id
        ));

        return $brand_category_count == $total_categories;
    }

    /**
     * Create a new brand
     *
     * @param string $name Brand name
     * @param string $description Brand description
     * @return int|false Brand ID or false on failure
     */
    public function create_brand($name, $description = '') {
        global $wpdb;

        $slug = sanitize_title($name);

        // Check if brand already exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->brands_table} WHERE slug = %s",
            $slug
        ));

        if ($exists) {
            $this->logger->warning('Brand already exists', ['name' => $name, 'slug' => $slug]);
            return false;
        }

        $result = $wpdb->insert(
            $this->brands_table,
            [
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
            ]
        );

        if ($result) {
            $brand_id = $wpdb->insert_id;
            $this->logger->info('Created new brand', ['brand_id' => $brand_id, 'name' => $name]);

            // Automatically assign all categories to new brand
            $categories = $this->get_categories();
            foreach ($categories as $category) {
                $wpdb->insert(
                    $this->brand_categories_table,
                    [
                        'brand_id' => $brand_id,
                        'category_id' => $category->id,
                    ]
                );
            }

            return $brand_id;
        }

        return false;
    }

    /**
     * Create a new category
     *
     * @param string $name Category name
     * @param int $code Category code
     * @param string $description Category description
     * @param float $markup_percentage Markup percentage (default 15.00)
     * @return int|false Category ID or false on failure
     */
    public function create_category($name, $code, $description = '', $markup_percentage = 15.00) {
        global $wpdb;

        // Check if category code already exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->categories_table} WHERE code = %d",
            $code
        ));

        if ($exists) {
            $this->logger->warning('Category code already exists', ['name' => $name, 'code' => $code]);
            return false;
        }

        // Define default category codes that should be active
        $default_category_codes = [1, 7, 12, 14, 18];

        // Set active status based on whether this is a default category
        $is_active = in_array($code, $default_category_codes) ? 1 : 0;

        $result = $wpdb->insert(
            $this->categories_table,
            [
                'name' => $name,
                'code' => $code,
                'description' => $description,
                'is_active' => $is_active,
                'markup_percentage' => $markup_percentage,
            ]
        );

        if ($result) {
            $category_id = $wpdb->insert_id;
            $this->logger->info('Created new category', ['category_id' => $category_id, 'name' => $name, 'code' => $code, 'is_active' => $is_active]);
            return $category_id;
        }

        return false;
    }

    /**
     * Update a brand
     *
     * @param int $brand_id Brand ID
     * @param string $name Brand name
     * @param string $description Brand description
     * @return bool Success status
     */
    public function update_brand($brand_id, $name, $description = '') {
        global $wpdb;

        // Check if brand exists
        $existing_brand = $wpdb->get_row($wpdb->prepare(
            "SELECT id, name, slug FROM {$this->brands_table} WHERE id = %d",
            $brand_id
        ));

        if (!$existing_brand) {
            $this->logger->warning('Brand not found for update', ['brand_id' => $brand_id]);
            return false;
        }

        // Generate new slug if name changed
        $new_slug = sanitize_title($name);
        if ($new_slug !== $existing_brand->slug) {
            // Check if new slug conflicts with another brand
            $slug_conflict = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->brands_table} WHERE slug = %s AND id != %d",
                $new_slug,
                $brand_id
            ));

            if ($slug_conflict) {
                $this->logger->warning('Brand slug conflict during update', [
                    'brand_id' => $brand_id,
                    'new_name' => $name,
                    'new_slug' => $new_slug
                ]);
                return false;
            }
        }

        $result = $wpdb->update(
            $this->brands_table,
            [
                'name' => $name,
                'slug' => $new_slug,
                'description' => $description,
            ],
            ['id' => $brand_id]
        );

        if ($result) {
            $this->logger->info('Updated brand', [
                'brand_id' => $brand_id,
                'name' => $name,
                'slug' => $new_slug
            ]);
            return true;
        }

        return false;
    }

    /**
     * Update brand categories
     *
     * @param int $brand_id Brand ID
     * @param array $category_ids List of category IDs (empty means all)
     * @return bool Success status
     */
    public function update_brand_categories($brand_id, $category_ids = []) {
        global $wpdb;

        // Remove existing relationships
        $wpdb->delete($this->brand_categories_table, ['brand_id' => $brand_id]);

        if (empty($category_ids)) {
            // Assign all categories
            $categories = $this->get_categories();
            foreach ($categories as $category) {
                $wpdb->insert(
                    $this->brand_categories_table,
                    [
                        'brand_id' => $brand_id,
                        'category_id' => $category->id,
                    ]
                );
            }
        } else {
            // Assign specific categories
            foreach ($category_ids as $category_id) {
                $wpdb->insert(
                    $this->brand_categories_table,
                    [
                        'brand_id' => $brand_id,
                        'category_id' => $category_id,
                    ]
                );
            }
        }

        $this->logger->info('Updated brand categories', ['brand_id' => $brand_id, 'category_count' => count($category_ids)]);
        return true;
    }

    /**
     * Build sync configuration (replaces hardcoded config)
     *
     * @return array Sync configuration
     */
    public function get_sync_config() {
        $brands = $this->get_brands();
        $categories = $this->get_categories();

        // Build category reference
        $category_reference = [];
        $common_categories = [];

        foreach ($categories as $category) {
            $category_reference[$category->name] = $category->code;
            $common_categories[] = $category->code;
        }

        // Build brand configuration
        $brand_config = [];
        foreach ($brands as $brand) {
            $brand_categories = $this->get_brand_categories($brand->id);

            if ($this->brand_has_all_categories($brand->id)) {
                // Brand has all categories
                $brand_config[$brand->name] = [];
            } else {
                // Brand has specific categories
                $category_codes = [];
                foreach ($brand_categories as $category) {
                    $category_codes[] = $category->code;
                }
                $brand_config[$brand->name] = $category_codes;
            }
        }

        return [
            'category_reference' => $category_reference,
            'common_corp_categories' => $common_categories,
            'brand_config' => $brand_config,
        ];
    }

    /**
     * Build jobs list from dynamic configuration
     *
     * @return array List of sync jobs
     */
    public function build_jobs_list() {
        // Use jobs store if available
        if (isset($GLOBALS['shopcommerce_jobs_store'])) {
            return $GLOBALS['shopcommerce_jobs_store']->get_jobs();
        }

        // Fallback to local implementation
        $brands = $this->get_brands();
        $jobs = [];

        foreach ($brands as $brand) {
            if ($this->brand_has_all_categories($brand->id)) {
                // Get all available categories instead of empty array
                $all_categories = $this->get_categories();
                $category_codes = [];
                foreach ($all_categories as $category) {
                    $category_codes[] = $category->code;
                }
                $jobs[] = [
                    'brand' => $brand->name,
                    'brand_id' => $brand->id,
                    'categories' => $category_codes, // All activated categories
                ];
            } else {
                $brand_categories = $this->get_brand_categories($brand->id);
                $category_codes = [];
                foreach ($brand_categories as $category) {
                    $category_codes[] = $category->code;
                }
                $jobs[] = [
                    'brand' => $brand->name,
                    'brand_id' => $brand->id,
                    'categories' => $category_codes,
                ];
            }
        }

        return $jobs;
    }

    /**
     * Delete a brand
     *
     * @param int $brand_id Brand ID
     * @return bool Success status
     */
    public function delete_brand($brand_id) {
        global $wpdb;

        // Delete brand-category relationships first
        $wpdb->delete($this->brand_categories_table, ['brand_id' => $brand_id]);

        // Delete brand
        $result = $wpdb->delete($this->brands_table, ['id' => $brand_id]);

        if ($result) {
            $this->logger->info('Deleted brand', ['brand_id' => $brand_id]);
            return true;
        }

        return false;
    }

    /**
     * Delete a category
     *
     * @param int $category_id Category ID
     * @return bool Success status
     */
    public function delete_category($category_id) {
        global $wpdb;

        // Delete brand-category relationships first
        $wpdb->delete($this->brand_categories_table, ['category_id' => $category_id]);

        // Delete category
        $result = $wpdb->delete($this->categories_table, ['id' => $category_id]);

        if ($result) {
            $this->logger->info('Deleted category', ['category_id' => $category_id]);
            return true;
        }

        return false;
    }

    /**
     * Toggle brand active status
     *
     * @param int $brand_id Brand ID
     * @param bool $active Active status
     * @return bool Success status
     */
    public function toggle_brand_active($brand_id, $active) {
        global $wpdb;

        $result = $wpdb->update(
            $this->brands_table,
            ['is_active' => $active ? 1 : 0],
            ['id' => $brand_id]
        );

        if ($result) {
            $this->logger->info('Toggled brand active status', ['brand_id' => $brand_id, 'active' => $active]);
            return true;
        }

        return false;
    }

    /**
     * Toggle category active status
     *
     * @param int $category_id Category ID
     * @param bool $active Active status
     * @return bool Success status
     */
    public function toggle_category_active($category_id, $active) {
        global $wpdb;

        $result = $wpdb->update(
            $this->categories_table,
            ['is_active' => $active ? 1 : 0],
            ['id' => $category_id]
        );

        if ($result) {
            $this->logger->info('Toggled category active status', ['category_id' => $category_id, 'active' => $active]);
            return true;
        }

        return false;
    }

    /**
     * Create brands from API response, ignoring existing ones
     *
     * @param array $api_brands Brands data from API response
     * @return array Results with created, skipped, and error counts
     */
    public function create_brands_from_api($api_brands) {
        global $wpdb;

        if (!is_array($api_brands)) {
            $this->logger->error('Invalid API brands data', ['type' => gettype($api_brands)]);
            return [
                'created' => 0,
                'skipped' => 0,
                'errors' => 1,
                'error_messages' => ['Invalid API brands data format']
            ];
        }

        $results = [
            'created' => 0,
            'skipped' => 0,
            'errors' => 0,
            'error_messages' => [],
            'created_brands' => [],
            'skipped_brands' => []
        ];

        $this->logger->info('Starting to create brands from API', ['total_api_brands' => count($api_brands)]);

        foreach ($api_brands as $api_brand) {
            try {
                // Validate API brand structure
                if (!is_array($api_brand) || !isset($api_brand['MarcaHomologada'])) {
                    $results['errors']++;
                    $results['error_messages'][] = 'Invalid brand structure: missing MarcaHomologada';
                    continue;
                }

                // Use MarcaHomologada as the brand name, fallback to Marks if needed
                $brand_name = !empty($api_brand['MarcaHomologada']) ? trim($api_brand['MarcaHomologada']) : trim($api_brand['Marks'] ?? '');

                if (empty($brand_name)) {
                    $results['errors']++;
                    $results['error_messages'][] = 'Empty brand name for CodigoMarca: ' . ($api_brand['CodigoMarca'] ?? 'unknown');
                    continue;
                }

                // Check if brand already exists (by name or slug)
                $slug = sanitize_title($brand_name);
                $existing_brand = $wpdb->get_row($wpdb->prepare(
                    "SELECT id, name FROM {$this->brands_table} WHERE name = %s OR slug = %s",
                    $brand_name,
                    $slug
                ));

                if ($existing_brand) {
                    $results['skipped']++;
                    $results['skipped_brands'][] = [
                        'name' => $brand_name,
                        'existing_name' => $existing_brand->name,
                        'reason' => 'Brand already exists'
                    ];
                    $this->logger->debug('Skipping existing brand', [
                        'api_brand' => $brand_name,
                        'existing_brand' => $existing_brand->name
                    ]);
                    continue;
                }

                // Check if this is a default brand (should be active)
                $default_brands = ['HP INC', 'DELL', 'LENOVO', 'APPLE', 'ASUS', 'BOSE', 'EPSON', 'JBL'];
                $is_active = in_array(strtoupper($brand_name), $default_brands) ? 1 : 0;

                // Create the brand
                $description = sprintf(
                    'Brand created from API - CodigoMarca: %s, CodigoCategoria: %s',
                    $api_brand['CodigoMarca'] ?? 'N/A',
                    $api_brand['CodigoCategoria'] ?? 'N/A'
                );

                $result = $wpdb->insert(
                    $this->brands_table,
                    [
                        'name' => $brand_name,
                        'slug' => $slug,
                        'description' => $description,
                        'is_active' => $is_active,
                    ]
                );

                if ($result) {
                    $brand_id = $wpdb->insert_id;
                    $results['created']++;
                    $results['created_brands'][] = [
                        'id' => $brand_id,
                        'name' => $brand_name,
                        'codigo_marca' => $api_brand['CodigoMarca'] ?? null,
                        'codigo_categoria' => $api_brand['CodigoCategoria'] ?? null
                    ];

                    // Automatically assign all categories to new brand
                    $categories = $this->get_categories();
                    foreach ($categories as $category) {
                        $wpdb->insert(
                            $this->brand_categories_table,
                            [
                                'brand_id' => $brand_id,
                                'category_id' => $category->id,
                            ]
                        );
                    }

                    $this->logger->info('Created brand from API', [
                        'brand_id' => $brand_id,
                        'name' => $brand_name,
                        'codigo_marca' => $api_brand['CodigoMarca'] ?? null,
                        'is_active' => $is_active
                    ]);
                } else {
                    $results['errors']++;
                    $error_message = 'Database error creating brand: ' . $wpdb->last_error;
                    $results['error_messages'][] = $error_message;
                    $this->logger->error('Database error creating brand from API', [
                        'brand_name' => $brand_name,
                        'error' => $error_message
                    ]);
                }

            } catch (Exception $e) {
                $results['errors']++;
                $error_message = 'Exception processing brand: ' . $e->getMessage();
                $results['error_messages'][] = $error_message;
                $this->logger->error('Exception processing brand from API', [
                    'brand_data' => $api_brand,
                    'error' => $error_message
                ]);
            }
        }

        $this->logger->info('Completed creating brands from API', [
            'created' => $results['created'],
            'skipped' => $results['skipped'],
            'errors' => $results['errors']
        ]);

        return $results;
    }

    /**
     * Reset all brands and categories to default configuration
     *
     * This will clear all existing brands and categories and restore the default setup
     *
     * @return bool Success status
     */
    public function reset_to_defaults() {
        global $wpdb;

        try {
            $this->logger->info('Starting reset of brands and categories to defaults');

            // Clear all existing data
            $wpdb->query("TRUNCATE TABLE {$this->brand_categories_table}");
            $wpdb->query("TRUNCATE TABLE {$this->categories_table}");
            $wpdb->query("TRUNCATE TABLE {$this->brands_table}");

            $this->logger->info('Cleared all existing brands and categories');

            // Reinitialize with default data using central migrator
            if (isset($GLOBALS['shopcommerce_migrator'])) {
                $GLOBALS['shopcommerce_migrator']->initialize_default_data();
                $this->logger->info('Reinitialized default data using central migrator');
            } else {
                $this->logger->warning('Central migrator not available, manual initialization required');
                return false;
            }

            $this->logger->info('Successfully reset brands and categories to defaults');
            return true;

        } catch (Exception $e) {
            $this->logger->error('Error during reset to defaults', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get markup percentage for a specific category
     *
     * @param string $category_code Category code
     * @return float Markup percentage
     */
    public function get_category_markup($category_code) {
        global $wpdb;

        $markup_table = $wpdb->prefix . 'shopcommerce_category_markup';

        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT markup_percentage FROM {$markup_table} WHERE category_code = %s",
            $category_code
        ));

        return $result !== null ? floatval($result) : 0.0;
    }
}