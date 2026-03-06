<?php
/**
 * Gestión de asignación de motorizados (recojo/entrega)
 * 
 * @package merc-shipment-container-management
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ========================================
 * LIMPIEZA DIARIA DE MOTORIZADO RECOJO
 * ========================================
 * Cada día a las 00:01 se elimina el merc_motorizo_recojo_default de todos los usuarios
 */

add_action('merc_daily_cleanup_motorizo_default', 'merc_cleanup_motorizo_recojo_default');
function merc_cleanup_motorizo_recojo_default() {
    global $wpdb;
    
    error_log("\n════════════════════════════════════════════════════════════");
    error_log("🧹 [LIMPIEZA DIARIA] Eliminando merc_motorizo_recojo_default");
    error_log("════════════════════════════════════════════════════════════");
    
    $users_with_driver = $wpdb->get_col(
        "SELECT DISTINCT user_id FROM {$wpdb->usermeta} 
        WHERE meta_key = 'merc_motorizo_recojo_default'"
    );
    
    error_log("📊 Usuarios con motorizado_recojo_default: " . count($users_with_driver));
    
    $deleted_count = 0;
    foreach ($users_with_driver as $user_id) {
        $motorizado = get_user_meta($user_id, 'merc_motorizo_recojo_default', true);
        $deleted = delete_user_meta($user_id, 'merc_motorizo_recojo_default');
        
        if ($deleted) {
            error_log("   ✅ Usuario #$user_id: Eliminado motorizado #$motorizado");
            $deleted_count++;
        }
    }
    
    error_log("🔄 Total usuarios limpios: $deleted_count");
    error_log("✅ [LIMPIEZA DIARIA COMPLETADA]");
    error_log("════════════════════════════════════════════════════════════\n");
}

/**
 * AJAX handler: Asignar motorizado masivamente de RECOJO a usuarios
 */
add_action('wp_ajax_merc_assign_motorizado_bulk', 'merc_assign_motorizado_bulk_ajax');
add_action('wp_ajax_nopriv_merc_assign_motorizado_bulk', 'merc_assign_motorizado_bulk_ajax');
function merc_assign_motorizado_bulk_ajax() {
    error_log("\n🔍 MERC_ASSIGN_MOTORIZADO_BULK - Solicitud AJAX RECIBIDA");
    
    try {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
        
        if (empty($nonce) || !wp_verify_nonce($nonce, 'merc_assign_motorizado')) {
            error_log("❌ NONCE verificado como INVÁLIDO");
            wp_send_json_error(['message' => 'Nonce inválido'], 403);
        }
        
        $user_ids = isset($_POST['user_ids']) ? array_map('intval', (array) $_POST['user_ids']) : [];
        $driver_id = isset($_POST['driver_id']) ? intval($_POST['driver_id']) : 0;
        $container_id = isset($_POST['container_id']) ? intval($_POST['container_id']) : 0;
        
        if (empty($user_ids) || !$driver_id || !$container_id) {
            wp_send_json_error(['message' => 'Datos inválidos']);
        }
        
        error_log("════════════════════════════════════════════");
        error_log("🔄 INICIANDO ASIGNACIÓN DE MOTORIZADO");
        error_log("   Driver ID: " . $driver_id);
        error_log("   User IDs: " . json_encode($user_ids));
        error_log("════════════════════════════════════════════");
        
        $total = 0;
        foreach ($user_ids as $uid) {
            error_log("→ Procesando usuario #$uid");
            update_user_meta($uid, 'merc_motorizo_recojo_default', $driver_id);
            
            // Buscar envíos del usuario en este contenedor
            global $wpdb;
            $shipments = $wpdb->get_col($wpdb->prepare(
                "SELECT pm.post_id FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                LEFT JOIN {$wpdb->postmeta} pm_tipo ON pm.post_id = pm_tipo.post_id AND pm_tipo.meta_key = 'tipo_envio'
                WHERE p.post_status = 'publish'
                AND p.post_type = 'wpcargo_shipment'
                AND pm.meta_key = 'shipment_container_recojo'
                AND pm.meta_value = %d
                AND pm_tipo.meta_value = 'normal'",
                $container_id
            ));
            
            foreach ($shipments as $ship_id) {
                if (merc_pickup_date_is_today($ship_id)) {
                    $shipper = get_post_meta($ship_id, 'registered_shipper', true);
                    if ($shipper == $uid) {
                        update_post_meta($ship_id, 'wpcargo_motorizo_recojo', $driver_id);
                        delete_post_meta($ship_id, 'wpcargo_driver');
                        add_post_meta($ship_id, 'wpcargo_driver', $driver_id);
                        $total++;
                        error_log("   ✅ Envío $ship_id actualizado");
                    }
                }
            }
        }
        
        error_log("✅ Total envíos asignados: $total");
        wp_send_json_success(['message' => "Motorizado asignado a " . count($user_ids) . " usuario(s)", 'count' => $total]);
        
    } catch (Exception $e) {
        error_log("❌ ERROR: " . $e->getMessage());
        wp_send_json_error(['message' => 'Error: ' . $e->getMessage()]);
    }
}

/**
 * Hook: Aplicar motorizado default al crear nuevos envíos
 * Se ejecuta con máxima prioridad después de guardar
 */
add_action('after_wpcfe_save_shipment', 'merc_asignar_motorizado_default_al_crear', 999, 2);
function merc_asignar_motorizado_default_al_crear($post_id, $data = array()) {
    error_log("\n🎯 [ASIGNAR MOTORIZADO DEFAULT] Envío #" . $post_id);
    
    if (get_post_type($post_id) !== 'wpcargo_shipment') {
        return;
    }
    
    $user_id = get_post_meta($post_id, 'registered_shipper', true);
    
    if (!$user_id) {
        error_log("❌ No hay usuario para este envío");
        return;
    }
    
    // Obtener el motorizado default del usuario
    $motorizado_default = get_user_meta($user_id, 'merc_motorizo_recojo_default', true);
    
    if (empty($motorizado_default)) {
        error_log("ℹ️ Usuario #" . $user_id . " NO tiene motorizado default");
        return;
    }
    
    // Verificar si ya tiene motorizado
    $motorizado_actual = get_post_meta($post_id, 'wpcargo_motorizo_recojo', true);
    
    if (!empty($motorizado_actual)) {
        error_log("⚠️ Envío ya tiene motorizado, sincronizando wpcargo_driver");
        update_post_meta($post_id, 'wpcargo_driver', intval($motorizado_actual));
        return;
    }
    
    // Verificar si tiene fecha FUTURA
    if (merc_pickup_date_is_future($post_id)) {
        error_log("⏭️ Envío tiene fecha FUTURA, NO asignando motorizado");
        return;
    }
    
    // Asignar motorizado default
    update_post_meta($post_id, 'wpcargo_motorizo_recojo', $motorizado_default);
    delete_post_meta($post_id, 'wpcargo_driver');
    add_post_meta($post_id, 'wpcargo_driver', $motorizado_default);
    
    error_log("✅ Motorizado default asignado: #" . $motorizado_default);
}

/**
 * Render JavaScript para inyectar selects duales de motorizado (recojo/entrega)
 */
add_action('admin_footer', 'merc_dual_motorizado_js');
add_action('wp_footer', 'merc_dual_motorizado_js');
function merc_dual_motorizado_js() {
    ?>
    <script>
    (function($){
        $(function(){
            var $orig = $('select[name="wpcargo_driver"]');
            if (!$orig.length) return;

            var urlParams = new URLSearchParams(window.location.search);
            var wpcfe = (urlParams.get('wpcfe') || '').toLowerCase();
            if (wpcfe !== 'add' && wpcfe !== 'update') return;

            if ($('select[name="wpcargo_motorizo_recojo"]').length) return;

            var tipo = ($('input[name="tipo_envio"]').val() || $('select[name="tipo_envio"]').val() || '').toString().toLowerCase();
            var motorizo_recojo_val = '';
            var motorizo_entrega_val = '';

            if ((!tipo || wpcfe === 'update') && wpcfe === 'update') {
                var shipmentId = ($('input[name="post_id"]').val() || $('input[name="post_ID"]').val());
                if (shipmentId) {
                    $.ajax({
                        url: ajaxurl || '/wp-admin/admin-ajax.php',
                        type: 'POST',
                        data: { action: 'merc_get_shipment_data', shipment_id: shipmentId },
                        async: false,
                        success: function(resp) {
                            if (resp && resp.success && resp.data) {
                                tipo = (resp.data.tipo_envio || '').toString().toLowerCase();
                                motorizo_recojo_val = resp.data.motorizo_recojo ? resp.data.motorizo_recojo.toString() : '';
                                motorizo_entrega_val = resp.data.motorizo_entrega ? resp.data.motorizo_entrega.toString() : '';
                            }
                        }
                    });
                }
            }

            if (tipo !== 'normal') return;

            $orig.attr('style', 'display:none !important');
            $orig.attr('hidden', 'hidden');

            var $recojo = $('<select/>', { name: 'wpcargo_motorizo_recojo', class: $orig.attr('class') });
            var $entrega = $('<select/>', { name: 'wpcargo_motorizo_entrega', class: $orig.attr('class') });

            $orig.find('option').each(function(){
                var $opt = $(this).clone().removeAttr('selected');
                $recojo.append($opt.clone());
                $entrega.append($opt.clone());
            });

            var $wrap = $('<div style="display:flex;flex-direction:column;gap:8px;"></div>');
            var $labelR = $('<label>Motorizado (Recojo)</label>');
            var $labelE = $('<label>Motorizado (Entrega)</label>');
            $wrap.append($labelR).append($recojo).append($labelE).append($entrega);
            $orig.after($wrap);

            $recojo.val(motorizo_recojo_val);
            $entrega.val(motorizo_entrega_val);
        });
    })(jQuery);
    </script>
    <?php
}

/**
 * Guardar metas duales de motorizado al guardar post
 */
add_action('save_post_wpcargo_shipment', 'merc_save_dual_motorizado_meta', 999, 1);
function merc_save_dual_motorizado_meta($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    error_log("💾 [DUAL_MOTORIZADO_SAVE] Guardando motorizado dual para envío #{$post_id}");

    if (isset($_POST['wpcargo_motorizo_recojo'])) {
        $val = intval($_POST['wpcargo_motorizo_recojo']);
        if ($val > 0) {
            update_post_meta($post_id, 'wpcargo_motorizo_recojo', $val);
        } else {
            delete_post_meta($post_id, 'wpcargo_motorizo_recojo');
        }
    }

    if (isset($_POST['wpcargo_motorizo_entrega'])) {
        $val = intval($_POST['wpcargo_motorizo_entrega']);
        if ($val > 0) {
            update_post_meta($post_id, 'wpcargo_motorizo_entrega', $val);
        } else {
            delete_post_meta($post_id, 'wpcargo_motorizo_entrega');
        }
    }

    error_log("✅ DUAL_MOTORIZADO_SAVE COMPLETADO");
}

/**
 * AJAX handler: Asignar motorizado ENTREGA masivamente
 */
add_action('wp_ajax_merc_assign_motorizado_entrega_bulk', 'merc_assign_motorizado_entrega_bulk_ajax');
add_action('wp_ajax_nopriv_merc_assign_motorizado_entrega_bulk', 'merc_assign_motorizado_entrega_bulk_ajax');
function merc_assign_motorizado_entrega_bulk_ajax() {
    error_log("\n🔍 MERC_ASSIGN_MOTORIZADO_ENTREGA_BULK");
    
    try {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
        
        if (empty($nonce) || !wp_verify_nonce($nonce, 'merc_assign_motorizado')) {
            wp_send_json_error(['message' => 'Nonce inválido'], 403);
        }
        
        $shipment_ids = isset($_POST['shipment_ids']) ? array_map('intval', (array) $_POST['shipment_ids']) : [];
        $driver_id = isset($_POST['driver_id']) ? intval($_POST['driver_id']) : 0;
        
        if (empty($shipment_ids) || !$driver_id) {
            wp_send_json_error(['message' => 'Datos inválidos']);
        }
        
        $total = 0;
        foreach ($shipment_ids as $sid) {
            update_post_meta($sid, 'wpcargo_motorizo_entrega', $driver_id);
            delete_post_meta($sid, 'wpcargo_driver');
            add_post_meta($sid, 'wpcargo_driver', $driver_id);
            $total++;
        }
        
        error_log("✅ Total asignados: $total");
        wp_send_json_success(['message' => "Motorizado asignado a $total envío(s)", 'count' => $total]);
        
    } catch (Exception $e) {
        error_log("❌ ERROR: " . $e->getMessage());
        wp_send_json_error(['message' => 'Error: ' . $e->getMessage()]);
    }
}
