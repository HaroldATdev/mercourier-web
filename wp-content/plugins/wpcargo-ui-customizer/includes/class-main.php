<?php

namespace WPCargo_UI_Customizer;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Main {
    /**
     * Run the plugin
     */
    public function run() {
        // Initialize menus customizer
        $menus = new Menus();
        $menus->init();

        // Initialize tables customizer
        $tables = new Tables();
        $tables->init();

        // Initialize footer customizer
        $footer = new Footer();
        $footer->init();

        // Initialize styles
        $styles = new Styles();
        $styles->init();

        error_log( '[WPCargo UI Customizer] Plugin initialized' );
    }
}
