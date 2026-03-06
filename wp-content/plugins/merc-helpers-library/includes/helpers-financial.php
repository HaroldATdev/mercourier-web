<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Format amount for display (2 decimals)
 * 
 * @param float $amount Amount to format
 * @return string Formatted amount
 */
function merc_format_amount( $amount ) {
    return number_format( floatval( $amount ), 2, '.', ',' );
}

/**
 * Normalize amount text to float
 * 
 * @param string $amount Amount text
 * @return float Normalized amount
 */
function merc_normalize_amount( $amount ) {
    $normalized = str_replace( [ ',', ' ' ], '', strval( $amount ) );
    return floatval( $normalized );
}

/**
 * Get shipment revenue (opposite of cost)
 * 
 * @param int $shipment_id Shipment ID
 * @return float Revenue amount
 */
function merc_get_shipment_revenue( $shipment_id ) {
    $cost = merc_get_shipment_cost( $shipment_id );
    return $cost; // In this system, cost = revenue for shipper
}

/**
 * Get user total debt
 * 
 * @param int $user_id User ID
 * @return float Total debt
 */
function merc_get_user_total_debt( $user_id ) {
    $history = get_user_meta( $user_id, 'merc_liquidations', true );
    if ( ! is_array( $history ) ) {
        return 0.00;
    }

    $debt = 0.00;

    foreach ( $history as $record ) {
        if ( isset( $record['status'] ) && $record['status'] === 'unpaid' ) {
            $debt += floatval( $record['amount'] ?: 0 );
        }
    }

    return round( $debt, 2 );
}

/**
 * Get user total liquidations
 * 
 * @param int $user_id User ID
 * @return float Total liquidations
 */
function merc_get_user_total_liquidations( $user_id ) {
    $history = get_user_meta( $user_id, 'merc_liquidations', true );
    if ( ! is_array( $history ) ) {
        return 0.00;
    }

    $total = 0.00;

    foreach ( $history as $record ) {
        $total += floatval( $record['amount'] ?: 0 );
    }

    return round( $total, 2 );
}

/**
 * Get user today's revenue
 * 
 * @param int $user_id User ID (shipper)
 * @return float Today's revenue
 */
function merc_get_user_today_revenue( $user_id ) {
    global $wpdb;

    $today = merc_get_today();

    $query = $wpdb->prepare(
        "
        SELECT SUM(CAST(pm.meta_value AS DECIMAL(10,2))) as total
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm_shipper ON p.ID = pm_shipper.post_id AND pm_shipper.meta_key = 'registered_shipper'
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'wpcargo_costo_envio'
        WHERE p.post_type = 'wpcargo_shipment'
        AND p.post_status = 'publish'
        AND pm_shipper.meta_value = %d
        AND DATE(p.post_date) = %s
        ",
        $user_id,
        $today
    );

    $result = $wpdb->get_row( $query );
    $total  = $result && isset( $result->total ) ? floatval( $result->total ) : 0.00;

    return round( $total, 2 );
}

/**
 * Check if shipment is paid
 * 
 * @param int $shipment_id Shipment ID
 * @return bool
 */
function merc_is_shipment_paid( $shipment_id ) {
    $paid = get_post_meta( $shipment_id, 'wpcargo_servicio_cobrado', true );
    return strtolower( $paid ) === 'si' || $paid === '1';
}

/**
 * Mark shipment as paid
 * 
 * @param int $shipment_id Shipment ID
 * @param string $liquidation_id Liquidation ID
 * @return bool
 */
function merc_mark_shipment_as_paid( $shipment_id, $liquidation_id = '' ) {
    update_post_meta( $shipment_id, 'wpcargo_servicio_cobrado', 'si' );
    update_post_meta( $shipment_id, 'wpcargo_fecha_liquidacion_remitente', current_time( 'mysql' ) );

    if ( ! empty( $liquidation_id ) ) {
        update_post_meta( $shipment_id, 'wpcargo_included_in_liquidation', $liquidation_id );
    }

    return true;
}

/**
 * Convert currency string to float
 * Example: "S/. 100.50" -> 100.50
 * 
 * @param string $currency Currency string
 * @return float Amount
 */
function merc_parse_currency( $currency ) {
    $normalized = preg_replace( '/[^\d.,]/', '', strval( $currency ) );
    $normalized = str_replace( ',', '.', $normalized );
    return floatval( $normalized );
}

/**
 * Format amount as currency
 * 
 * @param float $amount Amount
 * @param string $symbol Currency symbol (default: 'S/.')
 * @return string Formatted currency
 */
function merc_format_currency( $amount, $symbol = 'S/.' ) {
    $formatted = number_format( floatval( $amount ), 2, '.', ',' );
    return sprintf( '%s %s', $symbol, $formatted );
}
