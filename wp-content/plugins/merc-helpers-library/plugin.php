<?php
/**
 * Plugin Name: MERCourier Helpers Library
 * Plugin URI: https://mercourier.local
 * Description: Librería centralizada de funciones helper reutilizables para MERCourier
 * Version: 1.0.0
 * Author: MERCourier Development
 * Author URI: https://mercourier.local
 * License: MIT
 * Text Domain: merc-helpers-library
 * Domain Path: /languages
 * 
 * @package Merc_Helpers_Library
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define constants
define( 'MERC_HELPERS_VERSION', '1.0.0' );
define( 'MERC_HELPERS_FILE', __FILE__ );
define( 'MERC_HELPERS_DIR', plugin_dir_path( MERC_HELPERS_FILE ) );
define( 'MERC_HELPERS_URL', plugin_dir_url( MERC_HELPERS_FILE ) );

// Load main class
require_once MERC_HELPERS_DIR . 'includes/class-main.php';

// Load all helper functions
require_once MERC_HELPERS_DIR . 'includes/helpers-date.php';
require_once MERC_HELPERS_DIR . 'includes/helpers-shipment.php';
require_once MERC_HELPERS_DIR . 'includes/helpers-user.php';
require_once MERC_HELPERS_DIR . 'includes/helpers-financial.php';

// Initialize the plugin
function merc_helpers_library_init() {
    do_action( 'merc_helpers_library_loaded' );
    error_log( '[MERCourier Helpers Library] Plugin initialized' );
}

add_action( 'plugins_loaded', 'merc_helpers_library_init', 10 );

// Activation hook
register_activation_hook( MERC_HELPERS_FILE, function() {
    error_log( '[MERCourier Helpers Library] Plugin activated' );
    do_action( 'merc_helpers_library_activated' );
} );

// Deactivation hook
register_deactivation_hook( MERC_HELPERS_FILE, function() {
    error_log( '[MERCourier Helpers Library] Plugin deactivated' );
    do_action( 'merc_helpers_library_deactivated' );
} );
