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
 * @var string $motorizo_recojo_html
 * @var string $motorizo_entrega_html
 */
?>
<tr class="shipment-row" data-tienda="<?php echo esc_attr( $tienda ?: 'N/A' ); ?>">
<td><?php echo $actions_html; ?></td>

<td><?php echo ! empty( $distrito_recojo )
	? esc_html( $distrito_recojo )
	: '<span style="color:#999;">N/A</span>'; ?></td>

<td><?php echo ! empty( $distrito_destino )
	? esc_html( $distrito_destino )
	: '<span style="color:#999;">N/A</span>'; ?></td>

<td><?php echo esc_html( $fecha ); ?></td>

<td style="text-align:center;"><?php echo $tipo_html; ?></td>

<td style="text-align:center;"><?php echo $cambio_html; ?></td>

<td><?php echo $motorizo_recojo_html; ?></td>

<td><?php echo $motorizo_entrega_html; ?></td>

<td><?php echo isset( $shipment_id ) ? esc_html( $shipment_id ) : ''; ?></td>
</tr>
