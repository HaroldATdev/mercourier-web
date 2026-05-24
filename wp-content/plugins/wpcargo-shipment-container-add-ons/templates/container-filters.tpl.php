<div id="wpcsc-filter_wrapper" class="row mb-3 border-bottom">
	<section class="col-md-9 mb-3">
		<form id="wpcfe-filters" action="<?php echo $page_url; ?>" class="form-inline" style="width: 100%">
			<?php do_action( 'wpcsc_before_container_filters' ); ?>
			<?php if( !empty( $wpcargo->status ) ): ?>
				<div class="form-group wpcfe-filter status-filter p-0 mx-1">
					<label class="sr-only" for="status"><?php esc_html_e('Status', 'wpcargo-shipment-container' ); ?></label>
					<select id="status" name="status" class="form-control md-form wpcfe-select">
						<option value=""><?php echo esc_html__('All Status', 'wpcargo-shipment-container' ); ?></option>
						<?php 
							foreach ( $wpcargo->status as $status ) {
								?><option value="<?php echo $status; ?>" <?php echo selected( $sstatus, $status ); ?>><?php echo $status; ?></option><?php
							}
						?>
					</select>
				</div>
			<?php endif; ?>
			<div id="wpcfe-created-fields" class="form-group wpcfe-filter receiver-filter p-0 mx-1">
				<div class="md-form form-group">
					<?php _e('Date Created', 'wpcargo-shipment-container' ); ?>
					<input id="date_start" type="text" name="date_start" class="form-control daterange_picker start_date px-2 py-1 mx-2" value="<?php echo $date_start; ?>" autocomplete="off" style="width: 96px;">
					<div class="input-group-addon"><?php _e('to', 'wpcargo-shipment-container' ); ?></div>
					<input id="date_end" type="text" name="date_end" class="form-control daterange_picker end_date px-2 py-1 mx-2" value="<?php echo $date_end; ?>" autocomplete="off" style="width: 96px;">
				</div>
			</div>
			<?php do_action( 'wpcsc_after_container_filters' ); ?>
			<div class="form-group submit-filter p-0 mx-1">
				<button id="wpcfe-submit-filter" type="submit" class="btn btn-primary btn-fill btn-sm"><?php esc_html_e('Filter', 'wpcargo-shipment-container' ); ?></button>
			</div>
		</form>
	</section>
	<section class="col-md-3 mb-3">
		<form id="wpcfe-search" class="float-md-none float-lg-right form-inline" action="<?php echo $page_url; ?>" method="get">
			<input type="hidden" name="wpcsc" value="s">
			<div class="form-sm">
				<label for="search-shipment" class="sr-only"><?php echo wpc_scpt_container_num_label(); ?></label>
				<input type="text" class="form-control form-control-sm" name="num" id="search-shipment" placeholder="<?php echo wpc_scpt_container_num_label(); ?>" value="<?php echo $searched; ?>">
				<button type="submit" class="btn btn-primary btn-sm mx-md-0 ml-2"><?php esc_html_e('Search', 'wpcargo-shipment-container' ); ?></button>
			</div>
		</form>
	</section>
</div>
<div id="wpcsc-action_wrapper" class="mb-3">
	<div class="table-top">
		<form id="shipment-sort" class="float-right" action="<?php echo $page_url; ?>" method="get">
			<select name="wpcsc_page" class="form-control form-control-sm browser-default" style="display: inline-block; margin: 0.375rem 0;">
				<option ><?php echo __('Show entries', 'wpcargo-shipment-container' ); ?></option>
				<?php foreach( $wpcsc_list as $list ): ?>
				<option value="<?php echo $list; ?>" <?php selected($wpcsc_page, $list ); ?>><?php echo $list ?> <?php echo __('entries', 'wpcargo-shipment-container' ); ?></option>
				<?php endforeach; ?>
			</select>
		</form>
		
		<!-- ESTADÍSTICAS DE CONTENEDORES -->
		<div style="margin-right: 20px; display: inline-block;">
			<?php
				global $wpdb;
				
				// Ajustar a zona horaria de Perú (UTC-5)
				$original_tz = date_default_timezone_get();
				date_default_timezone_set('America/Lima');
				$today_peru = date('d/m/Y');
				$today_peru_alt = date('j/n/Y');
				date_default_timezone_set($original_tz);

				// No additional GET filters here: only filter by pickup date (today)
				
				$transient_key = 'merc_cont_stats_' . date('Ymd');
				$stats = get_transient($transient_key);
				
				if (false === $stats) {
					// Puntos de Recojo: contar usuarios ÚNICOS con envíos tipo normal para HOY
					// que AÚN NO TIENEN motorizado de recojo asignado (wpcargo_motorizo_recojo vacío o 0)
					// y que no estén en estado terminal
					$recojo_query = "
						SELECT COUNT(DISTINCT CONCAT(pm_shipper.meta_value, '-', IFNULL(pm_cont_rec.meta_value, '0'))) as conteo
						FROM {$wpdb->posts} p
						INNER JOIN {$wpdb->postmeta} pm_shipper ON pm_shipper.post_id = p.ID AND pm_shipper.meta_key = 'registered_shipper'
						INNER JOIN {$wpdb->postmeta} pm_tipo ON pm_tipo.post_id = p.ID AND pm_tipo.meta_key = 'tipo_envio'
						INNER JOIN {$wpdb->postmeta} pd ON pd.post_id = p.ID AND pd.meta_key = 'wpcargo_pickup_date_picker'
						LEFT JOIN {$wpdb->postmeta} pm_cont_rec ON pm_cont_rec.post_id = p.ID AND pm_cont_rec.meta_key = 'shipment_container_recojo'
						LEFT JOIN {$wpdb->postmeta} pm_mot_rec ON pm_mot_rec.post_id = p.ID AND pm_mot_rec.meta_key = 'wpcargo_motorizo_recojo'
						LEFT JOIN {$wpdb->postmeta} pm_status ON pm_status.post_id = p.ID AND pm_status.meta_key = 'wpcargo_status'
						WHERE p.post_type = 'wpcargo_shipment' AND p.post_status = 'publish'
						AND pm_shipper.meta_value != ''
						AND pm_tipo.meta_value = 'normal'
						AND (pd.meta_value = '{$today_peru}' OR pd.meta_value = '{$today_peru_alt}')
						AND (pm_mot_rec.meta_value IS NULL OR pm_mot_rec.meta_value = '' OR pm_mot_rec.meta_value = '0')
						AND (pm_status.meta_value IS NULL OR UPPER(TRIM(pm_status.meta_value)) IN ('PENDIENTE', 'RECOGIDO', 'NO RECOGIDO'))
					";
					$puntos_recojo = $wpdb->get_var($recojo_query);
					$puntos_recojo = $puntos_recojo ?: 0;

					// Puntos de Entrega: contar total de envíos de HOY
					// que AÚN NO TIENEN motorizado de entrega asignado (wpcargo_motorizo_entrega vacío o 0)
					// y que no estén en estado terminal
					$entrega_query = "
						SELECT COUNT(DISTINCT p.ID) as conteo
						FROM {$wpdb->posts} p
						INNER JOIN {$wpdb->postmeta} pm_fecha ON pm_fecha.post_id = p.ID AND pm_fecha.meta_key = 'wpcargo_pickup_date_picker'
						LEFT JOIN {$wpdb->postmeta} pm_mot_ent ON pm_mot_ent.post_id = p.ID AND pm_mot_ent.meta_key = 'wpcargo_motorizo_entrega'
						LEFT JOIN {$wpdb->postmeta} pm_status ON pm_status.post_id = p.ID AND pm_status.meta_key = 'wpcargo_status'
						WHERE p.post_type = 'wpcargo_shipment' AND p.post_status = 'publish'
						AND (pm_fecha.meta_value = '{$today_peru}' OR pm_fecha.meta_value = '{$today_peru_alt}')
						AND (pm_mot_ent.meta_value IS NULL OR pm_mot_ent.meta_value = '' OR pm_mot_ent.meta_value = '0')
						AND (pm_status.meta_value IS NULL OR pm_status.meta_value NOT IN ('ENTREGADO', 'EN RUTA', 'NO RECIBIDO', 'ANULADO', 'DELIVERED', 'COMPLETE', 'FINALIZED'))
					";
					$puntos_entrega = $wpdb->get_var($entrega_query);
					$puntos_entrega = $puntos_entrega ?: 0;
					
					$stats = array(
						'recojo' => $puntos_recojo,
						'entrega' => $puntos_entrega
					);
					set_transient($transient_key, $stats, 2 * MINUTE_IN_SECONDS);
				} else {
					$puntos_recojo = $stats['recojo'];
					$puntos_entrega = $stats['entrega'];
				}
			?>
			<span style="font-weight: bold; margin-right: 30px; color: #333;">
				📍 Puntos de Recojo: <span style="color: #007bff; font-size: 1.1em;"><?php echo $puntos_recojo; ?></span>
			</span>
			<span style="font-weight: bold; color: #333;">
				🚚 Puntos de Entrega: <span style="color: #28a745; font-size: 1.1em;"><?php echo $puntos_entrega; ?></span>
			</span>
		</div>
		
		<?php if( can_access_containers() && update_container_role() ): ?>
		<a href="<?php echo $page_url; ?>?wpcsc=add" class="addShipmentContainer btn btn-primary btn-sm"><i class="fa fa-truck text-white"></i> <?php echo wpc_scpt_add_new_item_label(); ?></a>
		<?php endif; ?>
		<?php do_action('wpcsc_after_add_container_dashboard'); ?>
	</div>
</div>









