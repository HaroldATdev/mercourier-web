<?php
/**
 * Plugin Name: Merc Warehouse
 * Plugin URI: https://mercourier.com
 * Description: Módulo de gestión de almacén de productos
 * Version: 1.0.0
 * Author: Mercourier
 * License: Proprietary
 * Text Domain: merc-warehouse
 */

if (!defined('ABSPATH')) exit;

// En requests AJAX, iniciar buffer aquí (los plugins cargan ANTES que functions.php del tema).
// Así el BOM u output espurio del tema queda atrapado en el buffer y se puede limpiar
// en cada handler AJAX antes de enviar el JSON.
if (defined('DOING_AJAX') && DOING_AJAX) {
    ob_start();
}

define('MERC_WAREHOUSE_FILE', __FILE__);
define('MERC_WAREHOUSE_DIR', plugin_dir_path(__FILE__));
define('MERC_WAREHOUSE_URL', plugin_dir_url(__FILE__));

// Cargar archivos
require_once MERC_WAREHOUSE_DIR . 'includes/shortcodes.php';
require_once MERC_WAREHOUSE_DIR . 'includes/ajax.php';
require_once MERC_WAREHOUSE_DIR . 'includes/hooks.php';

register_activation_hook(__FILE__, function() {
    error_log('[MERC WAREHOUSE] Plugin activado');
});

register_deactivation_hook(__FILE__, function() {
    error_log('[MERC WAREHOUSE] Plugin desactivado');
});
