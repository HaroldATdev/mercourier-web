<?php
/**
 * Diagnostic tool to check WordPress shipment metadata.
 * Usage: Access https://yourdomain.com/check_order.php?id=41645
 */

// Load WordPress
require_once('wp-load.php');

// Security check: Only allow logged-in administrators
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Acceso denegado. Debes iniciar sesión como administrador.' );
}

$post_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ( $post_id <= 0 || get_post_type($post_id) !== 'wpcargo_shipment' ) {
    wp_die( 'ID de envío inválido o no encontrado.' );
}

$post = get_post($post_id);

echo "<h1>Diagnóstico de Envío #{$post_id} ({$post->post_title})</h1>";
echo "<table border='1' cellpadding='8' style='border-collapse: collapse; font-family: monospace;'>";
echo "<tr><th>Meta Key</th><th>Value</th><th>Información Extra</th></tr>";

$keys = array(
    'tipo_envio',
    'wpcargo_type_of_shipment',
    'wpcargo_status',
    'wpcargo_status_anterior',
    'shipment_container_recojo',
    'shipment_container_entrega',
    'wpcargo_motorizo_recojo',
    'wpcargo_motorizo_entrega',
    'wpcargo_driver',
    'wpcargo_distrito_recojo',
    'wpcargo_distrito_destino'
);

foreach ($keys as $key) {
    $val = get_post_meta($post_id, $key, true);
    $extra = '';
    
    // Añadir información adicional de contenedores o usuarios
    if ( ($key === 'shipment_container_recojo' || $key === 'shipment_container_entrega') && !empty($val) ) {
        $container_title = get_the_title($val);
        $agent = get_post_meta($val, 'delivery_agent', true);
        $agent_name = '';
        if ($agent) {
            $user = get_user_by('id', $agent);
            $agent_name = $user ? $user->display_name : "ID#$agent";
        }
        $extra = "Contenedor: '$container_title' | Conductor del Contenedor: '$agent_name'";
    } elseif ( in_array($key, array('wpcargo_motorizo_recojo', 'wpcargo_motorizo_entrega', 'wpcargo_driver')) && !empty($val) ) {
        $user = get_user_by('id', $val);
        $extra = $user ? "Nombre: " . $user->display_name : "Usuario no encontrado";
    }
    
    echo "<tr><td><strong>{$key}</strong></td><td>" . var_export($val, true) . "</td><td>{$extra}</td></tr>";
}
echo "</table>";

echo "<h2>Estado de la reparación automática</h2>";
$repair_done = get_transient('merc_repair_drivers_done');
echo "<p>Transient 'merc_repair_drivers_done': " . var_export($repair_done, true) . " (Si es 'true', significa que la reparación ya se ejecutó o está en espera. Si quieres forzarla de nuevo, añade ?force_repair=1 a la URL)</p>";

if ( isset($_GET['force_repair']) && $_GET['force_repair'] == 1 ) {
    delete_transient('merc_repair_drivers_done');
    if ( function_exists('merc_repair_existing_shipments_drivers') ) {
        merc_repair_existing_shipments_drivers( $post_id, true );
        echo "<p><strong>¡Reparación forzada y ejecutada en esta página para el envío #{$post_id}!</strong></p>";
    } else {
        echo "<p><strong>Transient borrado. La reparación se ejecutará en la próxima carga de página del administrador (el gancho no está definido).</strong></p>";
    }
}
?>

