<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * MERC_Tracking_Validator
 * Corresponde a STEP 0A + STEP 0 + STEP 3 + STEP 3.5 del functions.php original.
 *
 * - Pre-inserción: garantiza unicidad del post_title (tracking number).
 * - Post-import paso 3: validación de duplicados por tracking.
 * - Post-import paso 3: validación por datos de remitente + destinatario.
 */
class MERC_Tracking_Validator {

	public function __construct() {
		// STEP 0A – antes de insertar en DB
		add_filter( 'wp_insert_post_data', [ $this, 'ensure_unique_tracking' ], 10, 2 );

		// STEP 0 – inmediatamente después de importar (prioridad 3)
		add_action( 'wpcie_after_save_csv_import', [ $this, 'validate_duplicate' ], 3, 2 );

		// STEP 3 – validación final (prioridad 15)
		add_action( 'wpcie_after_save_csv_import', [ $this, 'validate_duplicate_final' ], 15, 2 );

		// STEP 3.5 – validación por remitente + destinatario (prioridad 16)
		add_action( 'wpcie_after_save_csv_import', [ $this, 'validate_duplicate_by_data' ], 16, 2 );
	}

	/* ── STEP 0A: pre-inserción ──────────────────────────────────────── */

	public function ensure_unique_tracking( array $data, array $postarr ): array {
		if ( $data['post_type'] !== 'wpcargo_shipment' ) return $data;
		if ( ! empty( $postarr['ID'] ) ) return $data; // es actualización, no tocar

		$title = $data['post_title'];
		if ( empty( $title ) || ! $this->tracking_exists( $title ) ) return $data;

		if ( preg_match( '/^(MERC-)?(\d+)$/', $title, $m ) ) {
			$prefix = ! empty( $m[1] ) ? $m[1] : 'MERC-';
			$number = (int) $m[2];
			$length = strlen( $m[2] );
			$tries  = 0;
			do {
				$number++;
				$new = $prefix . str_pad( $number, $length, '0', STR_PAD_LEFT );
				$tries++;
			} while ( $this->tracking_exists( $new ) && $tries < 10 );

			$data['post_title'] = $tries >= 10 ? $title . '-' . time() : $new;
		} else {
			$data['post_title'] = $title . '-' . time();
		}

		return $data;
	}

	/* ── STEP 0: duplicado reciente (60s) ───────────────────────────── */

	public function validate_duplicate( int $shipment_id, array $record ): void {
		global $wpdb;
		[ $check, $post_title ] = $this->get_tracking_and_title( $shipment_id );
		if ( ! $check ) return;

		$dups = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore
			"SELECT COUNT(*) FROM {$wpdb->posts}
			 WHERE post_type='wpcargo_shipment' AND post_status='publish'
			 AND post_title=%s AND ID!=%d
			 AND post_date > DATE_SUB(NOW(), INTERVAL 60 SECOND)",
			$check, $shipment_id
		) );

		if ( $dups > 0 ) {
			$new = $post_title . '-' . time();
			wp_update_post( [ 'ID' => $shipment_id, 'post_title' => $new ] );
			update_post_meta( $shipment_id, 'wpcargo_original_tracking', $check );
			update_post_meta( $shipment_id, 'wpcargo_duplicate_attempt', 'yes' );
		}
	}

	/* ── STEP 3: duplicado global ────────────────────────────────────── */

	public function validate_duplicate_final( int $shipment_id, array $record ): void {
		global $wpdb;
		[ $check, $post_title ] = $this->get_tracking_and_title( $shipment_id );
		if ( ! $check ) return;

		$dups = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore
			"SELECT COUNT(*) FROM {$wpdb->posts}
			 WHERE post_type='wpcargo_shipment' AND post_status='publish'
			 AND post_title=%s AND ID!=%d",
			$check, $shipment_id
		) );

		if ( $dups > 0 ) {
			$new = $post_title . '-' . time();
			wp_update_post( [ 'ID' => $shipment_id, 'post_title' => $new ] );
			update_post_meta( $shipment_id, 'wpcargo_original_tracking', $check );
			update_post_meta( $shipment_id, 'wpcargo_duplicate_attempt', 'yes' );
		}
	}

	/* ── STEP 3.5: duplicado por remitente + destinatario ────────────── */

	public function validate_duplicate_by_data( int $shipment_id, array $record ): void {
		global $wpdb;

		$sn = get_post_meta( $shipment_id, 'wpcargo_shipper_name',   true );
		$sp = get_post_meta( $shipment_id, 'wpcargo_shipper_phone',  true );
		$rn = get_post_meta( $shipment_id, 'wpcargo_receiver_name',  true );
		$rp = get_post_meta( $shipment_id, 'wpcargo_receiver_phone', true );

		if ( empty( $sn ) || empty( $rn ) || empty( $rp ) ) return;

		$norm  = fn( string $s ) => strtolower( trim( preg_replace( '/\s+/', ' ', $s ) ) );
		$strip = fn( string $s ) => preg_replace( '/[^0-9]/', '', $s );

		$candidates = $wpdb->get_col( $wpdb->prepare( // phpcs:ignore
			"SELECT DISTINCT p.ID FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} a ON p.ID=a.post_id AND a.meta_key='wpcargo_shipper_name'
			 INNER JOIN {$wpdb->postmeta} b ON p.ID=b.post_id AND b.meta_key='wpcargo_shipper_phone'
			 INNER JOIN {$wpdb->postmeta} c ON p.ID=c.post_id AND c.meta_key='wpcargo_receiver_name'
			 INNER JOIN {$wpdb->postmeta} d ON p.ID=d.post_id AND d.meta_key='wpcargo_receiver_phone'
			 WHERE p.post_type='wpcargo_shipment' AND p.post_status='publish'
			 AND p.ID!=%d AND p.post_date > DATE_SUB(NOW(), INTERVAL 30 DAY)",
			$shipment_id
		) );

		foreach ( $candidates as $eid ) {
			$match_s = $norm( $sn ) === $norm( get_post_meta( $eid, 'wpcargo_shipper_name',  true ) )
			        && $strip( $sp ) === $strip( get_post_meta( $eid, 'wpcargo_shipper_phone', true ) );
			$match_r = $norm( $rn ) === $norm( get_post_meta( $eid, 'wpcargo_receiver_name',  true ) )
			        && $strip( $rp ) === $strip( get_post_meta( $eid, 'wpcargo_receiver_phone', true ) );

			if ( $match_s && $match_r ) {
				update_post_meta( $shipment_id, 'wpcargo_duplicate_sender_receiver', 'yes' );
				update_post_meta( $shipment_id, 'wpcargo_duplicate_of_shipment',     $eid );
				update_post_meta( $shipment_id, 'wpcargo_import_validation_error',
					"Duplicado: mismo remitente y destinatario que envío #{$eid}" );
				break;
			}
		}
	}

	/* ── Helpers ─────────────────────────────────────────────────────── */

	private function tracking_exists( string $title ): bool {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore
			"SELECT ID FROM {$wpdb->posts}
			 WHERE post_type='wpcargo_shipment' AND post_status='publish'
			 AND post_title=%s LIMIT 1",
			$title
		) );
	}

	private function get_tracking_and_title( int $id ): array {
		$tracking = get_post_meta( $id, 'wpcargo_tracking_number', true );
		$post     = get_post( $id );
		$title    = $post ? $post->post_title : '';
		$check    = $tracking ?: $title;
		if ( empty( $check ) || $check === 'SIN TÍTULO' ) return [ null, $title ];
		return [ $check, $title ];
	}
}

new MERC_Tracking_Validator();
