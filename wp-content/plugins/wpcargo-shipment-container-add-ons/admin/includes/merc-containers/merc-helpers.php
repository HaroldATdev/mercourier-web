<?php
/**
 * Funciones auxiliares para gestión de contenedores
 * Parte del plugin WPCargo Shipment Container Add-ons
 * Módulo Mercourier - Auto-asignación y sincronización
 * 
 * @package wpcargo-shipment-container-add-ons
 * @subpackage merc-containers
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Normalizar texto para comparación (remover acentos, espacios extra, etc)
 */
function merc_normalizar_texto($texto) {
    // Convertir a minúsculas
    $texto = mb_strtolower($texto, 'UTF-8');
    
    // Remover acentos
    $replacements = array(
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        'â' => 'a', 'ê' => 'e', 'î' => 'i', 'ô' => 'o', 'û' => 'u',
        'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
        'ã' => 'a', 'õ' => 'o', 'ñ' => 'n',
    );
    
    foreach ($replacements as $from => $to) {
        $texto = str_replace($from, $to, $texto);
    }
    
    // Remover espacios múltiples
    $texto = preg_replace('/\s+/', ' ', $texto);
    
    // Trim
    return trim($texto);
}

/**
 * Obtener envíos asignados al contenedor de RECOJO
 */
function merc_get_shipments_by_container_recojo($container_id) {
    global $wpdb;
    
    $sql = "SELECT tbl1.ID FROM {$wpdb->prefix}posts AS tbl1 ";
    $sql .= "RIGHT JOIN {$wpdb->prefix}postmeta as tbl2 ON tbl1.ID = tbl2.post_id ";
    $sql .= "LEFT JOIN {$wpdb->prefix}postmeta as tbl3 ON tbl1.ID = tbl3.post_id AND tbl3.meta_key = 'wpcargo_status' ";
    $sql .= "LEFT JOIN {$wpdb->prefix}postmeta as tbl4 ON tbl1.ID = tbl4.post_id AND tbl4.meta_key = 'tipo_envio' ";
    $sql .= "WHERE tbl1.post_status = 'publish' AND tbl1.post_type = 'wpcargo_shipment' ";
    $sql .= "AND tbl2.meta_key = 'shipment_container_recojo' ";
    $sql .= "AND tbl2.meta_value = %s ";
    $sql .= "AND (tbl4.meta_value != 'normal' OR tbl3.meta_value IN ('PENDIENTE', 'RECOGIDO', 'NO RECOGIDO')) ";
    $sql .= "ORDER BY tbl1.ID ASC";
    
    return $wpdb->get_col($wpdb->prepare($sql, $container_id));
}

/**
 * Obtener envíos asignados al contenedor de ENTREGA
 */
function merc_get_shipments_by_container_entrega($container_id) {
    global $wpdb;
    
    $sql = "SELECT tbl1.ID FROM {$wpdb->prefix}posts AS tbl1 ";
    $sql .= "RIGHT JOIN {$wpdb->prefix}postmeta as tbl2 ON tbl1.ID = tbl2.post_id ";
    $sql .= "WHERE tbl1.post_status = 'publish' AND tbl1.post_type = 'wpcargo_shipment' ";
    $sql .= "AND tbl2.meta_key = 'shipment_container_entrega' ";
    $sql .= "AND tbl2.meta_value = %s ";
    $sql .= "ORDER BY tbl1.ID ASC";
    
    return $wpdb->get_col($wpdb->prepare($sql, $container_id));
}

/**
 * Obtener TODOS los envíos asignados a un contenedor
 */
function merc_get_all_container_shipments($container_id) {
    $shipments_recojo = merc_get_shipments_by_container_recojo($container_id);
    $shipments_entrega = merc_get_shipments_by_container_entrega($container_id);
    
    return array_unique(array_merge($shipments_recojo, $shipments_entrega));
}

/**
 * Obtener motorizado activo asignado a cliente
 */
function merc_get_motorizado_activo($shipment_id) {
    $user_id = get_post_meta($shipment_id, 'registered_shipper', true);
    if (empty($user_id)) return null;
    
    $motorizado = get_user_meta($user_id, 'merc_motorizo_recojo_default', true);
    if (empty($motorizado)) return null;
    
    return get_userdata($motorizado);
}
