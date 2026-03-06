<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get user phone number
 * 
 * @param int $user_id User ID
 * @return string User phone
 */
function merc_get_user_phone( $user_id ) {
    $phone = get_user_meta( $user_id, 'phone', true );
    if ( empty( $phone ) ) {
        $phone = get_user_meta( $user_id, 'billing_phone', true );
    }
    if ( empty( $phone ) ) {
        $phone = get_user_meta( $user_id, 'wpcargo_shipper_phone', true );
    }
    return $phone ?: '';
}

/**
 * Get user full name
 * 
 * @param int $user_id User ID
 * @return string Full name
 */
function merc_get_user_full_name( $user_id ) {
    $first_name = get_user_meta( $user_id, 'first_name', true );
    $last_name  = get_user_meta( $user_id, 'last_name', true );
    $full_name  = trim( $first_name . ' ' . $last_name );

    if ( empty( $full_name ) ) {
        $user = get_user_by( 'id', $user_id );
        $full_name = $user ? $user->display_name : '';
    }

    return $full_name;
}

/**
 * Get user address
 * 
 * @param int $user_id User ID
 * @return string User address
 */
function merc_get_user_address( $user_id ) {
    $address = get_user_meta( $user_id, 'billing_address_1', true );
    return $address ?: '';
}

/**
 * Get user district
 * 
 * @param int $user_id User ID
 * @return string User district
 */
function merc_get_user_district( $user_id ) {
    $district = get_user_meta( $user_id, 'distrito', true );
    return $district ?: '';
}

/**
 * Get user company/store name
 * 
 * @param int $user_id User ID
 * @return string Company name
 */
function merc_get_user_company( $user_id ) {
    $company = get_user_meta( $user_id, 'billing_company', true );
    return $company ?: '';
}

/**
 * Get user email
 * 
 * @param int $user_id User ID
 * @return string User email
 */
function merc_get_user_email( $user_id ) {
    $user = get_user_by( 'id', $user_id );
    return $user ? $user->user_email : '';
}

/**
 * Check if user has role
 * 
 * @param int    $user_id User ID
 * @param string $role    Role name
 * @return bool
 */
function merc_user_has_role( $user_id, $role ) {
    $user = get_user_by( 'id', $user_id );
    return $user && in_array( $role, (array) $user->roles );
}

/**
 * Check if user is client
 * 
 * @param int $user_id User ID
 * @return bool
 */
function merc_is_client_user( $user_id ) {
    return merc_user_has_role( $user_id, 'wpcargo_client' );
}

/**
 * Check if user is driver
 * 
 * @param int $user_id User ID
 * @return bool
 */
function merc_is_driver_user( $user_id ) {
    return merc_user_has_role( $user_id, 'wpcargo_driver' );
}

/**
 * Get all drivers
 * 
 * @return array Array of WP_User objects
 */
function merc_get_all_drivers() {
    return get_users( [
        'role'    => 'wpcargo_driver',
        'orderby' => 'meta_key',
        'meta_key' => 'first_name',
        'order'   => 'ASC',
    ] );
}

/**
 * Get driver name by ID
 * 
 * @param int $driver_id Driver ID
 * @return string Driver name
 */
function merc_get_driver_name( $driver_id ) {
    $fname = get_user_meta( $driver_id, 'first_name', true );
    $lname = get_user_meta( $driver_id, 'last_name', true );
    $name  = trim( $fname . ' ' . $lname );

    if ( empty( $name ) ) {
        $user = get_user_by( 'id', $driver_id );
        $name = $user ? $user->display_name : '';
    }

    return $name;
}
