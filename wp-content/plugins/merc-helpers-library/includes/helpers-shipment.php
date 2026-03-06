<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Count shipments of a specific type created today by a client
 * 
 * @param int    $client_id Client/shipper ID
 * @param string $tipo      Shipment type
 * @return int Shipment count
 */
function merc_count_envios_del_tipo_hoy( $client_id, $tipo ) {
    global $wpdb;

    $hoy                = merc_get_today();
    $tipo_normalized    = sanitize_text_field( $tipo );

    // Normalize tipo
    if ( stripos( $tipo, 'agencia' ) !== false || $tipo_normalized === 'express' ) {
        $tipo_search = 'express';
    } elseif ( stripos( $tipo, 'emprendedor' ) !== false || $tipo_normalized === 'normal' ) {
        $tipo_search = 'normal';
    } elseif ( stripos( $tipo, 'full' ) !== false ) {
        $tipo_search = 'full_fitment';
    } else {
        $tipo_search = $tipo_normalized;
    }

    $query = $wpdb->prepare(
        "
        SELECT COUNT(*) as total
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm_shipper ON p.ID = pm_shipper.post_id AND pm_shipper.meta_key = 'registered_shipper'
        LEFT JOIN {$wpdb->postmeta} pm_type ON p.ID = pm_type.post_id AND pm_type.meta_key = 'wpcargo_type_of_shipment'
        LEFT JOIN {$wpdb->postmeta} pm_container ON p.ID = pm_container.post_id AND pm_container.meta_key = 'wpcargo_container'
        WHERE p.post_type = 'wpcargo_shipment'
        AND p.post_status = 'publish'
        AND pm_shipper.meta_value = %d
        AND DATE(p.post_date) = %s
        ",
        $client_id,
        $hoy
    );

    $result = $wpdb->get_row( $query );
    return $result && isset( $result->total ) ? intval( $result->total ) : 0;
}

/**
 * Get active driver for a shipment
 * 
 * @param int $shipment_id Shipment ID
 * @return int|false Driver ID or false
 */
function merc_get_motorizado_activo( $shipment_id ) {
    // States indicating delivery phase
    $estados_entrega = [
        'EN BASE MERCOURIER',
        'RECEPCIONADO',
        'LISTO PARA SALIR',
        'EN RUTA',
        'ENTREGADO',
        'NO RECIBIDO',
        'REPROGRAMADO'
    ];

    // Get current status
    $estado_actual = get_post_meta( $shipment_id, 'wpcargo_status', true );

    // Check if we're in delivery phase
    $es_fase_entrega = false;
    if ( ! empty( $estado_actual ) ) {
        foreach ( $estados_entrega as $estado ) {
            if ( stripos( $estado_actual, $estado ) !== false ) {
                $es_fase_entrega = true;
                break;
            }
        }
    }

    // If in delivery phase, try to get delivery driver
    if ( $es_fase_entrega ) {
        $motorizado_entrega = get_post_meta( $shipment_id, 'wpcargo_motorizo_entrega', true );
        if ( ! empty( $motorizado_entrega ) ) {
            return intval( $motorizado_entrega );
        }
        // Fallback to pickup driver
        $motorizado_recojo = get_post_meta( $shipment_id, 'wpcargo_motorizo_recojo', true );
        if ( ! empty( $motorizado_recojo ) ) {
            return intval( $motorizado_recojo );
        }
    } else {
        // If in pickup phase, use pickup driver
        $motorizado_recojo = get_post_meta( $shipment_id, 'wpcargo_motorizo_recojo', true );
        if ( ! empty( $motorizado_recojo ) ) {
            return intval( $motorizado_recojo );
        }
    }

    return false;
}

/**
 * Get shipment status
 * 
 * @param int $shipment_id Shipment ID
 * @return string Shipment status
 */
function merc_get_shipment_status( $shipment_id ) {
    return get_post_meta( $shipment_id, 'wpcargo_status', true ) ?: 'PENDIENTE';
}

/**
 * Get shipment cost
 * 
 * @param int $shipment_id Shipment ID
 * @return float Shipment cost
 */
function merc_get_shipment_cost( $shipment_id ) {
    $cost = get_post_meta( $shipment_id, 'wpcargo_costo_envio', true );
    return floatval( $cost ) ?: 0.00;
}

/**
 * Get shipment pickup date
 * 
 * @param int $shipment_id Shipment ID
 * @return string Pickup date in d/m/Y format
 */
function merc_get_shipment_pickup_date( $shipment_id ) {
    $date = get_post_meta( $shipment_id, 'wpcargo_pickup_date_picker', true );
    if ( empty( $date ) ) {
        $date = get_post_meta( $shipment_id, 'wpcargo_pickup_date', true );
    }
    if ( empty( $date ) ) {
        $date = get_post_meta( $shipment_id, 'calendarenvio', true );
    }
    if ( empty( $date ) ) {
        $date = get_post_meta( $shipment_id, 'wpcargo_fecha_envio', true );
    }
    return $date ?: '';
}

/**
 * Check if shipment pickup date is today
 * 
 * @param int $shipment_id Shipment ID
 * @return bool
 */
function merc_pickup_date_is_today( $shipment_id ) {
    $pickup_date = merc_get_shipment_pickup_date( $shipment_id );
    return merc_is_today( $pickup_date );
}

/**
 * Get shipment tracking number
 * 
 * @param int $shipment_id Shipment ID
 * @return string Tracking number
 */
function merc_get_shipment_tracking( $shipment_id ) {
    $post = get_post( $shipment_id );
    return $post ? $post->post_title : '';
}

/**
 * Normalize shipment type
 * 
 * @param string $tipo Shipment type
 * @return string Normalized type
 */
function merc_normalize_tipo_envio( $tipo ) {
    $tipo_lower = strtolower( trim( $tipo ) );

    if ( stripos( $tipo_lower, 'agencia' ) !== false || $tipo_lower === 'express' ) {
        return 'express';
    } elseif ( stripos( $tipo_lower, 'emprendedor' ) !== false || $tipo_lower === 'normal' ) {
        return 'normal';
    } elseif ( stripos( $tipo_lower, 'full' ) !== false ) {
        return 'full_fitment';
    }

    return $tipo_lower;
}
