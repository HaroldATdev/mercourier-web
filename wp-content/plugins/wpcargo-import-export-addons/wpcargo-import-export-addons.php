<?php
/*
	Plugin Name: WPCargo Import and Export Add-ons
	Plugin URI: https://www.wpcargo.com/product/wpcargo-importexport-add-ons/
	Description: Allows you to Import/Export your shipments or to make backups. Requires WPCargo plugin to work.
	Version: 6.0.0
	Author: <a href="http://wptaskforce.com/">WPTaskforce</a>
	Author URI: http://www.wpcargo.com
	Text Domain: wpc-import-export
	Domain Path: /languages
*/
if (! defined('WPINC')) {
	die;
}
/** Define plugin constants */
define('WPC_IMPORT_EXPORT_VERSION', '6.0.0');
define('WPC_IMPORT_EXPORT_FILE', __FILE__);
define('WPC_IMPORT_EXPORT_BASENAME', plugin_basename(__FILE__));
define('WPC_IMPORT_EXPORT_URL', plugin_dir_url(__FILE__));
define('WPC_IMPORT_EXPORT_PATH', plugin_dir_path(__FILE__));
define('WPC_IMPORT_EXPORT_UPDATE_REMOTE', 'updates-8.1');
// Includes Files
require_once(WPC_IMPORT_EXPORT_PATH . 'includes/lang.php');
require_once(WPC_IMPORT_EXPORT_PATH . 'includes/functions.php');
require_once(WPC_IMPORT_EXPORT_PATH . 'includes/hooks.php');
require_once(WPC_IMPORT_EXPORT_PATH . 'includes/assets.php');
require_once(WPC_IMPORT_EXPORT_PATH . 'includes/import-export.php');
