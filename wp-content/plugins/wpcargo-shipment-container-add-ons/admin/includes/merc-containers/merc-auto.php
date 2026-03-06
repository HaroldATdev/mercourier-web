<?php
/**
 * Auto-asignación de contenedores según tipo de envío y distrito
 * 
 * @package merc-shipment-container-management
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Hook: Auto-asignar contenedor cuando se crea/actualiza un envío
 * Basado en el tipo de envío y el distrito (origen para EMPRENDEDOR, destino para AGENCIA)
 */
add_action('wpcargo_after_save_shipment', 'merc_auto_assign_shipment_to_container', 100, 1);
add_action('save_post_wpcargo_shipment', 'merc_auto_assign_shipment_to_container', 100, 1);
function merc_auto_assign_shipment_to_container($post_id) {
    error_log("🔍 AUTO-ASIGNACIÓN INICIADA - Envío #{$post_id}");
    
    // Verificar que sea un envío
    if (get_post_type($post_id) !== 'wpcargo_shipment') {
        error_log("❌ AUTO-ASIGNACIÓN - No es un envío wpcargo_shipment");
        return;
    }
    
    // Verificar que no sea un autoguardado
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        error_log("❌ AUTO-ASIGNACIÓN - Es un autoguardado, omitiendo");
        return;
    }
    
    // Obtener tipo de envío
    $tipo_envio = get_post_meta($post_id, 'tipo_envio', true);
    if (empty($tipo_envio) && isset($_POST['tipo_envio'])) {
        $tipo_envio = sanitize_text_field($_POST['tipo_envio']);
    }
    
    error_log("📦 AUTO-ASIGNACIÓN - Tipo de envío: " . ($tipo_envio ?: 'NO DEFINIDO'));
    
    // Determinar qué tipo de contenedor y distrito usar según el tipo de envío
    $distrito = '';
    $distrito_destino = '';
    $meta_key_contenedor = '';
    
    $tipo_lower = strtolower($tipo_envio);
    if ($tipo_lower === 'express' || stripos($tipo_lower, 'agencia') !== false || $tipo_lower === 'full_fitment' || strpos($tipo_lower, 'full') !== false) {
        // MERC AGENCIA/FULL FITMENT: usar distrito de DESTINO → contenedor de ENTREGA
        $distrito = get_post_meta($post_id, 'wpcargo_distrito_destino', true);
        if (empty($distrito) && isset($_POST['wpcargo_distrito_destino'])) {
            $distrito = sanitize_text_field($_POST['wpcargo_distrito_destino']);
        }
        $meta_key_contenedor = 'shipment_container_entrega';
        error_log("🎯 AUTO-ASIGNACIÓN - AGENCIA/FULL detectado, distrito destino: " . ($distrito ?: 'NO DEFINIDO') . " → contenedor de ENTREGA");
    } elseif (strtolower($tipo_envio) === 'normal' || stripos($tipo_envio, 'emprendedor') !== false) {
        // MERC EMPRENDEDOR: usar distrito de RECOJO → contenedor de RECOJO
        $distrito = get_post_meta($post_id, 'wpcargo_distrito_recojo', true);
        if (empty($distrito) && isset($_POST['wpcargo_distrito_recojo'])) {
            $distrito = sanitize_text_field($_POST['wpcargo_distrito_recojo']);
        }
        $meta_key_contenedor = 'shipment_container_recojo';
        error_log("🎯 AUTO-ASIGNACIÓN - EMPRENDEDOR detectado, distrito recojo: " . ($distrito ?: 'NO DEFINIDO') . " → contenedor de RECOJO");
        
        // También necesitaremos el distrito de destino para asignar el contenedor de entrega
        $distrito_destino = get_post_meta($post_id, 'wpcargo_distrito_destino', true);
        if (empty($distrito_destino) && isset($_POST['wpcargo_distrito_destino'])) {
            $distrito_destino = sanitize_text_field($_POST['wpcargo_distrito_destino']);
        }
    }
    
    if (empty($distrito) || empty($meta_key_contenedor)) {
        error_log("❌ AUTO-ASIGNACIÓN - No se pudo determinar el distrito o tipo de contenedor, abortando");
        return;
    }
    
    // Verificar si ya tiene un contenedor asignado de este tipo
    $container_actual = get_post_meta($post_id, $meta_key_contenedor, true);
    if (!empty($container_actual)) {
        error_log("⏭️ AUTO-ASIGNACIÓN - Envío ya tiene {$meta_key_contenedor} #{$container_actual}, omitiendo");
        return;
    }
    
    // Buscar el contenedor que coincida con el distrito
    $args = array(
        'post_type'      => 'shipment_container',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC'
    );
    
    $containers = get_posts($args);
    $container_encontrado = _merc_find_container($containers, $distrito);
    
    // Si se encontró un contenedor, asignar el envío
    if ($container_encontrado) {
        update_post_meta($post_id, $meta_key_contenedor, $container_encontrado);
        error_log("✅ Auto-asignación - Envío #{$post_id}: {$meta_key_contenedor} #{$container_encontrado} asignado");
        do_action('merc_after_auto_assign_container', $post_id, $container_encontrado, $distrito, $meta_key_contenedor);
    } else {
        error_log("❌ Auto-asignación - NO se encontró contenedor compatible para distrito: {$distrito}");
    }
    
    // Si es MERC EMPRENDEDOR, SIEMPRE intentar asignar AMBOS contenedores
    if (strtolower($tipo_envio) === 'normal' && !empty($distrito_destino)) {
        error_log("📍 Auto-asignación EMPRENDEDOR - Validando contenedores para RECOJO y ENTREGA...");
        
        // Asegurarse de que tiene contenedor de ENTREGA asignado
        $container_entrega_actual = get_post_meta($post_id, 'shipment_container_entrega', true);
        if (empty($container_entrega_actual)) {
            error_log("📍 Auto-asignación EMPRENDEDOR - Asignando contenedor de ENTREGA para: {$distrito_destino}");
            $container_entrega_encontrado = _merc_find_container($containers, $distrito_destino);
            
            if ($container_entrega_encontrado) {
                update_post_meta($post_id, 'shipment_container_entrega', $container_entrega_encontrado);
                error_log("✅ Auto-asignación EMPRENDEDOR - Envío #{$post_id}: shipment_container_entrega #{$container_entrega_encontrado} asignado");
            } else {
                error_log("⚠️ Auto-asignación EMPRENDEDOR - No se encontró contenedor de entrega para: {$distrito_destino}");
            }
        }
    }
}

/**
 * Función auxiliar: buscar contenedor que coincida con district
 * Usa búsqueda exacta, normalizada y luego por palabras clave
 */
function _merc_find_container($containers, $distrito) {
    $container_encontrado = null;
    
    foreach ($containers as $container) {
        $container_title = $container->post_title;
        $distrito_limpio = merc_normalizar_texto($distrito);
        $container_limpio = merc_normalizar_texto($container_title);
        
        // 1. Búsqueda EXACTA
        if (strtolower(trim($distrito)) === strtolower(trim($container_title))) {
            error_log("   ✅ Coincidencia EXACTA: {$distrito} = {$container_title}");
            $container_encontrado = $container->ID;
            break;
        }
        
        // 2. Búsqueda EXACTA normalizada
        if ($distrito_limpio === $container_limpio) {
            error_log("   ✅ Coincidencia EXACTA normalizada: {$distrito_limpio} = {$container_limpio}");
            $container_encontrado = $container->ID;
            break;
        }
        
        // 3. Búsqueda por SUBCADENA
        if (strpos($container_limpio, $distrito_limpio) !== false) {
            error_log("   ✅ Coincidencia por subcadena: '{$distrito_limpio}' en '{$container_limpio}'");
            $container_encontrado = $container->ID;
            break;
        }
    }
    
    // Si no encontró coincidencia exacta, intentar búsqueda por palabras
    if (empty($container_encontrado)) {
        error_log("   ℹ️ Sin coincidencia exacta, intentando búsqueda de palabras-clave...");
        
        foreach ($containers as $container) {
            $container_title = $container->post_title;
            $distrito_limpio = merc_normalizar_texto($distrito);
            $container_limpio = merc_normalizar_texto($container_title);
            
            $palabras_distrito = array_filter(explode(' ', $distrito_limpio), function($p) {
                return strlen($p) > 2;
            });
            
            $coincidencias = 0;
            foreach ($palabras_distrito as $palabra) {
                if (strpos($container_limpio, $palabra) !== false) {
                    $coincidencias++;
                }
            }
            
            if ($coincidencias === count($palabras_distrito) && count($palabras_distrito) > 0) {
                error_log("   ✅ Coincidencia por palabras-clave: {$coincidencias}/{count($palabras_distrito)} coinciden");
                $container_encontrado = $container->ID;
                break;
            }
        }
    }
    
    return $container_encontrado;
}
