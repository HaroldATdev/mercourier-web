<?php
if ( ! defined('ABSPATH') ) exit;

class WCMAS_Admin {

    public function __construct() {
        add_action('admin_menu',    [$this, 'registrar_menu']);
        add_action('admin_notices', [$this, 'mostrar_notice']);

        add_action('admin_post_wcmas_guardar_columna',  [$this, 'handle_guardar_columna']);
        add_action('admin_post_wcmas_eliminar_columna', [$this, 'handle_eliminar_columna']);
        add_action('admin_post_wcmas_reordenar',        [$this, 'handle_reordenar']);
        add_action('admin_post_wcmas_guardar_config',   [$this, 'handle_guardar_config']);
        add_action('admin_post_wcmas_reset_columnas',   [$this, 'handle_reset_columnas']);

        // AJAX: accesible para cualquier usuario logueado con permiso
        add_action('wp_ajax_wcmas_procesar_lote',  [$this, 'ajax_procesar_lote']);
        add_action('wp_ajax_wcmas_validar_fila',   [$this, 'ajax_validar_fila']);
    }

    public function registrar_menu(): void {
        add_menu_page('Envíos Masivos','Envíos Masivos','manage_options',
            'wcmas-grilla',[$this,'pagina_grilla_admin'],'dashicons-grid-view', 58);
        add_submenu_page('wcmas-grilla','Carga Masiva (Admin)','Carga Masiva',
            'manage_options','wcmas-grilla',[$this,'pagina_grilla_admin']);
        add_submenu_page('wcmas-grilla','Configurar Columnas','Columnas',
            'manage_options','wcmas-columnas',[$this,'pagina_columnas']);
        add_submenu_page('wcmas-grilla','Historial de Importaciones','Historial',
            'manage_options','wcmas-historial',[$this,'pagina_historial']);
        add_submenu_page('wcmas-grilla','Configuración','Configuración',
            'manage_options','wcmas-config',[$this,'pagina_config']);
    }

    public function pagina_grilla_admin(): void {
        if ( ! current_user_can('manage_options') ) wp_die();
        $columnas  = WCMAS_Columnas::obtener_activas();
        $todas     = WCMAS_Columnas::obtener_todas();
        $usuarios  = wcmas_get_usuarios_select();
        $nonce     = wp_create_nonce('wcmas_procesar_nonce');
        $filas_init= max(5, intval(get_option('wcmas_filas_default', 10)));
        $historial = WCMAS_Historial::obtener(5, 0, 0);
        $es_admin  = true;
        wcmas_tpl('admin/grilla-admin.tpl.php', compact('columnas','todas','usuarios','nonce','filas_init','es_admin','historial'));
    }

    public function pagina_columnas(): void {
        if ( ! current_user_can('manage_options') ) wp_die();
        $edit_id  = sanitize_key($_GET['editar'] ?? '');
        $columna  = $edit_id ? WCMAS_Columnas::obtener_por_id($edit_id) : null;
        $columnas = WCMAS_Columnas::obtener_todas();
        wcmas_tpl('admin/columnas.tpl.php', compact('edit_id','columna','columnas'));
    }

    public function pagina_historial(): void {
        if ( ! current_user_can('manage_options') ) wp_die();
        $ver_id   = intval($_GET['ver'] ?? 0);
        $detalle  = $ver_id ? WCMAS_Historial::obtener_por_id($ver_id) : null;
        $page_num = max(1, intval($_GET['paged'] ?? 1));
        $per_page = 20;
        $total    = WCMAS_Historial::total();
        $lista    = WCMAS_Historial::obtener($per_page, ($page_num - 1) * $per_page);
        wcmas_tpl('admin/historial.tpl.php', compact('lista','detalle','total','page_num','per_page'));
    }

    public function pagina_config(): void {
        if ( ! current_user_can('manage_options') ) wp_die();
        $tracking_prefix = get_option('wcmas_tracking_prefix', 'LISTO');
        $filas_default   = intval(get_option('wcmas_filas_default', 10));
        wcmas_tpl('admin/config.tpl.php', compact('tracking_prefix','filas_default'));
    }

    /* ── Handlers POST ─────────────────────────────────────────────── */

    public function handle_reset_columnas(): void {
        check_admin_referer('wcmas_reset_columnas');
        if ( ! current_user_can('manage_options') ) wp_die();
        delete_option( WCMAS_Columnas::OPTION_KEY );
        WCMAS_Columnas::instalar_defaults();
        wcmas_redirect('wcmas-columnas', 'reset');
    }

    public function handle_guardar_columna(): void {
        check_admin_referer('wcmas_columna_nonce');
        if ( ! current_user_can('manage_options') ) wp_die();
        $id_orig = sanitize_key($_POST['id_original'] ?? '');
        $r = WCMAS_Columnas::guardar([
            'id'          => sanitize_key($_POST['id'] ?? ''),
            'label'       => sanitize_text_field(wp_unslash($_POST['label'] ?? '')),
            'meta_key'    => sanitize_text_field(wp_unslash($_POST['meta_key'] ?? '')),
            'tipo'        => sanitize_key($_POST['tipo'] ?? 'text'),
            'activa'      => !empty($_POST['activa']),
            'obligatorio' => !empty($_POST['obligatorio']),
            'default_val' => sanitize_text_field(wp_unslash($_POST['default_val'] ?? '')),
            'opciones'    => sanitize_textarea_field(wp_unslash($_POST['opciones'] ?? '')),
            'placeholder' => sanitize_text_field(wp_unslash($_POST['placeholder'] ?? '')),
            'ancho'       => sanitize_key($_POST['ancho'] ?? 'md'),
        ], $id_orig);
        wcmas_redirect('wcmas-columnas', is_wp_error($r) ? 'error_req' : 'guardado');
    }

    public function handle_eliminar_columna(): void {
        check_admin_referer('wcmas_columna_nonce');
        if ( ! current_user_can('manage_options') ) wp_die();
        WCMAS_Columnas::eliminar(sanitize_key($_POST['id'] ?? ''));
        wcmas_redirect('wcmas-columnas', 'eliminado');
    }

    public function handle_reordenar(): void {
        check_admin_referer('wcmas_reordenar_nonce');
        if ( ! current_user_can('manage_options') ) wp_die();
        WCMAS_Columnas::reordenar(array_map('sanitize_key', $_POST['orden'] ?? []));
        wp_send_json_success();
    }

    public function handle_guardar_config(): void {
        check_admin_referer('wcmas_config_nonce');
        if ( ! current_user_can('manage_options') ) wp_die();
        update_option('wcmas_tracking_prefix', strtoupper(sanitize_text_field($_POST['tracking_prefix'] ?? 'LISTO')));
        update_option('wcmas_filas_default',   max(1, intval($_POST['filas_default'] ?? 10)));
        wcmas_redirect('wcmas-config', 'guardado');
    }

    /* ── AJAX ──────────────────────────────────────────────────────── */

    public function ajax_procesar_lote(): void {
        check_ajax_referer('wcmas_procesar_nonce', 'nonce');
        if ( ! wcmas_puede_crear() ) wp_send_json_error(['msg'=>'Sin permisos.'], 403);

        $filas = $_POST['filas'] ?? [];
        if ( ! is_array($filas) || empty($filas) ) {
            wp_send_json_error(['msg'=>'No hay filas para procesar.']);
        }

        // El remitente se define por fila en la columna registered_shipper.
        $user_id_destino = get_current_user_id();

        // Sanitizar filas
        $filas_limpias = array_map(function($fila) {
            if ( ! is_array($fila) ) return [];
            return array_map(fn($v) => sanitize_text_field(wp_unslash($v)), $fila);
        }, $filas);

        $resultados = WCMAS_Procesador::procesar_lote($filas_limpias, $user_id_destino);

        $ok_count  = count(array_filter($resultados, fn($r) => $r['ok']));
        $err_count = count($resultados) - $ok_count;

        // Registrar en historial
        WCMAS_Historial::registrar($resultados, get_current_user_id(), $user_id_destino);

        // Preparar respuesta con label del primer campo para mostrar en resultado
        $primer_col = array_values(WCMAS_Columnas::obtener_activas())[0] ?? null;
        $resp = array_map(function($r) use ($primer_col) {
            return [
                'ok'       => $r['ok'],
                'fila_num' => $r['fila_num'],
                'tracking' => $r['tracking'] ?? '',
                'post_id'  => $r['post_id']  ?? 0,
                'errores'  => $r['errores']  ?? [],
                'label'    => $primer_col ? ($r['datos'][$primer_col['id']] ?? '') : '',
            ];
        }, $resultados);

        wp_send_json_success([
            'resultados'    => $resp,
            'ok'            => $ok_count,
            'errores'       => $err_count,
            'asignado_a_id' => $user_id_destino,
        ]);
    }

    public function ajax_validar_fila(): void {
        check_ajax_referer('wcmas_procesar_nonce', 'nonce');
        if ( ! wcmas_puede_crear() ) wp_send_json_error([], 403);
        $fila   = array_map(fn($v) => sanitize_text_field(wp_unslash($v)), $_POST['fila'] ?? []);
        $errors = WCMAS_Procesador::validar_fila($fila);
        wp_send_json_success(['errores' => $errors, 'valida' => empty($errors)]);
    }

    public function mostrar_notice(): void {
        $key = sanitize_key($_GET['wcmas_msg'] ?? '');
        if (!$key) return;
        $msgs = [
            'guardado'  => ['success', 'Guardado correctamente.'],
            'eliminado' => ['success', 'Eliminado correctamente.'],
            'error_req' => ['error',   'Faltan campos obligatorios.'],
            'reset'     => ['success', 'Columnas restauradas a los valores por defecto.'],
        ];
        if ( isset($msgs[$key]) ) {
            [$t,$m] = $msgs[$key];
            echo "<div class='notice notice-{$t} is-dismissible'><p>{$m}</p></div>";
        }
    }
}

new WCMAS_Admin();
