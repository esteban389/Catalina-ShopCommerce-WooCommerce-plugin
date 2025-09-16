# ShopCommerce Product Sync Plugin

A modular WordPress plugin to synchronize products from ShopCommerce API with WooCommerce, specifically designed for Hekalsoluciones.

## Features

- **Modular Architecture**: Clean separation of concerns with dedicated classes for each functionality
- **API Integration**: Robust ShopCommerce API client with token management and error handling
- **Product Synchronization**: Intelligent product creation and updates with SKU conflict resolution
- **Cron Scheduling**: Automated synchronization with configurable intervals
- **Admin Interface**: Comprehensive dashboard with real-time monitoring and controls
- **Activity Logging**: Detailed logging with filtering and search capabilities
- **Image Management**: Smart image handling with update tracking to prevent unnecessary downloads
- **Performance Optimized**: Batch processing for efficient large catalog synchronization

## Requirements

- WordPress 5.0 or higher
- WooCommerce 3.0 or higher
- PHP 7.2 or higher
- Memory Limit: 128MB or higher
- Max Execution Time: 300 seconds or higher

## Installation

1. Upload the `shopcommerce-sync` folder to your WordPress plugins directory (`/wp-content/plugins/`)
2. Activate the plugin through the WordPress admin interface
3. Configure API credentials in Settings → ShopCommerce Sync

## Configuration

### API Credentials

1. Navigate to **ShopCommerce Sync → Settings**
2. Enter your ShopCommerce API credentials:
   - API Username: Your ShopCommerce username
   - API Password: Your ShopCommerce password
   - API Base URL: ShopCommerce API endpoint (default: https://shopcommerce.mps.com.co:7965/)
3. Click "Test Connection" to verify credentials

### Sync Settings

Configure how and when products are synchronized:

- **Sync Interval**: Choose how often to run automatic sync (recommended: Hourly)
- **Batch Size**: Number of products to process in each batch (default: 100)
- **Product Settings**: Default stock status, product visibility, image updates

### Brand Configuration

The plugin comes pre-configured with brand-specific settings:

- **HP Inc**: All categories (Accessories, Computers, Printing, Video, Servers)
- **DELL**: All categories
- **LENOVO**: All categories
- **APPLE**: Accessories and Computers
- **ASUS**: Computers
- **BOSE**: All categories
- **EPSON**: All categories
- **JBL**: All categories (if available)

## Usage

### Dashboard

The main dashboard provides:
- Overview of sync statistics and status
- Quick actions for testing connection and running sync
- Recent activity log
- System status indicators

### Sync Control

Manual sync operations:
- **Next Job Sync**: Run the next job in the queue
- **Full Sync**: Run sync for all configured brands
- **Brand Sync**: Run sync for a specific brand
- **Job Queue Management**: View and manage the sync job queue

### Settings

Comprehensive configuration options:
- API connection settings
- Sync scheduling
- Product behavior settings
- Logging configuration

## Architecture

The plugin follows a modular architecture:

```
shopcommerce-sync/
├── shopcommerce-sync.php          # Main plugin bootstrap
├── includes/
│   ├── class-shopcommerce-logger.php    # Logging and activity tracking
│   ├── class-shopcommerce-api.php       # API client and request handling
│   ├── class-shopcommerce-helpers.php   # Utility functions and helpers
│   ├── class-shopcommerce-product.php   # WooCommerce product management
│   ├── class-shopcommerce-cron.php      # Cron job scheduling
│   ├── class-shopcommerce-sync.php      # Main sync coordination
│   └── functions-admin.php             # Admin interface functions
├── admin/
│   └── templates/                       # Admin interface templates
├── assets/
│   ├── css/admin.css                   # Admin styles
│   └── js/admin.js                     # Admin JavaScript
└── logs/                               # Sync log files
```

### Core Classes

- **ShopCommerce_Logger**: Centralized logging with activity tracking
- **ShopCommerce_API**: API client with token management and error handling
- **ShopCommerce_Helpers**: Utility functions for WooCommerce operations
- **ShopCommerce_Product**: WooCommerce product creation and updates
- **ShopCommerce_Cron**: Cron job scheduling and management
- **ShopCommerce_Sync**: Main sync coordination and business logic

## Logging

The plugin provides comprehensive logging:

- **Activity Log**: Track sync operations, product changes, and errors
- **File Logging**: Detailed logs stored in `/logs/shopcommerce-sync.log`
- **WordPress Debug Log**: Critical errors logged to WordPress debug log
- **Admin Dashboard**: Recent activity displayed in dashboard widget

### Log Levels

- **Debug**: Detailed debugging information
- **Info**: General information about sync operations
- **Warning**: Warning messages that don't prevent operation
- **Error**: Errors that affect functionality
- **Critical**: Critical errors that stop operations

## Error Handling

The plugin includes robust error handling:

- **API Errors**: Automatic retry and fallback mechanisms
- **SKU Conflicts**: Intelligent conflict resolution with meta field storage
- **Connection Issues**: Graceful degradation and error reporting
- **WooCommerce Errors**: Proper validation and error messages

## Performance

Optimizations for large catalogs:

- **Batch Processing**: Products processed in configurable batches
- **Caching**: API token caching and product lookup optimization
- **Image Updates**: Smart image update tracking to prevent unnecessary downloads
- **Memory Management**: Efficient memory usage for large product catalogs

## Security

- **Input Validation**: All user input sanitized and validated
- **Nonces**: CSRF protection for all admin actions
- **Capability Checks**: Proper authorization verification
- **Secure Storage**: Sensitive data handled securely

## Extensibility

The modular architecture allows for easy extension:

- **New Providers**: Additional API providers can be added
- **Custom Processors**: Custom product processing logic
- **Additional Features**: Order handling, checkout customization, etc.
- **Third-party Integration**: Easy integration with other plugins

## Troubleshooting

### Common Issues

**API Connection Failed**
- Verify API credentials are correct
- Check if API endpoint is accessible
- Ensure server allows external connections
- Review server error logs

**Sync Not Running**
- Check if WordPress cron is working
- Verify scheduled tasks in wp-cron.php
- Check if plugin is properly activated
- Review sync logs for errors

**Product Creation Errors**
- Verify WooCommerce is active and properly configured
- Check product data format and required fields
- Review SKU conflict resolution
- Check server memory and execution time limits

### Debug Mode

Enable debug mode for troubleshooting:

1. Set `WP_DEBUG` to `true` in wp-config.php
2. Enable detailed logging in plugin settings
3. Check log files in `/logs/` directory
4. Review WordPress debug log

## Support

For support and issues:

- **GitHub Issues**: Report bugs and request features
- **Documentation**: Check inline code documentation
- **Community**: WordPress.org support forums
- **Developer**: Contact plugin developer for customizations

## Changelog

### Version 2.0.0
- Complete modular rewrite
- Improved architecture and maintainability
- Enhanced error handling and logging
- Better admin interface
- Performance optimizations
- Security improvements

## License

This plugin is licensed under the GPL-2.0+ license. See LICENSE file for details.

## Credits

Developed by Esteban Andres Murcia Acuña
- Website: https://estebanmurcia.dev/
- Email: your-email@example.com