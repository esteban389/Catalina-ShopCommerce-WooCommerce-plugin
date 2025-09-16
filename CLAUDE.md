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
  - `functions-admin.php` - Admin interface functions and menu setup
- `admin/templates/` - Admin interface templates for dashboard, settings, products, and sync control

## Key Architecture

### Modular Design
The plugin follows a modular architecture with clear separation of concerns:
- **Bootstrap**: Main plugin file initializes all components
- **API Layer**: Handles ShopCommerce API communication with OAuth2 authentication
- **Product Layer**: Manages WooCommerce product operations
- **Scheduling Layer**: WordPress cron job management
- **Logging Layer**: Centralized logging with activity tracking
- **Admin Layer**: Complete admin interface with dashboard and controls

### API Integration
- **Base URL**: `https://shopcommerce.mps.com.co:7965/`
- **Authentication**: OAuth2 password grant flow
- **Token Endpoint**: `/Token`
- **Catalog Endpoint**: `/api/Webapi/VerCatalogo`
- **Timeout**: 14 minutes (840 seconds) for API requests
- **Credentials**: Currently hardcoded in `class-shopcommerce-api.php:33-34`

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

### Plugin Testing
1. **Plugin Installation**: Place plugin in WordPress `wp-content/plugins/` directory
2. **Activation**: Activate through WordPress admin interface
3. **Debug Interface**: Access via WordPress Admin → ShopCommerce Sync → Dashboard
4. **Manual Sync**: Use admin interface to trigger sync manually

### Admin Interface
- **Dashboard**: ShopCommerce Sync → Dashboard
- **Products**: ShopCommerce Sync → Products
- **Sync Control**: ShopCommerce Sync → Sync Control
- **Settings**: ShopCommerce Sync → Settings

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
- API credentials should be moved to WordPress options or environment variables
- Current implementation has hardcoded credentials in source code
- Plugin properly checks for `ABSPATH` to prevent direct access
- WordPress security best practices should be followed for modifications

## File Structure Details

### Core Classes
- **ShopCommerce_Logger**: Handles logging with different levels (debug, info, warning, error, critical)
- **ShopCommerce_API**: Manages API communication with token caching and retry logic
- **ShopCommerce_Helpers**: Utility functions for WooCommerce operations and data processing
- **ShopCommerce_Product**: Handles WooCommerce product creation, updates, and SKU conflict resolution
- **ShopCommerce_Cron**: Manages WordPress cron scheduling and job queue
- **ShopCommerce_Sync**: Coordinates the entire sync workflow and business logic

### Admin Interface
- **Dashboard**: Overview with statistics, quick actions, and recent activity
- **Products**: Product management and filtering
- **Sync Control**: Manual sync operations and queue management
- **Settings**: Configuration for API, sync behavior, and logging

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

- **Current Version**: 2.1.0 (as defined in index.php)
- **Architecture**: Modular rewrite (v2.0.0+)
- **Compatibility**: WordPress 5.0+, WooCommerce 3.0+, PHP 7.2+