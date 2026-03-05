<?php
/**
 * Plugin Name: DHV Courier – CSV Import Pro
 * Plugin URI:  https://dhvcourier.com
 * Description: Importación CSV de shipments: validación de duplicados, normalización tipo_envio, auto-fill remitente, asignación motorizado y datos financieros.
 * Version:     1.0.0
 * Author:      DHV Courier
 * Text Domain: merc-csv-import
 * Requires PHP: 8.1
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'MERC_CSV_VERSION', '1.0.0' );
define( 'MERC_CSV_PATH',    plugin_dir_path( __FILE__ ) );
define( 'MERC_CSV_URL',     plugin_dir_url( __FILE__ ) );

require_once MERC_CSV_PATH . 'includes/functions.php';
require_once MERC_CSV_PATH . 'admin/classes/class-tracking-validator.php';
require_once MERC_CSV_PATH . 'admin/classes/class-tipo-envio-normalizer.php';
require_once MERC_CSV_PATH . 'admin/classes/class-sender-autofill.php';
require_once MERC_CSV_PATH . 'admin/classes/class-financial-import.php';
