<?php
/**
 * Funciones Auxiliares para Contenedores (Mercourier)
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
    $texto = trim($texto);
    $texto = strtoupper($texto);
    
    // Reemplazar caracteres con tildes
    $tildes = array(
        'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
        'À' => 'A', 'È' => 'E', 'Ì' => 'I', 'Ò' => 'O', 'Ù' => 'U',
        'Ñ' => 'N',
        'Ü' => 'U'
    );
    
    return strtr($texto, $tildes);
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
 * Obtener TODOS los envíos asignados a un contenedor (recojo + entrega)
 * Mantiene compatibilidad con código existente
 */
function merc_get_all_container_shipments($container_id) {
    $shipments_recojo = merc_get_shipments_by_container_recojo($container_id);
    $shipments_entrega = merc_get_shipments_by_container_entrega($container_id);
    
    // Combinar y remover duplicados (por si acaso)
    return array_unique(array_merge($shipments_recojo, $shipments_entrega));
}

/**
 * Obtener motorizado activo asignado a cliente
 */
function merc_get_motorizado_activo($shipment_id) {
    $user_id = get_post_meta($shipment_id, 'registered_shipper', true);
    if (empty($user_id)) return null;
    
    $motorizado = get_user_meta($user_id, 'merc_motorizo_recojo_default', true);
    return $motorizado ?: null;
}
