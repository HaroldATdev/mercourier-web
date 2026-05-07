<?php
if (!defined('ABSPATH')) exit;

// ---------------------------------------------------------------------------
// REGISTRO DE ACCIONES AJAX
// ---------------------------------------------------------------------------
add_action('wp_ajax_merc_almacen_get_productos', 'merc_almacen_get_productos');
add_action('wp_ajax_merc_guardar_producto', 'merc_guardar_producto');
add_action('wp_ajax_merc_obtener_clientes_lista', 'merc_obtener_clientes_lista');
add_action('wp_ajax_nopriv_merc_obtener_clientes_lista', 'merc_obtener_clientes_lista');
add_action('wp_ajax_merc_obtener_producto', 'merc_obtener_producto');
add_action('wp_ajax_merc_actualizar_producto', 'merc_actualizar_producto');
add_action('wp_ajax_merc_eliminar_producto', 'merc_eliminar_producto');
add_action('wp_ajax_merc_get_product_units', 'merc_get_product_units_ajax');
add_action('wp_ajax_merc_subir_foto_producto', 'merc_subir_foto_producto_ajax');
add_action('wp_ajax_merc_registrar_ingreso', 'merc_registrar_ingreso_ajax');
add_action('wp_ajax_merc_registrar_egreso', 'merc_registrar_egreso_ajax');
add_action('wp_ajax_merc_get_historial_movimientos', 'merc_get_historial_movimientos_ajax');

// ---------------------------------------------------------------------------
// HANDLERS
// ---------------------------------------------------------------------------

/**
 * Obtener lista de productos con stock y metadatos
 */
function merc_almacen_get_productos() {
    $current_user = wp_get_current_user();
    $roles = (array)$current_user->roles;
    $is_admin = function_exists('merc_user_can_edit_warehouse') ? merc_user_can_edit_warehouse() : current_user_can('manage_options');
    $is_client = in_array('wpcargo_client', $roles);
    
    error_log("🔷 [AJAX] Usuario: " . $current_user->user_login . " | Roles: " . implode(', ', $roles) . " | IsAdmin: " . ($is_admin?'SI':'NO') . " | IsClient: " . ($is_client?'SI':'NO'));

    // Validar nonce
    if (isset($_POST['nonce'])) {
        if (!wp_verify_nonce($_POST['nonce'], 'merc_almacen')) {
            wp_send_json_error(['message' => 'Nonce inválido']);
        }
    }
    
    if (!is_user_logged_in() || (!$is_admin && !$is_client)) {
        while ( ob_get_level() > 0 ) { ob_end_clean(); }
        wp_send_json_error(['message' => 'No autorizado']);
    }
    
    $args = array(
        'post_type' => 'merc_producto',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'orderby' => 'title',
        'order' => 'ASC'
    );
    
    // Definir si es un administrador con poder total sobre el almacén
    $is_warehouse_admin = (function_exists('merc_user_can_edit_warehouse') && merc_user_can_edit_warehouse()) || current_user_can('manage_options');
    
    // Si no es admin de almacén, forzamos el filtro de cliente
    if (!$is_warehouse_admin && $is_client) {
        $args['meta_query'] = array(
            array(
                'key' => '_merc_producto_cliente_asignado',
                'value' => (string) $current_user->ID,
                'compare' => '=',
            )
        );
    }

    // Si es admin y seleccionó un cliente específico en el filtro
    if ($is_warehouse_admin && !empty($_POST['cliente_id'])) {
        $args['meta_query'] = array(
            array(
                'key' => '_merc_producto_cliente_asignado',
                'value' => (string) intval($_POST['cliente_id']),
                'compare' => '=',
            )
        );
    }
    
    $ps = get_posts($args);
    $lista = array();
    
    foreach ($ps as $p) {
        $cliente_meta = get_post_meta($p->ID, '_merc_producto_cliente_asignado', true);
        
        // Filtro de seguridad adicional: si no es admin, solo puede ver lo suyo
        if (!$is_warehouse_admin) {
            if ((string)$cliente_meta !== (string)$current_user->ID) {
                continue;
            }
        }
        
        $c = function_exists('merc_get_product_stock') ? merc_get_product_stock($p->ID) : 0;
        $estado = get_post_meta($p->ID, '_merc_producto_estado', true) ?: 'sin_asignar';
        $motorizado = get_post_meta($p->ID, '_merc_producto_motorizado', true) ?: '-';
        
        $billing_company = '';
        $cliente_nombre = '';
        if ($cliente_meta) {
            $bc = get_user_meta(intval($cliente_meta), 'billing_company', true);
            $fn = get_user_meta(intval($cliente_meta), 'billing_first_name', true) ?: get_user_meta(intval($cliente_meta), 'first_name', true);
            $ln = get_user_meta(intval($cliente_meta), 'billing_last_name', true) ?: get_user_meta(intval($cliente_meta), 'last_name', true);
            
            $billing_company = !empty($bc) ? $bc : trim($fn . ' ' . $ln);
            if (empty($billing_company)) {
                $u = get_userdata(intval($cliente_meta));
                $billing_company = $u ? $u->display_name : 'Sin nombre';
            }
            $cliente_nombre = trim($fn . ' ' . $ln);
        }

        $lista[] = array(
            'id'                => $p->ID,
            'nombre'            => $p->post_title,
            'codigo_barras'     => get_post_meta($p->ID, '_merc_producto_codigo_barras', true),
            'cliente_asignado'  => $cliente_meta,
            'billing_company'   => $billing_company,
            'cliente_nombre'    => $cliente_nombre,
            'cantidad'          => !empty($c) ? intval($c) : 0,
            'fecha_creacion'    => get_the_date('d/m/Y H:i', $p->ID),
            'fecha_modificacion'=> get_the_modified_date('d/m/Y H:i', $p->ID),
            'estado'            => $estado,
            'motorizado'        => $motorizado,
            'tipo_medida'       => get_post_meta($p->ID, '_merc_producto_tipo_medida', true) ?: '',
            'valor_medida'      => get_post_meta($p->ID, '_merc_producto_valor_medida', true) ?: '',
            'foto_url'          => function_exists('merc_get_product_foto_url') ? merc_get_product_foto_url($p->ID) : '',
        );
    }
    
    while ( ob_get_level() > 0 ) { ob_end_clean(); }
    wp_send_json_success(['productos' => $lista]);
}

/**
 * Guardar nuevo producto
 */
function merc_guardar_producto() {
    if (isset($_POST['nonce']) && !wp_verify_nonce($_POST['nonce'], 'merc_almacen')) {
        wp_send_json_error(['message' => 'Nonce inválido']);
    }
    if (!(function_exists('merc_user_can_edit_warehouse') ? merc_user_can_edit_warehouse() : current_user_can('manage_options'))) {
        wp_send_json_error(['message' => 'No autorizado']);
    }
    
    $nombre = sanitize_text_field($_POST['nombre'] ?? '');
    if (empty($nombre)) wp_send_json_error(['message' => 'El nombre es obligatorio']);
    
    $product_id = wp_insert_post([
        'post_type'   => 'merc_producto',
        'post_title'  => $nombre,
        'post_status' => 'publish'
    ]);
    
    if (is_wp_error($product_id)) wp_send_json_error(['message' => 'Error al crear']);
    
    update_post_meta($product_id, '_merc_producto_codigo_barras', sanitize_text_field($_POST['codigo_barras'] ?? ''));
    update_post_meta($product_id, '_merc_producto_cliente_asignado', (string)intval($_POST['cliente_asignado'] ?? 0));
    update_post_meta($product_id, '_merc_producto_estado', 'sin_asignar');
    
    $cantidad = intval($_POST['cantidad'] ?? 0);
    if (function_exists('merc_set_product_stock')) {
        merc_set_product_stock($product_id, $cantidad, $_POST['codigo_barras']);
    }
    
    while ( ob_get_level() > 0 ) { ob_end_clean(); }
    wp_send_json_success(['message' => 'Producto guardado', 'product_id' => $product_id]);
}

/**
 * Obtener lista de clientes (roles wpcargo_client)
 */
function merc_obtener_clientes_lista() {
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'No logueado']);
    
    $clientes = get_users(['role' => 'wpcargo_client', 'orderby' => 'display_name']);
    $lista = [];
    foreach ($clientes as $c) {
        $empresa = get_user_meta($c->ID, 'billing_company', true) ?: $c->display_name;
        $lista[] = ['id' => (string)$c->ID, 'nombre' => $empresa];
    }
    while ( ob_get_level() > 0 ) { ob_end_clean(); }
    wp_send_json_success(['clientes' => $lista]);
}

/**
 * Obtener datos de un producto para edición
 */
function merc_obtener_producto() {
    $id = intval($_POST['product_id'] ?? 0);
    $p = get_post($id);
    if (!$p) wp_send_json_error(['message' => 'No encontrado']);
    
    $datos = [
        'id'               => $id,
        'nombre'           => $p->post_title,
        'codigo_barras'    => get_post_meta($id, '_merc_producto_codigo_barras', true),
        'cliente_asignado' => (string)get_post_meta($id, '_merc_producto_cliente_asignado', true),
        'cantidad'         => function_exists('merc_get_product_stock') ? merc_get_product_stock($id) : 0,
        'peso'             => get_post_meta($id, '_merc_producto_peso', true),
        'tipo_medida'      => get_post_meta($id, '_merc_producto_tipo_medida', true),
        'valor_medida'     => get_post_meta($id, '_merc_producto_valor_medida', true),
    ];
    while ( ob_get_level() > 0 ) { ob_end_clean(); }
    wp_send_json_success($datos);
}

/**
 * Actualizar producto existente
 */
function merc_actualizar_producto() {
    if (isset($_POST['nonce']) && !wp_verify_nonce($_POST['nonce'], 'merc_almacen')) {
        wp_send_json_error(['message' => 'Nonce inválido']);
    }
    if (!(function_exists('merc_user_can_edit_warehouse') ? merc_user_can_edit_warehouse() : current_user_can('manage_options'))) {
        wp_send_json_error(['message' => 'No autorizado']);
    }
    
    $id = intval($_POST['product_id'] ?? 0);
    $nombre = sanitize_text_field($_POST['nombre'] ?? '');
    if (!$id || empty($nombre)) wp_send_json_error(['message' => 'Datos incompletos']);
    
    wp_update_post(['ID' => $id, 'post_title' => $nombre]);
    update_post_meta($id, '_merc_producto_codigo_barras', sanitize_text_field($_POST['codigo_barras'] ?? ''));
    update_post_meta($id, '_merc_producto_cliente_asignado', (string)intval($_POST['cliente_asignado'] ?? 0));
    
    while ( ob_get_level() > 0 ) { ob_end_clean(); }
    wp_send_json_success(['message' => 'Producto actualizado']);
}

/**
 * Eliminar producto (físicamente)
 */
function merc_eliminar_producto() {
    if (!(function_exists('merc_user_can_edit_warehouse') ? merc_user_can_edit_warehouse() : current_user_can('manage_options'))) {
        wp_send_json_error(['message' => 'No autorizado']);
    }
    $id = intval($_POST['product_id'] ?? 0);
    wp_delete_post($id, true);
    while ( ob_get_level() > 0 ) { ob_end_clean(); }
    wp_send_json_success(['message' => 'Eliminado']);
}

/**
 * Obtener unidades individuales de un producto (stock_units)
 */
function merc_get_product_units_ajax() {
    $product_id = intval($_POST['product_id'] ?? 0);
    if (!$product_id) wp_send_json_error(['message' => 'ID inválido']);
    
    global $wpdb;
    $table = function_exists('merc_get_stock_table_name') ? merc_get_stock_table_name() : $wpdb->prefix . 'merc_stock_units';
    $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE product_id = %d ORDER BY id ASC", $product_id), ARRAY_A);
    
    foreach ($rows as &$row) {
        $row['tracking'] = '-';
        if (!empty($row['shipment_id'])) {
            $ship = get_post($row['shipment_id']);
            if ($ship) $row['tracking'] = $ship->post_title;
        }
    }
    while ( ob_get_level() > 0 ) { ob_end_clean(); }
    wp_send_json_success($rows);
}

/**
 * Subir o actualizar foto del producto
 */
function merc_subir_foto_producto_ajax() {
    if (isset($_POST['nonce']) && !wp_verify_nonce($_POST['nonce'], 'merc_almacen')) {
        wp_send_json_error(['message' => 'Nonce inválido']);
    }
    $product_id = intval($_POST['product_id'] ?? 0);
    if (empty($_FILES['foto'])) wp_send_json_error(['message' => 'Sin archivo']);
    
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    
    $att_id = media_handle_upload('foto', $product_id);
    if (is_wp_error($att_id)) wp_send_json_error(['message' => 'Error al subir']);
    
    update_post_meta($product_id, '_merc_producto_foto_id', $att_id);
    while ( ob_get_level() > 0 ) { ob_end_clean(); }
    wp_send_json_success(['url' => wp_get_attachment_image_url($att_id, 'thumbnail')]);
}

/**
 * Registrar ingreso de mercadería (suma stock)
 */
function merc_registrar_ingreso_ajax() {
    $pid = intval($_POST['product_id'] ?? 0);
    $qty = intval($_POST['cantidad'] ?? 0);
    if ($pid <= 0 || $qty <= 0) wp_send_json_error(['message' => 'Datos inválidos']);
    
    $actual = function_exists('merc_get_product_stock') ? merc_get_product_stock($pid) : 0;
    $nuevo = $actual + $qty;
    
    if (function_exists('merc_set_product_stock')) merc_set_product_stock($pid, $nuevo);
    if (function_exists('merc_registrar_movimiento')) merc_registrar_movimiento($pid, 'ingreso', $qty, 'ingreso_mercaderia', $_POST['notas'] ?? '');
    
    while ( ob_get_level() > 0 ) { ob_end_clean(); }
    wp_send_json_success(['message' => 'Ingreso registrado', 'nuevo_stock' => $nuevo]);
}

/**
 * Registrar egreso de mercadería (resta stock físico)
 */
function merc_registrar_egreso_ajax() {
    $pid = intval($_POST['product_id'] ?? 0);
    $qty = intval($_POST['cantidad'] ?? 0);
    $motivo = sanitize_text_field($_POST['motivo'] ?? 'ajuste');
    
    global $wpdb;
    $table = function_exists('merc_get_stock_table_name') ? merc_get_stock_table_name() : $wpdb->prefix . 'merc_stock_units';
    
    $ids = $wpdb->get_col($wpdb->prepare(
        "SELECT id FROM {$table} WHERE product_id = %d AND status = 'available' ORDER BY id ASC LIMIT %d",
        $pid, $qty
    ));
    
    if (count($ids) < $qty) wp_send_json_error(['message' => 'No hay suficientes unidades disponibles']);
    
    $wpdb->query("DELETE FROM {$table} WHERE id IN (" . implode(',', $ids) . ")");
    if (function_exists('merc_registrar_movimiento')) merc_registrar_movimiento($pid, 'egreso', $qty, $motivo, $_POST['notas'] ?? '');
    
    $nuevo = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE product_id = %d", $pid)));
    update_post_meta($pid, '_merc_producto_cantidad', $nuevo);
    
    while ( ob_get_level() > 0 ) { ob_end_clean(); }
    wp_send_json_success(['message' => 'Egreso registrado', 'nuevo_stock' => $nuevo]);
}

/**
 * Obtener historial de movimientos
 */
function merc_get_historial_movimientos_ajax() {
    $pid = intval($_POST['product_id'] ?? 0);
    global $wpdb;
    $table = $wpdb->prefix . 'merc_stock_movements';
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT m.*, u.display_name as admin_nombre 
         FROM {$table} m 
         LEFT JOIN {$wpdb->users} u ON m.admin_id = u.ID 
         WHERE m.product_id = %d 
         ORDER BY m.created_at DESC LIMIT 100", 
        $pid
    ), ARRAY_A);
    while ( ob_get_level() > 0 ) { ob_end_clean(); }
    wp_send_json_success(['movimientos' => $rows ?: []]);
}

