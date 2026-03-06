<?php
/**
 * Reasignación de contenedores cuando el envío llega a EN BASE MERCOURIER
 * 
 * @package merc-shipment-container-management
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Hook: Reasignar contenedor cuando estado cambia a EN BASE MERCOURIER
 * Desasigna contenedor de RECOJO y asigna de ENTREGA basado en distrito destino
 */
add_action('updated_post_meta', 'merc_reasignar_en_base_mercourier', 10, 4);
add_action('wpc_add_sms_shipment_history', 'merc_procesar_cambio_estado_base_mercourier', 9, 1);

function merc_procesar_cambio_estado_base_mercourier($post_id) {
    $status = get_post_meta($post_id, 'wpcargo_status', true);
    
    if (stripos($status, 'EN BASE MERCOURIER') !== false) {
        error_log("🔔 HOOK ALTERNATIVO: Detectado EN BASE MERCOURIER");
        merc_reasignar_en_base_mercourier(null, $post_id, 'wpcargo_status', $status);
    }
}

function merc_reasignar_en_base_mercourier($meta_id, $post_id, $meta_key, $meta_value) {
    if (get_post_type($post_id) !== 'wpcargo_shipment') {
        return;
    }
    
    if ($meta_key !== 'wpcargo_status') {
        return;
    }
    
    if (stripos($meta_value, 'EN BASE MERCOURIER') === false) {
        return;
    }
    
    $tipo_envio = get_post_meta($post_id, 'tipo_envio', true);
    
    // Solo procesar MERC EMPRENDEDOR (normal)
    if (strtolower($tipo_envio) !== 'normal') {
        error_log("⏭️ Reasignación EN BASE - No es MERC EMPRENDEDOR");
        return;
    }
    
    $contenedor_entrega_actual = get_post_meta($post_id, 'shipment_container_entrega', true);
    $ya_tiene_entrega = !empty($contenedor_entrega_actual);
    
    error_log("🔄 Reasignación EN BASE MERCOURIER - Envío #{$post_id}");
    
    $container_recojo = get_post_meta($post_id, 'shipment_container_recojo', true);
    error_log("📦 Contenedor recojo: #{$container_recojo}");
    
    // Transferir motorizado
    $motorizado_recojo = get_post_meta($post_id, 'wpcargo_motorizo_recojo', true);
    $motorizado_entrega = get_post_meta($post_id, 'wpcargo_motorizo_entrega', true);
    
    $motorizado_para_entrega = !empty($motorizado_entrega) ? $motorizado_entrega : $motorizado_recojo;
    
    if (!empty($motorizado_para_entrega)) {
        delete_post_meta($post_id, 'wpcargo_driver');
        add_post_meta($post_id, 'wpcargo_driver', $motorizado_para_entrega);
        
        if (empty($motorizado_entrega) && !empty($motorizado_recojo)) {
            update_post_meta($post_id, 'wpcargo_motorizo_entrega', $motorizado_recojo);
            error_log("🚚 Motorizado transferido de recojo a entrega");
        }
        
        error_log("📍 wpcargo_driver actualizado a #{$motorizado_para_entrega}");
    }
    
    // Asignar contenedor de entrega si no lo tiene
    if (!$ya_tiene_entrega) {
        $distrito = get_post_meta($post_id, 'wpcargo_distrito_destino', true);
        
        if (empty($distrito)) {
            error_log("⚠️ No se encontró distrito de destino");
            return;
        }
        
        error_log("📍 Buscando contenedor ENTREGA para: {$distrito}");
        
        $args = array(
            'post_type'      => 'shipment_container',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC'
        );
        
        $containers = get_posts($args);
        $container_encontrado = _merc_find_container($containers, $distrito);
        
        if ($container_encontrado) {
            update_post_meta($post_id, 'shipment_container_entrega', $container_encontrado);
            error_log("✅ Contenedor ENTREGA asignado #{$container_encontrado}");
            do_action('merc_after_assign_entrega_container', $post_id, $container_encontrado, $distrito);
        } else {
            error_log("❌ No se encontró contenedor de ENTREGA");
        }
    } else {
        error_log("✅ Ya tiene contenedor entrega #{$contenedor_entrega_actual}");
    }
}

/**
 * Hook: Desasignar contenedor cuando está EN BASE MERCOURIER
 */
add_action('updated_post_meta', 'merc_desasignar_contenedor_en_base', 10, 4);
function merc_desasignar_contenedor_en_base($meta_id, $post_id, $meta_key, $meta_value) {
    if (get_post_type($post_id) !== 'wpcargo_shipment') {
        return;
    }
    
    if ($meta_key !== 'wpcargo_shipment_history') {
        return;
    }
    
    $history = get_post_meta($post_id, 'wpcargo_shipment_history', true);
    
    if (!is_array($history) || empty($history)) {
        return;
    }
    
    $ultimo_estado = end($history);
    
    if (isset($ultimo_estado['status'])) {
        $estado_normalizado = merc_normalizar_texto($ultimo_estado['status']);
        
        if (stripos($estado_normalizado, 'EN BASE MERCOURIER') !== false || 
            stripos($estado_normalizado, 'BASE MERCOURIER') !== false) {
            delete_post_meta($post_id, 'shipment_container');
            error_log("🗑️ Contenedor desasignado (EN BASE MERCOURIER)");
        }
    }
}
