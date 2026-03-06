<?php
/**
 * Plugin Name: WPCargo Shipment Filters
 * Plugin URI: https://mercourier.local
 * Description: Sistema modular de filtros avanzados para envíos - Fecha, Cliente, Motorizado, Tienda
 * Version: 1.0.0
 * Author: MERCourier Development
 * Author URI: https://mercourier.local
 * License: MIT
 * Text Domain: wpcargo-shipment-filters
 * Domain Path: /languages
 * 
 * @package WPCargo_Shipment_Filters
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define constants
define( 'WPCARGO_SF_VERSION', '1.0.0' );
define( 'WPCARGO_SF_FILE', __FILE__ );
define( 'WPCARGO_SF_DIR', plugin_dir_path( WPCARGO_SF_FILE ) );
define( 'WPCARGO_SF_URL', plugin_dir_url( WPCARGO_SF_FILE ) );

// Load main class
require_once WPCARGO_SF_DIR . 'includes/class-main.php';

// Initialize the plugin
function wpcargo_shipment_filters_init() {
    $plugin = new \WPCargo_Shipment_Filters\Main();
    $plugin->run();
}

add_action( 'plugins_loaded', 'wpcargo_shipment_filters_init', 15 );

// Activation hook
register_activation_hook( WPCARGO_SF_FILE, function() {
    error_log( '[WPCargo Shipment Filters] Plugin activated' );
    do_action( 'wpcargo_sf_activated' );
} );

// Deactivation hook
register_deactivation_hook( WPCARGO_SF_FILE, function() {
    error_log( '[WPCargo Shipment Filters] Plugin deactivated' );
    do_action( 'wpcargo_sf_deactivated' );
} );
