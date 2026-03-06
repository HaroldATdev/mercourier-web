<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get today's date in Y-m-d format
 * 
 * @return string Today's date
 */
function merc_get_today() {
    return current_time( 'Y-m-d' );
}

/**
 * Get tomorrow in d/m/Y format (skip Sundays)
 * 
 * @return string Tomorrow's date
 */
function merc_get_tomorrow_formatted() {
    $tomorrow_ts = strtotime( '+1 day', current_time( 'timestamp' ) );
    // If tomorrow is Sunday (w = 0) skip to Monday
    $weekday = date( 'w', $tomorrow_ts );
    if ( $weekday === '0' || $weekday === 0 ) {
        $tomorrow_ts = strtotime( '+2 days', current_time( 'timestamp' ) );
    }
    return date( 'd/m/Y', $tomorrow_ts );
}

/**
 * Get time limits by shipment type
 * 
 * @param string $tipo Shipment type
 * @return array Time limits array
 */
function merc_get_time_limits( $tipo ) {
    $tipo_lower = strtolower( trim( $tipo ) );

    if ( $tipo_lower === 'express' || stripos( $tipo, 'agencia' ) !== false ) {
        return [
            'sin_envios' => '12:30',     // 12:30 PM
            'con_envios' => '13:00',     // 1:00 PM (13:00)
            'nombre'     => 'EXPRESS'
        ];
    } elseif ( $tipo_lower === 'normal' || stripos( $tipo, 'emprendedor' ) !== false ) {
        return [
            'sin_envios' => '10:00',     // 10:00 AM
            'con_envios' => '10:00',     // 10:00 AM
            'nombre'     => 'NORMAL'
        ];
    } elseif ( $tipo_lower === 'full_fitment' || stripos( $tipo, 'full' ) !== false ) {
        return [
            'sin_envios' => '11:30',     // 11:30 AM
            'con_envios' => '12:15',     // 12:15 PM
            'nombre'     => 'FULL FITMENT'
        ];
    }

    return [ 'sin_envios' => '23:59', 'con_envios' => '23:59', 'nombre' => 'UNKNOWN' ];
}

/**
 * Get current time in HH:MM format
 * 
 * @return string Current time
 */
function merc_get_current_time() {
    return current_time( 'H:i' );
}

/**
 * Check if date is today
 * 
 * @param string $date Date in d/m/Y format
 * @return bool
 */
function merc_is_today( $date ) {
    if ( empty( $date ) ) {
        return false;
    }

    // Parse date from d/m/Y format
    $parts = explode( '/', $date );
    if ( count( $parts ) !== 3 ) {
        return false;
    }

    $date_obj = \DateTime::createFromFormat( 'd/m/Y', $date );
    if ( ! $date_obj ) {
        return false;
    }

    $today = new \DateTime();
    return $date_obj->format( 'Y-m-d' ) === $today->format( 'Y-m-d' );
}

/**
 * Convert date from d/m/Y to Y-m-d
 * 
 * @param string $date Date in d/m/Y format
 * @return string|false Date in Y-m-d format or false
 */
function merc_convert_date_to_iso( $date ) {
    if ( empty( $date ) ) {
        return false;
    }

    $date_obj = \DateTime::createFromFormat( 'd/m/Y', $date );
    if ( ! $date_obj ) {
        return false;
    }

    return $date_obj->format( 'Y-m-d' );
}

/**
 * Convert date from Y-m-d to d/m/Y
 * 
 * @param string $date Date in Y-m-d format
 * @return string|false Date in d/m/Y format or false
 */
function merc_convert_date_from_iso( $date ) {
    if ( empty( $date ) ) {
        return false;
    }

    $date_obj = \DateTime::createFromFormat( 'Y-m-d', $date );
    if ( ! $date_obj ) {
        return false;
    }

    return $date_obj->format( 'd/m/Y' );
}
