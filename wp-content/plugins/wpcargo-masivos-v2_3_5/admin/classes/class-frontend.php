<?php
if ( ! defined('ABSPATH') ) exit;

class WCMAS_Frontend {

    public function __construct() {
        add_shortcode('wcmas-masivos',          [$this, 'render_shortcode']);
        add_action('wpcfe_after_create_shipment', [$this, 'sidebar_item_html']);
    }

    /** Añadir item al sidebar del dashboard de WPCargo justo debajo de Crear Servicio */
    public function sidebar_item_html(): void {
        // Visible para cualquier usuario que pueda crear envíos
        if ( ! wcmas_puede_crear() ) return;
        
        $page_id = wcmas_get_frontend_page_id();
        $active_class = ( !isset($_GET['wpcfe']) && get_the_ID() == $page_id ) ? 'active' : '';
        ?>
        <a href="<?php echo esc_url(wcmas_frontend_url()); ?>" class="list-group-item waves-effect wcmas-masivos <?php echo $active_class; ?>"> 
            <i class="fa fa-table mr-3"></i>Crear envíos masivos
        </a>
        <?php
    }

    public function render_shortcode(): string {
        if ( ! wcmas_puede_crear() ) {
            return '<div class="alert alert-warning"><i class="fa fa-lock mr-2"></i>Acceso restringido.</div>';
        }
        // Encolar Select2 si el usuario es admin (necesario para el selector de clientes)
        if ( wcmas_es_admin() ) {
            wp_enqueue_style('wcmas-select2',
                'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
                [], '4.1.0');
            wp_enqueue_script('wcmas-select2',
                'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
                ['jquery'], '4.1.0', true);
                
            wp_add_inline_script('wcmas-select2', "
                jQuery(function($) {
                    if (typeof $.fn.select2 === 'undefined' || !document.getElementById('wcmas-shipper-select')) return;
                    $('#wcmas-shipper-select').select2({
                        placeholder: 'Buscar cliente...',
                        allowClear: true,
                        minimumInputLength: 1,
                        width: '320px',
                        ajax: {
                            url: WCMAS.ajax_url,
                            type: 'POST',
                            dataType: 'json',
                            delay: 300,
                            data: function(params) {
                                return { action: 'wcmas_buscar_clientes', nonce: WCMAS.nonce, q: params.term };
                            },
                            processResults: function(data) {
                                return { results: (data.results || []).map(function(c) {
                                    return { id: c.id, text: c.text };
                                })};
                            },
                            cache: true
                        }
                    });
                });
            ");
        }
        // Flatpickr — datepicker para columnas tipo 'date' (todos los usuarios)
        wp_enqueue_style('wcmas-flatpickr',
            'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
            [], '4.6.13');
        wp_enqueue_script('wcmas-flatpickr',
            'https://cdn.jsdelivr.net/npm/flatpickr',
            [], '4.6.13', true);
        wp_enqueue_script('wcmas-flatpickr-es',
            'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js',
            ['wcmas-flatpickr'], '4.6.13', true);
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



