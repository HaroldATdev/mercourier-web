<?php
/**
 * Liquidaciones y Pagos con Vouchers para MERCourier
 * - Sistema de liquidaciones
 * - Gestión de vouchers de pago
 * - Helper: merc_pickup_date_is_today()
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * HELPER: merc_pickup_date_is_today()
 * Verifica si la fecha de recojo de un envío es HOY
 */
function merc_pickup_date_is_today( $shipment_id ) {
	if ( empty($shipment_id) ) return false;
	$today = current_time('Y-m-d');

	// Posibles metas donde se guarda la fecha
	$candidates = array(
		get_post_meta($shipment_id, 'wpcargo_pickup_date_picker', true),
		get_post_meta($shipment_id, 'wpcargo_calendarenvio', true),
		get_post_meta($shipment_id, 'wpcargo_pickup_date', true),
	);

	foreach ( $candidates as $val ) {
		if ( empty($val) ) continue;
		$val = trim((string) $val);

		// Si ya está en formato Y-m-d
		if ( preg_match('/^\d{4}-\d{2}-\d{2}$/', $val) ) {
			if ( $val === $today ) return true;
			continue;
		}

		// Intentar parsear dd/mm/YYYY
		$parts = preg_split('/[\/\-\.]/', $val);
		if ( count($parts) === 3 ) {
			if ( strlen($parts[0]) === 4 ) {
				// yyyy-mm-dd
				$normalized = sprintf('%04d-%02d-%02d', intval($parts[0]), intval($parts[1]), intval($parts[2]));
			} else {
				// dd/mm/yyyy
				$day = intval($parts[0]);
				$mon = intval($parts[1]);
				$yr = intval($parts[2]);
				if ( $yr < 100 ) $yr += 2000;
				$normalized = sprintf('%04d-%02d-%02d', $yr, $mon, $day);
			}
			if ( $normalized === $today ) return true;
		}

		// Intentar strtotime fallback
		$ts = strtotime($val);
		if ( $ts !== false ) {
			if ( date('Y-m-d', $ts) === $today ) return true;
		}
	}

	return false;
}

/**
 * HELPER: merc_save_pod_payment_methods()
 * Guardar métodos de pago del POD (comprobantes)
 */
function merc_save_pod_payment_methods($shipment_id, $form_data) {
	if ( empty($shipment_id) ) return;
	
	// Extrae métodos de pago del formulario
	$payment_methods = array();
	
	if ( isset($form_data['payment_method']) ) {
		$method = sanitize_text_field($form_data['payment_method']);
		$payment_methods[] = array(
			'metodo' => $method,
			'monto' => isset($form_data['monto']) ? floatval($form_data['monto']) : 0.0,
			'timestamp' => current_time('mysql'),
		);
	}
	
	if ( !empty($payment_methods) ) {
		update_post_meta( $shipment_id, 'pod_payment_methods', json_encode($payment_methods) );
	}
}
add_action('wpcargo_extra_pod_saving', 'merc_save_pod_payment_methods', 10, 2);

/**
 * HELPER: merc_get_shipment_voucher_url()
 * Obtener URL del voucher de pago de un envío
 */
function merc_get_shipment_voucher_url( $shipment_id ) {
	if ( empty($shipment_id) ) return '';
	
	$voucher_meta = get_post_meta($shipment_id, 'merc_payment_voucher', true);
	
	if ( is_array($voucher_meta) && isset($voucher_meta['url']) ) {
		return $voucher_meta['url'];
	}
	
	if ( is_string($voucher_meta) ) {
		return $voucher_meta;
	}
	
	return '';
}

/**
 * HELPER: merc_get_shipment_voucher_thumb_html()
 * HTML con miniatura del voucher
 */
function merc_get_shipment_voucher_thumb_html( $shipment_id, $size = 60 ) {
	$url = merc_get_shipment_voucher_url($shipment_id);
	if ( empty($url) ) return '❌ Sin voucher';
	
	return '<img src="' . esc_url($url) . '" width="' . intval($size) . '" height="' . intval($size) . '" style="border-radius: 4px;" />';
}

/**
 * HELPER: merc_display_payment_methods_frontend()
 * Mostrar métodos de pago en el frontend
 */
function merc_display_payment_methods_frontend($shipment_detail) {
	if ( empty($shipment_detail) ) return;
	
	$shipment_id = $shipment_detail;
	$pod_methods = get_post_meta($shipment_id, 'pod_payment_methods', true);
	
	if ( empty($pod_methods) ) return;
	
	$methods = is_string($pod_methods) ? json_decode($pod_methods, true) : $pod_methods;
	if ( !is_array($methods) ) return;
	?>
	<div style="margin-top: 20px; padding: 15px; background: #f5f5f5; border-radius: 4px;">
		<h4 style="margin-top: 0;">💳 Métodos de Pago</h4>
		<?php foreach ( $methods as $method ) : ?>
			<div style="padding: 8px 0; border-bottom: 1px solid #ddd;">
				<span style="font-weight: bold;"><?php echo esc_html(ucfirst($method['metodo'])); ?></span>: 
				<span style="color: #27ae60; font-weight: bold;">S/. <?php echo number_format($method['monto'], 2); ?></span>
				<?php if ( isset($method['timestamp']) ) : ?>
					<span style="color: #999; font-size: 12px;"> - <?php echo esc_html($method['timestamp']); ?></span>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
}
add_action('wpcargo_after_package_details', 'merc_display_payment_methods_frontend', 20);

/**
 * HELPER: merc_is_shipment_liquidation_verified()
 * Verificar si la liquidación de un envío fué verificada
 */
function merc_is_shipment_liquidation_verified( $shipment_id ) {
	$verified_meta = get_post_meta($shipment_id, 'merc_liquidation_verified', true);
	return !empty($verified_meta) && $verified_meta === '1';
}

/**
 * AJAX: merc_liquidar_pago()
 * Liquidar pago individual de envío
 */
function merc_liquidar_pago_ajax() {
	if ( !isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'merc_liquidar_pago') ) {
		wp_send_json_error(array('message' => 'Falló verificación de seguridad'));
		return;
	}
	
	if ( !current_user_can('administrator') ) {
		wp_send_json_error(array('message' => 'Acceso denegado'));
		return;
	}
	
	$shipment_id = isset($_POST['shipment_id']) ? intval($_POST['shipment_id']) : 0;
	$tipo = isset($_POST['tipo']) ? sanitize_text_field($_POST['tipo']) : '';
	
	if ( empty($shipment_id) || empty($tipo) ) {
		wp_send_json_error(array('message' => 'Datos inválidos'));
		return;
	}
	
	// Marcar como liquidado según tipo
	if ( $tipo === 'motorizado' ) {
		update_post_meta($shipment_id, 'wpcargo_estado_pago_motorizado', 'liquidado');
	} else if ( $tipo === 'remitente' ) {
		update_post_meta($shipment_id, 'wpcargo_included_in_liquidation', 1);
	}
	
	wp_send_json_success(array('message' => 'Liquidación registrada correctamente'));
}
add_action('wp_ajax_merc_liquidar_pago', 'merc_liquidar_pago_ajax');

/**
 * AJAX: merc_liquidar_todo_ajax()
 * Liquidar TODO (masivo) de un usuario
 */
function merc_liquidar_todo_ajax() {
	if ( !isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'merc_liquidar_todo') ) {
		wp_send_json_error(array('message' => 'Falló verificación de seguridad'));
		return;
	}
	
	if ( !current_user_can('administrator') ) {
		wp_send_json_error(array('message' => 'Acceso denegado'));
		return;
	}
	
	$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
	$tipo = isset($_POST['tipo']) ? sanitize_text_field($_POST['tipo']) : '';
	
	if ( empty($user_id) || empty($tipo) ) {
		wp_send_json_error(array('message' => 'Datos inválidos'));
		return;
	}
	
	// Si hay archivo voucher, procesarlo
	if ( isset($_FILES['voucher']) && $_FILES['voucher']['size'] > 0 ) {
		$upload = wp_handle_upload($_FILES['voucher'], array('test_form' => false));
		if ( isset($upload['url']) ) {
			update_user_meta($user_id, 'merc_latest_voucher_url', $upload['url']);
		}
	}
	
	wp_send_json_success(array('message' => 'Liquidación masiva registrada correctamente'));
}
add_action('wp_ajax_merc_liquidar_todo', 'merc_liquidar_todo_ajax');

/**
 * AJAX: merc_get_voucher_ajax()
 * Obtener voucher de pago
 */
function merc_get_voucher_ajax() {
	if ( !isset($_POST['shipment_id']) ) {
		wp_send_json_error('No se especificó shipment_id');
		return;
	}
	
	$shipment_id = intval($_POST['shipment_id']);
	$tipo = isset($_POST['tipo']) ? sanitize_text_field($_POST['tipo']) : '';
	
	$voucher_url = merc_get_shipment_voucher_url($shipment_id);
	
	$vouchers = array();
	if ( !empty($voucher_url) ) {
		$vouchers[] = array(
			'url' => $voucher_url,
			'monto' => 0.0,
		);
	}
	
	wp_send_json_success(array(
		'vouchers' => $vouchers,
		'message' => 'Vouchers cargados correctamente'
	));
}
add_action('wp_ajax_merc_get_voucher', 'merc_get_voucher_ajax');

/**
 * Sistema de Liquidaciones
 */
function merc_admin_liquidaciones( $fecha_inicio = '', $fecha_fin = '', $filtro_estado = '', $filtro_cliente = 0 ) {
	global $wpdb;
	
	$fecha_inicio = !empty($fecha_inicio) ? sanitize_text_field($fecha_inicio) : date('Y-m-d');
	$fecha_fin = !empty($fecha_fin) ? sanitize_text_field($fecha_fin) : date('Y-m-d');
	?>
	
	<div class="merc-liquidaciones-section">
		<h3>📜 Historial de Liquidaciones</h3>
		
		<div style="background: #f9f9f9; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
			<p style="margin: 0;">
				Rango: <strong><?php echo esc_html($fecha_inicio); ?></strong> 
				a <strong><?php echo esc_html($fecha_fin); ?></strong>
			</p>
		</div>
		
		<table class="merc-entregas-table" style="width: 100%; border-collapse: collapse;">
			<thead>
				<tr style="background: #e9ecef;">
					<th style="padding: 12px; border: 1px solid #dee2e6; text-align: left;">Fecha</th>
					<th style="padding: 12px; border: 1px solid #dee2e6; text-align: left;">Tipo</th>
					<th style="padding: 12px; border: 1px solid #dee2e6; text-align: left;">Usuario</th>
					<th style="padding: 12px; border: 1px solid #dee2e6; text-align: left;">Monto</th>
					<th style="padding: 12px; border: 1px solid #dee2e6; text-align: left;">Estado</th>
					<th style="padding: 12px; border: 1px solid #dee2e6; text-align: left;">Acciones</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td colspan="6" style="text-align: center; padding: 40px; color: #999;">
						📊 No hay liquidaciones registradas en este período
					</td>
				</tr>
			</tbody>
		</table>
	</div>
	<?php
}

