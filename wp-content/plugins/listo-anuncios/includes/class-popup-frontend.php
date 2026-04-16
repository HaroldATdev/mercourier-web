<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class LA_Popup_Frontend {

    public function __construct() {
        add_filter( 'wpcfe_after_sidebar_menus', [ $this, 'sidebar_item' ], 30, 1 );
        add_action( 'wp_enqueue_scripts',        [ $this, 'enqueue_popup_assets' ] );
        add_action( 'wp_footer',                 [ $this, 'inline_popup_script' ] );
    }

    public function sidebar_item( array $menu ): array {
        if ( ! current_user_can( 'manage_options' ) ) return $menu;
        $menu['la-anuncios'] = [
            'page-id'   => la_get_frontend_page_id(),
            'label'     => 'Anuncios',
            'permalink' => get_permalink( la_get_frontend_page_id() ),
            'icon'      => 'fa-bullhorn',
        ];
        return $menu;
    }

    public function enqueue_popup_assets() {
        if ( $this->es_pagina_anuncios() ) return;

        $data = $this->get_contexto_data();
        if ( empty( $data['activo'] ) || empty( $data['image_url'] ) ) return;

        wp_enqueue_script(
            'la-popup-script',
            LA_URL . 'assets/js/popup.js',
            [ 'jquery' ],
            LA_VERSION,
            true
        );

        wp_localize_script( 'la-popup-script', 'LA_Popup', [
            'version'   => intval( $data['version'] ),
            'image_url' => esc_url( $data['image_url'] ),
        ]);
    }

    public function inline_popup_script() {
        static $ya_ejecutado = false;
        if ( $ya_ejecutado ) return;

        if ( wp_script_is( 'la-popup-script', 'done' ) )     return;
        if ( wp_script_is( 'la-popup-script', 'enqueued' ) ) return;
        if ( wp_script_is( 'la-popup-script', 'to_do' ) )    return;

        if ( $this->es_pagina_anuncios() ) return;

        $data = $this->get_contexto_data();
        if ( empty( $data['activo'] ) || empty( $data['image_url'] ) ) return;

        $ya_ejecutado = true;

        $version   = intval( $data['version'] );
        $image_url = esc_url( $data['image_url'] );

        echo "<script>
        var LA_Popup = {
            version: '{$version}',
            image_url: '{$image_url}'
        };
        </script>";

        $js_path = LA_PATH . 'assets/js/popup.js';
        if ( file_exists( $js_path ) ) {
            echo '<script>' . file_get_contents( $js_path ) . '</script>';
        }
    }

    /**
     * Decide qué anuncio mostrar según el contexto:
     * - Panel de WPCargo → anuncio panel (solo no-admins)
     * - Resto → anuncio web (todos los visitantes)
     */
    private function get_contexto_data(): array {
        $es_panel = $this->es_dashboard_wpcargo();

        if ( $es_panel ) {
            // En el panel solo mostramos a clientes (no admins)
            if ( current_user_can( 'manage_options' ) ) return [];
            return get_option( LA_OPTION_PANEL, $this->defaults() );
        }

        // Página web pública — todos los visitantes
        return get_option( LA_OPTION_WEB, $this->defaults() );
    }

    private function es_dashboard_wpcargo(): bool {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        if ( strpos( $request_uri, 'wpcfe=dashboard' ) !== false ) return true;
        if ( strpos( $request_uri, '/dashboard/' ) !== false && is_user_logged_in() ) return true;

        $wpcargo_page_id = get_option( 'wpcfe_dashboard_page_id' );
        if ( $wpcargo_page_id && is_page( $wpcargo_page_id ) ) return true;

        return false;
    }

    private function es_pagina_anuncios(): bool {
        $page_id = (int) get_option( 'la_frontend_page_id' );
        if ( ! $page_id ) return false;

        if ( is_page( $page_id ) ) return true;

        $current_url = trailingslashit( home_url( $_SERVER['REQUEST_URI'] ) );
        $page_url    = trailingslashit( get_permalink( $page_id ) );

        return $page_url && $current_url === $page_url;
    }

    private function defaults(): array {
        return [
            'image_url' => '',
            'image_id'  => 0,
            'version'   => 0,
            'activo'    => false,
        ];
    }
}
