<?php
/**
 * Auto-Asignación de Contenedores (Mercourier)
 * Asigna automáticamente envíos a contenedores según distrito
 *
 * @package wpcargo-shipment-container-add-ons
 * @subpackage merc-containers
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Hook: Auto-asignar envío a contenedor según distrito
 * Se ejecuta al guardar un envío
 * Integral: Soporta MERC EMPRENDEDOR (asigna RECOJO + ENTREGA)
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
    $distrito_destino = ''; // Para MERC EMPRENDEDOR
    $meta_key_contenedor = '';
    
    $tipo_lower = strtolower($tipo_envio);
    if ($tipo_lower === 'express' || $tipo_lower === 'full_fitment' || strpos($tipo_lower, 'full') !== false) {
        // MERC AGENCIA/FULL FITMENT: usar distrito de DESTINO → contenedor de ENTREGA
        $distrito = get_post_meta($post_id, 'wpcargo_distrito_destino', true);
        if (empty($distrito) && isset($_POST['wpcargo_distrito_destino'])) {
            $distrito = sanitize_text_field($_POST['wpcargo_distrito_destino']);
        }
        $meta_key_contenedor = 'shipment_container_entrega';
        error_log("🎯 AUTO-ASIGNACIÓN - AGENCIA/FULL detectado, distrito destino: " . ($distrito ?: 'NO DEFINIDO') . " → contenedor de ENTREGA");
    } elseif (strtolower($tipo_envio) === 'normal') {
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
        error_log("❌ AUTO-ASIGNACIÓN - No se pudo determinar el distrito o tipo de contenedor (tipo_envio={$tipo_envio}), abortando");
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
    $container_encontrado = null;
    
    foreach ($containers as $container) {
        $container_title = $container->post_title;
        
        // Limpiar y normalizar las cadenas para comparación (sin tildes)
        $distrito_limpio = merc_normalizar_texto($distrito);
        $container_limpio = merc_normalizar_texto($container_title);
        
        // 1. Búsqueda EXACTA (prima prioridad)
        if (strtolower(trim($distrito)) === strtolower(trim($container_title))) {
            error_log("   ✅ Coincidencia EXACTA: {$distrito} = {$container_title}");
            $container_encontrado = $container->ID;
            break;
        }
        
        // 2. Búsqueda EXACTA normalizada (sin tildes)
        if ($distrito_limpio === $container_limpio) {
            error_log("   ✅ Coincidencia EXACTA normalizada: {$distrito_limpio} = {$container_limpio}");
            $container_encontrado = $container->ID;
            break;
        }
        
        // 3. Búsqueda por SUBCADENA exacta
        if (strpos($container_limpio, $distrito_limpio) !== false) {
            error_log("   ✅ Coincidencia por subcadena: '{$distrito_limpio}' encontrado en '{$container_limpio}'");
            $container_encontrado = $container->ID;
            break;
        }
    }
    
    // Si no encontró coincidencia exacta, intentar búsqueda MÁS ESTRICTA con palabras completas
    if (empty($container_encontrado)) {
        error_log("   ℹ️ Sin coincidencia exacta, intentando búsqueda de palabras-clave...");
        
        foreach ($containers as $container) {
            $container_title = $container->post_title;
            $distrito_limpio = merc_normalizar_texto($distrito);
            $container_limpio = merc_normalizar_texto($container_title);
            
            // BÚSQUEDA MÁS ESTRICTA: Todas las palabras significativas del distrito deben estar en el contenedor
            $palabras_distrito = array_filter(explode(' ', $distrito_limpio), function($p) {
                return strlen($p) > 2; // Solo palabras con más de 2 caracteres
            });
            
            $coincidencias = 0;
            foreach ($palabras_distrito as $palabra) {
                if (strpos($container_limpio, $palabra) !== false) {
                    $coincidencias++;
                }
            }
            
            // TODAS las palabras deben coincidir
            if ($coincidencias === count($palabras_distrito) && count($palabras_distrito) > 0) {
                error_log("   ✅ Coincidencia por palabras-clave");
                $container_encontrado = $container->ID;
                break;
            }
        }
    }
    
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
        error_log("📍 Auto-asignación EMPRENDEDOR - Validando contentedores para RECOJO y ENTREGA...");
        merc_assign_emprendedor_containers($post_id, $distrito, $distrito_destino, $containers);
    }
}

/**
 * Asignar ambos contenedores para MERC EMPRENDEDOR
 */
function merc_assign_emprendedor_containers($post_id, $distrito, $distrito_destino, $containers) {
    // Asignar RECOJO
    $container_recojo_actual = get_post_meta($post_id, 'shipment_container_recojo', true);
    if (empty($container_recojo_actual)) {
        $container_recojo = merc_find_container_by_district($distrito, $containers);
        if ($container_recojo) {
            update_post_meta($post_id, 'shipment_container_recojo', $container_recojo);
            error_log("✅ Asignado contenedor RECOJO #{$container_recojo}");
        }
    }
    
    // Asignar ENTREGA
    $container_entrega_actual = get_post_meta($post_id, 'shipment_container_entrega', true);
    if (empty($container_entrega_actual)) {
        $container_entrega = merc_find_container_by_district($distrito_destino, $containers);
        if ($container_entrega) {
            update_post_meta($post_id, 'shipment_container_entrega', $container_entrega);
            error_log("✅ Asignado contenedor ENTREGA #{$container_entrega}");
        }
    }
}

/**
 * Buscar contenedor por distrito (helper)
 */
function merc_find_container_by_district($distrito, $containers) {
    if (empty($distrito)) {
        return null;
    }
    
    foreach ($containers as $container) {
        $container_title = $container->post_title;
        $distrito_limpio = merc_normalizar_texto($distrito);
        $container_limpio = merc_normalizar_texto($container_title);
        
        // Búsqueda exacta
        if (strtolower(trim($distrito)) === strtolower(trim($container_title))) {
            return $container->ID;
        }
        
        // Búsqueda normalizada
        if ($distrito_limpio === $container_limpio) {
            return $container->ID;
        }
        
        // Búsqueda por subcadena
        if (strpos($container_limpio, $distrito_limpio) !== false) {
            return $container->ID;
        }
    }
    
    // Búsqueda avanzada por palabras clave
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
            return $container->ID;
        }
    }
    
    return null;
}
