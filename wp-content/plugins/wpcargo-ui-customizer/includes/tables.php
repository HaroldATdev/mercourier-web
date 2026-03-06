<?php

namespace WPCargo_UI_Customizer;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Tables {
    /**
     * Initialize
     */
    public function init() {
        // Remove table columns
        add_action( 'init', [ $this, 'manipulate_shipment_columns' ], 20 );
        add_action( 'plugins_loaded', [ $this, 'manipulate_shipment_columns' ], 20 );

        // Manipulate table via JavaScript
        add_action( 'wp_footer', [ $this, 'shipment_table_js' ], 15 );
    }

    /**
     * Remove unwanted shipment table columns
     */
    public function manipulate_shipment_columns() {
        // Remove Shipment Type Column
        remove_action( 'wpcfe_shipment_table_header', 'wpcfe_shipment_table_header_type', 25 );
        remove_action( 'wpcfe_shipment_table_data', 'wpcfe_shipment_table_data_type', 25 );

        // Remove Shipper / Receiver Column
        remove_action( 'wpcfe_shipment_after_tracking_number_header', 'wpcfe_shipper_receiver_shipment_header_callback', 25 );
        remove_action( 'wpcfe_shipment_after_tracking_number_data', 'wpcfe_shipper_receiver_shipment_data_callback', 25 );

        // Remove Container Column
        remove_action( 'wpcfe_shipment_table_header', 'wpcsc_shipment_container_table_header', 10 );
        remove_action( 'wpcfe_shipment_table_data', 'wpcsc_shipment_container_table_data', 10 );
    }

    /**
     * JavaScript to manipulate shipment table columns
     */
    public function shipment_table_js() {
        ?>
        <script>
        (function($){
            $(function(){
                var $table = $('#shipment-list');
                if (!$table.length) return;

                function findThIndexByText($ths, text){
                    var idx = -1;
                    $ths.each(function(i){
                        var t = $(this).text().toUpperCase().trim();
                        if (t.indexOf(text.toUpperCase()) !== -1) { idx = i; return false; }
                    });
                    return idx;
                }

                function moveColumn(afterText, moveText){
                    var $ths = $table.find('thead tr:first th');
                    var afterIdx = findThIndexByText($ths, afterText);
                    var moveIdx = findThIndexByText($ths, moveText);
                    if (afterIdx === -1 || moveIdx === -1 || moveIdx === afterIdx+1) return;

                    var $moveTh = $ths.eq(moveIdx);
                    var $afterTh = $ths.eq(afterIdx);
                    $moveTh.insertAfter($afterTh);

                    // Move corresponding TD in each row
                    $table.find('tbody tr').each(function(){
                        var $cells = $(this).find('td');
                        var $moveTd = $cells.eq(moveIdx);
                        var $afterTd = $cells.eq(afterIdx);
                        if ($moveTd.length && $afterTd.length) {
                            $moveTd.insertAfter($afterTd);
                        }
                    });
                }

                // Move 'Estado' column after 'Cambio de Producto'
                moveColumn('Cambio de Producto', 'Estado');

                // Move tracking number column after 'Motorizado Entrega'
                var trackingCandidates = [
                    'Número de seguimiento',
                    'Número',
                    'Seguimiento',
                    'Tracking',
                    'Tracking Number',
                    'Número de tracking',
                    'Número de Tracking'
                ];
                trackingCandidates.forEach(function(candidate){
                    moveColumn('Motorizado Entrega', candidate);
                });
            });
        })(jQuery);
        </script>
        <?php
    }
}
