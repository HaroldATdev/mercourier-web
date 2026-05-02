<?php
if (!defined('ABSPATH')) exit;

// AJAX: cliente sube voucher para pagar una deuda de liquidación (MARCA DEBE)
add_action('wp_ajax_merc_cliente_pagar_deuda', 'merc_cliente_pagar_deuda_ajax');
function merc_cliente_pagar_deuda_ajax() {
    if ( ! isset($_POST['nonce']) || ! wp_verify_nonce( $_POST['nonce'], 'merc_cliente_pagar' ) ) {
        wp_send_json_error(array('message'=>'Nonce inválido'));
    }
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    if ( $user_id <= 0 ) wp_send_json_error(array('message'=>'Usuario inválido'));
    $current = wp_get_current_user();
    if ( $current->ID !== $user_id && ! current_user_can('administrator') ) {
        wp_send_json_error(array('message'=>'Sin permisos'));
    }

    $liq_id = isset($_POST['liq_id']) ? sanitize_text_field($_POST['liq_id']) : '';
    if ( empty($liq_id) ) wp_send_json_error(array('message'=>'Liquidación inválida'));

    if ( empty($_FILES) || empty($_FILES['voucher']) ) {
        wp_send_json_error(array('message'=>'Debes adjuntar un comprobante (imagen o PDF).'));
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $file = $_FILES['voucher'];
    $overrides = array( 'test_form' => false );
    $movefile = wp_handle_upload( $file, $overrides );
    if ( isset( $movefile['error'] ) ) {
        wp_send_json_error( array( 'message' => 'Error al subir comprobante: ' . $movefile['error'] ) );
    }

    $filename = $movefile['file'];
    $filetype = wp_check_filetype( basename( $filename ), null );
    $attachment = array(
        'post_mime_type' => $filetype['type'],
        'post_title'     => sanitize_file_name( basename( $filename ) ),
        'post_content'   => '',
        'post_status'    => 'inherit'
    );
    $attachment_id = wp_insert_attachment( $attachment, $filename );
    if ( ! is_wp_error( $attachment_id ) ) {
        $attach_data = wp_generate_attachment_metadata( $attachment_id, $filename );
        wp_update_attachment_metadata( $attachment_id, $attach_data );
    }

    $history = get_user_meta( $user_id, 'merc_liquidations', true );
    if ( ! is_array( $history ) ) {
        wp_send_json_error(array('message'=>'No se encontró el historial'));
    }

    $found = false;
    foreach ( $history as &$entry ) {
        if ( isset($entry['id']) && $entry['id'] === $liq_id ) {
            $entry['attachment_id'] = $attachment_id;
            $found = true;
            break;
        }
    }
    
    if ( ! $found ) {
        wp_send_json_error(array('message'=>'Liquidación no encontrada'));
    }

    update_user_meta( $user_id, 'merc_liquidations', $history );

    wp_send_json_success(array('message'=>'Comprobante subido y pendiente de verificación administrativa.'));
}

// AJAX: cliente sube voucher para crear y pagar proactivamente su deuda global
add_action('wp_ajax_merc_cliente_crear_y_pagar_deuda', 'merc_cliente_crear_y_pagar_deuda_ajax');
function merc_cliente_crear_y_pagar_deuda_ajax() {
    if ( ! isset($_POST['nonce']) || ! wp_verify_nonce( $_POST['nonce'], 'merc_cliente_pagar' ) ) {
        wp_send_json_error(array('message'=>'Nonce inválido'));
    }
    
    $current = wp_get_current_user();
    if ( ! $current->ID ) {
        wp_send_json_error(array('message'=>'Usuario inválido'));
    }
    $user_id = $current->ID;

    if ( empty($_FILES) || empty($_FILES['voucher']) ) {
        wp_send_json_error(array('message'=>'Debes adjuntar un comprobante (imagen o PDF).'));
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $file = $_FILES['voucher'];
    $overrides = array( 'test_form' => false );
    $movefile = wp_handle_upload( $file, $overrides );
    if ( isset( $movefile['error'] ) ) {
        wp_send_json_error( array( 'message' => 'Error al subir comprobante: ' . $movefile['error'] ) );
    }

    $filename = $movefile['file'];
    $filetype = wp_check_filetype( basename( $filename ), null );
    $attachment = array(
        'post_mime_type' => $filetype['type'],
        'post_title'     => sanitize_file_name( basename( $filename ) ),
        'post_content'   => '',
        'post_status'    => 'inherit'
    );
    $attachment_id = wp_insert_attachment( $attachment, $filename );
    if ( ! is_wp_error( $attachment_id ) ) {
        $attach_data = wp_generate_attachment_metadata( $attachment_id, $filename );
        wp_update_attachment_metadata( $attachment_id, $attach_data );
    }

    global $wpdb;
    // Buscar todos los envíos pendientes del cliente que estén ENTREGADOS (los que conforman la deuda)
    $query = $wpdb->prepare("
        SELECT p.ID
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm_shipper ON p.ID = pm_shipper.post_id AND pm_shipper.meta_key = 'registered_shipper'
        LEFT JOIN {$wpdb->postmeta} pm_included ON p.ID = pm_included.post_id AND pm_included.meta_key = 'wpcargo_included_in_liquidation'
        WHERE p.post_type = 'wpcargo_shipment' AND p.post_status = 'publish'
        AND pm_shipper.meta_value = %s
        AND (pm_included.meta_value IS NULL OR pm_included.meta_value = '')
    ", $user_id);
    
    // Aquí filtramos más si fuera necesario, pero por ahora enlazaremos todos los envíos no liquidados
    // ya que la deuda abarca todo el balance histórico pendiente.
    $shipments_db = $wpdb->get_col($query);

    $liq_id = 'LIQ-' . date('Ymd-His') . '-' . wp_generate_password(4, false);
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
    
    $entry = array(
        'id'            => $liq_id,
        'date'          => current_time('Y-m-d H:i:s'),
        'amount'        => $amount,
        'shipments'     => $shipments_db,
        'action'        => 'marca_debe',
        'verified'      => false,
        'attachment_id' => $attachment_id
    );

    $history = get_user_meta( $user_id, 'merc_liquidations', true );
    if ( ! is_array( $history ) ) $history = array();
    
    array_unshift($history, $entry); // Añadir al principio
    update_user_meta( $user_id, 'merc_liquidations', $history );

    wp_send_json_success(array('message'=>'Comprobante subido y registrado exitosamente.'));
}

// AJAX: Verificar liquidación (admin) — aplica la liquidación y marca envíos
add_action('wp_ajax_merc_verify_liquidation', 'merc_verify_liquidation_ajax');
function merc_verify_liquidation_ajax() {
    if ( ! isset($_POST['nonce']) || ! wp_verify_nonce( $_POST['nonce'], 'merc_verify' ) ) {
        wp_send_json_error(array('message'=>'Nonce inválido'));
    }
    if ( ! current_user_can('administrator') ) {
        wp_send_json_error(array('message'=>'Sin permisos'));
    }
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $liq_id = isset($_POST['liq_id']) ? sanitize_text_field($_POST['liq_id']) : '';
    if ( $user_id <= 0 || empty($liq_id) ) wp_send_json_error(array('message'=>'Datos inválidos'));

    $history = get_user_meta( $user_id, 'merc_liquidations', true );
    if ( ! is_array($history) ) wp_send_json_error(array('message'=>'No hay historial'));

    $found = false;
    foreach ( $history as $i => $entry ) {
        if ( isset($entry['id']) && $entry['id'] === $liq_id ) {
            $found = true;
            if ( isset($entry['verified']) && $entry['verified'] ) {
                wp_send_json_error(array('message'=>'Ya verificado'));
            }

            // Aplicar: marcar envíos incluidos y servicios cobrados
            $shipments = isset($entry['shipments']) && is_array($entry['shipments']) ? $entry['shipments'] : array();
            foreach ( $shipments as $shipment_id ) {
                update_post_meta( $shipment_id, 'wpcargo_servicio_cobrado', 'si' );
                update_post_meta( $shipment_id, 'wpcargo_included_in_liquidation', $liq_id );
                update_post_meta( $shipment_id, 'wpcargo_fecha_liquidacion_remitente', current_time('mysql') );
            }

            // Marcar entry como verificada
            $history[$i]['verified'] = true;
            $history[$i]['verified_by'] = get_current_user_id();
            $history[$i]['verified_date'] = current_time('mysql');
            
            // Si la entrada corresponde a una penalidad, marcarla como pagada
            if ( isset($entry['penalty_id']) && ! empty($entry['penalty_id']) ) {
                $pen_id = intval($entry['penalty_id']);
                if ( $pen_id > 0 ) {
                    update_post_meta($pen_id, 'status', 'paid');
                    update_post_meta($pen_id, 'paid_at', current_time('mysql'));
                    update_post_meta($pen_id, 'payment_ref_liquidation', $liq_id);
                }
            }

            update_user_meta( $user_id, 'merc_liquidations', $history );
            wp_send_json_success(array('message'=>'Liquidación verificada y aplicada.'));
        }
    }
    if ( ! $found ) wp_send_json_error(array('message'=>'Liquidación no encontrada'));
}

// AJAX handlers para finanzas adicionales
add_action('wp_ajax_merc_finance_get_summary', 'merc_finance_get_summary');
function merc_finance_get_summary() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'No autorizado']);
    }
    wp_send_json_success(['summary' => []]);
}
?>


