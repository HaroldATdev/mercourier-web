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

// Iniciar buffer de salida siempre (no solo en AJAX).
// Los plugins cargan ANTES que functions.php del tema, así cualquier BOM u output
// espurio de cualquier archivo queda atrapado en el buffer.
// En peticiones normales PHP descarga el buffer automaticamente al finalizar.
// En AJAX, cada handler llama ob_end_clean() antes de enviar JSON.
ob_start();

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
