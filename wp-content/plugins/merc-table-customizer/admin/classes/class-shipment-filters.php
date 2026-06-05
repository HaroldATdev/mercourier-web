<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * MERC_Shipment_Filters
 *
 * Gestiona todos los filtros del historial de envíos del frontend WPCargo:
 *   - Barra de filtros: Fecha, Marca, Celular destinatario, Motorizado recojo/entrega.
 *   - Query filters: todas las condiciones como EXISTS subqueries en posts_where (sin JOINs extra).
 *   - Rename "Shipments" → "Historial de Envíos".
 */
if ( ! class_exists( 'MERC_Shipment_Filters' ) ) {

class MERC_Shipment_Filters {

    /** Condiciones WHERE acumuladas para inyectar via posts_where. */
    private array $custom_where_conds = [];

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
        add_action( 'wpcfe_after_shipment_filters', [ $this, 'render_filter_cliente' ], 104 );
        add_action( 'wpcfe_after_shipment_filters', [ $this, 'render_distrito_filter' ],  105 );

        // ── Query: todos los filtros via EXISTS (sin JOINs en WP_Query) ───
        // Preparamos las condiciones en wpcfe_dashboard_arguments y las
        // inyectamos en posts_where para evitar JOINs lentos en meta_query.
        add_filter( 'wpcfe_dashboard_arguments', [ $this, 'prepare_custom_filter_clauses' ], 20 );

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
        // Quitar filtro de fecha redundante de blocksy-child para evitar duplicación y JOINs lentos
        remove_filter( 'wpcfe_dashboard_meta_query', 'wpcfe_shipping_date_meta_query_callback' );
    }

    /* ── Ocultar controles AJAX nativos rotos (shipper/receiver Select2) ── */

    public function suppress_native_ajax_filters_css(): void {
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
        $current_user = wp_get_current_user();
        if ( ! in_array( 'administrator', (array) $current_user->roles ) &&
             ! in_array( 'wpcargo_admin', (array) $current_user->roles ) &&
             ! in_array( 'wpcargo_driver', (array) $current_user->roles ) ) {
            return;
        }

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
        $current_user = wp_get_current_user();
        if ( ! in_array( 'administrator', (array) $current_user->roles ) &&
             ! in_array( 'wpcargo_admin', (array) $current_user->roles ) &&
             ! in_array( 'wpcargo_driver', (array) $current_user->roles ) ) {
            return;
        }

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
        $today = current_time( 'Y-m-d' );
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

    /* ── UI: Filtro de Distrito de Entrega ──────────────────────────────── */
    public function render_distrito_filter(): void {
        $current_user = wp_get_current_user();
        if ( ! in_array( 'administrator', (array) $current_user->roles ) &&
             ! in_array( 'wpcargo_admin', (array) $current_user->roles ) ) {
            return;
        }
        $selected = isset( $_GET['distrito_destino'] ) ? sanitize_text_field( $_GET['distrito_destino'] ) : '';
        $distritos = $this->get_distritos();
        ?>
        <div class="form-group wpcfe-filter p-0 mx-1">
            <div class="md-form form-group" style="margin:0;">
                <select name="distrito_destino" class="form-control form-control-sm wpcfe-select">
                    <option value="">Distrito Entrega...</option>
                    <?php foreach ( $distritos as $distrito ) : ?>
                        <option value="<?php echo esc_attr( $distrito ); ?>" <?php selected( $selected, $distrito ); ?>>
                            <?php echo esc_html( $distrito ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php
    }

    public function render_driver_filters(): void {
        $current_user = wp_get_current_user();
        if ( ! in_array( 'administrator', (array) $current_user->roles ) &&
             ! in_array( 'wpcargo_admin', (array) $current_user->roles ) &&
             ! in_array( 'wpcargo_driver', (array) $current_user->roles ) ) {
            return;
        }

        $value_recojo  = isset( $_GET['wpcargo_motorizo_recojo'] )
            ? esc_attr( $_GET['wpcargo_motorizo_recojo'] )
            : '';
        $value_entrega = isset( $_GET['wpcargo_motorizo_entrega'] )
            ? esc_attr( $_GET['wpcargo_motorizo_entrega'] )
            : '';

        $drivers_data = get_transient('merc_driver_filters_data');
        if ( $drivers_data === false ) {
            $drivers = get_users( [
                'role'    => 'wpcargo_driver',
                'orderby' => 'display_name',
                'order'   => 'ASC',
            ] );
            $drivers_data = [];
            foreach ( $drivers as $driver ) {
                $nombre = trim(
                    get_user_meta( $driver->ID, 'first_name', true ) . ' ' .
                    get_user_meta( $driver->ID, 'last_name',  true )
                );
                $drivers_data[$driver->ID] = $nombre ?: $driver->display_name;
            }
            natcasesort($drivers_data); // Ordenar alfabéticamente sin distinguir mayúsculas
            set_transient('merc_driver_filters_data', $drivers_data, 12 * HOUR_IN_SECONDS);
        }
        ?>

        <!-- MOTORIZADO RECOJO -->
        <div class="form-group wpcfe-filter p-0 mx-1">
            <div class="md-form form-group" style="margin:0;">
                <select name="wpcargo_motorizo_recojo" class="form-control form-control-sm wpcfe-select">
                    <option value="">Motorizado Recojo...</option>
                    <?php foreach ( $drivers_data as $id => $display_name ) : ?>
                        <option value="<?php echo esc_attr( $id ); ?>" <?php selected( $value_recojo, $id ); ?>>
                            <?php echo esc_html( $display_name ); ?>
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
                    <?php foreach ( $drivers_data as $id => $display_name ) : ?>
                        <option value="<?php echo esc_attr( $id ); ?>" <?php selected( $value_entrega, $id ); ?>>
                            <?php echo esc_html( $display_name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <?php
    }

    /* ── UI: Filtro de Cliente (Marca por Nombre) ─────────────────────────────────── */

    public function render_filter_cliente(): void {
        $current_user = wp_get_current_user();
        if ( ! in_array( 'administrator', (array) $current_user->roles ) &&
             ! in_array( 'wpcargo_admin', (array) $current_user->roles ) &&
             ! in_array( 'wpcargo_driver', (array) $current_user->roles ) ) {
            return;
        }

        $clientes_data = get_transient('merc_client_filters_data');
        if ( $clientes_data === false ) {
            $clientes = get_users( [
                'role'    => 'wpcargo_client',
                'orderby' => 'display_name',
                'order'   => 'ASC',
            ] );
            $clientes_data = [];
            foreach ( $clientes as $cliente ) {
                $clientes_data[$cliente->ID] = trim( $cliente->first_name . ' ' . $cliente->last_name ) ?: $cliente->display_name;
            }
            set_transient('merc_client_filters_data', $clientes_data, 12 * HOUR_IN_SECONDS);
        }

        $selected = isset( $_GET['filter_wpcargoclient'] ) ? intval( $_GET['filter_wpcargoclient'] ) : 0;
        ?>
        <div class="form-group wpcfe-filter p-0 mx-1">
            <div class="md-form form-group" style="margin:0;">
                <select name="filter_wpcargoclient" class="form-control form-control-sm wpcfe-select">
                    <option value="">Marca por Nombre</option>
                    <?php foreach ( $clientes_data as $id => $display_name ) : ?>
                        <option value="<?php echo esc_attr( $id ); ?>" <?php selected( $selected, $id ); ?>>
                            <?php echo esc_html( $display_name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php
    }

    /* ── Query: construye EXISTS subqueries e inyecta en posts_where ─────── */
    /*                                                                         */
    /* Cada filtro genera un EXISTS (SELECT 1 FROM wp_postmeta WHERE ...)     */
    /* que MySQL resuelve con el índice meta_key sin JOINs en la query        */
    /* principal de WP_Query. Mucho más rápido que meta_query o post__in.    */

    public function prepare_custom_filter_clauses( array $args ): array {
        global $wpdb;

        $this->custom_where_conds = [];

        unset( $args['date_query'] ); // evitar conflicto con date_query nativo

        // ── Fecha ─────────────────────────────────────────────────────────
        $from = isset( $_GET['shipping_date_start'] )
            ? sanitize_text_field( $_GET['shipping_date_start'] ) : '';
        $to   = isset( $_GET['shipping_date_end'] )
            ? sanitize_text_field( $_GET['shipping_date_end'] )   : '';

        if ( empty( $from ) && empty( $to ) ) {
            $from = current_time( 'Y-m-d' );
            $to   = current_time( 'Y-m-d' );
        }

        $has_from = $from && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from );
        $has_to   = $to   && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to );

        if ( $has_from || $has_to ) {
            $keys_in = "'wpcargo_pickup_date_picker','wpcargo_pickup_date','wpcargo_calendarenvio','wpcargo_fecha_envio'";
            $date_cond = '';

            if ( $has_from && $has_to ) {
                if ( $from === $to ) {
                    // Día único → comparación directa (usa índice en meta_value sin STR_TO_DATE)
                    $d = \DateTime::createFromFormat( 'Y-m-d', $from );
                    if ( $d ) {
                        // Buscar en formatos: ISO (YYYY-MM-DD) o Legacy (DD/MM/YYYY, etc.)
                        $formats = [
                            $from, // YYYY-MM-DD
                            $d->format( 'd/m/Y' ),
                            $d->format( 'j/n/Y' ),
                            $d->format( 'd-m-Y' ),
                            $d->format( 'j-n-Y' )
                        ];
                        $formats = array_unique( $formats );
                        $placeholders = implode( ',', array_fill( 0, count( $formats ), '%s' ) );
                        $date_cond = $wpdb->prepare( "pm_dt.meta_value IN ($placeholders)", ...$formats );
                    } else {
                        $date_cond = $wpdb->prepare( "pm_dt.meta_value = %s", $from );
                    }
                } else {
                    // Rango de fechas
                    try {
                        $start_dt = new \DateTime( $from );
                        $end_dt   = new \DateTime( $to );
                        if ( $start_dt > $end_dt ) {
                            $temp = $start_dt;
                            $start_dt = $end_dt;
                            $end_dt = $temp;
                            
                            $temp_str = $from;
                            $from = $to;
                            $to = $temp_str;
                        }

                        $diff = $start_dt->diff( $end_dt )->days;

                        if ( $diff <= 31 ) {
                            // Generar todos los formatos tradicionales para el rango
                            $legacy_dates = [];
                            $end_dt_inclusive = clone $end_dt;
                            $end_dt_inclusive->modify( '+1 day' );
                            $interval = new \DateInterval( 'P1D' );
                            $period   = new \DatePeriod( $start_dt, $interval, $end_dt_inclusive );

                            foreach ( $period as $date ) {
                                $legacy_dates[] = $date->format( 'd/m/Y' );
                                $legacy_dates[] = $date->format( 'j/n/Y' );
                                $legacy_dates[] = $date->format( 'd-m-Y' );
                                $legacy_dates[] = $date->format( 'j-n-Y' );
                            }
                            $legacy_dates = array_unique( $legacy_dates );

                            if ( ! empty( $legacy_dates ) ) {
                                $placeholders = implode( ',', array_fill( 0, count( $legacy_dates ), '%s' ) );
                                $date_cond = $wpdb->prepare(
                                    "(pm_dt.meta_value BETWEEN %s AND %s) OR pm_dt.meta_value IN ($placeholders)",
                                    ...array_merge( [ $from, $to ], $legacy_dates )
                                );
                            } else {
                                $date_cond = $wpdb->prepare( "pm_dt.meta_value BETWEEN %s AND %s", $from, $to );
                            }
                        } else {
                            // Rango muy grande: usamos STR_TO_DATE de respaldo para evitar una lista IN gigantesca
                            $date_cond = $wpdb->prepare(
                                "( (pm_dt.meta_value BETWEEN %s AND %s) OR (STR_TO_DATE(pm_dt.meta_value, '%%d/%%m/%%Y') BETWEEN STR_TO_DATE(%s, '%%Y-%%m-%%d') AND STR_TO_DATE(%s, '%%Y-%%m-%%d')) )",
                                $from, $to, $from, $to
                            );
                        }
                    } catch ( \Exception $e ) {
                        // Respaldo en caso de error
                        $date_cond = $wpdb->prepare( "pm_dt.meta_value BETWEEN %s AND %s", $from, $to );
                    }
                }
            } elseif ( $has_from ) {
                // Solo fecha inicio
                $date_cond = $wpdb->prepare(
                    "(pm_dt.meta_value >= %s OR STR_TO_DATE(pm_dt.meta_value, '%%d/%%m/%%Y') >= STR_TO_DATE(%s, '%%Y-%%m-%%d'))",
                    $from, $from
                );
            } else {
                // Solo fecha fin
                $date_cond = $wpdb->prepare(
                    "(pm_dt.meta_value <= %s OR STR_TO_DATE(pm_dt.meta_value, '%%d/%%m/%%Y') <= STR_TO_DATE(%s, '%%Y-%%m-%%d'))",
                    $to, $to
                );
            }

            // Usamos subconsulta EXISTS no correlacionada para máximo rendimiento
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $final_condition = "EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm_dt
                WHERE pm_dt.post_id = {$wpdb->posts}.ID
                  AND pm_dt.meta_key IN ({$keys_in})
                  AND ( {$date_cond} )
            )";

            $this->custom_where_conds[] = $final_condition;
        }

        // ── Marca ─────────────────────────────────────────────────────────
        $current_user = wp_get_current_user();
        $is_admin_or_driver = in_array( 'administrator', (array) $current_user->roles ) ||
                              in_array( 'wpcargo_admin', (array) $current_user->roles ) ||
                              in_array( 'wpcargo_driver', (array) $current_user->roles );

        if ( ! empty( $_GET['wpcargo_tiendaname'] ) && $is_admin_or_driver ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $this->custom_where_conds[] = $wpdb->prepare(
                "EXISTS (
                    SELECT 1 FROM {$wpdb->postmeta} pm_mc
                    WHERE pm_mc.post_id = {$wpdb->posts}.ID
                      AND pm_mc.meta_key = 'wpcargo_tiendaname'
                      AND pm_mc.meta_value = %s
                )",
                sanitize_text_field( $_GET['wpcargo_tiendaname'] )
            );
        }

        // ── Celular ───────────────────────────────────────────────────────
        if ( ! empty( $_GET['celular_destinatario'] ) && $is_admin_or_driver ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $this->custom_where_conds[] = $wpdb->prepare(
                "EXISTS (
                    SELECT 1 FROM {$wpdb->postmeta} pm_cl
                    WHERE pm_cl.post_id = {$wpdb->posts}.ID
                      AND pm_cl.meta_key = 'wpcargo_receiver_phone'
                      AND pm_cl.meta_value = %s
                )",
                sanitize_text_field( $_GET['celular_destinatario'] )
            );
        }

        // ── Motorizado Recojo ─────────────────────────────────────────────
        if ( ! empty( $_GET['wpcargo_motorizo_recojo'] ) && $is_admin_or_driver ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $this->custom_where_conds[] = $wpdb->prepare(
                "EXISTS (
                    SELECT 1 FROM {$wpdb->postmeta} pm_mr
                    WHERE pm_mr.post_id = {$wpdb->posts}.ID
                      AND pm_mr.meta_key = 'wpcargo_motorizo_recojo'
                      AND pm_mr.meta_value = %s
                )",
                intval( $_GET['wpcargo_motorizo_recojo'] )
            );
        }

        // ── Motorizado Entrega ────────────────────────────────────────────
        if ( ! empty( $_GET['wpcargo_motorizo_entrega'] ) && $is_admin_or_driver ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $this->custom_where_conds[] = $wpdb->prepare(
                "EXISTS (
                    SELECT 1 FROM {$wpdb->postmeta} pm_me
                    WHERE pm_me.post_id = {$wpdb->posts}.ID
                      AND pm_me.meta_key = 'wpcargo_motorizo_entrega'
                      AND pm_me.meta_value = %s
                )",
                intval( $_GET['wpcargo_motorizo_entrega'] )
            );
        }

        // ── Distrito de Entrega ───────────────────────────────────────────────
        if ( ! empty( $_GET['distrito_destino'] ) && $is_admin_or_driver ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $this->custom_where_conds[] = $wpdb->prepare(
                "EXISTS (
                    SELECT 1 FROM {$wpdb->postmeta} pm_dd
                    WHERE pm_dd.post_id = {$wpdb->posts}.ID
                      AND pm_dd.meta_key = 'wpcargo_distrito_destino'
                      AND pm_dd.meta_value = %s
                )",
                sanitize_text_field( $_GET['distrito_destino'] )
            );
        }
        // ── Cliente (Marca por Nombre) ────────────────────────────────────
        if ( ! empty( $_GET['filter_wpcargoclient'] ) && $is_admin_or_driver ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $this->custom_where_conds[] = $wpdb->prepare(
                "{$wpdb->posts}.ID IN (
                    SELECT pm_cl.post_id FROM {$wpdb->postmeta} pm_cl
                    WHERE pm_cl.meta_key = 'registered_shipper'
                      AND pm_cl.meta_value = %s
                )",
                intval( $_GET['filter_wpcargoclient'] )
            );
        }

        if ( ! empty( $this->custom_where_conds ) ) {
            add_filter( 'posts_where', [ $this, 'inject_custom_where' ], 99, 2 );
        }

        return $args;
    }

    /**
     * Inyecta las condiciones EXISTS en el WHERE de WP_Query.
     * Se auto-elimina tras la primera ejecución para no afectar otras queries.
     */
    public function inject_custom_where( string $where, \WP_Query $query ): string {
        if ( $query->get( 'post_type' ) !== 'wpcargo_shipment' ) {
            return $where;
        }
        remove_filter( 'posts_where', [ $this, 'inject_custom_where' ], 99 );

        $original_where = $where;
        
        foreach ( $this->custom_where_conds as $cond ) {
            $where .= " AND ({$cond})";
        }


        return $where;
    }

    /* ── Helpers ─────────────────────────────────────────────────────────── */

    private function get_distritos(): array {
        global $wpdb;
        $cached = get_transient( 'merc_distritos_list' );
        if ( $cached !== false ) return $cached;
        $rows = $wpdb->get_col(
            "SELECT DISTINCT meta_value FROM {$wpdb->postmeta}
             WHERE meta_key = 'wpcargo_distrito_destino'
             AND meta_value != ''
             ORDER BY meta_value ASC"
        );
        $distritos = array_filter( array_map( 'trim', $rows ) );
        usort( $distritos, 'strnatcasecmp' );
        set_transient( 'merc_distritos_list', $distritos, 6 * HOUR_IN_SECONDS );
        return $distritos;
    }

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
        set_transient( 'merc_marcas_list', $marcas, 30 * MINUTE_IN_SECONDS );
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
        set_transient( 'merc_celulares_list', $celulares, 30 * MINUTE_IN_SECONDS );
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

} // End if ( ! class_exists( 'MERC_Shipment_Filters' ) )

if ( class_exists( 'MERC_Shipment_Filters' ) ) {
    new MERC_Shipment_Filters();
}






