<?php
/**
 * Settings template for ShopCommerce Sync
 *
 * @package ShopCommerce_Sync
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap shopcommerce-admin">
    <h1>ShopCommerce Settings</h1>

    <form method="post" action="options.php" id="shopcommerce-settings-form">
        <?php
        settings_fields('shopcommerce_settings_group');
        do_settings_sections('shopcommerce-sync-settings');
        ?>
    </form>

    <!-- API Configuration -->
    <div class="settings-section">
        <h2>API Configuration</h2>
        <p>Configure your ShopCommerce API credentials and connection settings.</p>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="shopcommerce_api_username">API Username</label>
                </th>
                <td>
                    <input type="text" id="shopcommerce_api_username" name="shopcommerce_api_username"
                           value="<?php echo esc_attr(get_option('shopcommerce_api_username', 'pruebas@hekalsoluciones.com')); ?>"
                           class="regular-text">
                    <p class="description">Your ShopCommerce API username.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="shopcommerce_api_password">API Password</label>
                </th>
                <td>
                    <input type="password" id="shopcommerce_api_password" name="shopcommerce_api_password"
                           value="<?php echo esc_attr(get_option('shopcommerce_api_password', '')); ?>"
                           class="regular-text">
                    <p class="description">Your ShopCommerce API password.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="shopcommerce_api_base_url">API Base URL</label>
                </th>
                <td>
                    <input type="url" id="shopcommerce_api_base_url" name="shopcommerce_api_base_url"
                           value="<?php echo esc_attr(get_option('shopcommerce_api_base_url', 'https://shopcommerce.mps.com.co:7965/')); ?>"
                           class="regular-text">
                    <p class="description">Base URL for the ShopCommerce API.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="shopcommerce_api_timeout">API Timeout (seconds)</label>
                </th>
                <td>
                    <input type="number" id="shopcommerce_api_timeout" name="shopcommerce_api_timeout"
                           value="<?php echo esc_attr(get_option('shopcommerce_api_timeout', '840')); ?>"
                           min="60" max="3600" class="small-text">
                    <p class="description">Timeout for API requests in seconds (default: 840).</p>
                </td>
            </tr>
        </table>

        <div class="api-test-section">
            <h3>API Connection Test</h3>
            <button type="button" class="button button-secondary" id="test-api-settings-btn">Test Connection</button>
            <div id="api-test-result"></div>
        </div>
    </div>

    <!-- Sync Settings -->
    <div class="settings-section">
        <h2>Sync Settings</h2>
        <p>Configure how and when products are synchronized.</p>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="shopcommerce_cron_interval">Sync Interval</label>
                </th>
                <td>
                    <select id="shopcommerce_cron_interval" name="shopcommerce_cron_interval">
                        <option value="every_minute" <?php selected(get_option('shopcommerce_cron_interval'), 'every_minute'); ?>>
                            Every Minute (for testing)
                        </option>
                        <option value="every_15_minutes" <?php selected(get_option('shopcommerce_cron_interval'), 'every_15_minutes'); ?>>
                            Every 15 Minutes
                        </option>
                        <option value="every_30_minutes" <?php selected(get_option('shopcommerce_cron_interval'), 'every_30_minutes'); ?>>
                            Every 30 Minutes
                        </option>
                        <option value="hourly" <?php selected(get_option('shopcommerce_cron_interval'), 'hourly'); ?>>
                            Hourly (recommended)
                        </option>
                        <option value="twicedaily" <?php selected(get_option('shopcommerce_cron_interval'), 'twicedaily'); ?>>
                            Twice Daily
                        </option>
                        <option value="daily" <?php selected(get_option('shopcommerce_cron_interval'), 'daily'); ?>>
                            Daily
                        </option>
                    </select>
                    <p class="description">How often to run automatic sync operations.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="shopcommerce_batch_size">Batch Size</label>
                </th>
                <td>
                    <input type="number" id="shopcommerce_batch_size" name="shopcommerce_batch_size"
                           value="<?php echo esc_attr(get_option('shopcommerce_batch_size', '100')); ?>"
                           min="10" max="1000" class="small-text">
                    <p class="description">Number of products to process in each batch (default: 100).</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="shopcommerce_enable_logging">Enable Detailed Logging</label>
                </th>
                <td>
                    <input type="checkbox" id="shopcommerce_enable_logging" name="shopcommerce_enable_logging"
                           value="1" <?php checked(get_option('shopcommerce_enable_logging', '1')); ?>>
                    <p class="description">Enable detailed logging for debugging purposes.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="shopcommerce_log_retention_days">Log Retention (days)</label>
                </th>
                <td>
                    <input type="number" id="shopcommerce_log_retention_days" name="shopcommerce_log_retention_days"
                           value="<?php echo esc_attr(get_option('shopcommerce_log_retention_days', '30')); ?>"
                           min="1" max="365" class="small-text">
                    <p class="description">How many days to keep activity logs (default: 30).</p>
                </td>
            </tr>
        </table>
    </div>

    <!-- Product Settings -->
    <div class="settings-section">
        <h2>Product Settings</h2>
        <p>Configure how products are created and updated in WooCommerce.</p>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="shopcommerce_default_stock_status">Default Stock Status</label>
                </th>
                <td>
                    <select id="shopcommerce_default_stock_status" name="shopcommerce_default_stock_status">
                        <option value="instock" <?php selected(get_option('shopcommerce_default_stock_status'), 'instock'); ?>>
                            In Stock
                        </option>
                        <option value="outofstock" <?php selected(get_option('shopcommerce_default_stock_status'), 'outofstock'); ?>>
                            Out of Stock
                        </option>
                        <option value="onbackorder" <?php selected(get_option('shopcommerce_default_stock_status'), 'onbackorder'); ?>>
                            On Backorder
                        </option>
                    </select>
                    <p class="description">Default stock status for new products.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="shopcommerce_product_status">Product Status</label>
                </th>
                <td>
                    <select id="shopcommerce_product_status" name="shopcommerce_product_status">
                        <option value="publish" <?php selected(get_option('shopcommerce_product_status'), 'publish'); ?>>
                            Published
                        </option>
                        <option value="draft" <?php selected(get_option('shopcommerce_product_status'), 'draft'); ?>>
                            Draft
                        </option>
                        <option value="pending" <?php selected(get_option('shopcommerce_product_status'), 'pending'); ?>>
                            Pending
                        </option>
                    </select>
                    <p class="description">Default status for new products.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="shopcommerce_update_images">Update Product Images</label>
                </th>
                <td>
                    <input type="checkbox" id="shopcommerce_update_images" name="shopcommerce_update_images"
                           value="1" <?php checked(get_option('shopcommerce_update_images', '1')); ?>>
                    <p class="description">Update product images during sync (checked every 24 hours).</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="shopcommerce_create_categories">Create Missing Categories</label>
                </th>
                <td>
                    <input type="checkbox" id="shopcommerce_create_categories" name="shopcommerce_create_categories"
                           value="1" <?php checked(get_option('shopcommerce_create_categories', '1')); ?>>
                    <p class="description">Automatically create WooCommerce categories if they don't exist.</p>
                </td>
            </tr>
        </table>
    </div>

    <!-- Brand Configuration -->
    <div class="settings-section">
        <h2>Brand Configuration</h2>
        <p>Configure which brands and categories to synchronize.</p>

        <div class="brand-config">
            <p><strong>Currently configured brands:</strong></p>
            <ul>
                <?php
                $brands = [
                    'HP INC' => [1, 7, 12, 14, 18],
                    'DELL' => [1, 7, 12, 14, 18],
                    'LENOVO' => [1, 7, 12, 14, 18],
                    'APPLE' => [1, 7],
                    'ASUS' => [7],
                    'BOSE' => [],
                    'EPSON' => [],
                    'JBL' => [],
                ];

                foreach ($brands as $brand => $categories):
                ?>
                    <li>
                        <?php echo esc_html($brand); ?>
                        <?php if (empty($categories)): ?>
                            (All Categories)
                        <?php else: ?>
                            (Categories: <?php echo implode(', ', $categories); ?>)
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="description">Brand configuration is currently managed in the code. Future versions will allow configuration through this interface.</p>
        </div>
    </div>

    <!-- Save Settings -->
    <div class="settings-actions">
        <?php submit_button('Save Settings', 'primary', 'save-settings'); ?>
        <button type="button" class="button button-secondary" id="reset-settings-btn">Reset to Defaults</button>
    </div>
</div>

<style>
.shopcommerce-admin .settings-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}

.shopcommerce-admin .settings-section h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.shopcommerce-admin .api-test-section {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.shopcommerce-admin .brand-config {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 4px;
}

.shopcommerce-admin .settings-actions {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

#api-test-result {
    margin-top: 15px;
    padding: 10px;
    border-radius: 4px;
}

#api-test-result.success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

#api-test-result.error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Test API connection with current settings
    $('#test-api-settings-btn').on('click', function() {
        var $btn = $(this);
        var $result = $('#api-test-result');

        $btn.prop('disabled', true).text('Testing...');
        $result.removeClass('success error').html('<p>Testing connection...</p>');

        // Collect form data
        var formData = {
            action: 'shopcommerce_test_connection',
            nonce: shopcommerce_admin.nonce
        };

        // Add current form values
        $('input[type="text"], input[type="password"], input[type="url"], input[type="number"], select').each(function() {
            if ($(this).attr('name') && $(this).attr('name').startsWith('shopcommerce_')) {
                formData[$(this).attr('name')] = $(this).val();
            }
        });

        $.ajax({
            url: shopcommerce_admin.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    $result.addClass('success').html('<p>✓ API connection successful!</p>');
                } else {
                    $result.addClass('error').html('<p>✗ API connection failed: ' + response.data.error + '</p>');
                }
            },
            error: function() {
                $result.addClass('error').html('<p>✗ Connection test failed due to network error.</p>');
            },
            complete: function() {
                $btn.prop('disabled', false).text('Test Connection');
            }
        });
    });

    // Reset settings to defaults
    $('#reset-settings-btn').on('click', function() {
        if (confirm('Are you sure you want to reset all settings to their default values?')) {
            if (confirm('This action cannot be undone. Are you absolutely sure?')) {
                // Ask about resetting brands and categories
                var resetBrandsCategories = confirm('Would you also like to reset all brands and categories to their default configuration?\n\nThis will remove all custom brands and categories and restore the original default setup.');

                // Reset form fields to defaults
                $('#shopcommerce_api_username').val('pruebas@hekalsoluciones.com');
                $('#shopcommerce_api_password').val('');
                $('#shopcommerce_api_base_url').val('https://shopcommerce.mps.com.co:7965/');
                $('#shopcommerce_api_timeout').val('840');
                $('#shopcommerce_cron_interval').val('hourly');
                $('#shopcommerce_batch_size').val('100');
                $('#shopcommerce_enable_logging').prop('checked', true);
                $('#shopcommerce_log_retention_days').val('30');
                $('#shopcommerce_default_stock_status').val('instock');
                $('#shopcommerce_product_status').val('publish');
                $('#shopcommerce_update_images').prop('checked', true);
                $('#shopcommerce_create_categories').prop('checked', true);

                if (resetBrandsCategories) {
                    // Show loading message
                    var $btn = $(this);
                    $btn.prop('disabled', true).text('Resetting...');

                    $.ajax({
                        url: shopcommerce_admin.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'shopcommerce_reset_brands_categories',
                            nonce: shopcommerce_admin.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('Settings have been reset to defaults.\n\nBrands and categories have been reset to default configuration.\n\nClick "Save Settings" to apply the settings changes.');
                            } else {
                                alert('Settings have been reset to defaults, but there was an error resetting brands and categories: ' + response.data.message + '\n\nClick "Save Settings" to apply the settings changes.');
                            }
                        },
                        error: function() {
                            alert('Settings have been reset to defaults, but there was a network error while resetting brands and categories.\n\nClick "Save Settings" to apply the settings changes.');
                        },
                        complete: function() {
                            $btn.prop('disabled', false).text('Reset to Defaults');
                        }
                    });
                } else {
                    alert('Settings have been reset to defaults. Click "Save Settings" to apply.');
                }
            }
        }
    });

    // Save settings via AJAX
    $('#save-settings').on('click', function(e) {
        e.preventDefault();

        var $btn = $(this);
        var formData = $('#shopcommerce-settings-form').serializeArray();

        // Convert to settings object
        var settings = {};
        $.each(formData, function(i, field) {
            if (field.name.startsWith('shopcommerce_')) {
                settings[field.name] = field.value;
            }
        });

        $btn.prop('disabled', true).text('Saving...');

        $.ajax({
            url: shopcommerce_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'shopcommerce_update_settings',
                nonce: shopcommerce_admin.nonce,
                settings: settings
            },
            success: function(response) {
                if (response.success) {
                    alert('Settings saved successfully!');
                    location.reload();
                } else {
                    alert('Failed to save settings: ' + response.data.error);
                }
            },
            error: function() {
                alert('Failed to save settings due to network error.');
            },
            complete: function() {
                $btn.prop('disabled', false).text('Save Settings');
            }
        });
    });
});
</script>