<?php
/**
 * Plugin Name: Merc Returns
 * Plugin URI: https://mercourier.com
 * Description: Módulo de gestión de devoluciones, cambios de producto y estado de entregas
 * Version: 1.0.0
 * Author: Mercourier
 * Author URI: https://mercourier.com
 * License: Proprietary
 * Text Domain: merc-returns
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes del plugin
define('MERC_RETURNS_FILE', __FILE__);
define('MERC_RETURNS_DIR', plugin_dir_path(__FILE__));
define('MERC_RETURNS_URL', plugin_dir_url(__FILE__));
define('MERC_RETURNS_BASENAME', plugin_basename(__FILE__));

// Cargar archivos requeridos
require_once MERC_RETURNS_DIR . 'includes/helpers.php';
require_once MERC_RETURNS_DIR . 'includes/shortcodes.php';
require_once MERC_RETURNS_DIR . 'includes/ajax.php';
require_once MERC_RETURNS_DIR . 'includes/hooks.php';

/**
 * Hook de activación
 */
register_activation_hook(__FILE__, function() {
    error_log('[MERC RETURNS] Plugin activado');
});

/**
 * Hook de desactivación
 */
register_deactivation_hook(__FILE__, function() {
    error_log('[MERC RETURNS] Plugin desactivado');
});
