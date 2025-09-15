# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a WordPress plugin called "ShopCommerce Product Sync Plugin" that synchronizes products from an external ShopCommerce API with WooCommerce. The plugin is specifically designed for Hekalsoluciones and includes automated scheduling functionality.

## Project Structure

- `index.php` - Main plugin file with plugin header, activation/deactivation hooks, and admin debug interface
- `includes/product-sync.php` - Core synchronization logic containing:
  - API token management and authentication
  - Product catalog retrieval from ShopCommerce API
  - WordPress cron job scheduling
  - Error handling and logging

## Key Architecture

### Plugin Core
The plugin follows standard WordPress plugin architecture with:
- Main plugin file in root directory
- Includes directory for core functionality
- WordPress hooks for activation/deactivation
- Admin interface for debugging

### API Integration
- **Base URL**: `https://shopcommerce.mps.com.co:7965/`
- **Authentication**: OAuth2 password grant flow
- **Token Endpoint**: `/Token`
- **Catalog Endpoint**: `/api/Webapi/VerCatalogo`
- **Timeout**: 14 minutes for API requests

### Scheduling System
- Uses WordPress cron jobs with `wp_schedule_event()`
- Default interval: hourly
- Custom cron schedule support for every minute
- Hook name: `provider_product_sync_hook`

## Development Commands

Since this is a WordPress plugin without a build system, development involves:

1. **Plugin Testing**: Place plugin in WordPress `wp-content/plugins/` directory
2. **Debug Interface**: Access via WordPress Admin → Tools → ShopCommerce Product Sync Debug
3. **Manual Sync**: Use the debug interface to trigger sync manually
4. **Log Monitoring**: Check WordPress `debug.log` for sync operations and errors

## Configuration Notes

- API credentials are hardcoded in `includes/product-sync.php:70-71`
- The plugin includes commented-out order handler and checkout customizer classes
- Uses Crush for deployment (`.crush/` directory present)
- No package.json, composer.json, or build configuration files

## Security Considerations

- API credentials should be moved to WordPress options or environment variables
- Current implementation has hardcoded credentials in the source code
- Plugin properly checks for `ABSPATH` to prevent direct access
- WordPress security best practices should be followed for any modifications