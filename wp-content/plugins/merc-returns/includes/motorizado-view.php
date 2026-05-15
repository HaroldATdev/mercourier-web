<?php
/**
 * Vista de Devoluciones para Motorizados - Merc Returns
 */

if (!defined('ABSPATH')) exit;

/**
 * Shortcode para mostrar las devoluciones asignadas a un motorizado
 */
add_shortcode('merc_devoluciones_motorizado', 'merc_render_devoluciones_motorizado');
function merc_render_devoluciones_motorizado() {
    $user = wp_get_current_user();
    if (!in_array('wpcargo_driver', (array) $user->roles)) {
        return '<div class="alert alert-danger">No tienes permisos para ver esta sección.</div>';
    }

    $motorizado_id = $user->ID;
    
    // Filtros por defecto: Ayer y Hoy
    $default_desde = date('Y-m-d', strtotime('-1 day'));
    $default_hasta = date('Y-m-d');
    
    $desde = isset($_GET['desde']) && !empty($_GET['desde']) ? sanitize_text_field($_GET['desde']) : $default_desde;
    $hasta = isset($_GET['hasta']) && !empty($_GET['hasta']) ? sanitize_text_field($_GET['hasta']) : $default_hasta;
    $buscar = isset($_GET['buscar']) ? sanitize_text_field($_GET['buscar']) : '';

    $args = array(
        'post_type'      => 'wpcargo_shipment',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'orderby'        => 'date',
        'order'          => 'DESC'
    );

    // Condición: Estado final de devolución OR merc_es_devolucion = 1
    $base = array(
        'relation' => 'OR',
        array('key' => 'wpcargo_status', 'value' => array('Reprogramado','Anulado','No recibido'), 'compare' => 'IN'),
        array('key' => 'cambio_producto', 'value' => 'Sí', 'compare' => '='),
        array('key' => 'merc_es_devolucion', 'value' => '1', 'compare' => '=')
    );

    $meta_query = array('relation' => 'AND', $base);

    // Condición: Que el motorizado actual esté involucrado en la devolución
    $meta_query[] = array(
        'relation' => 'OR',
        array('key' => 'wpcargo_driver', 'value' => $motorizado_id, 'compare' => '='),
        array('key' => 'merc_motorizado_devolucion', 'value' => $motorizado_id, 'compare' => '=')
    );

    // Filtro por Fechas (wpcargo_pickup_date_picker)
    if (!empty($desde) && !empty($hasta)) {
        global $wpdb;
        $ids = $wpdb->get_col($wpdb->prepare("
            SELECT post_id FROM {$wpdb->postmeta}
            WHERE meta_key = 'wpcargo_pickup_date_picker'
            AND STR_TO_DATE(meta_value, '%%d/%%m/%%Y') BETWEEN %s AND %s
        ", $desde, $hasta));
        
        if (!empty($ids)) {
            $args['post__in'] = $ids;
        } else {
            $args['post__in'] = array(0);
        }
    }

    if (!empty($buscar)) $args['s'] = $buscar;

    $args['meta_query'] = $meta_query;
    $query = new WP_Query($args);

    ob_start();
    ?>
    <div class="merc-devoluciones-motorizado-wrap" style="background:#fff; border-radius:8px; box-shadow:0 2px 10px rgba(0,0,0,0.05); padding:20px; margin-bottom: 20px;">
        <h2 style="margin-bottom: 20px; font-size: 22px; color: #333; display: flex; align-items: center; gap: 10px;">
            <i class="fa fa-undo" style="color: #f44336;"></i> Mis Devoluciones Pendientes
        </h2>
        
        <form method="get" action="" style="display:flex; gap:15px; margin-bottom: 20px; flex-wrap: wrap; background: #f9f9f9; padding: 15px; border-radius: 6px; border: 1px solid #eee;">
            <input type="hidden" name="wpcfe" value="devoluciones">
            <div style="flex:1; min-width: 200px;">
                <label style="display:block; font-size:12px; font-weight:bold; margin-bottom:5px;">Buscar Tracking</label>
                <input type="text" name="buscar" value="<?php echo esc_attr($buscar); ?>" placeholder="Ej: MERC-123" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
            </div>
            <div>
                <label style="display:block; font-size:12px; font-weight:bold; margin-bottom:5px;">Desde</label>
                <input type="date" name="desde" value="<?php echo esc_attr($desde); ?>" style="padding:8px; border:1px solid #ccc; border-radius:4px;">
            </div>
            <div>
                <label style="display:block; font-size:12px; font-weight:bold; margin-bottom:5px;">Hasta</label>
                <input type="date" name="hasta" value="<?php echo esc_attr($hasta); ?>" style="padding:8px; border:1px solid #ccc; border-radius:4px;">
            </div>
            <div style="display:flex; align-items:flex-end;">
                <button type="submit" class="button button-primary" style="padding:8px 20px; border:none; background:#2271b1; color:#fff; border-radius:4px; cursor:pointer;">
                    Filtrar
                </button>
            </div>
        </form>

        <div style="overflow-x:auto;">
            <table class="table table-striped table-hover" style="width:100%; text-align:left; border-collapse:collapse;">
                <thead style="background:#f1f1f1;">
                    <tr>
                        <th style="padding:12px; border-bottom:2px solid #ddd;">Fecha</th>
                        <th style="padding:12px; border-bottom:2px solid #ddd;">Tracking</th>
                        <th style="padding:12px; border-bottom:2px solid #ddd;">Cliente</th>
                        <th style="padding:12px; border-bottom:2px solid #ddd;">Estado Actual</th>
                        <th style="padding:12px; border-bottom:2px solid #ddd;">Marca</th>
                        <th style="padding:12px; border-bottom:2px solid #ddd;">Tipo de Retorno</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($query->have_posts()): while ($query->have_posts()): $query->the_post(); 
                        $id = get_the_ID();
                        $tracking = get_the_title();
                        $cliente = get_post_meta($id, 'wpcargo_receiver_name', true);
                        $estado_envio = get_post_meta($id, 'wpcargo_status', true);
                        $marca = get_post_meta($id, 'wpcargo_tiendaname', true);
                        $es_devolucion_marca = get_post_meta($id, 'merc_es_devolucion', true);
                        $cambio_producto = get_post_meta($id, 'cambio_producto', true);
                        
                        $fecha_raw = get_post_meta($id, 'wpcargo_pickup_date_picker', true);
                        if ($fecha_raw) {
                            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $fecha_raw)) {
                                $fecha = $fecha_raw;
                            } else {
                                $fecha = date_i18n('d/m/Y', strtotime($fecha_raw));
                            }
                        } else {
                            $fecha = '';
                        }
                        
                        $dashboard_url = get_permalink(wpcfe_admin_page());
                        $detalle_url = add_query_arg(array('wpcfe' => 'track', 'num' => $tracking), $dashboard_url);
                    ?>
                        <tr>
                            <td style="padding:12px; border-bottom:1px solid #eee;"><?php echo esc_html($fecha); ?></td>
                            <td style="padding:12px; border-bottom:1px solid #eee;">
                                <a href="<?php echo esc_url($detalle_url); ?>" style="font-weight:bold; color:#2271b1;">
                                    <?php echo esc_html($tracking); ?>
                                </a>
                            </td>
                            <td style="padding:12px; border-bottom:1px solid #eee;"><?php echo esc_html($cliente); ?></td>
                            <td style="padding:12px; border-bottom:1px solid #eee;">
                                <span style="background:#eee; padding:3px 8px; border-radius:12px; font-size:12px; font-weight:bold; color:#555;">
                                    <?php echo esc_html($estado_envio); ?>
                                </span>
                            </td>
                            <td style="padding:12px; border-bottom:1px solid #eee;"><?php echo esc_html($marca); ?></td>
                            <td style="padding:12px; border-bottom:1px solid #eee;">
                                <?php if ($cambio_producto === 'Sí'): ?>
                                    <span style="color:#00796b; font-weight:bold;"><i class="fa fa-exchange"></i> Cambio de Producto</span>
                                <?php else: ?>
                                    <span style="color:#f44336; font-weight:bold;"><i class="fa fa-undo"></i> Devolución Normal</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr>
                            <td colspan="6" style="text-align:center; padding:30px; color:#777;">
                                <i class="fa fa-check-circle" style="font-size:32px; color:#4caf50; display:block; margin-bottom:10px;"></i>
                                No tienes devoluciones pendientes en este rango de fechas.
                            </td>
                        </tr>
                    <?php endif; wp_reset_postdata(); ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Función auxiliar para contar devoluciones pendientes del motorizado (Ayer y Hoy)
 */
function merc_motorizado_get_devoluciones_count($motorizado_id) {
    $desde = date('Y-m-d', strtotime('-1 day'));
    $hasta = date('Y-m-d');
    
    $base = array(
        'relation' => 'OR',
        array('key' => 'wpcargo_status', 'value' => array('Reprogramado','Anulado','No recibido'), 'compare' => 'IN'),
        array('key' => 'cambio_producto', 'value' => 'Sí', 'compare' => '='),
        array('key' => 'merc_es_devolucion', 'value' => '1', 'compare' => '=')
    );

    $meta_query = array(
        'relation' => 'AND', 
        $base,
        array(
            'relation' => 'OR',
            array('key' => 'wpcargo_driver', 'value' => $motorizado_id, 'compare' => '='),
            array('key' => 'merc_motorizado_devolucion', 'value' => $motorizado_id, 'compare' => '=')
        )
    );

    global $wpdb;
    $ids = $wpdb->get_col($wpdb->prepare("
        SELECT post_id FROM {$wpdb->postmeta}
        WHERE meta_key = 'wpcargo_pickup_date_picker'
        AND STR_TO_DATE(meta_value, '%%d/%%m/%%Y') BETWEEN %s AND %s
    ", $desde, $hasta));

    if (empty($ids)) return 0;

    $query = new WP_Query(array(
        'post_type'      => 'wpcargo_shipment',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'fields'         => 'ids',
        'post__in'       => $ids,
        'meta_query'     => $meta_query
    ));

    return $query->found_posts;
}

/**
 * Añadir la pestaña al sidebar del WPCargo Frontend Manager (Motorizados)
 */
add_filter('wpcfe_after_sidebar_menus', 'merc_returns_add_motorizado_sidebar_menu');
function merc_returns_add_motorizado_sidebar_menu($menus) {
    $user = wp_get_current_user();
    if (in_array('wpcargo_driver', (array) $user->roles)) {
        $count = merc_motorizado_get_devoluciones_count($user->ID);
        $badge = $count > 0 ? " <span class='badge badge-pill bg-danger align-top'>{$count}</span>" : "";
        
        $menus['merc_devoluciones_motorizado'] = array(
            'label'     => 'Mis Devoluciones' . $badge,
            'permalink' => get_permalink(wpcfe_admin_page()) . '?wpcfe=devoluciones',
            'icon'      => 'fa-undo'
        );
    }
    return $menus;
}

/**
 * Inyectar el shortcode en el Dashboard si $_GET['wpcfe'] = 'devoluciones'
 */
add_action('wpcfe_dashboard_before_content', 'merc_returns_render_motorizado_dashboard_tab');
function merc_returns_render_motorizado_dashboard_tab() {
    if (isset($_GET['wpcfe']) && $_GET['wpcfe'] === 'devoluciones') {
        echo '<div id="wpcfe-custom-devoluciones">';
        echo do_shortcode('[merc_devoluciones_motorizado]');
        echo '</div>';
        echo '<style>
            #content-container > *:not(#wpcfe-custom-devoluciones) { display: none !important; }
            #wpcfe-custom-devoluciones { display: block !important; }
        </style>';
    }
}

