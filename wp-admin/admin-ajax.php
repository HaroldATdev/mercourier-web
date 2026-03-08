<?php
/**
 * WordPress Ajax Process Execution
 *
 * @package WordPress
 * @subpackage Administration
 *
 * @link https://developer.wordpress.org/plugins/javascript/ajax
 */

/**
 * Executing Ajax process.
 *
 * @since 2.1.0
 */
define( 'DOING_AJAX', true );
error_log('🔴 [AJAX-INIT] admin-ajax.php iniciado');
if ( ! defined( 'WP_ADMIN' ) ) {
	define( 'WP_ADMIN', true );
}

/** Load WordPress Bootstrap */
require_once dirname( __DIR__ ) . '/wp-load.php';
error_log('🔴 [AJAX-INIT] wp-load.php cargado');

/** Allow for cross-domain requests (from the front end). */
send_origin_headers();
error_log('🔴 [AJAX-INIT] Headers enviados');

header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
header( 'X-Robots-Tag: noindex' );

// Require a valid action parameter.
if ( empty( $_REQUEST['action'] ) || ! is_scalar( $_REQUEST['action'] ) ) {
	error_log('🔴 [AJAX-INIT] No action parameter found');
	wp_die( '0', 400 );
}

error_log('🔴 [AJAX-INIT] Action parameter válido: ' . sanitize_text_field($_REQUEST['action']));

/** Load WordPress Administration APIs */
require_once ABSPATH . 'wp-admin/includes/admin.php';
error_log('🔴 [AJAX-INIT] admin.php cargado');

/** Load Ajax Handlers for WordPress Core */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';
error_log('🔴 [AJAX-INIT] ajax-actions.php cargado');

send_nosniff_header();
nocache_headers();
error_log('🔴 [AJAX-INIT] Headers de nosniff/nocache establecidos');

/** This action is documented in wp-admin/admin.php */
error_log('🔴 [AJAX-INIT] Antes de do_action admin_init');

// Skip admin_init callbacks entirely for AJAX to avoid blocking hooks.
if ( defined('DOING_AJAX') && DOING_AJAX ) {
    remove_all_actions('admin_init');
    error_log('🔴 [AJAX-INIT] Removed all admin_init actions for AJAX');
}

// Execute admin_init (now empty in AJAX)
do_action( 'admin_init' );

// restore error handler context
restore_error_handler();
$elapsed = microtime(true) - $start_time;
error_log('🔴 [AJAX-INIT] Después de do_action admin_init (' . round($elapsed, 3) . 's)');


$core_actions_get = array(
	'fetch-list',
	'ajax-tag-search',
	'wp-compression-test',
	'imgedit-preview',
	'oembed-cache',
	'autocomplete-user',
	'dashboard-widgets',
	'logged-in',
	'rest-nonce',
);

$core_actions_post = array(
	'oembed-cache',
	'image-editor',
	'delete-comment',
	'delete-tag',
	'delete-link',
	'delete-meta',
	'delete-post',
	'trash-post',
	'untrash-post',
	'delete-page',
	'dim-comment',
	'add-link-category',
	'add-tag',
	'get-tagcloud',
	'get-comments',
	'replyto-comment',
	'edit-comment',
	'add-menu-item',
	'add-meta',
	'add-user',
	'closed-postboxes',
	'hidden-columns',
	'update-welcome-panel',
	'menu-get-metabox',
	'wp-link-ajax',
	'menu-locations-save',
	'menu-quick-search',
	'meta-box-order',
	'get-permalink',
	'sample-permalink',
	'inline-save',
	'inline-save-tax',
	'find_posts',
	'widgets-order',
	'save-widget',
	'delete-inactive-widgets',
	'set-post-thumbnail',
	'date_format',
	'time_format',
	'wp-remove-post-lock',
	'dismiss-wp-pointer',
	'upload-attachment',
	'get-attachment',
	'query-attachments',
	'save-attachment',
	'save-attachment-compat',
	'send-link-to-editor',
	'send-attachment-to-editor',
	'save-attachment-order',
	'media-create-image-subsizes',
	'heartbeat',
	'get-revision-diffs',
	'save-user-color-scheme',
	'update-widget',
	'query-themes',
	'parse-embed',
	'set-attachment-thumbnail',
	'parse-media-shortcode',
	'destroy-sessions',
	'install-plugin',
	'activate-plugin',
	'update-plugin',
	'crop-image',
	'generate-password',
	'save-wporg-username',
	'delete-plugin',
	'search-plugins',
	'search-install-plugins',
	'activate-plugin',
	'update-theme',
	'delete-theme',
	'install-theme',
	'get-post-thumbnail-html',
	'get-community-events',
	'edit-theme-plugin-file',
	'wp-privacy-export-personal-data',
	'wp-privacy-erase-personal-data',
	'health-check-site-status-result',
	'health-check-dotorg-communication',
	'health-check-is-in-debug-mode',
	'health-check-background-updates',
	'health-check-loopback-requests',
	'health-check-get-sizes',
	'toggle-auto-updates',
	'send-password-reset',
);

// Deprecated.
$core_actions_post_deprecated = array(
	'wp-fullscreen-save-post',
	'press-this-save-post',
	'press-this-add-category',
	'health-check-dotorg-communication',
	'health-check-is-in-debug-mode',
	'health-check-background-updates',
	'health-check-loopback-requests',
);

$core_actions_post = array_merge( $core_actions_post, $core_actions_post_deprecated );

// Register core Ajax calls.
if ( ! empty( $_GET['action'] ) && in_array( $_GET['action'], $core_actions_get, true ) ) {
	add_action( 'wp_ajax_' . $_GET['action'], 'wp_ajax_' . str_replace( '-', '_', $_GET['action'] ), 1 );
}

if ( ! empty( $_POST['action'] ) && in_array( $_POST['action'], $core_actions_post, true ) ) {
	add_action( 'wp_ajax_' . $_POST['action'], 'wp_ajax_' . str_replace( '-', '_', $_POST['action'] ), 1 );
}

add_action( 'wp_ajax_nopriv_generate-password', 'wp_ajax_nopriv_generate_password' );

add_action( 'wp_ajax_nopriv_heartbeat', 'wp_ajax_nopriv_heartbeat', 1 );

// Register Plugin Dependencies Ajax calls.
add_action( 'wp_ajax_check_plugin_dependencies', array( 'WP_Plugin_Dependencies', 'check_plugin_dependencies_during_ajax' ) );

$action = $_REQUEST['action'];

error_log('� [AJAX-MAIN] A PUNTO DE EJECUTAR CALLBACKS');
error_log('�🔹 [AJAX] Action: ' . $action);
error_log('🔹 [AJAX] is_user_logged_in(): ' . (is_user_logged_in() ? 'true' : 'false'));
error_log('🔹 [AJAX] has_action(wp_ajax_' . $action . '): ' . (has_action("wp_ajax_{$action}") ? 'true' : 'false'));

if ( is_user_logged_in() ) {
	// If no action is registered, return a Bad Request response.
	if ( ! has_action( "wp_ajax_{$action}" ) ) {
		error_log('❌ [AJAX] No hook encontrado para wp_ajax_' . $action);
		wp_die( '0', 400 );
	}

	error_log('✅ [AJAX] A PUNTO DE DISPARAR wp_ajax_' . $action);
	do_action( "wp_ajax_{$action}" );
	error_log('❌ [AJAX] REGRESÓ DE do_action sin ejecutar callback');
} else {
	// If no action is registered, return a Bad Request response.
	if ( ! has_action( "wp_ajax_nopriv_{$action}" ) ) {
		wp_die( '0', 400 );
	}

	do_action( "wp_ajax_nopriv_{$action}" );
}

// Default status.
wp_die( '0' );
