<?php if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Variables disponibles en este template:
 * @var int    $shipment_id
 * @var string $tienda
 * @var string $actions_html
 * @var string $distrito_recojo
 * @var string $distrito_destino
 * @var string $fecha
 * @var string $tipo_html
 * @var string $cambio_html
 * @var string $estado
 * @var string $motorizo_recojo_html
 * @var string $motorizo_entrega_html
 *
 * NOTA: Este template NO incluye el wrapper <tr>. Los TDs se insertan dentro
 * del <tr> que WPCargo ya abrió mediante el hook wpcfe_shipment_table_data.
 */
?>
<td class="merc-tienda-cell" data-tienda="<?php echo esc_attr( $tienda ?: '' ); ?>" data-cliente-id="<?php echo esc_attr( $cliente_id ?: '' ); ?>" data-cliente-nombre="<?php 
	$cliente_nombre = '';
	if ( $cliente_id ) {
		$first_name = get_user_meta( intval( $cliente_id ), 'first_name', true );
		$last_name = get_user_meta( intval( $cliente_id ), 'last_name', true );
		if ( $first_name || $last_name ) {
			$cliente_nombre = trim( $first_name . ' ' . $last_name );
		}
	}
	echo esc_attr( $cliente_nombre );
?>" data-shipment-id="<?php echo esc_attr( $shipment_id ); ?>" data-distrito="<?php echo esc_attr( $distrito_recojo ?: '' ); ?>" data-motorizo="<?php echo esc_attr( isset( $motorizo_recojo_name ) ? $motorizo_recojo_name : '' ); ?>">
	<?php if ( $tienda ) : ?>
		<strong><?php echo esc_html( $tienda ); ?></strong><br>
	<?php endif; ?>
	<?php echo $actions_html; ?>
</td>

<?php if ( current_user_can('manage_options') ) : ?>
<td style="vertical-align: middle;">
	<?php echo isset($whatsapp_buttons_html) ? $whatsapp_buttons_html : ''; ?>
</td>
<?php endif; ?>

<td>
	<?php 
		$receiver_display = ! empty( $receiver_name ) ? esc_html( $receiver_name ) : '<span style="color:#999;">Sin Nombre</span>';
		$distrito_display = ! empty( $distrito_destino ) ? esc_html( $distrito_destino ) : '<span style="color:#999;">N/A</span>';
		echo "<strong>{$receiver_display}</strong><br>{$distrito_display}";
	?>
</td>

<td><?php echo esc_html( $fecha ); ?></td>

<td style=""><?php echo $tipo_html; ?></td>

<td style="text-align:center;"><?php echo $cambio_html; ?></td>

<?php $caja_cerrada = ( '1' === get_post_meta( $shipment_id, 'merc_caja_cerrada', true ) ) ? '1' : '0'; ?>

<td class="shipment-status <?php echo esc_attr( sanitize_title( $estado ) ); ?>"
    data-merc-caja-cerrada="<?php echo esc_attr( $caja_cerrada ); ?>"
    data-es-reprogramado="<?php echo esc_attr( $es_reprogramado ?? 0 ); ?>">
    <?php echo esc_html( $estado ); ?>
</td>


<td><?php echo $motorizo_entrega_html; ?></td>

<?php
if ( current_user_can('manage_options') ) :
	$estado_upper  = strtoupper( trim( $estado ) );
	// Verificar si el envío pasó por LISTO PARA SALIR
	$paso_lps = get_post_meta( $shipment_id, 'merc_paso_listo_para_salir', true );
	if ( ! $paso_lps ) {
		// Fallback histórico
		$historial = get_post_meta( $shipment_id, 'wpcargo_shipments_update', true );
		if ( is_array( $historial ) ) {
			foreach ( $historial as $h ) {
				if ( strtoupper( trim( $h['status'] ?? '' ) ) === 'LISTO PARA SALIR' ) {
					$paso_lps = '1';
					break;
				}
			}
		}
	}
	$es_avanzado = ! empty( $paso_lps );
?>
<td style="text-align:center; font-size:18px;">
	<?php if ( $es_avanzado ) : ?>
		<span title="Pasó Listo para Salir" style="color:#28a745;">✅</span>
	<?php else : ?>
		<span title="Estado Inicial" style="color:#dc3545;">❌</span>
	<?php endif; ?>
</td>
<?php endif; ?>

