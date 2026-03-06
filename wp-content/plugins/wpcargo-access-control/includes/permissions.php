<?php
/**
 * Permissions Matrix Module
 * Define and manage user permissions by email
 *
 * @package WPCargo_Access_Control
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * NUEVA FUNCIÓN: merc_get_permisos()
 * Obtener matrix de permisos por email de usuario
 * 
 * @return array Matriz completa de permisos
 */
function merc_get_permisos() {
	return apply_filters( 'merc_permissions_matrix', array(
		'admin@mercourier.com' => array(
			'*' // Acceso total
		),
		'mercourier2019@gmail.com' => array(
			'*' // Acceso total
		),
		'seminarioromanbetzylu@gmail.com' => array(
			'/wpcumanage-users/',
			'/dashboard/',
			'/dashboard/?wpcfe=add',
			'/import-export/',
			'/receiving/',
		),
		'mirellavu6@hotmail.com' => array(
			'/wpcumanage-users/',
			'/dashboard/',
			'/dashboard/?wpcfe=add',
			'/import-export/',
			'/receiving/',
		),
		'marisabel_16_02@hotmail.com' => array(
			'/wpcumanage-users/',
			'/dashboard/',
			'/dashboard/?wpcfe=add',
			'/import-export/',
			'/receiving/',
		),
		'cmoralesdiaz248@gmail.com' => array(
			'/almacen-de-productos/',
			'/devoluciones/',
			'/dashboard/',
			'/receiving/',
		),
		'isaiasjossep.sanchezgutierrez@gmail.com' => array(
			'/containers/',
			'/dashboard/',
			'/panel-admin/',
		),
		'bonilucy07@gmail.com' => array(
			'/containers/',
			'/panel-admin/',
			'/dashboard/',
			'/dashboard/?wpcfe=add',
			'/import-export/',
			'/wpcumanage-users/',
			'/receiving/',
			'/devoluciones/',
			'/almacen-de-productos/',
		),
		'grissel.6@gmail.com' => array(
			'/containers/',
			'/panel-admin/',
			'/dashboard/',
			'/dashboard/?wpcfe=add',
			'/import-export/',
			'/wpcumanage-users/',
			'/receiving/',
			'/devoluciones/',
			'/almacen-de-productos/',
		),
	));
}

/**
 * Get default permissions matrix
 * Estructura: email => [allowed_paths]
 */
function wpcac_get_default_permissions() {
    return merc_get_permisos();
}

/**
 * Get permissions for a specific user (by email)
 * 
 * @param string $email User email
 * @return array Allowed paths for user
 */
function wpcac_get_user_permissions($email) {
    $email = strtolower($email);
    $matrix = apply_filters('wpcac_permissions_matrix', wpcac_get_default_permissions());
    
    return isset($matrix[$email]) ? $matrix[$email] : array();
}

/**
 * Check if current user can access a specific path
 * 
 * @param string $path Path to check
 * @return bool True if allowed
 */
function wpcac_current_user_can_access($path) {
    if (!is_user_logged_in()) {
        return false;
    }
    
    $current_user = wp_get_current_user();
    
    // Admins always have access
    if (in_array('administrator', (array) $current_user->roles, true)) {
        return true;
    }
    
    $permissions = wpcac_get_user_permissions($current_user->user_email);
    
    if (empty($permissions)) {
        return false;
    }
    
    $path = rtrim($path, '/');
    
    // Check if path matches any allowed route
    foreach ($permissions as $allowed) {
        $allowed = rtrim($allowed, '/');
        if ($allowed === '' || $path === $allowed || strpos($path, $allowed) === 0) {
            return true;
        }
    }
    
    return false;
}

/**
 * Get all allowed paths for current user
 * 
 * @return array Allowed paths
 */
function wpcac_get_current_user_allowed_paths() {
    if (!is_user_logged_in()) {
        return array();
    }
    
    $current_user = wp_get_current_user();
    
    // Admins get all paths
    if (in_array('administrator', (array) $current_user->roles, true)) {
        return array('*');
    }
    
    return wpcac_get_user_permissions($current_user->user_email);
}

/**
 * Add user permissions programmatically
 * 
 * @param string $email User email
 * @param array $paths Allowed paths
 */
function wpcac_add_user_permissions($email, $paths) {
    $email = strtolower($email);
    $matrix = get_option('wpcac_permissions_matrix', wpcac_get_default_permissions());
    
    if (!is_array($matrix)) {
        $matrix = wpcac_get_default_permissions();
    }
    
    $matrix[$email] = $paths;
    update_option('wpcac_permissions_matrix', $matrix);
    
    do_action('wpcac_permissions_updated', $email, $paths);
}

/**
 * Remove user permissions
 * 
 * @param string $email User email
 */
function wpcac_remove_user_permissions($email) {
    $email = strtolower($email);
    $matrix = get_option('wpcac_permissions_matrix', wpcac_get_default_permissions());
    
    if (!is_array($matrix)) {
        $matrix = wpcac_get_default_permissions();
    }
    
    if (isset($matrix[$email])) {
        unset($matrix[$email]);
        update_option('wpcac_permissions_matrix', $matrix);
        do_action('wpcac_permissions_removed', $email);
    }
}
