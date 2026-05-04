<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCROL_Permisos {

    // Permisos del dashboard y sidebar unificados
    const META_KEY = 'wcrol_permisos';

    /** NULL = sin restricción (acceso total) */
    public static function obtener( int $user_id ): ?array {
        $raw = get_user_meta($user_id, self::META_KEY, true);

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



    public static function quitar_restricciones( int $user_id ): void {
        delete_user_meta($user_id, self::META_KEY);
    }


    /**
     * Ordena: primero wpcargo_admin (por número de módulos descendente),
     * al final wordpress_admin (que el wpcargo_admin no puede editar).
     */
    private static function ordenar( array $resultado ): array {
        usort($resultado, function( $a, $b ) {
            $a_wp = ($a['tipo_acceso'] === 'wordpress_admin') ? 1 : 0;
            $b_wp = ($b['tipo_acceso'] === 'wordpress_admin') ? 1 : 0;

            // WP admins al final
            if ( $a_wp !== $b_wp ) {
                return $a_wp - $b_wp;
            }

            // Dentro del mismo grupo: más módulos primero
            // Los 'sin restricción' se tratan como máximo
            $a_num = $a['sin_restriccion'] ? PHP_INT_MAX : (int) $a['num_modulos'];
            $b_num = $b['sin_restriccion'] ? PHP_INT_MAX : (int) $b['num_modulos'];

            return $b_num - $a_num; // descendente
        });

        return $resultado;
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

        return self::ordenar($resultado);
    }

    /**
     * Filtra el array del sidebar de WPCargo.
     */
    public static function filtrar_sidebar( array $menu, int $user_id ): array {

        $slugs_permitidos = self::obtener($user_id);

        if ( $slugs_permitidos === null ) {
            return $menu;
        }

        $catalogo = WCROL_Modulos::obtener_todos();
        $page_ids_permitidos     = [];
        $sidebar_keys_permitidos = [];

        if ( is_array($slugs_permitidos) ) {
            foreach ( $slugs_permitidos as $slug ) {
                if ( ! isset($catalogo[$slug]) ) continue;

                $mod = $catalogo[$slug];
                if ( ! empty($mod['page_id']) ) {
                    $page_ids_permitidos[(int)$mod['page_id']] = true;
                }
                if ( ! empty($mod['sidebar_key']) ) {
                    $sidebar_keys_permitidos[$mod['sidebar_key']] = true;
                }
            }
        }

        $filtrado = [];

        foreach ( $menu as $key => $item ) {
            $page_id   = (int)($item['page-id'] ?? 0);
            $permitido = false;

            if ( $page_id && isset($page_ids_permitidos[$page_id]) ) {
                $permitido = true;
            } elseif ( isset($sidebar_keys_permitidos[$key]) ) {
                $permitido = true;
            }

            if ( $permitido ) {
                $filtrado[$key] = $item;
            }
        }

        return $filtrado;
    }
}

