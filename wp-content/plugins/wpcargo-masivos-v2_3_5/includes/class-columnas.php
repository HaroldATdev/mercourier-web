<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Gestión de columnas configurables del plugin Envíos Masivos.
 *
 * Tipos especiales:
 *  - 'select'         → dropdown con opciones estáticas
 *  - 'select_wpcf'    → dropdown cuyas opciones se leen dinámicamente de wp_wpcargo_custom_fields
 *  - 'tipo_servicio'  → select especial EMPRENDEDOR/AGENCIA/FULLFITMENT con autocompletado de costos
 *  - 'monto'          → número que se autocompleta según distrito+tipo y se bloquea si modo=NO COBRAR
 *  - 'date'           → datepicker DD/MM/YYYY con domingos bloqueados
 */
class WCMAS_Columnas {

    const OPTION_KEY = 'wcmas_columnas_v2';

    /**
     * Lee las opciones de un campo select desde wp_wpcargo_custom_fields.
     * Usa la columna field_data (serializada) del campo identificado por field_key.
     */
    public static function get_opciones_wpcf( string $field_key ): array {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT field_data FROM wp_wpcargo_custom_fields WHERE field_key = %s LIMIT 1",
            $field_key
        ));
        if ( ! $row || ! $row->field_data ) return [];
        $data = maybe_unserialize($row->field_data);
        if ( ! is_array($data) ) return [];
        // Limpiar espacios iniciales que WPCargo añade (ej: " Barranco" → "Barranco")
        return array_values(array_filter(array_map('trim', $data)));
    }

    /**
     * Lee las tarifas de envío por distrito desde wp_options (wcmas_tarifas).
     * Formato: [ 'Miraflores' => ['normal'=>13, 'express'=>18, 'full_fitment'=>20], ... ]
     * Si no hay tarifas configuradas retorna array vacío.
     */
    public static function get_tarifas(): array {
        $tarifas = get_option('wcmas_tarifas', []);
        return is_array($tarifas) ? $tarifas : [];
    }

    private static function defaults(): array {
        return [
            'dest_nombre' => [
                'id'=>'dest_nombre','label'=>'Nombre y Apellidos (Recibe)','meta_key'=>'wpcargo_receiver_name',
                'tipo'=>'text','activa'=>true,'obligatorio'=>true,
                'default_val'=>'','opciones'=>[],'placeholder'=>'Nombre completo','ancho'=>'lg','orden'=>1,
            ],
            'dest_telefono' => [
                'id'=>'dest_telefono','label'=>'Celular (Recibe)','meta_key'=>'wpcargo_receiver_phone',
                'tipo'=>'phone','activa'=>true,'obligatorio'=>true,
                'default_val'=>'','opciones'=>[],'placeholder'=>'9XXXXXXXX','ancho'=>'md','orden'=>2,
            ],
            'dest_direccion' => [
                'id'=>'dest_direccion','label'=>'Dirección (Recibe)','meta_key'=>'wpcargo_receiver_address',
                'tipo'=>'text','activa'=>true,'obligatorio'=>true,
                'default_val'=>'','opciones'=>[],'placeholder'=>'Av. / Jr. / Calle...','ancho'=>'lg','orden'=>3,
            ],
            // PUNTO 1: dist_recojo se extrae automáticamente del perfil del remitente
            // No aparece como columna editable — wcmas_get_datos_remitente() lo provee
            'dist_destino' => [
                'id'=>'dist_destino','label'=>'Distrito de Destino','meta_key'=>'wpcargo_distrito_destino',
                'tipo'=>'select_wpcf','activa'=>true,'obligatorio'=>true,
                'default_val'=>'','opciones'=>[],'placeholder'=>'','ancho'=>'md','orden'=>4,
                'wpcf_key'=>'wpcargo_distrito_destino',
            ],
            'tipo_servicio' => [
                'id'=>'tipo_servicio','label'=>'Tipo de Servicio','meta_key'=>'tipo_envio',
                'tipo'=>'tipo_servicio','activa'=>true,'obligatorio'=>true,
                'default_val'=>'','opciones'=>[
                    'EMPRENDEDOR' => 'normal',
                    'AGENCIA'     => 'express',
                    'FULLFITMENT' => 'full_fitment',
                ],'placeholder'=>'','ancho'=>'md','orden'=>6,
            ],
            'modo_pago' => [
                'id'=>'modo_pago','label'=>'Modo de Pago','meta_key'=>'payment_wpcargo_mode_field',
                'tipo'=>'select_wpcf','activa'=>true,'obligatorio'=>true,
                'default_val'=>'','opciones'=>[],'placeholder'=>'','ancho'=>'md','orden'=>7,
                'wpcf_key'=>'payment_wpcargo_mode_field',
            ],
            'costo_producto' => [
                'id'=>'costo_producto','label'=>'Costo Producto S/','meta_key'=>'wpcargo_costo_producto',
                'tipo'=>'number','activa'=>true,'obligatorio'=>false,
                'default_val'=>'0.00','opciones'=>[],'placeholder'=>'0.00','ancho'=>'sm','orden'=>8,
            ],
            'costo_servicio' => [
                'id'=>'costo_servicio','label'=>'Costo Servicio S/','meta_key'=>'wpcargo_costo_envio',
                'tipo'=>'number','activa'=>true,'obligatorio'=>false,
                'default_val'=>'0.00','opciones'=>[],'placeholder'=>'0.00','ancho'=>'sm','orden'=>9,
            ],
            'monto_total' => [
                'id'=>'monto_total','label'=>'Monto Total S/','meta_key'=>'monto',
                'tipo'=>'monto','activa'=>true,'obligatorio'=>false,
                'default_val'=>'0.00','opciones'=>[],'placeholder'=>'0.00','ancho'=>'sm','orden'=>10,
            ],
            'link_maps_dest' => [
                'id'=>'link_maps_dest','label'=>'Link Maps Destino','meta_key'=>'link_maps',
                'tipo'=>'text','activa'=>true,'obligatorio'=>false,
                'default_val'=>'','opciones'=>[],'placeholder'=>'https://maps.app.goo.gl/...','ancho'=>'lg','orden'=>11,
            ],
            'cambio_producto' => [
                'id'=>'cambio_producto','label'=>'¿Cambio de producto?','meta_key'=>'cambio_producto',
                'tipo'=>'select','activa'=>true,'obligatorio'=>false,
                'default_val'=>'No','opciones'=>['Sí','No'],'placeholder'=>'','ancho'=>'sm','orden'=>12,
            ],
            'notas' => [
                'id'=>'notas','label'=>'Indicaciones','meta_key'=>'wpcargo_comments',
                'tipo'=>'text','activa'=>false,'obligatorio'=>false,
                'default_val'=>'','opciones'=>[],'placeholder'=>'Indicaciones de entrega','ancho'=>'lg','orden'=>13,
            ],
        ];
    }

    public static function instalar_defaults(): void {
        if ( ! get_option(self::OPTION_KEY) ) {
            update_option(self::OPTION_KEY, self::defaults(), false);
        }
    }

    public static function obtener_todas(): array {
        $cols = get_option(self::OPTION_KEY, []);
        if ( empty($cols) ) $cols = self::defaults();
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
        $tipos_validos = ['text','number','phone','email','select','select_wpcf','textarea','date','tipo_servicio','monto'];
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
            'wpcf_key'    => sanitize_text_field($datos['wpcf_key'] ?? ''),
        ];
        update_option(self::OPTION_KEY, $cols, false);
        return true;
    }

    public static function eliminar( string $id ): void {
        $cols = self::obtener_todas(); unset($cols[$id]);
        update_option(self::OPTION_KEY, $cols, false);
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

    /**
     * Serializa columnas para el JS de la grilla.
     * Para tipo select_wpcf: carga las opciones dinámicamente desde wp_wpcargo_custom_fields.
     * Para tipo tipo_servicio: exporta el mapa label→valor y las tarifas.
     */
    public static function para_js( bool $solo_activas = true ): string {
        $cols    = $solo_activas ? self::obtener_activas() : self::obtener_todas();
        $tarifas = self::get_tarifas();

        $result = array_values(array_map(function($c) use ($tarifas) {
            $tipo = $c['tipo'];

            // Resolver opciones dinámicas desde wp_wpcargo_custom_fields
            $opciones = $c['opciones'] ?? [];
            if ( $tipo === 'select_wpcf' && ! empty($c['wpcf_key']) ) {
                $opciones = self::get_opciones_wpcf($c['wpcf_key']);
            }

            // Para tipo_servicio exportar el mapa display→valor y tarifas
            $tipo_servicio_map = null;
            if ( $tipo === 'tipo_servicio' ) {
                $tipo_servicio_map = $c['opciones'] ?? [
                    'EMPRENDEDOR' => 'normal',
                    'AGENCIA'     => 'express',
                    'FULLFITMENT' => 'full_fitment',
                ];
            }

            return [
                'id'               => $c['id'],
                'label'            => $c['label'],
                'meta_key'         => $c['meta_key'],
                'tipo'             => $tipo,
                'activa'           => (bool)$c['activa'],
                'obligatorio'      => (bool)$c['obligatorio'],
                'default_val'      => $c['default_val'] ?? '',
                'opciones'         => $opciones,
                'placeholder'      => $c['placeholder'] ?? '',
                'ancho'            => $c['ancho'] ?? 'md',
                'tipo_servicio_map'=> $tipo_servicio_map,
                'tarifas'          => $tipo === 'tipo_servicio' ? $tarifas : null,
            ];
        }, $cols));

        return wp_json_encode($result);
    }
}
