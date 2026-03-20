<?php
// Display fulfillment products if they exist
$fulfillment_productos = get_post_meta( $shipment_id, '_merc_productos_multi', true );
error_log( 'Fulfillment productos: ' . print_r( $fulfillment_productos, true ) );
error_log( 'Shipment ID: ' . $shipment_id );
if ( is_array( $fulfillment_productos ) && ! empty( $fulfillment_productos ) ) {
	?>
	<div id="merc-productos-fulfillment" class="wpcargo-row detail-section">
		<div class="wpcargo-col-md-12">
			<p id="merc-productos-fulfillment-header" class="header-title"><strong><?php echo esc_html__( 'Productos', 'wpcargo' ); ?></strong></p>
		</div>
		<?php
			foreach( $fulfillment_productos as $producto ) {
			if ( ! empty( $producto['id'] ) ) {
				$product_id = $producto['id'];
				$cantidad = ! empty( $producto['cantidad'] ) ? $producto['cantidad'] : 1;
				
				// Get product using WordPress native function
				$product_post = get_post( $product_id );
				
				// DEBUG: Log what we're getting
				error_log( 'Product ID: ' . $product_id . ', Post type: ' . ( $product_post ? $product_post->post_type : 'null' ) . ', Post title: ' . ( $product_post ? $product_post->post_title : 'null' ) );
				
				if ( $product_post ) {
					$product_name = $product_post->post_title;
					$product_image_id = get_post_meta( $product_id, '_thumbnail_id', true );
					$product_image = $product_image_id ? wp_get_attachment_url( $product_image_id ) : '';
					
					// Get additional product data
					$tipo_medida = get_post_meta( $product_id, '_merc_producto_tipo_medida', true );
					$dimensiones = get_post_meta( $product_id, '_merc_producto_dimensiones', true );
					$peso = get_post_meta( $product_id, '_weight', true );
					?>
					<div class="wpcargo-col-md-4">
						<p class="wpcargo-label"><strong><?php echo esc_html( $product_name ); ?>:</strong></p>
						<p class="wpcargo-label-info"><?php echo sprintf( esc_html_x( 'x%d', 'quantidade', 'mercourier' ), absint( $cantidad ) ); ?></p>
						
						<?php if ( $tipo_medida ) : ?>
							<p class="wpcargo-label-info" style="font-size: 0.9em; color: #666;">Tipo: <?php echo esc_html( $tipo_medida ); ?></p>
						<?php endif; ?>
						
						<?php if ( ! empty( $dimensiones ) && is_array( $dimensiones ) ) : ?>
							<p class="wpcargo-label-info" style="font-size: 0.9em; color: #666;">Dim: <?php echo intval( $dimensiones['largo'] ?? 0 ) . 'x' . intval( $dimensiones['ancho'] ?? 0 ) . 'x' . intval( $dimensiones['alto'] ?? 0 ); ?> cm</p>
						<?php endif; ?>
						
						<?php if ( $peso ) : ?>
							<p class="wpcargo-label-info" style="font-size: 0.9em; color: #666;">Peso: <?php echo esc_html( $peso ); ?> kg</p>
						<?php endif; ?>
						
						<?php if ( $product_image ) : ?>
							<img src="<?php echo esc_url( $product_image ); ?>" alt="<?php echo esc_attr( $product_name ); ?>" style="max-width: 100px; height: auto; margin-top: 5px;">
						<?php endif; ?>
					</div>
					<?php
				}
			}
		}
		?>
	</div>
	<?php
}

foreach( $sections as $section_key => $section_label ){
	$fields = wpc_cf_get_fields($section_key);
	if( empty( $fields  ) ){
		continue;
	}
	?>
	<div id="<?php echo $section_key; ?>" class="wpcargo-row detail-section">
		<div class="wpcargo-col-md-12">
			<p id="<?php echo $section_key; ?>-header" class="header-title"><strong><?php echo $section_label; ?></strong></p>
		</div>
		<?php
			foreach( $fields as $field){
				$field_key 		= get_post_meta($shipment_id, $field->field_key, TRUE);
				$field_label 	= stripslashes( $field->label );
				if( is_serialized($field_key) ){
					if( $field->field_type == 'url' ) {
						$url_key_unserialized = unserialize($field_key);
						if(is_array($url_key_unserialized)) {
							$target_blank = !empty($url_key_unserialized[2]) ? 'target="_blank"' :'';
							?>
							<div class="wpcargo-col-md-4">
								<p class="wpcargo-label"><strong><?php echo $field_label; ?>:</strong></p>
								<p class="wpcargo-label-info">
									<a href="<?php echo $url_key_unserialized[1]; ?>" <?php echo $target_blank; ?>><?php echo $url_key_unserialized[0]; ?></a>
								</p>
							</div>
							<?php
						}
					}elseif( $field->field_type == 'address' ) {
						$address = wpccf_get_address( $shipment_id, $field->field_key);	
						?>			
						<div class="wpcargo-col-md-4">			
							<p class="wpcargo-label"><strong><?php echo $field_label; ?>:</strong></p>				
							<p class="wpcargo-label-info"><?php echo $address; ?></p>			
						</div>			
						<?php		
					}else{
						$field_key 	= maybe_unserialize($field_key);
						$field_key = array_filter( array_map('trim', $field_key ) );
						$field_key = implode(", ", $field_key);
						?>			
						<div class="wpcargo-col-md-4">			
							<p class="wpcargo-label"><strong><?php echo $field_label; ?>:</strong></p>	
							<p class="wpcargo-label-info"><?php echo $field_key; ?></p>	
						</div>			
						<?php
					}
				}elseif( $field->field_type == 'file' ) {
					$explode_data = explode(",", $field_key);
					?>
					<div class="wpcargo-col-md-4">
						<p class="wpcargo-label"><strong><?php echo $field_label; ?>:</strong></p>
						<?php if(is_array($explode_data)): ?>
							<?php foreach(array_filter($explode_data) as $get_file): ?>
								<div class="file-wrap">
									<a href="<?php echo wp_get_attachment_url($get_file); ?>"><?php echo wp_get_attachment_image($get_file, 'thumbnail', TRUE); ?></a>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
					<?php
				}elseif( $field->field_type == 'signature' ) {
				?>
					<div class="wpcargo-col-md-4">
						<p class="wpcargo-label"><strong><?php echo $field_label; ?>:</strong></p>
						<div class="signature-wrap">
							<img src="<?php echo wp_get_attachment_url( get_post_meta($shipment_id, $field->field_key.'-attachement-id', TRUE) ); ?>">
						</div>
					</div>
					<?php
				}elseif( $field->field_type == 'date' ) {
					$field_key 				= get_post_meta($shipment_id, $field->field_key, TRUE);
					$wpc_date_format 		= get_option( 'date_format' );	
					?>			
					<div class="wpcargo-col-md-4">			
						<p class="wpcargo-label"><strong><?php echo $field_label; ?>:</strong></p>			
						<p class="wpcargo-label-info"><?php echo $field_key; ?></p>			
					</div>			
					<?php		
				}elseif( $field->field_type == 'agent' ) {
					$agentID = get_post_meta($shipment_id, $field->field_key, TRUE);
					if( $agentID && is_numeric( $agentID ) ){
					$field_key = wpccf_user_displayname( $agentID );
					}else{
						$field_key = $agentID;
					}
					?>			
					<div class="wpcargo-col-md-4">			
						<p class="wpcargo-label"><strong><?php echo $field_label; ?>:</strong></p>				
						<p class="wpcargo-label-info"><?php echo $field_key; ?></p>			
					</div>			
					<?php		
				}elseif( $field->field_type == 'address' ) {
					$address = wpccf_get_address( $shipment_id, $field->field_key);	
					?>			
					<div class="wpcargo-col-md-4">			
						<p class="wpcargo-label"><strong><?php echo $field_label; ?>:</strong></p>				
						<p class="wpcargo-label-info"><?php echo $address; ?></p>			
					</div>			
					<?php		
				}elseif( $field->field_type == 'url' ) {

					$field_key 		= maybe_unserialize( $field_key );
					$field_key 		= is_array( $field_key ) ? $field_key : array();
					$target_blank 	= count($field_key) == 3 ? 'target="_blank"' :'';

					?>
					<div class="wpcargo-col-md-4">
						<p class="wpcargo-label"><strong><?php echo $field_label; ?>:</strong></p>
						<?php if( !empty( $field_key ) ): ?>
						<p class="wpcargo-label-info">
							<a href="<?php echo $field_key[1]; ?>" <?php echo $target_blank; ?>><?php echo $field_key[0]; ?></a>
						</p>
						<?php endif; ?>
					</div>
					<?php		
				}else{
					$field_key = get_post_meta($shipment_id, $field->field_key, TRUE);
					$value = maybe_unserialize( $field_key  );
					$value = is_array( $value ) ? implode(", ", $value) : $value ;
					?>			
					<div class="wpcargo-col-md-4">	
						<p class="wpcargo-label"><strong><?php echo $field_label; ?>:</strong></p>
						<p class="wpcargo-label-info"><?php echo $value; ?></p>			
					</div>			
					<?php
				}
			}
		?>
	</div>
	<?php
}

