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

	/* ── Enqueue CSS/JS para accordion de tiendas ───────────────────── */

	public function enqueue_table_scripts(): void {
		// Inyectar CSS y JS inline
		?>
		<style>
			/* Wrapper principal */
			#shipment-history-accordion {
				display: block;
				width: 100%;
			}

			/* Card de tienda */
			.merc-tienda-card {
				border: 1px solid #ddd;
				border-radius: 6px;
				overflow: hidden;
				box-shadow: 0 2px 4px rgba(0,0,0,0.05);
				margin-bottom: 8px;
			}

			.merc-tienda-card:hover {
				box-shadow: 0 4px 8px rgba(0,0,0,0.1);
			}

			/* Header de card */
			.merc-tienda-card-header {
				background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
				color: white;
				padding: 12px 16px;
				cursor: pointer;
				user-select: none;
				display: flex;
				justify-content: space-between;
				align-items: center;
				font-weight: bold;
				font-size: 14px;
				transition: all 0.3s ease;
			}

			.merc-tienda-card-header:hover {
				background: linear-gradient(135deg, #5568d3 0%, #653a8a 100%);
			}

			.merc-tienda-card-header-left {
				display: flex;
				align-items: center;
				gap: 10px;
			}

			.merc-tienda-checkbox {
				width: 18px;
				height: 18px;
				cursor: pointer;
			}

			.merc-tienda-icon {
				font-size: 18px;
				transition: transform 0.3s ease;
				margin-left: auto;
			}

			.merc-tienda-card.collapsed .merc-tienda-icon {
				transform: rotate(-90deg);
			}

			.merc-tienda-count {
				font-size: 12px;
				opacity: 0.85;
			}

			/* Contenedor de tabla */
			.merc-tienda-card-content {
				display: none;
				overflow-x: auto;
			}

			.merc-tienda-card.expanded .merc-tienda-card-content {
				display: block;
			}

			/* Tabla dentro de card */
			.merc-tienda-card-table {
				width: 100%;
				border-collapse: collapse;
				margin: 0;
				background: white;
			}

			.merc-tienda-card-table thead {
				background: #f5f5f5;
				border-bottom: 2px solid #ddd;
			}

			.merc-tienda-card-table thead th {
				padding: 10px 12px;
				text-align: left;
				font-weight: 600;
				font-size: 13px;
				color: #333;
			}

			.merc-tienda-card-table tbody tr {
				border-top: 1px solid #eee;
			}

			.merc-tienda-card-table tbody tr:hover {
				background-color: #f9f9f9;
			}

			.merc-tienda-card-table tbody td {
				padding: 8px 12px;
				vertical-align: middle;
				font-size: 13px;
			}
		</style>
		<script>
		jQuery(function($) {
			// Esperar un poco a que el DOM esté completamente listo
			setTimeout(function() {
				const $tableWrapper = $('#shipment-history-list');
				if ( ! $tableWrapper.length || ! $tableWrapper.find('table#shipment-history').length ) {
					console.warn('Tabla #shipment-history no encontrada');
					return;
				}

				const $table = $tableWrapper.find('table#shipment-history');
				const $tbody = $table.find('tbody');

				if ( ! $tbody.length || $tbody.find('tr.shipment-row').length === 0 ) {
					console.warn('No hay filas shipment-row');
					return;
				}

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

				console.log('Tiendas encontradas:', orden);

				// Crear wrapper accordion
				const $accordion = $('<div>')
					.attr('id', 'shipment-history-accordion')
					.css({
						'display': 'block',
						'width': '100%'
					});

				// Obtener header para saber qué columnas mostrar
				const $headerRow = $table.find('thead tr').first();
				let headerHtml = '';
				if ( $headerRow.length ) {
					$headerRow.find('th').each(function() {
						headerHtml += '<th>' + $(this).html() + '</th>';
					});
				}

				// Renderizar cards por tienda (collapsed por defecto)
				orden.forEach(function(tienda) {
					const tiendaSlug = tienda.replace(/\s+/g, '-').toLowerCase().replace(/[^a-z0-9-]/g, '');
					const rowCount = tiendas[tienda].length;

					// Header de card
					const $cardHeader = $('<div>')
						.addClass('merc-tienda-card-header')
						.html(
							'<div class="merc-tienda-card-header-left">' +
							'<input type="checkbox" class="merc-tienda-checkbox merc-select-all-' + tiendaSlug + '">' +
							'<span><strong>' + tienda + '</strong> <span class="merc-tienda-count">(' + rowCount + ' envíos)</span></span>' +
							'</div>' +
							'<span class="merc-tienda-icon">▼</span>'
						);

					// Tabla interna
					const $cardTable = $('<table>')
						.addClass('merc-tienda-card-table wpc-shipment-history')
						.html(
							'<thead><tr>' + headerHtml + '</tr></thead>' +
							'<tbody></tbody>'
						);

					const $cardTbody = $cardTable.find('tbody');
					tiendas[tienda].forEach(function($row) {
						$cardTbody.append($row);
					});

					// Contenedor de contenido
					const $cardContent = $('<div>')
						.addClass('merc-tienda-card-content')
						.append($cardTable);

					// Card completa (collapsed por defecto)
					const $card = $('<div>')
						.addClass('merc-tienda-card collapsed')
						.attr('data-tienda', tiendaSlug)
						.append($cardHeader)
						.append($cardContent);

					$accordion.append($card);

					// Event: click en header para toggle
					$cardHeader.on('click', function(e) {
						if (!$(e.target).closest('.merc-tienda-checkbox').length) {
							$card.toggleClass('collapsed').toggleClass('expanded');
						}
					});

					// Event: checkbox de header selecciona todos
					$cardHeader.find('.merc-tienda-checkbox').on('change', function() {
						const isChecked = $(this).prop('checked');
						$cardTbody.find('input[type="checkbox"]').prop('checked', isChecked);
					});
				});

				// Reemplazar tabla original con accordion
				$tableWrapper.html($accordion);
				console.log('✅ Accordion de tiendas generado correctamente');

			}, 100);
		});
		</script>
		<?php
	}
}

new MERC_Shipment_Table();
