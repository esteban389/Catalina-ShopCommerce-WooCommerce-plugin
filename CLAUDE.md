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
- **Scheduling Layer**: WordPress cron job management
- **Logging Layer**: Centralized logging with activity tracking
- **Admin Layer**: Complete admin interface with dashboard and controls

**New Features:**
- **Dynamic Configuration**: Database-driven brand and category management
- **Order Integration**: Automatic detection of external provider products in orders
- **Enhanced Logging**: Improved activity tracking and log management interface
- **Brand Management**: Dedicated interface for managing brand configurations
- **API Integration**: Automatic brand creation from API responses with duplicate detection
- **Reset Functionality**: One-click reset to default configuration

### API Integration
- **Base URL**: `https://shopcommerce.mps.com.co:7965/`
- **Authentication**: OAuth2 password grant flow
- **Token Endpoint**: `/Token`
- **Catalog Endpoint**: `/api/Webapi/VerCatalogo`
- **Timeout**: 14 minutes (840 seconds) for API requests
- **Credentials**: Currently hardcoded in `includes/class-shopcommerce-api.php:33-34`

### Scheduling System
- Uses WordPress cron jobs with `wp_schedule_event()`
- Default interval: hourly
- Custom cron schedule support for every minute
- Hook name: `provider_product_sync_hook`
- Job queue system for processing brands in batches

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

### Code Standards
- Follow WordPress coding standards
- Use proper PHP documentation blocks
- Implement WordPress security best practices
- No automated linting/building configured

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
- **ShopCommerce_Config**: Dynamic configuration management with database storage, API brand creation, and reset functionality
- **ShopCommerce_Logger**: Centralized logging with activity tracking
- **ShopCommerce_API**: API client with token management and error handling
- **ShopCommerce_Helpers**: Utility functions for WooCommerce operations
- **ShopCommerce_Product**: WooCommerce product creation and updates
- **ShopCommerce_Cron**: Cron job scheduling and management
- **ShopCommerce_Sync**: Main sync coordination and business logic

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

## Version Information

- **Current Version**: 2.5.0 (as defined in index.php plugin header)
- **Architecture Version**: 2.0.0+ (modular rewrite)
- **Compatibility**: WordPress 5.0+, WooCommerce 3.0+, PHP 7.2+
- **Note**: Version constant in index.php shows 2.0.0 but plugin header defines 2.5.0

## Database Schema

The plugin creates custom database tables for configuration management:

### Tables Created
- `wp_shopcommerce_brands`: Stores brand configurations
- `wp_shopcommerce_categories`: Stores category mappings
- `wp_shopcommerce_brand_categories`: Stores brand-category relationships

### Table Management
- Tables are created automatically on plugin activation
- Managed through ShopCommerce_Config class
- Supports dynamic brand and category configuration through admin interface
- Includes automatic initialization with default hardcoded brand/category relationships
- Supports creating brands from API responses with duplicate detection
- Provides reset functionality to restore default configuration

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