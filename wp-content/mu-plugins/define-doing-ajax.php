<?php
/**
 * Define DOING_AJAX early for access control bypass
 * Load textdomain early to prevent just-in-time loading errors
 * Must-use plugin to load before other plugins
 */

// Define DOING_AJAX if we're accessing admin-ajax.php
if ( false !== strpos($_SERVER['REQUEST_URI'], 'admin-ajax.php') ) {
    if ( !defined('DOING_AJAX') ) {
        define('DOING_AJAX', true);
    }
}

// Load textdomain for wpcargo-frontend-manager on wp_loaded to prevent translation errors
add_action( 'wp_loaded', 'wpcfe_early_load_textdomain', 1 );
function wpcfe_early_load_textdomain() {
    if ( !is_textdomain_loaded( 'wpcargo-frontend-manager' ) ) {
        load_plugin_textdomain( 
            'wpcargo-frontend-manager', 
            false, 
            'wpcargo-frontend-manager/languages'
        );
    }
}
