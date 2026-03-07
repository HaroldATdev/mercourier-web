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
			/* Estilos para accordion */
			#shipment-history-accordion {
				display: block;
				width: 100%;
			}

			.merc-tienda-card {
				border: 1px solid #ddd;
				border-radius: 6px;
				overflow: hidden;
				box-shadow: 0 2px 4px rgba(0,0,0,0.05);
				margin-bottom: 12px;
			}

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

			.merc-tienda-info {
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
				margin-left: auto;
				font-size: 16px;
				transition: transform 0.3s;
			}

			.merc-tienda-card.collapsed .merc-tienda-icon {
				transform: rotate(-90deg);
			}

			.merc-tienda-card-content {
				max-height: 2000px;
				overflow: hidden;
				transition: max-height 0.3s ease;
				background: white;
			}

			.merc-tienda-card.collapsed .merc-tienda-card-content {
				max-height: 0;
				overflow: hidden;
			}

			.merc-tienda-card-table {
				width: 100%;
				border-collapse: collapse;
				margin: 0;
				background: white;
			}

			.merc-tienda-card-table thead {
				background: #f5f5f5;
				border-bottom: 1px solid #ddd;
			}

			.merc-tienda-card-table thead th {
				padding: 10px 12px;
				text-align: left;
				font-weight: 600;
				font-size: 12px;
				color: #333;
				border: none;
			}

			.merc-tienda-card-table tbody tr {
				border-top: 1px solid #eee;
			}

			.merc-tienda-card-table tbody tr:hover {
				background: #f9f9f9;
			}

			.merc-tienda-card-table tbody td {
				padding: 8px 12px;
				vertical-align: middle;
				font-size: 13px;
			}
		</style>
		<script>
		(function($) {
			console.log('🚀 merc-table script loaded');

			let initialized = false;

			function initializeAccordion() {
				if (initialized) return true;

				console.log('🔍 Buscando tabla...');
				
				// Buscar cualquier tabla con tbody
				let $table = null;
				const $allTables = $('table');
				
				console.log('📍 Encontradas ' + $allTables.length + ' tablas en total');

				$allTables.each(function() {
					const $t = $(this);
					const rows = $t.find('tbody tr').length;
					if (rows > 0 && $t.find('tbody tr[data-tienda]').length > 0) {
						console.log('✅ Tabla con data-tienda encontrada:', $t.attr('id'), '(' + rows + ' filas)');
						$table = $t;
						return false; // break
					}
				});

				if (!$table || !$table.length) {
					console.log('❌ No encontré tabla con filas data-tienda');
					return false;
				}

				const $tbody = $table.find('tbody');
				console.log('📝 Total filas:', $tbody.find('tr').length);

				// Agrupar filas por tienda
				const tiendas = {};
				const orden = [];

				$tbody.find('tr').each(function() {
					const $row = $(this);
					const tienda = $row.data('tienda') || 'Sin tienda';
					
					if (!tiendas[tienda]) {
						tiendas[tienda] = [];
						orden.push(tienda);
					}
					tiendas[tienda].push($row.clone());
				});

				console.log('📊 Tiendas:', orden);

				// Crear accordion
				const $accordion = $('<div id="shipment-history-accordion"></div>');

			// Crear cards
			orden.forEach(function(tienda) {
				const tiendaSlug = tienda.replace(/[^a-z0-9]/gi, '').toLowerCase().substr(0, 10);
				const rowsForTienda = tiendas[tienda];

				const $header = $('<div class="merc-tienda-card-header"></div>').html(
					'<div class="merc-tienda-info">' +
					'<input type="checkbox" class="merc-tienda-checkbox">' +
					'<strong>' + tienda + '</strong>' +
					'<span style="font-size:11px; opacity:0.8;">(' + rowsForTienda.length + ' envíos)</span>' +
					'</div>' +
					'<span class="merc-tienda-icon">▼</span>'
				);

				// Tabla interna SIN header para evitar desalineacion
				const $innerTable = $('<table class="merc-tienda-card-table wpc-shipment-history table table-hover table-sm"><tbody></tbody></table>');
				const $innerTbody = $innerTable.find('tbody');
				
				rowsForTienda.forEach(function($row) {
					$innerTbody.append($row);
				});

				const $content = $('<div class="merc-tienda-card-content"></div>').append($innerTable);

					const $card = $('<div class="merc-tienda-card collapsed"></div>')
						.append($header)
						.append($content);

					$header.on('click', function(e) {
						if (!$(e.target).is('input[type="checkbox"]') && !$(e.target).closest('input[type="checkbox"]').length) {
							$card.toggleClass('collapsed');
						}
					});

					$header.find('input[type="checkbox"]').on('change', function() {
						const isChecked = $(this).prop('checked');
						$innerTbody.find('input[type="checkbox"]').prop('checked', isChecked);
					});

					$accordion.append($card);
				});

				// Reemplazar tabla
				const $wrapper = $table.closest('#shipment-history-list') || $table.closest('.table-responsive') || $table.parent();
				if ($wrapper.length) {
					$wrapper.html($accordion);
				} else {
					$table.replaceWith($accordion);
				}

				initialized = true;
				console.log('✅ Accordion generado!');
				return true;
			}

			// Usar MutationObserver para detectar cuando se añade la tabla
			const observerConfig = { childList: true, subtree: true };
			const observer = new MutationObserver(function(mutations) {
				if (!initialized && $('tbody tr[data-tienda]').length > 0) {
					console.log('👁️ MutationObserver - detectó tabla con data-tienda');
					observer.disconnect();
					setTimeout(initializeAccordion, 100);
				}
			});

			// Iniciar observación
			observer.observe(document.body, observerConfig);

			// Intentar inicializar también en document.ready
			$(document).ready(function() {
				console.log('📌 Document ready');
				setTimeout(initializeAccordion, 500);
			});

			// Timeout para limpiar observer si no se usa
			setTimeout(function() {
				if (!initialized && observer) {
					console.log('⏱️  Limpiando observer - timeout');
					observer.disconnect();
				}
			}, 10000);

		})(jQuery);
		</script>
		<?php
	}
}

new MERC_Shipment_Table();
