<?php
/**
 * Define DOING_AJAX early and suppress translation notices during AJAX
 * Must-use plugin to load before other plugins
 */

// Detect AJAX requests
$is_ajax = false;
if ( false !== strpos( $_SERVER['REQUEST_URI'], 'admin-ajax.php' ) ) {
    $is_ajax = true;
}

// Define DOING_AJAX early
if ( $is_ajax && !defined('DOING_AJAX') ) {
    define('DOING_AJAX', true);
}

// For AJAX requests, we need to prevent translation loading notices converting to HTML errors
if ( $is_ajax ) {
    // Shut down all error display completely for AJAX
    @error_reporting(0);
    @ini_set('display_errors', '0');
    
    // Suppress error handler for translation errors
    add_filter( 'doing_it_wrong_trigger_error', '__return_false', 9999 );
    
    // Load the textdomain immediately before plugins try to use it
    if ( function_exists( 'load_plugin_textdomain' ) ) {
        @load_plugin_textdomain(
            'wpcargo-frontend-manager',
            false,
            'wpcargo-frontend-manager/languages'
        );
    }
}

