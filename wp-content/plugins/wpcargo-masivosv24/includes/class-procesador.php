<?php
if ( ! defined('ABSPATH') ) exit;

class WCMAS_Procesador {

    public static function procesar_fila( array $fila, int $user_id = 0 ): array {
        $columnas = WCMAS_Columnas::obtener_activas();
        $errores  = [];
        $meta     = [];
        $author   = $user_id ?: get_current_user_id();

        // ── Determinar tipo de entrega ──────────────────────────────────────
        $tipo_programado  = trim($fila['tipo_programado'] ?? '');
        $tipo_lower       = strtolower($tipo_programado);
        $es_domicilio     = ( $tipo_lower === 'domicilio' );
        $es_mercado_flex  = ( $tipo_lower === 'mercado flex' );

        foreach ( $columnas as $col ) {
            $valor = trim($fila[$col['id']] ?? '');

            // Valor por defecto si celda vacía
            if ( $valor === '' && ($col['default_val'] ?? '') !== '' ) {
                $valor = $col['default_val'];
            }

            // listo_monto_total: calculado automáticamente, no validar aquí
            if ( $col['id'] === 'listo_monto_total' ) continue;

            // Obligatoriedad según tipo_programado
            $obligatorio = $col['obligatorio'];
            // Dirección y distrito: obligatorios en Domicilio y Mercado Flex
            if ( in_array($col['id'], ['distrito_envio','dest_direccion'], true) ) {
                $obligatorio = $es_domicilio || $es_mercado_flex;
            }
            // Teléfono: solo obligatorio en Domicilio
            if ( $col['id'] === 'dest_telefono' ) {
                $obligatorio = $es_domicilio;
            }
            // En Mercado Flex: ignorar teléfono si viene vacío
            if ( $col['id'] === 'dest_telefono' && $es_mercado_flex ) {
                continue; // no es campo aplicable
            }

            // listo_monto_producto: obligatorio solo si listo_cobrar_producto = si
            if ( $col['id'] === 'listo_monto_producto' ) {
                $cobrar = strtolower(trim($fila['listo_cobrar_producto'] ?? 'no'));
                $obligatorio = ($cobrar === 'si');
            }

            if ( $obligatorio && $valor === '' ) {
                $errores[$col['id']] = "'{$col['label']}' es obligatorio.";
                continue;
            }
            if ( $valor === '' ) continue;

            // Tipos especiales: shipper y number_readonly no van a meta directamente aquí
            if ( $col['tipo'] === 'shipper' ) {
                // registered_shipper se asigna vía $author o el valor del campo (user_id)
                if ( is_numeric($valor) ) $author = intval($valor);
                $meta[$col['meta_key']] = $author;
                continue;
            }
            if ( $col['tipo'] === 'select_db' || $col['tipo'] === 'select' ) {
                $meta[$col['meta_key']] = sanitize_text_field($valor);
                continue;
            }

            $validado = self::validar_tipo($valor, $col['tipo'], $col['label']);
            if ( is_wp_error($validado) ) {
                $errores[$col['id']] = $validado->get_error_message();
                continue;
            }
            $meta[$col['meta_key']] = $validado;
        }

        if ( ! empty($errores) ) {
            return ['ok' => false, 'errores' => $errores, 'datos' => $fila];
        }

        // ── Calcular monto total ────────────────────────────────────────────
        $cobrar_producto = strtolower(trim($fila['listo_cobrar_producto'] ?? 'no'));
        $modo_pago       = strtoupper(trim($fila['modo_de_pago'] ?? ''));
        $monto_envio     = floatval(str_replace(',', '.', $fila['monto_envio'] ?? 0));
        $monto_producto  = ($cobrar_producto === 'si')
            ? floatval(str_replace(',', '.', $fila['listo_monto_producto'] ?? 0))
            : 0;
        // Si modo_de_pago = NO COBRAR => monto total = 0
        $monto_total = ($modo_pago === 'NO COBRAR') ? 0.0 : ($monto_envio + $monto_producto);

        $meta['listo_monto_total']     = number_format($monto_total, 2, '.', '');
        $meta['listo_cobrar_producto'] = $cobrar_producto;
        if ( $cobrar_producto !== 'si' ) {
            $meta['listo_monto_producto'] = '0.00';
        }

        // Autoasignacion por distrito: si no hay contenedor asignado, ambos campos van vacios.
        $container = sanitize_text_field((string)($fila['shipment_container'] ?? ''));
        $driver_raw = $fila['wpcargo_driver'] ?? '';
        $driver     = is_numeric($driver_raw) ? intval($driver_raw) : 0;

        if ( $container === '' ) {
            $meta['shipment_container'] = '';
            $meta['wpcargo_driver'] = '';
        } else {
            $meta['shipment_container'] = $container;
            $meta['wpcargo_driver'] = ( $driver > 0 ) ? $driver : '';
        }

        // ── Crear el envío ──────────────────────────────────────────────────
        $tracking = wcmas_generar_tracking();
        $post_id  = wp_insert_post([
            'post_type'   => 'wpcargo_shipment',
            'post_status' => 'publish',
            'post_author' => $author,
            'post_title'  => $tracking,
            'post_name'   => sanitize_title($tracking),
        ], true);

        if ( is_wp_error($post_id) ) {
            return ['ok' => false, 'errores' => ['_' => $post_id->get_error_message()], 'datos' => $fila];
        }

        // ── Guardar meta ────────────────────────────────────────────────────
        foreach ( $meta as $key => $val ) {
            update_post_meta($post_id, $key, $val);
        }

        // shipment_title también como post title visible en WPCargo
        if ( ! empty($meta['shipment_title']) ) {
            wp_update_post(['ID' => $post_id, 'post_title' => $meta['shipment_title']]);
            // Conservar tracking como meta separado
            update_post_meta($post_id, 'wpcargo_tracking_number', $tracking);
        }

        update_post_meta($post_id, 'registered_shipper',      $author);
        update_post_meta($post_id, 'wpcargo_shipper_name',     self::obtener_nombre_remitente($author));
        update_post_meta($post_id, 'wpcargo_status',          wcmas_default_status());
        update_post_meta($post_id, 'wpcargo_tracking_number', $tracking);
        update_post_meta($post_id, 'wpcargo_created_via',     'envios_masivos');

        do_action('wpcargo_after_create_shipment', $post_id, $meta);
        do_action('wpcfe_after_create_shipment',   $post_id);

        return [
            'ok'       => true,
            'post_id'  => $post_id,
            'tracking' => $tracking,
            'errores'  => [],
            'datos'    => $fila,
        ];
    }

    public static function procesar_lote( array $filas, int $user_id = 0 ): array {
        $resultados = [];
        foreach ( $filas as $i => $fila ) {
            $r = self::procesar_fila($fila, $user_id);
            $r['fila_num'] = $i + 1;
            $resultados[]  = $r;
        }
        return $resultados;
    }

    public static function validar_fila( array $fila ): array {
        $columnas        = WCMAS_Columnas::obtener_activas();
        $errores         = [];
        $tipo_lower      = strtolower(trim($fila['tipo_programado'] ?? ''));
        $es_domicilio    = ($tipo_lower === 'domicilio');
        $es_mercado_flex = ($tipo_lower === 'mercado flex');
        $cobrar          = strtolower(trim($fila['listo_cobrar_producto'] ?? 'no'));

        foreach ( $columnas as $col ) {
            if ( $col['id'] === 'listo_monto_total' ) continue;
            // En Mercado Flex: dirección no aplica
            if ( $col['id'] === 'dest_direccion' && $es_mercado_flex ) continue;
            $valor = trim($fila[$col['id']] ?? '');
            if ( $valor === '' && ($col['default_val'] ?? '') !== '' ) continue;

            $obligatorio = $col['obligatorio'];
            if ( in_array($col['id'], ['dest_direccion','distrito_envio'], true) ) {
                $obligatorio = $es_domicilio || $es_mercado_flex;
            }
            if ( $col['id'] === 'dest_telefono' ) {
                $obligatorio = $es_domicilio;
            }
            if ( $col['id'] === 'listo_monto_producto' ) {
                $obligatorio = ($cobrar === 'si');
            }

            if ( $obligatorio && $valor === '' ) {
                $errores[$col['id']] = "'{$col['label']}' es obligatorio.";
                continue;
            }
            if ( $valor === '' ) continue;
            if ( in_array($col['tipo'], ['select','select_db','shipper'], true) ) continue;
            $v = self::validar_tipo($valor, $col['tipo'], $col['label']);
            if ( is_wp_error($v) ) $errores[$col['id']] = $v->get_error_message();
        }
        return $errores;
    }

    private static function validar_tipo( string $valor, string $tipo, string $label ): string|\WP_Error {
        switch ($tipo) {
            case 'number':
            case 'number_readonly':
                if ( ! is_numeric(str_replace(',', '.', $valor)) ) {
                    return new \WP_Error('fmt', "'{$label}' debe ser un número.");
                }
                return str_replace(',', '.', $valor);
            case 'phone':
                $limpio = preg_replace('/[\s\-\+\(\)]/', '', $valor);
                if ( ! preg_match('/^\d{7,15}$/', $limpio) ) {
                    return new \WP_Error('fmt', "'{$label}' debe ser un teléfono válido.");
                }
                return $limpio;
            case 'email':
                if ( ! is_email($valor) ) {
                    return new \WP_Error('fmt', "'{$label}' debe ser un email válido.");
                }
                return sanitize_email($valor);
            default:
                return sanitize_text_field($valor);
        }
    }

    private static function obtener_nombre_remitente( int $user_id ): string {
        if ( $user_id <= 0 ) return '';

        $first = trim((string) get_user_meta($user_id, 'first_name', true));
        $last  = trim((string) get_user_meta($user_id, 'last_name', true));
        $full  = trim($first . ' ' . $last);
        if ( $full !== '' ) return $full;

        $user = get_userdata($user_id);
        if ( ! $user ) return '';

        $display = trim((string) ($user->display_name ?? ''));
        if ( $display !== '' ) return $display;

        return trim((string) ($user->user_login ?? ''));
    }
}
