<?php
if ( ! defined('ABSPATH') ) exit;

class WCMAS_Columnas {

    public const OPTION_KEY = 'wcmas_columnas_v2';

    private static function defaults(): array {
        return [
            'tipo_envio' => [
                'id'=>'tipo_envio','label'=>'Tipo de Envío','meta_key'=>'tipo_envio',
                'tipo'=>'select_db','activa'=>true,'obligatorio'=>true,
                'default_val'=>'','opciones'=>[],'placeholder'=>'','ancho'=>'md','orden'=>1,
            ],
            'fecha_envio' => [
                'id'=>'fecha_envio','label'=>'Fecha de Envío','meta_key'=>'wpcargo_pickup_date_picker',
                'tipo'=>'date','activa'=>true,'obligatorio'=>true,
                'default_val'=>'','opciones'=>[],'placeholder'=>'','ancho'=>'md','orden'=>1.5,
            ],
            'registered_shipper' => [
                'id'=>'registered_shipper','label'=>'Remitente','meta_key'=>'registered_shipper',
                'tipo'=>'shipper','activa'=>true,'obligatorio'=>false,
                'default_val'=>'','opciones'=>[],'placeholder'=>'','ancho'=>'md','orden'=>3,
            ],
            'dest_nombre' => [
                'id'=>'dest_nombre','label'=>'Destinatario','meta_key'=>'wpcargo_receiver_name',
                'tipo'=>'text','activa'=>true,'obligatorio'=>true,
                'default_val'=>'','opciones'=>[],'placeholder'=>'Nombre completo','ancho'=>'lg','orden'=>5,
            ],
            'dest_direccion' => [
                'id'=>'dest_direccion','label'=>'Dirección','meta_key'=>'wpcargo_receiver_address',
                'tipo'=>'text','activa'=>true,'obligatorio'=>false,
                'default_val'=>'','opciones'=>[],'placeholder'=>'Av. / Jr. / Calle...','ancho'=>'lg','orden'=>6,
            ],
            'distrito_envio' => [
                'id'=>'distrito_envio','label'=>'Distrito','meta_key'=>'wpcargo_distrito_destino',
                'tipo'=>'select','activa'=>true,'obligatorio'=>false,
                'default_val'=>'','opciones'=>self::get_distritos(),'placeholder'=>'','ancho'=>'md','orden'=>7,
            ],
            'link_maps' => [
                'id'=>'link_maps','label'=>'Link Maps','meta_key'=>'link_maps',
                'tipo'=>'text','activa'=>true,'obligatorio'=>false,
                'default_val'=>'','opciones'=>[],'placeholder'=>'https://maps.app.goo.gl/...','ancho'=>'md','orden'=>8,
            ],
            'dest_telefono' => [
                'id'=>'dest_telefono','label'=>'Teléfono','meta_key'=>'wpcargo_receiver_phone',
                'tipo'=>'phone','activa'=>true,'obligatorio'=>false,
                'default_val'=>'','opciones'=>[],'placeholder'=>'9XXXXXXXX','ancho'=>'md','orden'=>8.5,
            ],
            'modo_de_pago' => [
                'id'=>'modo_de_pago','label'=>'Modo de Pago','meta_key'=>'payment_wpcargo_mode_field',
                'tipo'=>'select_db','activa'=>true,'obligatorio'=>false,
                'default_val'=>'','opciones'=>[],'placeholder'=>'','ancho'=>'md','orden'=>9,
            ],
            'cambio_producto' => [
                'id'=>'cambio_producto','label'=>'¿Cambio?','meta_key'=>'cambio_producto',
                'tipo'=>'select','activa'=>true,'obligatorio'=>false,
                'default_val'=>'No','opciones'=>['No','Sí'],'placeholder'=>'','ancho'=>'sm','orden'=>10,
            ],
            'costo_producto' => [
                'id'=>'costo_producto','label'=>'Costo Producto S/','meta_key'=>'wpcargo_costo_producto',
                'tipo'=>'number','activa'=>true,'obligatorio'=>false,
                'default_val'=>'','opciones'=>[],'placeholder'=>'0.00','ancho'=>'sm','orden'=>11,
            ],
            'costo_envio' => [
                'id'=>'costo_envio','label'=>'Costo Envío S/','meta_key'=>'wpcargo_costo_envio',
                'tipo'=>'number','activa'=>true,'obligatorio'=>false,
                'default_val'=>'','opciones'=>[],'placeholder'=>'0.00','ancho'=>'sm','orden'=>12,
            ],
            'monto' => [
                'id'=>'monto','label'=>'Monto Total S/','meta_key'=>'monto',
                'tipo'=>'number_readonly','activa'=>true,'obligatorio'=>false,
                'default_val'=>'','opciones'=>[],'placeholder'=>'0.00','ancho'=>'sm','orden'=>13,
            ],
            'comentario_envio' => [
                'id'=>'comentario_envio','label'=>'Comentario','meta_key'=>'wpcargo_comments',
                'tipo'=>'text','activa'=>true,'obligatorio'=>false,
                'default_val'=>'','opciones'=>[],'placeholder'=>'Indicaciones de entrega','ancho'=>'lg','orden'=>14,
            ],
        ];
    }

    public static function get_distritos(): array {
        $bd = self::get_meta_values_distintos('wpcargo_distrito_destino');
        if (!empty($bd)) return $bd;
        return array_keys(wcmas_get_tarifas_default());
    }

    public static function get_tarifa_distrito( string $distrito ): float {
        $tarifas = wcmas_get_tarifas();
        return floatval($tarifas[$distrito]['normal'] ?? $tarifas[$distrito]['express'] ?? 0);
    }

    public static function instalar_defaults(): void {
        if ( ! get_option(self::OPTION_KEY) ) {
            update_option(self::OPTION_KEY, self::defaults(), false);
        }
    }

    public static function obtener_todas(): array {
        $cols = get_option(self::OPTION_KEY, []);
        $eliminadas = get_option('wcmas_columnas_eliminadas', []);
        $defaults = self::defaults();
        if ( empty($cols) ) {
            $cols = $defaults;
        } else {
            // Migración: añadir columnas de defaults que no estén en BD y NO hayan sido eliminadas
            // Excluimos explícitamente 'registered_shipper' porque ahora es un control global, ya no va por fila
            foreach ($defaults as $id => $col) {
                if ($id === 'registered_shipper') continue;
                if (!isset($cols[$id]) && !in_array($id, $eliminadas, true)) {
                    $cols[$id] = $col;
                }
            }
        }
        uasort($cols, fn($a,$b) => ($a['orden']??99) <=> ($b['orden']??99));
        return $cols;
    }

    public static function obtener_activas(): array {
        return array_filter(self::obtener_todas(), fn($c) => !empty($c['activa']));
    }

    public static function obtener_por_id( string $id ): ?array {
        return self::obtener_todas()[$id] ?? null;
    }

    public static function guardar( array $datos, string $id_original = '' ): true|\WP_Error {
        $id = sanitize_key($datos['id'] ?? '');
        if ( !$id || !($datos['label'] ?? '') || !($datos['meta_key'] ?? '') ) {
            return new \WP_Error('req', 'ID, etiqueta y meta_key son obligatorios.');
        }
        $cols  = self::obtener_todas();
        if ( $id_original && $id_original !== $id ) unset($cols[$id_original]);
        $orden = isset($cols[$id]) ? ($cols[$id]['orden'] ?? 99) : (empty($cols) ? 1 : max(array_column($cols,'orden')) + 1);
        $tipos_validos = ['text','number','phone','email','select','select_db','textarea','shipper','number_readonly'];
        $cols[$id] = [
            'id'          => $id,
            'label'       => sanitize_text_field($datos['label']),
            'meta_key'    => sanitize_text_field($datos['meta_key']),
            'tipo'        => in_array($datos['tipo']??'text', $tipos_validos) ? $datos['tipo'] : 'text',
            'activa'      => !empty($datos['activa']),
            'obligatorio' => !empty($datos['obligatorio']),
            'default_val' => sanitize_text_field($datos['default_val'] ?? ''),
            'opciones'    => self::parsear_opciones($datos['opciones'] ?? ''),
            'placeholder' => sanitize_text_field($datos['placeholder'] ?? ''),
            'ancho'       => in_array($datos['ancho']??'md',['sm','md','lg']) ? $datos['ancho'] : 'md',
            'orden'       => intval($datos['orden'] ?? $orden),
        ];
        update_option(self::OPTION_KEY, $cols, false);
        return true;
    }

    public static function eliminar( string $id ): void {
        $cols = self::obtener_todas();
        unset($cols[$id]);
        update_option(self::OPTION_KEY, $cols, false);
        
        $eliminadas = get_option('wcmas_columnas_eliminadas', []);
        if (!in_array($id, $eliminadas, true)) {
            $eliminadas[] = $id;
            update_option('wcmas_columnas_eliminadas', $eliminadas, false);
        }
    }

    public static function reordenar( array $orden_ids ): void {
        $cols = self::obtener_todas();
        foreach ( $orden_ids as $pos => $id ) {
            if ( isset($cols[$id]) ) $cols[$id]['orden'] = $pos + 1;
        }
        update_option(self::OPTION_KEY, $cols, false);
    }

    private static function parsear_opciones( $raw ): array {
        if ( is_array($raw) ) return array_map('sanitize_text_field', array_filter($raw));
        return array_filter(array_map('trim', explode("\n", $raw)));
    }

    /** Opciones de modo_de_pago desde el sistema real */
    public static function get_modos_de_pago(): array {
        return ['NO COBRAR', 'EFECTIVO', 'YAPE/PLIN', 'POS'];
    }

    /** Opciones de tipo_envio */
    public static function get_tipos_envio(): array {
        return [
            ['value' => 'normal', 'label' => 'EMPRENDEDOR'],
            ['value' => 'express', 'label' => 'AGENCIA']
        ];
    }



    public static function get_opciones_wpcf( string $meta_key ): array {
        global $wpdb;
        $opciones = [];

        // 1. Obtener opciones de la configuración de WPCargo Custom Fields (para distritos nuevos)
        $table = $wpdb->prefix . 'wpcargo_custom_fields';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
        
        if ( $table_exists ) {
            $field_data = $wpdb->get_var($wpdb->prepare(
                "SELECT field_data FROM {$table} WHERE field_key = %s LIMIT 1",
                $meta_key
            ));
            
            if ( ! empty($field_data) ) {
                $unserialized = maybe_unserialize($field_data);
                
                if ( is_array($unserialized) ) {
                    // WPCargo guarda las opciones como un array serializado
                    foreach ( $unserialized as $item ) {
                        if ( is_string($item) && trim($item) !== '' ) {
                            $opciones[] = trim($item);
                        }
                    }
                } else {
                    // Fallback para datos antiguos guardados como texto separado
                    $separador = strpos($field_data, ',') !== false ? ',' : "\n";
                    $items = explode($separador, $field_data);
                    foreach ( $items as $item ) {
                        $item = trim($item);
                        if ( $item !== '' ) {
                            $opciones[] = $item;
                        }
                    }
                }
            }
        }

        // 2. Si WPCargo tiene opciones, SOLO usamos esas (ignora el historial antiguo).
        // Si no tiene, hacemos fallback al historial de la base de datos.
        if ( ! empty($opciones) ) {
            $todas = array_unique(array_filter($opciones));
        } else {
            $bd = self::get_meta_values_distintos($meta_key);
            $todas = array_unique(array_filter($bd));
        }
        
        // 3. Ordenar alfabéticamente
        sort($todas);
        
        return array_values($todas);
    }

    private static function get_meta_values_distintos( string $meta_key, string $extra_where = '' ): array {
        global $wpdb;
        $query = $wpdb->prepare(
            "SELECT DISTINCT meta_value
             FROM {$wpdb->prefix}postmeta
             WHERE meta_key = %s
             AND meta_value != ''
             {$extra_where}
             ORDER BY meta_value ASC",
            $meta_key
        );
        $rows = $wpdb->get_col($query);
        return is_array($rows) ? array_values(array_filter(array_map('strval', $rows))) : [];
    }

    public static function para_js( bool $solo_activas = true ): string {
        $cols = $solo_activas ? self::obtener_activas() : self::obtener_todas();
        $modos_pago = null;
        $tipos_envio = null;
        $tipos_programado = null;
        foreach ( $cols as &$c ) {
            $col_id = $c['id'] ?? '';
            $meta_key = $c['meta_key'] ?? '';

            // Mantener estos campos clave siempre como select_db aunque la config haya sido alterada.
            if ( in_array($col_id, ['tipo_envio','tipo_programado','modo_de_pago'], true) ) {
                $c['tipo'] = 'select_db';
            }

            // Forzar que el distrito de envío SIEMPRE use las opciones más recientes, 
            // saltándose el caché de la BD.
            if ( $meta_key === 'wpcargo_distrito_destino' || $col_id === 'distrito_envio' ) {
                $c['opciones'] = self::get_opciones_wpcf('wpcargo_distrito_destino');
            }

            if ( $c['tipo'] !== 'select_db' ) continue;

            if ( $meta_key === 'payment_wpcargo_mode_field' || $col_id === 'modo_de_pago' ) {
                if ( $modos_pago === null ) $modos_pago = self::get_modos_de_pago();
                $c['opciones'] = $modos_pago;
                continue;
            }

            if ( $meta_key === 'wpcargo_type_of_shipment' || $col_id === 'tipo_envio' ) {
                if ( $tipos_envio === null ) $tipos_envio = self::get_tipos_envio();
                $c['opciones'] = $tipos_envio;
                continue;
            }

            // Ya no usamos tipo_programado en BD ni en la grilla. Eliminado.
        }
        unset($c);
        return wp_json_encode(array_values(array_map(function($c) {
            return [
                'id'          => $c['id'],
                'label'       => $c['label'],
                'meta_key'    => $c['meta_key'],
                'tipo'        => $c['tipo'],
                'activa'      => (bool)$c['activa'],
                'obligatorio' => (bool)$c['obligatorio'],
                'default_val' => $c['default_val'] ?? '',
                'opciones'    => $c['opciones'] ?? [],
                'placeholder' => $c['placeholder'] ?? '',
                'ancho'       => $c['ancho'] ?? 'md',
            ];
        }, $cols)));
    }
}



