<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * MERC_Shipment_Filters
 *
 * Gestiona todos los filtros del historial de envíos del frontend WPCargo:
 *   - Barra de filtros: Fecha, Marca, Celular destinatario, Motorizado recojo/entrega.
 *   - Query filters: fecha (SQL directo), marca, celular y motorizado (meta_query).
 *   - Rename "Shipments" → "Historial de Envíos".
 */
class MERC_Shipment_Filters {

    public function __construct() {
        // Quitar el filtro de fecha nativo de WPCargo antes de añadir los propios
        add_action( 'plugins_loaded', [ $this, 'remove_native_filters' ], 20 );

        // ── UI: Ocultar controles AJAX nativos rotos de WPCargo ──────────
        add_action( 'wp_head', [ $this, 'suppress_native_ajax_filters_css' ] );

        // ── UI: Barra de filtros ──────────────────────────────────────────
        add_action( 'wpcfe_after_shipment_filters', [ $this, 'render_date_filter' ],    100 );
        add_action( 'wpcfe_after_shipment_filters', [ $this, 'render_marca_filter' ],   101 );
        add_action( 'wpcfe_after_shipment_filters', [ $this, 'render_celular_filter' ], 102 );
        add_action( 'wpcfe_after_shipment_filters', [ $this, 'render_driver_filters' ], 103 );

        // ── Query: filtros aplicados al WP_Query ──────────────────────────
        add_filter( 'wpcfe_dashboard_arguments',  [ $this, 'apply_date_and_marca_query' ], 20 );
        add_filter( 'wpcfe_dashboard_meta_query', [ $this, 'apply_driver_meta_query' ] );
        add_filter( 'wpcfe_dashboard_meta_query', [ $this, 'apply_celular_meta_query' ] );

        // ── Internacionalización ──────────────────────────────────────────
        add_filter( 'gettext', [ $this, 'rename_shipments_text' ], 20, 3 );

        // ── Fallback para wpcfe_table_header cuando wpccf no está activo ─
        add_filter( 'wpcfe_table_header', [ $this, 'fix_table_header_fallback' ], 5, 2 );
    }

    /* ── Eliminar filtros nativos de WPCargo ────────────────────────────── */

    public function remove_native_filters(): void {
        remove_action( 'wpcfe_after_shipment_filters', 'wpcfe_shipment_created_date_filter_callback', 100 );
        // Ambas variantes del nombre (WPCargo tiene un typo en versiones distintas)
        remove_filter( 'wpcfe_dashboard_arguments', 'wpcfe_shipment_created_date_query_args_callback' );
        remove_filter( 'wpcfe_dashboard_arguments', 'wpcfe_shipment_created_date_quuery_args_callback' );
    }

    /* ── Ocultar controles AJAX nativos rotos (shipper/receiver Select2) ── */

    public function suppress_native_ajax_filters_css(): void {
        if ( ! isset( $_GET['wpcfe'] ) || $_GET['wpcfe'] !== 'shipments' ) {
            return;
        }
        ?>
        <style id="merc-hide-native-ajax-filters">
            /* Oculta los filtros AJAX nativos de WPCargo (shipper/receiver)
               que fallan por la dependencia de wpccf_get_field_by_metakey.
               Los filtros equivalentes (Marca y Celular) son provistos por
               el plugin merc-table-customizer mediante controles PHP nativos. */
            #wpcfe-filters .shipper-filter,
            #wpcfe-filters .receiver-filter {
                display: none !important;
            }
        </style>
        <?php
    }

    /* ── UI: Filtro de Fecha de Envío ───────────────────────────────────── */

    public function render_date_filter(): void {
        $today = date( 'Y-m-d' );
        $start = isset( $_GET['shipping_date_start'] )
            ? sanitize_text_field( $_GET['shipping_date_start'] )
            : $today;
        $end   = isset( $_GET['shipping_date_end'] )
            ? sanitize_text_field( $_GET['shipping_date_end'] )
            : $today;
        ?>
        <div id="wpcfe-custom-shipping-date" class="form-group wpcfe-filter p-0 mx-1">
            <div class="md-form form-group">
                <strong>Fecha de Envío</strong>
                <input id="shipping_date_start"
                       name="shipping_date_start"
                       type="text"
                       class="form-control daterange_picker start_date px-2 py-1 mx-2"
                       style="width:110px;font-weight:500;"
                       autocomplete="off"
                       value="<?php echo esc_attr( $start ); ?>"
                       placeholder="YYYY-MM-DD" />
                <input id="shipping_date_end"
                       name="shipping_date_end"
                       type="text"
                       class="form-control daterange_picker end_date px-2 py-1 mx-2"
                       style="width:110px;font-weight:500;"
                       autocomplete="off"
                       value="<?php echo esc_attr( $end ); ?>"
                       placeholder="YYYY-MM-DD" />
            </div>
        </div>
        <?php
    }

    /* ── UI: Filtro por Marca (Nombre de Tienda) ────────────────────────── */

    public function render_marca_filter(): void {
        $marcas       = $this->get_marcas();
        $marca_actual = isset( $_GET['wpcargo_tiendaname'] )
            ? sanitize_text_field( $_GET['wpcargo_tiendaname'] )
            : '';
        ?>
        <div class="form-group wpcfe-filter p-0 mx-1">
            <div class="md-form form-group" style="margin:0;">
                <select name="wpcargo_tiendaname" class="form-control form-control-sm wpcfe-select">
                    <option value="">Todas las marcas</option>
                    <?php foreach ( $marcas as $marca ) : ?>
                        <option value="<?php echo esc_attr( $marca ); ?>" <?php selected( $marca_actual, $marca ); ?>>
                            <?php echo esc_html( $marca ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php
    }

    /* ── UI: Filtro por Celular del Destinatario ────────────────────────── */

    public function render_celular_filter(): void {
        $celular = isset( $_GET['celular_destinatario'] )
            ? sanitize_text_field( $_GET['celular_destinatario'] )
            : '';
        ?>
        <div class="form-group wpcfe-filter p-0 mx-1">
            <div class="md-form form-group" style="margin:0;">
                <input type="text"
                       name="celular_destinatario"
                       class="form-control form-control-sm"
                       style="width:150px;"
                       placeholder="Celular destinatario"
                       value="<?php echo esc_attr( $celular ); ?>" />
            </div>
        </div>
        <?php
    }

    /* ── UI: Filtros de Motorizado Recojo y Entrega ─────────────────────── */

    public function render_driver_filters(): void {
        $current_user = wp_get_current_user();
        if ( in_array( 'wpcargo_client', (array) $current_user->roles ) ) {
            return; // Los clientes no ven los filtros de motorizado
        }

        $value_recojo  = isset( $_GET['wpcargo_motorizo_recojo'] )
            ? esc_attr( $_GET['wpcargo_motorizo_recojo'] )
            : '';
        $value_entrega = isset( $_GET['wpcargo_motorizo_entrega'] )
            ? esc_attr( $_GET['wpcargo_motorizo_entrega'] )
            : '';

        $drivers = get_users( [
            'role'    => 'wpcargo_driver',
            'orderby' => 'display_name',
            'order'   => 'ASC',
        ] );
        ?>

        <!-- MOTORIZADO RECOJO -->
        <div class="form-group wpcfe-filter p-0 mx-1">
            <div class="md-form form-group" style="margin:0;">
                <select name="wpcargo_motorizo_recojo" class="form-control form-control-sm wpcfe-select">
                    <option value="">Motorizado Recojo...</option>
                    <?php foreach ( $drivers as $driver ) :
                        $nombre = trim(
                            get_user_meta( $driver->ID, 'first_name', true ) . ' ' .
                            get_user_meta( $driver->ID, 'last_name',  true )
                        );
                    ?>
                        <option value="<?php echo esc_attr( $driver->ID ); ?>" <?php selected( $value_recojo, $driver->ID ); ?>>
                            <?php echo esc_html( $nombre ?: $driver->display_name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- MOTORIZADO ENTREGA -->
        <div class="form-group wpcfe-filter p-0 mx-1">
            <div class="md-form form-group" style="margin:0;">
                <select name="wpcargo_motorizo_entrega" class="form-control form-control-sm wpcfe-select">
                    <option value="">Motorizado Entrega...</option>
                    <?php foreach ( $drivers as $driver ) :
                        $nombre = trim(
                            get_user_meta( $driver->ID, 'first_name', true ) . ' ' .
                            get_user_meta( $driver->ID, 'last_name',  true )
                        );
                    ?>
                        <option value="<?php echo esc_attr( $driver->ID ); ?>" <?php selected( $value_entrega, $driver->ID ); ?>>
                            <?php echo esc_html( $nombre ?: $driver->display_name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <?php
    }

    /* ── Query: Fecha + Marca (wpcfe_dashboard_arguments) ──────────────── */

    public function apply_date_and_marca_query( array $args ): array {
        global $wpdb;

        $from = isset( $_GET['shipping_date_start'] )
            ? sanitize_text_field( $_GET['shipping_date_start'] )
            : '';
        $to   = isset( $_GET['shipping_date_end'] )
            ? sanitize_text_field( $_GET['shipping_date_end'] )
            : '';

        // Sin parámetros: mostrar el día de hoy por defecto
        if ( empty( $from ) && empty( $to ) ) {
            $from = current_time( 'Y-m-d' );
            $to   = current_time( 'Y-m-d' );
        }

        $has_from = $from && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from );
        $has_to   = $to   && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to );

        if ( $has_from || $has_to ) {
            unset( $args['date_query'] ); // Evitar conflicto con date_query nativo

            $date_conditions = [];
            if ( $has_from ) {
                $date_conditions[] = "STR_TO_DATE(pm.meta_value, '%d/%m/%Y') >= STR_TO_DATE('"
                    . esc_sql( $from ) . "', '%Y-%m-%d')";
            }
            if ( $has_to ) {
                $date_conditions[] = "STR_TO_DATE(pm.meta_value, '%d/%m/%Y') <= STR_TO_DATE('"
                    . esc_sql( $to ) . "', '%Y-%m-%d')";
            }
            $final_condition = implode( ' AND ', $date_conditions );

            $date_ids = $wpdb->get_col( "
                SELECT DISTINCT p.ID
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'wpcargo_shipment'
                  AND pm.meta_key IN (
                      'wpcargo_pickup_date_picker',
                      'wpcargo_pickup_date',
                      'calendarenvio',
                      'wpcargo_fecha_envio'
                  )
                  AND ( {$final_condition} )
            " );

            if ( ! empty( $date_ids ) ) {
                $args['post__in'] = isset( $args['post__in'] ) && ! empty( $args['post__in'] )
                    ? array_values( array_intersect( $args['post__in'], $date_ids ) )
                    : array_map( 'intval', $date_ids );
                $args['post_status'] = [ 'publish', 'draft', 'pending', 'private' ];
            } else {
                $args['post__in'] = [ 0 ]; // Sin resultados
            }
        }

        // ── Filtro por marca / nombre de tienda ───────────────────────────
        if ( ! empty( $_GET['wpcargo_tiendaname'] ) ) {
            if ( ! isset( $args['meta_query'] ) ) {
                $args['meta_query'] = [];
            }
            $args['meta_query']['wpcargo_tiendaname'] = [
                'key'     => 'wpcargo_tiendaname',
                'value'   => sanitize_text_field( $_GET['wpcargo_tiendaname'] ),
                'compare' => 'LIKE',
            ];
        }

        return $args;
    }

    /* ── Query: Motorizado (wpcfe_dashboard_meta_query) ────────────────── */

    public function apply_driver_meta_query( array $meta_query ): array {
        if ( ! empty( $_GET['wpcargo_motorizo_recojo'] ) ) {
            $meta_query[] = [
                'key'     => 'wpcargo_motorizo_recojo',
                'value'   => intval( $_GET['wpcargo_motorizo_recojo'] ),
                'compare' => '=',
            ];
        }
        if ( ! empty( $_GET['wpcargo_motorizo_entrega'] ) ) {
            $meta_query[] = [
                'key'     => 'wpcargo_motorizo_entrega',
                'value'   => intval( $_GET['wpcargo_motorizo_entrega'] ),
                'compare' => '=',
            ];
        }
        return $meta_query;
    }

    /* ── Query: Celular del destinatario (wpcfe_dashboard_meta_query) ───── */

    public function apply_celular_meta_query( array $meta_query ): array {
        if ( ! empty( $_GET['celular_destinatario'] ) ) {
            $meta_query[] = [
                'key'     => 'wpcargo_receiver_phone',
                'value'   => sanitize_text_field( $_GET['celular_destinatario'] ),
                'compare' => 'LIKE',
            ];
        }
        return $meta_query;
    }

    /* ── Helpers ─────────────────────────────────────────────────────────── */

    private function get_marcas(): array {
        global $wpdb;
        $results = $wpdb->get_col( "
            SELECT DISTINCT meta_value
            FROM {$wpdb->postmeta}
            WHERE meta_key  = 'wpcargo_tiendaname'
              AND meta_value IS NOT NULL
              AND meta_value != ''
            ORDER BY meta_value ASC
        " );
        return array_unique( array_map( 'trim', $results ) );
    }

    /* ── Rename "Shipments" → "Historial de Envíos" ─────────────────────── */

    public function rename_shipments_text( string $translated_text, string $text, string $domain ): string {
        if ( $domain !== 'wpcargo-frontend-manager' ) {
            return $translated_text;
        }
        if ( $text === 'Shipments' ) {
            return 'Historial de Envíos';
        }
        if ( strpos( $text, 'Shipments <span' ) !== false ) {
            return str_replace( 'Shipments', 'Historial de Envíos', $text );
        }
        return $translated_text;
    }

    /* ── Fallback para wpcfe_table_header ───────────────────────────────── */
    /* Garantiza que field_key siempre exista aunque wpccf no esté activo.   */

    public function fix_table_header_fallback( array $header_data, string $section ): array {
        if ( ! empty( $header_data['field_key'] ) ) {
            return $header_data; // Ya tiene datos correctos, no tocar.
        }
        // Mapeo de sección → meta_key y etiqueta locales
        $defaults = [
            'shipper'  => [ 'label' => 'Nombre de la Marca', 'field_key' => 'wpcargo_tiendaname' ],
            'receiver' => [ 'label' => 'Celular',            'field_key' => 'wpcargo_receiver_phone' ],
        ];
        return $defaults[ $section ] ?? $header_data;
    }
}

new MERC_Shipment_Filters();
