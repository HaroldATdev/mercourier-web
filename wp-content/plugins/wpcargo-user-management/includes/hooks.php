<?php
if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly
}
function wpcumanage_user_table_header_user_id()
{
  echo "<th class='wpcumanage-userid-header'>" . __('User ID', 'wpcargo-umanagement') . "</th>";
}
function wpcumanage_user_table_data_user_id($user)
{
  echo '<td class="wpcumanage-user_id">' . wpcuser_prefix_id($user) . '' . wpcuser_unique_id($user) . '</td>';
}

function wpcumanage_user_table_header_username()
{
  echo "<th class='wpcumanage-usename-header'>" . __('Username', 'wpcargo-umanagement') . "</th>";
}
function wpcumanage_user_table_data_username($user)
{
  echo '<td class="wpcumanage-user_login">' . get_avatar($user->user_email, 32) . '' . $user->user_login . '</td>';
}
function wpcumanage_user_table_header_name()
{
  echo "<th class='wpcumanage-name-header'>" . __('Name', 'wpcargo-umanagement') . "</th>";
}
function wpcumanage_user_table_data_name($user)
{
  global $wpcargo;
  echo '<td class="wpcumanage-name">' . $wpcargo->user_fullname($user->ID) . '</td>';
}
function wpcumanage_user_table_header_email()
{
  echo "<th class='wpcumanage-email-header'>" . __('Email', 'wpcargo-umanagement') . "</th>";
}
function wpcumanage_user_table_data_email($user)
{
  echo '<td class="wpcumanage-email">' . $user->user_email . '</td>';
}
function wpcumanage_user_table_header_roles()
{
  echo "<th style='width:160px;' class='wpcumanage-roles-header'>" . __('Roles', 'wpcargo-umanagement') . "</th>";
}
function wpcumanage_user_table_data_roles($user)
{
  $roles = array_map(function ($role) {
    global $wp_roles;
    return translate_user_role($wp_roles->roles[$role]['name']);
  }, $user->roles);
  echo '<td class="wpcumanage-roles">' . implode(', ', $roles) . '</td>';
}
function wpcumanage_user_table_header_groups()
{
  echo "<th style='width:160px;' class='wpcumanage-groups-header'>" . __('Groups', 'wpcargo-umanagement') . "</th>";
}
function wpcumanage_user_table_data_groups($user)
{
  $_user_id     = $user->ID;
  $_groups      = wpcumanage_get_all_user_group_id_and_label();
  $_user_groups = wpcumanage_get_all_user_groups($_user_id);
  $_output      = '';
  foreach ($_groups as $_key => $_value) {
    if (in_array($_value->user_group_id, $_user_groups)) {
      $_output .= $_value->label . ', ';
    }
  }
  echo '<td class="wpcumanage-group">' . rtrim($_output, ', ') . '</td>';
}

// ── NUEVA: Columna Nombre Completo ────────────────────────────────────────
function wpcumanage_user_table_header_fullname()
{
  echo "<th class='wpcumanage-fullname-header'>Nombre Completo</th>";
}
function wpcumanage_user_table_data_fullname($user)
{
  $nombre = '';

  if ( in_array( 'wpcargo_client', $user->roles ) ) {
    // Para clientes: intentar billing_first_name / billing_last_name primero
    $first  = get_user_meta( $user->ID, 'billing_first_name', true );
    $last   = get_user_meta( $user->ID, 'billing_last_name',  true );
    $nombre = trim( $first . ' ' . $last );
  }

  // Fallback a first_name / last_name estándar de WordPress
  if ( empty( $nombre ) ) {
    $first  = get_user_meta( $user->ID, 'first_name', true );
    $last   = get_user_meta( $user->ID, 'last_name',  true );
    $nombre = trim( $first . ' ' . $last );
  }

  // Último fallback: display_name
  if ( empty( $nombre ) ) {
    $nombre = $user->display_name;
  }

  echo '<td class="wpcumanage-fullname">' . esc_html( $nombre ) . '</td>';
}
// ─────────────────────────────────────────────────────────────────────────

function wpcumanage_user_table_header_blocked()
{
  echo "<th style='width:160px;' class='wpcumanage-blocked-header'>" . __('Bloqueado', 'wpcargo-umanagement') . "</th>";
}
function wpcumanage_user_table_data_blocked($user)
{
  $client_id = $user->ID;
  $esta_bloqueado = false;
  
  error_log("🔍 TABLA USUARIOS - Verificando bloqueo para User #{$client_id} ({$user->user_login})");
  
  // Verificar si es un cliente (rol WPCargo Client)
  if (in_array('wpcargo_client', $user->roles)) {
    error_log("   ✓ Es cliente WPCargo");
    
    // 1. Verificar bloqueo manual explícito
    $bloqueado_manual = get_user_meta($client_id, 'merc_bloqueado_manual', true);
    if ($bloqueado_manual == '1') {
      error_log("   ✓ Tiene bloqueo manual explícito");
      $esta_bloqueado = true;
    }
    
    // 2. Verificar bloqueo automático por tipo de envío (si existen estas metas)
    if (!$esta_bloqueado) {
      $tipo_normal_bloqueado = get_user_meta($client_id, 'merc_tipo_normal_bloqueado', true);
      $tipo_express_bloqueado = get_user_meta($client_id, 'merc_tipo_express_bloqueado', true);
      $tipo_full_fitment_bloqueado = get_user_meta($client_id, 'merc_tipo_full_fitment_bloqueado', true);
      
      if ($tipo_normal_bloqueado == '1' || $tipo_express_bloqueado == '1' || $tipo_full_fitment_bloqueado == '1') {
        error_log("   ✓ Al menos un tipo de envío está bloqueado (bloqueo automático)");
        $esta_bloqueado = true;
      }
    }
    
    // 3. Verificar bloqueo automático por envíos pendientes
    if (!$esta_bloqueado && function_exists('merc_cliente_tiene_envios_pendientes_hoy')) {
      error_log("   ✓ Función de bloqueo por envíos pendientes existe, ejecutando...");
      $esta_bloqueado = merc_cliente_tiene_envios_pendientes_hoy($client_id);
      error_log("   📊 Bloqueo automático por envíos pendientes: " . ($esta_bloqueado ? "BLOQUEADO" : "NO BLOQUEADO"));
    }
    
    // 4. Verificar bloqueo automático por horario
    if (!$esta_bloqueado) {
      $bloqueado_por_hora_normal    = false;
      $bloqueado_por_hora_express   = false;
      $bloqueado_por_hora_full      = false;
      
      if (function_exists('merc_check_tipo_normal_blocked')) {
        $bloqueado_por_hora_normal = merc_check_tipo_normal_blocked($client_id);
        if ($bloqueado_por_hora_normal) error_log("   ✓ BLOQUEADO: Es tarde para envíos NORMAL");
      }
      if (function_exists('merc_check_tipo_express_blocked')) {
        $bloqueado_por_hora_express = merc_check_tipo_express_blocked($client_id);
        if ($bloqueado_por_hora_express) error_log("   ✓ BLOQUEADO: Es tarde para envíos EXPRESS");
      }
      if (function_exists('merc_check_tipo_full_fitment_blocked')) {
        $bloqueado_por_hora_full = merc_check_tipo_full_fitment_blocked($client_id);
        if ($bloqueado_por_hora_full) error_log("   ✓ BLOQUEADO: Es tarde para envíos FULL FITMENT");
      }
      
      $esta_bloqueado = $bloqueado_por_hora_normal || $bloqueado_por_hora_express || $bloqueado_por_hora_full;
      if ($esta_bloqueado) error_log("   📊 Bloqueo automático por horario: BLOQUEADO");
    }
    
    error_log("   📊 Resultado final: " . ($esta_bloqueado ? "BLOQUEADO" : "NO BLOQUEADO"));
  } else {
    error_log("   ℹ️ No es cliente WPCargo (roles: " . implode(', ', $user->roles) . ")");
  }
  
  if ( in_array('wpcargo_client', $user->roles) ) {
    if ($esta_bloqueado) {
      echo '<td class="wpcumanage-blocked"><button class="btn btn-sm btn-danger wpcum-unblock-user px-2 m-0 text-white" data-id="' . $user->ID . '">' . __('Desbloquear', 'wpcargo-umanagement') . '</button></td>';
    } else {
      echo '<td class="wpcumanage-blocked"><button class="btn btn-sm btn-warning wpcum-block-user px-2 m-0 text-dark" data-id="' . $user->ID . '">' . __('Bloquear', 'wpcargo-umanagement') . '</button></td>';
    }
  } else {
    echo '<td class="wpcumanage-blocked"><span class="text-muted">-</span></td>';
  }
}
function wpcumanage_user_table_header_status()
{
  echo '<th class="wpcumanage-status-header">' . __('Status', 'wpcargo-umanagement') . '</th>';
}
function wpcumanage_user_table_data_status($user)
{
  $label = __('Active', 'wpcargo-umanagement');
  if (in_array('wpcargo_pending_client', $user->roles) || empty($user->roles)) {
    $label = '<button class="btn btn-sm btn-info wpcfe-approve-client px-2 m-0 text-white" data-id="' . $user->ID . '">' . __('Approve', 'wpcargo-umanagement') . '</button>';
  }
  echo '<td class="wpcumanage-status">' . $label . '</td>';
}
function wpcumanage_user_table_header_access()
{
  echo '<th class="wpcumanage-access-header">' . __('Access', 'wpcargo-umanagement') . '</th>';
}
function wpcumanage_user_table_data_access($user)
{
  $access          = wpcumanage_user_access($user->ID);
  $access_count    = is_array($access) ? count($access)  : 0;
  $str_access      = is_array($access) ? implode(',', $access)  : '';
  $class           = $access_count > 0 ? 'btn-info' : 'btn-light text-dark';
  echo '<td class="wpcumanage-access"><a data-id="' . $user->ID . '"  data-access="' . $str_access . '" class="btn ' . $class . ' btn-sm p-2 font-weight-bold wpcumange-update-access" data-toggle="modal" data-target="#wpcumanageAccessModal">(' . $access_count . ') ' . __('Access', 'wpcargo-umanagement') . '</a></td>';
}
function wpcumanage_user_table_header_defaults()
{
  echo '<th class="wpcumanage-default-header">' . __('Default Users', 'wpcargo-umanagement') . '</th>';
}
function wpcumanage_user_table_data_defaults($user)
{
  $default_users   = wpcumanage_default_users($user->ID);
  $access_count    = count($default_users);
  $class           = $access_count > 0 ? 'btn-info' : 'btn-light text-dark';
  echo '<td class="wpcumanage-default"><a data-id="' . $user->ID . '"  data-default="' . htmlspecialchars(wp_json_encode($default_users)) . '" class="btn ' . $class . ' btn-sm p-2 font-weight-bold wpcumange-update-assign_user" data-toggle="modal" data-target="#wpcumanageAssingmentModal">(' . $access_count . ') ' . __('Defaults', 'wpcargo-umanagement') . '</a></td>';
}
function wpcumanage_user_saved_callback()
{
  if (!isset($_GET['ustat']) && !isset($_GET['umsg'])) {
    return false;
  }
  $status = $_GET['ustat'] == 'success' ? 'success' : 'danger';
?>
  <div id="wpcumange-user-notification" class="alert alert-<?php echo $status; ?> p-2">
    <?php echo $_GET['umsg']; ?>
  </div>
  <script>
    setTimeout(() => {
      jQuery('#wpcumange-user-notification').remove();
    }, 6000);
    var uri = window.location.toString();
    if (uri.indexOf("?") > 0) {
      var clean_uri = uri.substring(0, uri.indexOf("?"));
      <?php if ($status != 'success'): ?>
        clean_uri = clean_uri + "?uaction=add";
      <?php endif; ?>
      window.history.replaceState({}, document.title, clean_uri);
    }
  </script>
<?php
}
// Hooks & Filters
function wpcumanage_row_action_callback($actions)
{
  $mylinks = array(
    '<a href="' . admin_url('admin.php?page=wptaskforce-helper') . '" aria-label="' . __('License', 'wpcargo-umanagement') . '">' . __('License', 'wpcargo-umanagement') . '</a>'
  );
  $actions = array_merge($actions, $mylinks);
  return $actions;
}
function wpcumanage_load_textdomain()
{
  load_plugin_textdomain('wpcargo-umanagement', false, '/wpcargo-user-management/languages');
}


// Update User Group
function wpcumanage_save_account_user_group_callback($user_data, $data, $user_id = "")
{
  $_user_id     = !empty($user_id) ? $user_id : $user_data->ID;
  $_user_groups = !empty($data['_groups']) ? $data['_groups'] : array();
  update_user_meta($_user_id, 'user_groups', maybe_serialize($_user_groups));
  do_action('um_after_save_user_data', $_user_id, $data);
}

add_action('wp_head', function () {
  global $wpdb;
  $table_name = $wpdb->prefix . WPCU_MANAGEMENT_DB_USER_GROUP;
  $results = $wpdb->get_results("SELECT `user_group_id`, `users` FROM " . $table_name);
  $user_groups = array();
  foreach ($results as $key => $value) {
    $user_ids = is_serialized($value->users) ? maybe_unserialize($value->users) : array();
    if (!empty($user_ids)) {
      foreach ($user_ids as $user_id) {
        $groups = $value->user_group_id;
        $user_groups[$user_id][] = $groups;
      }
    }
  }
  foreach ($user_groups as $user_id => $groups) {
    if (!metadata_exists('user', $user_id, 'user_groups')) {
      update_user_meta($user_id, 'user_groups', maybe_serialize($groups));
    }
  }
});

function wpcumanage_user_group_narivation_callback()
{
  $page_url   = get_the_permalink(wpcumanage_users_page()) . '?umpage=group';
?>
  <div class="row border-bottom">
    <div class="col-md-8">
      <div id="wpcumanage-optpage" class="pb-2">
        <button id="wpcumanage-add-group" type="button" class="btn btn-info btn-sm waves-effect waves-light" data-toggle="modal" data-target="#addUserGroupModal"><i class="fa fa-plus text-white"></i> <?php echo wpcumanage_add_group_label(); ?></button>
      </div>
    </div>
    <div class="col-md-4">
      <form id="wpcumanage-search" class="float-md-none float-lg-right" action="<?php echo $page_url; ?>" method="get">
        <div class="form-inline">
          <label for="search-payment" class="sr-only"><?php esc_html_e('User Group', 'wpcargo-umanagement'); ?></label>
          <input type="hidden" name="umpage" value="group">
          <input type="text" class="form-control form-control-sm" name="umsearch" id="umsearch" placeholder="<?php echo wpcumanage_group_name_label(); ?>">
          <button type="submit" class="btn btn-primary btn-sm mx-md-0 ml-2"><?php esc_html_e('Search', 'wpcargo-umanagement'); ?></button>
        </div>
      </form>
    </div>
  </div>
<?php
}


function wpcumanage_user_group_add_modal_callback()
{
?>
  <div class="modal fade top" id="addUserGroupModal" tabindex="-1" role="dialog" aria-labelledby="addUserGroupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <form id="addUserGroup-form" data-type="add">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="addUserGroupModalLabel"><?php echo wpcumanage_add_group_label(); ?></h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <div class="col-md-12">
              <div class="form-group">
                <label for="wpcumanage_ug_label"><?php echo wpcumanage_group_name_label(); ?></label>
                <input id="wpcumanage_ug_label" type="text" class="form-control" name="wpcumanage_ug_label">
              </div>
            </div>
            <div class="col-md-12">
              <div class="form-group">
                <label for="wpcumanage_ug_label"><?php echo wpcumanage_description_label(); ?></label>
                <textarea rows="4" name="wpcumanage_ug_desc" id="wpcumanage_ug_desc" class="form-control wpcumanage_ug_desc" value=""></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal"><?php esc_html_e('Close', 'wpcargo-umanagement'); ?></button>
            <button type="submit" class="btn btn-sm btn-primary"><?php esc_html_e('Add', 'wpcargo-umanagement'); ?></button>
          </div>
        </div>
      </form>
    </div>
  </div>
<?php
}

/**
 * Menu items list for per-employee access control
 */
function wpcumanage_menu_items($prefer_frontend = false)
{
  $items = array();
  if ( $prefer_frontend ) {
    $actions_to_capture = array(
      'wpcfe_after_create_shipment',
      'wpcfe_after_add_shipment',
      'wpcfe_before_add_shipment',
      'wpcfe_after_sidebar_custom_menu'
    );
    foreach ( $actions_to_capture as $act ) {
      try {
        ob_start();
        do_action( $act );
        $html = ob_get_clean();
        if ( ! empty( $html ) ) {
          if ( preg_match_all('/<a\b[^>]*>(.*?)<\/a>/is', $html, $matches) ) {
            foreach ( $matches[0] as $i => $full ) {
              $anchor = $full;
              $href = '';
              if ( preg_match('/href\s*=\s*"([^"]+)"/i', $anchor, $hm) || preg_match("/href\s*=\s*'([^']+)'/i", $anchor, $hm) ) {
                $href = isset($hm[1]) ? $hm[1] : '';
              }
              $text = trim( strip_tags( $matches[1][$i] ) );
              if ( empty( $text ) ) continue;
              $slug = sanitize_title( $text );
              if ( ! isset( $items[$slug] ) ) {
                $items[$slug] = $text;
              }
            }
          }
        }
      } catch ( Exception $e ) {
        @ob_end_clean();
      }
    }
  }
  if (function_exists('wpcfe_after_sidebar_menu_items')) {
    $sidebar_items = wpcfe_after_sidebar_menu_items();
    if (!empty($sidebar_items) && is_array($sidebar_items)) {
      foreach ($sidebar_items as $key => $entry) {
        $label = is_array($entry) && !empty($entry['label']) ? $entry['label'] : (is_string($entry) ? $entry : '');
        $label = trim(strip_tags($label));
        if (empty($label)) continue;
        $slug = sanitize_title($label);
        if (!isset($items[$slug])) $items[$slug] = $label;
      }
    }
  }

  if (function_exists('wpcfe_after_sidebar_menus')) {
    $more = wpcfe_after_sidebar_menus();
    if (!empty($more) && is_array($more)) {
      foreach ($more as $key => $entry) {
        $label = is_array($entry) && !empty($entry['label']) ? $entry['label'] : (is_string($entry) ? $entry : '');
        $label = trim(strip_tags($label));
        if (empty($label)) continue;
        $slug = sanitize_title($label);
        if (!isset($items[$slug])) $items[$slug] = $label;
      }
    }
  }

  if (function_exists('wp_get_nav_menu_locations') && function_exists('wp_get_nav_menu_items')) {
    $locations = wp_get_nav_menu_locations();
    if (!empty($locations) && !empty($locations['wpcfe-dashboard-sidebar-menu'])) {
      $menu_id = $locations['wpcfe-dashboard-sidebar-menu'];
      $menu_items = wp_get_nav_menu_items($menu_id);
      if (!empty($menu_items)) {
        foreach ($menu_items as $mi) {
          $title = trim(strip_tags($mi->title));
          if (empty($title)) continue;
          $slug = sanitize_title($title);
          if (!isset($items[$slug])) $items[$slug] = $title;
        }
      }
    }
  }

  if ( ! $prefer_frontend ) {
    $cached = get_option('wpcumanage_menu_items_cache', array());
    if (!empty($cached) && is_array($cached)) {
      foreach ($cached as $slug => $label) {
        if (!isset($items[$slug])) $items[$slug] = $label;
      }
    }
  }

  if (empty($items)) {
    $items = array(
      'dashboard'  => __('Dashboard', 'wpcargo-umanagement'),
      'shipments'  => __('Shipments', 'wpcargo-umanagement'),
      'containers' => __('Containers', 'wpcargo-umanagement'),
      'reports'    => __('Reports', 'wpcargo-umanagement'),
      'invoices'   => __('Invoices', 'wpcargo-umanagement'),
      'settings'   => __('Settings', 'wpcargo-umanagement')
    );
  }
  return apply_filters('wpcumanage_menu_items', $items);
}

/**
 * Cache admin menu items (run in admin so global $menu is available)
 */
function wpcumanage_cache_admin_menu_items()
{
  if (!is_admin()) return;
  global $menu;
  if (empty($menu) || !is_array($menu)) return;
  $items = array();
  foreach ($menu as $m) {
    $title = trim(strip_tags($m[0]));
    if (empty($title)) continue;
    $slug = sanitize_title($title);
    $items[$slug] = $title;
  }
  if (!empty($items)) update_option('wpcumanage_menu_items_cache', $items);
}

function wpcumanage_cache_frontend_nav_menus()
{
  if (!is_admin()) return;
  if (!function_exists('wp_get_nav_menu_locations')) return;
  $locations = wp_get_nav_menu_locations();
  if (empty($locations) || !is_array($locations)) return;
  $items = array();
  foreach ($locations as $loc => $menu_id) {
    $menu_items = wp_get_nav_menu_items($menu_id);
    if (empty($menu_items)) continue;
    foreach ($menu_items as $mi) {
      if (empty($mi->title)) continue;
      $title = trim(strip_tags($mi->title));
      if (empty($title)) continue;
      $slug = sanitize_title($title);
      if (!isset($items[$slug])) $items[$slug] = $title;
    }
  }
  if (!empty($items)) update_option('wpcumanage_menu_items_cache', $items);
}
add_action('admin_init', 'wpcumanage_cache_frontend_nav_menus', 101);

/**
 * Render menu-permissions UI on user form end
 */
function wpcumanage_user_menu_permissions_ui($_user_id = 0)
{
  static $wpcumanage_menu_ui_rendered = false;
  if ( $wpcumanage_menu_ui_rendered ) return;
  $user_id = 0;
  if (is_object($_user_id) && isset($_user_id->ID)) {
    $user_id = intval($_user_id->ID);
  } elseif (!empty($_user_id) && is_numeric($_user_id)) {
    $user_id = intval($_user_id);
  } elseif (!empty($_REQUEST['uid'])) {
    $user_id = intval($_REQUEST['uid']);
  } elseif (!empty($_GET['uid'])) {
    $user_id = intval($_GET['uid']);
  }
  $menu_items = wpcumanage_menu_items(true);
  $saved = $user_id ? get_user_meta($user_id, 'wpcumanage_menu_access', true) : array();
  $saved = is_array($saved) ? $saved : array();
  $wpcumanage_menu_ui_rendered = true;
  ?>
  <div class="row mb-4 wpcumanage-menu-access">
    <div class="col-sm-12">
      <h2 class="h6 py-2 border-bottom font-weight-bold"><?php _e('Menu Access (Empleado)', 'wpcargo-umanagement'); ?></h2>
    </div>
    <div class="col-sm-12">
      <p class="text-muted"><?php _e('Seleccione los módulos que este empleado puede ver en el menú lateral.', 'wpcargo-umanagement'); ?></p>
        <style>
          .wpcumanage-menu-access .wpcumanage-table td .wpcumanage-checkbox-wrap { display:inline-flex; align-items:center; gap:8px; }
          .wpcumanage-menu-access .wpcumanage-table td input[type="checkbox"] { width:18px; height:18px; margin:0; vertical-align:middle; visibility:visible; }
          .wpcumanage-menu-access .wpcumanage-table { border-collapse:collapse; }
          .wpcumanage-menu-access .wpcumanage-table td { padding:8px 6px; vertical-align:middle; }
        </style>
        <style>
          .wpcumanage-menu-access .wpcumanage-list { max-width:700px; }
          .wpcumanage-menu-access .list-group-item { border:0; padding:8px 12px; }
          .wpcumanage-menu-access input[type="checkbox"],
          .wpcumanage-menu-access .form-check-input {
          width:18px !important;
          height:18px !important;
          margin:0 !important;
          vertical-align:middle !important;
          opacity:1 !important;
          visibility:visible !important;
          display: inline-block !important;
        }
          .wpcumanage-menu-access .item-label { display:block; }
        </style>

        <div class="wpcumanage-list">
          <ul class="list-group list-group-flush">
            <?php foreach ($menu_items as $slug => $label): ?>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                <span class="item-label"><?php echo esc_html($label); ?></span>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="_menu_access_<?php echo esc_attr($slug); ?>" name="_menu_access[]" value="<?php echo esc_attr($slug); ?>" <?php echo in_array($slug, $saved) ? 'checked' : ''; ?> />
                  <label class="form-check-label sr-only" for="_menu_access_<?php echo esc_attr($slug); ?>"><?php _e('Ver', 'wpcargo-umanagement'); ?></label>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
    </div>
  </div>
  <script>
    (function($){
      var selector = 'select[name="_roles[]"]';
      var $roles = $(selector);

      function hasEmployeeRole(vals){
        if(!vals) return false;
        if($.isArray(vals)) return vals.indexOf('wpcargo_employee') !== -1;
        if(typeof vals === 'string') return vals.split(',').indexOf('wpcargo_employee') !== -1;
        return false;
      }

      function toggleMenuAccess(){
        try{
          var vals = $roles.val();
          var show = hasEmployeeRole(vals);
          if(show) $('.wpcumanage-menu-access').show(); else $('.wpcumanage-menu-access').hide();
        }catch(e){
          console && console.warn && console.warn('wpcumanage: toggleMenuAccess error', e);
        }
      }

      function removeDuplicateCheckboxes(){
        $('.wpcumanage-list .list-group-item').each(function(){
          var $inputs = $(this).find('input[type="checkbox"]');
          if($inputs.length > 1){
            var keepIndex = 0;
            $inputs.each(function(i){
              var $el = $(this);
              var isVisible = $el.is(':visible') && $el.css('visibility') !== 'hidden' && $el.css('display') !== 'none';
              if(isVisible){ keepIndex = i; return false; }
            });
            $inputs.each(function(i){ if(i !== keepIndex) $(this).remove(); });
          }
        });
      }

      function observeDuplicateCheckboxes(){
        var target = document.querySelector('.wpcumanage-list');
        if(!target || typeof MutationObserver === 'undefined') return;
        var observer = new MutationObserver(function(mutations){
          mutations.forEach(function(m){
            removeDuplicateCheckboxes();
          });
        });
        observer.observe(target, { childList: true, subtree: true });
      }

      function bindEvents(){
        $(document).off('change.wpcumanage', selector).on('change.wpcumanage', selector, toggleMenuAccess);
        $(document).off('select2:select.wpcumanage select2:unselect.wpcumanage').on('select2:select.wpcumanage select2:unselect.wpcumanage', selector, toggleMenuAccess);
      }

      $(document).ready(function(){
        $roles = $(selector);
        toggleMenuAccess();
        removeDuplicateCheckboxes();
        observeDuplicateCheckboxes();
        bindEvents();
        var tries = 0; var maxTries = 10;
        var poll = setInterval(function(){
          tries++;
          $roles = $(selector);
          removeDuplicateCheckboxes();
          toggleMenuAccess();
          bindEvents();
          if(tries >= maxTries) clearInterval(poll);
        }, 300);
      });
    })(jQuery);
  </script>
  <?php
}
add_action('wpcumanage_user_form_end', 'wpcumanage_user_menu_permissions_ui', 15);
add_action('wpcumanage_after_user_form', 'wpcumanage_user_menu_permissions_ui', 15);
add_action('wpcumanage_user_form_middle', 'wpcumanage_user_menu_permissions_ui', 15);

/**
 * Save menu permissions when user is saved
 */
function wpcumanage_save_menu_permissions($user_id, $data)
{
  if (empty($user_id)) return;
  if (isset($data['_menu_access']) && is_array($data['_menu_access'])) {
    $menu = array_map('sanitize_text_field', $data['_menu_access']);
    update_user_meta($user_id, 'wpcumanage_menu_access', $menu);
    return;
  }

  $roles_from_data = array();
  if (!empty($data) && isset($data['_roles'])) {
    if (is_array($data['_roles'])) {
      $roles_from_data = $data['_roles'];
    } else {
      $roles_from_data = array_map('trim', explode(',', (string) $data['_roles']));
    }
  }
  if (in_array('wpcargo_employee', $roles_from_data)) {
    $all = array_keys(wpcumanage_menu_items(true));
    update_user_meta($user_id, 'wpcumanage_menu_access', $all);
    return;
  }

  $user = get_userdata($user_id);
  $roles = $user ? (array) $user->roles : array();
  if (in_array('wpcargo_employee', $roles)) {
    $all = array_keys(wpcumanage_menu_items(true));
    update_user_meta($user_id, 'wpcumanage_menu_access', $all);
    return;
  }

  delete_user_meta($user_id, 'wpcumanage_menu_access');
}
add_action('um_after_save_user_data', 'wpcumanage_save_menu_permissions', 10, 2);

/**
 * Apply menu permissions in admin area for current user
 */
function wpcumanage_apply_menu_permissions()
{
  if (!is_user_logged_in()) return;
  $current = wp_get_current_user();
  if (in_array('administrator', $current->roles)) return;
  $access = get_user_meta($current->ID, 'wpcumanage_menu_access', true);
  if (empty($access) || !is_array($access)) return;
  global $menu;
  if (empty($menu) || !is_array($menu)) return;
  $labels = array_map('strval', wpcumanage_menu_items());
  $label_map = array();
  foreach ($labels as $slug => $label) {
    $label_map[trim(strip_tags($label))] = $slug;
  }
  foreach ($menu as $idx => $m) {
    $title = isset($m[0]) ? trim(strip_tags($m[0])) : '';
    if (!$title) continue;
    if (isset($label_map[$title])) {
      $slug = $label_map[$title];
      if (!in_array($slug, $access)) {
        unset($menu[$idx]);
      }
    }
  }
}
add_action('admin_menu', 'wpcumanage_apply_menu_permissions', 999);

function wpcumanage_user_group_update_modal_callback()
{
?>
  <div class="modal fade top" id="updateUserGroupModal" tabindex="-1" role="dialog" aria-labelledby="updateUserGroupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <form id="updateUserGroup-form" data-type="update">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="updateUserGroupModalLabel"><?php echo wpcumanage_update_group_label(); ?></h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal"><?php esc_html_e('Close', 'wpcargo-umanagement'); ?></button>
            <button type="submit" class="btn btn-sm btn-primary"><?php echo wpcumanage_update_label(); ?></button>
          </div>
        </div>
      </form>
    </div>
  </div>
<?php
}
function wpcumanage_edit_user_users_group_callback($user_data)
{
  $user_group_ids    = wpcumanage_get_all_user_group_ids();
  $account_group_ids  = wpcumanage_get_array_groups_ids($user_data->ID);
?>
  <h3><?php esc_html_e('WPCargo User Group', 'wpcargo-umanagement'); ?></h3>
  <table id="wpcumanage_edit_group_table" class="form-table">
    <th><label><?php esc_html_e('Groups', 'wpcargo-umanagement'); ?></label></th>
    <td>
      <select name="_groups[]" id="wpcumanage_ug_users" class="_groups" multiple>
        <?php foreach ($user_group_ids as $group_id): ?>
          <option value="<?php echo $group_id; ?>" <?php echo (array_key_exists($group_id, $account_group_ids)) ? 'selected' : ''; ?>><?php echo wpcumanage_get_user_group_label($group_id); ?></option>
        <?php endforeach; ?>
      </select>
    </td>
  </table>
<?php
}
function wpcumanage_edit_user_update_group_callback($user_id, $old_user_data, $userdata)
{
  wpcumanage_save_account_user_group_callback($userdata, $_POST, $user_id);
}

/**
 * DEPRECATED: Desbloqueo manual ahora se maneja en scripts.js
 */
function wpcumanage_unblock_modal_callback()
{
    // Ahora todo se maneja en scripts.js con selector de minutos
}

function wpcumanage_user_form_middle_username($user_data, $is_update)
{
?>
  <div class="row mb-4">
    <div class="col-sm-12">
      <h2 class="h6 py-2 border-bottom font-weight-bold"><?php echo apply_filters('wpcfe_reg_user_info', __('Login Information', 'wpcargo-umanagement')); ?></h2>
    </div>
    <?php wpcumanage_generate_template(wpcum_personal_info_fields(), $user_data, $is_update); ?>
  </div>
<?php
}

function wpcum_update_user_email($user_id)
{
  $email = wp_get_current_user()->user_email ?: '';
?>
  <div class="form-group col-md-6">
    <label for="user_email" class="active">Email</label>
    <input id="user_email" class="form-control " type="text" name="user_email" value="<?php echo $email; ?>">
  </div>
<?php
}

function wpcfe_after_save_profile_email($user_id)
{
  if (!empty($_POST['user_email'])) {
    wp_update_user(array('ID' => $user_id, 'user_email' => $_POST['user_email']));
    $_POST['wpcfe-notification'] = array(
      'status'    => 'success',
      'icon'      => 'check',
      'message'   => __('User Email has been successfully updated.', 'wpcargo-frontend-manager')
    );
  }
}

//** Load Plugin text domain
add_action('plugins_loaded', 'wpcumanage_load_textdomain');
// Create plugin pages
add_action('wp_loaded', 'wpcumanage_create_default_pages');
// Add plugin action links
add_filter('plugin_action_links_' . WPCU_MANAGEMENT_BASENAME, 'wpcumanage_row_action_callback', 10);

function wpcumanage_plugins_loaded_callback()
{
  // FM Scripts
  add_filter('wpcfe_registered_styles', 'wpcumanage_registered_styles');
  add_filter('wpcfe_registered_scripts', 'wpcumanage_registered_scripts');

  // ── User Table Hooks ──────────────────────────────────────────────────────
  // Orden de columnas:
  // 1. User ID
  // 2. Usuario (con avatar)
  // 3. Nombre Completo   ← NUEVA
  // 4. Email
  // 5. Roles
  // 6. Bloqueado
  // 7. Status
  add_action('wpcumanage_user_table_header', 'wpcumanage_user_table_header_user_id',   10);
  add_action('wpcumanage_user_table_data',   'wpcumanage_user_table_data_user_id',     10);

  add_action('wpcumanage_user_table_header', 'wpcumanage_user_table_header_usuario',   20);
  add_action('wpcumanage_user_table_data',   'wpcumanage_user_table_data_usuario',     20);

  add_action('wpcumanage_user_table_header', 'wpcumanage_user_table_header_fullname',  30); // ← NUEVA
  add_action('wpcumanage_user_table_data',   'wpcumanage_user_table_data_fullname',    30); // ← NUEVA

  add_action('wpcumanage_user_table_header', 'wpcumanage_user_table_header_email',     40);
  add_action('wpcumanage_user_table_data',   'wpcumanage_user_table_data_email',       40);

  add_action('wpcumanage_user_table_header', 'wpcumanage_user_table_header_roles',     50);
  add_action('wpcumanage_user_table_data',   'wpcumanage_user_table_data_roles',       50);

  // add_action('wpcumanage_user_table_header', 'wpcumanage_user_table_header_groups',  60);
  // add_action('wpcumanage_user_table_data',   'wpcumanage_user_table_data_groups',    60);

  add_action('wpcumanage_user_table_header', 'wpcumanage_user_table_header_blocked',   70);
  add_action('wpcumanage_user_table_data',   'wpcumanage_user_table_data_blocked',     70);

  add_action('wpcumanage_user_table_header', 'wpcumanage_user_table_header_status',    80);
  add_action('wpcumanage_user_table_data',   'wpcumanage_user_table_data_status',      80);
  // ─────────────────────────────────────────────────────────────────────────

  add_action('wpcumanage_before_user_table', 'wpcumanage_user_saved_callback');
  add_action('wpcumanage_before_user_form', 'wpcumanage_user_saved_callback');
  add_action('wpcumanage_after_user_table_pagination', 'wpcumanage_unblock_modal_callback');

  // User Groups
  add_action('wpcumanage_user_group_before_form', 'wpcumanage_user_group_narivation_callback');
  add_action('wpcumanage_user_group_after_form', 'wpcumanage_user_group_add_modal_callback');
  add_action('wpcumanage_user_group_after_form', 'wpcumanage_user_group_update_modal_callback');
  add_action('wpcumanage_after_save_user', 'wpcumanage_save_account_user_group_callback', 10, 2);

  // Additional fields
  add_action('wpcumanage_user_form_middle', 'wpcumanage_user_form_middle_username', 10, 2);
  add_action('wpcfe_after_personal_details', 'wpcum_update_user_email', 10, 2);
  add_action('wpcfe_after_save_profile', 'wpcfe_after_save_profile_email', 10, 2);

  // wp-admin edit user
  add_action('show_user_profile', 'wpcumanage_edit_user_users_group_callback');
  add_action('edit_user_profile', 'wpcumanage_edit_user_users_group_callback');
  add_action('profile_update', 'wpcumanage_edit_user_update_group_callback', 10, 3);

  // Um Roles
  add_action('wpcargo_after_assign_email', 'wpcum_select_user_roles', 99);

  // Modified Fields
  add_action("wpcfe_billing_address_fields", "wpcfe_billing_address_fields_cb_additional", 10, 1);
}
add_action('plugins_loaded', 'wpcumanage_plugins_loaded_callback');

add_action('user_new_form', 'wpcumanage_user_menu_permissions_ui', 15);

function wpcumanage_admin_user_permissions_modal()
{
  if (!is_admin()) return;
  if (!current_user_can('administrator')) return;
  $items = wpcumanage_menu_items(true);
  if (empty($items)) return;
  ?>
  <div id="wpcumanage-permissions-inline" style="display:none; border:1px solid #e1e1e1; padding:12px; margin-top:10px; background:#fff; border-radius:4px;">
    <h3 style="margin-top:0"><?php _e('Asignar permisos de menú (Empleado)', 'wpcargo-umanagement'); ?></h3>
    <div class="wpcumanage-permissions-list-inline" style="max-height:320px; overflow:auto; margin-bottom:10px;">
      <ul style="list-style:none; padding-left:0; margin:0;">
        <?php foreach ($items as $slug => $label): ?>
          <li style="padding:6px 0;">
            <label><input type="checkbox" class="wpcumanage-inline-checkbox" value="<?php echo esc_attr($slug); ?>"> <?php echo esc_html($label); ?></label>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <p style="margin:0;">
      <button type="button" class="button button-primary" id="wpcumanage-inline-save"><?php _e('Guardar permisos y continuar', 'wpcargo-umanagement'); ?></button>
      <button type="button" class="button" id="wpcumanage-inline-cancel"><?php _e('Cancelar', 'wpcargo-umanagement'); ?></button>
    </p>
  </div>
  <script>
    (function($){
      function showInline(target){
        var $panel = $('#wpcumanage-permissions-inline');
        if(!$panel.length) return;
        if(target && target.length){
          if($panel.parent().length && !$panel.parent().is(target.parent())){
            $panel.detach();
            target.after($panel);
          } else if(!$panel.parent().length){
            target.after($panel);
          }
        }
        $panel.show();
        try{ $panel[0].scrollIntoView({behavior:'smooth', block:'center'}); }catch(e){}
      }
      function hideInline(){ $('#wpcumanage-permissions-inline').hide(); }

      $(document).on('change', 'select[name="role"]', function(){
        try{
          var $sel = $(this);
          var val = $sel.val();
          if(!val) return;
          var open = false;
          if($.isArray(val)){
            open = (val.indexOf('administrator') !== -1);
          } else {
            open = ((''+val).split(',').indexOf('administrator') !== -1);
          }
          if(open) showInline($sel.closest('.form-field, .form-table, p, label'));
          else hideInline();
        }catch(err){ console && console.warn && console.warn('wpcumanage inline change error', err); }
      });

      $(document).on('submit', 'form#your-profile, form#createuser, form#your-profile', function(e){
        try{
          var $form = $(this);
          if($form.data('wpcumanage-inline-submitted')) return true;

          var roleVal = '';
          var $roleSelect = $form.find('select[name="role"]');
          if($roleSelect.length) roleVal = $roleSelect.val();
          if(!roleVal){
            var $rolesInput = $form.find('select[name="_roles[]"]');
            if($rolesInput.length) roleVal = $rolesInput.val();
          }
          var isEmployee = false;
          if($.isArray(roleVal)) isEmployee = (roleVal.indexOf('wpcargo_employee') !== -1);
          else isEmployee = ((''+roleVal).split(',').indexOf('wpcargo_employee') !== -1);
          if(!isEmployee) return true;

          if($form.find('input[name="_menu_access[]"]').length) return true;

          e.preventDefault();
          if($roleSelect.length) showInline($roleSelect.closest('p, .form-field, .form-table'));
          else showInline($form.find(':input').last());

          $('#wpcumanage-inline-cancel').off('click').on('click', function(){ hideInline(); });
          $('#wpcumanage-inline-save').off('click').on('click', function(){
            var vals = [];
            $('.wpcumanage-inline-checkbox:checked').each(function(){ vals.push($(this).val()); });
            for(var i=0;i<vals.length;i++){
              var inpt = $('<input>').attr('type','hidden').attr('name','_menu_access[]').val(vals[i]);
              $form.append(inpt);
            }
            $form.data('wpcumanage-inline-submitted', true);
            if($form.length && $form[0] && typeof $form[0].submit === 'function'){
              $form[0].submit();
            } else {
              $form.trigger('submit');
            }
          });
        }catch(err){ console && console.warn && console.warn('wpcumanage inline error', err); }
      });
    })(jQuery);
  </script>
  <?php
}

function wpcumanage_user_table_header_usuario()
{
    echo "<th class='wpcumanage-usuario-header'>Usuario</th>";
}

function wpcumanage_user_table_data_usuario($user)
{
    if ( in_array('wpcargo_client', $user->roles) ) {
        $billing_company = get_user_meta( $user->ID, 'billing_company', true );
        if ( ! empty( $billing_company ) ) {
            $nombre = $billing_company;
        } else {
            $first = get_user_meta( $user->ID, 'billing_first_name', true );
            $last  = get_user_meta( $user->ID, 'billing_last_name', true );
            $nombre = trim( $first . ' ' . $last );

            if ( empty( $nombre ) ) {
                $first = get_user_meta( $user->ID, 'first_name', true );
                $last  = get_user_meta( $user->ID, 'last_name', true );
                $nombre = trim( $first . ' ' . $last );
            }

            if ( empty( $nombre ) ) {
                $nombre = $user->user_login;
            }
        }
    } else {
        $first = get_user_meta( $user->ID, 'first_name', true );
        $last  = get_user_meta( $user->ID, 'last_name', true );
        $nombre = trim( $first . ' ' . $last );

        if ( empty( $nombre ) ) {
            $nombre = $user->user_login;
        }
    }
    echo '<td class="wpcumanage-usuario">' . get_avatar($user->user_email, 32) . ' ' . esc_html($nombre) . '</td>';
}

add_action('admin_footer-user-new.php', 'wpcumanage_admin_user_permissions_modal');
add_action('admin_footer-user-edit.php', 'wpcumanage_admin_user_permissions_modal');
