<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * MERC_Container_Assign
 *
 * Asigna automáticamente el contenedor correcto según el distrito seleccionado
 * en el formulario de creación/edición de envíos.
 *
 * Antes estaba en functions.php del tema – movido al plugin para better separation.
 */
class MERC_Container_Assign {

	public function __construct() {
		add_action( 'wp_enqueue_scripts',                                [ $this, 'enqueue_scripts' ] );
		add_action( 'wp_ajax_merc_buscar_contenedor_por_distrito',       [ $this, 'buscar_contenedor_ajax' ] );
		add_action( 'wp_ajax_nopriv_merc_buscar_contenedor_por_distrito', [ $this, 'buscar_contenedor_ajax' ] );
	}

	/* ── Encolar JS ──────────────────────────────────────────────────── */

	public function enqueue_scripts(): void {
		if ( ! isset( $_GET['wpcfe'] ) || ! in_array( $_GET['wpcfe'], [ 'add', 'update' ], true ) ) {
			return;
		}

		$mode       = sanitize_text_field( $_GET['wpcfe'] );
		$shipment_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

		wp_enqueue_script(
			'merc-container-assign',
			MERC_FORM_URL . 'admin/assets/js/container-assign.js',
			[ 'jquery' ],
			MERC_FORM_VERSION,
			true
		);

		wp_localize_script( 'merc-container-assign', 'MercContainerAssign', [
			'ajaxurl'    => admin_url( 'admin-ajax.php' ),
			'mode'       => $mode,
			'shipmentId' => $shipment_id,
		] );
	}

	/* ── AJAX: buscar contenedor por distrito ────────────────────────── */

	public function buscar_contenedor_ajax(): void {
		$distrito   = isset( $_POST['distrito'] )   ? sanitize_text_field( $_POST['distrito'] )   : '';
		$tipo_envio = isset( $_POST['tipo_envio'] ) ? sanitize_text_field( $_POST['tipo_envio'] ) : '';

		if ( empty( $distrito ) ) {
			wp_send_json_error( [ 'message' => 'Distrito vacío' ] );
		}

		$distrito_normalizado = strtolower( remove_accents( trim( $distrito ) ) );

		// Usar caché transient (5 minutos)
		$cache_key  = 'merc_containers_list';
		$containers = get_transient( $cache_key );

		if ( false === $containers ) {
			global $wpdb;
			$containers = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT ID, post_title FROM {$wpdb->posts}
					WHERE post_type = %s AND post_status = %s
					ORDER BY post_title ASC LIMIT 50",
					'shipment_container',
					'publish'
				)
			);
			set_transient( $cache_key, $containers, 5 * MINUTE_IN_SECONDS );
		}

		if ( empty( $containers ) ) {
			wp_send_json_error( [ 'message' => 'No hay contenedores' ] );
		}

		$container_encontrado = null;

		// Buscar coincidencia directa
		foreach ( $containers as $container ) {
			if ( strpos( strtolower( remove_accents( $container->post_title ) ), $distrito_normalizado ) !== false ) {
				$container_encontrado = $container;
				break;
			}
		}

		// Coincidencia parcial por palabras (si no hubo directa)
		if ( ! $container_encontrado ) {
			$palabras = array_filter(
				preg_split( '/\s+/', $distrito_normalizado ),
				fn( $w ) => strlen( $w ) > 2
			);

			if ( ! empty( $palabras ) ) {
				foreach ( $containers as $container ) {
					$cn   = strtolower( remove_accents( $container->post_title ) );
					$hits = 0;
					foreach ( $palabras as $pal ) {
						if ( strpos( $cn, $pal ) !== false ) $hits++;
					}
					if ( $hits > 0 && $hits >= ( count( $palabras ) / 2 ) ) {
						$container_encontrado = $container;
						break;
					}
				}
			}
		}

		if ( $container_encontrado ) {
			$driver_id = get_post_meta( $container_encontrado->ID, 'wpcargo_driver', true );
			wp_send_json_success( [
				'container_id'   => $container_encontrado->ID,
				'container_name' => $container_encontrado->post_title,
				'driver_id'      => ! empty( $driver_id ) ? $driver_id : null,
				'tipo_envio'     => $tipo_envio,
			] );
		} else {
			wp_send_json_error( [ 'message' => 'No se encontró contenedor' ] );
		}
	}
}

new MERC_Container_Assign();
