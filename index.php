<?php
/**
 * Plugin Name:       ShopCommerce Product Sync Plugin
 * Description:       A plugin to sync products from ShopCommerce with WooCommerce, specially for Hekalsoluciones.
 * Version:           1.1.0
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
require_once plugin_dir_path( __FILE__ ) . 'includes/admin-management-page.php';
//require_once plugin_dir_path( __FILE__ ) . 'includes/class-order-handler.php';
//require_once plugin_dir_path( __FILE__ ) . 'includes/class-checkout-customizer.php';

// Instantiate the classes to set up the hooks
//new My_Provider_Order_Handler();
//new My_Provider_Checkout_Customizer();

register_activation_hook( __FILE__, 'product_sync_plugin_activate' );
register_deactivation_hook( __FILE__, 'product_sync_plugin_deactivate' );
