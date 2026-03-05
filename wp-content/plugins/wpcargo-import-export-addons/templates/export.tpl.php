<form id="wpcie-export-form" method="POST" class="container-fluid" action="<?php echo get_the_permalink(); ?>" >
    <div id="wpcie-fields-wrapper" class="row">
        <div id="wpcie-meta_fields_wrapper" class="col-md-4 border-right">
            <?php do_action( 'wpcie_frontend_before_export_form_field' ); ?>
            <section class="form-group">
                <label for="<?php echo $search_data['metakey']; ?>"><?php echo $search_data['label']; ?></label>
                <input id="<?php echo $search_data['metakey']; ?>" type="text" class="form-control _group-data" name="<?php echo $search_data['metakey']; ?>" value="">
            </section>
            <?php if( !empty($users) && !wpcie_is_client() ): ?>
                <section class="form-group">
                    <label for="registered_shipper"><?php _e( 'Registered Shipper', 'wpc-import-export' ); ?></label>
                    <select name="registered_shipper" class="form-control browser-default custom-select _group-data" id="registered_shipper">
                        <option value=""><?php _e('-- Registered Shipper --', 'wpc-import-export' ); ?></option>
                        <?php foreach( $users as $uid => $ufullname ): ?>
                            <option value="<?php echo $uid; ?>"><?php echo $ufullname; ?></option>
                        <?php endforeach; ?>      
                    </select>
                </section>
            <?php endif; ?>
            <?php if( !empty( $wpcargo->status ) ): ?>
                <section class="form-group">
                    <label for="shipment_status"><?php _e( 'Status', 'wpc-import-export' ); ?></label>
                    <select name="wpcargo_status" class="form-control browser-default custom-select _group-data" id="shipment_status">
                        <option value=""><?php _e('-- Status --', 'wpc-import-export' ); ?></option>
                        <?php foreach( $wpcargo->status as $status ): ?>
                            <option value="<?php  echo $status; ?>"><?php echo $status; ?></option>
                        <?php endforeach; ?>      
                    </select>
                </section>
            <?php endif; ?>
            <?php do_action( 'wpcie_frontend_middle_export_form_field' ); ?>
            <section class="form-row mb-4">
                <div class="col-md-6 mb-4">
                    <div class="md-form">
                        <input placeholder="<?php _e('YYYY-MM-DD', 'wpc-import-export'); ?>" type="text" id="startingDate" name="date-from" class="form-control datepicker _group-data" required>
                        <label for="startingDate"><?php _e( 'Start', 'wpc-import-export' ); ?></label>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                    <div class="md-form">
                        <input placeholder="<?php _e('YYYY-MM-DD', 'wpc-import-export'); ?>" type="text" id="endingDate" name="date-to" class="form-control datepicker _group-data" required>
                        <label for="endingDate"><?php _e( 'End', 'wpc-import-export' ); ?></label>
                    </div>
                </div>
            </section>
            <?php do_action( 'wpcie_frontend_after_export_form_field' ); ?>
        </div>
        <div id="wpcie-meta_options_wrapper" class="col-md-8">
            <div class="container">
                <section id="multi-select-export" class="row">
                    <div class="col-sm-5">
                        <label for="wpcie-multiselect" class="col-sm-12"><?php _e( 'Available Fields', 'wpc-import-export' ); ?></label>
                        <select id="wpcie-multiselect" class="form-control browser-default custom-select" size="12" multiple="multiple">
                            <?php
                            ksort($field_options);
                            if($field_options) {
                                foreach( $field_options as $value ){
                                    ?><option value="<?php echo $value['meta_key']; ?>"><?php echo stripslashes( $value['label'] ); ?></option><?php
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-sm-2">
                        <label class="col-sm-12 text-center"><i class="fa fa-list-alt"></i></label>
                        <button type="button" id="wpcie-multiselect_rightAll" class="btn btn-sm btn-success"><i class="fa fa-angle-double-right font-weight-bold"></i></button>
                        <button type="button" id="wpcie-multiselect_rightSelected" class="btn btn-sm btn-success"><i class="fa fa-angle-right font-weight-bold"></i></button>
                        <button type="button" id="wpcie-multiselect_leftSelected" class="btn btn-sm btn-success"><i class="fa fa-angle-left font-weight-bold"></i></button>
                        <button type="button" id="wpcie-multiselect_leftAll" class="btn btn-sm btn-success"><i class="fa fa-angle-double-left font-weight-bold"></i></button>
                    </div>
                    <div class="col-sm-5">
                        <label for="wpcie-multiselect_to" class="col-sm-12"><?php _e( 'Selected Fields', 'wpc-import-export' ); ?></label>
                        <select id="wpcie-multiselect_to" class="form-control browser-default custom-select" size="12" multiple="multiple">
                            <?php 
                                if(!empty( $saved_field_options ) ) {
                                    foreach ($saved_field_options as $optkey => $optvalue ) {
                                        echo "<option value='".$optkey."'>".stripslashes( $optvalue )."</option>";
                                    }
                                }
                            ?>
                        </select>
                    </div>
                </section>
            </div><!-- End Container -->
        </div>
        <section class="form-group">
		 <div class="col-sm-12 mt-2">
			 <input type="checkbox" class="form-check-input"  id="export_only_label" name="export_only_label" value="1"  onclick="togglePackagesSection()" />
		  <label for="export_only_label" class="form-check-label"><?php _e( 'Exclude meta keys on template header', 'wpc-import-export' ); ?></label>
		  
		</div>
		</section>
        <div class="col-sm-12 mt-2">
        <input type="submit" class="btn btn-primary btn-sm" name="export_shipment" value="<?php _e( 'Export Shipment', 'wpc-import-export' ); ?>" />
        </div>
    </div> <!-- End row -->
</form>