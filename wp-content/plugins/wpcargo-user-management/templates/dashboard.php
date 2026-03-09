<!-- SweetAlert2 CSS y JS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<?php
// SOLUCIÓN: Inicializar variables faltantes que son requeridas por el template
if (!isset($paged)) {
    $paged = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
    if ($paged < 1) $paged = 1;
}
if (!isset($page_url)) {
    $page_url = get_the_permalink(wpcumanage_users_page()) ?: home_url('/users');
}

// Inicializar $users_per_page SIEMPRE (necesario para paginación)
$users_per_page = defined('WPCU_MANAGEMENT_ITEMS_PAGE') ? WPCU_MANAGEMENT_ITEMS_PAGE : 10;

if (!isset($total_pages)) {
    // Calcular total de páginas desde la query
    if (isset($wpcumanage_query) && is_object($wpcumanage_query) && method_exists($wpcumanage_query, 'get_total')) {
        $total_users = $wpcumanage_query->get_total();
    } else {
        $total_users = count_users();
        $total_users = isset($total_users['total_users']) ? $total_users['total_users'] : 0;
    }
    $total_pages = ceil($total_users / $users_per_page);
}

// Procesar búsqueda de usuario (nombre, email o empresa)
$search_company = isset($_GET['_user']) ? sanitize_text_field($_GET['_user']) : '';

// Procesar filtro por rol
$filter_role = isset($_GET['_role']) ? sanitize_text_field($_GET['_role']) : '';


?>

<div id="wpcumanage-table-wrapper" class="table-responsive">
    <h1 class="h4"><?php _e('Users', 'wpcargo-umanagement'); ?></h1>
    
    
    <?php do_action('wpcumanage_before_user_table', $wpcumanage_query); ?>
    <table id="wpcumanage-user-list" class="table table-hover table-sm">
        <thead>
            <tr>
                <?php do_action('wpcumanage_user_table_header'); ?>
                <td class="text-center wpcumanage-header-action"><?php _e('Action', 'wpcargo-umanagement'); ?></td>
            </tr>
        </thead>
        <tbody>
            <?php 
            global $wpdb;
            $users_to_display = array();
            
            // Query SQL EFICIENTE - paginación y búsqueda DIRECTAMENTE en la BD
            $search_condition = '';
            
            // Filtro por búsqueda de usuario/empresa
            if (!empty($search_company)) {
                $search_term = '%' . $wpdb->esc_like($search_company) . '%';
                $search_condition = $wpdb->prepare(
                    " AND (
                        u.user_login LIKE %s OR
                        u.user_email LIKE %s OR
                        u.display_name LIKE %s OR
                        (SELECT COUNT(*) FROM {$wpdb->usermeta} um 
                         WHERE um.user_id = u.ID AND um.meta_value LIKE %s 
                         AND um.meta_key IN ('billing_company', 'shipping_company', 'company', 'billing_first_name', 'billing_last_name', 'shipping_first_name', 'shipping_last_name')) > 0
                    )",
                    $search_term, $search_term, $search_term, $search_term
                );
            }
            
            // Filtro por rol
            if (!empty($filter_role)) {
                $role_search_term = '%"' . $wpdb->esc_like($filter_role) . '"%';
                $role_condition = $wpdb->prepare(
                    " AND u.ID IN (
                        SELECT user_id FROM {$wpdb->usermeta} 
                        WHERE meta_key = %s AND meta_value LIKE %s
                    )",
                    $wpdb->prefix . 'capabilities',
                    $role_search_term
                );
                $search_condition .= $role_condition;
            }
            
            // Contar total
            $total_users = $wpdb->get_var("SELECT COUNT(DISTINCT u.ID) FROM {$wpdb->users} u WHERE 1=1 {$search_condition}");
            $total_pages = ceil($total_users / $users_per_page);
            
            // Obtener usuarios paginados (SOLO LOS DE ESTA PÁGINA)
            $start = ($paged - 1) * $users_per_page;
            $user_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT u.ID FROM {$wpdb->users} u WHERE 1=1 {$search_condition} ORDER BY u.user_registered DESC LIMIT %d OFFSET %d",
                $users_per_page, 
                $start
            ));
            
            // Convertir IDs a objetos WP_User
            foreach ((array)$user_ids as $user_id) {
                $user = get_user_by('id', $user_id);
                if ($user) {
                    $users_to_display[] = $user;
                }
            }
            
            if (!empty($users_to_display)): ?>
            
                <?php foreach ($users_to_display as $user): ?>
                    <?php
                    $access         = wpcumanage_user_access($user->ID);
                    $str_access     = is_array($access) ? implode(',', $access)  : '';
                    ?>
                    <tr id="user-<?php echo $user->ID; ?>" class="user-row">
                        <?php do_action('wpcumanage_user_table_data', $user); ?>
                        <td class="wpcumanage-action text-center">
                            <a href="<?php echo $page_url; ?>?umpage=edit&uid=<?php echo $user->ID; ?>" title="<?php _e('Update', 'wpcargo-umanagement'); ?>" class="mr-2"><i class="fa fa-edit text-info"></i></a>
                            <a href="#" title="<?php _e('Add Access', 'wpcargo-umanagement'); ?>" data-id="<?php echo $user->ID; ?>" data-access="<?php echo $str_access; ?>" class="wpcumange-update-access mr-2" data-toggle="modal" data-target="#wpcumanageAccessModal"><i class="fa fa-key text-success"></i></a>
                            <a href="#" class="wpcumange-deactivate-account" data-id="<?php echo $user->ID; ?>" title="<?php _e('Deactivate', 'wpcargo-umanagement'); ?>"><i class="fa fa-user-times text-danger"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="100" class="text-center text-muted py-4">
                        <?php 
                        if (!empty($search_company) || !empty($filter_role)) {
                            echo __('No se encontraron usuarios con ese criterio.', 'wpcargo-umanagement');
                        } else {
                            echo __('No hay usuarios disponibles.', 'wpcargo-umanagement');
                        }
                        ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?php do_action('wpcumanage_after_user_table', $wpcumanage_query); ?>
    
    <style>
        #wpcumanage-user-pagination {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            border: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        #wpcumanage-user-pagination .pagination-info {
            font-size: 13px;
            color: #666;
            font-weight: 500;
        }
        
        #wpcumanage-user-pagination .pagination-links {
            display: flex;
            gap: 5px;
            margin: 0;
        }
        
        #wpcumanage-user-pagination .page-numbers {
            display: inline-block;
            padding: 6px 10px;
            font-size: 12px;
            border: 1px solid #dee2e6;
            border-radius: 3px;
            color: #495057;
            text-decoration: none;
            transition: all 0.2s ease;
            background: white;
        }
        
        #wpcumanage-user-pagination .page-numbers:hover {
            background-color: #e9ecef;
            border-color: #adb5bd;
        }
        
        #wpcumanage-user-pagination .page-numbers.current {
            background-color: #007bff;
            border-color: #007bff;
            color: white !important;
            font-weight: bold;
        }
        
        #wpcumanage-user-pagination .prev, 
        #wpcumanage-user-pagination .next {
            padding: 6px 12px;
            border-radius: 3px;
        }
        
        #wpcumanage-user-pagination .prev:hover,
        #wpcumanage-user-pagination .next:hover {
            background-color: #0056b3;
            border-color: #0056b3;
            color: white;
        }
    </style>
    
    <?php
    // Construct the base URL for pagination links with preserved filters
    $base_url = $page_url;
    
    // Agregar parámetros de búsqueda a la URL base si existen
    if (!empty($search_company)) {
        $base_url = add_query_arg('_user', $search_company, $base_url);
    }
    if (!empty($filter_role)) {
        $base_url = add_query_arg('_role', $filter_role, $base_url);
    }
    
    // Usar paginate_links con la URL base que incluye los filtros
    $pagination_url = add_query_arg('paged', '%#%', $base_url);
    ?>
    
    <div class="row mt-4">
        <nav id="wpcumanage-user-pagination" class="col-md-12">
            <div class="pagination-info">
                <?php
                if (!empty($users_to_display)) {
                    echo sprintf(
                        __('Mostrando <strong>%d</strong> usuario(s) de <strong>%d</strong> total | Página <strong>%d</strong> de <strong>%d</strong>', 'wpcargo-umanagement'),
                        count($users_to_display),
                        isset($total_users) ? $total_users : 0,
                        $paged,
                        $total_pages
                    );
                }
                ?>
            </div>
            <div class="pagination-links">
                <?php
                if (!empty($users_to_display) && $total_pages > 1) {
                    echo paginate_links(array(
                        'base' => $pagination_url,
                        'format' => '',
                        'current' => $paged,
                        'total' => $total_pages,
                        'prev_text' => '<i class="fa fa-chevron-left"></i> ' . __('Anterior', 'wpcargo-umanagement'),
                        'next_text' => __('Siguiente', 'wpcargo-umanagement') . ' <i class="fa fa-chevron-right"></i>',
                        'type' => 'plain',
                    ));
                }
                ?>
            </div>
        </nav>
    </div>
    <?php do_action('wpcumanage_after_user_table_pagination', $wpcumanage_query); ?>
</div>


<script>
jQuery(document).ready(function($) {
    var currentUserId = null;
    
    $(document).on('click', '.merc-assign-driver', function(e) {
        e.preventDefault();
        currentUserId = $(this).data('id');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'merc_get_client_driver',
                client_id: currentUserId,
                _nonce: '<?php echo wp_create_nonce('merc_driver_assign'); ?>'
            },
            success: function(response) {
                var currentDriver = '';
                if (response.success && response.data.driver_id) {
                    currentDriver = response.data.driver_id;
                }
                showDriverAssignModal(currentDriver);
            },
            error: function(err) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudo cargar el motorizado actual'
                });
            }
        });
    });
    
    function showDriverAssignModal(currentDriver) {
        var driversOptions = `<?php 
            $drivers = get_users(['role' => 'wpcargo_driver']);
            echo '<option value="">-- Sin asignar --</option>';
            foreach ($drivers as $driver) {
                echo '<option value="' . esc_attr($driver->ID) . '">' . esc_html($driver->display_name) . '</option>';
            }
        ?>`;
        
        var htmlContent = `
            <div style="text-align: left;">
                <div class="form-group">
                    <label for="merc_driver_select_swal" style="font-weight: bold; margin-bottom: 10px; display: block;">Selecciona el Motorizado:</label>
                    <select id="merc_driver_select_swal" class="form-control" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        ${driversOptions}
                    </select>
                </div>
            </div>
        `;
        
        Swal.fire({
            title: '🚗 Asignar Motorizado',
            html: htmlContent,
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: '✅ Guardar',
            cancelButtonText: '❌ Cancelar',
            confirmButtonColor: '#007bff',
            didOpen: function() {
                $('#merc_driver_select_swal').val(currentDriver);
            }
        }).then((result) => {
            if (result.isConfirmed) {
                var driverId = $('#merc_driver_select_swal').val();
                saveDriverAssignment(driverId);
            }
        });
    }
    
    function saveDriverAssignment(driverId) {
        if (!currentUserId) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Usuario ID no válido'
            });
            return;
        }
        
        Swal.fire({
            title: 'Guardando...',
            html: 'Por favor espera',
            icon: 'info',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => Swal.showLoading()
        });
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'merc_assign_driver_to_client',
                client_id: currentUserId,
                driver_id: driverId,
                _nonce: '<?php echo wp_create_nonce('merc_driver_assign'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '✅ Éxito',
                        text: response.data.message || 'Asignación realizada',
                        confirmButtonColor: '#007bff'
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.data.message || 'Error al guardar'
                    });
                }
            },
            error: function(err) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error de conexión'
                });
            }
        });
    }
});
</script>