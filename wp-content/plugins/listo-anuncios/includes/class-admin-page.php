<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class LA_Admin_Page {

    public function __construct() {
        add_action( 'admin_menu',            [ $this, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_la_save_web',       [ $this, 'ajax_save_web' ] );
        add_action( 'wp_ajax_la_save_panel',     [ $this, 'ajax_save_panel' ] );
        add_action( 'wp_ajax_la_delete_web',     [ $this, 'ajax_delete_web' ] );
        add_action( 'wp_ajax_la_delete_panel',   [ $this, 'ajax_delete_panel' ] );

        add_filter( 'wpcfe_menu_items',    [ $this, 'agregar_menu_wpcargo' ] );
        add_action( 'wpcfe_page_content',  [ $this, 'render_pagina_frontend' ] );
        add_action( 'wp_enqueue_scripts',  [ $this, 'enqueue_assets_frontend' ] );
    }

    public function register_menu() {
        add_menu_page(
            'Anuncios',
            'Anuncios',
            'manage_options',
            'listo-anuncios',
            [ $this, 'render_page' ],
            'dashicons-megaphone',
            30
        );
    }

    public function agregar_menu_wpcargo( $items ) {
        if ( ! current_user_can( 'manage_options' ) ) return $items;
        $items['listo-anuncios'] = [
            'title' => __( 'Anuncios', 'listo-anuncios' ),
            'icon'  => 'dashicons-megaphone',
            'slug'  => 'listo-anuncios',
        ];
        return $items;
    }

    public function render_pagina_frontend( $slug ) {
        if ( $slug !== 'listo-anuncios' ) return;
        if ( ! current_user_can( 'manage_options' ) ) {
            echo '<p>No tienes permisos para ver esta sección.</p>';
            return;
        }
        wp_enqueue_media();
        $data_web   = $this->get_data( LA_OPTION_WEB );
        $data_panel = $this->get_data( LA_OPTION_PANEL );
        include LA_PATH . 'templates/admin-page.php';
    }

    public function enqueue_assets( $hook ) {
        if ( $hook !== 'toplevel_page_listo-anuncios' ) return;
        $this->cargar_assets();
    }

    public function enqueue_assets_frontend() {
        if ( ! is_user_logged_in() ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;

        global $post;
        $fe_page = get_option( 'wpcfe_dashboard_page_id' );
        if ( ! $fe_page || ! is_page( $fe_page ) ) return;
        if ( empty( $_GET['wpcfe_page'] ) && strpos( $_SERVER['REQUEST_URI'], 'listo-anuncios' ) === false ) return;

        wp_enqueue_media();
        $this->cargar_assets();
    }

    private function cargar_assets() {
        wp_enqueue_style( 'la-admin-style', LA_URL . 'assets/css/admin.css', [], LA_VERSION );
        wp_enqueue_script( 'la-admin-script', LA_URL . 'assets/js/admin.js', [ 'jquery' ], LA_VERSION, true );
        wp_localize_script( 'la-admin-script', 'LA_Admin', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'la_nonce' ),
        ]);
    }

    private function get_data( $option ) {
        return get_option( $option, [
            'image_url' => '',
            'image_id'  => 0,
            'version'   => 0,
            'activo'    => false,
        ]);
    }

    // ── Guardar web ───────────────────────────────────────────────────────
    public function ajax_save_web() {
        $this->ajax_save( LA_OPTION_WEB );
    }

    // ── Guardar panel ─────────────────────────────────────────────────────
    public function ajax_save_panel() {
        $this->ajax_save( LA_OPTION_PANEL );
    }

    private function ajax_save( $option ) {
        check_ajax_referer( 'la_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Sin permisos.' );

        $image_id  = intval( $_POST['image_id'] ?? 0 );
        $image_url = esc_url_raw( $_POST['image_url'] ?? '' );

        if ( ! $image_id || ! $image_url ) wp_send_json_error( 'Datos incompletos.' );

        $data              = $this->get_data( $option );
        $data['image_id']  = $image_id;
        $data['image_url'] = $image_url;
        $data['activo']    = true;
        $data['version']   = intval( $data['version'] ?? 0 ) + 1;

        update_option( $option, $data );
        wp_send_json_success( [ 'version' => $data['version'], 'image_url' => $image_url ] );
    }

    // ── Eliminar web ──────────────────────────────────────────────────────
    public function ajax_delete_web() {
        $this->ajax_delete( LA_OPTION_WEB );
    }

    // ── Eliminar panel ────────────────────────────────────────────────────
    public function ajax_delete_panel() {
        $this->ajax_delete( LA_OPTION_PANEL );
    }

    private function ajax_delete( $option ) {
        check_ajax_referer( 'la_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Sin permisos.' );

        $data              = $this->get_data( $option );
        $data['image_id']  = 0;
        $data['image_url'] = '';
        $data['activo']    = false;
        update_option( $option, $data );

        wp_send_json_success();
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Acceso denegado.' );
        $data_web   = $this->get_data( LA_OPTION_WEB );
        $data_panel = $this->get_data( LA_OPTION_PANEL );
        include LA_PATH . 'templates/admin-page.php';
    }
}
