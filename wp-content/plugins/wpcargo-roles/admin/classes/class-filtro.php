<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCROL_Filtro {

    public function __construct() {
        // Prioridad 0: solo observar y capturar (nunca modifica)
        add_filter('wpcfe_after_sidebar_menu_items', [WCROL_Modulos::class, 'capturar_sidebar_real'], 0, 1);
        add_filter('wpcfe_after_sidebar_menus', [WCROL_Modulos::class, 'capturar_sidebar_real'], 0, 1);

        // Prioridad 999: filtrar según permisos del usuario
        add_filter('wpcfe_after_sidebar_menu_items', [$this, 'filtrar'],  999, 1);
        add_filter('wpcfe_after_sidebar_menus', [$this, 'filtrar'],  999, 1);
        add_filter('wpcfe_sidebar_menus',        [$this, 'filtrar'],  999, 1);

        // Ocultar ítems del core hardcodeados en el frontend si no hay permiso
        add_action('wp_footer', [$this, 'ocultar_items_core'], 999);
    }

    public function filtrar( array $menu ): array {
        if ( ! is_user_logged_in() ) return $menu;
        $user_id = get_current_user_id();

        // WP Admins sin restricciones configuradas: acceso total
        if ( wcrol_puede_gestionar() && WCROL_Permisos::es_sin_restriccion($user_id) ) {
            return $menu;
        }

        return WCROL_Permisos::filtrar_sidebar($menu, $user_id);
    }

    public function ocultar_items_core(): void {
        if ( ! is_user_logged_in() ) return;
        $user_id = get_current_user_id();

        // Si es admin y no tiene restricciones, mostrar todo
        if ( wcrol_puede_gestionar() && WCROL_Permisos::es_sin_restriccion($user_id) ) return;

        $slugs_permitidos = WCROL_Permisos::obtener($user_id);
        
        // Si no tiene restricciones, mostrar todo
        if ( $slugs_permitidos === null ) return;

        $hide_css = '';
        $catalogo = WCROL_Modulos::obtener_todos();

        // Mapeo manual de URLs conocidas para asegurar que el CSS enganche
        // (útil para ítems de menú de WordPress nativo que no tienen la clase del slug)
        $url_map = [
            'core_crear'             => 'wpcfe=add',
            'merc-almacen-productos' => '/almacen-de-productos',
            'merc-panel-admin'       => '/panel-admin',
            'merc_devoluciones'      => '/devoluciones',
            'receiving-menu'         => '/receiving',
            'wpcsc-menu'             => '/containers',
            'user-menu'              => '/wpcumanage-users',
            'wcmas-masivos'          => '/envios-masivos',
            'wcrol-roles'            => '/roles-accesos',
            'la-anuncios'            => '/anuncios',
            'wpcpod-menu'            => '/pod-report',
            'wpcpod-pickup-route'    => '/pickup',
            'wpcpod-route'           => '/pod-route',
        ];

        // Obtener la URL dinámica de Horarios y Bloqueos para ocultarlo correctamente si está desmarcado
        $url_horarios = get_option('merc_bloqueos_frontend_url', '');
        if ($url_horarios) {
            $parsed = wp_parse_url($url_horarios);
            if (!empty($parsed['path'])) {
                // Agregar al mapa la ruta sin trailing slash para mayor coincidencia
                $url_map['merc-bloqueos'] = rtrim($parsed['path'], '/');
            }
        }

        foreach ( $catalogo as $slug => $mod ) {
            if ( ! in_array($slug, $slugs_permitidos, true) ) {
                
                // Si es "Escritorio", el link tiene ?wpcfe=dashboard o ícono de cubos (evitando Historial)
                if ( $slug === 'core_escritorio' ) {
                    $hide_css .= '.sidebar-fixed a.list-group-item[href*="wpcfe=dashboard"], .mobile-sidebar-menu a.list-group-item[href*="wpcfe=dashboard"] { display: none !important; }';
                    $hide_css .= '.sidebar-fixed a.list-group-item:not([href*="="]):has(.fa-cubes), .mobile-sidebar-menu a.list-group-item:not([href*="="]):has(.fa-cubes) { display: none !important; }';
                    continue;
                }

                // Si es "Historial de Envíos" (que es /dashboard/ pelado)
                if ( $slug === 'history' ) {
                    // Historial no tiene "=" en la URL, pero NO es Escritorio
                    // Para ser seguros, ocultamos el que tiene class history o termina en /dashboard/ exactamente
                    $hide_css .= '.sidebar-fixed a.list-group-item.history, .mobile-sidebar-menu a.list-group-item.history { display: none !important; }';
                    $hide_css .= '.sidebar-fixed a.list-group-item[href$="/dashboard/"], .mobile-sidebar-menu a.list-group-item[href$="/dashboard/"] { display: none !important; }';
                    continue;
                }

                // Selector 1: por clase (WPCargo añade el slug como clase a sus items de array)
                $selectors = [
                    '.sidebar-fixed a.list-group-item.' . $slug,
                    '.mobile-sidebar-menu a.list-group-item.' . $slug
                ];

                // Selector 2: por URL conocida (si está en el mapa)
                if ( isset($url_map[$slug]) ) {
                    $selectors[] = '.sidebar-fixed a.list-group-item[href*="' . $url_map[$slug] . '"]';
                    $selectors[] = '.mobile-sidebar-menu a.list-group-item[href*="' . $url_map[$slug] . '"]';
                }

                $hide_css .= implode(', ', $selectors) . ' { display: none !important; } ';
            }
        }

        // Además, nos aseguramos de que el escritorio no oculte "Historial de envíos" si Historial sí está permitido
        if ( in_array('history', $slugs_permitidos, true) && ! in_array('core_escritorio', $slugs_permitidos, true) ) {
            // Forzar a mostrar Historial si Escritorio lo ocultó por error (ej. usando .fa-cubes)
            $hide_css .= '.sidebar-fixed a.list-group-item[href$="/dashboard/"], .mobile-sidebar-menu a.list-group-item[href$="/dashboard/"] { display: block !important; }';
        }

        if ( $hide_css ) {
            echo '<style type="text/css">/* WPCargo Roles & Accesos */ ' . $hide_css . '</style>';
        }
    }
}

new WCROL_Filtro();

