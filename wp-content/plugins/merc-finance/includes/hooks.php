<?php
if (!defined('ABSPATH')) exit;

// Hooks y acciones para finanzas


// Interceptar cambios de estado antes de actualizar
add_action('update_post_meta', function($meta_id, $post_id, $meta_key, $meta_value) {
    // Interceptar ANTES de que se actualice, para capturar el estado anterior
    if ($meta_key !== 'wpcargo_status') {
        return;
    }
    
    $estado_actual = get_post_meta($post_id, 'wpcargo_status', true);
    
    // Si el nuevo estado es "LISTO PARA SALIR" y hay un estado anterior diferente
    if (!empty($meta_value) && stripos($meta_value, 'LISTO PARA SALIR') !== false && !empty($estado_actual) && $estado_actual !== $meta_value) {
        error_log("🔵 [BEFORE_META_UPDATE] Interceptando cambio de estado en Envío #" . $post_id);
        error_log("   Estado Actual: '" . $estado_actual . "' -> Nuevo: '" . $meta_value . "'");
        
        // Guardar en meta específico ANTES de que se actualice
        update_post_meta($post_id, 'wpcargo_status_anterior', $estado_actual);
        error_log("   ✅ Meta 'wpcargo_status_anterior' establecido a: '" . $estado_actual . "'");
        
        // También agregar al historial - PERO SOLO si no existe un registro reciente igual
        $historial = maybe_unserialize(get_post_meta($post_id, 'wpcargo_shipments_update', true));
        if (!is_array($historial)) {
            $historial = array();
        }
        
        // Verificar si el primer registro ya es identical (evitar duplicados)
        $crear_registro = true;
        if (!empty($historial) && is_array($historial[0])) {
            $first = $historial[0];
            if ($first['status'] === $estado_actual && 
                strpos($first['remarks'], 'Estado anterior') !== false) {
                error_log("   ℹ️  Registro anterior ya existe, evitando duplicado");
                $crear_registro = false;
            }
        }
        
        if ($crear_registro) {
            // Crear registro del estado anterior
            $previous_state_record = array(
                'status' => $estado_actual,
                'date' => current_time('Y-m-d'),
                'time' => current_time('H:i:s'),
                'updated-name' => wp_get_current_user()->display_name,
                'location' => get_post_meta($post_id, 'location', true),
                'remarks' => 'Estado anterior (cambio a LISTO PARA SALIR)'
            );
            
            array_unshift($historial, $previous_state_record);
            update_post_meta($post_id, 'wpcargo_shipments_update', $historial);
            error_log("   ✅ Historial actualizado (total: " . count($historial) . " registros)");
        }
    }
}, 10, 4);

// Detectar cambios de estado para sincronizar costo de envío
add_action('updated_post_meta', function($meta_id, $post_id, $meta_key, $meta_value) {
    if ( defined('MERC_DEBUG') && MERC_DEBUG ) {
        error_log(sprintf('MERC_DEBUG_META_UPDATE - meta_id=%s post_id=%s meta_key=%s meta_value=%s', $meta_id, $post_id, $meta_key, is_scalar($meta_value) ? $meta_value : json_encode($meta_value)));
    }
    if ( $meta_key !== 'wpcargo_status' ) {
        if ( defined('MERC_DEBUG') && MERC_DEBUG ) {
            error_log('MERC_DEBUG_META_UPDATE - Ignorado (no es wpcargo_status)');
        }
        return;
    }

    merc_sync_service_cost_by_status( $post_id );
}, 10, 4);

if ( ! function_exists( 'merc_get_adjusted_service_cost' ) ) {
    function merc_get_adjusted_service_cost( $shipment_id ) {
        $status = strtoupper( trim( (string) get_post_meta( $shipment_id, 'wpcargo_status', true ) ) );

        $base_cost = floatval( get_post_meta( $shipment_id, 'wpcargo_costo_envio', true ) );

        if ( stripos( $status, 'REPROGRAMADO' ) !== false || stripos( $status, 'ANULADO' ) !== false ) {
            $base_cost = 0.0;
        }
        
        // Sumar cargos adicionales si existen
        $cargos = get_post_meta( $shipment_id, 'merc_cargos_adicionales', true );
        $adicional = 0.0;
        if ( is_array( $cargos ) ) {
            foreach ( $cargos as $cargo ) {
                $estado = isset($cargo['estado']) ? strtolower($cargo['estado']) : 'activo';
                if ( $estado !== 'anulado' ) {
                    $adicional += floatval( $cargo['monto'] );
                }
            }
        }
        
        return $base_cost + $adicional;
    }
}

if ( ! function_exists( 'merc_sync_service_cost_by_status' ) ) {
    function merc_sync_service_cost_by_status( $shipment_id ) {
        if ( get_post_type( $shipment_id ) !== 'wpcargo_shipment' ) {
            return;
        }

        $status = strtoupper( trim( (string) get_post_meta( $shipment_id, 'wpcargo_status', true ) ) );
        $special = ( stripos( $status, 'REPROGRAMADO' ) !== false || stripos( $status, 'ANULADO' ) !== false );

        $service_key  = 'wpcargo_costo_envio';
        $backup_key   = '_merc_original_wpcargo_costo_envio';
        $flag_key     = '_merc_service_zeroed_by_status';
        $current_cost  = get_post_meta( $shipment_id, $service_key, true );

        if ( $special ) {
            if ( get_post_meta( $shipment_id, $backup_key, true ) === '' && $current_cost !== '' ) {
                update_post_meta( $shipment_id, $backup_key, $current_cost );
            }

            update_post_meta( $shipment_id, $service_key, '0.00' );
            update_post_meta( $shipment_id, $flag_key, '1' );
            return;
        }

        if ( get_post_meta( $shipment_id, $flag_key, true ) === '1' ) {
            $original_cost = get_post_meta( $shipment_id, $backup_key, true );

            if ( $original_cost !== '' ) {
                update_post_meta( $shipment_id, $service_key, $original_cost );
            }

        }
    }
}

