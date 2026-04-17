<?php
if ( ! defined('ABSPATH') ) exit;

function wcmas_tpl( string $tpl, array $vars = [] ): void {
    $file = WCMAS_PATH . 'admin/templates/' . $tpl;
    if ( ! file_exists($file) ) { echo '<p>Template no encontrado: '.esc_html($tpl).'</p>'; return; }
    extract($vars, EXTR_SKIP);
    require $file;
}

function wcmas_url( string $page, array $extra = [] ): string {
    return add_query_arg(array_merge(['page' => $page], $extra), admin_url('admin.php'));
}

function wcmas_redirect( string $page, string $msg = '', array $extra = [] ): void {
    $params = array_merge(['page' => $page], $extra);
    if ($msg) $params['wcmas_msg'] = $msg;
    wp_redirect(add_query_arg($params, admin_url('admin.php')));
    exit;
}

function wcmas_get_frontend_page_id(): int {
    $saved = (int) get_option('wcmas_frontend_page_id');
    if ( $saved && get_post_status($saved) === 'publish' ) return $saved;
    global $wpdb;
    $id = (int) $wpdb->get_var("SELECT ID FROM {$wpdb->prefix}posts WHERE post_content LIKE '%[wcmas-masivos]%' AND post_status='publish' LIMIT 1");
    if ( ! $id ) {
        $id = (int) wp_insert_post([
            'post_title'   => 'Envíos Masivos',
            'post_content' => '[wcmas-masivos]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ]);
    }
    if ( $id ) {
        update_post_meta($id, '_wp_page_template', 'dashboard.php');
        update_post_meta($id, 'wpcfe_menu_icon',   'fa fa-table mr-3');
        update_option('wcmas_frontend_page_id', $id, false);
    }
    return $id;
}

function wcmas_frontend_url( array $extra = [] ): string {
    $url = get_permalink(wcmas_get_frontend_page_id()) ?: home_url('/envios-masivos/');
    return $extra ? add_query_arg($extra, $url) : $url;
}

function wcmas_generar_tracking(): string {
    if ( function_exists('wpcargo_generate_tracking_number') ) {
        return wpcargo_generate_tracking_number();
    }
    $raw_prefix = strtoupper((string) get_option('wcmas_tracking_prefix', 'LISTO'));
    $prefix = preg_replace('/[^A-Z0-9]/', '', $raw_prefix);
    if ( $prefix === '' ) $prefix = 'LISTO';

    try {
        $numero = random_int(0, 999999);
    } catch ( \Throwable $e ) {
        $numero = mt_rand(0, 999999);
    }

    return sprintf('%s-%06d', $prefix, $numero);
}

function wcmas_default_status(): string {
    $status = get_option('wpcfe_default_status');
    if ( ! $status ) $status = 'Pending';
    return apply_filters('wcmas_default_status', $status);
}

function wcmas_puede_crear(): bool {
    if ( ! is_user_logged_in() ) return false;
    if ( function_exists('can_wpcfe_add_shipment') ) {
        return (bool) can_wpcfe_add_shipment();
    }
    $user  = wp_get_current_user();
    $roles = (array) $user->roles;
    $roles_permitidos = ['administrator', 'wpcargo_admin', 'wpcargo_client',
                         'wpcargo_employee', 'cargo_agent', 'wpcargo_branch_manager', 'editor'];
    return (bool) array_intersect($roles, $roles_permitidos);
}

function wcmas_es_admin(): bool {
    if ( ! is_user_logged_in() ) return false;
    if ( current_user_can('manage_options') ) return true;
    if ( function_exists('wpcfe_is_super_admin') && wpcfe_is_super_admin() ) return true;
    return in_array('wpcargo_admin', (array) wp_get_current_user()->roles, true);
}

/**
 * Lista SOLO usuarios con rol wpcargo_client para la columna Remitente en admin.
 */
function wcmas_get_usuarios_select(): array {
    $users = get_users([
        'role__in' => ['wpcargo_client'],
        'orderby'  => 'display_name',
        'order'    => 'ASC',
        'number'   => 500,
    ]);
    $result = [];
    foreach ( $users as $u ) {
        $nombre = trim($u->display_name ?: $u->user_login);
        $result[] = [
            'id'    => $u->ID,
            'label' => "{$nombre} <{$u->user_email}>",
        ];
    }
    return $result;
}
