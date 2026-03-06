<?php
/**
 * AJAX handlers para operaciones de contenedor y motorizado
 * 
 * @package merc-shipment-container-management
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX: Obtener datos del shipment (tipo, estado anterior/actual, motorizado)
 */
add_action('wp_ajax_merc_get_shipment_data', 'merc_get_shipment_data_ajax');
function merc_get_shipment_data_ajax() {
    $shipment_id = isset($_POST['shipment_id']) ? intval($_POST['shipment_id']) : 0;
    
    error_log("📥 [GET_SHIPMENT_DATA_AJAX] Solicitud para Envío #{$shipment_id}");
    
    if (empty($shipment_id)) {
        wp_send_json_error(['message' => 'ID de envío vacío']);
    }
    
    $tipo_envio = get_post_meta($shipment_id, 'tipo_envio', true);
    $estado_actual = get_post_meta($shipment_id, 'wpcargo_status', true);
    $estado_prev = get_post_meta($shipment_id, 'wpcargo_status_anterior', true);
    
    // Si no hay estado anterior, obtener del historial
    if (empty($estado_prev)) {
        $historial = get_post_meta($shipment_id, 'wpcargo_shipments_update', true);
        if (!empty($historial) && is_array($historial)) {
            if (isset($historial[1]) && is_array($historial[1])) {
                $estado_prev = $historial[1]['status'] ?? '';
            }
        }
    }
    
    // Obtener motorizados
    $motorizo_recojo = get_post_meta($shipment_id, 'wpcargo_motorizo_recojo', true);
    $motorizo_entrega = get_post_meta($shipment_id, 'wpcargo_motorizo_entrega', true);
    
    // Obtener cliente
    $customer_id = get_post_meta($shipment_id, 'wpcargo_customer_id', true);
    if (empty($customer_id)) {
        $customer_id = get_post_meta($shipment_id, 'registered_shipper', true);
    }
    $customer_name = '';
    if (!empty($customer_id)) {
        $u = get_userdata(intval($customer_id));
        if ($u) {
            $customer_name = trim($u->first_name . ' ' . $u->last_name) ?: $u->display_name;
        }
    }
    
    wp_send_json_success([
        'tipo_envio' => $tipo_envio,
        'estado_actual' => $estado_actual,
        'estado_prev' => $estado_prev,
        'shipment_id' => $shipment_id,
        'customer_id' => $customer_id,
        'customer_name' => $customer_name,
        'motorizo_recojo' => $motorizo_recojo,
        'motorizo_entrega' => $motorizo_entrega,
    ]);
}

/**
 * AJAX: Actualización masiva de estado (wpcr_bulk_update)
 */
add_action('wp_ajax_wpcr_bulk_update', 'merc_wpcr_bulk_update_ajax', 5);
add_action('wp_ajax_nopriv_wpcr_bulk_update', 'merc_wpcr_bulk_update_ajax', 5);
function merc_wpcr_bulk_update_ajax() {
    $selected_shipments = isset($_POST['selectedShipment']) ? sanitize_text_field($_POST['selectedShipment']) : '';
    $shipments_array = array_filter(array_map('intval', explode(',', $selected_shipments)));
    
    error_log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    error_log("🔄 [BULK_UPDATE] Procesando " . count($shipments_array) . " envío(s)");
    error_log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    
    if (empty($shipments_array)) {
        wp_send_json_error(['message' => 'No shipments selected']);
    }
    
    $receiver_fields = isset($_POST['receiverFields']) ? $_POST['receiverFields'] : [];
    $new_status = '';
    
    foreach ($receiver_fields as $field) {
        if ($field['index'] === 'status') {
            $new_status = sanitize_text_field($field['val']);
            break;
        }
    }
    
    error_log("📝 Nuevo Estado: '" . $new_status . "'");
    
    $updated_count = 0;
    
    foreach ($shipments_array as $shipment_id) {
        error_log("┌─ Procesando Envío #" . $shipment_id);
        
        $old_status = get_post_meta($shipment_id, 'wpcargo_status', true);
        error_log("│  Estado Actual: '" . $old_status . "'");
        
        // Si es LISTO PARA SALIR, guardar estado anterior
        if (!empty($new_status) && stripos($new_status, 'LISTO PARA SALIR') !== false && !empty($old_status)) {
            error_log("│  💾 Guardando estado anterior en historial...");
            
            $shipment_history = maybe_unserialize(get_post_meta($shipment_id, 'wpcargo_shipments_update', true));
            if (!is_array($shipment_history)) {
                $shipment_history = array();
            }
            
            $previous_state_record = array(
                'status' => $old_status,
                'date' => date('Y-m-d'),
                'time' => date('H:i:s'),
                'updated-name' => wp_get_current_user()->display_name,
                'location' => get_post_meta($shipment_id, 'location', true),
                'remarks' => 'Estado anterior (actualización masiva a LISTO PARA SALIR)'
            );
            
            array_unshift($shipment_history, $previous_state_record);
            update_post_meta($shipment_id, 'wpcargo_shipments_update', $shipment_history);
            update_post_meta($shipment_id, 'wpcargo_status_anterior', $old_status);
            
            error_log("│     ✅ Historial actualizado");
        }
        
        // Actualizar estado
        if (!empty($new_status)) {
            update_post_meta($shipment_id, 'wpcargo_status', $new_status);
            error_log("│  📌 Nuevo Estado: '" . $new_status . "'");
            $updated_count++;
        }
        
        error_log("└─ Completado\n");
    }
    
    error_log("✅ [BULK_UPDATE_FINALIZADO] " . $updated_count . " actualizado(s)\n");
    
    wp_send_json_success([
        'message' => 'Se actualizaron ' . $updated_count . ' envío(s)',
        'count' => $updated_count
    ]);
}

/**
 * AJAX: Remover envío del contenedor
 */
add_action('wp_ajax_merc_remove_shipment_from_container', 'merc_remove_shipment_from_container_ajax');
function merc_remove_shipment_from_container_ajax() {
    check_ajax_referer('merc_remove_shipment', 'nonce');
    
    $shipment_id = isset($_POST['shipment_id']) ? intval($_POST['shipment_id']) : 0;
    $meta_key = isset($_POST['meta_key']) ? sanitize_text_field($_POST['meta_key']) : '';
    
    if (!$shipment_id || !in_array($meta_key, ['shipment_container_recojo', 'shipment_container_entrega'])) {
        wp_send_json_error(['message' => 'Parámetros no válidos']);
    }
    
    $deleted = delete_post_meta($shipment_id, $meta_key);
    
    if ($deleted) {
        wp_send_json_success(['message' => 'Envío removido correctamente']);
    } else {
        wp_send_json_error(['message' => 'No se pudo remover el envío']);
    }
}

/**
 * AJAX: Obtener número de estadosposibles
 */
add_action('wp_ajax_wpcpod_get_all_possible_statuses', 'merc_get_all_possible_statuses_ajax');
function merc_get_all_possible_statuses_ajax() {
    $statuses = maybe_unserialize(get_option('wpcpod_shipment_statuses', array()));
    
    if (!is_array($statuses)) {
        $statuses = array();
    }
    
    wp_send_json_success(['statuses' => $statuses]);
}
