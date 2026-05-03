<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Catálogo de módulos.
 *
 * ESTRATEGIA ACTUALIZADA:
 * - Catálogo base de 16 módulos estáticos siempre presentes.
 * - Sincronización permite agregar nuevos pero nunca borra los base.
 */
class WCROL_Modulos {

    const OPTION_KEY  = 'wcrol_modulos_catalogo';
    const CAPTURE_KEY = 'wcrol_sidebar_capturado';

    /**
     * Módulos base garantizados (Los 16 del menú lateral).
     */
    public static function get_modulos_base(): array {
        return [
            'core_escritorio' => [
                'slug'        => 'core_escritorio',
                'label'       => 'Escritorio',
                'icon'        => 'fa-cubes',
                'fuente'      => 'wpcargo_core',
                'page_id'     => 0,
                'sidebar_key' => 'core_escritorio',
            ],
            'core_crear' => [
                'slug'        => 'core_crear',
                'label'       => 'Crear servicio',
                'icon'        => 'fa-plus',
                'fuente'      => 'wpcargo_core',
                'page_id'     => 0,
                'sidebar_key' => 'core_crear',
            ],
            'history' => [
                'slug'        => 'history',
                'label'       => 'Historial de Envíos',
                'icon'        => 'fa-history',
                'fuente'      => 'plugin',
                'page_id'     => 0,
                'sidebar_key' => 'history',
            ],
            'receiving-menu' => [
                'slug'        => 'receiving-menu',
                'label'       => 'Escáner',
                'icon'        => 'fa-barcode',
                'fuente'      => 'plugin',
                'page_id'     => 0,
                'sidebar_key' => 'receiving-menu',
            ],
            'merc-almacen-productos' => [
                'slug'        => 'merc-almacen-productos',
                'label'       => 'Almacén de Productos',
                'icon'        => 'fa-building',
                'fuente'      => 'plugin',
                'page_id'     => 0,
                'sidebar_key' => 'merc-almacen-productos',
            ],
            'merc-panel-admin' => [
                'slug'        => 'merc-panel-admin',
                'label'       => 'Finanzas Admin',
                'icon'        => 'fa-money',
                'fuente'      => 'plugin',
                'page_id'     => 0,
                'sidebar_key' => 'merc-panel-admin',
            ],
            'merc_devoluciones' => [
                'slug'        => 'merc_devoluciones',
                'label'       => 'Devoluciones',
                'icon'        => 'fa-undo',
                'fuente'      => 'plugin',
                'page_id'     => 0,
                'sidebar_key' => 'merc_devoluciones',
            ],
            'wpcsc-menu' => [
                'slug'        => 'wpcsc-menu',
                'label'       => 'Contenedores',
                'icon'        => 'fa-truck',
                'fuente'      => 'plugin',
                'page_id'     => 0,
                'sidebar_key' => 'wpcsc-menu',
            ],
            'wpcpod-route' => [
                'slug'        => 'wpcpod-route',
                'label'       => 'Entrega de mercadería',
                'icon'        => 'fa-check-square-o',
                'fuente'      => 'plugin',
                'page_id'     => 0,
                'sidebar_key' => 'wpcpod-route',
            ],
            'wpcpod-pickup-route' => [
                'slug'        => 'wpcpod-pickup-route',
                'label'       => 'Recojo de mercadería',
                'icon'        => 'fa-address-book',
                'fuente'      => 'plugin',
                'page_id'     => 0,
                'sidebar_key' => 'wpcpod-pickup-route',
            ],
            'wpcpod-menu' => [
                'slug'        => 'wpcpod-menu',
                'label'       => 'Informe del conductor',
                'icon'        => 'fa-file-text-o',
                'fuente'      => 'plugin',
                'page_id'     => 0,
                'sidebar_key' => 'wpcpod-menu',
            ],
            'wcrol-roles' => [
                'slug'        => 'wcrol-roles',
                'label'       => 'Roles & Accesos',
                'icon'        => 'fa-shield',
                'fuente'      => 'plugin',
                'page_id'     => 0,
                'sidebar_key' => 'wcrol-roles',
            ],
            'wcmas-masivos' => [
                'slug'        => 'wcmas-masivos',
                'label'       => 'Envíos Masivos',
                'icon'        => 'fa-upload',
                'fuente'      => 'plugin',
                'page_id'     => 0,
                'sidebar_key' => 'wcmas-masivos',
            ],
            'la-anuncios' => [
                'slug'        => 'la-anuncios',
                'label'       => 'Anuncios',
                'icon'        => 'fa-bullhorn',
                'fuente'      => 'plugin',
                'page_id'     => 0,
                'sidebar_key' => 'la-anuncios',
            ],
            'merc-bloqueos' => [
                'slug'        => 'merc-bloqueos',
                'label'       => 'Horarios y Bloqueos',
                'icon'        => 'fa-calendar-times-o',
                'fuente'      => 'plugin',
                'page_id'     => 0,
                'sidebar_key' => 'merc-bloqueos',
            ],
            'user-menu' => [
                'slug'        => 'user-menu',
                'label'       => 'Usuarios',
                'icon'        => 'fa-users',
                'fuente'      => 'plugin',
                'page_id'     => 0,
                'sidebar_key' => 'user-menu',
            ],
        ];
    }

    /**
     * Registrar catálogo vacío solo si aún no existe.
     */
    public static function registrar_defaults(): void {
        if ( get_option(self::OPTION_KEY) ) {
            return;
        }

        update_option(self::OPTION_KEY, self::get_modulos_base(), false);
    }

    /**
     * Obtener todos los módulos guardados + los base siempre.
     */
    public static function obtener_todos(): array {
        $guardados = get_option(self::OPTION_KEY, []);
        $guardados = is_array($guardados) ? $guardados : [];
        
        // Filtrar toda la basura de sincronizaciones antiguas (solo conservar manuales limpios)
        $manuales = [];
        foreach ($guardados as $k => $v) {
            if ( is_array($v) && isset($v['slug']) && ($v['fuente'] ?? '') === 'manual' ) {
                $manuales[$k] = $v;
            }
        }
        
        // Siempre garantizar los 16 base por si acaso fueron borrados accidentalmente
        $base = self::get_modulos_base();
        return array_merge($base, $manuales);
    }

    /**
     * Obtener un módulo específico por slug.
     */
    public static function obtener( string $slug ): ?array {
        return self::obtener_todos()[$slug] ?? null;
    }

    /**
     * Normaliza labels para detectar duplicados visuales.
     */
    private static function normalizar_label( string $label ): string {
        $label = wp_strip_all_tags($label);
        $label = preg_replace('/<span.*?<\/span>/i', '', $label);
        $label = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $label);
        $label = remove_accents($label);
        $label = strtolower(trim($label));
        $label = preg_replace('/\s+/', ' ', $label);

        $map = [
            'almacen de productos'   => 'almacen de productos',
            'recojo de mercaderia'   => 'recojo de mercaderia',
            'entrega de mercaderia'  => 'entrega de mercaderia',
            'devoluciones'           => 'devoluciones',
            'tracking'               => 'tracking',
            'informe del conductor'  => 'informe del conductor',
            'roles accesos'          => 'roles accesos',
            'roles y accesos'        => 'roles accesos',
            'escaner'                => 'escaner',
            'receiving'              => 'escaner',
        ];

        return $map[$label] ?? $label;
    }

    /**
     * Captura el sidebar real del dashboard WPCargo.
     */
    public static function capturar_sidebar_real( array $menu ): array {
        if ( ! empty($menu) ) {
            $previo = get_transient(self::CAPTURE_KEY);
            $previo = is_array($previo) ? $previo : [];
            $fusion = array_merge($previo, $menu);
            set_transient(self::CAPTURE_KEY, $fusion, DAY_IN_SECONDS);
        }
        return $menu;
    }

    /**
     * Sincroniza módulos automáticamente.
     */
    public static function sincronizar(): int {

        $catalogo = self::obtener_todos();
        $nuevos   = 0;
        $labels_existentes = [];

        foreach ( $catalogo as $mod ) {
            $label_existente = self::normalizar_label($mod['label'] ?? '');
            if ( $label_existente ) {
                $labels_existentes[$label_existente] = true;
            }
        }

        // 1. Sincronizar páginas WordPress con template dashboard.php
        $pages = get_posts([
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [[
                'key'   => '_wp_page_template',
                'value' => 'dashboard.php',
            ]],
        ]);

        foreach ( $pages as $page ) {
            $ya_existe = false;

            foreach ( $catalogo as $mod ) {
                if ( isset($mod['page_id']) && (int)$mod['page_id'] === $page->ID ) {
                    $ya_existe = true;
                    break;
                }
            }

            if ( $ya_existe ) continue;

            $label_normalizado = self::normalizar_label($page->post_title);

            if ( isset($labels_existentes[$label_normalizado]) ) continue;

            $slug_mod = 'page_' . $page->ID;

            $icon_raw = get_post_meta($page->ID, 'wpcfe_menu_icon', true) ?: '';
            preg_match('/fa-[\w-]+/', $icon_raw, $m);
            $icon = $m[0] ?? 'fa-circle-o';

            $catalogo[$slug_mod] = [
                'slug'        => $slug_mod,
                'label'       => trim($page->post_title),
                'icon'        => $icon,
                'fuente'      => 'pagina',
                'page_id'     => $page->ID,
                'sidebar_key' => '',
            ];

            $labels_existentes[$label_normalizado] = true;
            $nuevos++;
        }

        // 2. Sincronizar ítems capturados desde el sidebar real
        $capturado = get_transient(self::CAPTURE_KEY);

        if ( ! is_array($capturado) || empty($capturado) ) {
            $capturado = [];
            if ( function_exists('wpcfe_after_sidebar_menu_items') ) {
                $items = wpcfe_after_sidebar_menu_items();
                if ( is_array($items) && ! empty($items) ) $capturado = array_merge($capturado, $items);
            }
            if ( function_exists('wpcfe_after_sidebar_menus') ) {
                $menus = wpcfe_after_sidebar_menus();
                if ( is_array($menus) && ! empty($menus) ) $capturado = array_merge($capturado, $menus);
            }
            
            // Captura de custom actions (ej. merc-bloqueos)
            ob_start();
            do_action('wpcfe_after_sidebar_custom_menu');
            $custom_html = ob_get_clean();
            
            if ( ! empty($capturado) ) {
                set_transient(self::CAPTURE_KEY, $capturado, DAY_IN_SECONDS);
            }
        }

        if ( is_array($capturado) ) {
            foreach ( $capturado as $sidebar_key => $item ) {
                $page_id = (int)($item['page-id'] ?? 0);
                $ya_existe = false;

                foreach ( $catalogo as $mod ) {
                    $match_key = (isset($mod['sidebar_key']) && $mod['sidebar_key'] === $sidebar_key);
                    $match_pid = ($page_id && isset($mod['page_id']) && (int)$mod['page_id'] === $page_id);
                    if ( $match_key || $match_pid ) {
                        $ya_existe = true;
                        break;
                    }
                }

                if ( $ya_existe ) continue;

                if ( $page_id ) {
                    $slug_mod = 'page_' . $page_id;
                    if ( isset($catalogo[$slug_mod]) ) {
                        $catalogo[$slug_mod]['sidebar_key'] = $sidebar_key;
                        continue;
                    }
                }

                $label_sidebar = $item['label'] ?? $sidebar_key;
                $label_limpio  = trim(wp_strip_all_tags($label_sidebar));
                $label_normalizado = self::normalizar_label($label_sidebar);

                if ( isset($labels_existentes[$label_normalizado]) ) continue;

                $slug_mod = sanitize_key($sidebar_key) ?: 'item_' . md5($sidebar_key);

                $icon_raw = $item['icon'] ?? '';
                preg_match('/fa-[\w-]+/', $icon_raw, $m);

                $catalogo[$slug_mod] = [
                    'slug'        => $slug_mod,
                    'label'       => $label_limpio,
                    'icon'        => $m[0] ?? ($icon_raw ?: 'fa-circle-o'),
                    'fuente'      => 'plugin',
                    'page_id'     => $page_id,
                    'sidebar_key' => $sidebar_key,
                ];

                $labels_existentes[$label_normalizado] = true;
                $nuevos++;
            }
        }

        update_option(self::OPTION_KEY, $catalogo, false);

        return $nuevos;
    }

    /**
     * Guardar o editar un módulo manualmente.
     */
    public static function guardar( array $datos, string $slug_original = '' ): true|\WP_Error {
        $slug = $datos['slug'] ?? '';
        if ( ! $slug || ! ($datos['label'] ?? '') ) {
            return new \WP_Error('req', 'Campos obligatorios.');
        }

        $catalogo = self::obtener_todos();

        if ( $slug_original && $slug_original !== $slug ) {
            unset($catalogo[$slug_original]);
        }

        $catalogo[$slug] = array_merge($catalogo[$slug] ?? [], [
            'slug'        => $slug,
            'label'       => sanitize_text_field($datos['label']),
            'icon'        => sanitize_text_field($datos['icon'] ?? 'fa-circle-o'),
            'page_id'     => intval($datos['page_id'] ?? 0),
            'sidebar_key' => sanitize_text_field($datos['sidebar_key'] ?? ''),
            'fuente'      => $catalogo[$slug]['fuente'] ?? 'manual',
        ]);

        update_option(self::OPTION_KEY, $catalogo, false);
        return true;
    }

    /**
     * Eliminar módulo.
     */
    public static function eliminar( string $slug ): void {
        $catalogo = self::obtener_todos();
        
        // No permitir eliminar los base
        $base = self::get_modulos_base();
        if ( isset($base[$slug]) ) return;

        unset($catalogo[$slug]);
        update_option(self::OPTION_KEY, $catalogo, false);
    }
}
