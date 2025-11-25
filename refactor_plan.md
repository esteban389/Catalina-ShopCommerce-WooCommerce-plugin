# ShopCommerce Plugin Refactoring Plan

## Overview

This plan outlines a systematic refactoring approach to transform the ShopCommerce plugin from a functional but tightly-coupled codebase into a maintainable, testable, and scalable WordPress plugin following industry best practices.

## Phase 1: Foundation - Dependency Injection Container

### 1.1 Create Service Container Class

**File**: `includes/class-shopcommerce-container.php`

**Purpose**: Replace all `$GLOBALS` usage with a proper dependency injection container.

**Implementation**:

- Create `ShopCommerce_Container` class with:
  - `register(string $key, callable $factory)` method to register services
  - `get(string $key)` method to retrieve services (with singleton pattern)
  - `has(string $key)` method to check if service exists
  - `resolve()` method to auto-resolve dependencies using reflection
- Store services in private `$services` array
- Store resolved instances in private `$instances` array for singleton pattern
- Add `getLogger()`, `getApiClient()`, `getProductHandler()`, etc. as convenience methods

**Dependencies**: None (foundation class)

**Migration Strategy**:

- Create container class first
- Update `index.php` to instantiate container instead of globals
- Gradually migrate classes to use container instead of globals
- Keep globals as fallback during transition period

### 1.2 Update Plugin Initialization

**File**: `index.php`

**Changes**:

- Replace `shopcommerce_sync_init()` function to:
  - Create `ShopCommerce_Container` instance
  - Register all services in container with factory closures
  - Store container instance in single global: `$GLOBALS['shopcommerce_container']`
- Update service registration order to respect dependencies:

  1. Logger (no dependencies)
  2. Migrator (depends on Logger)
  3. Config (depends on Logger)
  4. Jobs Store (depends on Logger)
  5. API Client (depends on Logger)
  6. Helpers (depends on Logger)
  7. Product Handler (depends on Logger, Helpers)
  8. Cron Scheduler (depends on Logger, Jobs Store)
  9. Sync Handler (depends on Logger, API, Product Handler, Cron, Jobs Store)
  10. Batch Processor (depends on Logger, Jobs Store, Product Handler)

**Example Registration**:

```php
$container->register('logger', function() {
    return new ShopCommerce_Logger();
});

$container->register('product_handler', function($container) {
    return new ShopCommerce_Product(
        $container->get('logger'),
        $container->get('helpers')
    );
});
```

### 1.3 Create Container Interface

**File**: `includes/interfaces/interface-shopcommerce-container.php`

**Purpose**: Define contract for dependency injection container.

**Methods**:

- `register(string $key, callable $factory): void`
- `get(string $key): object`
- `has(string $key): bool`

## Phase 2: Extract Product Responsibilities

### 2.1 Create Product Mapper Class

**File**: `includes/class-shopcommerce-product-mapper.php`

**Purpose**: Extract all data transformation logic from `ShopCommerce_Product`.

**Extract from `ShopCommerce_Product`**:

- `map_product_data()` method (lines 521-609)
- `calculate_product_price()` method (lines 43-79)
- `convert_to_cop()` method (lines 88-137)

**New Class Structure**:

```php
class ShopCommerce_Product_Mapper {
    private $logger;
    private $config;
    
    public function map(array $product_data, string $brand, ?string $sku = null): array
    public function calculatePrice(float $original_price, int $category_code, string $currency_code = 'COP'): float
    public function convertToCop(float $price, string $currency_code): float
    private function buildDescription(array $product_data): string
    private function extractMetadata(array $product_data, string $brand, ?string $sku): array
}
```

**Dependencies**: Logger, Config Manager

**Update `ShopCommerce_Product`**:

- Remove extracted methods
- Inject `ShopCommerce_Product_Mapper` in constructor
- Update `create_product()` and `update_product()` to use mapper

### 2.2 Create Product Duplicate Checker Class

**File**: `includes/class-shopcommerce-product-duplicate-checker.php`

**Purpose**: Extract duplicate detection logic.

**Extract from `ShopCommerce_Product`**:

- `check_for_duplicates()` method (lines 221-321)
- All duplicate detection methods (wp_query, wc_sku, db_sku, shopcommerce_sku, external_provider_sku)

**New Class Structure**:

```php
class ShopCommerce_Product_Duplicate_Checker {
    private $logger;
    
    public function checkForDuplicates(string $sku): array
    public function findByWpQuery(string $sku): ?int
    public function findByWcSku(string $sku): ?int
    public function findByDbSku(string $sku): ?int
    public function findByShopCommerceSku(string $sku): ?int
    public function findByExternalProviderSku(string $sku): ?int
}
```

**Dependencies**: Logger

**Update `ShopCommerce_Product`**:

- Remove `check_for_duplicates()` method
- Inject `ShopCommerce_Product_Duplicate_Checker` in constructor
- Update `process_product()` and `create_product_safely()` to use checker

### 2.3 Create Product Image Handler Class

**File**: `includes/class-shopcommerce-product-image-handler.php`

**Purpose**: Extract image management logic.

**Extract from `ShopCommerce_Product`**:

- `handle_image_update()` method (lines 669-697)
- Image-related logic from `apply_product_data()`

**Extract from `ShopCommerce_Helpers`**:

- `attach_product_image()` method (if exists)

**New Class Structure**:

```php
class ShopCommerce_Product_Image_Handler {
    private $logger;
    private $helpers;
    
    public function handleImageUpdate(WC_Product $product, string $image_url, string $product_name): bool
    public function attachImage(string $image_url, string $product_name): ?int
    private function shouldUpdateImage(WC_Product $product, string $image_url): bool
    private function getCurrentImageUrl(int $image_id): ?string
}
```

**Dependencies**: Logger, Helpers

**Update `ShopCommerce_Product`**:

- Remove `handle_image_update()` method
- Inject `ShopCommerce_Product_Image_Handler` in constructor
- Update `apply_product_data()` to use image handler

### 2.4 Create Product Creator Class

**File**: `includes/class-shopcommerce-product-creator.php`

**Purpose**: Extract product creation logic.

**Extract from `ShopCommerce_Product`**:

- `create_product()` method (lines 367-418)
- `create_product_safely()` method (lines 330-358)

**New Class Structure**:

```php
class ShopCommerce_Product_Creator {
    private $logger;
    private $mapper;
    private $duplicate_checker;
    private $image_handler;
    
    public function create(array $product_data, string $brand): array
    public function createSafely(array $product_data, string $brand): array
    private function createProductObject(array $mapped_data, string $brand): WC_Product_Simple
    private function validateSku(string $sku): string
}
```

**Dependencies**: Logger, Product Mapper, Duplicate Checker, Image Handler

**Update `ShopCommerce_Product`**:

- Remove creation methods
- Inject `ShopCommerce_Product_Creator` in constructor
- Update `process_product()` to delegate to creator

### 2.5 Create Product Updater Class

**File**: `includes/class-shopcommerce-product-updater.php`

**Purpose**: Extract product update logic.

**Extract from `ShopCommerce_Product`**:

- `update_product()` method (lines 428-511)
- `apply_product_data()` method (lines 619-660)

**New Class Structure**:

```php
class ShopCommerce_Product_Updater {
    private $logger;
    private $mapper;
    private $image_handler;
    
    public function update(WC_Product $product, array $product_data, string $brand): array
    public function applyProductData(WC_Product $product, array $mapped_data, array $original_data, string $brand): void
    private function applyTaxSettings(WC_Product $product, array $mapped_data, string $brand): void
    private function applyCategories(WC_Product $product, ?string $category_name): void
    private function applyMetadata(WC_Product $product, array $metadata): void
}
```

**Dependencies**: Logger, Product Mapper, Image Handler

**Update `ShopCommerce_Product`**:

- Remove update methods
- Inject `ShopCommerce_Product_Updater` in constructor
- Update `process_product()` to delegate to updater

### 2.6 Refactor ShopCommerce_Product to Orchestrator

**File**: `includes/class-shopcommerce-product.php`

**Purpose**: Transform into a thin orchestrator that coordinates specialized classes.

**New Structure**:

```php
class ShopCommerce_Product {
    private $logger;
    private $mapper;
    private $duplicate_checker;
    private $creator;
    private $updater;
    
    public function processProduct(array $product_data, string $brand): array
    public function processBatch(array $products, string $brand): array
    public function getStatistics(): array
    public function cleanupDuplicateProducts(bool $dry_run = true): array
}
```

**Methods to Keep**:

- `process_product()` - orchestrates creation/update
- `process_batch()` - batch processing
- `get_statistics()` - statistics aggregation
- `cleanup_duplicate_products()` - cleanup utility

**Methods to Remove**:

- All extracted methods (moved to specialized classes)

## Phase 3: Standardize Error Handling

### 3.1 Create Result Class

**File**: `includes/class-shopcommerce-result.php`

**Purpose**: Standardize operation results across the plugin.

**Structure**:

```php
class ShopCommerce_Result {
    private $success;
    private $data;
    private $error;
    private $errors;
    
    public static function success($data = null): self
    public static function failure(string $error, array $errors = []): self
    public function isSuccess(): bool
    public function isFailure(): bool
    public function getData()
    public function getError(): ?string
    public function getErrors(): array
    public function toArray(): array
}
```

**Usage Pattern**:

```php
// Instead of: return ['success' => true, 'product_id' => 123];
return ShopCommerce_Result::success(['product_id' => 123]);

// Instead of: return ['success' => false, 'error' => 'Message'];
return ShopCommerce_Result::failure('Message');
```

### 3.2 Create Custom Exception Classes

**Files**:

- `includes/exceptions/class-shopcommerce-exception.php` (base)
- `includes/exceptions/class-shopcommerce-api-exception.php`
- `includes/exceptions/class-shopcommerce-product-exception.php`
- `includes/exceptions/class-shopcommerce-validation-exception.php`

**Base Exception Structure**:

```php
class ShopCommerce_Exception extends Exception {
    protected $context;
    
    public function __construct(string $message, array $context = [], int $code = 0, Throwable $previous = null)
    public function getContext(): array
    public function toArray(): array
}
```

**Specialized Exceptions**:

- `ShopCommerce_Api_Exception` - API communication errors
- `ShopCommerce_Product_Exception` - Product operation errors
- `ShopCommerce_Validation_Exception` - Validation errors

### 3.3 Update All Methods to Use Result Pattern

**Files**: All class files in `includes/`

**Changes**:

- Replace all `return ['success' => ...]` patterns with `ShopCommerce_Result`
- Replace silent failures with exceptions
- Update error handling to catch exceptions and convert to Results
- Update logging to use exception context

**Example Migration**:

```php
// Before:
public function process_product($product_data, $brand) {
    $results = ['success' => false, 'error' => null];
    try {
        // ... logic
        $results['success'] = true;
    } catch (Exception $e) {
        $results['error'] = $e->getMessage();
    }
    return $results;
}

// After:
public function processProduct(array $product_data, string $brand): ShopCommerce_Result {
    try {
        // ... logic
        return ShopCommerce_Result::success(['product_id' => $id]);
    } catch (ShopCommerce_Product_Exception $e) {
        $this->logger->error('Product processing failed', $e->getContext());
        return ShopCommerce_Result::failure($e->getMessage(), $e->getContext());
    }
}
```

## Phase 4: Centralize Configuration

### 4.1 Enhance Configuration Manager

**File**: `includes/class-shopcommerce-config.php`

**Add Methods**:

- `getTaxStatus(): string` - Get default tax status for products
- `getTaxClass(): string` - Get default tax class for products
- `getDefaultStockStatus(): string` - Get default stock status
- `getBatchSize(): int` - Get batch processing size
- `getMarkupPercentage(int $category_code): float` - Already exists, ensure it's used everywhere
- `get(string $key, $default = null)` - Generic getter with caching
- `set(string $key, $value): void` - Generic setter

**Remove Hardcoded Values**:

- Replace `0.15` markup default with `getMarkupPercentage()`
- Replace `500` batch size with `getBatchSize()`
- Replace `'instock'` with `getDefaultStockStatus()`

### 4.2 Create Configuration Constants Class

**File**: `includes/class-shopcommerce-config-constants.php`

**Purpose**: Define all configuration keys as constants.

**Structure**:

```php
class ShopCommerce_Config_Constants {
    // Tax Settings
    const TAX_STATUS = 'shopcommerce_product_tax_status';
    const TAX_CLASS = 'shopcommerce_product_tax_class';
    
    // Product Settings
    const DEFAULT_STOCK_STATUS = 'shopcommerce_default_stock_status';
    const BATCH_SIZE = 'shopcommerce_batch_size';
    
    // Markup Settings
    const DEFAULT_MARKUP = 'shopcommerce_default_markup_percentage';
    
    // API Settings
    const API_USERNAME = 'shopcommerce_api_username';
    const API_PASSWORD = 'shopcommerce_api_password';
    
    // Default Values
    const DEFAULT_TAX_STATUS = 'taxable';
    const DEFAULT_TAX_CLASS = '';
    const DEFAULT_STOCK_STATUS_VALUE = 'instock';
    const DEFAULT_BATCH_SIZE = 500;
    const DEFAULT_MARKUP_VALUE = 15.0;
}
```

**Usage**:

```php
$tax_status = $config->get(ShopCommerce_Config_Constants::TAX_STATUS, ShopCommerce_Config_Constants::DEFAULT_TAX_STATUS);
```

## Phase 5: Database Schema Management

### 5.1 Enhance Migrator Class

**File**: `includes/class-shopcommerce-migrator.php`

**Purpose**: Centralize all database schema management.

**Add Methods**:

- `getCurrentVersion(): string` - Get current schema version
- `getTargetVersion(): string` - Get target schema version
- `migrate(): void` - Run all pending migrations
- `rollback(string $version): void` - Rollback to specific version
- `createMigration(string $name): void` - Generate migration file

**Migration File Structure**:

- Create `migrations/` directory
- Each migration file: `YYYYMMDDHHMMSS_description.php`
- Migration class: `ShopCommerce_Migration_YYYYMMDDHHMMSS_Description`

**Example Migration**:

```php
class ShopCommerce_Migration_20240101000000_CreateBrandsTable {
    public function up(): void {
        // Create table
    }
    
    public function down(): void {
        // Drop table
    }
}
```

### 5.2 Consolidate Table Creation

**Files**:

- `includes/class-shopcommerce-config.php` (remove table creation)
- `includes/class-shopcommerce-jobs-store.php` (remove table creation)

**Action**:

- Move all `create_*_table()` methods to Migrator
- Create migration files for each table
- Remove table creation from class constructors
- Ensure Migrator runs before any class that needs tables

## Phase 6: Add Interfaces and Abstractions

### 6.1 Create Core Interfaces

**Files**:

- `includes/interfaces/interface-shopcommerce-logger.php`
- `includes/interfaces/interface-shopcommerce-api-client.php`
- `includes/interfaces/interface-shopcommerce-product-handler.php`
- `includes/interfaces/interface-shopcommerce-config-manager.php`

**Logger Interface**:

```php
interface ShopCommerce_Logger_Interface {
    public function debug(string $message, array $context = []): void;
    public function info(string $message, array $context = []): void;
    public function warning(string $message, array $context = []): void;
    public function error(string $message, array $context = []): void;
    public function logActivity(string $type, string $message, array $context = []): void;
}
```

**API Client Interface**:

```php
interface ShopCommerce_Api_Client_Interface {
    public function authenticate(): bool;
    public function getCatalog(string $brand, array $categories): ShopCommerce_Result;
    public function realizarPedido(array $data): ShopCommerce_Result;
}
```

### 6.2 Update Classes to Implement Interfaces

**Files**: All class files

**Changes**:

- Add `implements` clause to class declarations
- Ensure all interface methods are implemented
- Update type hints to use interfaces instead of concrete classes

**Example**:

```php
// Before:
public function __construct($logger, $api_client) {
    $this->logger = $logger;
    $this->api_client = $api_client;
}

// After:
public function __construct(
    ShopCommerce_Logger_Interface $logger,
    ShopCommerce_Api_Client_Interface $api_client
) {
    $this->logger = $logger;
    $this->api_client = $api_client;
}
```

## Phase 7: Input Validation

### 7.1 Create Validator Class

**File**: `includes/class-shopcommerce-validator.php`

**Purpose**: Centralize all input validation logic.

**Structure**:

```php
class ShopCommerce_Validator {
    public function validateProductData(array $data): ShopCommerce_Result
    public function validateSku(string $sku): ShopCommerce_Result
    public function validatePrice(float $price): ShopCommerce_Result
    public function validateBrand(string $brand): ShopCommerce_Result
    public function validateCategoryCode(int $code): ShopCommerce_Result
    private function validateRequiredFields(array $data, array $required): array
    private function validateFieldTypes(array $data, array $rules): array
}
```

**Validation Rules**:

- Product data must have: Name, Sku, precio, Quantity
- SKU must be non-empty string
- Price must be positive number
- Brand must be non-empty string
- Category code must be valid integer (1, 7, 12, 14, 18)

### 7.2 Add API Response Validation

**File**: `includes/class-shopcommerce-api-response-validator.php`

**Purpose**: Validate API responses before processing.

**Structure**:

```php
class ShopCommerce_Api_Response_Validator {
    public function validateCatalogResponse(array $response): ShopCommerce_Result
    public function validateProduct(array $product): ShopCommerce_Result
    private function validateProductStructure(array $product): array
}
```

**Validation**:

- Check response structure
- Validate each product has required fields
- Validate data types
- Return validation errors in Result

### 7.3 Integrate Validation

**Files**:

- `includes/class-shopcommerce-api.php` - Validate responses
- `includes/class-shopcommerce-product-mapper.php` - Validate input data
- `includes/class-shopcommerce-product-creator.php` - Validate before creation

**Integration Points**:

- API client validates responses before returning
- Product mapper validates data before mapping
- Product creator validates before creating

## Phase 8: Security Improvements

### 8.1 Move API Credentials to Secure Storage

**File**: `includes/class-shopcommerce-api.php`

**Changes**:

- Remove hardcoded credentials (lines 33-34)
- Use `get_option()` with encryption
- Add `updateApiCredentials(string $username, string $password): void` method
- Encrypt password using `wp_salt()` before storage

**Storage**:

- Store in WordPress options with `autoload = false`
- Use `update_option('shopcommerce_api_username', $username, false)`
- Use `update_option('shopcommerce_api_password', $encrypted_password, false)`

### 8.2 Add Nonce Verification

**File**: `includes/functions-admin.php`

**Changes**:

- Add nonce field to all admin forms
- Verify nonce in all AJAX handlers
- Add `wp_verify_nonce()` checks before processing

**Example**:

```php
function shopcommerce_ajax_handler() {
    check_ajax_referer('shopcommerce_admin_nonce', 'nonce');
    // ... handler logic
}
```

### 8.3 Enhance Input Sanitization

**Files**: All files that accept user input

**Changes**:

- Use `sanitize_text_field()` for text inputs
- Use `sanitize_email()` for emails
- Use `absint()` for integers
- Use `floatval()` with validation for floats
- Use `wp_kses_post()` for HTML content

**Create Sanitizer Helper**:

**File**: `includes/class-shopcommerce-sanitizer.php`

```php
class ShopCommerce_Sanitizer {
    public static function sanitizeProductData(array $data): array
    public static function sanitizeSku(string $sku): string
    public static function sanitizePrice($price): float
    public static function sanitizeText(string $text): string
}
```

## Phase 9: Performance Optimization

### 9.1 Implement Query Caching

**File**: `includes/class-shopcommerce-cache.php`

**Purpose**: Cache expensive database queries.

**Structure**:

```php
class ShopCommerce_Cache {
    private $cache_group = 'shopcommerce';
    
    public function get(string $key)
    public function set(string $key, $value, int $expiration = 3600): bool
    public function delete(string $key): bool
    public function clear(): void
    private function buildKey(string $key): string
}
```

**Cache Keys**:

- Product lookups by SKU
- Category lookups by name
- Brand configurations
- API token (already implemented, ensure consistency)

### 9.2 Optimize Duplicate Checking

**File**: `includes/class-shopcommerce-product-duplicate-checker.php`

**Changes**:

- Cache results of duplicate checks
- Use single query with multiple methods in UNION
- Add index hints for performance
- Limit to most efficient method first (wc_get_product_id_by_sku)

### 9.3 Batch Database Operations

**Files**: All classes with database operations

**Changes**:

- Use `$wpdb->prepare()` for all queries
- Batch insert/update operations where possible
- Use transactions for multi-step operations
- Add database query logging in debug mode

## Phase 10: Code Quality and Standards

### 10.1 Standardize Naming Conventions

**Files**: All files

**Changes**:

- Class names: `ShopCommerce_ClassName` (already correct)
- Method names: `camelCase` for private, `camelCase` for public (WordPress standard)
- Variable names: `snake_case` (WordPress standard)
- Constants: `SHOPCOMMERCE_CONSTANT_NAME`

**Rename Methods**:

- `process_product()` → `processProduct()`
- `create_product()` → `createProduct()`
- `update_product()` → `updateProduct()`
- `check_for_duplicates()` → `checkForDuplicates()`

### 10.2 Add Type Declarations

**Files**: All class files

**Changes**:

- Add parameter type hints to all methods
- Add return type hints where possible
- Use strict types: `declare(strict_types=1);` at top of files
- Use union types (PHP 8+) where appropriate

**Example**:

```php
declare(strict_types=1);

class ShopCommerce_Product {
    public function processProduct(array $product_data, string $brand): ShopCommerce_Result
}
```

### 10.3 Add PHPDoc Blocks

**Files**: All class files

**Changes**:

- Add `@param` for all parameters
- Add `@return` for all methods
- Add `@throws` for exceptions
- Add `@since` for version tracking
- Add class-level `@package` and `@subpackage`

### 10.4 Organize File Structure

**New Directory Structure**:

```
includes/
  interfaces/
    interface-shopcommerce-*.php
  exceptions/
    class-shopcommerce-*.php
  migrations/
    YYYYMMDDHHMMSS_*.php
  class-shopcommerce-*.php
```

## Phase 11: Testing Infrastructure

### 11.1 Create Test Base Class

**File**: `tests/class-shopcommerce-test-base.php`

**Purpose**: Base class for all tests.

**Structure**:

```php
abstract class ShopCommerce_Test_Base extends WP_UnitTestCase {
    protected $container;
    
    public function setUp(): void {
        parent::setUp();
        $this->container = new ShopCommerce_Container();
        // Register test doubles
    }
    
    public function tearDown(): void {
        parent::tearDown();
        // Cleanup
    }
}
```

### 11.2 Create Mock Classes

**Files**:

- `tests/mocks/class-mock-logger.php`
- `tests/mocks/class-mock-api-client.php`

**Purpose**: Provide test doubles for dependencies.

## Implementation Order

1. **Phase 1** (Container) - Foundation for everything else
2. **Phase 4** (Configuration) - Needed by other phases
3. **Phase 3** (Error Handling) - Needed before refactoring
4. **Phase 2** (Product Responsibilities) - Major refactoring
5. **Phase 5** (Database) - Infrastructure
6. **Phase 6** (Interfaces) - Abstractions
7. **Phase 7** (Validation) - Data integrity
8. **Phase 8** (Security) - Critical
9. **Phase 9** (Performance) - Optimization
10. **Phase 10** (Code Quality) - Ongoing
11. **Phase 11** (Testing) - Parallel development

## Migration Strategy

### Backward Compatibility

- Keep old method names as aliases during transition
- Maintain `$GLOBALS` as fallback for 2 versions
- Use feature flags for new implementations
- Gradual migration: one class at a time

### Testing Strategy

- Write tests for new classes before refactoring
- Run existing functionality tests after each phase
- Use integration tests to verify end-to-end flow
- Maintain test coverage above 70%

### Rollback Plan

- Each phase is independently reversible
- Keep git branches for each phase
- Tag releases after each completed phase
- Document breaking changes in CHANGELOG.md