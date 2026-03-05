<?php
/**
 * Role-based Access Filters Module
 * Handle page access restrictions and sidebar filtering
 *
 * @package WPCargo_Access_Control
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if access bypass is enabled for today
 * 
 * @return bool True if bypass is enabled
 */
function wpcac_is_bypass_enabled_today() {
    $bypass_date = get_option('merc_skip_blocks_today');
    return $bypass_date === wpcac_get_today();
}

/**
 * Get current date in Y-m-d format
 * 
 * @return string Current date
 */
function wpcac_get_today() {
    return current_time('Y-m-d');
}

/**
 * Main template redirect handler - restrict page access
 */
function wpcac_template_redirect_handler() {
    // Don't perform checks for AJAX/REST/CLI requests
    if (defined('DOING_AJAX') && DOING_AJAX) {
        return;
    }
    if (defined('REST_REQUEST') && REST_REQUEST) {
        return;
    }
    if (defined('WP_CLI') && WP_CLI) {
        return;
    }

    if (!is_user_logged_in()) {
        return;
    }

    $current_user = wp_get_current_user();
    $current_user_roles = is_object($current_user) && isset($current_user->roles) && is_array($current_user->roles) 
        ? $current_user->roles 
        : array();

    // Administrators bypass all checks
    if (in_array('administrator', (array) $current_user_roles, true)) {
        return;
    }

    // If bypass is enabled, allow access to everyone
    if (wpcac_is_bypass_enabled_today()) {
        return;
    }

    $email = strtolower($current_user->user_email);
    $permissions = wpcac_get_user_permissions($email);

    if (empty($permissions)) {
        return; // No restrictions for this user
    }

    // Normalize current path
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $path = rtrim($path, '/');

    // Always allowed paths
    $always_allowed = array('/wp-login.php', '/wp-admin', '/wp-json', '/admin-post.php');
    foreach ($always_allowed as $allowed) {
        if ($allowed !== '' && strpos($path, $allowed) === 0) {
            return;
        }
    }

    // Allow static assets
    $ext = pathinfo($path, PATHINFO_EXTENSION);
    $static_exts = array('css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'woff', 'woff2', 'map', 'ico', 'ttf', 'eot', 'otf');
    if ($ext && in_array(strtolower($ext), $static_exts, true)) {
        return;
    }

    // Check if user can access current path
    if (!wpcac_current_user_can_access($path)) {
        wp_redirect(home_url());
        exit;
    }
}

add_action('template_redirect', 'wpcac_template_redirect_handler');

/**
 * Filter sidebar menu items
 */
function wpcac_filter_sidebar_menu_items($items) {
    if (!is_user_logged_in()) {
        return $items;
    }

    $email = strtolower(wp_get_current_user()->user_email);
    $permissions = wpcac_get_user_permissions($email);

    if (empty($permissions)) {
        return $items;
    }

    foreach ($items as $key => $item) {
        $permalink = $item['permalink'] ?? '';
        $permitido = false;

        foreach ($permissions as $pagina) {
            if (strpos($permalink, $pagina) !== false) {
                $permitido = true;
                break;
            }
        }

        if (!$permitido) {
            unset($items[$key]);
        }
    }

    return $items;
}

add_filter('wpcfe_after_sidebar_menu_items', 'wpcac_filter_sidebar_menu_items', 99);

/**
 * Filter sidebar menus
 */
function wpcac_filter_sidebar_menus($items) {
    if (!is_user_logged_in()) {
        return $items;
    }

    $email = strtolower(wp_get_current_user()->user_email);
    $permissions = wpcac_get_user_permissions($email);

    if (empty($permissions)) {
        return $items;
    }

    foreach ($items as $key => $item) {
        $permalink = $item['permalink'] ?? '';
        $permitido = false;

        foreach ($permissions as $pagina) {
            if (strpos($permalink, $pagina) !== false) {
                $permitido = true;
                break;
            }
        }

        if (!$permitido) {
            unset($items[$key]);
        }
    }

    return $items;
}

add_filter('wpcfe_after_sidebar_menus', 'wpcac_filter_sidebar_menus', 99);

/**
 * Output CSS to hide restricted items
 */
function wpcac_output_hide_css() {
    if (!is_user_logged_in()) {
        return;
    }

    $email = strtolower(wp_get_current_user()->user_email);
    $permissions = wpcac_get_user_permissions($email);

    if (empty($permissions)) {
        return;
    }

    $paginas_permitidas = $permissions;

    $todos_los_items = array(
        '/dashboard/?wpcfe=add'       => '.sidebar-fixed .list-group-item[href*="wpcfe=add"], .mobile-sidebar-menu .list-group-item[href*="wpcfe=add"]',
        '/dashboard/historial'        => '.sidebar-fixed .list-group-item:has(.fa-cubes), .mobile-sidebar-menu .list-group-item:has(.fa-cubes)',
        '/import-export/?type=import' => '.sidebar-fixed .list-group-item[href*="import-export"], .mobile-sidebar-menu .list-group-item[href*="import-export"]',
        '/receiving/'                 => '.sidebar-fixed .list-group-item[href*="receiving"], .mobile-sidebar-menu .list-group-item[href*="receiving"]',
        '/almacen-de-productos/'      => '.sidebar-fixed .list-group-item[href*="almacen-de-productos"], .mobile-sidebar-menu .list-group-item[href*="almacen-de-productos"]',
        '/panel-admin/'               => '.sidebar-fixed .list-group-item[href*="panel-admin"], .mobile-sidebar-menu .list-group-item[href*="panel-admin"]',
        '/devoluciones/'              => '.sidebar-fixed .list-group-item[href*="devoluciones"], .mobile-sidebar-menu .list-group-item[href*="devoluciones"]',
        '/containers/'                => '.sidebar-fixed .list-group-item[href*="containers"], .mobile-sidebar-menu .list-group-item[href*="containers"]',
        '/wpcumanage-users/'          => '.sidebar-fixed .list-group-item[href*="wpcumanage-users"], .mobile-sidebar-menu .list-group-item[href*="wpcumanage-users"]',
        '/wpcpod-report-order/'       => '.sidebar-fixed .list-group-item[href*="wpcpod-report-order"], .mobile-sidebar-menu .list-group-item[href*="wpcpod-report-order"]',
    );

    $css_ocultar = array();

    foreach ($todos_los_items as $url => $selector) {
        $permitido = false;

        foreach ($paginas_permitidas as $pagina) {
            if (strpos($url, $pagina) !== false) {
                $permitido = true;
                break;
            }
        }

        if (!$permitido) {
            $css_ocultar[] = $selector;
        }
    }

    if (!empty($css_ocultar)) {
        echo '<style>' . implode(', ', $css_ocultar) . ' { display: none !important; }</style>';
    }
}

add_action('wp_head', 'wpcac_output_hide_css');

/**
 * Also filter in admin to prevent access
 */
function wpcac_admin_init_check() {
    if (!is_user_logged_in()) {
        return;
    }

    $current_user = wp_get_current_user();

    // Special users with full access
    $super_admins = array('mercourier2019@gmail.com', 'davidmorilloacuna@gmail.com');

    if (in_array($current_user->user_email, $super_admins, true)) {
        return;
    }

    // Block non-admin access to wp-admin
    if (!in_array('administrator', (array) $current_user->roles, true)) {
        wp_redirect(home_url());
        exit;
    }
}

add_action('admin_init', 'wpcac_admin_init_check', 1);

/**
 * Allow specific super admins full permissions
 */
function wpcac_allow_full_permissions() {
    $user = wp_get_current_user();

    if (!is_object($user) || !isset($user->user_email)) {
        return array();
    }

    if ($user->user_email === 'mercourier2019@gmail.com') {
        return array_fill_keys(
            array_keys(get_role('administrator')->capabilities),
            true
        );
    }

    return array();
}

add_filter('user_has_cap', function($allcaps, $caps, $args, $user) {
    if (isset($user->user_email) && $user->user_email === 'mercourier2019@gmail.com') {
        return array_fill_keys(array_keys($allcaps), true);
    }
    return $allcaps;
}, 10, 4);
