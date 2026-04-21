<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCROL_Permisos {

    // Permisos del dashboard
    const META_KEY = 'wcrol_permisos';

    // NUEVO:
    // Permisos exclusivos del menú lateral
    const META_KEY_SIDEBAR = 'wcrol_sidebar_permisos';

    /** NULL = sin restricción (acceso total) */
    public static function obtener( int $user_id ): ?array {
        $raw = get_user_meta($user_id, self::META_KEY, true);

        if ( $raw === '' || $raw === false ) {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    // NUEVO:
    // Obtener permisos del menú lateral
    public static function obtener_sidebar( int $user_id ): ?array {
        $raw = get_user_meta($user_id, self::META_KEY_SIDEBAR, true);

        if ( $raw === '' || $raw === false ) {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    public static function es_sin_restriccion( int $user_id ): bool {
        return self::obtener($user_id) === null;
    }

    public static function guardar( int $user_id, array $slugs ): void {
        update_user_meta(
            $user_id,
            self::META_KEY,
            wp_json_encode(array_values($slugs))
        );
    }

    // NUEVO:
    // Guardar permisos del menú lateral
    public static function guardar_sidebar( int $user_id, array $slugs ): void {
        update_user_meta(
            $user_id,
            self::META_KEY_SIDEBAR,
            wp_json_encode(array_values($slugs))
        );
    }

    public static function quitar_restricciones( int $user_id ): void {
        delete_user_meta($user_id, self::META_KEY);
    }

    // NUEVO:
    // Eliminar restricciones del menú lateral
    public static function quitar_sidebar( int $user_id ): void {
        delete_user_meta($user_id, self::META_KEY_SIDEBAR);
    }

    public static function obtener_usuarios(): array {
        $users = get_users([
            'role__in' => ['administrator', WCROL_Rol_WPCargo::SLUG],
            'orderby'  => 'display_name',
            'order'    => 'ASC',
            'number'   => 300,
        ]);

        $resultado = [];

        foreach ( $users as $user ) {
            $permisos    = self::obtener($user->ID);
            $tipo        = WCROL_Rol_WPCargo::tipo_acceso($user->ID);

            $resultado[] = [
                'user'            => $user,
                'permisos'        => $permisos,
                'sin_restriccion' => ($permisos === null),
                'num_modulos'     => $permisos === null ? 'Todos' : count($permisos),
                'tipo_acceso'     => $tipo,
            ];
        }

        return $resultado;
    }

    /**
     * Filtra el array del sidebar de WPCargo.
     */
    public static function filtrar_sidebar( array $menu, int $user_id ): array {

        // Permisos normales del dashboard
        $slugs_permitidos = self::obtener($user_id);

        // NUEVO:
        // Permisos exclusivos del menú lateral
        $sidebar_permitidos = self::obtener_sidebar($user_id);

        // Si ambos son null => acceso total
        if ( $slugs_permitidos === null && $sidebar_permitidos === null ) {
            return $menu;
        }

        $catalogo = WCROL_Modulos::obtener_todos();

        $page_ids_permitidos     = [];
        $sidebar_keys_permitidos = [];
        $sidebar_lookup          = [];

        // Construir lookup de módulos dashboard
        if ( is_array($slugs_permitidos) ) {
            foreach ( $slugs_permitidos as $slug ) {

                if ( ! isset($catalogo[$slug]) ) {
                    continue;
                }

                $mod = $catalogo[$slug];

                if ( ! empty($mod['page_id']) ) {
                    $page_ids_permitidos[(int)$mod['page_id']] = true;
                }

                if ( ! empty($mod['sidebar_key']) ) {
                    $sidebar_keys_permitidos[$mod['sidebar_key']] = true;
                }
            }
        }

        // NUEVO:
        // Construir lookup de sidebar permitido
        if ( is_array($sidebar_permitidos) ) {
            foreach ( $sidebar_permitidos as $slug ) {
                $sidebar_lookup[$slug] = true;
            }
        }

        $filtrado = [];

        foreach ( $menu as $key => $item ) {

            $page_id   = (int)($item['page-id'] ?? 0);
            $permitido = false;

            // Validar dashboard por page_id
            if ( $page_id && isset($page_ids_permitidos[$page_id]) ) {
                $permitido = true;
            }

            // Validar dashboard por sidebar_key
            elseif ( isset($sidebar_keys_permitidos[$key]) ) {
                $permitido = true;
            }

            // NUEVO:
            // Validar menú lateral
            if ( $permitido && is_array($sidebar_permitidos) ) {
                $permitido = isset($sidebar_lookup[$key]);
            }

            if ( $permitido ) {
                $filtrado[$key] = $item;
            }
        }

        return $filtrado;
    }
}