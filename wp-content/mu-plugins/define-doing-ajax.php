<?php
/**
 * Define DOING_AJAX early for access control bypass
 * Prevent translation loading errors during AJAX
 * Must-use plugin to load before other plugins
 */

// Define DOING_AJAX if we're accessing admin-ajax.php
if ( false !== strpos($_SERVER['REQUEST_URI'], 'admin-ajax.php') ) {
    if ( !defined('DOING_AJAX') ) {
        define('DOING_AJAX', true);
    }
    
    // For AJAX, set a flag to prevent strict translation loading checks
    if ( !defined('WP_DISABLE_FATAL_ERROR_HANDLER') ) {
        define('WP_DISABLE_FATAL_ERROR_HANDLER', true);
    }
}

// Register textdomain path EARLY to avoid just-in-time loading issues later
add_action( 'muplugins_loaded', function() {
    global $wp_textdomain_registry;
    
    if ( ! isset( $wp_textdomain_registry ) || ! is_object( $wp_textdomain_registry ) ) {
        return;
    }
    
    // Pre-register the textdomain path so just-in-time loading knows where to find it
    $wpcfe_path = '/wpcargo-frontend-manager/languages';
    if ( defined( 'WP_PLUGIN_DIR' ) && file_exists( WP_PLUGIN_DIR . $wpcfe_path ) ) {
        $wp_textdomain_registry->set( 'wpcargo-frontend-manager', WP_PLUGIN_DIR . $wpcfe_path, 'wpcargo-frontend-manager' );
    }
}, 1 );

// Load textdomain at the appropriate wp_loaded hook
add_action( 'wp_loaded', function() {
    if ( !is_textdomain_loaded( 'wpcargo-frontend-manager' ) ) {
        load_plugin_textdomain( 
            'wpcargo-frontend-manager', 
            false, 
            'wpcargo-frontend-manager/languages'
        );
    }
}, 999 );

