<?php
/**
 * Debug script: Verificar productos y sus metas
 * Acceder a: https://mercourier.local/debug-productos.php
 */

// Cargar WordPress
require_once( dirname( __FILE__ ) . '/wp-load.php' );

// Solo acceso local
if ( ! isset( $_GET['debug_key'] ) || $_GET['debug_key'] !== 'debug123' ) {
	die( 'Acceso denegado' );
}

echo '<h1>🔍 Debug: Productos y Metas</h1>';

// 1. Contar total de productos
$total_productos = wp_count_posts( 'merc_producto' );
echo '<h2>📊 Total de productos</h2>';
echo '<p>Publicados: ' . $total_productos->publish . '</p>';
echo '<p>Borradores: ' . $total_productos->draft . '</p>';

// 2. Listar productos
$productos = get_posts( [
	'post_type'      => 'merc_producto',
	'posts_per_page' => -1,
	'post_status'    => 'publish',
] );

echo '<h2>📦 Listado de productos</h2>';
echo '<table border="1" cellpadding="10">';
echo '<tr><th>ID</th><th>Título</th><th>Cliente Asignado</th><th>Estado</th><th>Stock</th></tr>';

foreach ( $productos as $prod ) {
	$cliente_id = get_post_meta( $prod->ID, '_merc_producto_cliente_asignado', true );
	$estado     = get_post_meta( $prod->ID, '_merc_producto_estado', true );
	$stock      = function_exists( 'merc_get_product_stock' ) ? merc_get_product_stock( $prod->ID ) : 'N/A';
	
	$cliente_nombre = $cliente_id ? get_userdata( $cliente_id )->user_login : '(sin asignar)';
	
	echo '<tr>';
	echo '<td>' . $prod->ID . '</td>';
	echo '<td>' . $prod->post_title . '</td>';
	echo '<td>' . $cliente_nombre . ' (ID: ' . $cliente_id . ')</td>';
	echo '<td>' . ( $estado ?: '(vacío)' ) . '</td>';
	echo '<td>' . $stock . '</td>';
	echo '</tr>';
}

echo '</table>';

// 3. Contar productos sin cliente asignado
global $wpdb;
$sin_cliente = $wpdb->get_var( "
	SELECT COUNT(DISTINCT p.ID) 
	FROM {$wpdb->posts} p
	LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_merc_producto_cliente_asignado'
	WHERE p.post_type = 'merc_producto' 
	AND p.post_status = 'publish'
	AND (pm.meta_value IS NULL OR pm.meta_value = '')
" );

echo '<h2>⚠️ Productos sin cliente asignado</h2>';
echo '<p>Total: ' . $sin_cliente . '</p>';

// 4. Listar clientes
echo '<h2>👥 Clientes en el sistema</h2>';
$clientes = get_users( [
	'role' => 'wpcargo_client',
	'number' => -1,
] );

echo '<table border="1" cellpadding="10">';
echo '<tr><th>ID</th><th>Usuario</th><th>Empresa</th><th>Productos</th></tr>';

foreach ( $clientes as $cliente ) {
	// Contar productos de este cliente
	$productos_cliente = get_posts( [
		'post_type'      => 'merc_producto',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'meta_query'     => [
			[
				'key'   => '_merc_producto_cliente_asignado',
				'value' => $cliente->ID,
			],
		],
	] );
	
	$empresa = get_user_meta( $cliente->ID, 'billing_company', true );
	
	echo '<tr>';
	echo '<td>' . $cliente->ID . '</td>';
	echo '<td>' . $cliente->user_login . '</td>';
	echo '<td>' . $empresa . '</td>';
	echo '<td>' . count( $productos_cliente ) . '</td>';
	echo '</tr>';
}

echo '</table>';

echo '<hr>';
echo '<p><small>Acceso: debug_key=debug123 en URL</small></p>';
?>
