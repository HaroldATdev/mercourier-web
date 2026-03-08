<?php
/**
 * Debug AJAX requests
 * Must-use plugin to track all AJAX calls
 */

// Log all AJAX requests
if ( false !== strpos($_SERVER['REQUEST_URI'], 'admin-ajax.php') ) {
    error_log('🔹 [AJAX REQUEST] ' . $_SERVER['REQUEST_URI'] . ' - POST action: ' . (isset($_POST['action']) ? sanitize_text_field($_POST['action']) : 'NO ACTION'));
}

// Capture wp_die calls by hooking into fatal error handler
register_shutdown_function(function() {
    if ( false !== strpos($_SERVER['REQUEST_URI'], 'admin-ajax.php') ) {
        $error = error_get_last();
        if ($error !== NULL) {
            error_log('⚠️ [FATAL ERROR] ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line']);
        }
    }
});
