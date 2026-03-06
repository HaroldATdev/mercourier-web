<?php

namespace WPCargo_UI_Customizer;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Footer {
    /**
     * Initialize
     */
    public function init() {
        add_filter( 'wpcfe_footer_credits', [ $this, 'custom_footer_text' ] );
    }

    /**
     * Customize footer text
     */
    public function custom_footer_text() {
        echo sprintf(
            'Copyright © %d - Diseñado por <a href="https://diffcode.net" target="_blank">DIFFCODE</a>',
            date( 'Y' )
        );
    }
}
