<?php
/**
 * Example Integration with WPCargo Access Control Plugin
 * Add this code to your theme's functions.php to customize permissions
 */

// Example: Add custom permissions for new users
add_filter('wpcac_permissions_matrix', function($matrix) {
    // Agregar nuevo usuario con permisos limitados
    $matrix['nuevo_operario@ejemplo.com'] = array(
        '/dashboard/',
        '/receiving/',
    );
    
    // Expandir permisos de usuario existente
    if (isset($matrix['cmoralesdiaz248@gmail.com'])) {
        $matrix['cmoralesdiaz248@gmail.com'][] = '/containers/';
    }
    
    return $matrix;
});

// Example: Log all permission checks (for debugging)
add_action('wpcac_permissions_updated', function($email, $paths) {
    error_log("🔧 Permisos actualizados para {$email}: " . json_encode($paths));
}, 10, 2);

// Example: Perform custom action when bypass is toggled
add_action('wpcac_skip_status_changed', function($enable, $today, $count) {
    $status = $enable ? 'ENABLED' : 'DISABLED';
    error_log("🎛️ Bypass {$status} para {$count} clientes en {$today}");
}, 10, 3);

// Example: Custom access check before template_redirect
if (function_exists('wpcac_current_user_can_access')) {
    add_action('init', function() {
        if (!is_user_logged_in()) {
            return;
        }
        
        $current_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Log all access attempts
        if (!wpcac_current_user_can_access($current_path)) {
            $user = wp_get_current_user();
            error_log("⛔ Acceso denegado para {$user->user_email} a {$current_path}");
        }
    });
}

// Example: Get user permissions programmatically
if (function_exists('wpcac_get_user_permissions')) {
    // Use in your custom code:
    // $email = 'user@example.com';
    // $allowed_paths = wpcac_get_user_permissions($email);
    // if (in_array('/dashboard/', $allowed_paths)) {
    //     // User can access dashboard
    // }
}
