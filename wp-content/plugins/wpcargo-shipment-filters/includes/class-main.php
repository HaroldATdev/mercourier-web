<?php

namespace WPCargo_Shipment_Filters;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Main {

    /**
     * Constructor
     */
    public function __construct() {
        // Load dependencies
        $this->load_dependencies();
    }

    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        require_once WPCARGO_SF_DIR . 'includes/filters.php';
        require_once WPCARGO_SF_DIR . 'includes/filters-ui.php';
        require_once WPCARGO_SF_DIR . 'includes/scripts.php';
    }

    /**
     * Run the plugin
     */
    public function run() {
        // Initialize filters
        $filters = new Filters();
        $filters->register_hooks();

        // Initialize UI
        $ui = new Filters_UI();
        $ui->register_hooks();

        // Initialize scripts
        $scripts = new Scripts();
        $scripts->register_hooks();

        // Log plugin loaded
        error_log( '[WPCargo Shipment Filters] Filters initialized successfully' );
    }
}
