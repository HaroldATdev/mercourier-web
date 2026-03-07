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

	/* ── Enqueue JS para agrupar por tienda ───────────────────────── */

	public function enqueue_table_scripts(): void {
		?>
		<style>
			.merc-tienda-group-header {
				background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
				color: white;
				font-weight: bold;
				font-size: 13px;
				text-align: left;
				padding: 0 !important;
			}
			.merc-tienda-group-header td {
				padding: 10px 12px !important;
				border: none !important;
			}
			.merc-tienda-group-header .shipment-row {
				display: none;
			}
		</style>
		<script>
		(function($) {
			function groupByTienda() {
				const $table = $('table.wpc-shipment-history, table#shipment-history');
				if (!$table.length) return;

				const $tbody = $table.find('tbody');
				const rows = $tbody.find('tr.shipment-row');
				
				if (rows.length === 0) return;

				const groups = {};
				const order = [];

				// Agrupar filas
				rows.each(function() {
					const $row = $(this);
					const tienda = $row.data('tienda') || 'Sin tienda';
					
					if (!groups[tienda]) {
						groups[tienda] = [];
						order.push(tienda);
					}
					groups[tienda].push($row);
				});

				// Reconstruir tbody con headers
				$tbody.empty();
				
				order.forEach(function(tienda) {
					// Header de tienda
					const $header = $('<tr class="merc-tienda-group-header"></tr>').html(
						'<td colspan="10">📦 ' + tienda + ' (' + groups[tienda].length + ' envíos)</td>'
					);
					$tbody.append($header);
					
					// Filas de tienda
					groups[tienda].forEach(function($row) {
						$tbody.append($row);
					});
				});

				console.log('✅ Tabla agrupada por tienda');
			}

			// Ejecutar cuando la tabla esté lista
			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', groupByTienda);
			} else {
				groupByTienda();
			}

			// Reintentar cada 500ms por si la tabla carga con AJAX
			let attempts = 0;
			const interval = setInterval(function() {
				if ($('tbody tr[data-tienda]').length > 0) {
					clearInterval(interval);
					groupByTienda();
				}
				attempts++;
				if (attempts > 20) clearInterval(interval);
			}, 500);
		})(jQuery);
		</script>
		<?php
	}
}

new MERC_Shipment_Table();
