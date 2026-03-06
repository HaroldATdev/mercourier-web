<?php
/**
 * Bloqueos y restricciones de edición según estado
 * 
 * @package merc-shipment-container-management
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Bloquear edición cuando estado es ENTREGADO
 */
add_filter('user_has_cap', 'merc_bloquear_edicion_entregado', 10, 4);
function merc_bloquear_edicion_entregado($allcaps, $caps, $args, $user) {
    if (!is_admin()) {
        return $allcaps;
    }
    
    if (isset($args[0]) && $args[0] === 'edit_post' && !empty($args[2])) {
        $post_id = $args[2];
        
        if (get_post_type($post_id) === 'wpcargo_shipment') {
            $estado = strtoupper(trim(get_post_meta($post_id, 'wpcargo_status', true)));
            
            $estados_bloqueados = array('ENTREGADO', 'RECOGIDO', 'NO RECOGIDO', 'ANULADO', 'REPROGRAMADO', 'NO RECIBIDO');
            
            foreach ($estados_bloqueados as $estado_bloqueado) {
                if (stripos($estado, $estado_bloqueado) !== false) {
                    $allcaps['edit_post'] = false;
                    $allcaps['edit_posts'] = false;
                    break;
                }
            }
        }
    }
    
    return $allcaps;
}

/**
 * Agregar aviso visual cuando envío está ENTREGADO
 */
add_action('edit_form_after_title', 'merc_aviso_entregado_no_editable');
function merc_aviso_entregado_no_editable($post) {
    if ($post->post_type !== 'wpcargo_shipment') {
        return;
    }
    
    $estado = strtoupper(trim(get_post_meta($post->ID, 'wpcargo_status', true)));
    
    $estados_bloqueados = array('ENTREGADO', 'RECOGIDO', 'NO RECOGIDO', 'ANULADO', 'REPROGRAMADO', 'NO RECIBIDO');
    $is_bloqueado = false;
    
    foreach ($estados_bloqueados as $estado_bloqueado) {
        if (stripos($estado, $estado_bloqueado) !== false) {
            $is_bloqueado = true;
            break;
        }
    }
    
    if ($is_bloqueado) {
        ?>
        <div class="notice notice-warning" style="margin: 15px 0; padding: 12px; border-left: 4px solid #ffb900;">
            <p style="margin: 0; font-weight: bold;">
                🔒 Este envío está en estado <strong><?php echo esc_html($estado); ?></strong> y no puede ser editado.
            </p>
        </div>
        <style>
            #post-body input:not([type="hidden"]),
            #post-body select,
            #post-body textarea {
                pointer-events: none;
                opacity: 0.6;
                background-color: #f5f5f5 !important;
            }
            #publish, #save-post, .submitdelete {
                display: none !important;
            }
        </style>
        <?php
    }
}

/**
 * Bloquear cambios a estados finales
 */
add_action('wp_ajax_merc_actualizar_estado', 'merc_bloquear_estados_finales', 0);
add_action('wp_ajax_nopriv_merc_actualizar_estado', 'merc_bloquear_estados_finales', 0);

function merc_bloquear_estados_finales() {
    if (!is_user_logged_in()) {
        wp_send_json_error('No autorizado');
    }

    $shipment_id = intval($_POST['shipment_id'] ?? 0);

    if (!$shipment_id) {
        wp_send_json_error('Shipment inválido');
    }

    $estado_actual = strtoupper(trim(
        get_post_meta($shipment_id, 'wpcargo_status', true)
    ));

    $estados_finales = array(
        'ENTREGADO',
        'RECOGIDO',
        'NO RECOGIDO',
        'ANULADO',
        'REPROGRAMADO',
        'NO RECIBIDO'
    );

    if (in_array($estado_actual, $estados_finales, true)) {
        wp_send_json_error(array(
            'message' => '⛔ Este envío está en estado ' . $estado_actual . ' y no puede ser modificado.'
        ));
    }
}
