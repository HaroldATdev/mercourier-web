<?php

namespace WPCargo_Shipment_Filters;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Filters_UI {

    /**
     * Register hooks
     */
    public function register_hooks() {
        // Date filter UI
        add_action( 'wpcfe_after_shipment_filters', [ $this, 'render_date_filter' ], 10 );

        // Driver filters UI
        add_action( 'wpcfe_after_shipment_filters', [ $this, 'render_driver_filters' ], 101 );

        // Client search UI
        add_action( 'wpcfe_after_shipment_filters', [ $this, 'render_client_search' ], 20 );

        // Tiendaname filter UI
        add_action( 'wpcfe_after_shipment_filters', [ $this, 'render_tiendaname_filter' ], 30 );

        // Styles inline
        add_action( 'wp_head', [ $this, 'inline_styles' ] );
    }

    /**
     * Render date range filter
     */
    public function render_date_filter() {
        $default_date = date( 'Y-m-d' );

        $start_value = isset( $_GET['shipping_date_start'] )
            ? sanitize_text_field( $_GET['shipping_date_start'] )
            : $default_date;

        $end_value = isset( $_GET['shipping_date_end'] )
            ? sanitize_text_field( $_GET['shipping_date_end'] )
            : $default_date;

        ?>
        <div id="wpcfe-custom-shipping-date" class="form-group wpcfe-filter receiver-filter p-0 mx-1">
            <div class="md-form form-group">
                <strong><?php _e( 'Fecha de Envío', 'wpcargo-shipment-filters' ); ?></strong>

                <input id="shipping_date_start"
                       class="form-control daterange_picker start_date px-2 py-1 mx-2"
                       style="width: 110px; font-weight: 500;"
                       autocomplete="off"
                       name="shipping_date_start"
                       type="text"
                       value="<?php echo esc_attr( $start_value ); ?>"
                       placeholder="YYYY-MM-DD" />

                <input id="shipping_date_end"
                       class="form-control daterange_picker end_date px-2 py-1 mx-2"
                       style="width: 110px; font-weight: 500;"
                       autocomplete="off"
                       name="shipping_date_end"
                       type="text"
                       value="<?php echo esc_attr( $end_value ); ?>"
                       placeholder="YYYY-MM-DD" />
            </div>
        </div>
        <?php
    }

    /**
     * Render driver filters
     */
    public function render_driver_filters() {
        $current_user = wp_get_current_user();

        // Only show for non-clients
        if ( in_array( 'wpcargo_client', (array) $current_user->roles ) ) {
            return;
        }

        $value_recojo  = isset( $_GET['wpcargo_motorizo_recojo'] ) ? esc_attr( $_GET['wpcargo_motorizo_recojo'] ) : '';
        $value_entrega = isset( $_GET['wpcargo_motorizo_entrega'] ) ? esc_attr( $_GET['wpcargo_motorizo_entrega'] ) : '';

        $drivers = get_users( [
            'role'    => 'wpcargo_driver',
            'orderby' => 'display_name',
            'order'   => 'ASC',
        ] );

        ?>
        <!-- MOTORIZADO RECOJO -->
        <div class="form-group wpcfe-filter p-0 mx-1">
            <div class="md-form form-group" style="margin: 0;">
                <select name="wpcargo_motorizo_recojo" class="form-control form-control-sm wpcfe-select">
                    <option value="">Motorizado Recojo...</option>
                    <?php foreach ( $drivers as $driver ) : ?>
                        <option value="<?php echo esc_attr( $driver->ID ); ?>" <?php selected( $value_recojo, $driver->ID ); ?>>
                            <?php
                            $nombre = trim(
                                get_user_meta( $driver->ID, 'first_name', true ) . ' ' .
                                get_user_meta( $driver->ID, 'last_name', true )
                            );
                            echo esc_html( $nombre ?: $driver->display_name );
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- MOTORIZADO ENTREGA -->
        <div class="form-group wpcfe-filter p-0 mx-1">
            <div class="md-form form-group" style="margin: 0;">
                <select name="wpcargo_motorizo_entrega" class="form-control form-control-sm wpcfe-select">
                    <option value="">Motorizado Entrega...</option>
                    <?php foreach ( $drivers as $driver ) : ?>
                        <option value="<?php echo esc_attr( $driver->ID ); ?>" <?php selected( $value_entrega, $driver->ID ); ?>>
                            <?php
                            $nombre = trim(
                                get_user_meta( $driver->ID, 'first_name', true ) . ' ' .
                                get_user_meta( $driver->ID, 'last_name', true )
                            );
                            echo esc_html( $nombre ?: $driver->display_name );
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php
    }

    /**
     * Render client search filter
     */
    public function render_client_search() {
        ?>
        <script>
        jQuery(document).ready(function($) {

            function createSearchableClienteAssign() {
                var $orig = $('#prod-cliente-asignado');
                if ($orig.length === 0) return;
                if ($orig.data('enhanced')) return;

                var options = [];
                $orig.find('option').each(function() {
                    var $o = $(this);
                    options.push({ id: $o.val(), text: $o.text() });
                });

                var $wrapper = $('<div class="searchable-select-wrapper" style="position:relative;width:100%;"></div>');
                var $input   = $('<input type="text" class="searchable-select-input" placeholder="Buscar cliente..." autocomplete="off" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;">');
                var $list    = $('<div class="searchable-select-list" style="position:absolute;z-index:99999;top:100%;left:0;right:0;background:#fff;border:1px solid #ddd;max-height:220px;overflow:auto;display:none;border-radius:6px;margin-top:6px;"></div>');

                options.forEach(function(opt) {
                    if (!opt.text) return;
                    var $item = $('<div class="ssi-item" data-id="' + opt.id + '" style="padding:8px 10px;cursor:pointer;border-bottom:1px solid #f1f1f1;">' + opt.text + '</div>');
                    $item.on('click', function(e) {
                        e.preventDefault();
                        $input.val(opt.text);
                        $orig.val(opt.id).trigger('change');
                        $list.hide();
                    });
                    $list.append($item);
                });

                $wrapper.append($input).append($list);
                $orig.after($wrapper).hide();
                $orig.data('enhanced', true);

                function filterList(q) {
                    var qq  = (q || '').toLowerCase().trim();
                    var any = false;
                    $list.find('.ssi-item').each(function() {
                        var $it = $(this);
                        if ($it.text().toLowerCase().indexOf(qq) !== -1) {
                            $it.show(); any = true;
                        } else {
                            $it.hide();
                        }
                    });
                    if (any) $list.show(); else $list.hide();
                }

                $input.on('input', function() { filterList($(this).val()); });
                $input.on('focus', function() { filterList($(this).val()); });

                $(document).on('click.searchableCliente', function(e) {
                    if (!$(e.target).closest($wrapper).length) { $list.hide(); }
                });

                var cur   = $orig.val();
                var found = cur ? options.find(function(o){ return o.id == cur; }) : null;
                if (found) $input.val(found.text);
            }

            window.createSearchableClienteAssign = createSearchableClienteAssign;
            createSearchableClienteAssign();

        });
        </script>
        <?php
    }

    /**
     * Render tiendaname filter
     */
    public function render_tiendaname_filter() {
        $current_value = isset( $_GET['wpcargo_tiendaname'] ) ? sanitize_text_field( $_GET['wpcargo_tiendaname'] ) : '';

        // Get all unique tiendanames
        global $wpdb;
        $tiendanames = $wpdb->get_col(
            "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} 
            WHERE meta_key = 'wpcargo_tiendaname' 
            AND meta_value != '' 
            ORDER BY meta_value ASC"
        );

        if ( empty( $tiendanames ) ) {
            return;
        }

        ?>
        <div id="tiendaname-filter-field" class="form-group wpcfe-filter p-0 mx-1">
            <div class="md-form form-group">
                <select id="wpcargo-tiendaname-search" name="wpcargo_tiendaname" class="form-control form-control-sm wpcfe-select">
                    <option value="">Todas las tiendas</option>
                    <?php foreach ( $tiendanames as $tienda ) : ?>
                        <option value="<?php echo esc_attr( $tienda ); ?>" <?php selected( $current_value, $tienda ); ?>>
                            <?php echo esc_html( $tienda ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php
    }

    /**
     * Inline CSS styles
     */
    public function inline_styles() {
        ?>
        <style>
        /* Tiendaname filter styling */
        #tiendaname-filter-field .select2-container--default .select2-selection--single {
            height: 37px !important;
            background: #fff;
        }

        #tiendaname-filter-field .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 36px !important;
            font-size: 14px;
        }

        #tiendaname-filter-field .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px !important;
            right: 8px !important;
        }

        #tiendaname-filter-field .select2-container--default .select2-selection--single .select2-selection__placeholder {
            color: #6c757d !important;
        }

        #tiendaname-filter-field .select2-container--default .select2-selection--single .select2-selection__clear {
            margin-right: 20px !important;
        }

        #tiendaname-filter-field .md-form {
            margin-bottom: 0 !important;
        }

        .select2-container--default .select2-results__option {
            font-size: 14px !important;
        }
        </style>
        <?php
    }
}
