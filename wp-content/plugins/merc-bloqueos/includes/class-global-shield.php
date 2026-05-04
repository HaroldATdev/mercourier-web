<?php
/**
 * Escudo Global de Bloqueo
 */

if (!defined('ABSPATH')) {
    exit;
}

class Merc_Bloqueos_Global_Shield {
    public function __construct() {
        // Para Envíos Masivos y otras páginas que sí usan the_content()
        add_filter('the_content', [$this, 'filter_content'], 9999);
        
        // Para el Dashboard de WPCargo (que NO usa the_content() internamente)
        add_action('wpcfe_dashboard_before_content', [$this, 'inject_dashboard_banner'], 1);
    }

    private function is_user_blocked() {
        if (!is_user_logged_in() || merc_is_admin_user()) {
            return false;
        }
        $user_id = get_current_user_id();
        return get_user_meta($user_id, 'merc_bloqueo_total', true) === '1';
    }

    public function filter_content($content) {
        if (!$this->is_user_blocked()) return $content;

        global $post;
        if (!$post || !is_page()) return $content;

        $masivos_page_id = (int) get_option('wcmas_frontend_page_id');
        if ($post->ID === $masivos_page_id) {
            // Reemplazo total para Masivos (ocultando el resto con CSS por si acaso)
            return $this->get_blocked_html(true) . '<style>.merc-block-banner ~ * { display: none !important; }</style>';
        }

        // Si hay otros shortcodes logísticos (Almacén, Finanzas, etc.) y the_content es llamado
        if (
            strpos($post->post_content, '[wpcargo_') !== false || 
            strpos($post->post_content, '[wcmas_') !== false ||
            strpos($post->post_content, '[merc_almacen_productos]') !== false ||
            strpos($post->post_content, '[merc_panel_cliente]') !== false ||
            strpos($post->post_content, '[merc_finanzas_cliente]') !== false ||
            strpos($post->post_content, '[merc_devoluciones]') !== false
        ) {
            return $this->get_blocked_html(false) . $content;
        }

        return $content;
    }

    public function inject_dashboard_banner() {
        if (!$this->is_user_blocked()) return;

        global $post;
        $wpcfe_page_id = function_exists('wpcfe_admin_page') ? (int) wpcfe_admin_page() : (int) get_option('wpcfe_admin');
        if (!$post || $post->ID !== $wpcfe_page_id) {
            return;
        }

        $is_creation = (isset($_GET['wpcfe']) && $_GET['wpcfe'] === 'add');
        
        echo $this->get_blocked_html($is_creation);
        
        if ($is_creation) {
            // Magia CSS: Ocultamos todos los elementos hermanos que sigan después de nuestro banner
            // Esto oculta el formulario de crear envío sin romper el sidebar ni el header.
            echo '<style>.merc-block-banner ~ * { display: none !important; }</style>';
        }
    }

    private function get_blocked_html($is_full_replacement) {
        $margin = $is_full_replacement ? '50px auto' : '0 0 30px 0';
        $shadow = $is_full_replacement ? '0 10px 30px rgba(0,0,0,0.08)' : 'none';
        $border = $is_full_replacement ? 'border-top: 5px solid #dc3545;' : 'border-left: 5px solid #dc3545;';
        
        ob_start();
        ?>
        <div class="merc-block-banner" style="background: #fff; padding: <?php echo $is_full_replacement ? '40px' : '20px 25px'; ?>; border-radius: 6px; box-shadow: <?php echo $shadow; ?>; text-align: <?php echo $is_full_replacement ? 'center' : 'left'; ?>; max-width: <?php echo $is_full_replacement ? '600px' : '100%'; ?>; margin: <?php echo $margin; ?>; <?php echo $border; ?> display: flex; align-items: center; gap: 20px; border: 1px solid #f5c6cb; background-color: #f8d7da;">
            <div style="font-size: <?php echo $is_full_replacement ? '60px' : '40px'; ?>; line-height: 1; margin-bottom: <?php echo $is_full_replacement ? '20px' : '0'; ?>;">🚫</div>
            <div style="flex: 1;">
                <h3 style="font-size: <?php echo $is_full_replacement ? '24px' : '20px'; ?>; font-weight: 700; color: #721c24; margin: 0 0 10px 0; text-transform: uppercase;">Cuenta Suspendida</h3>
                <p style="font-size: 15px; color: #721c24; margin: 0; line-height: 1.5;">
                    Tu cuenta se encuentra temporalmente suspendida para crear nuevos envíos. Por favor, comunícate con administración para regularizar tu estado.
                </p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}



