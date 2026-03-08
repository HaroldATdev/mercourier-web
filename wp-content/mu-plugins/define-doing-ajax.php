<?php
/**
 * Define DOING_AJAX early for access control bypass
 * Must-use plugin to load before other plugins
 */

error_log('🔌 mu-plugin: define-doing-ajax.php cargado');

// Define DOING_AJAX if we're accessing admin-ajax.php
if ( false !== strpos($_SERVER['REQUEST_URI'], 'admin-ajax.php') ) {
    if ( !defined('DOING_AJAX') ) {
        define('DOING_AJAX', true);
        error_log('✅ mu-plugin: DOING_AJAX definida en mu-plugin');
    }
    error_log('📍 mu-plugin: REQUEST_URI = ' . $_SERVER['REQUEST_URI']);
}

