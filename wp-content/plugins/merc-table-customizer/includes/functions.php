<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Helpers globales del plugin merc-table-customizer.
 */

/**
 * Carga un template del plugin.
 *
 * @param string $file  Ruta relativa desde admin/templates/  (ej. 'frontend/table-row.tpl.php')
 * @param array  $data  Variables a inyectar en el template.
 */
function mtc_include_template( string $file, array $data = [] ): void {
	$path = MERC_TABLE_PATH . "admin/templates/{$file}";
	if ( ! file_exists( $path ) ) {
		wp_die( "Plantilla no encontrada: {$file}" );
	}
	if ( $data ) {
		extract( $data, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract
	}
	include $path;
}

/**
 * Remover columnas default de la tabla de shipments
 */
function merc_manipulate_shipment_columns() {
	// Remove Shipment Type Column
	remove_action( 'wpcfe_shipment_table_header', 'wpcfe_shipment_table_header_type', 25 ); 
	remove_action( 'wpcfe_shipment_table_data', 'wpcfe_shipment_table_data_type', 25 );
	
	// Remove Shipper / Receiver Column
	remove_action( 'wpcfe_shipment_after_tracking_number_header', 'wpcfe_shipper_receiver_shipment_header_callback', 25 );
	remove_action( 'wpcfe_shipment_after_tracking_number_data', 'wpcfe_shipper_receiver_shipment_data_callback', 25 );
	
	// Remove Container Column
	remove_action( 'wpcfe_shipment_table_header', 'wpcsc_shipment_container_table_header', 10 );
	remove_action( 'wpcfe_shipment_table_data', 'wpcsc_shipment_container_table_data', 10 );
	
	// Quitar Status (lo reubicamos)
	remove_action( 'wpcfe_shipment_table_header', 'wpcfe_shipment_table_header_status', 25 );
	remove_action( 'wpcfe_shipment_table_data', 'wpcfe_shipment_table_data_status', 25 );
	
	// Quitar Print (lo reubicamos y renombramos)
	remove_action( 'wpcfe_shipment_table_header_action', 'wpcfe_shipment_table_header_action_print', 25 );
	remove_action( 'wpcfe_shipment_table_data_action', 'wpcfe_shipment_table_action_print', 25 );
	
	// ✅ Mantener action rows (View/Edit/Delete) - Se renderizarán en la columna Tienda
	// (NO remover para que aparezcan en wpcfe_shipment_action_rows)
}
add_action( 'init', 'merc_manipulate_shipment_columns' );
add_action( 'plugins_loaded', 'merc_manipulate_shipment_columns', 20 );

/**
 * Agregar columnas en el orden correcto via wpcfe_shipment_table_header
 */
function merc_custom_table_header() {
	echo '<th>Nombre de Tienda</th>';
	echo '<th>Distrito de Recojo</th>';
	echo '<th>Distrito de Entrega</th>';
	echo '<th>Fecha</th>';
	echo '<th>Tipo de Servicio</th>';
	echo '<th>Cambio de Producto</th>';
	echo '<th>Motorizado Recojo</th>';
	echo '<th>Motorizado Entrega</th>';
}
add_action( 'wpcfe_shipment_table_header', 'merc_custom_table_header', 99 );

/**
 * Agregar datos en el mismo orden
 */
function merc_custom_table_data( $shipment_id ) {
	// 1. NOMBRE DE TIENDA
	$tienda_name = get_post_meta( $shipment_id, 'wpcargo_tiendaname', true );
	$tienda_display = !empty($tienda_name) ? esc_html($tienda_name) : '<span style="color:#999;">N/A</span>';
	$action_rows = function_exists('wpcfe_shipment_action_rows') ? wpcfe_shipment_action_rows( $shipment_id ) : array();
	$action_html = '';
	if ( !empty($action_rows) ) {
		$action_html = '<div class="wpcfe-action-row" style="margin-top:6px;">' . implode(' | ', $action_rows) . '</div>';
	}
	echo '<td>' . $tienda_display . $action_html . '</td>';

	// 2. DISTRITO DE RECOJO
	$distrito_recojo = get_post_meta( $shipment_id, 'wpcargo_distrito_recojo', true );
	echo '<td>' . ( !empty($distrito_recojo) ? esc_html($distrito_recojo) : '<span style="color:#999;">N/A</span>' ) . '</td>';

	// 3. DISTRITO DE ENTREGA
	$distrito_destino = get_post_meta( $shipment_id, 'wpcargo_distrito_destino', true );
	if ( empty($distrito_destino) ) {
		$distrito_destino = get_post_meta( $shipment_id, 'wpcargo_destination', true );
	}
	echo '<td>' . ( !empty($distrito_destino) ? esc_html($distrito_destino) : '<span style="color:#999;">N/A</span>' ) . '</td>';

	// 4. FECHA
	$fecha_envio = get_post_meta( $shipment_id, 'wpcargo_calendarenvio', true );
	if ( empty($fecha_envio) ) {
		$fecha_envio = date( 'd/m/Y', strtotime( get_post_field('post_date', $shipment_id) ) );
	}
	echo '<td>' . esc_html($fecha_envio) . '</td>';

	// 5. TIPO DE SERVICIO
	$tipo_envio = get_post_meta( $shipment_id, 'tipo_envio', true );
	$tipo_lower = strtolower( trim($tipo_envio) );
	if ( $tipo_lower === 'express' || stripos($tipo_envio, 'agencia') !== false ) {
		$tipo_display = '<span style="background:#ff5722;color:white;padding:4px 12px;border-radius:4px;font-weight:bold;font-size:11px;">MERC AGENCIA</span>';
	} elseif ( $tipo_lower === 'normal' || stripos($tipo_envio, 'emprendedor') !== false ) {
		$tipo_display = '<span style="background:#2196f3;color:white;padding:4px 12px;border-radius:4px;font-weight:bold;font-size:11px;">MERC EMPRENDEDOR</span>';
	} elseif ( !empty($tipo_envio) ) {
		$tipo_display = '<span style="background:#ff9800;color:white;padding:4px 8px;border-radius:4px;font-size:10px;">⚠️ ' . esc_html($tipo_envio) . '</span>';
	} else {
		$tipo_display = '<span style="background:#757575;color:white;padding:4px 8px;border-radius:4px;font-size:10px;">Sin tipo</span>';
	}
	echo '<td style="text-align:center;">' . $tipo_display . '</td>';

	// 6. CAMBIO DE PRODUCTO
	$cambio_producto = get_post_meta( $shipment_id, 'cambio_producto', true );
	if ( $cambio_producto === 'Sí' ) {
		$cambio_display = '<span style="background:#c62828;color:#fff;padding:4px 12px;border-radius:14px;font-weight:bold;font-size:11px;">⚠ SÍ</span>';
	} else {
		$cambio_display = '<span style="background:#2e7d32;color:#fff;padding:4px 12px;border-radius:14px;font-weight:bold;font-size:11px;">NO</span>';
	}
	echo '<td style="text-align:center;">' . $cambio_display . '</td>';

	// 7. MOTORIZADO RECOJO
	$recojo_id = get_post_meta( $shipment_id, 'wpcargo_motorizo_recojo', true );
	if ( !empty($recojo_id) ) {
		$first  = get_user_meta( $recojo_id, 'first_name', true );
		$last   = get_user_meta( $recojo_id, 'last_name', true );
		$nombre = trim( $first . ' ' . $last );
		if ( empty($nombre) ) {
			$u = get_userdata($recojo_id);
			$nombre = $u ? $u->display_name : '-';
		}
		echo '<td>' . esc_html($nombre) . '</td>';
	} else {
		echo '<td><span style="color:#999;">-</span></td>';
	}

	// 8. MOTORIZADO ENTREGA
	$entrega_id = get_post_meta( $shipment_id, 'wpcargo_motorizo_entrega', true );
	if ( !empty($entrega_id) ) {
		$first  = get_user_meta( $entrega_id, 'first_name', true );
		$last   = get_user_meta( $entrega_id, 'last_name', true );
		$nombre = trim( $first . ' ' . $last );
		if ( empty($nombre) ) {
			$u = get_userdata($entrega_id);
			$nombre = $u ? $u->display_name : '-';
		}
		echo '<td>' . esc_html($nombre) . '</td>';
	} else {
		echo '<td><span style="color:#999;">-</span></td>';
	}
}
add_action( 'wpcfe_shipment_table_data', 'merc_custom_table_data', 99 );

/**
 * Renombrar menú para drivers
 */
function custom_rename_driver_menu_callback( $menu_items ){
	$current_user = wp_get_current_user();
	if( !in_array( 'wpcargo_driver', $current_user->roles ) ){
		return $menu_items;
	}
	
	if( isset($menu_items['wpcpod-pickup-route']) ){
		$menu_items['wpcpod-pickup-route']['label'] = 'Recojo de mercadería';
	}
	
	if( isset($menu_items['wpcpod-route']) ){
		$menu_items['wpcpod-route']['label'] = 'Entrega de mercadería';
	}
	
	return $menu_items; 
}
add_filter( 'wpcfe_after_sidebar_menus', 'custom_rename_driver_menu_callback', 10 );

/**
 * Renombrar menú "Receiving" a "Escáner"
 */
function custom_rename_receiving_menu_callback( $menu_items ){
	if( isset($menu_items['receiving-menu']) ){
		$menu_items['receiving-menu']['label'] = 'Escáner';
	}
	return $menu_items; 
}
add_filter( 'wpcfe_after_sidebar_menu_items', 'custom_rename_receiving_menu_callback' );

/**
 * Bloquear calendario de envíos (mecanismo simplificado)
 */
function custom_block_calendar_script() {
	if ( isset($_GET['wpcfe']) && $_GET['wpcfe'] == 'add' ) { 
		$is_admin = current_user_can('administrator');
		$current_user = wp_get_current_user();
		$is_client = in_array('wpcargo_client', $current_user->roles);
		$tiene_deudas = false;
		$tiene_desbloqueo_manual = false;
		
		if ($is_client) {
			$hoy = current_time('Y-m-d');
			$desbloqueo_manual_fecha = get_user_meta($current_user->ID, 'merc_desbloqueado_manualmente_fecha', true);
			$envios_permitidos = intval(get_user_meta($current_user->ID, 'merc_desbloqueo_manual_envios_permitidos', true));
			$tiene_desbloqueo_manual = ($desbloqueo_manual_fecha === $hoy && $envios_permitidos > 0);
		}
		?>
		<script>
			document.addEventListener("DOMContentLoaded", function () {
				const dateInput = document.querySelector("#wpcargo_pickup_date_picker");
				const isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
				const isClient = <?php echo $is_client ? 'true' : 'false'; ?>;
				const tieneDeudas = <?php echo $tiene_deudas ? 'true' : 'false'; ?>;
				const tieneDesbloqueoManual = <?php echo $tiene_desbloqueo_manual ? 'true' : 'false'; ?>;
				const forcedDateStr = <?php $fd = get_user_meta($current_user->ID, 'merc_force_pickup_date', true); echo json_encode($fd ? $fd : ''); ?>;
		
				if (dateInput) {
					const currentDate = new Date();
					let targetDate = new Date(currentDate);

					if (forcedDateStr) {
						const parts = forcedDateStr.split('/');
						if (parts.length === 3) {
							const d = parseInt(parts[0],10);
							const m = parseInt(parts[1],10) - 1;
							const y = parseInt(parts[2],10);
							const parsed = new Date(y, m, d);
							if (!isNaN(parsed.getTime())) {
								const todayZero = new Date(currentDate.getFullYear(), currentDate.getMonth(), currentDate.getDate());
								if (parsed.getTime() >= todayZero.getTime()) {
									targetDate = parsed;
									console.log('🔒 Fecha forzada detectada: ' + forcedDateStr);
								}
							}
						}
					}
		  
					function adjustTargetDate() {
						if (isClient) {
							if (tieneDeudas) {
								console.log('⚠️ Cliente con deudas - Calendario bloqueado');
								return;
							}
							
							if (tieneDesbloqueoManual) {
								console.log('🔓 DESBLOQUEO MANUAL ACTIVO');
								if (targetDate.getDay() === 0) {
									targetDate.setDate(targetDate.getDate() + 1);
								}
								return;
							}
							
							if (targetDate.getDay() === 0) {
								targetDate.setDate(targetDate.getDate() + 1);
							}
						} 
						else if (isAdmin) {
							console.log('👑 Administrador - Sin restricciones');
						}
						else {
							if (targetDate.getDay() === 0) {
								targetDate.setDate(targetDate.getDate() + 1);
							}
						}
					}
					
					if (!isAdmin) {
						adjustTargetDate();
					}
					
					function generateDisabledDates() {
						const disabledDays = [];
						const end = new Date(targetDate.getFullYear(), targetDate.getMonth(), targetDate.getDate());
						const start = new Date(end);
						start.setDate(start.getDate() - 365);
						for (let d = new Date(start); d < end; d.setDate(d.getDate() + 1)) {
							disabledDays.push([d.getFullYear(), d.getMonth(), d.getDate()]);
						}
						const currentYear = currentDate.getFullYear();
						for (let year = currentYear; year <= currentYear + 10; year++) {
							for (let month = 0; month < 12; month++) {
								for (let day = 1; day <= 31; day++) {
									const tempDate = new Date(year, month, day);
									if (tempDate.getFullYear() === year && tempDate.getDay() === 0) {
										disabledDays.push([year, month, day]);
									}
								}
							}
						}
						return disabledDays;
					}
					
					jQuery.extend(jQuery.fn.pickadate.defaults, {
						monthsFull: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'],
						monthsShort: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
						weekdaysFull: ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'],
						weekdaysShort: ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'],
						today: 'Hoy',
						clear: 'Limpiar',
						close: 'Cerrar',
						firstDay: 1,
						format: 'dd/mm/yyyy',
						formatSubmit: 'dd/mm/yyyy'
					});
					
					const calendarConfig = {
						format: "dd/mm/yyyy",
						formatSubmit: "dd/mm/yyyy"
					};
					
					if (!isAdmin) {
						calendarConfig.min = targetDate;
						calendarConfig.disable = generateDisabledDates();
					}
					
					jQuery(dateInput).pickadate(calendarConfig);
					
					function formatDateDDMMYYYY(date) {
						const day = String(date.getDate()).padStart(2, '0');
						const month = String(date.getMonth() + 1).padStart(2, '0');
						const year = date.getFullYear();
						return day + '/' + month + '/' + year;
					}
					
					if (!isAdmin) {
						jQuery(dateInput).val(formatDateDDMMYYYY(targetDate));
					} else {
						jQuery(dateInput).val(formatDateDDMMYYYY(currentDate));
					}
				}
			});
		</script>
		<?php
	}
}
add_action('wp_footer', 'custom_block_calendar_script');
