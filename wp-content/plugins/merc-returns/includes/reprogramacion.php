<?php
/**
 * Módulo de Reprogramación de Envíos
 * 
 * Maneja:
 * 1. Marcar un envío como REPROGRAMADO (es_reprogramado = 1)
 * 2. Verificar automáticamente si llegó la fecha y cambiar a RECEPCIONADO
 * 3. Pintar las filas de NARANJA cuando están reprogramadas
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Hook: cuando el estado cambia a REPROGRAMADO
 * Activa la bandera es_reprogramado = 1
 */
add_action('update_post_meta_wpcargo_status', function($meta_id, $object_id, $meta_key, $meta_value) {
    if ($meta_value === 'REPROGRAMADO') {
        error_log("🟠 REPROG: Envío $object_id cambió a REPROGRAMADO - Activando bandera es_reprogramado");
        update_post_meta($object_id, 'es_reprogramado', 1);
    }
}, 10, 4);

/**
 * Hook: cuando el estado cambia DESDE REPROGRAMADO
 * Si cambia a otro estado diferente de REPROGRAMADO, desactiva la bandera
 */
add_action('update_post_meta_wpcargo_status', function($meta_id, $object_id, $meta_key, $meta_value) {
    if ($meta_value !== 'REPROGRAMADO') {
        $es_reprogramado = get_post_meta($object_id, 'es_reprogramado', true);
        if ($es_reprogramado == 1) {
            error_log("🟠 REPROG: Envío $object_id cambió a $meta_value - Desactivando bandera es_reprogramado");
            update_post_meta($object_id, 'es_reprogramado', 0);
        }
    }
}, 10, 4);

/**
 * CRON: Verificar envíos REPROGRAMADOS y cambiar a RECEPCIONADO si llegó la fecha
 * 
 * Se ejecuta cada hora (configurar en wp-config.php si es necesario)
 */
add_action('merc_reprogramacion_check_cron', 'merc_reprogramacion_check_daily');

function merc_reprogramacion_check_daily() {
    error_log("🟠 REPROG: [CRON] Iniciando verificación de envíos reprogramados...");
    
    // Obtener envíos que están marcados como reprogramados
    $args = [
        'post_type'      => 'wpcargo_shipment',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'meta_query'     => [
            [
                'key'   => 'es_reprogramado',
                'value' => 1,
                'type'  => 'NUMERIC',
            ],
            [
                'key'     => 'wpcargo_status',
                'value'   => 'REPROGRAMADO',
                'compare' => '=',
            ],
        ],
    ];
    
    $envios = get_posts($args);
    error_log("🟠 REPROG: Encontrados " . count($envios) . " envíos reprogramados");
    
    $hoy = date('Y-m-d'); // Formato: 2026-04-22
    $cambios = 0;
    
    foreach ($envios as $shipment) {
        // Obtener la fecha de reprogramación (nueva fecha de envío)
        $fecha_reprogramada = get_post_meta($shipment->ID, 'wpcargo_pickup_date_picker', true);
        
        if (!$fecha_reprogramada) {
            error_log("🔴 REPROG: Envío #{$shipment->ID} no tiene fecha de reprogramación");
            continue;
        }
        
        // Normalizar fecha a formato Y-m-d
        $fecha_normalized = merc_reprog_normalize_date($fecha_reprogramada);
        
        error_log("🟡 REPROG: Envío #{$shipment->ID} - Hoy: $hoy | Reprogramado para: $fecha_normalized");
        
        // Si la fecha llegó o pasó, cambiar a RECEPCIONADO
        if (strtotime($fecha_normalized) <= strtotime($hoy)) {
            error_log("🟢 REPROG: ¡Fecha llegó! Envío #{$shipment->ID} cambiando a RECEPCIONADO");
            
            // Cambiar estado
            update_post_meta($shipment->ID, 'wpcargo_status', 'RECEPCIONADO');
            
            // Registrar en historial
            $historial = get_post_meta($shipment->ID, 'wpcargo_shipments_update', true);
            if (!is_array($historial)) {
                $historial = [];
            }
            
            $historial[] = [
                'date'         => current_time('Y-m-d'),
                'time'         => current_time('H:i:s'),
                'status'       => 'RECEPCIONADO',
                'updated-by'   => 'Sistema - Reprogramación Automática',
                'remarks'      => 'Estado cambiado automáticamente por llegada de fecha reprogramada',
            ];
            
            update_post_meta($shipment->ID, 'wpcargo_shipments_update', $historial);
            
            // Desactivar bandera (ya no es reprogramado)
            update_post_meta($shipment->ID, 'es_reprogramado', 0);
            
            $cambios++;
        }
    }
    
    error_log("🟠 REPROG: [CRON] Completado - $cambios envíos cambiados a RECEPCIONADO");
}

/**
 * Normalizar fecha a formato Y-m-d
 * Maneja formatos: 2026-04-22, 22/04/2026, 22\04\2026
 */
function merc_reprog_normalize_date($fecha) {
    if (empty($fecha)) {
        return null;
    }
    
    // Si ya está en formato Y-m-d, devolver
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        return $fecha;
    }
    
    // Intentar parsear formato DD/MM/YYYY o DD\MM\YYYY
    if (preg_match('/^(\d{2})[\/\\\\](\d{2})[\/\\\\](\d{4})$/', $fecha, $matches)) {
        return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
    }
    
    // Si no se puede parsear, retornar null
    error_log("⚠️ REPROG: No se pudo parsear fecha: $fecha");
    return null;
}

/**
 * PROGRAMAR EL CRON (opcional, si no está programado)
 */
add_action('wp_loaded', function() {
    if (!wp_next_scheduled('merc_reprogramacion_check_cron')) {
        wp_schedule_event(time(), 'hourly', 'merc_reprogramacion_check_cron');
        error_log("🟠 REPROG: Cron de reprogramación programado cada hora");
    }
});

/**
 * Limpiar cron al desactivar plugin
 */
register_deactivation_hook(MERC_RETURNS_FILE, function() {
    wp_clear_scheduled_hook('merc_reprogramacion_check_cron');
});
