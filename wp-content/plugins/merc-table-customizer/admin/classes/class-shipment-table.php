<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * MERC_Shipment_Table
 * Reorganiza columnas de la tabla de shipments del frontend WPCargo.
 * El HTML vive en admin/templates/frontend/table-*.tpl.php.
 */
class MERC_Shipment_Table {

	private string $tpl_path;

	public function __construct() {
		$this->tpl_path = MERC_TABLE_PATH . 'admin/templates/frontend/';

		add_action( 'plugins_loaded',              [ $this, 'remove_default_columns' ], 20 );
		add_action( 'wpcfe_shipment_table_header', [ $this, 'custom_header' ],          99 );
		add_action( 'wpcfe_shipment_table_data',   [ $this, 'custom_data' ],            99 );
		add_action( 'wp_footer',                   [ $this, 'enqueue_table_scripts' ],  99 );
	}

	/* ── Quitar columnas default ─────────────────────────────────────── */

	public function remove_default_columns(): void {
		remove_action( 'wpcfe_shipment_after_tracking_number_header', 'wpcfe_shipper_receiver_shipment_header_callback', 25 );
		remove_action( 'wpcfe_shipment_after_tracking_number_data',   'wpcfe_shipper_receiver_shipment_data_callback',   25 );
		remove_action( 'wpcfe_shipment_table_header', 'wpcfe_shipment_table_header_type',   25 );
		remove_action( 'wpcfe_shipment_table_data',   'wpcfe_shipment_table_data_type',     25 );
		remove_action( 'wpcfe_shipment_table_header', 'wpcfe_shipment_table_header_status', 25 );
		remove_action( 'wpcfe_shipment_table_data',   'wpcfe_shipment_table_data_status',   25 );
		remove_action( 'wpcfe_shipment_table_header_action', 'wpcfe_shipment_table_header_action_print', 25 );
		remove_action( 'wpcfe_shipment_table_data_action',   'wpcfe_shipment_table_action_print',        25 );
		// Quitar columna "Container" del plugin wpcargo-shipment-container-add-ons
		remove_action( 'wpcfe_shipment_table_header', 'wpcsc_shipment_container_table_header', 10 );
		remove_action( 'wpcfe_shipment_table_data',   'wpcsc_shipment_container_table_data',   10 );
	}

	/* ── Header ──────────────────────────────────────────────────────── */

	public function custom_header(): void {
		$this->render_tpl( 'table-header.tpl.php', [] );
	}

	/* ── Data ────────────────────────────────────────────────────────── */

	public function custom_data( int $shipment_id ): void {
		$tienda      = get_post_meta( $shipment_id, 'wpcargo_tiendaname', true );

		$action_rows  = function_exists( 'wpcfe_shipment_action_rows' ) ? wpcfe_shipment_action_rows( $shipment_id ) : [];
		$actions_html = ! empty( $action_rows )
			? '<div class="wpcfe-action-row" style="margin-top:6px;">' . implode( ' | ', $action_rows ) . '</div>'
			: '';

		$distrito_recojo  = get_post_meta( $shipment_id, 'wpcargo_distrito_recojo',  true );
		$distrito_destino = get_post_meta( $shipment_id, 'wpcargo_distrito_destino', true )
		                 ?: get_post_meta( $shipment_id, 'wpcargo_destination',       true );

		$fecha = get_post_meta( $shipment_id, 'wpcargo_pickup_date_picker', true )
		      ?: get_post_meta( $shipment_id, 'wpcargo_calendarenvio', true )
		      ?: date( 'd/m/Y', strtotime( get_post_field( 'post_date', $shipment_id ) ) );

		$tipo_html             = $this->render_tipo( get_post_meta( $shipment_id, 'tipo_envio', true ) );
		$cambio_html           = $this->render_cambio( get_post_meta( $shipment_id, 'cambio_producto', true ) );
		$motorizo_recojo_html  = $this->render_driver( get_post_meta( $shipment_id, 'wpcargo_motorizo_recojo',  true ) );
		$motorizo_entrega_html = $this->render_driver( get_post_meta( $shipment_id, 'wpcargo_motorizo_entrega', true ) );

		$this->render_tpl( 'table-row.tpl.php', compact(
			'shipment_id', 'tienda', 'actions_html',
			'distrito_recojo', 'distrito_destino', 'fecha',
			'tipo_html', 'cambio_html', 'motorizo_recojo_html', 'motorizo_entrega_html'
		) );
	}

	/* ── Template renderer ───────────────────────────────────────────── */

	private function render_tpl( string $file, array $data ): void {
		// extract() en este scope + include: el template accede a todas las variables.
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		extract( $data );
		include $this->tpl_path . $file;
	}

	/* ── Helpers de render ───────────────────────────────────────────── */

	private function render_tipo( string $tipo ): string {
		$lower = strtolower( trim( $tipo ) );
		if ( $lower === 'express' || stripos( $tipo, 'agencia' ) !== false )
			return '<span style="background:#ff5722;color:white;padding:4px 12px;border-radius:4px;font-weight:bold;font-size:11px;">MERC AGENCIA</span>';
		if ( $lower === 'normal' || stripos( $tipo, 'emprendedor' ) !== false )
			return '<span style="background:#2196f3;color:white;padding:4px 12px;border-radius:4px;font-weight:bold;font-size:11px;">MERC EMPRENDEDOR</span>';
		if ( ! empty( $tipo ) )
			return '<span style="background:#ff9800;color:white;padding:4px 8px;border-radius:4px;font-size:10px;">⚠️ ' . esc_html( $tipo ) . '</span>';
		return '<span style="background:#757575;color:white;padding:4px 8px;border-radius:4px;font-size:10px;">Sin tipo</span>';
	}

	private function render_cambio( string $cambio ): string {
		return $cambio === 'Sí'
			? '<span style="background:#c62828;color:#fff;padding:4px 12px;border-radius:14px;font-weight:bold;font-size:11px;">⚠ SÍ</span>'
			: '<span style="background:#2e7d32;color:#fff;padding:4px 12px;border-radius:14px;font-weight:bold;font-size:11px;">NO</span>';
	}

	private function render_driver( $user_id ): string {
		if ( empty( $user_id ) ) return '<span style="color:#999;">-</span>';
		$nombre = trim( get_user_meta( $user_id, 'first_name', true ) . ' ' . get_user_meta( $user_id, 'last_name', true ) );
		if ( empty( $nombre ) ) {
			$u = get_userdata( $user_id );
			$nombre = $u ? $u->display_name : '-';
		}
		return esc_html( $nombre );
	}

	/* ── Enqueue CSS/JS para collapse de tiendas ───────────────────── */

	public function enqueue_table_scripts(): void {
		// Inyectar CSS y JS inline
		?>
		<style>
			/* Estilos para agrupación de tiendas */
			.merc-tienda-header {
				background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
				color: white;
				padding: 3px 16px;
				font-weight: bold;
				font-size: 14px;
				cursor: pointer;
				user-select: none;
				display: flex;
				justify-content: space-between;
				align-items: center;
				transition: all 0.3s ease;
			}
			.merc-tienda-header:hover {
				background: linear-gradient(135deg, #5568d3 0%, #653a8a 100%);
			}
			.merc-tienda-icon {
				display: inline-block;
				font-size: 16px;
				transition: transform 0.3s ease;
			}
			.merc-tienda-header.collapsed .merc-tienda-icon {
				transform: rotate(-90deg);
			}
			.shipment-row.hidden-by-tienda {
				display: none !important;
			}
			tr[data-tienda] .merc-tienda-count {
				font-size: 12px;
				opacity: 0.9;
				margin-left: 10px;
			}
		</style>
		<script>
		jQuery(function($) {
			const $table = $('table#shipment-history');
			if ( ! $table.length ) return;

			// Obtener tabla body
			const $tbody = $table.find('tbody');
			if ( ! $tbody.length ) return;

			// Agrupar filas por tienda
			const tiendas = {};
			const orden = [];

			$tbody.find('tr.shipment-row').each(function() {
				const $row = $(this);
				const tienda = $row.data('tienda') || 'Sin tienda';
				
				if ( ! tiendas[tienda] ) {
					tiendas[tienda] = [];
					orden.push(tienda);
				}
				tiendas[tienda].push($row.clone(true));
			});

			// Limpiar tbody
			$tbody.empty();

			// Renderizar ordenado por tienda
			orden.forEach(function(tienda) {
				const $header = $('<tr>')
					.addClass('merc-tienda-header-row')
					.attr('data-tienda-header', tienda)
					.html(
						'<td colspan="8" style="padding: 0; border: none;">' +
						'<div class="merc-tienda-header merc-tienda-header-' + tienda.replace(/\s+/g, '-').toLowerCase() + '">' +
						'<span>📦 ' + tienda + ' <span class="merc-tienda-count">(' + tiendas[tienda].length + ' envíos)</span></span>' +
						'<span class="merc-tienda-icon">▼</span>' +
						'</div>' +
						'</td>' +
						'</tr>'
					);

				$tbody.append($header);

				// Agregar filas de esta tienda
				tiendas[tienda].forEach(function($row) {
					$row.addClass('shipment-row-group-' + tienda.replace(/\s+/g, '-').toLowerCase());
					$tbody.append($row);
				});
			});

			// Event listener para headers de tienda (delegado)
			$(document).on('click', '.merc-tienda-header', function(e) {
				e.preventDefault();
				e.stopPropagation();

				const $header = $(this);
				const $row = $header.closest('tr');
				const tienday = $row.attr('data-tienda-header');
				const isCollapsed = $header.toggleClass('collapsed').hasClass('collapsed');

				// Toggle rows de esta tienda
				const selector = 'tr.shipment-row-group-' + tienday.replace(/\s+/g, '-').toLowerCase();
				$tbody.find(selector).toggleClass('hidden-by-tienda', isCollapsed);
			});

			// Expand all por defecto
			$tbody.find('tr.merc-tienda-header-row').each(function() {
				$(this).find('.merc-tienda-header').removeClass('collapsed');
			});
			$tbody.find('tr.shipment-row-group-' + orden[0].replace(/\s+/g, '-').toLowerCase()).removeClass('hidden-by-tienda');
		});
		</script>
		<?php
	}
}

new MERC_Shipment_Table();
