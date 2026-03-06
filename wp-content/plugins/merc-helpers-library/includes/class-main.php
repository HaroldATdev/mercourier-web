<?php

namespace Merc_Helpers_Library;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Main {
    /**
     * Get helper version
     */
    public static function get_version() {
        return MERC_HELPERS_VERSION;
    }

    /**
     * Get helper directory
     */
    public static function get_dir() {
        return MERC_HELPERS_DIR;
    }

    /**
     * Get helper URL
     */
    public static function get_url() {
        return MERC_HELPERS_URL;
    }
}
