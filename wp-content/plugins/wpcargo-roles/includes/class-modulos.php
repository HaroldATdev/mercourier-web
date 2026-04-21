<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Catálogo de módulos.
 *
 * ESTRATEGIA:
 * - Guarda módulos provenientes de páginas dashboard.php
 * - Guarda módulos provenientes de plugins / sidebar capturado
 * - Evita duplicados por page_id, sidebar_key y label normalizado
 * - Permite filtrar correctamente módulos aunque usen page-id o sidebar_key
 */
class WCROL_Modulos {

    const OPTION_KEY  = 'wcrol_modulos_catalogo';
    const CAPTURE_KEY = 'wcrol_sidebar_capturado';

    /**
     * Registrar catálogo vacío solo si aún no existe.
     */
    public static function registrar_defaults(): void {
        if ( get_option(self::OPTION_KEY) ) {
            return;
        }

        update_option(self::OPTION_KEY, [], false);
    }

    /**
     * Obtener todos los módulos guardados.
     */
    public static function obtener_todos(): array {
        $guardados = get_option(self::OPTION_KEY, []);
        return is_array($guardados) ? $guardados : [];
    }

    /**
     * Obtener un módulo específico por slug.
     */
    public static function obtener( string $slug ): ?array {
        return self::obtener_todos()[$slug] ?? null;
    }

    /**
     * Normaliza labels para detectar duplicados visuales.
     *
     * Ejemplo:
     * - "<i>📦</i> Almacén de Productos"
     * - "Almacen de Productos"
     * - "almacén de productos"
     *
     * Todos terminan siendo:
     * "almacen de productos"
     */
    private static function normalizar_label( string $label ): string {

        // Eliminar HTML
        $label = wp_strip_all_tags($label);

        // Eliminar spans o badges extra
        $label = preg_replace('/<span.*?<\/span>/i', '', $label);

        // Eliminar emojis y caracteres raros
        $label = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $label);

        // Eliminar acentos
        $label = remove_accents($label);

        // Minúsculas
        $label = strtolower(trim($label));

        // Unificar espacios
        $label = preg_replace('/\s+/', ' ', $label);

        // Normalizaciones manuales para casos conocidos
        $map = [
            'almacen de productos'   => 'almacen de productos',
            'recojo de mercaderia'   => 'recojo de mercaderia',
            'entrega de mercaderia'  => 'entrega de mercaderia',
            'devoluciones'           => 'devoluciones',
            'tracking'               => 'tracking',
            'informe del conductor'  => 'informe del conductor',
            'roles accesos'          => 'roles accesos',
            'roles y accesos'        => 'roles accesos',
        ];

        return $map[$label] ?? $label;
    }

    /**
     * Captura el sidebar real del dashboard WPCargo.
     *
     * Se usa para detectar módulos de plugins que no tienen página dashboard.php.
     */
    public static function capturar_sidebar_real( array $menu ): array {

        if ( ! empty($menu) ) {

            $previo = get_transient(self::CAPTURE_KEY);
            $previo = is_array($previo) ? $previo : [];

            // Fusionar menús para conservar todos los capturados
            $fusion = array_merge($previo, $menu);

            set_transient(self::CAPTURE_KEY, $fusion, DAY_IN_SECONDS);
        }

        return $menu;
    }

    /**
     * Sincroniza módulos automáticamente.
     *
     * Fuentes:
     * - Páginas con template dashboard.php
     * - Sidebar capturado de plugins
     *
     * Evita duplicados por:
     * - page_id
     * - sidebar_key
     * - label normalizado
     */
    public static function sincronizar(): int {

        $catalogo = self::obtener_todos();
        $nuevos   = 0;

        /**
         * Índice de labels ya existentes para evitar duplicados visuales.
         */
        $labels_existentes = [];

        foreach ( $catalogo as $mod ) {
            $label_existente = self::normalizar_label($mod['label'] ?? '');

            if ( $label_existente ) {
                $labels_existentes[$label_existente] = true;
            }
        }

        /**
         * 1. Sincronizar páginas WordPress con template dashboard.php
         */
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

            // Buscar si ya existe por page_id
            $ya_existe = false;

            foreach ( $catalogo as $mod ) {
                if (
                    isset($mod['page_id']) &&
                    (int)$mod['page_id'] === $page->ID
                ) {
                    $ya_existe = true;
                    break;
                }
            }

            if ( $ya_existe ) {
                continue;
            }

            // Evitar duplicados por label
            $label_normalizado = self::normalizar_label($page->post_title);

            if ( isset($labels_existentes[$label_normalizado]) ) {
                continue;
            }

            // Generar slug único
            $slug_mod = 'page_' . $page->ID;

            // Obtener icono desde meta
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

        /**
         * 2. Sincronizar ítems capturados desde el sidebar real
         */
        $capturado = get_transient(self::CAPTURE_KEY);

        // Fallback: intentar capturar en runtime
        if ( ! is_array($capturado) || empty($capturado) ) {

            $capturado = [];

            if ( function_exists('wpcfe_after_sidebar_menu_items') ) {
                $items = wpcfe_after_sidebar_menu_items();

                if ( is_array($items) && ! empty($items) ) {
                    $capturado = array_merge($capturado, $items);
                }
            }

            if ( function_exists('wpcfe_after_sidebar_menus') ) {
                $menus = wpcfe_after_sidebar_menus();

                if ( is_array($menus) && ! empty($menus) ) {
                    $capturado = array_merge($capturado, $menus);
                }
            }

            if ( ! empty($capturado) ) {
                set_transient(self::CAPTURE_KEY, $capturado, DAY_IN_SECONDS);
            }
        }

        if ( is_array($capturado) ) {

            foreach ( $capturado as $sidebar_key => $item ) {

                $page_id = (int)($item['page-id'] ?? 0);

                // Buscar si ya existe por sidebar_key o page_id
                $ya_existe = false;

                foreach ( $catalogo as $mod ) {

                    $match_key = (
                        isset($mod['sidebar_key']) &&
                        $mod['sidebar_key'] === $sidebar_key
                    );

                    $match_pid = (
                        $page_id &&
                        isset($mod['page_id']) &&
                        (int)$mod['page_id'] === $page_id
                    );

                    if ( $match_key || $match_pid ) {
                        $ya_existe = true;
                        break;
                    }
                }

                if ( $ya_existe ) {
                    continue;
                }

                // Si existe page_id y ya existe page_xxx, actualizar sidebar_key
                if ( $page_id ) {

                    $slug_mod = 'page_' . $page_id;

                    if ( isset($catalogo[$slug_mod]) ) {
                        $catalogo[$slug_mod]['sidebar_key'] = $sidebar_key;
                        continue;
                    }
                }

                // Limpiar label para detectar duplicados
                $label_sidebar = $item['label'] ?? $sidebar_key;
                $label_limpio  = trim(wp_strip_all_tags($label_sidebar));
                $label_normalizado = self::normalizar_label($label_sidebar);

                // Evitar duplicados por label
                if ( isset($labels_existentes[$label_normalizado]) ) {
                    continue;
                }

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

        // Si cambió el slug, eliminar el anterior
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

        unset($catalogo[$slug]);

        update_option(self::OPTION_KEY, $catalogo, false);
    }
}