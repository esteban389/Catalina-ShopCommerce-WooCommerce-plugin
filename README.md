# ShopCommerce Product Sync Plugin

A powerful WordPress plugin designed specifically for **Hekalsoluciones** that automatically synchronizes products from the ShopCommerce API with your WooCommerce store.

## =€ What Does This Plugin Do?

This plugin connects your WooCommerce store to the ShopCommerce product catalog and:

-  **Automatically imports products** from ShopCommerce API
-  **Keeps products updated** with latest pricing and inventory
-  **Supports major brands** like HP, Dell, Lenovo, Apple, ASUS, Bose, Epson, and JBL
-  **Handles thousands of products** efficiently with batch processing
-  **Provides detailed logging** so you can track all sync activities
-  **Works with your existing WooCommerce** setup

## <¯ Who Is This For?

This plugin is perfect for:
- **Hekalsoluciones** and their partners
- WooCommerce store owners selling computer and electronics products
- Businesses that need to keep their product catalog synchronized with external suppliers
- Anyone looking for an automated product management solution

## =Ë Requirements

Before installing, make sure you have:
- WordPress 5.0 or higher
- WooCommerce 3.0 or higher
- PHP 7.2 or higher
- Access to your website's admin panel
- Valid ShopCommerce API credentials (contact Hekalsoluciones if you don't have these)

## =à Installation

### Step 1: Upload the Plugin
1. Download the plugin zip file
2. Go to your WordPress Admin Dashboard
3. Navigate to **Plugins ’ Add New ’ Upload Plugin**
4. Choose the plugin zip file and click **Install Now**

### Step 2: Activate the Plugin
1. After installation, click **Activate Plugin**
2. You'll see a new menu item called **"ShopCommerce Sync"** in your WordPress admin menu

### Step 3: Configure Settings
1. Go to **ShopCommerce Sync ’ Settings**
2. Test your API connection
3. Configure sync intervals and batch sizes
4. Save your settings

## <® How to Use

### Dashboard Overview
The main dashboard shows you:
- **Sync Statistics**: Total products, last sync time, success rates
- **Recent Activity**: Latest sync operations and their results
- **Quick Actions**: Manual sync options and system status

### Manual Product Sync
If automatic scheduling doesn't work (see note below), you can manually sync products:

1. Go to **ShopCommerce Sync ’ Sync Control**
2. Choose your sync option:
   - **Run Next Job**: Sync the next batch of products
   - **Full Sync**: Sync all configured brands and categories
   - **Brand-Specific Sync**: Sync products from specific brands only
3. Click **Run Sync** and watch the progress

### Managing Products
1. Go to **ShopCommerce Sync ’ Products**
2. View all synchronized products
3. Filter by brand, category, or sync status
4. Perform bulk actions (publish, unpublish, delete)

### Viewing Logs
1. Go to **ShopCommerce Sync ’ Logs**
2. View detailed activity logs
3. Filter by activity type or date
4. Export logs if needed

### Managing Brands & Categories
1. Go to **ShopCommerce Sync ’ Brands & Categories**
2. Add, edit, or remove brands
3. Configure which categories to sync for each brand
4. Reset to default configuration if needed

##   Important Note About Automatic Scheduling

**During testing, we found that WordPress's automatic scheduling (cron jobs) may not work reliably on all hosting environments.** This means your products might not sync automatically as expected.

### Manual Cron Job Setup (Recommended)

To ensure reliable product synchronization, we recommend setting up a manual cron job:

#### Option 1: Using cPanel (Most Common)
1. Log in to your hosting control panel (cPanel)
2. Find the **"Cron Jobs"** icon
3. Add a new cron job with these settings:
   - **Minute**: `0`
   - **Hour**: `*` (every hour)
   - **Day**: `*`
   - **Month**: `*`
   - **Weekday**: `*`
   - **Command**: `wget -q -O - https://your-domain.com/wp-cron.php?doing_wp_cron > /dev/null 2>&1`

#### Option 2: Using Server Command Line
If you have SSH access to your server:
```bash
# Edit crontab
crontab -e

# Add this line (runs every hour)
0 * * * * wget -q -O - https://your-domain.com/wp-cron.php?doing_wp_cron > /dev/null 2>&1
```

#### Option 3: Using Online Cron Services
If your hosting doesn't support cron jobs:
1. Use a service like [EasyCron](https://www.easycron.com/) or [Cron-job.org](https://cron-job.org/)
2. Set up a job to visit: `https://your-domain.com/wp-cron.php?doing_wp_cron`
3. Schedule it to run every hour

**Replace `your-domain.com` with your actual website domain.**

## =' Troubleshooting

### Products Not Syncing?
1. **Check API Connection**: Go to **Settings ’ Test Connection**
2. **Verify Credentials**: Ensure your API credentials are correct
3. **Check Logs**: Look for error messages in **Logs** section
4. **Manual Sync**: Try running a manual sync from **Sync Control**

### Sync Taking Too Long?
1. **Reduce Batch Size**: In **Settings**, reduce the "Batch Size" to 50 or 25
2. **Check Server Resources**: Ensure your hosting has enough memory and processing power
3. **Contact Support**: If issues persist, reach out to Hekalsoluciones support

### Error Messages?
1. **Check Logs**: Detailed error information is always logged
2. **System Status**: Use the debug tools in **Sync Control**
3. **Contact Support**: Provide error details from the logs

## =Þ Support & Help

### Getting Help
- **Email Support**: Contact Hekalsoluciones support team
- **Documentation**: Check the admin dashboard for inline help
- **Debug Tools**: Use built-in diagnostics in **Sync Control**

### Reporting Issues
If you encounter problems:
1. **Check Logs**: Note any error messages
2. **System Info**: Provide your WordPress and WooCommerce versions
3. **Steps to Reproduce**: Describe what you were doing when the issue occurred
4. **Contact**: Send details to Hekalsoluciones support

## = Security

Your data security is important:
- All API communications are encrypted
- No sensitive data is stored locally
- Regular security updates are provided
- WordPress security best practices are followed

## =È Best Practices

### For Optimal Performance
- **Schedule syncs** during off-peak hours
- **Monitor logs** regularly for any issues
- **Keep plugin updated** to the latest version
- **Backup your site** before major sync operations

### For Large Catalogs
- **Use smaller batch sizes** (25-50 products)
- **Increase server memory** if possible
- **Schedule syncs** less frequently (every 2-4 hours)
- **Monitor server resources** during sync operations

## <‰ Features at a Glance

| Feature | Description |
|---------|-------------|
| **Product Sync** | Automatic import and update of products from ShopCommerce API |
| **Brand Support** | HP, Dell, Lenovo, Apple, ASUS, Bose, Epson, JBL |
| **Batch Processing** | Efficient handling of large product catalogs |
| **Real-time Logging** | Detailed tracking of all sync operations |
| **Manual Controls** | Full control over when and how products sync |
| **WooCommerce Integration** | Seamless integration with your existing store |
| **Admin Dashboard** | Easy-to-use interface for all operations |
| **Category Management** | Flexible category synchronization options |

---

## =Ä License

This plugin is licensed under GPL-2.0+. You are free to use, modify, and distribute it under the terms of the GNU General Public License.

## > Contributing

This plugin is developed specifically for Hekalsoluciones. For feature requests or bug reports, please contact the Hekalsoluciones development team.

---

**Need help?** Don't hesitate to reach out to Hekalsoluciones support for assistance with setup, configuration, or troubleshooting.