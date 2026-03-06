<?php
/**
 * Plugin Name: WPCargo UI Customizer
 * Plugin URI: https://mercourier.local
 * Description: Personalización modular de UI - Renombramiento de menús, ocultación de campos, cambios visuales
 * Version: 1.0.0
 * Author: MERCourier Development
 * Author URI: https://mercourier.local
 * License: MIT
 * Text Domain: wpcargo-ui-customizer
 * Domain Path: /languages
 * 
 * @package WPCargo_UI_Customizer
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define constants
define( 'WPCARGO_UI_VERSION', '1.0.0' );
define( 'WPCARGO_UI_FILE', __FILE__ );
define( 'WPCARGO_UI_DIR', plugin_dir_path( WPCARGO_UI_FILE ) );
define( 'WPCARGO_UI_URL', plugin_dir_url( WPCARGO_UI_FILE ) );

// Load includes
require_once WPCARGO_UI_DIR . 'includes/class-main.php';
require_once WPCARGO_UI_DIR . 'includes/menus.php';
require_once WPCARGO_UI_DIR . 'includes/tables.php';
require_once WPCARGO_UI_DIR . 'includes/footer.php';
require_once WPCARGO_UI_DIR . 'includes/styles.php';

// Initialize plugin
function wpcargo_ui_customizer_init() {
    $plugin = new \WPCargo_UI_Customizer\Main();
    $plugin->run();
}

add_action( 'plugins_loaded', 'wpcargo_ui_customizer_init', 15 );

// Activation hook
register_activation_hook( WPCARGO_UI_FILE, function() {
    error_log( '[WPCargo UI Customizer] Plugin activated' );
    do_action( 'wpcargo_ui_customizer_activated' );
} );

// Deactivation hook
register_deactivation_hook( WPCARGO_UI_FILE, function() {
    error_log( '[WPCargo UI Customizer] Plugin deactivated' );
    do_action( 'wpcargo_ui_customizer_deactivated' );
} );
