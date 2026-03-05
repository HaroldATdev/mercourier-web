<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Helpers globales del plugin merc-csv-import.
 */

/**
 * Carga un template del plugin.
 *
 * @param string $file  Ruta relativa desde admin/templates/  (ej. 'admin/import-log.tpl.php')
 * @param array  $data  Variables a inyectar en el template.
 */
function mci_include_template( string $file, array $data = [] ): void {
	$path = MERC_CSV_PATH . "admin/templates/{$file}";
	if ( ! file_exists( $path ) ) {
		wp_die( "Plantilla no encontrada: {$file}" );
	}
	if ( $data ) {
		extract( $data, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract
	}
	include $path;
}

/**
 * Wrappers de compatibilidad para código legado que llame
 * a merc_get_district_prices() / merc_find_district_price()
 * antes de que la clase MERC_Financial_Import esté disponible.
 * (Las versiones canónicas se definen en class-financial-import.php)
 */
if ( ! function_exists( 'merc_get_district_prices' ) ) {
	function merc_get_district_prices( string $tipo = 'normal' ): array {
		return class_exists( 'MERC_Financial_Import' )
			? MERC_Financial_Import::get_prices( $tipo )
			: [];
	}
}

if ( ! function_exists( 'merc_find_district_price' ) ) {
	function merc_find_district_price( string $destination, string $tipo = 'normal' ): float {
		return class_exists( 'MERC_Financial_Import' )
			? MERC_Financial_Import::find_district_price( $destination, $tipo )
			: 0.00;
	}
}
