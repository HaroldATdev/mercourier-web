<?php
/**
 * Sincronización de estados y transiciones de motorizado según estado del envío
 * 
 * @package merc-shipment-container-management
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Bloquear que POST sobrescriba wpcargo_driver directamente
 */
add_action('wp_insert_post_data', 'merc_remove_driver_from_post_data', 10, 2);
function merc_remove_driver_from_post_data($data, $postarr) {
    if ($data['post_type'] !== 'wpcargo_shipment' || empty($postarr['ID'])) {
        return $data;
    }
    
    if ($postarr['ID'] > 0 && isset($_POST['wpcargo_driver'])) {
        error_log("🚫 [REMOVE_DRIVER_POST] Removiendo wpcargo_driver de POST");
        unset($_POST['wpcargo_driver']);
    }
    
    return $data;
}

/**
 * Sincronizar wpcargo_driver con máxima prioridad cuando cambia motor izo
 */
add_action('updated_post_meta', 'merc_sync_driver_priority_first', 0, 4);
function merc_sync_driver_priority_first($meta_id, $post_id, $meta_key, $meta_value) {
    if ($meta_key !== 'wpcargo_motorizo_recojo' && $meta_key !== 'wpcargo_motorizo_entrega') {
        return;
    }
    
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'wpcargo_shipment') {
        return;
    }
    
    $estado_actual = get_post_meta($post_id, 'wpcargo_status', true);
    $estados_recojo = array('PENDIENTE', 'RECOGIDO', 'NO RECOGIDO');
    
    error_log("🔄 [SYNC_PRIORITY_FIRST] Meta: $meta_key | Valor: " . intval($meta_value) . " | Estado: $estado_actual");
    
    if (in_array($estado_actual, $estados_recojo)) {
        $motorizado_final = get_post_meta($post_id, 'wpcargo_motorizo_recojo', true);
        if (!empty($motorizado_final)) {
            update_post_meta($post_id, 'wpcargo_driver', intval($motorizado_final));
            error_log("   ✅ wpcargo_driver sincronizado desde RECOJO");
        }
    } else {
        $motorizado_final = get_post_meta($post_id, 'wpcargo_motorizo_entrega', true);
        if (!empty($motorizado_final)) {
            update_post_meta($post_id, 'wpcargo_driver', intval($motorizado_final));
            error_log("   ✅ wpcargo_driver sincronizado desde ENTREGA");
        }
    }
}

/**
 * Sincronizar wpcargo_driver cuando cambia wpcargo_motorizo_recojo
 */
add_action('updated_post_meta', 'merc_sync_on_motorizo_recojo_update', 10, 4);
function merc_sync_on_motorizo_recojo_update($meta_id, $post_id, $meta_key, $meta_value) {
    if ($meta_key !== 'wpcargo_motorizo_recojo') {
        return;
    }
    
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'wpcargo_shipment') {
        return;
    }
    
    $estado_actual = get_post_meta($post_id, 'wpcargo_status', true);
    $estados_recojo = array('PENDIENTE', 'RECOGIDO', 'NO RECOGIDO');
    
    error_log("🔄 [MOTORIZO_RECOJO_UPDATE] Envío #$post_id");
    
    if (in_array($estado_actual, $estados_recojo)) {
        if (!empty($meta_value)) {
            update_post_meta($post_id, 'wpcargo_driver', intval($meta_value));
            error_log("   ✅ wpcargo_driver sincronizado desde RECOJO");
        }
    }
}

/**
 * Sincronizar wpcargo_driver cuando cambia wpcargo_motorizo_entrega
 */
add_action('updated_post_meta', 'merc_sync_on_motorizo_entrega_update', 10, 4);
function merc_sync_on_motorizo_entrega_update($meta_id, $post_id, $meta_key, $meta_value) {
    if ($meta_key !== 'wpcargo_motorizo_entrega') {
        return;
    }
    
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'wpcargo_shipment') {
        return;
    }
    
    $estado_actual = get_post_meta($post_id, 'wpcargo_status', true);
    $estados_recojo = array('PENDIENTE', 'RECOGIDO', 'NO RECOGIDO');
    
    error_log("🔄 [MOTORIZO_ENTREGA_UPDATE] Envío #$post_id");
    
    if (!in_array($estado_actual, $estados_recojo)) {
        if (!empty($meta_value)) {
            update_post_meta($post_id, 'wpcargo_driver', intval($meta_value));
            error_log("   ✅ wpcargo_driver sincronizado desde ENTREGA");
        }
    }
}

/**
 * Sincronizar wpcargo_driver cuando se carga el formulario de edición
 */
add_action('wpcfe_before_load_shipment_form', 'merc_sync_driver_on_form_load', 99);
function merc_sync_driver_on_form_load($shipment_id) {
    if (!$shipment_id) {
        return;
    }
    
    $post = get_post($shipment_id);
    if (!$post || $post->post_type !== 'wpcargo_shipment') {
        return;
    }
    
    $estado_actual = get_post_meta($shipment_id, 'wpcargo_status', true);
    $estados_recojo = array('PENDIENTE', 'RECOGIDO', 'NO RECOGIDO');
    
    error_log("🔄 [SYNC_DRIVER_ON_LOAD] Envío #$shipment_id - Estado: '$estado_actual'");
    
    if (in_array($estado_actual, $estados_recojo)) {
        $motorizado = get_post_meta($shipment_id, 'wpcargo_motorizo_recojo', true);
        if (!empty($motorizado)) {
            update_post_meta($shipment_id, 'wpcargo_driver', intval($motorizado));
            error_log("   ✅ Sincronizado desde RECOJO");
        }
    } else {
        $motorizado = get_post_meta($shipment_id, 'wpcargo_motorizo_entrega', true);
        if (!empty($motorizado)) {
            update_post_meta($shipment_id, 'wpcargo_driver', intval($motorizado));
            error_log("   ✅ Sincronizado desde ENTREGA");
        }
    }
}

/**
 * Asignar estado FINAL correcto según tipo de envío
 */
function merc_asignar_estado_final_segun_tipo($post_id) {
    error_log("\n🔧 [ASIGNAR ESTADO FINAL] Envío #$post_id");
    
    $tipo_envio = get_post_meta($post_id, 'tipo_envio', true);
    
    if (empty($tipo_envio)) {
        error_log("   ⚠️ No hay tipo_envio");
        return;
    }
    
    $tipo_lower = strtolower(trim($tipo_envio));
    $estado_actual = get_post_meta($post_id, 'wpcargo_status', true);
    
    if ($tipo_lower === 'express' || 
        stripos($tipo_envio, 'agencia') !== false || 
        $tipo_lower === 'full_fitment' || 
        stripos($tipo_envio, 'full fitment') !== false) {
        
        if ($estado_actual !== 'RECEPCIONADO') {
            error_log("   ✅ Asignando RECEPCIONADO (AGENCIA/FULL)");
            update_post_meta($post_id, 'wpcargo_status', 'RECEPCIONADO');
        }
    }
    elseif ($tipo_lower === 'normal' || stripos($tipo_envio, 'emprendedor') !== false) {
        error_log("   📦 Tipo NORMAL/EMPRENDEDOR");
        
        if (empty($estado_actual)) {
            $estados_recojo = get_option('wpcpod_pickup_route_status', array());
            if (!empty($estados_recojo) && is_array($estados_recojo)) {
                $estado_inicial = reset($estados_recojo);
                update_post_meta($post_id, 'wpcargo_status', $estado_inicial);
                error_log("   ✔️ Estado inicial asignado");
            }
        }
    }
}
