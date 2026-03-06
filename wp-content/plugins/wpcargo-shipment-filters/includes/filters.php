<?php

namespace WPCargo_Shipment_Filters;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Filters {

    /**
     * Register hooks
     */
    public function register_hooks() {
        // Date filter meta query
        add_filter( 'wpcfe_dashboard_meta_query', [ $this, 'shipping_date_meta_query' ] );
        add_filter( 'wpcfe_dashboard_arguments', [ $this, 'filter_shipment_by_date_and_tiendaname' ], 20 );
        
        // Driver filters meta query
        add_filter( 'wpcfe_dashboard_meta_query', [ $this, 'driver_filters_meta_query' ] );
    }

    /**
     * Add date range meta query
     * 
     * @param array $meta_query
     * @return array
     */
    public function shipping_date_meta_query( $meta_query ) {
        $date_from_iso = isset( $_GET['shipping_date_start'] )
            ? sanitize_text_field( $_GET['shipping_date_start'] )
            : '';

        $date_to_iso = isset( $_GET['shipping_date_end'] )
            ? sanitize_text_field( $_GET['shipping_date_end'] )
            : '';

        if ( empty( $date_from_iso ) || empty( $date_to_iso ) ) {
            return $meta_query;
        }

        // Validate format
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from_iso ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to_iso ) ) {
            return $meta_query;
        }

        // Generate dates array from start to end
        $start = new \DateTime( $date_from_iso );
        $end   = new \DateTime( $date_to_iso );
        $end->modify( '+1 day' ); // Include last day

        $dates = array();
        $interval = new \DateInterval( 'P1D' );
        $period   = new \DatePeriod( $start, $interval, $end );

        foreach ( $period as $dt ) {
            $dates[] = $dt->format( 'd/m/Y' );
        }

        if ( ! empty( $dates ) ) {
            $meta_query[] = array(
                'key'     => 'wpcargo_pickup_date_picker',
                'value'   => $dates,
                'compare' => 'IN',
            );
        }

        return $meta_query;
    }

    /**
     * Filter shipments by date and tiendaname using direct query
     * 
     * @param array $args WP_Query arguments
     * @return array Modified args
     */
    public function filter_shipment_by_date_and_tiendaname( $args ) {
        global $wpdb;

        error_log( "🔵 [FILTER] filter_shipment_by_date_and_tiendaname called" );
        error_log( "🔵 [FILTER] Current user: " . get_current_user_id() );

        // 1. DATE FILTER
        $from = isset( $_GET['shipping_date_start'] ) ? sanitize_text_field( $_GET['shipping_date_start'] ) : '';
        $to   = isset( $_GET['shipping_date_end'] ) ? sanitize_text_field( $_GET['shipping_date_end'] ) : '';

        // Apply today by default if no dates provided
        if ( empty( $from ) && empty( $to ) ) {
            $from = current_time( 'Y-m-d' );
            $to   = current_time( 'Y-m-d' );
            error_log( "🔵 [FILTER] No date parameters, applying today: {$from}" );
        }

        error_log( "🔵 [FILTER] Date from: '{$from}' | Date to: '{$to}'" );

        $date_filtered_ids = array();

        // Validate date formats and build SQL condition
        $has_from = $from && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from );
        $has_to   = $to && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to );

        if ( $has_from || $has_to ) {
            // Remove date_query to avoid conflicts
            if ( isset( $args['date_query'] ) ) {
                error_log( "🔵 [FILTER] Removing date_query to avoid conflicts" );
                unset( $args['date_query'] );
            }

            // Build date conditions
            $date_conditions = array();

            if ( $has_from ) {
                $date_conditions[] = "STR_TO_DATE(pm.meta_value, '%d/%m/%Y') >= STR_TO_DATE('" . sanitize_text_field( $from ) . "', '%Y-%m-%d')";
            }

            if ( $has_to ) {
                $date_conditions[] = "STR_TO_DATE(pm.meta_value, '%d/%m/%Y') <= STR_TO_DATE('" . sanitize_text_field( $to ) . "', '%Y-%m-%d')";
            }

            $final_date_condition = implode( ' AND ', $date_conditions );

            // Query shipments with date in specified range
            $query = "
                SELECT DISTINCT p.ID 
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'wpcargo_shipment'
                AND pm.meta_key IN ('wpcargo_pickup_date_picker', 'wpcargo_pickup_date', 'calendarenvio', 'wpcargo_fecha_envio')
                AND ({$final_date_condition})
            ";

            error_log( "🔵 [FILTER] SQL Query: " . $query );

            $date_filtered_ids = $wpdb->get_col( $query );

            error_log( "🔵 [FILTER] IDs found by date: " . count( $date_filtered_ids ) );
            if ( ! empty( $date_filtered_ids ) ) {
                error_log( "🔵 [FILTER] First 10 IDs: " . implode( ', ', array_slice( $date_filtered_ids, 0, 10 ) ) );
            }

            // Apply post__in filter
            if ( ! empty( $date_filtered_ids ) ) {
                if ( isset( $args['post__in'] ) && ! empty( $args['post__in'] ) ) {
                    error_log( "🔵 [FILTER] post__in exists with " . count( $args['post__in'] ) . " IDs" );
                    $args['post__in'] = array_intersect( $args['post__in'], $date_filtered_ids );
                    error_log( "🔵 [FILTER] After intersect: " . count( $args['post__in'] ) . " IDs" );
                } else {
                    error_log( "🔵 [FILTER] Creating new post__in" );
                    $args['post__in'] = $date_filtered_ids;
                }
            } else {
                error_log( "🔵 [FILTER] No IDs found, setting post__in to empty array" );
                $args['post__in'] = array( 0 );
            }
        }

        // 2. TIENDANAME FILTER
        if ( ! empty( $_GET['wpcargo_tiendaname'] ) ) {
            $tiendaname = sanitize_text_field( $_GET['wpcargo_tiendaname'] );
            error_log( "🔵 [FILTER] Applying tiendaname filter: {$tiendaname}" );

            // Query posts by tiendaname
            $tienda_ids = $wpdb->get_col( $wpdb->prepare(
                "SELECT DISTINCT post_id FROM {$wpdb->postmeta} 
                WHERE meta_key = 'wpcargo_tiendaname' 
                AND meta_value LIKE %s",
                '%' . $wpdb->esc_like( $tiendaname ) . '%'
            ) );

            error_log( "🔵 [FILTER] IDs found by tiendaname: " . count( $tienda_ids ) );

            if ( ! empty( $tienda_ids ) ) {
                if ( isset( $args['post__in'] ) && ! empty( $args['post__in'] ) ) {
                    $args['post__in'] = array_intersect( $args['post__in'], $tienda_ids );
                } else {
                    $args['post__in'] = $tienda_ids;
                }
            } else {
                $args['post__in'] = array( 0 );
            }
        }

        error_log( "🔵 [FILTER] Final post__in count: " . ( isset( $args['post__in'] ) ? count( $args['post__in'] ) : 0 ) );

        return $args;
    }

    /**
     * Add driver filters to meta query
     * 
     * @param array $meta_query
     * @return array
     */
    public function driver_filters_meta_query( $meta_query ) {
        if ( isset( $_GET['wpcargo_motorizo_recojo'] ) && ! empty( $_GET['wpcargo_motorizo_recojo'] ) ) {
            $meta_query[] = [
                'key'     => 'wpcargo_motorizo_recojo',
                'value'   => intval( $_GET['wpcargo_motorizo_recojo'] ),
                'compare' => '='
            ];
        }

        if ( isset( $_GET['wpcargo_motorizo_entrega'] ) && ! empty( $_GET['wpcargo_motorizo_entrega'] ) ) {
            $meta_query[] = [
                'key'     => 'wpcargo_motorizo_entrega',
                'value'   => intval( $_GET['wpcargo_motorizo_entrega'] ),
                'compare' => '='
            ];
        }

        return $meta_query;
    }
}
