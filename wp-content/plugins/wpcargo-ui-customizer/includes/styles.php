<?php

namespace WPCargo_UI_Customizer;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Styles {
    /**
     * Initialize
     */
    public function init() {
        add_action( 'wp_head', [ $this, 'global_styles' ] );
        add_action( 'wp_footer', [ $this, 'global_scripts' ], 1 );
    }

    /**
     * Global CSS styles
     */
    public function global_styles() {
        ?>
        <style>
        /* Hide financial sidebar badge */
        .merc-sidebar-badge {
            display: none !important;
        }

        /* Make delivery tables responsive */
        .merc-entregas-table {
            display: block;
            overflow-x: auto;
            white-space: nowrap;
            width: 100%;
        }

        /* Hide Location field from shipment form */
        label[for="location"],
        #location,
        .status_location,
        input[name="location"],
        .form-group:has(#location),
        .form-group:has(.status_location),
        .form-group:has(label[for="location"]),
        div:has(> label[for="location"]),
        div:has(> #location) {
            display: none !important;
        }
        </style>
        <?php
    }

    /**
     * Global JavaScript - Ensure body overflow and dropdown fixes
     */
    public function global_scripts() {
        ?>
        <script>
        (function() {
            // Ensure body always has overflow auto
            document.body.style.overflow = 'auto';

            // Fix for Bootstrap dropdowns - force close manually
            document.addEventListener('click', function(e) {
                // Ignore clicks in payment method area
                if (e.target.closest('#payment-methods-list') || e.target.closest('.method-selector')) {
                    return;
                }

                // Check if click is on a dropdown button
                var dropdownBtn = e.target.closest('[data-toggle="dropdown"]');

                if (dropdownBtn) {
                    var dropdownMenu = dropdownBtn.nextElementSibling;
                    if (dropdownMenu && dropdownMenu.classList.contains('dropdown-menu')) {
                        if (dropdownMenu.classList.contains('show')) {
                            e.preventDefault();
                            e.stopPropagation();
                            dropdownMenu.classList.remove('show');
                            dropdownBtn.setAttribute('aria-expanded', 'false');
                            return false;
                        }
                    }
                }

                // Close all other dropdowns if clicking outside
                var clickedInsideDropdown = e.target.closest('.dropdown-menu');
                var clickedInsidePaymentMethod = e.target.closest('.method-selector');

                if (!clickedInsideDropdown && !dropdownBtn && !clickedInsidePaymentMethod) {
                    document.querySelectorAll('.dropdown-menu.show').forEach(function(menu) {
                        // Don't close if part of payment method system
                        if (menu.closest('#payment-methods-list')) {
                            return;
                        }

                        menu.classList.remove('show');
                        var btn = menu.previousElementSibling;
                        if (btn) {
                            btn.setAttribute('aria-expanded', 'false');
                        }
                    });
                }
            }, true);

            // Verify body never has overflow hidden
            setInterval(function() {
                if (document.body.style.overflow === 'hidden') {
                    document.body.style.overflow = 'auto';
                }
            }, 500);
        })();
        </script>
        <?php
    }
}
