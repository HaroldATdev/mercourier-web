<?php
/**
 * Plugin Name: Listo Anuncios
 * Plugin URI:  https://listocourier.com
 * Description: Módulo de anuncios con pop-up para la página web y el panel de WPCargo.
 * Version:     2.0.0
 * Author:      Listo Courier
 * Text Domain: listo-anuncios
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'LA_VERSION',       '2.0.0' );
define( 'LA_PATH',          plugin_dir_path( __FILE__ ) );
define( 'LA_URL',           plugin_dir_url( __FILE__ ) );
define( 'LA_OPTION_WEB',    'listo_anuncios_web' );
define( 'LA_OPTION_PANEL',  'listo_anuncios_panel' );

require_once LA_PATH . 'includes/class-admin-page.php';
require_once LA_PATH . 'includes/class-popup-frontend.php';

// ── Activación ────────────────────────────────────────────────────────────────
register_activation_hook( __FILE__, 'la_activate' );
function la_activate() {
    $defaults = [
        'image_url' => '',
        'image_id'  => 0,
        'version'   => 0,
        'activo'    => false,
    ];
    if ( ! get_option( LA_OPTION_WEB ) )   add_option( LA_OPTION_WEB,   $defaults );
    if ( ! get_option( LA_OPTION_PANEL ) ) add_option( LA_OPTION_PANEL, $defaults );
    la_get_frontend_page_id();
}

// ── Desinstalación ────────────────────────────────────────────────────────────
register_uninstall_hook( __FILE__, 'la_uninstall' );
function la_uninstall() {
    $page_id = get_option( 'la_frontend_page_id' );
    if ( $page_id ) {
        wp_delete_post( $page_id, true );
        delete_option( 'la_frontend_page_id' );
    }
    delete_option( LA_OPTION_WEB );
    delete_option( LA_OPTION_PANEL );
}

// ── Crear/recuperar la página del módulo ──────────────────────────────────────
function la_get_frontend_page_id(): int {
    $saved = (int) get_option( 'la_frontend_page_id' );
    if ( $saved && get_post_status( $saved ) === 'publish' ) {
        return $saved;
    }

    global $wpdb;
    $id = (int) $wpdb->get_var(
        "SELECT ID FROM {$wpdb->prefix}posts
         WHERE post_content LIKE '%[listo-anuncios]%'
           AND post_status = 'publish' LIMIT 1"
    );

    if ( ! $id ) {
        $id = (int) wp_insert_post([
            'post_title'   => 'Anuncios',
            'post_content' => '[listo-anuncios]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ]);
    }

    if ( $id ) {
        update_post_meta( $id, '_wp_page_template', 'dashboard.php' );
        update_post_meta( $id, 'wpcfe_menu_icon', 'fa fa-bullhorn mr-3' );
        update_option( 'la_frontend_page_id', $id, false );
    }

    return $id;
}

// ── Shortcode [listo-anuncios] ────────────────────────────────────────────────
add_shortcode( 'listo-anuncios', 'la_render_shortcode' );
function la_render_shortcode(): string {
    if ( ! current_user_can( 'manage_options' ) ) {
        return '<p>No tienes permisos para ver esta sección.</p>';
    }

    wp_enqueue_media();
    wp_enqueue_style( 'la-admin-style', LA_URL . 'assets/css/admin.css', [], LA_VERSION );
    wp_enqueue_script( 'la-admin-script', LA_URL . 'assets/js/admin.js', [ 'jquery' ], LA_VERSION, true );
    wp_localize_script( 'la-admin-script', 'LA_Admin', [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'la_nonce' ),
    ]);

    $data_web   = get_option( LA_OPTION_WEB,   [ 'image_url' => '', 'image_id' => 0, 'version' => 0, 'activo' => false ] );
    $data_panel = get_option( LA_OPTION_PANEL, [ 'image_url' => '', 'image_id' => 0, 'version' => 0, 'activo' => false ] );

    ob_start();
    include LA_PATH . 'templates/admin-page.php';
    return ob_get_clean();
}

// ── Init ──────────────────────────────────────────────────────────────────────
add_action( 'plugins_loaded', function() {
    new LA_Admin_Page();
    new LA_Popup_Frontend();
    la_get_frontend_page_id();
});
