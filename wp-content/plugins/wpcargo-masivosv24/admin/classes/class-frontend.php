<?php
if ( ! defined('ABSPATH') ) exit;

class WCMAS_Frontend {

    public function __construct() {
        add_shortcode('wcmas-masivos',          [$this, 'render_shortcode']);
        add_filter('wpcfe_after_sidebar_menus', [$this, 'sidebar_item'], 29, 1);
    }

    /** Añadir item al sidebar del dashboard de WPCargo */
    public function sidebar_item( array $menu ): array {
        // Visible para cualquier usuario que pueda crear envíos
        if ( ! wcmas_puede_crear() ) return $menu;
        $menu['wcmas-masivos'] = [
            'page-id'   => wcmas_get_frontend_page_id(),
            'label'     => 'Envíos Masivos',
            'permalink' => wcmas_frontend_url(),
            'icon'      => 'fa-table',
        ];
        return $menu;
    }

    public function render_shortcode(): string {
        if ( ! wcmas_puede_crear() ) {
            return '<div class="alert alert-warning"><i class="fa fa-lock mr-2"></i>Acceso restringido.</div>';
        }
        ob_start();
        $es_admin  = wcmas_es_admin();
        $columnas  = WCMAS_Columnas::obtener_activas();
        $filas_init= max(5, intval(get_option('wcmas_filas_default', 10)));
        $nonce     = wp_create_nonce('wcmas_procesar_nonce');
        // Select de usuarios solo para admins
        $usuarios  = $es_admin ? wcmas_get_usuarios_select() : [];
        $page_url  = wcmas_frontend_url();
        // Historial: admins ven todos, clientes solo los suyos
        $historial = $es_admin
            ? WCMAS_Historial::obtener(5, 0, 0)
            : WCMAS_Historial::obtener(5, 0, get_current_user_id());
        wcmas_tpl('frontend/grilla.tpl.php', compact('columnas','filas_init','nonce','es_admin','usuarios','page_url','historial'));
        return ob_get_clean();
    }
}

new WCMAS_Frontend();
