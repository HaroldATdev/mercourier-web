<?php
/**
 * Manual Unlock System Module
 * Handle daily manual unblocking of client access
 *
 * @package WPCargo_Access_Control
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Apply unlock status to all clients for today
 * 
 * @param bool $enable Whether to enable or disable unlock
 */
function wpcac_apply_skip_to_all_clients($enable = true) {
    $today = wpcac_get_today();
    
    $args = array(
        'role' => 'wpcargo_client',
        'fields' => 'ID',
        'number' => 0,
    );
    
    $users = get_users($args);
    $count = 0;
    
    foreach ($users as $uid) {
        if ($enable) {
            update_user_meta($uid, 'merc_desbloqueado_manualmente_fecha', $today);
            update_user_meta($uid, 'merc_desbloqueo_manual_envios_permitidos', 9999);
        } else {
            delete_user_meta($uid, 'merc_desbloqueado_manualmente_fecha');
            delete_user_meta($uid, 'merc_desbloqueo_manual_envios_permitidos');
        }
        $count++;
    }
    
    error_log(sprintf("🔁 wpcac_apply_skip_to_all_clients: %s %d clientes procesados", $enable ? 'ENABLE' : 'DISABLE', $count));
    
    do_action('wpcac_skip_status_changed', $enable, $today, $count);
}

/**
 * Toggle bypass status for today
 * Handler for admin action
 */
function wpcac_handle_admin_unlock_toggle() {
    $current_user = wp_get_current_user();
    $is_merc_admin = $current_user->user_email === 'mercourier2019@gmail.com';
    
    if (!current_user_can('manage_options') && !$is_merc_admin) {
        return;
    }

    if (!empty($_GET['wpcac_toggle_skip_today'])) {
        $action = sanitize_text_field($_GET['wpcac_toggle_skip_today']);
        
        if ($action === 'enable') {
            update_option('merc_skip_blocks_today', wpcac_get_today());
            wpcac_apply_skip_to_all_clients(true);
            error_log("✅ Admin activó bypass de bloqueos para hoy: " . wpcac_get_today());
        } elseif ($action === 'disable') {
            update_option('merc_skip_blocks_today', '');
            wpcac_apply_skip_to_all_clients(false);
            error_log("❌ Admin desactivó bypass de bloqueos");
        }

        $redirect = remove_query_arg('wpcac_toggle_skip_today');
        wp_safe_redirect($redirect);
        exit;
    }
}

add_action('admin_init', 'wpcac_handle_admin_unlock_toggle');

/**
 * Register admin menu for unlock control
 */
function wpcac_register_admin_menu() {
    $current_user = wp_get_current_user();
    $is_merc_admin = $current_user->user_email === 'mercourier2019@gmail.com';
    
    if (current_user_can('manage_options') || $is_merc_admin) {
        add_submenu_page(
            'tools.php',
            'Skip Blocks Recojo',
            'Skip Blocks Recojo',
            'manage_options',
            'wpcac-skip-blocks',
            'wpcac_render_unlock_page'
        );
    }
}

add_action('admin_menu', 'wpcac_register_admin_menu');

/**
 * Get current bypass status
 * 
 * @return bool True if bypass is enabled for today
 */
function wpcac_get_bypass_status() {
    return wpcac_is_bypass_enabled_today();
}

/**
 * Handle AJAX requests for unlock toggle (optional, for future use)
 */
function wpcac_ajax_toggle_unlock() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }

    $action = sanitize_text_field($_POST['action_type'] ?? '');

    if ($action === 'enable') {
        update_option('merc_skip_blocks_today', wpcac_get_today());
        wpcac_apply_skip_to_all_clients(true);
        wp_send_json_success(array(
            'message' => 'Bypass habilitado',
            'status' => 'enabled',
            'date' => wpcac_get_today(),
        ));
    } elseif ($action === 'disable') {
        update_option('merc_skip_blocks_today', '');
        wpcac_apply_skip_to_all_clients(false);
        wp_send_json_success(array(
            'message' => 'Bypass deshabilitado',
            'status' => 'disabled',
        ));
    }

    wp_send_json_error(array('message' => 'Invalid action'));
}

add_action('wp_ajax_wpcac_toggle_unlock', 'wpcac_ajax_toggle_unlock');

