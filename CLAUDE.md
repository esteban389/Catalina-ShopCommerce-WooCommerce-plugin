# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a WordPress plugin called "ShopCommerce Product Sync Plugin" that synchronizes products from an external ShopCommerce API with WooCommerce. The plugin is specifically designed for Hekalsoluciones and features a modular architecture with automated scheduling functionality.

## Project Structure

- `index.php` - Main plugin file with plugin header, activation/deactivation hooks, and dependency initialization
- `includes/` - Core functionality classes:
  - `class-shopcommerce-logger.php` - Centralized logging and activity tracking
  - `class-shopcommerce-api.php` - API client with authentication and request handling
  - `class-shopcommerce-helpers.php` - Utility functions and WooCommerce helpers
  - `class-shopcommerce-product.php` - WooCommerce product creation and updates
  - `class-shopcommerce-cron.php` - WordPress cron job scheduling and management
  - `class-shopcommerce-cron-debug.php` - Debug utilities for cron system diagnostics
  - `class-shopcommerce-sync.php` - Main sync coordination and business logic
  - `class-shopcommerce-config.php` - Dynamic configuration management with database storage
  - `functions-admin.php` - Admin interface functions and menu setup
  - `functions-orders.php` - Order management and external provider product detection
- `admin/templates/` - Admin interface templates for dashboard, settings, products, orders, brands, sync control, and logs
- `admin/js/` - JavaScript assets for enhanced admin functionality

## Key Architecture

### Enhanced Architecture
The plugin follows a modular architecture with recent additions:

**Core Components:**
- **Bootstrap**: Main plugin file initializes all components
- **Configuration Manager**: Dynamic brand/category configuration with database storage
- **Order Management**: External provider product detection and order logging
- **API Layer**: Handles ShopCommerce API communication with OAuth2 authentication
- **Product Layer**: Manages WooCommerce product operations
- **Scheduling Layer**: WordPress cron job management with debug utilities
- **Logging Layer**: Centralized logging with activity tracking
- **Admin Layer**: Complete admin interface with dashboard and controls

**New Features:**
- **Dynamic Configuration**: Database-driven brand and category management
- **Order Integration**: Automatic detection of external provider products in orders
- **Enhanced Logging**: Improved activity tracking and log management interface
- **Brand Management**: Dedicated interface for managing brand configurations
- **API Integration**: Automatic brand creation from API responses with duplicate detection
- **Reset Functionality**: One-click reset to default configuration
- **Debug Tools**: Comprehensive cron system diagnostics and debugging

### API Integration
- **Base URL**: `https://shopcommerce.mps.com.co:7965/`
- **Authentication**: OAuth2 password grant flow
- **Token Endpoint**: `/Token`
- **Catalog Endpoint**: `/api/Webapi/VerCatalogo`
- **Brands Endpoint**: `/api/Webapi/VerMarcas`
- **Categories Endpoint**: `/api/Webapi/Ver_Categoria`
- **Timeout**: 14 minutes (840 seconds) for API requests
- **Credentials**: Currently hardcoded in `includes/class-shopcommerce-api.php:33-34`

### Scheduling System
- Uses WordPress cron jobs with `wp_schedule_event()`
- Default interval: hourly
- Custom cron schedule support for every minute, 15 minutes, and 30 minutes
- Hook name: `shopcommerce_product_sync_hook`
- Job queue system for processing brands in batches
- Debug class available for comprehensive cron diagnostics

### Brand and Category Configuration
The plugin is configured for selective synchronization:
- **HP Inc**: All categories (Accessories, Computers, Printing, Video, Servers)
- **DELL**: All categories
- **LENOVO**: All categories
- **APPLE**: Accessories and Computers
- **ASUS**: Computers
- **BOSE**: All categories
- **EPSON**: All categories
- **JBL**: All categories (if available)

## Development Commands

Since this is a WordPress plugin without a build system, development involves:

### Plugin Development
1. **Direct File Editing**: Edit PHP files directly in the plugin directory
2. **Plugin Testing**: Place plugin in WordPress `wp-content/plugins/` directory
3. **Activation**: Activate through WordPress admin interface
4. **Debug Interface**: Access via WordPress Admin → ShopCommerce Sync → Dashboard
5. **Manual Sync**: Use admin interface to trigger sync manually

### Debug Commands
The plugin includes comprehensive debug utilities:

**Cron Diagnostics:**
```php
// To run cron diagnostics
$debug = new ShopCommerce_Cron_Debug();
$debug->run_diagnostics();
```

**Cache Management:**
```php
// Clear jobs store cache (if available)
if (isset($GLOBALS['shopcommerce_jobs_store'])) {
    $GLOBALS['shopcommerce_jobs_store']->clear_cache();
}

// Clear all sync cache and reset state
if (isset($GLOBALS['shopcommerce_sync'])) {
    $GLOBALS['shopcommerce_sync']->clear_cache();
}
```

**Manual Sync Operations:**
```php
// Run full sync for all brands
if (isset($GLOBALS['shopcommerce_sync'])) {
    $result = $GLOBALS['shopcommerce_sync']->run_full_sync();
}

// Test API connection
if (isset($GLOBALS['shopcommerce_sync'])) {
    $result = $GLOBALS['shopcommerce_sync']->test_connection();
}
```

### Code Standards
- Follow WordPress coding standards
- Use proper PHP documentation blocks
- Implement WordPress security best practices
- No automated linting/building configured

### Important Development Notes
- **Global Instances**: All core classes are available as `$GLOBALS['shopcommerce_*']` variables
- **Dependencies**: Logger is always initialized first to ensure proper logging throughout the system
- **Database Tables**: Created automatically on plugin activation in `ShopCommerce_Config` and `ShopCommerce_Jobs_Store`
- **Category Codes**: Use numeric codes [1, 7, 12, 14, 18] for API communication, not descriptive names

### Admin Interface (Enhanced)
- **Dashboard**: ShopCommerce Sync → Dashboard
- **Products**: ShopCommerce Sync → Products
- **Orders**: ShopCommerce Sync → Orders (NEW)
- **Brands & Categories**: ShopCommerce Sync → Brands & Categories (NEW)
- **Sync Control**: ShopCommerce Sync → Sync Control
- **Settings**: ShopCommerce Sync → Settings
- **Logs**: ShopCommerce Sync → Logs (ENHANCED)

### Debug Tools
- **Manual Sync**: Run next job, full sync, or brand-specific sync
- **Queue Management**: View and manage sync job queue
- **Activity Logs**: View detailed sync activity and errors
- **System Status**: Check API connection and plugin health
- **Cron Diagnostics**: Comprehensive cron system debugging

## Configuration Notes

### API Configuration
- API credentials are currently hardcoded in `includes/class-shopcommerce-api.php:33-34`
- Should be moved to WordPress options or environment variables for security
- Base URL, token endpoint, and catalog endpoint are configurable

### Plugin Settings
- **Sync Interval**: Configurable cron schedule (hourly recommended)
- **Batch Size**: Number of products per batch (default: 100)
- **Product Settings**: Stock status, visibility, image update behavior
- **Logging**: Configurable log levels and file logging

### Security Considerations

### Current Implementation
- API credentials are currently hardcoded in `includes/class-shopcommerce-api.php:33-34`
- Plugin properly checks for `ABSPATH` to prevent direct access
- WordPress security best practices should be followed for modifications

### Recommendations
- Move API credentials to WordPress options or environment variables
- Implement proper input validation and sanitization
- Add nonce verification for all admin actions
- Implement capability checks for admin functions
- Consider implementing rate limiting for API calls

## File Structure Details

### Core Classes (Updated)
- **ShopCommerce_Jobs_Store**: Centralized management of brands, categories, and sync jobs with caching (5-minute TTL)
- **ShopCommerce_Config**: Dynamic configuration management with database storage, API brand creation, and reset functionality
- **ShopCommerce_Logger**: Centralized logging with activity tracking and file-based logging
- **ShopCommerce_API**: API client with OAuth2 authentication, token management, and error handling
- **ShopCommerce_Helpers**: Utility functions for WooCommerce operations and fallback job management
- **ShopCommerce_Product**: WooCommerce product creation, updates, and batch processing
- **ShopCommerce_Cron**: WordPress cron job scheduling and management with custom schedule intervals
- **ShopCommerce_Cron_Debug**: Comprehensive debug utilities for cron system diagnostics
- **ShopCommerce_Sync**: Main sync coordination and business logic with job queue processing

### Critical Architecture Patterns

**Jobs Management Hierarchy:**
1. **Primary**: `ShopCommerce_Jobs_Store` (cached, database-driven)
2. **Secondary**: `ShopCommerce_Config` (dynamic configuration)
3. **Fallback**: `ShopCommerce_Helpers` (hardcoded configuration)
4. **Ultimate Fallback**: `ShopCommerce_Cron` (static hardcoded jobs)

**Brand-Category Handling:**
- Brands with "all categories" must send all available category codes `[1, 7, 12, 14, 18]` to API
- Empty arrays are NOT sent - they prevent the `X-CATEGORIA` header from being added
- Fixed in: `ShopCommerce_Jobs_Store::get_jobs()`, `ShopCommerce_Config::build_jobs_list()`, `ShopCommerce_Helpers::build_jobs_list()`

**API Communication:**
- **Authentication**: OAuth2 password grant with token caching
- **Headers**: `Authorization: Bearer {token}`, `X-MARKS: {brand}`, `X-CATEGORIA: {comma_separated_codes}`
- **Timeout**: 840 seconds (14 minutes)
- **Category Codes**: Numeric only [1=Accessories, 7=Computers, 12=Printing, 14=Video, 18=Servers]

### Additional Functions
- **functions-orders.php**: Order management and external provider product detection
- **functions-admin.php**: Admin interface functions and menu setup

### Admin Interface (Enhanced)
- **Dashboard**: Overview with statistics, quick actions, and recent activity
- **Products**: Product management and filtering
- **Orders**: Order management with external provider detection (NEW)
- **Brands & Categories**: Brand and category configuration management (NEW)
- **Sync Control**: Manual sync operations and queue management
- **Settings**: Configuration for API, sync behavior, and logging
- **Logs**: Enhanced activity log viewing and management (ENHANCED)

### Logging System
- **Activity Log**: Tracks sync operations, product changes, and errors
- **File Logging**: Detailed logs stored in `/logs/shopcommerce-sync.log`
- **WordPress Debug Log**: Critical errors logged to WordPress debug log
- **Admin Dashboard**: Recent activity displayed in dashboard widget

## Performance Considerations

- **Batch Processing**: Products processed in configurable batches to avoid timeouts
- **Caching**: API token caching and product lookup optimization
- **Memory Management**: Efficient memory usage for large product catalogs
- **Image Updates**: Smart image update tracking to prevent unnecessary downloads
- **Error Handling**: Graceful degradation and retry mechanisms for API failures

## Testing Guidelines

### Manual Testing
1. **API Connection**: Test connection in admin interface
2. **Sync Operations**: Run manual sync for individual brands
3. **Product Verification**: Check WooCommerce products are created/updated correctly
4. **Log Review**: Monitor logs for errors and performance issues

### Debug Mode
1. Enable `WP_DEBUG` in wp-config.php
2. Enable detailed logging in plugin settings
3. Check log files in `/logs/` directory
4. Review WordPress debug log for critical errors
5. Use ShopCommerce_Cron_Debug class for cron diagnostics

## Version Information

- **Current Version**: 2.5.0 (as defined in index.php plugin header)
- **Architecture Version**: 2.0.0+ (modular rewrite)
- **Compatibility**: WordPress 5.0+, WooCommerce 3.0+, PHP 7.2+
- **Note**: Version constant `SHOPCOMMERCE_SYNC_VERSION` in index.php shows 2.0.0 but plugin header defines 2.5.0
- **Recent Architecture Changes**: Jobs Store integration, enhanced brand/category management, improved caching

### Critical File Locations for Category Management
- **includes/class-shopcommerce-jobs-store.php:202-213** - Jobs Store category fetching logic
- **includes/class-shopcommerce-config.php:494-505** - Config manager category fetching logic
- **includes/class-shopcommerce-helpers.php:795-807** - Helpers fallback category logic
- **includes/class-shopcommerce-cron.php:331-365** - Cron scheduler hardcoded fallback jobs
- **includes/class-shopcommerce-api.php:~100-120** - API client category header implementation

## Database Schema

The plugin creates custom database tables for configuration and job management:

### Tables Created
- `wp_shopcommerce_brands`: Stores brand configurations (id, name, slug, description, is_active)
- `wp_shopcommerce_categories`: Stores category mappings (id, name, code, description, is_active)
- `wp_shopcommerce_brand_categories`: Stores brand-category relationships (brand_id, category_id)

### Table Management
- Tables are created automatically on plugin activation by both `ShopCommerce_Jobs_Store` and `ShopCommerce_Config`
- Managed through centralized Jobs Store with 5-minute caching
- Supports dynamic brand and category configuration through admin interface
- Includes automatic initialization with default brand/category relationships:
  - **HP Inc**, **DELL**, **LENOVO**: All categories [1, 7, 12, 14, 18]
  - **APPLE**: Accessories and Computers [1, 7]
  - **ASUS**: Computers [7]
  - **BOSE**, **EPSON**, **JBL**: All categories [1, 7, 12, 14, 18]
- Supports creating brands from API responses with duplicate detection
- Provides reset functionality to restore default configuration
- **Important**: Both classes create tables independently - ensure proper activation sequence

## Important Implementation Details

### WooCommerce Integration
- **Order Hooks**: Integrates with WooCommerce order lifecycle (creation, processing, completion)
- **Product Detection**: Automatically detects external provider products in orders
- **Metadata Tracking**: Stores provider information in order item metadata
- **Logging**: Comprehensive logging of order processing and external product detection

### Global Instance Management
- All core classes are instantiated globally during plugin initialization
- Available as `$GLOBALS['shopcommerce_*']` variables
- Follows dependency injection pattern for class instantiation
- Logger is always initialized first to ensure proper logging throughout the system

### AJAX Endpoints
The plugin provides extensive AJAX functionality through functions-admin.php:

**Core Operations:**
- Connection testing and manual sync
- Queue management and job rebuilding
- Settings updates and cache clearing
- Activity log retrieval and management

**Brand & Category Management:**
- CRUD operations for brands and categories
- API-driven brand and category synchronization
- Reset functionality for default configuration

**Product Management:**
- Bulk product operations (trash, delete, publish, draft)
- Product edit history tracking
- Product details modal with comprehensive information
- Duplicate product cleanup utilities

**Order Management:**
- Order retrieval with ShopCommerce metadata filtering
- Order details with external provider product detection
- Metadata updates for existing orders
- Incomplete orders with external product tracking

### Debug and Diagnostics
- **ShopCommerce_Cron_Debug**: Comprehensive cron system diagnostics
- **XML Attributes Testing**: Test parsing and formatting of XML attributes
- **System Health Monitoring**: API connection testing and plugin status checks
- **Activity Logging**: Detailed activity tracking with filtering and pagination