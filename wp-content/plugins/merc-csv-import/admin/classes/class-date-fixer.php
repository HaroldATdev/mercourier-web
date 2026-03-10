<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * MERC_Date_Fixer
 * 
 * Corrige las fechas de pickup que vienen en formato DD/MM/YYYY del CSV.
 * El plugin encriptado de import-export usa strtotime() que interpreta
 * 9/3/2026 como MM/DD/YYYY, guardándolo como 3/9/2026.
 * 
 * Esta clase lo arregla usando DateTime::createFromFormat('d/m/Y')
 * 
 * Prioridad 5 — al principio, antes de cualquier otro procesamiento
 */
class MERC_Date_Fixer {

	public function __construct() {
		add_action( 'wpcie_after_save_csv_import', [ $this, 'fix_pickup_dates' ], 5, 2 );
	}

	/**
	 * Corrige las fechas de pickup del formato DD/MM/YYYY a YYYY-MM-DD
	 * Se ejecuta inmediatamente después de que se guarda el shipment desde CSV
	 */
	public function fix_pickup_dates( int $shipment_id, array $record ): void {
		if ( get_post_type( $shipment_id ) !== 'wpcargo_shipment' ) {
			return;
		}

		// Las claves de meta donde puede estar la fecha de pickup
		$date_meta_keys = [
			'wpcargo_pickup_date_picker',
			'wpcargo_pickup_date',
			'calendarenvio',
			'wpcargo_fecha_envio',
		];

		foreach ( $date_meta_keys as $meta_key ) {
			$date_value = get_post_meta( $shipment_id, $meta_key, true );
			
			if ( empty( $date_value ) ) {
				continue;
			}

			// Si ya está en formato YYYY-MM-DD, saltar
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_value ) ) {
				continue;
			}

			// Intentar parsear como DD/MM/YYYY
			$fixed_date = $this->parse_date_ddmmyyyy( $date_value );
			
			if ( $fixed_date && $fixed_date !== $date_value ) {
				update_post_meta( $shipment_id, $meta_key, $fixed_date );
				error_log( "🔧 MERC_Date_Fixer: Shipment #{$shipment_id} - {$meta_key}: '{$date_value}' → '{$fixed_date}'" );
			}
		}
	}

	/**
	 * Parsea una fecha en formato DD/MM/YYYY a YYYY-MM-DD
	 * 
	 * Soporta:
	 * - DD/MM/YYYY (español)
	 * - YYYY-MM-DD (ya correcto)
	 * - DD-MM-YYYY (con guiones)
	 * 
	 * @param string $date_str La fecha como string
	 * @return string|false Fecha en formato YYYY-MM-DD o false si es inválida
	 */
	private function parse_date_ddmmyyyy( string $date_str ) {
		if ( empty( $date_str ) ) {
			return false;
		}

		$date_str = trim( $date_str );

		// Si ya está en formato YYYY-MM-DD, retornar tal cual
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_str ) ) {
			return $date_str;
		}

		// Intentar parsear como D/M/YYYY (SIN ceros - 9/3/2026)
		$dt = \DateTime::createFromFormat( 'j/n/Y', $date_str );
		if ( $dt ) {
			return $dt->format( 'Y-m-d' );
		}

		// Intentar parsear como DD/MM/YYYY (CON ceros - 09/03/2026)
		$dt = \DateTime::createFromFormat( 'd/m/Y', $date_str );
		if ( $dt ) {
			return $dt->format( 'Y-m-d' );
		}

		// Intentar parsear como DD-MM-YYYY
		$dt = \DateTime::createFromFormat( 'd-m-Y', $date_str );
		if ( $dt ) {
			return $dt->format( 'Y-m-d' );
		}

		// Intentar parsear como D-M-YYYY (sin ceros con guiones)
		$dt = \DateTime::createFromFormat( 'j-n-Y', $date_str );
		if ( $dt ) {
			return $dt->format( 'Y-m-d' );
		}

		// Intentar parsear como YYYY/MM/DD
		$dt = \DateTime::createFromFormat( 'Y/m/d', $date_str );
		if ( $dt ) {
			return $dt->format( 'Y-m-d' );
		}

		// No se pudo parsear
		return false;
	}
}

new MERC_Date_Fixer();

