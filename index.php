<?php
/**
 * Plugin Name:       ShopCommerce Product Sync Plugin
 * Description:       A plugin to sync products from ShopCommerce with WooCommerce, specially for Hekalsoluciones.
 * Version:           1.0.2
 * Author:            Esteban Andres Murcia Acuña
 * Author URI:        https://estebanmurcia.dev/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       shopcommerce-product-sync-plugin
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Include the files containing the main classes
require_once plugin_dir_path( __FILE__ ) . 'includes/product-sync.php';
//require_once plugin_dir_path( __FILE__ ) . 'includes/class-order-handler.php';
//require_once plugin_dir_path( __FILE__ ) . 'includes/class-checkout-customizer.php';

// Instantiate the classes to set up the hooks
//new My_Provider_Order_Handler();
//new My_Provider_Checkout_Customizer();

register_activation_hook( __FILE__, 'product_sync_plugin_activate' );
register_deactivation_hook( __FILE__, 'product_sync_plugin_deactivate' );

add_action( 'admin_menu', function() {
    add_management_page( 
        'ShopCommerce Product Sync Debug',
        'ShopCommerce Product Sync Debug',
        'manage_options',
        'shopcommerce-product-sync-debug',
        'shopcommerce_product_sync_debug_page'
    );
});

function shopcommerce_product_sync_debug_page() {
    if ( isset($_POST['run_sync']) ) {
        provider_product_sync_hook();
        echo '<div class="updated"><p>Sync executed. Check debug.log.</p></div>';
    }
    echo '<div class="wrap"><h1>ShopCommerce Product Sync Debug</h1>
          <form method="post"><button class="button button-primary" name="run_sync">Run Sync Now</button></form>
          </div>';
}
