<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * MERC_Shipment_Filters
 *
 * Gestiona todos los filtros del historial de envíos del frontend WPCargo:
 *   - Barra de filtros: Fecha, Marca, Celular destinatario, Motorizado recojo/entrega.
 *   - Query filters: fecha (SQL directo + post__in), marca, celular y motorizado (meta_query).
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

        // ── Query: filtros en meta_query (mismo nivel que las condiciones de WPCargo) ─
        // IMPORTANTE: celular, marca y motorizados van en wpcfe_dashboard_meta_query
        // para quedar en el nivel interno correcto de la estructura que WPCargo arma.
        add_filter( 'wpcfe_dashboard_meta_query', [ $this, 'apply_marca_meta_query' ] );
        add_filter( 'wpcfe_dashboard_meta_query', [ $this, 'apply_celular_meta_query' ] );
        add_filter( 'wpcfe_dashboard_meta_query', [ $this, 'apply_driver_meta_query' ] );

        // ── Query: fecha usa wpcfe_dashboard_arguments para post__in (SQL directo) ──
        add_filter( 'wpcfe_dashboard_arguments', [ $this, 'apply_date_query' ], 20 );

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
        // Mostrar solo para usuarios logueados; el selector es suficientemente
        // específico (#wpcfe-filters) para no afectar otras páginas.
        if ( ! is_user_logged_in() ) {
            return;
        }
        ?>
        <style id="merc-hide-native-ajax-filters">
            /* Oculta los selectores AJAX nativos de WPCargo (shipper/receiver)
               que fallan por la dependencia de wpccf_get_field_by_metakey.
               Los reemplazos (Marca y Celular) los provee merc-table-customizer. */
            #wpcfe-filters .shipper-filter,
            #wpcfe-filters .receiver-filter {
                display: none !important;
            }
        </style>
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
        $celulares      = $this->get_celulares();
        $celular_actual = isset( $_GET['celular_destinatario'] )
            ? sanitize_text_field( $_GET['celular_destinatario'] )
            : '';
        ?>
        <div class="form-group wpcfe-filter p-0 mx-1">
            <div class="md-form form-group" style="margin:0;">
                <select name="celular_destinatario" class="form-control form-control-sm wpcfe-select">
                    <option value="">Todo Celular</option>
                    <?php foreach ( $celulares as $celular ) : ?>
                        <option value="<?php echo esc_attr( $celular ); ?>" <?php selected( $celular_actual, $celular ); ?>>
                            <?php echo esc_html( $celular ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
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

    /* ── UI: Filtros de Motorizado Recojo y Entrega ─────────────────────── */

    public function render_driver_filters(): void {
        $current_user = wp_get_current_user();
        if ( in_array( 'wpcargo_client', (array) $current_user->roles ) ) {
            return;
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

    /* ── Query: Fecha → post__in (wpcfe_dashboard_arguments) ───────────── */
    /*                                                                        */
    /* Solo maneja el filtro de fecha mediante SQL directo y post__in.       */
    /* Por defecto muestra HOY si no se pasan parámetros de fecha.           */

    public function apply_date_query( array $args ): array {
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

        if ( ! $has_from && ! $has_to ) {
            return $args;
        }

        unset( $args['date_query'] ); // Evitar conflicto con date_query nativo

        // Cachear el resultado para no repetir la consulta en recargas del mismo rango
        $cache_key = 'merc_date_ids_' . md5( $from . '_' . $to );
        $date_ids  = get_transient( $cache_key );

        if ( $date_ids === false ) {
            // Caso más común: día único — usar comparación directa (usa índice en meta_value)
            if ( $has_from && $has_to && $from === $to ) {
                $d        = \DateTime::createFromFormat( 'Y-m-d', $from );
                $date_dmy = $d ? $d->format( 'd/m/Y' ) : null;

                if ( $date_dmy ) {
                    $condition = $wpdb->prepare( 'pm.meta_value = %s', $date_dmy );
                } else {
                    $condition = $wpdb->prepare(
                        "STR_TO_DATE(pm.meta_value, '%%d/%%m/%%Y') = STR_TO_DATE(%s, '%%Y-%%m-%%d')",
                        $from
                    );
                }
            } else {
                // Rango de fechas: STR_TO_DATE inevitable (sin índice)
                $conditions = [];
                if ( $has_from ) {
                    $conditions[] = $wpdb->prepare(
                        "STR_TO_DATE(pm.meta_value, '%%d/%%m/%%Y') >= STR_TO_DATE(%s, '%%Y-%%m-%%d')",
                        $from
                    );
                }
                if ( $has_to ) {
                    $conditions[] = $wpdb->prepare(
                        "STR_TO_DATE(pm.meta_value, '%%d/%%m/%%Y') <= STR_TO_DATE(%s, '%%Y-%%m-%%d')",
                        $to
                    );
                }
                $condition = implode( ' AND ', $conditions );
            }

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $date_ids = $wpdb->get_col( "
                SELECT DISTINCT p.ID
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'wpcargo_shipment'
                  AND pm.meta_key IN (
                      'wpcargo_pickup_date_picker',
                      'wpcargo_pickup_date',
                      'wpcargo_calendarenvio',
                      'wpcargo_fecha_envio'
                  )
                  AND ( {$condition} )
            " );

            $date_ids = $date_ids ?: [];
            set_transient( $cache_key, $date_ids, MINUTE_IN_SECONDS );
        }

        if ( ! empty( $date_ids ) ) {
            $existing         = isset( $args['post__in'] ) && ! empty( $args['post__in'] )
                ? $args['post__in']
                : null;
            $args['post__in'] = $existing
                ? array_values( array_intersect( $existing, $date_ids ) )
                : array_map( 'intval', $date_ids );
        } else {
            $args['post__in'] = [ 0 ]; // Sin resultados para este rango de fechas
        }

        return $args;
    }

    /* ── Query: Marca / Nombre de tienda (wpcfe_dashboard_meta_query) ───── */

    public function apply_marca_meta_query( array $meta_query ): array {
        if ( ! empty( $_GET['wpcargo_tiendaname'] ) ) {
            $meta_query[] = [
                'key'     => 'wpcargo_tiendaname',
                'value'   => sanitize_text_field( $_GET['wpcargo_tiendaname'] ),
                'compare' => 'LIKE',
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
                'compare' => '=',
            ];
        }
        return $meta_query;
    }

    /* ── Query: Motorizado Recojo / Entrega (wpcfe_dashboard_meta_query) ── */

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

    /* ── Helpers ─────────────────────────────────────────────────────────── */

    private function get_marcas(): array {
        $cached = get_transient( 'merc_marcas_list' );
        if ( $cached !== false ) {
            return $cached;
        }
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $results = $wpdb->get_col( "
            SELECT DISTINCT meta_value
            FROM {$wpdb->postmeta}
            WHERE meta_key  = 'wpcargo_tiendaname'
              AND meta_value IS NOT NULL
              AND meta_value != ''
            ORDER BY meta_value ASC
        " );
        $marcas = array_unique( array_map( 'trim', $results ) );
        set_transient( 'merc_marcas_list', $marcas, 5 * MINUTE_IN_SECONDS );
        return $marcas;
    }

    private function get_celulares(): array {
        $cached = get_transient( 'merc_celulares_list' );
        if ( $cached !== false ) {
            return $cached;
        }
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $results = $wpdb->get_col( "
            SELECT DISTINCT meta_value
            FROM {$wpdb->postmeta}
            WHERE meta_key  = 'wpcargo_receiver_phone'
              AND meta_value IS NOT NULL
              AND meta_value != ''
            ORDER BY meta_value ASC
        " );
        $celulares = array_unique( array_map( 'trim', $results ) );
        set_transient( 'merc_celulares_list', $celulares, 5 * MINUTE_IN_SECONDS );
        return $celulares;
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
            return $header_data;
        }
        $defaults = [
            'shipper'  => [ 'label' => 'Nombre de la Marca', 'field_key' => 'wpcargo_tiendaname' ],
            'receiver' => [ 'label' => 'Celular',            'field_key' => 'wpcargo_receiver_phone' ],
        ];
        return $defaults[ $section ] ?? $header_data;
    }
}

new MERC_Shipment_Filters();
