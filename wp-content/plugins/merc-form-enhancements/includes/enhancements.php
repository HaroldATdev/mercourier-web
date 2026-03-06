<?php
/**
 * Funciones faltantes de merc-form-enhancements
 * - Auto-asignación motorizado en importación
 * - Asignación de unidades full fitment
 * - Validación de duplicados de tracking
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Auto-asignar motorizado durante importación CSV
 */
function merc_auto_asignar_motorizado_importacion($shipment_id) {
	if ( empty($shipment_id) ) return;
	
	// Obtener tipo de envío
	$tipo_envio = get_post_meta($shipment_id, 'tipo_envio', true);
	if ( empty($tipo_envio) ) {
		$tipo_envio = get_post_meta($shipment_id, 'wpcargo_type_of_shipment', true);
	}
	
	$tipo_lower = strtolower(trim($tipo_envio));
	
	// Intentar obtener motorizado por defecto según tipo
	$motorizado_recojo = 0;
	$motorizado_entrega = 0;
	
	// Buscar motorizado disponible según el tipo (puedes usar tu lógica aquí)
	// Por ahora, dejamos con valor 0 (será asignado manualmente después)
	
	if ( !empty($motorizado_recojo) ) {
		update_post_meta($shipment_id, 'wpcargo_motorizo_recojo', $motorizado_recojo);
	}
	
	if ( !empty($motorizado_entrega) ) {
		update_post_meta($shipment_id, 'wpcargo_motorizo_entrega', $motorizado_entrega);
	}
}
add_action('wpcie_after_save_csv_import', 'merc_auto_asignar_motorizado_importacion', 25, 2);

/**
 * Asignar unidades de full fitment
 */
function merc_asignar_unidades_full_fitment($post_id, $post) {
	if ( !$post || $post->post_type !== 'wpcargo_shipment' ) return;
	
	// Obtener tipo de envío
	$tipo_envio = get_post_meta($post_id, 'tipo_envio', true);
	if ( empty($tipo_envio) ) {
		$tipo_envio = get_post_meta($post_id, 'wpcargo_type_of_shipment', true);
	}
	
	$tipo_lower = strtolower(trim($tipo_envio));
	
	// Si es full_fitment, calcular y asignar unidades
	if ( stripos($tipo_envio, 'full_fitment') !== false || $tipo_lower === 'full fitment' ) {
		// Obtener cantidad de paquetes/items
		$paquetes_meta = get_post_meta($post_id, 'wpcargo_packages', true);
		$paquetes = is_array($paquetes_meta) ? count($paquetes_meta) : (empty($paquetes_meta) ? 1 : 1);
		
		// Asignar unidades = cantidad de paquetes
		update_post_meta($post_id, 'wpcargo_full_fitment_units', $paquetes);
	}
}
add_action('save_post_wpcargo_shipment', 'merc_asignar_unidades_full_fitment', 15, 2);
add_action('wpcie_after_save_csv_import', function($shipment_id, $record) {
	merc_asignar_unidades_full_fitment($shipment_id, get_post($shipment_id));
}, 22, 2);

/**
 * Validación: Prevenir duplicados de tracking
 */
function merc_validate_tracking_duplicate($shipment_id, $record) {
	if ( empty($shipment_id) ) return;
	
	// Obtener número de tracking
	$tracking = get_post_field('post_title', $shipment_id);
	if ( empty($tracking) ) {
		return;
	}
	
	// Buscar otros posts con el mismo título
	global $wpdb;
	$duplicates = $wpdb->get_var($wpdb->prepare("
		SELECT COUNT(ID) FROM {$wpdb->posts}
		WHERE post_type = 'wpcargo_shipment'
		AND post_status = 'publish'
		AND post_title = %s
		AND ID != %d
	", $tracking, $shipment_id));
	
	if ( $duplicates > 0 ) {
		wp_delete_post($shipment_id, true);
		error_log(sprintf('[MERC-FORM] Tracking duplicado eliminado: %s (ID: %d)', $tracking, $shipment_id));
	}
}
add_action('wpcie_after_save_csv_import', 'merc_validate_tracking_duplicate', 3, 2);

/**
 * Helper: Validación final de campos en formulario
 */
function merc_validate_form_shipment_data($shipment_data) {
	// Validar que tipo_envio no esté vacío
	if ( empty($shipment_data['tipo_envio']) ) {
		$shipment_data['tipo_envio'] = 'normal'; // valor por defecto
	}
	
	// Validar formato de monto (debe ser decimal)
	if ( isset($shipment_data['monto']) ) {
		$shipment_data['monto'] = floatval($shipment_data['monto']);
	}
	
	return $shipment_data;
}
add_filter('wpcfe_before_save_shipment_data', 'merc_validate_form_shipment_data');

/**
 * Hook: Bloquear cambios en envíos entregados
 */
function merc_prevent_edit_delivered_shipment($post_id, $post, $update) {
	if ( $post->post_type !== 'wpcargo_shipment' || !$update ) return;
	
	$status = get_post_meta($post_id, 'wpcargo_status', true);
	if ( $status === 'ENTREGADO' ) {
		wp_die(
			'<h2>Envío Entregado</h2>' .
			'<p>No se puede editar un envío que ya ha sido entregado.</p>' .
			'<p><a href="' . admin_url('admin.php?page=wpcargo-shipments') . '">← Volver al listado</a></p>'
		);
	}
}
// NOTA: Este hook se ejecuta muy tarde, considerar usar save_post_wpcargo_shipment con prioridad baja

/**
 * CSS y JS para mejoras del formulario
 */
function merc_form_enhancements_styles() {
	// Estilos globales para formulario
	?>
	<style>
		/* Estilos para campos tipo_envio */
		.merc-tipo-envio-selector {
			display: flex;
			gap: 15px;
			margin: 10px 0;
			flex-wrap: wrap;
		}
		.merc-tipo-envio-selector label {
			display: flex;
			align-items: center;
			gap: 8px;
			cursor: pointer;
			padding: 10px 15px;
			border: 2px solid #ddd;
			border-radius: 6px;
			transition: all 0.3s;
		}
		.merc-tipo-envio-selector label:hover {
			border-color: #3498db;
			background: #f0f8ff;
		}
		.merc-tipo-envio-selector input[type="radio"]:checked + span {
			background: #3498db;
			color: white;
		}
		
		/* Validación bloqueada */
		.merc-blocked-message {
			background: #ffebee;
			border-left: 4px solid #f44336;
			padding: 12px;
			border-radius: 4px;
			margin: 10px 0;
			color: #c62828;
		}
	</style>
	<?php
}
add_action('wp_head', 'merc_form_enhancements_styles');
add_action('admin_head', 'merc_form_enhancements_styles');
