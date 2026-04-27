<div class="pod-tracking-header wpcargo-container">
    <div class="wpcargo-row"> 
        <div id="wpcargo-barcode-header" class="wpcargo-col text-center">
            <img src="<?php echo $wpcargo->barcode_url( $get_sid ); ?>" alt="<?php echo get_the_title($get_sid); ?>">
            <h2 class="wpcargo-title"><?php echo get_the_title($get_sid); ?></h2>
            <h3><?php echo $wpcargo_get_status; ?></h3>
        </div>          
    </div>
    <div class="wpcargo-row">
        <div class="pod-shipper wpcargo-md-6">
            <h4 class="header-title"><?php esc_html_e( 'Envía', 'wpcargo-pod' ); ?></h4>
            <p><span><?php esc_html_e('Shipper Name:', 'wpcargo-pod' ); ?></span>  <?php echo get_post_meta($get_sid, 'wpcargo_shipper_name', true); ?></p>
            <?php 
            $phone_shipper = get_post_meta($get_sid, 'wpcargo_shipper_phone', true);
            $wa_shipper = preg_replace('/[^0-9]/', '', $phone_shipper);
            ?>
            <p><span><?php esc_html_e('Phone:', 'wpcargo-pod' ); ?></span>  <?php echo esc_html($phone_shipper); ?>
                <?php if($wa_shipper): ?>
                    <a href="https://wa.me/<?php echo esc_attr($wa_shipper); ?>" target="_blank" style="margin-left:8px; text-decoration:none; background:#25D366; color:white; padding:2px 8px; border-radius:12px; font-size:12px;" title="Contactar por WhatsApp">💬 WhatsApp</a>
                <?php endif; ?>
            </p>
            <p><span><?php esc_html_e('Email:', 'wpcargo-pod' ); ?></span>  <?php echo get_post_meta($get_sid, 'wpcargo_shipper_email', true); ?></p>
    		<p><span><?php esc_html_e('Shipper Address:', 'wpcargo-pod' ); ?></span>  <?php echo ''.get_post_meta($get_sid, 'wpcargo_shipper_address', true); ?></p>
        </div>
        <div class="pod-receiver wpcargo-md-6">
            <h4 class="header-title"><?php esc_html_e( 'Recibe', 'wpcargo-pod' ); ?></h4>
            <p><span><?php esc_html_e('Receiver Name:', 'wpcargo-pod' ); ?></span>  <?php echo get_post_meta($get_sid, 'wpcargo_receiver_name', true); ?></p>	
            <?php 
            $phone_receiver = get_post_meta($get_sid, 'wpcargo_receiver_phone', true);
            $wa_receiver = preg_replace('/[^0-9]/', '', $phone_receiver);
            ?>
            <p><span><?php esc_html_e('Phone:', 'wpcargo-pod' ); ?></span>  <?php echo esc_html($phone_receiver); ?>
                <?php if($wa_receiver): ?>
                    <a href="https://wa.me/<?php echo esc_attr($wa_receiver); ?>" target="_blank" style="margin-left:8px; text-decoration:none; background:#25D366; color:white; padding:2px 8px; border-radius:12px; font-size:12px;" title="Contactar por WhatsApp">💬 WhatsApp</a>
                <?php endif; ?>
            </p>
            <p><span><?php esc_html_e('Email:', 'wpcargo-pod' ); ?></span>  <?php echo get_post_meta($get_sid, 'wpcargo_receiver_email', true); ?></p>
            <p><span><?php esc_html_e('Receiver Address:', 'wpcargo-pod' ); ?></span>  <?php echo ''.get_post_meta($get_sid, 'wpcargo_receiver_address', true); ?></p>	
        </div>	
    </div>			
</div>
