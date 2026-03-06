<?php

namespace WPCargo_UI_Customizer;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Menus {
    /**
     * Initialize
     */
    public function init() {
        add_filter( 'wpcfe_after_sidebar_menus', [ $this, 'rename_driver_menu' ], 10 );
    }

    /**
     * Rename menu items for drivers
     * 
     * @param array $menu_items Menu items
     * @return array Modified menu items
     */
    public function rename_driver_menu( $menu_items ) {
        $current_user = wp_get_current_user();

        // Only for drivers
        if ( ! in_array( 'wpcargo_driver', (array) $current_user->roles ) ) {
            return $menu_items;
        }

        // Rename POD pickup and delivery menus
        if ( isset( $menu_items['wpcpod-pickup-route'] ) ) {
            $menu_items['wpcpod-pickup-route']['label'] = 'Recojo de mercadería';
        }

        if ( isset( $menu_items['wpcpod-route'] ) ) {
            $menu_items['wpcpod-route']['label'] = 'Entrega de mercadería';
        }

        return $menu_items;
    }
}
