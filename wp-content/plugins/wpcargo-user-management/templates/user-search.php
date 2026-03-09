<div class="table-top form-group">
    <form id="wpcfe-search" class="float-md-none float-lg-right" action="<?php echo get_permalink( wpcumanage_users_page() ); ?>" method="get">
        <div class="form-sm d-flex gap-2" style="align-items: center;">
            <label for="search-shipment" class="sr-only"><?php _e('Search User', 'wpcargo-invoice' ); ?></label>
            <input type="text" class="form-control form-control-sm" name="_user" id="search-shipment" placeholder="<?php _e('Nombre, empresa, email...', 'wpcargo-invoice' ); ?>" value="<?php echo $searched_user; ?>" style="flex: 1; min-width: 150px;">
            <button type="submit" class="btn btn-primary btn-sm px-0" style="white-space: nowrap; text-align: center;width: -webkit-fill-available;"><?php _e('Buscar', 'wpcargo-invoice' ); ?></button>
        </div>
    </form>
</div>