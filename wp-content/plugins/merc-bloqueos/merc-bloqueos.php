<?php
/**
 * Plugin Name: Mercourier Bloqueos V2
 * Description: Control avanzado de horarios y calendarios para envíos Mercourier (Emprendedor, Agencia, Full Fitment).
 * Version: 2.0.1
 * Author: Mercourier
 */

if (!defined('ABSPATH')) {
    exit;
}

// Constantes
define('MERC_BLOQUEOS_VERSION', '2.0.1');
define('MERC_BLOQUEOS_DIR', plugin_dir_path(__FILE__));
define('MERC_BLOQUEOS_URL', plugin_dir_url(__FILE__));

// Función global de permisos
if (!function_exists('merc_is_admin_user')) {
    function merc_is_admin_user() {
        if (current_user_can('manage_options')) return true;
        $user = wp_get_current_user();
        if (in_array('wpcargo_admin', (array) $user->roles)) return true;
        return false;
    }
}

// Cargar módulos
require_once MERC_BLOQUEOS_DIR . 'includes/class-settings.php';
require_once MERC_BLOQUEOS_DIR . 'includes/class-block-logic.php';
require_once MERC_BLOQUEOS_DIR . 'includes/class-ajax.php';
require_once MERC_BLOQUEOS_DIR . 'includes/class-save-guard.php';
require_once MERC_BLOQUEOS_DIR . 'includes/class-user-controls.php';
require_once MERC_BLOQUEOS_DIR . 'includes/class-global-shield.php';

// Inicializar el plugin
function merc_bloqueos_init() {
    new Merc_Bloqueos_Settings();
    new Merc_Bloqueos_Ajax();
    new Merc_Bloqueos_Save_Guard();
    new Merc_Bloqueos_User_Controls();
    new Merc_Bloqueos_Global_Shield();
}
add_action('plugins_loaded', 'merc_bloqueos_init');

/**
 * Excluir el script de bloqueos de la optimización y minificación de LiteSpeed Cache.
 * Esto evita que el archivo JS externo se corrompa en el entorno de Hostinger.
 */
add_filter('litespeed_optm_js_excludes', function($excludes) {
    $excludes[] = 'calendar-block.js';
    return $excludes;
});

function merc_bloqueos_enqueue_scripts() {
    if (!is_user_logged_in()) {
        return;
    }

    $es_formulario = (
        ( isset( $_GET['wpcfe'] ) && in_array( $_GET['wpcfe'], [ 'add', 'update' ], true ) ) ||
        ( is_page() && ( has_shortcode( get_post_field( 'post_content', get_the_ID() ), 'wpcfe_shipment_form' ) ||
                         has_shortcode( get_post_field( 'post_content', get_the_ID() ), 'wpcargo_add_shipment' ) ) )
    );

    // Si no estamos en el formulario, no cargamos el script (como lo hacíamos en functions.php)
    if ( ! $es_formulario ) return;

    $version = filemtime(MERC_BLOQUEOS_DIR . 'assets/js/calendar-block.js');
    $js_url  = MERC_BLOQUEOS_URL . 'assets/js/calendar-block.js?ver=' . $version;
    $ajaxurl = admin_url('admin-ajax.php');

    ?>
    <script>var mercBloqueos = { ajax_url: '<?php echo esc_js( $ajaxurl ); ?>' };</script>
    <script data-no-optimize="1" data-no-minify="1" src="<?php echo esc_url( $js_url ); ?>"></script>
    <?php
}
add_action('wp_footer', 'merc_bloqueos_enqueue_scripts', 20);


