<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Gestiona el rol personalizado 'wpcargo_admin'.
 *
 * CARACTERÍSTICAS DEL ROL:
 * - Puede hacer login en WordPress
 * - Puede acceder al dashboard FRONTEND de WPCargo
 * - NO puede acceder a wp-admin (redirigido automáticamente)
 * - Solo ve los módulos que el superadmin le asigne
 *
 * DIFERENCIA CON 'administrator':
 * - administrator → acceso total a wp-admin + dashboard WPCargo
 * - wpcargo_admin → solo dashboard WPCargo (asistentes, operadores)
 */
class WCROL_Rol_WPCargo {

    const SLUG = 'wpcargo_admin';
    const LABEL = 'Administrador WPCargo';
    const BRANCH_MANAGER_SLUG = 'wpcargo_branch_manager';

    /**
     * Capacidades permitidas para wpcargo_admin.
     * Incluye gestión de usuarios, contenido, editorial y capacidades adicionales,
     * pero NO incluye gestión del sistema (manage_options, plugins/temas/core, etc).
     */
    private static function caps_wpcargo_admin(): array {
        return [
            // Base
            'read'                     => true,
            'wpcargo_dashboard_access' => true,
            'manage_options'           => true,

            // Gestión de usuarios
            'list_users'    => true,
            'create_users'  => true,
            'edit_users'    => true,
            'promote_users' => true,
            'remove_users'  => true,
            'delete_users'  => true,

            // Gestión de contenido (posts/pages)
            'edit_posts'             => true,
            'edit_others_posts'      => true,
            'edit_published_posts'   => true,
            'edit_private_posts'     => true,
            'publish_posts'          => true,
            'delete_posts'           => true,
            'delete_others_posts'    => true,
            'delete_published_posts' => true,
            'delete_private_posts'   => true,
            'read_private_posts'     => true,

            'edit_pages'             => true,
            'edit_others_pages'      => true,
            'edit_published_pages'   => true,
            'edit_private_pages'     => true,
            'publish_pages'          => true,
            'delete_pages'           => true,
            'delete_others_pages'    => true,
            'delete_published_pages' => true,
            'delete_private_pages'   => true,
            'read_private_pages'     => true,

            // Administración editorial
            'moderate_comments' => true,
            'manage_categories' => true,
            'manage_links'      => true,
            'edit_dashboard'    => true,
            'upload_files'      => true,
            'import'            => true,
            'export'            => true,

            // Capacidades adicionales detectadas en el sitio
            'wpcode_edit_snippets'     => true,
            'wpcode_activate_snippets' => true,
        ];
    }

    /**
     * Capacidades de gestión del sistema que NO deben estar en wpcargo_admin.
     */
    private static function caps_sistema_excluidas(): array {
        return [
            'update_core',
            'install_plugins', 'activate_plugins', 'update_plugins', 'delete_plugins', 'edit_plugins',
            'install_themes', 'switch_themes', 'update_themes', 'delete_themes', 'edit_themes',
            'edit_files',
            'unfiltered_upload',
            'unfiltered_html',
            'edit_theme_options',
        ];
    }

    /**
     * Crea/sincroniza el rol wpcargo_admin para instalaciones nuevas y existentes.
     */
    private static function sincronizar_rol_wpcargo_admin(): void {
        $role = get_role(self::SLUG);

        if ( ! $role ) {
            add_role(self::SLUG, self::LABEL, self::caps_wpcargo_admin());
            return;
        }

        foreach ( self::caps_wpcargo_admin() as $cap => $grant ) {
            if ( $grant ) {
                $role->add_cap($cap);
            }
        }

        foreach ( self::caps_sistema_excluidas() as $cap ) {
            $role->remove_cap($cap);
        }
    }

    public static function init(): void {
        // Asegurar que el rol exista en runtime (no solo al activar plugin)
        add_action('init', [__CLASS__, 'asegurar_rol_registrado'], 5);
        // Bloquear acceso a wp-admin para usuarios wpcargo_admin
        add_action('admin_init',   [__CLASS__, 'bloquear_wp_admin']);
        // Redirigir login al dashboard frontend
        add_filter('login_redirect',[__CLASS__, 'redirigir_login'], 10, 3);
        // Asegurar que Frontend Manager lo considere en su redirección por roles
        add_filter('wpcfe_login_redirect_dashboard_role', [__CLASS__, 'agregar_rol_en_redireccion'], 10, 1);
        // Inyectar rol al leer la opción de roles permitidos del dashboard
        add_filter('option_wpcfe_access_dashboard_role', [__CLASS__, 'agregar_rol_en_redireccion'], 10, 1);
        // Permitir acceso al dashboard aunque la opción de roles no esté sincronizada
        add_filter('can_wpcfe_access_dashboard', [__CLASS__, 'permitir_dashboard_para_rol'], 10, 1);
        // Refuerzo final: evitar que otro filtro posterior vuelva a false
        add_filter('can_wpcfe_access_dashboard', [__CLASS__, 'permitir_dashboard_para_rol_prioridad_alta'], 9999, 1);
        // Integrar el rol con la lista de acceso del dashboard WPCargo
        add_action('init', [__CLASS__, 'asegurar_acceso_dashboard'], 20);
        // Ocultar barra de admin en frontend para estos usuarios
        add_action('after_setup_theme', [__CLASS__, 'ocultar_admin_bar']);

        // Integración con plugin Escáner: permitir estos roles en su validación.
        add_filter('dhv_scanner_roles', [__CLASS__, 'agregar_roles_en_escaner'], 10, 1);
    }

    /**
     * Agrega roles permitidos para el plugin dhv-escaner-v2.
     */
    public static function agregar_roles_en_escaner( $roles ): array {
        $roles = is_array($roles) ? $roles : [];

        if ( ! in_array(self::SLUG, $roles, true) ) {
            $roles[] = self::SLUG;
        }

        if ( ! in_array(self::BRANCH_MANAGER_SLUG, $roles, true) ) {
            $roles[] = self::BRANCH_MANAGER_SLUG;
        }

        return array_values(array_unique($roles));
    }

    public static function crear_rol(): void {
        // Sincronizar capacidades para asegurar definición actualizada del rol.
        self::sincronizar_rol_wpcargo_admin();

        self::asegurar_acceso_dashboard();
    }

    /**
     * Registra el rol si no existe (defensivo para instalaciones migradas).
     */
    public static function asegurar_rol_registrado(): void {
        self::sincronizar_rol_wpcargo_admin();
    }

    /**
     * Asegura que el rol wpcargo_admin esté incluido en la lista
     * de roles con acceso al dashboard de WPCargo Frontend Manager.
     */
    public static function asegurar_acceso_dashboard(): void {
        $roles = get_option('wpcfe_access_dashboard_role');
        $roles = is_array($roles) ? $roles : [];

        if ( ! in_array(self::SLUG, $roles, true) ) {
            $roles[] = self::SLUG;
            update_option('wpcfe_access_dashboard_role', array_values(array_unique($roles)), false);
        }
    }

    /**
     * Añade el rol en la lista usada por wpcargo-frontend-manager para login redirect.
     */
    public static function agregar_rol_en_redireccion( $roles ): array {
        $roles = is_array($roles) ? $roles : [];
        if ( ! in_array(self::SLUG, $roles, true) ) {
            $roles[] = self::SLUG;
        }
        return array_values(array_unique($roles));
    }

    /**
     * Override defensivo del permiso de acceso al dashboard de WPCargo.
     */
    public static function permitir_dashboard_para_rol( $result ): bool {
        if ( $result ) {
            self::debug_log('can_wpcfe_access_dashboard already true before wcrol filter.');
            return true;
        }
        if ( ! is_user_logged_in() ) {
            self::debug_log('user not logged in.');
            return false;
        }

        $user = wp_get_current_user();
        if ( ! $user || ! ($user instanceof \WP_User) ) {
            self::debug_log('wp_get_current_user invalid user object.');
            return false;
        }

        $is_wpcargo_admin_helper = wcrol_es_wpcargo_admin((int) $user->ID);
        $is_wpcargo_admin_role   = in_array(self::SLUG, (array)$user->roles, true);
        $has_dashboard_cap       = user_can($user, 'wpcargo_dashboard_access');
        $is_wpcargo_admin_meta   = self::tiene_rol_en_capabilities_meta((int) $user->ID);

        self::debug_log('decision context', [
            'user_id' => (int) $user->ID,
            'roles' => (array) $user->roles,
            'helper' => $is_wpcargo_admin_helper,
            'role_match' => $is_wpcargo_admin_role,
            'cap_match' => $has_dashboard_cap,
            'meta_match' => $is_wpcargo_admin_meta,
            'wp_prefix' => isset($GLOBALS['wpdb']) ? $GLOBALS['wpdb']->prefix : '',
        ]);

        if ( $is_wpcargo_admin_helper ) return true;
        if ( $is_wpcargo_admin_role ) return true;
        if ( $has_dashboard_cap ) return true;
        if ( $is_wpcargo_admin_meta ) return true;

        self::debug_log('final decision: false');
        return false;
    }

    /**
     * Misma validación en prioridad alta para garantizar el allow final.
     */
    public static function permitir_dashboard_para_rol_prioridad_alta( $result ): bool {
        return self::permitir_dashboard_para_rol($result);
    }

    /**
     * Verifica el rol directamente en usermeta *_capabilities.
     * Sirve para casos donde WordPress no está reconociendo el rol
     * aunque esté presente en la metadata del usuario.
     */
    private static function tiene_rol_en_capabilities_meta( int $user_id ): bool {
        if ( $user_id <= 0 ) return false;

        global $wpdb;
        $candidate_keys = array_values(array_unique(array_filter([
            $wpdb->prefix . 'capabilities',
            $wpdb->base_prefix . 'capabilities',
            'wp_capabilities',
        ])));

        foreach ( $candidate_keys as $meta_key ) {
            $caps = get_user_meta($user_id, $meta_key, true);
            $caps = maybe_unserialize($caps);
            // Si también tiene administrator, NO tratarlo como wpcargo_admin puro.
            if ( is_array($caps) && ! empty($caps[self::SLUG]) && empty($caps['administrator']) ) {
                return true;
            }
        }

        return false;
    }

    private static function debug_log( string $message, array $context = [] ): void {
        if ( ! defined('WP_DEBUG') || ! WP_DEBUG ) return;
        if ( ! isset($_GET['wcrol_debug']) || $_GET['wcrol_debug'] !== '1' ) return;

        $payload = $context ? ' | ' . wp_json_encode($context) : '';
        error_log('[wcrol] ' . $message . $payload);
    }

    /**
     * Bloquear acceso a wp-admin para rol wpcargo_admin.
     * Excepto peticiones AJAX que son necesarias para el frontend.
     */
    public static function bloquear_wp_admin(): void {
        if ( wp_doing_ajax() ) return;
        if ( ! is_user_logged_in() ) return;
        if ( ! wcrol_es_wpcargo_admin() ) return;

        // Redirigir al dashboard frontend de WPCargo
        $destino = wcrol_frontend_url();
        if ( ! $destino ) $destino = home_url('/');
        wp_safe_redirect($destino);
        exit;
    }

    /**
     * Tras el login, redirigir al dashboard frontend si es wpcargo_admin.
     */
    public static function redirigir_login( string $redirect_to, string $requested, \WP_User|\WP_Error $user ): string {
        if ( is_wp_error($user) ) return $redirect_to;
        if ( ! wcrol_es_wpcargo_admin($user->ID) ) return $redirect_to;
        return wcrol_frontend_url();
    }

    /**
     * Ocultar la barra de administración en el frontend
     * para usuarios wpcargo_admin (no la necesitan).
     */
    public static function ocultar_admin_bar(): void {
        if ( ! is_user_logged_in() ) return;
        if ( wcrol_es_wpcargo_admin() ) {
            show_admin_bar(false);
        }
    }

    /**
     * Cambiar el tipo de acceso de un usuario.
     * 
     * @param int    $user_id
     * @param string $tipo  'wordpress_admin' | 'wpcargo_admin'
     */
    public static function cambiar_tipo( int $user_id, string $tipo ): bool {
        $user = get_userdata($user_id);
        if ( ! $user ) return false;

        // Evitar auto-bloqueo: no permitir que el usuario actual se cambie
        // a wpcargo_admin desde esta acción.
        if ( $user_id === get_current_user_id() && $tipo === 'wpcargo_admin' ) {
            return false;
        }

        if ( $tipo === 'wpcargo_admin' ) {
            // Rol exclusivo para evitar mezclas (ej. administrator + wpcargo_admin)
            $user->set_role(self::SLUG);

            // Si existe el rol del plugin de sucursales, agregarlo también.
            if ( get_role(self::BRANCH_MANAGER_SLUG) ) {
                $user->add_role(self::BRANCH_MANAGER_SLUG);
            }

            self::sincronizar_capabilities_meta($user_id, [ self::SLUG => true ]);

            if ( get_role(self::BRANCH_MANAGER_SLUG) ) {
                self::sincronizar_capabilities_meta($user_id, [
                    self::SLUG => true,
                    self::BRANCH_MANAGER_SLUG => true,
                ]);
            }

            // Al cambiar a wpcargo_admin, otorgar acceso total (sin restricciones)
            // para que vea todos los módulos por defecto
            WCROL_Permisos::quitar_restricciones($user_id);
        } else {
            // Revertir a administrator de WordPress
            $user->set_role('administrator');
            self::sincronizar_capabilities_meta($user_id, [ 'administrator' => true ]);
        }

        // Evitar lecturas de rol en cache obsoleta.
        clean_user_cache($user_id);

        return true;
    }

    /**
     * Sincroniza las metas *_capabilities relevantes para evitar que el usuario
     * quede con roles mezclados por diferencias de prefijo/blog.
     */
    private static function sincronizar_capabilities_meta( int $user_id, array $new_caps ): void {
        if ( $user_id <= 0 ) return;

        global $wpdb;

        $keys = array_values(array_unique(array_filter([
            $wpdb->prefix . 'capabilities',
            $wpdb->base_prefix . 'capabilities',
            'wp_capabilities',
        ])));

        $all_meta = get_user_meta($user_id);
        if ( is_array($all_meta) ) {
            foreach ( $all_meta as $meta_key => $meta_values ) {
                if ( ! is_string($meta_key) || ! str_ends_with($meta_key, '_capabilities') ) {
                    continue;
                }

                $current = isset($meta_values[0]) ? maybe_unserialize($meta_values[0]) : [];
                if ( ! is_array($current) ) {
                    continue;
                }

                // Solo tocar metas donde ya participaba esta lógica de rol.
                if ( isset($current[self::SLUG]) || isset($current['administrator']) ) {
                    $keys[] = $meta_key;
                }
            }
        }

        $keys = array_values(array_unique($keys));
        foreach ( $keys as $meta_key ) {
            update_user_meta($user_id, $meta_key, $new_caps);
        }
    }

    /** Retorna el tipo de acceso como string legible */
    public static function tipo_acceso( int $user_id ): string {
        // Prioridad: administrator siempre gana sobre wpcargo_admin en casos mixtos.
        if ( wcrol_es_wp_admin($user_id) )      return 'wordpress_admin';
        if ( wcrol_es_wpcargo_admin($user_id) ) return 'wpcargo_admin';
        if ( self::tiene_rol_en_capabilities_meta($user_id) ) return 'wpcargo_admin';
        return 'otro';
    }
}

WCROL_Rol_WPCargo::init();
