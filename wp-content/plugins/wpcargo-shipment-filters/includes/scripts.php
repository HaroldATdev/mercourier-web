<?php

namespace WPCargo_Shipment_Filters;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Scripts {

    /**
     * Register hooks
     */
    public function register_hooks() {
        add_action( 'admin_footer', [ $this, 'admin_scripts' ] );
        add_action( 'wp_footer', [ $this, 'frontend_scripts' ] );
    }

    /**
     * Admin footer scripts
     */
    public function admin_scripts() {
        if ( ! is_user_logged_in() ) {
            return;
        }

        ?>
        <script>
        jQuery(function($){

            // Helper: number_format similar to PHP
            function number_format(number, decimals) {
                var n = Number(number);
                if (!isFinite(n)) n = 0;
                var prec = isNaN(decimals) ? 0 : Math.abs(decimals);
                var parts = n.toFixed(prec).split('.');
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                return parts.join('.');
            }
            
            // Get today in ISO format
            function getTodayIso() {
                var today = new Date();
                var yyyy = today.getFullYear();
                var mm = String(today.getMonth() + 1).padStart(2, '0');
                var dd = String(today.getDate()).padStart(2, '0');
                return yyyy + '-' + mm + '-' + dd;
            }
            
            // Auto-apply today's date filter if not set
            if ($('.wpcfe-dashboard').length > 0) {
                var urlParams = new URLSearchParams(window.location.search);
                var hasDateFilter = urlParams.has('shipping_date_start') || urlParams.has('shipping_date_end');
                
                if (!hasDateFilter) {
                    console.log('📅 No date filter, applying today automatically...');
                    var todayIso = getTodayIso();
                    
                    var currentUrl = window.location.href;
                    var separator = currentUrl.indexOf('?') > -1 ? '&' : '?';
                    var newUrl = currentUrl + separator + 'shipping_date_start=' + todayIso + '&shipping_date_end=' + todayIso;
                    
                    window.location.href = newUrl;
                }
            }

            // Make number_format available globally
            if (typeof window.number_format === 'undefined') {
                window.number_format = number_format;
            }
        });
        </script>
        <?php
    }

    /**
     * Frontend footer scripts
     */
    public function frontend_scripts() {
        if ( ! is_user_logged_in() ) {
            return;
        }

        ?>
        <script>
        (function(){
            // Make number_format available globally
            if (typeof window.number_format === 'undefined') {
                window.number_format = function(number, decimals) {
                    var n = Number(number);
                    if (!isFinite(n)) n = 0;
                    var prec = isNaN(decimals) ? 0 : Math.abs(decimals);
                    var parts = n.toFixed(prec).split('.');
                    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                    return parts.join('.');
                };
            }
        })();
        </script>
        <?php
    }
}
