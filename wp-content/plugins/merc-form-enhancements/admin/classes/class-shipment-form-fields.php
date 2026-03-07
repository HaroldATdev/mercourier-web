<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * MERC_Shipment_Form_Fields
 *
 * Renderiza campos adicionales en el formulario de creación/edición de envíos:
 *   - Desglose del costo de envío (shipping cost breakdown).
 *   - Selector de producto para envíos MERC Full Fitment.
 *
 * Antes estaba en functions.php del tema – movido al plugin para mejor separación.
 */
class MERC_Shipment_Form_Fields {

	public function __construct() {
		// Renderizar campos en el formulario
		add_action( 'after_wpcfe_shipment_form_fields', [ $this, 'render_shipping_cost' ],    1, 1 );
		add_action( 'after_wpcfe_shipment_form_fields', [ $this, 'render_producto_selector' ], 5, 1 );
		add_action( 'wpcfe_after_shipment_form_fields', [ $this, 'render_producto_selector' ], 5, 1 );
		add_action( 'wpcfe_shipment_form_fields',       [ $this, 'render_producto_selector' ], 999, 1 );

		// Scripts y estilos del formulario de paquetes
		add_action( 'wp_footer', [ $this, 'package_defaults_script' ], 15 );
		add_action( 'wp_head',   [ $this, 'package_form_styles' ] );

		// Guardar datos financieros al salvar el envío
		add_action( 'wpcargo_after_save_shipment', [ $this, 'save_financial_data' ], 20, 1 );
		add_action( 'save_post_wpcargo_shipment',  [ $this, 'save_financial_data' ], 20, 1 );
		add_action( 'save_post_wpcargo_shipment',  [ $this, 'verify_final_shipping_cost' ], 999999, 1 );
		add_action( 'edit_post',                   [ $this, 'log_edit_shipping_cost' ], 10, 2 );
	}

	/* ── Desglose del costo de envío ─────────────────────────────────────── */

	public function render_shipping_cost( $shipment_id = 0 ) {
		// Obtener el ID del envío en modo edición
		if ( empty( $shipment_id ) ) {
			if ( isset( $_GET['id'] ) && ! empty( $_GET['id'] ) ) {
				$shipment_id = intval( $_GET['id'] );
			} elseif ( isset( $_POST['shipment_id'] ) && ! empty( $_POST['shipment_id'] ) ) {
				$shipment_id = intval( $_POST['shipment_id'] );
			} else {
				global $post;
				if ( isset( $post->ID ) ) {
					$shipment_id = $post->ID;
				}
			}
		}

		// Inicializar variables
		$tipo_envio_actual        = '';
		$costo_envio_guardado     = 0;
		$costo_producto_guardado  = 0;
		$total_cobrar_guardado    = 0;
		$cargo_remitente_guardado = 0;

		// Si tenemos un ID válido, cargar datos desde la base de datos
		if ( $shipment_id > 0 ) {
			$tipo_envio_actual        = get_post_meta( $shipment_id, 'tipo_envio', true );
			$costo_envio_guardado     = get_post_meta( $shipment_id, 'wpcargo_costo_envio', true ) ?: 0;
			$costo_producto_guardado  = get_post_meta( $shipment_id, 'wpcargo_costo_producto', true ) ?: 0;
			$total_cobrar_guardado    = get_post_meta( $shipment_id, 'wpcargo_total_cobrar', true ) ?: 0;
			$cargo_remitente_guardado = get_post_meta( $shipment_id, 'wpcargo_cargo_remitente', true ) ?: 0;
		}

		// Si no hay tipo guardado, intentar desde URL (modo creación)
		if ( empty( $tipo_envio_actual ) && isset( $_GET['type'] ) ) {
			$tipo_envio_actual = sanitize_text_field( $_GET['type'] );
		}
		?>
		<!-- Campos ocultos para guardar datos financieros -->
		<input type="hidden" id="tipo-envio-actual" name="tipo_envio" value="<?php echo esc_attr( $tipo_envio_actual ); ?>">
		<input type="hidden" id="hidden-product-cost" name="wpcargo_costo_producto" value="<?php echo esc_attr( $costo_producto_guardado ); ?>">
		<input type="hidden" id="hidden-shipping-cost" name="wpcargo_costo_envio" value="<?php echo esc_attr( $costo_envio_guardado ); ?>">
		<input type="hidden" id="hidden-customer-payment" name="wpcargo_total_cobrar" value="<?php echo esc_attr( $total_cobrar_guardado ); ?>">
		<input type="hidden" id="hidden-sender-charge" name="wpcargo_cargo_remitente" value="<?php echo esc_attr( $cargo_remitente_guardado ); ?>">

		<!-- Sección de costo de envío -->
		<div class="col-md-12 mb-5" id="shipping-cost-section"
			data-tipo-envio="<?php echo esc_attr( $tipo_envio_actual ); ?>"
			data-costo-envio="<?php echo esc_attr( $costo_envio_guardado ); ?>">
			<div class="card">
				<div class="card-body">
					<h5><b>💰 Desglose del envío:</b></h5>

					<!-- Desglose detallado -->
					<div id="shipping-breakdown" style="font-size: 16px; margin-top: 15px;">
						<div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e0e0e0;">
							<span>Costo del producto:</span>
							<span style="font-weight: bold;">S/. <span id="product-cost">0.00</span></span>
						</div>
						<div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e0e0e0;">
							<span>Costo del envío:</span>
							<span style="font-weight: bold;">S/. <span id="shipping-cost">0.00</span></span>
						</div>
						<div style="display: flex; justify-content: space-between; padding: 12px 0; margin-top: 5px; background-color: #f8f9fa; padding: 10px; border-radius: 5px;">
							<span style="font-weight: bold; font-size: 18px;">Total a cobrar:</span>
							<span style="font-weight: bold; font-size: 18px; color: #1976D2;">S/. <span id="total-cost">0.00</span></span>
						</div>
					</div>

					<!-- Mensaje de validación -->
					<div id="validation-message" style="margin-top: 15px; padding: 10px; border-radius: 5px; display: none;"></div>

					<!-- Debug info (oculto en producción) -->
					<div id="debug-info" style="font-size: 12px; margin-top: 10px; color: #666; display: none;"></div>
				</div>
			</div>
		</div>

		<script type="text/javascript">
		jQuery(document).ready(function($) {
			// Tabla de precios por distrito para MERC AGENCIA (express)
			const districtPricesExpress = {
				"-- Seleccione uno --": 0.00,
				"El Agustino": 8.00,
				"San Juan de Lurigancho": 8.00,
				"Santa Anita": 8.00,
				"Ate - Salamanca - Vitarte": 10.00,
				"La Molina": 8.00,
				"Santa Clara": 10.00,
				"Huaycan - Gloria Grande - Pariachi": 12.00,
				"Molina Alta (Musa - Portada del Sol - Planicie)": 10.00,
				"Huachipa (Zoológico de Huachipa)": 10.00,
				"Callao": 8.00,
				"Bellavista": 8.00,
				"La Punta - Callao": 10.00,
				"La Perla": 8.00,
				"Pueblo Libre": 8.00,
				"Lima Cercado": 8.00,
				"Breña": 8.00,
				"San Miguel": 8.00,
				"Magdalena": 8.00,
				"Sarita Colonia (Comisaría Sarita Colonia)": 8.00,
				"Carmen de la Legua": 8.00,
				"Rímac": 8.00,
				"Independencia": 8.00,
				"Comas": 8.00,
				"Carabayllo": 10.00,
				"Puente Piedra": 10.00,
				"Ventanilla": 10.00,
				"Los Olivos": 8.00,
				"San Martin de Porres": 8.00,
				"Santiago de Surco": 8.00,
				"San Juan de Miraflores": 8.00,
				"Villa María del Triunfo": 10.00,
				"Villa El Salvador": 10.00,
				"Chorrillos": 8.00,
				"Barranco": 8.00,
				"Jesús María": 8.00,
				"Lince": 8.00,
				"La Victoria": 8.00,
				"Miraflores": 8.00,
				"San Isidro": 8.00,
				"Surquillo": 8.00,
				"San Borja": 8.00,
				"San Luis": 8.00,
				"Centro de Lima": 8.00
			};

			// Tabla de precios por distrito para MERC EMPRENDEDOR (normal)
			const districtPricesNormal = {
				"-- Seleccione uno --": 0.00,
				"El Agustino": 10.00,
				"San Juan de Lurigancho": 10.00,
				"Santa Anita": 10.00,
				"Ate - Salamanca - Vitarte": 10.00,
				"La Molina": 10.00,
				"Santa Clara": 12.00,
				"Huaycan - Gloria Grande - Pariachi": 14.00,
				"Molina Alta (Musa - Portada del Sol - Planicie)": 12.00,
				"Huachipa (Zoológico de Huachipa)": 12.00,
				"Callao": 10.00,
				"Bellavista": 10.00,
				"La Punta - Callao": 12.00,
				"La Perla": 10.00,
				"Pueblo Libre": 10.00,
				"Lima Cercado": 10.00,
				"Breña": 10.00,
				"San Miguel": 10.00,
				"Magdalena": 10.00,
				"Sarita Colonia (Comisaría Sarita Colonia)": 10.00,
				"Carmen de la Legua": 10.00,
				"Rímac": 10.00,
				"Independencia": 10.00,
				"Comas": 10.00,
				"Carabayllo": 13.00,
				"Puente Piedra": 13.00,
				"Ventanilla": 13.00,
				"Los Olivos": 10.00,
				"San Martin de Porres": 10.00,
				"Santiago de Surco": 10.00,
				"San Juan de Miraflores": 10.00,
				"Villa María del Triunfo": 12.00,
				"Villa El Salvador": 12.00,
				"Chorrillos": 10.00,
				"Barranco": 10.00,
				"Jesús María": 10.00,
				"Lince": 10.00,
				"La Victoria": 10.00,
				"San Isidro": 10.00,
				"Surquillo": 10.00,
				"San Borja": 10.00,
				"San Luis": 10.00,
				"Centro de Lima": 10.00
			};

			let cachedServiceType = null;

			function getServiceType() {
				if (cachedServiceType !== null) {
					return cachedServiceType;
				}

				const urlParams  = new URLSearchParams(window.location.search);
				const type       = urlParams.get('type');
				const shipmentId = urlParams.get('id');

				if (type === 'express') {
					cachedServiceType = 'express';
					return 'express';
				}
				if (type === 'full_fitment' || (type && type.toLowerCase().includes('full'))) {
					cachedServiceType = 'full_fitment';
					return 'full_fitment';
				}

				if (shipmentId) {
					const shippingSection = $('#shipping-cost-section');
					if (shippingSection.length > 0) {
						const tipoFromAttr = shippingSection.data('tipo-envio');
						if (tipoFromAttr && tipoFromAttr !== '') {
							const tipoLower = String(tipoFromAttr).toLowerCase().trim();
							if (tipoLower === 'express' || tipoLower.includes('agencia')) {
								cachedServiceType = 'express';
								return 'express';
							}
							if (tipoLower === 'full_fitment' || tipoLower.includes('full')) {
								cachedServiceType = 'full_fitment';
								return 'full_fitment';
							}
							cachedServiceType = 'normal';
							return 'normal';
						}
					}
				}

				const tipoEnvioField = $('#tipo-envio-actual').val() || $('input[name="tipo_envio"]').val();
				if (tipoEnvioField && tipoEnvioField.trim() !== '') {
					const tipoLower = tipoEnvioField.toLowerCase().trim();
					if (tipoLower === 'express' || tipoLower.includes('agencia')) {
						cachedServiceType = 'express';
						return 'express';
					}
					if (tipoLower === 'full_fitment' || tipoLower.includes('full')) {
						cachedServiceType = 'full_fitment';
						return 'full_fitment';
					}
				}

				cachedServiceType = 'normal';
				return 'normal';
			}

			function getDistrictPrices() {
				const serviceType = getServiceType();
				return serviceType === 'express' ? districtPricesExpress : districtPricesNormal;
			}

			function findBestMatch(destination) {
				const serviceType = getServiceType();
				if (serviceType === 'full_fitment') {
					return 10.00;
				}

				const districtPrices = getDistrictPrices();
				destination = destination.trim();

				if (districtPrices[destination] !== undefined) {
					return districtPrices[destination];
				}

				for (const district in districtPrices) {
					const mainName = district.split('(')[0].split(',')[0].trim();
					if (mainName.toLowerCase() === destination.toLowerCase()) {
						return districtPrices[district];
					}
				}

				for (const district in districtPrices) {
					if (district.toLowerCase().includes(destination.toLowerCase()) ||
						destination.toLowerCase().includes(district.toLowerCase())) {
						return districtPrices[district];
					}
				}

				return 0.00;
			}

			function showValidationMessage(message, type) {
				type = type || 'warning';
				const colors = {
					'warning': '#fff3cd',
					'error':   '#f8d7da',
					'success': '#d4edda',
					'info':    '#d1ecf1'
				};
				const textColors = {
					'warning': '#856404',
					'error':   '#721c24',
					'success': '#155724',
					'info':    '#0c5460'
				};
				$('#validation-message').css({
					'background-color': colors[type],
					'color':            textColors[type],
					'border':           '1px solid ' + textColors[type]
				}).html(message).show();
			}

			function hideValidationMessage() {
				$('#validation-message').hide();
			}

			function updateShippingBreakdown() {
				const destinationField = $('#wpcargo_distrito_destino');
				let destination = '';

				if (destinationField.length > 0) {
					if (destinationField.is('select')) {
						destination = destinationField.find('option:selected').text() || destinationField.val() || '';
					} else {
						destination = destinationField.val() || '';
					}
				}

				const montoInput = $('#wpcargo_monto');
				let totalAmount = 0;

				if (montoInput.length > 0) {
					totalAmount = parseFloat(montoInput.val()) || 0;
				} else {
					const altMontoInput = $('input[name*="monto"]:not([type="hidden"]):not([name*="costo"]):not([name*="total"]):not([name*="cargo"]), input[id*="monto"]:not([type="hidden"])');
					if (altMontoInput.length > 0) {
						totalAmount = parseFloat(altMontoInput.first().val()) || 0;
					}
				}

				const hiddenShippingCostField = $('#hidden-shipping-cost');
				const existingShippingCost    = parseFloat(hiddenShippingCostField.val()) || 0;
				const isEditMode = $('input[name="post_ID"]').length > 0 || $('input[name="shipment_id"]').length > 0;

				const shippingCost = findBestMatch(destination);

				let finalShippingCost = shippingCost;
				if (isEditMode && existingShippingCost > 0 && !window.districtChanged) {
					finalShippingCost = existingShippingCost;
				}

				let productCost = totalAmount - finalShippingCost;

				if (totalAmount === 0) {
					hideValidationMessage();
					$('#product-cost').text('0.00');
					$('#shipping-cost').text(finalShippingCost.toFixed(2));
					$('#total-cost').text(finalShippingCost.toFixed(2));
					return;
				}

				if (totalAmount < finalShippingCost) {
					showValidationMessage(
						'⚠️ Advertencia: El monto total (S/. ' + totalAmount.toFixed(2) +
						') es menor que el costo de envío (S/. ' + finalShippingCost.toFixed(2) +
						'). El costo del producto será negativo.',
						'warning'
					);
				} else if (totalAmount === finalShippingCost) {
					showValidationMessage(
						'ℹ️ El monto total coincide exactamente con el costo de envío. ' +
						'El costo del producto es S/. 0.00',
						'info'
					);
					productCost = 0;
				} else {
					hideValidationMessage();
				}

				$('#product-cost').text(productCost.toFixed(2));
				$('#shipping-cost').text(finalShippingCost.toFixed(2));
				$('#total-cost').text(totalAmount.toFixed(2));

				$('#hidden-product-cost').val(productCost.toFixed(2));
				$('#hidden-shipping-cost').val(finalShippingCost.toFixed(2));
				$('#hidden-customer-payment').val(totalAmount.toFixed(2));
			}

			$(document).on('change', '#wpcargo_distrito_destino, select[name="wpcargo_distrito_destino"]', function() {
				window.districtChanged = true;
				updateShippingBreakdown();
			});

			$(document).on('select2:select', '#wpcargo_distrito_destino', function() {
				window.districtChanged = true;
				updateShippingBreakdown();
			});

			$(document).on('input change', '#wpcargo_monto, input[name*="monto"]:not([type="hidden"]):not([name*="costo"]):not([name*="total"]):not([name*="cargo"]), input[id*="monto"]:not([type="hidden"])', function() {
				updateShippingBreakdown();
			});

			$(document).on('change', 'select[name="payment_wpcargo_mode_field"]', function() {
				setTimeout(function() { updateShippingBreakdown(); }, 300);
			});

			setTimeout(function() { updateShippingBreakdown(); }, 500);

			let checkInterval = setInterval(function() {
				const distritoField = $('#wpcargo_distrito_destino');
				const montoField    = $('#wpcargo_monto');
				if (distritoField.length > 0 && montoField.length > 0) {
					updateShippingBreakdown();
					clearInterval(checkInterval);
				}
			}, 500);

			setTimeout(function() { clearInterval(checkInterval); }, 10000);
		});
		</script>

		<style>
		#shipping-breakdown { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
		#validation-message { animation: mercSlideIn 0.3s ease-in-out; }
		@keyframes mercSlideIn {
			from { opacity: 0; transform: translateY(-10px); }
			to   { opacity: 1; transform: translateY(0); }
		}
		@media (max-width: 576px) {
			#shipping-breakdown { font-size: 14px; }
			#shipping-breakdown div:last-child { font-size: 16px !important; }
		}
		</style>
		<?php
	}

	/* ── Selector de producto para MERC Full Fitment ─────────────────────── */

	public function render_producto_selector( $shipment_id ) {
		// Solo mostrar si el tipo de envío es MERC FULL FITMENT
		if ( ! isset( $_GET['type'] ) || $_GET['type'] !== 'full_fitment' ) {
			return;
		}

		// Evitar renderizado múltiple
		static $ya_renderizado = false;
		if ( $ya_renderizado ) {
			return;
		}
		$ya_renderizado = true;

		// Obtener usuario actual
		$current_user_id = get_current_user_id();
		$es_admin        = current_user_can( 'manage_options' ) || current_user_can( 'edit_others_posts' );

		// Construir meta_query para filtrar por cliente
		$meta_query = [];
		if ( ! $es_admin ) {
			$meta_query = [
				[
					'key'     => '_merc_producto_cliente_asignado',
					'value'   => $current_user_id,
					'compare' => '=',
				],
			];
		}

		// Obtener productos disponibles filtrados por cliente
		$productos = get_posts( [
			'post_type'      => 'merc_producto',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
			'meta_query'     => $meta_query,
		] );

		// Filtrar productos con stock disponible
		$productos_disponibles = [];
		foreach ( $productos as $prod ) {
			$estado   = get_post_meta( $prod->ID, '_merc_producto_estado', true );
			$cantidad = function_exists( 'merc_get_product_stock' ) ? merc_get_product_stock( $prod->ID ) : 0;

			if ( empty( $estado ) || $estado === 'sin_asignar' || ( $estado === 'asignado' && intval( $cantidad ) > 0 ) ) {
				$productos_disponibles[] = $prod;
			}
		}

		// Obtener producto ya seleccionado (si existe)
		$producto_seleccionado  = get_post_meta( $shipment_id, '_merc_producto_id', true );
		$cantidad_seleccionada  = get_post_meta( $shipment_id, '_merc_producto_cantidad', true );

		// Si hay producto seleccionado y no está en la lista, agregarlo
		if ( $producto_seleccionado ) {
			$producto_actual = get_post( $producto_seleccionado );
			if ( $producto_actual ) {
				$ya_incluido = false;
				foreach ( $productos_disponibles as $p ) {
					if ( $p->ID == $producto_seleccionado ) {
						$ya_incluido = true;
						break;
					}
				}
				if ( ! $ya_incluido ) {
					$productos_disponibles[] = $producto_actual;
				}
			}
		}

		if ( empty( $cantidad_seleccionada ) ) {
			$cantidad_seleccionada = 1;
		}

		ob_start();
		?>
		<!-- INICIO SELECTOR PRODUCTOS MERC -->
		<?php wp_nonce_field( 'merc_envio_producto_guardar', 'merc_envio_producto_nonce' ); ?>
		<div class="col-md-12 mb-4" id="merc_producto_wrapper"
			style="display:block !important; visibility:visible !important; opacity:1 !important;">
			<div class="card">
				<section class="card-header">
					<strong>📦 Producto a Enviar</strong>
				</section>
				<section class="card-body">
					<?php if ( empty( $productos_disponibles ) ) : ?>
						<div class="alert alert-warning">
							<strong>⚠️ No hay productos disponibles</strong><br>
							Por favor, agrega productos al almacén desde el panel de administración.
						</div>
					<?php else : ?>
					<div class="row">
						<div class="col-md-8">
							<div class="form-group">
								<label for="merc_producto_id"><strong>Producto *</strong></label>
								<select id="merc_producto_id" name="merc_producto_id"
									class="form-control" required
									style="display:block !important; width:100% !important;">
									<option value="">-- Selecciona un producto --</option>
									<?php foreach ( $productos_disponibles as $prod ) :
										$stock    = function_exists( 'merc_get_product_stock' ) ? merc_get_product_stock( $prod->ID ) : 0;
										$stock    = ! empty( $stock ) ? intval( $stock ) : 0;
										$codigo   = get_post_meta( $prod->ID, '_merc_producto_codigo_barras', true );
										$selected = ( $prod->ID == $producto_seleccionado ) ? 'selected' : '';
									?>
										<option value="<?php echo esc_attr( $prod->ID ); ?>"
											data-stock="<?php echo esc_attr( $stock ); ?>"
											<?php echo $selected; ?>>
											<?php echo esc_html( $prod->post_title ); ?> - Stock: <?php echo esc_html( $stock ); ?>
											<?php if ( $codigo ) : ?> [<?php echo esc_html( $codigo ); ?>]<?php endif; ?>
										</option>
									<?php endforeach; ?>
								</select>
								<small class="text-muted">Solo se muestran productos disponibles (<?php echo count( $productos_disponibles ); ?> total)</small>
							</div>
						</div>
						<div class="col-md-4">
							<div class="form-group">
								<label for="merc_producto_cantidad"><strong>Cantidad *</strong></label>
								<input type="number"
									id="merc_producto_cantidad"
									name="merc_producto_cantidad"
									class="form-control"
									value="<?php echo esc_attr( $cantidad_seleccionada ); ?>"
									min="1"
									max="999"
									required
									style="display:block !important; width:100% !important;">
								<small id="merc_stock_display" class="text-muted"></small>
							</div>
						</div>
					</div>
					<div id="merc_stock_warning" class="alert alert-warning" style="display:none;">
						<strong>⚠️</strong> <span id="merc_warning_text"></span>
					</div>
					<?php endif; ?>
				</section>
			</div>
		</div>
		<!-- FIN SELECTOR PRODUCTOS MERC -->

		<script>
		jQuery(document).ready(function($) {
			var $productoSelect  = $('#merc_producto_id');
			var $cantidadInput   = $('#merc_producto_cantidad');
			var $stockDisplay    = $('#merc_stock_display');
			var $warning         = $('#merc_stock_warning');
			var $warningText     = $('#merc_warning_text');

			function actualizarStock() {
				var $option  = $productoSelect.find('option:selected');
				var stock    = parseInt($option.data('stock')) || 0;
				var cantidad = parseInt($cantidadInput.val()) || 0;

				if (!$option.val()) {
					$stockDisplay.text('');
					$warning.hide();
					return;
				}

				$stockDisplay.html('📦 Disponible: <strong>' + stock + '</strong>');
				$cantidadInput.attr('max', stock);

				if (cantidad > stock) {
					$warning.show();
					$warningText.text('Stock insuficiente. Solo hay ' + stock + ' unidades disponibles.');
					$cantidadInput.val(stock);
				} else {
					$warning.hide();
				}
			}

			$productoSelect.on('change', actualizarStock);
			$cantidadInput.on('input change', actualizarStock);

			$('form.wpcfe-new-shipment-form, form[name="wpcfe-shipment-form"]').on('submit', function(e) {
				var productoId = $productoSelect.val();
				if (!productoId) {
					e.preventDefault();
					alert('⚠️ Debes seleccionar un producto');
					$productoSelect.focus();
					return false;
				}
				var stock    = parseInt($productoSelect.find('option:selected').data('stock')) || 0;
				var cantidad = parseInt($cantidadInput.val()) || 0;
				if (cantidad > stock) {
					e.preventDefault();
					alert('⚠️ Stock insuficiente. Solo hay ' + stock + ' unidades.');
					$cantidadInput.focus();
					return false;
				}
			});

			actualizarStock();
		});
		</script>

		<style>
		#merc_producto_wrapper {
			display:    block   !important;
			visibility: visible !important;
			opacity:    1       !important;
			position:   relative !important;
			z-index:    1       !important;
		}
		</style>
		<?php
		echo ob_get_clean();
	}

	/* ── Valores predeterminados en campos de paquetes ───────────────────── */

	public function package_defaults_script(): void {
		if ( ! isset( $_GET['wpcfe'] ) || $_GET['wpcfe'] !== 'add' || ! isset( $_GET['type'] ) ) return;
		?>
		<script>
		jQuery(document).ready(function($) {
			setTimeout(function() {
				$('#wpcfe-packages-repeater tbody tr').each(function() {
					var $row = $(this);
					var lengthField = $row.find('input[name*="length"]');
					var widthField  = $row.find('input[name*="width"]');
					var heightField = $row.find('input[name*="height"]');
					var weightField = $row.find('input[name*="weight"]');
					if (lengthField.length && !lengthField.val()) lengthField.val('25');
					if (widthField.length  && !widthField.val())  widthField.val('25');
					if (heightField.length && !heightField.val()) heightField.val('25');
					if (weightField.length && !weightField.val()) weightField.val('3');
				});
			}, 500);
		});
		</script>
		<?php
	}

	/* ── CSS: ocultar columna de descripción en paquetes ─────────────────── */

	public function package_form_styles(): void {
		if ( ! isset( $_GET['wpcfe'] ) || $_GET['wpcfe'] !== 'add' ) return;
		$hide_packages = ( isset( $_GET['type'] ) && $_GET['type'] === 'full_fitment' );
		?>
		<style>
		textarea.wpc-pm-description,
		textarea[name*="[wpc-pm-description]"] { display: none !important; }
		#wpcfe-packages-repeater td:has(textarea.wpc-pm-description),
		#wpcfe-packages-repeater td:has(textarea[name*="[wpc-pm-description]"]) { display: none !important; }
		#wpcfe-packages-repeater thead tr th:nth-child(3) { display: none !important; }
		<?php if ( $hide_packages ) : ?>
		#package_id { display: none !important; }
		<?php endif; ?>
		</style>
		<?php
	}

	/* ── Guardar datos financieros del formulario ─────────────────────────── */

	public function save_financial_data( int $post_id ): void {
		if ( get_post_type( $post_id ) !== 'wpcargo_shipment' ) return;

		if ( isset( $_POST['wpcargo_costo_producto'] ) ) {
			update_post_meta( $post_id, 'wpcargo_costo_producto',
				sanitize_text_field( $_POST['wpcargo_costo_producto'] ) );
		}

		if ( isset( $_POST['wpcargo_costo_envio'] ) ) {
			update_post_meta( $post_id, 'wpcargo_costo_envio',
				sanitize_text_field( $_POST['wpcargo_costo_envio'] ) );
		}

		if ( isset( $_POST['wpcargo_cargo_remitente'] ) ) {
			update_post_meta( $post_id, 'wpcargo_cargo_remitente',
				sanitize_text_field( $_POST['wpcargo_cargo_remitente'] ) );
		}

		$monto = floatval( get_post_meta( $post_id, 'wpcargo_monto', true ) );

		update_post_meta( $post_id, 'wpcargo_quien_paga', 'remitente' );
		update_post_meta( $post_id, 'wpcargo_cobrado_por_motorizado', $monto > 0 ? $monto : '0' );

		if ( ! get_post_meta( $post_id, 'wpcargo_estado_pago_motorizado', true ) ) {
			update_post_meta( $post_id, 'wpcargo_estado_pago_motorizado', 'pendiente' );
		}
		if ( ! get_post_meta( $post_id, 'wpcargo_cliente_pago_a', true ) ) {
			update_post_meta( $post_id, 'wpcargo_cliente_pago_a', 'pendiente' );
		}
	}

	/* ── Verificación final del costo de envío (logging) ─────────────────── */

	public function verify_final_shipping_cost( int $post_id ): void {
		if ( get_post_type( $post_id ) !== 'wpcargo_shipment' ) return;
		$costo    = get_post_meta( $post_id, 'wpcargo_costo_envio', true );
		$distrito = get_post_meta( $post_id, 'wpcargo_distrito_destino', true );
		$tipo     = get_post_meta( $post_id, 'tipo_envio', true );
		error_log( "🔚 [FINAL_VERIFICATION] Envío #{$post_id} | Tipo: {$tipo} | Distrito: {$distrito} | Costo: {$costo}" );
	}

	/* ── Logging al editar un envío ───────────────────────────────────────── */

	public function log_edit_shipping_cost( int $post_id, \WP_Post $post ): void {
		if ( get_post_type( $post_id ) !== 'wpcargo_shipment' ) return;
		if ( $post->post_status === 'auto-draft' ) return;
		$costo    = get_post_meta( $post_id, 'wpcargo_costo_envio', true );
		$distrito = get_post_meta( $post_id, 'wpcargo_distrito_destino', true );
		$tipo     = get_post_meta( $post_id, 'tipo_envio', true );
		error_log( "✏️ [EDIT_DETECTED] Envío #{$post_id} | Tipo: {$tipo} | Distrito: {$distrito} | Costo antes: {$costo}" );
	}
}

new MERC_Shipment_Form_Fields();
