<?php

/**
 * ShopCommerce Migration Manager Class
 *
 * Handles all database migrations and schema updates centrally.
 * This provides a single source of truth for database structure.
 *
 * @package ShopCommerce_Sync
 */

class ShopCommerce_Migrator {

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
    private $migrations_table;
    private $currency_exchange_table;

    /**
     * Migration version
     */
    private $current_version = '1.3.0';

    /**
     * Available migrations with their versions
     */
    private $migrations = [
        '1.0.0' => 'create_initial_tables',
        '1.1.0' => 'add_markup_percentage_column',
        '1.2.0' => 'add_batch_queue_table',
        '1.3.0' => 'add_currency_exchange_table',
    ];

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
        $this->migrations_table = $wpdb->prefix . 'shopcommerce_migrations';
        $this->currency_exchange_table = $wpdb->prefix . 'shopcommerce_currency_exchange';
    }

    /**
     * Run all migrations
     *
     * @return bool Success status
     */
    public function run_migrations() {
        try {
            $this->logger->info('Starting database migrations', ['version' => $this->current_version]);

            // Create migrations table first (if it doesn't exist)
            $this->create_migrations_table();

            // Get current executed migrations
            $executed_migrations = $this->get_executed_migrations();

            // Run pending migrations in order
            $pending_count = 0;
            foreach ($this->migrations as $version => $migration_method) {
                if (!in_array($version, $executed_migrations)) {
                    $this->logger->info('Running migration', ['version' => $version]);

                    if ($this->run_migration($version, $migration_method)) {
                        $this->mark_migration_executed($version);
                        $pending_count++;
                    } else {
                        throw new Exception("Migration {$version} failed");
                    }
                }
            }

            // Initialize default data if needed (only for fresh installs)
            if (empty($executed_migrations)) {
                $this->initialize_default_data();
            }

            $this->logger->info('Database migrations completed successfully', [
                'executed_count' => $pending_count
            ]);
            return true;

        } catch (Exception $e) {
            $this->logger->error('Database migrations failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Create migrations tracking table
     */
    private function create_migrations_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->migrations_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            version varchar(20) NOT NULL,
            migration_name varchar(100) NOT NULL,
            executed_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY version (version)
        ) $charset_collate;";

        $this->execute_sql($sql, 'migrations table');
    }

    /**
     * Get list of executed migrations
     *
     * @return array Array of executed migration versions
     */
    private function get_executed_migrations() {
        global $wpdb;

        $results = $wpdb->get_col("SELECT version FROM {$this->migrations_table} ORDER BY version");
        return $results ? $results : [];
    }

    /**
     * Mark a migration as executed
     *
     * @param string $version Migration version
     * @return bool Success status
     */
    private function mark_migration_executed($version) {
        global $wpdb;

        if (!isset($this->migrations[$version])) {
            return false;
        }

        $migration_name = $this->migrations[$version];

        $result = $wpdb->insert(
            $this->migrations_table,
            [
                'version' => $version,
                'migration_name' => $migration_name,
            ],
            ['%s', '%s']
        );

        if ($result === false) {
            $this->logger->error('Failed to mark migration as executed', [
                'version' => $version,
                'error' => $wpdb->last_error
            ]);
            return false;
        }

        $this->logger->debug('Migration marked as executed', ['version' => $version]);
        return true;
    }

    /**
     * Run a specific migration
     *
     * @param string $version Migration version
     * @param string $migration_method Migration method name
     * @return bool Success status
     */
    private function run_migration($version, $migration_method) {
        try {
            // Check if method exists
            if (!method_exists($this, $migration_method)) {
                throw new Exception("Migration method {$migration_method} does not exist");
            }

            // Run the migration
            $this->$migration_method();

            $this->logger->info('Migration executed successfully', [
                'version' => $version,
                'method' => $migration_method
            ]);

            return true;

        } catch (Exception $e) {
            $this->logger->error('Migration execution failed', [
                'version' => $version,
                'method' => $migration_method,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Migration 1.0.0: Create initial tables
     */
    private function create_initial_tables() {
        $this->create_brands_table();
        $this->create_categories_table();
        $this->create_brand_categories_table();
    }

    /**
     * Migration 1.1.0: Add markup_percentage column
     */
    private function add_markup_percentage_column() {
        $this->ensure_markup_percentage_column();
    }

    /**
     * Migration 1.2.0: Add batch queue table
     */
    private function add_batch_queue_table() {
        $this->create_batch_queue_table();
    }

    /**
     * Migration 1.3.0: Add currency exchange table
     */
    private function add_currency_exchange_table() {
        $this->create_currency_exchange_table();
    }

    /**
     * Create brands table
     */
    private function create_brands_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->brands_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            slug varchar(100) NOT NULL,
            description text,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug)
        ) $charset_collate;";

        $this->execute_sql($sql, 'brands table');
    }

    /**
     * Create categories table with markup_percentage field
     */
    private function create_categories_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->categories_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            code int(11) NOT NULL,
            description text,
            is_active tinyint(1) DEFAULT 1,
            markup_percentage decimal(5,2) DEFAULT 15.00,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY code (code)
        ) $charset_collate;";

        $this->execute_sql($sql, 'categories table');
    }

    /**
     * Create brand-categories relationship table
     */
    private function create_brand_categories_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->brand_categories_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            brand_id int(11) NOT NULL,
            category_id int(11) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY brand_category (brand_id, category_id),
            KEY brand_id (brand_id),
            KEY category_id (category_id)
        ) $charset_collate;";

        $this->execute_sql($sql, 'brand-categories table');
    }

    /**
     * Create batch queue table
     */
    private function create_batch_queue_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->batch_queue_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            brand varchar(100) NOT NULL,
            categories text NOT NULL,
            batch_data longtext NOT NULL,
            batch_index int(11) NOT NULL,
            total_batches int(11) NOT NULL,
            status enum('pending','processing','completed','failed') DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            started_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            error_message text,
            attempts int(11) DEFAULT 0,
            max_attempts int(11) DEFAULT 3,
            priority int(11) DEFAULT 0,
            PRIMARY KEY  (id),
            KEY brand_status (brand, status),
            KEY status_created (status, created_at),
            KEY priority_status (priority, status)
        ) $charset_collate;";

        $this->execute_sql($sql, 'batch queue table');
    }

    /**
     * Create currency exchange table
     */
    private function create_currency_exchange_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->currency_exchange_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            from_currency varchar(3) NOT NULL,
            exchange_rate decimal(10,4) NOT NULL DEFAULT 1.0000,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY unique_from_currency (from_currency),
            KEY idx_from_currency (from_currency),
            KEY idx_updated_at (updated_at)
        ) $charset_collate;";

        $this->execute_sql($sql, 'currency exchange table');
    }

    /**
     * Ensure markup_percentage column exists in categories table
     */
    private function ensure_markup_percentage_column() {
        global $wpdb;

        // Check if the column exists
        $column_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
            DB_NAME,
            $this->categories_table,
            'markup_percentage'
        ));

        if (!$column_exists) {
            $sql = "ALTER TABLE {$this->categories_table}
                    ADD COLUMN markup_percentage decimal(5,2) DEFAULT 15.00
                    AFTER is_active";

            $this->execute_sql($sql, 'markup_percentage column addition');
            $this->logger->info('Added markup_percentage column to categories table');
        }
    }

    /**
     * Initialize default data if tables are empty
     */
    private function initialize_default_data() {
        global $wpdb;

        // Check if brands table is empty
        $brand_count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->brands_table}");

        if ($brand_count == 0) {
            $this->insert_default_brands();
        }

        // Check if categories table is empty
        $category_count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->categories_table}");

        if ($category_count == 0) {
            $this->insert_default_categories();
        }

        // Check if brand-categories table is empty and populate with existing relationships
        $relation_count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->brand_categories_table}");

        if ($relation_count == 0) {
            $this->insert_default_brand_categories();
        }
    }

    /**
     * Insert default brands
     */
    private function insert_default_brands() {
        global $wpdb;

        $default_brands = [
            ['HP INC', 'hp-inc', 'HP Inc products'],
            ['DELL', 'dell', 'Dell products'],
            ['LENOVO', 'lenovo', 'Lenovo products'],
            ['APPLE', 'apple', 'Apple products'],
            ['ASUS', 'asus', 'ASUS products'],
            ['BOSE', 'bose', 'Bose products'],
            ['EPSON', 'epson', 'Epson products'],
            ['JBL', 'jbl', 'JBL products'],
        ];

        foreach ($default_brands as $brand) {
            $wpdb->insert(
                $this->brands_table,
                [
                    'name' => $brand[0],
                    'slug' => $brand[1],
                    'description' => $brand[2],
                ]
            );
        }

        $this->logger->info('Initialized default brands', ['count' => count($default_brands)]);
    }

    /**
     * Insert default categories with markup percentages
     */
    private function insert_default_categories() {
        global $wpdb;

        $default_categories = [
            ['Accesorios Y Perifericos', 1, 'Accessories and peripherals', 15.00],
            ['Computadores', 7, 'Computers, laptops, workstations', 15.00],
            ['Impresión', 12, 'Printing equipment', 15.00],
            ['Video', 14, 'Video equipment and monitors', 15.00],
            ['Servidores Y Almacenamiento', 18, 'Servers and storage', 15.00],
        ];

        foreach ($default_categories as $category) {
            $wpdb->insert(
                $this->categories_table,
                [
                    'name' => $category[0],
                    'code' => $category[1],
                    'description' => $category[2],
                    'markup_percentage' => $category[3],
                ]
            );
        }

        $this->logger->info('Initialized default categories', ['count' => count($default_categories)]);
    }

    /**
     * Insert default brand-category relationships
     */
    private function insert_default_brand_categories() {
        global $wpdb;

        // Get all brands and categories
        $brands = $wpdb->get_results("SELECT id, name FROM {$this->brands_table}");
        $categories = $wpdb->get_results("SELECT id, code FROM {$this->categories_table}");

        // Create category code mapping
        $category_map = [];
        foreach ($categories as $category) {
            $category_map[$category->code] = $category->id;
        }

        // Define default brand-category relationships
        $default_relationships = [
            'HP INC' => [1, 7, 12, 14, 18],
            'DELL' => [1, 7, 12, 14, 18],
            'LENOVO' => [1, 7, 12, 14, 18],
            'APPLE' => [1, 7],
            'ASUS' => [7],
            'BOSE' => [], // All categories
            'EPSON' => [], // All categories
            'JBL' => [], // All categories
        ];

        foreach ($brands as $brand) {
            $brand_name = $brand->name;
            $brand_id = $brand->id;

            if (isset($default_relationships[$brand_name])) {
                $category_codes = $default_relationships[$brand_name];

                if (empty($category_codes)) {
                    // All categories for this brand
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
                    // Specific categories for this brand
                    foreach ($category_codes as $code) {
                        if (isset($category_map[$code])) {
                            $wpdb->insert(
                                $this->brand_categories_table,
                                [
                                    'brand_id' => $brand_id,
                                    'category_id' => $category_map[$code],
                                ]
                            );
                        }
                    }
                }
            }
        }

        $this->logger->info('Initialized default brand-category relationships');
    }

    /**
     * Execute SQL with error handling
     *
     * @param string $sql SQL to execute
     * @param string $context Context for logging
     */
    private function execute_sql($sql, $context = '') {
        global $wpdb;

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $result = dbDelta($sql);

        if (is_wp_error($result)) {
            $this->logger->error('Migration SQL error', [
                'context' => $context,
                'error' => $result->get_error_message(),
                'sql_preview' => substr($sql, 0, 200)
            ]);
            throw new Exception("Migration failed for {$context}: " . $result->get_error_message());
        }

        $this->logger->debug('Migration SQL executed successfully', [
            'context' => $context
        ]);
    }

    /**
     * Get current migration version
     *
     * @return string Current version
     */
    public function get_version() {
        return $this->current_version;
    }

    /**
     * Check if tables exist
     *
     * @return bool True if all tables exist
     */
    public function check_tables_exist() {
        global $wpdb;

        $tables = [
            $this->migrations_table,
            $this->brands_table,
            $this->categories_table,
            $this->brand_categories_table,
            $this->batch_queue_table,
            $this->currency_exchange_table
        ];

        foreach ($tables as $table) {
            $result = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
            if ($result !== $table) {
                return false;
            }
        }

        return true;
    }

    /**
     * Drop all tables (for testing/debugging)
     *
     * @param bool $confirm Confirmation flag
     * @return bool Success status
     */
    public function drop_all_tables($confirm = false) {
        if (!$confirm) {
            return false;
        }

        global $wpdb;

        try {
            $wpdb->query("DROP TABLE IF EXISTS {$this->migrations_table}");
            $wpdb->query("DROP TABLE IF EXISTS {$this->batch_queue_table}");
            $wpdb->query("DROP TABLE IF EXISTS {$this->brand_categories_table}");
            $wpdb->query("DROP TABLE IF EXISTS {$this->categories_table}");
            $wpdb->query("DROP TABLE IF EXISTS {$this->brands_table}");
            $wpdb->query("DROP TABLE IF EXISTS {$this->currency_exchange_table}");

            $this->logger->warning('All ShopCommerce tables dropped');
            return true;

        } catch (Exception $e) {
            $this->logger->error('Error dropping tables', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get migration status
     *
     * @return array Migration status information
     */
    public function get_migration_status() {
        global $wpdb;

        $executed_migrations = $this->get_executed_migrations();
        $pending_migrations = array_diff(array_keys($this->migrations), $executed_migrations);

        $status = [
            'current_version' => $this->current_version,
            'total_migrations' => count($this->migrations),
            'executed_count' => count($executed_migrations),
            'pending_count' => count($pending_migrations),
            'executed_migrations' => $executed_migrations,
            'pending_migrations' => array_values($pending_migrations),
            'last_migration' => null,
        ];

        // Get last executed migration
        if (!empty($executed_migrations)) {
            $last_migration = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->migrations_table} ORDER BY executed_at DESC LIMIT 1"
            ));

            if ($last_migration) {
                $status['last_migration'] = [
                    'version' => $last_migration->version,
                    'name' => $last_migration->migration_name,
                    'executed_at' => $last_migration->executed_at,
                ];
            }
        }

        return $status;
    }

    /**
     * Check if a specific migration has been executed
     *
     * @param string $version Migration version to check
     * @return bool True if migration has been executed
     */
    public function is_migration_executed($version) {
        global $wpdb;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->migrations_table} WHERE version = %s",
            $version
        ));

        return (int)$count > 0;
    }

    /**
     * Reset migration tracking (useful for testing)
     *
     * @param bool $confirm Confirmation flag
     * @return bool Success status
     */
    public function reset_migrations($confirm = false) {
        if (!$confirm) {
            return false;
        }

        global $wpdb;

        try {
            $wpdb->query("TRUNCATE TABLE {$this->migrations_table}");
            $this->logger->warning('Migration tracking reset');
            return true;

        } catch (Exception $e) {
            $this->logger->error('Error resetting migrations', ['error' => $e->getMessage()]);
            return false;
        }
    }
}