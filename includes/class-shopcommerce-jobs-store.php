<?php

/**
 * ShopCommerce Jobs Store Class
 *
 * Centralized management of brands, categories, and sync jobs.
 * This class replaces direct database operations with a unified interface.
 *
 * @package ShopCommerce_Sync
 */

class ShopCommerce_Jobs_Store {

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
    private $batch_queue_table;

    /**
     * Cache for jobs list
     */
    private $jobs_cache = null;
    private $cache_timestamp = 0;
    private $cache_ttl = 300; // 5 minutes cache

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
        $this->batch_queue_table = $wpdb->prefix . 'shopcommerce_batch_queue';

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
     * Get sync jobs list (cached)
     *
     * @return array List of sync jobs
     */
    public function get_jobs() {
        // Check cache
        if ($this->jobs_cache !== null && (time() - $this->cache_timestamp) < $this->cache_ttl) {
            return $this->jobs_cache;
        }

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

        // Cache the results
        $this->jobs_cache = $jobs;
        $this->cache_timestamp = time();

        return $jobs;
    }

    /**
     * Clear jobs cache
     */
    public function clear_cache() {
        $this->jobs_cache = null;
        $this->cache_timestamp = 0;
        $this->logger->debug('Cleared jobs store cache');
    }

    /**
     * Update an existing brand
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

            // Clear cache
            $this->clear_cache();

            return true;
        }

        return false;
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

            // Clear cache
            $this->clear_cache();

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
            $this->logger->info('Created new category', ['category_id' => $category_id, 'name' => $name, 'code' => $code, 'is_active' => $is_active, 'markup_percentage' => $markup_percentage]);

            // Clear cache
            $this->clear_cache();

            return $category_id;
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

        // Clear cache
        $this->clear_cache();

        return true;
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

            // Clear cache
            $this->clear_cache();

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

            // Clear cache
            $this->clear_cache();

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

            // Clear cache
            $this->clear_cache();

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

            // Clear cache
            $this->clear_cache();

            return true;
        }

        return false;
    }

    /**
     * Update category markup percentage
     *
     * @param int $category_id Category ID
     * @param float $markup_percentage Markup percentage
     * @return bool Success status
     */
    public function update_category_markup($category_id, $markup_percentage) {
        global $wpdb;

        $result = $wpdb->update(
            $this->categories_table,
            ['markup_percentage' => $markup_percentage],
            ['id' => $category_id],
            ['%f'],
            ['%d']
        );

        if ($result) {
            $this->logger->info('Updated category markup percentage', ['category_id' => $category_id, 'markup_percentage' => $markup_percentage]);

            // Clear cache
            $this->clear_cache();

            return true;
        }

        return false;
    }

    /**
     * Get category markup percentage by category code
     *
     * @param int $category_code Category code
     * @return float Markup percentage
     */
    public function get_category_markup_percentage($category_code) {
        global $wpdb;

        $markup = $wpdb->get_var($wpdb->prepare(
            "SELECT markup_percentage FROM {$this->categories_table} WHERE code = %d AND is_active = 1",
            $category_code
        ));

        return $markup !== null ? floatval($markup) : 15.00; // Default to 15% if not found
    }

    /**
     * Get category markup percentage by category name
     *
     * @param string $category_name Category name
     * @return float Markup percentage
     */
    public function get_category_markup_percentage_by_name($category_name) {
        global $wpdb;

        // Validate input
        if (empty($category_name)) {
            $this->logger->debug('Empty category name provided for markup lookup');
            return 15.00;
        }

        $markup = $wpdb->get_var($wpdb->prepare(
            "SELECT markup_percentage FROM {$this->categories_table} WHERE name = %s AND is_active = 1",
            $category_name
        ));

        if ($markup === null) {
            $this->logger->debug('Category markup not found, using default', [
                'category_name' => $category_name
            ]);
            return 15.00;
        }

        $markup_value = floatval($markup);
        $this->logger->debug('Retrieved category markup', [
            'category_name' => $category_name,
            'markup_percentage' => $markup_value
        ]);

        return $markup_value;
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

        // Clear cache
        $this->clear_cache();

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

            // Clear cache
            $this->clear_cache();

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
     * Batch Queue Management Methods
     */

    /**
     * Add a batch to the processing queue
     *
     * @param string $brand Brand name
     * @param array $categories Category codes
     * @param array $batch_data Batch product data
     * @param int $batch_index Batch index
     * @param int $total_batches Total number of batches
     * @param int $priority Priority level (higher = higher priority)
     * @return int|false Batch ID or false on failure
     */
    public function add_batch_to_queue($brand, $categories, $batch_data, $batch_index, $total_batches, $priority = 0) {
        global $wpdb;

        $result = $wpdb->insert(
            $this->batch_queue_table,
            [
                'brand' => $brand,
                'categories' => json_encode($categories),
                'batch_data' => json_encode($batch_data),
                'batch_index' => $batch_index,
                'total_batches' => $total_batches,
                'status' => 'pending',
                'priority' => $priority,
            ],
            ['%s', '%s', '%s', '%d', '%d', '%s', '%d']
        );

        if ($result) {
            $batch_id = $wpdb->insert_id;
            $this->logger->info('Added batch to queue', [
                'batch_id' => $batch_id,
                'brand' => $brand,
                'batch_index' => $batch_index,
                'total_batches' => $total_batches,
                'products_count' => count($batch_data)
            ]);
            return $batch_id;
        }

        $this->logger->error('Failed to add batch to queue', [
            'brand' => $brand,
            'batch_index' => $batch_index
        ]);
        return false;
    }

    /**
     * Get next pending batch from queue
     *
     * @param int $limit Maximum number of batches to retrieve
     * @return array List of pending batches
     */
    public function get_pending_batches($limit = 1) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->batch_queue_table}
             WHERE status = 'pending'
             ORDER BY priority DESC, created_at ASC
             LIMIT %d",
            $limit
        ));
    }

    /**
     * Get batch by ID
     *
     * @param int $batch_id Batch ID
     * @return object|null Batch object or null if not found
     */
    public function get_batch($batch_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->batch_queue_table} WHERE id = %d",
            $batch_id
        ));
    }

    /**
     * Update batch status
     *
     * @param int $batch_id Batch ID
     * @param string $status New status
     * @param string $error_message Error message (if any)
     * @return bool Success status
     */
    public function update_batch_status($batch_id, $status, $error_message = null) {
        global $wpdb;

        $data = [
            'status' => $status,
        ];

        if ($status === 'processing') {
            $data['started_at'] = current_time('mysql');
            $data['attempts'] = ['expression' => 'attempts + 1'];
        } elseif ($status === 'completed' || $status === 'failed') {
            $data['completed_at'] = current_time('mysql');
        }

        if ($error_message) {
            $data['error_message'] = $error_message;
        }

        $format = ['%s'];

        if (isset($data['started_at'])) {
            $format[] = '%s';
        }
        if (isset($data['completed_at'])) {
            $format[] = '%s';
        }
        if (isset($data['error_message'])) {
            $format[] = '%s';
        }

        // Handle attempts increment separately
        if (isset($data['attempts']['expression'])) {
            unset($data['attempts']);
            $result = $wpdb->query($wpdb->prepare(
                "UPDATE {$this->batch_queue_table}
                 SET status = %s,
                     started_at = COALESCE(started_at, %s),
                     completed_at = " . ($status === 'completed' || $status === 'failed' ? "%s" : "completed_at") . ",
                     error_message = " . ($error_message ? "%s" : "error_message") . ",
                     attempts = attempts + 1
                 WHERE id = %d",
                array_merge([$status, current_time('mysql')],
                    ($status === 'completed' || $status === 'failed' ? [current_time('mysql')] : []),
                    $error_message ? [$error_message] : [],
                    [$batch_id])
            ));
        } else {
            $result = $wpdb->update(
                $this->batch_queue_table,
                $data,
                ['id' => $batch_id],
                $format,
                ['%d']
            );
        }

        if ($result) {
            $this->logger->info('Updated batch status', [
                'batch_id' => $batch_id,
                'status' => $status,
                'error_message' => $error_message
            ]);
            return true;
        }

        $this->logger->error('Failed to update batch status', [
            'batch_id' => $batch_id,
            'status' => $status
        ]);
        return false;
    }

    /**
     * Increment batch attempt count
     *
     * @param int $batch_id Batch ID
     * @return bool True if successful
     */
    public function increment_batch_attempts($batch_id) {
        global $wpdb;

        $result = $wpdb->query($wpdb->prepare(
            "UPDATE {$this->batch_queue_table}
             SET attempts = attempts + 1
             WHERE id = %d",
            $batch_id
        ));

        if ($result) {
            $this->logger->debug('Incremented batch attempts', ['batch_id' => $batch_id]);
            return true;
        }

        $this->logger->error('Failed to increment batch attempts', ['batch_id' => $batch_id]);
        return false;
    }

    /**
     * Get queue statistics
     *
     * @return array Queue statistics
     */
    public function get_queue_stats() {
        global $wpdb;

        $stats = $wpdb->get_results(
            "SELECT status, COUNT(*) as count
             FROM {$this->batch_queue_table}
             GROUP BY status"
        );

        $result = [
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0,
        ];

        foreach ($stats as $stat) {
            if (isset($result[$stat->status])) {
                $result[$stat->status] = intval($stat->count);
            }
        }

        // Get oldest pending batch time
        $oldest_pending = $wpdb->get_var(
            "SELECT created_at FROM {$this->batch_queue_table}
             WHERE status = 'pending'
             ORDER BY created_at ASC
             LIMIT 1"
        );

        $result['oldest_pending'] = $oldest_pending;
        $result['total_batches'] = array_sum($result);

        return $result;
    }

    /**
     * Clean up old completed batches
     *
     * @param int $days_old Delete batches older than this many days
     * @return int Number of batches deleted
     */
    public function cleanup_old_batches($days_old = 7) {
        global $wpdb;

        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->batch_queue_table}
             WHERE status IN ('completed', 'failed')
             AND completed_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days_old
        ));

        if ($result) {
            $this->logger->info('Cleaned up old batches', [
                'days_old' => $days_old,
                'deleted_count' => $result
            ]);
        }

        return $result;
    }

    /**
     * Reset failed batches for retry
     *
     * @param string|null $brand Specific brand to reset (null for all)
     * @return int Number of batches reset
     */
    public function reset_failed_batches($brand = null) {
        global $wpdb;

        $where = ["status = 'failed'", "attempts < max_attempts"];
        $prepare_values = [];

        if ($brand) {
            $where[] = "brand = %s";
            $prepare_values[] = $brand;
        }

        $result = $wpdb->query($wpdb->prepare(
            "UPDATE {$this->batch_queue_table}
             SET status = 'pending',
                 started_at = NULL,
                 completed_at = NULL,
                 error_message = NULL
             WHERE " . implode(' AND ', $where),
            $prepare_values
        ));

        if ($result) {
            $this->logger->info('Reset failed batches for retry', [
                'brand' => $brand,
                'reset_count' => $result
            ]);
        }

        return $result;
    }

    /**
     * Delete a batch from the queue
     *
     * @param int $batch_id Batch ID to delete
     * @return bool True if successful
     */
    public function delete_batch($batch_id) {
        global $wpdb;

        $result = $wpdb->delete(
            $this->batch_queue_table,
            ['id' => $batch_id],
            ['%d']
        );

        if ($result) {
            $this->logger->info('Batch deleted successfully', ['batch_id' => $batch_id]);
            return true;
        }

        $this->logger->error('Failed to delete batch', ['batch_id' => $batch_id]);
        return false;
    }

    /**
     * Get batches with filtering and pagination
     *
     * @param int $per_page Number of batches per page
     * @param int $offset Offset for pagination
     * @param string $status Filter by status
     * @param string $brand Filter by brand
     * @param string $search Search in batch data
     * @return array Array of batch objects
     */
    public function get_batches($per_page = 20, $offset = 0, $status = '', $brand = '', $search = '') {
        global $wpdb;

        $where_clauses = ['1=1'];
        $prepare_values = [];

        if ($status) {
            $where_clauses[] = 'status = %s';
            $prepare_values[] = $status;
        }

        if ($brand) {
            $where_clauses[] = 'brand = %s';
            $prepare_values[] = $brand;
        }

        if ($search) {
            $where_clauses[] = '(brand LIKE %s OR error_message LIKE %s)';
            $prepare_values[] = '%' . $wpdb->esc_like($search) . '%';
            $prepare_values[] = '%' . $wpdb->esc_like($search) . '%';
        }

        $where_clause = implode(' AND ', $where_clauses);

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->batch_queue_table}
             WHERE {$where_clause}
             ORDER BY created_at DESC, priority DESC
             LIMIT %d OFFSET %d",
            array_merge($prepare_values, [$per_page, $offset])
        );

        $results = $wpdb->get_results($sql);

        if ($wpdb->last_error) {
            $this->logger->error('Failed to get batches', [
                'error' => $wpdb->last_error,
                'where_clause' => $where_clause,
                'per_page' => $per_page,
                'offset' => $offset
            ]);
            return [];
        }

        return $results;
    }

    /**
     * Get total count of batches with filtering
     *
     * @param string $status Filter by status
     * @param string $brand Filter by brand
     * @param string $search Search in batch data
     * @return int Total count
     */
    public function get_batches_count($status = '', $brand = '', $search = '') {
        global $wpdb;

        $where_clauses = ['1=1'];
        $prepare_values = [];

        if ($status) {
            $where_clauses[] = 'status = %s';
            $prepare_values[] = $status;
        }

        if ($brand) {
            $where_clauses[] = 'brand = %s';
            $prepare_values[] = $brand;
        }

        if ($search) {
            $where_clauses[] = '(brand LIKE %s OR error_message LIKE %s)';
            $prepare_values[] = '%' . $wpdb->esc_like($search) . '%';
            $prepare_values[] = '%' . $wpdb->esc_like($search) . '%';
        }

        $where_clause = implode(' AND ', $where_clauses);

        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->batch_queue_table} WHERE {$where_clause}",
            $prepare_values
        );

        $count = $wpdb->get_var($sql);

        if ($wpdb->last_error) {
            $this->logger->error('Failed to get batches count', [
                'error' => $wpdb->last_error,
                'where_clause' => $where_clause
            ]);
            return 0;
        }

        return intval($count);
    }

    /**
     * Get a single batch by ID
     *
     * @param int $batch_id Batch ID
     * @return object|null Batch object or null if not found
     */
    public function get_batch_by_id($batch_id) {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->batch_queue_table} WHERE id = %d",
            $batch_id
        );

        $batch = $wpdb->get_row($sql);

        if ($wpdb->last_error) {
            $this->logger->error('Failed to get batch by ID', [
                'batch_id' => $batch_id,
                'error' => $wpdb->last_error
            ]);
            return null;
        }

        return $batch;
    }

    /**
     * Update batch attempts count
     *
     * @param int $batch_id Batch ID
     * @param int $attempts Number of attempts
     * @return bool Success status
     */
    public function update_batch_attempts($batch_id, $attempts) {
        global $wpdb;

        $result = $wpdb->update(
            $this->batch_queue_table,
            ['attempts' => $attempts],
            ['id' => $batch_id],
            ['%d'],
            ['%d']
        );

        if ($result === false) {
            $this->logger->error('Failed to update batch attempts', [
                'batch_id' => $batch_id,
                'attempts' => $attempts
            ]);
            return false;
        }

        return true;
    }

    /**
     * Clear batch error message
     *
     * @param int $batch_id Batch ID
     * @return bool Success status
     */
    public function clear_batch_error($batch_id) {
        global $wpdb;

        $result = $wpdb->update(
            $this->batch_queue_table,
            ['error_message' => null],
            ['id' => $batch_id],
            ['%s'],
            ['%d']
        );

        if ($result === false) {
            $this->logger->error('Failed to clear batch error', [
                'batch_id' => $batch_id
            ]);
            return false;
        }

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
}