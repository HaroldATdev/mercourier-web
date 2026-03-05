<?php
if ( ! defined( 'WPINC' ) ) {
	die;
}
function wpcie_import_export_activation_callback() {
    if( wpcie_get_frontend_page() ){
        return false;
    }
    // Create post object
    $importExport = array(
        'post_title'    => wp_strip_all_tags( wpcie_lang_import_export_menu() ),
        'post_content'  => '[wpcie_import_export]',
        'post_status'   => 'publish',
        'post_type'   	=> 'page',
    );
    // Insert the post into the database
    $shortcode_id = wp_insert_post( $importExport );
    update_post_meta( $shortcode_id, '_wp_page_template', 'dashboard.php');
}
register_activation_hook( WPC_IMPORT_EXPORT_FILE, 'wpcie_import_export_activation_callback' );
// Register Settings
function register_wpcie_settings() {
    //register our settings
    register_setting( 'wpcie_registered_settings_group', 'wpcie_disable' );
    register_setting( 'wpcie_registered_settings_group', 'wpcie_restricted_role' );
    register_setting( 'wpcie_registered_settings_group', 'wpcie_email_notification' );
}
add_action( 'admin_init', 'register_wpcie_settings' );
// Settings page
function wpcie_settings_menu_callback(){
    global $wp_roles, $wpcargo;
    $roles      = $wp_roles->get_names();
    $rest_roles = wpcie_restricted_role();
    ?>
    <div class="wrap">
        <?php require_once( WPCARGO_PLUGIN_PATH.'admin/templates/admin-navigation.tpl.php' ); ?>		
        <?php require_once( WPC_IMPORT_EXPORT_PATH.'templates/settings.tpl.php' ); ?>
    </div>
    <?php
}
function wpcie_settings_menu(){		
    add_submenu_page( 
        'wpcargo-settings', 
        __('Import/Export Settings', 'wpc-import-export'),
        __('Import/Export Settings', 'wpc-import-export'),
        'manage_options',
        'admin.php?page=wpcie-settings'
    );
    add_submenu_page( 
        'wpcie-settings', 
        __('Import/Export Settings', 'wpc-import-export'),
        __('Import/Export Settings', 'wpc-import-export'),
        'manage_options',
        'wpcie-settings',
        'wpcie_settings_menu_callback'
    );
}
add_action('admin_menu', 'wpcie_settings_menu' ,45);
function wpcie_add_settings_nav_sms() {	
    $view = isset( $_GET['page'] ) ? $_GET['page'] : '';
    ?>
    <a class="nav-tab <?php echo ( $view == 'wpcie-settings') ? 'nav-tab-active' : '' ;  ?>" href="<?php echo admin_url().'admin.php?page=wpcie-settings'; ?>" ><?php _e( 'Import/Export', 'wpc-import-export' ); ?></a>		
    <?php	
}
add_action('wpc_add_settings_nav', 'wpcie_add_settings_nav_sms' ,45);
function wpcie_row_action_callback( $actions ){
    $mylinks = array(
		'<a href="' . admin_url( 'admin.php?page=wpcie-settings' ) . '" aria-label="' . __( 'Settings', 'wpc-import-export' ) . '">' . __( 'Settings', 'wpc-import-export' ) . '</a>',
		'<a href="' . admin_url( 'admin.php?page=wptaskforce-helper' ) . '" aria-label="' . __( 'License', 'wpc-import-export' ) . '">' . __( 'License', 'wpc-import-export' ) . '</a>'
	);
	$actions = array_merge( $actions, $mylinks );
	return $actions;
}
add_filter('plugin_action_links_' .WPC_IMPORT_EXPORT_BASENAME, 'wpcie_row_action_callback', 10);
// Remove Report WPCargo FREE submenu
add_action( 'plugins_loaded', function(){
    global $wpc_export_admin;
    remove_action('admin_menu', array( $wpc_export_admin,'wpc_import_export_submenu_page') );
} );
function wpcie_umaccess_list_callback( $access ){
	$access['import'] = __( 'Import Shipment', 'wpc-import-export' );
	$access['export'] = __( 'Export Shipment', 'wpc-import-export' );
	return $access;
}
add_filter( 'wpcumanage_access_list', 'wpcie_umaccess_list_callback' );


// Plugin Localization for the text Domain
function wpcargo_import_and_export_load_textdomain() {
	load_plugin_textdomain( 'wpc-import-export', false, '/wpcargo-import-export-addons/languages' );
}
add_action( 'plugins_loaded', 'wpcargo_import_and_export_load_textdomain' );
// Export users query filter
function wpcie_sql_users_filter_table( $sql ){
    if( wpcfe_is_super_admin() ){
        return $sql;
    }
    global $wpdb;
    $current_user       = wp_get_current_user();
    $current_id         = $current_user->ID;
    $user_roles         = $current_user->roles;
    if( in_array( 'wpcargo_branch_manager', $user_roles ) ){ // wpcargo_branch_manager
        $sql .= " LEFT JOIN {$wpdb->postmeta} AS tblbranch ON tblposts.ID = tblbranch.post_id";
    }elseif( in_array( 'cargo_agent', $user_roles ) ){
        $sql .= " LEFT JOIN {$wpdb->postmeta} AS tblagent ON tblposts.ID = tblagent.post_id";
    }elseif( in_array( 'wpcargo_driver', $user_roles ) ){
        $sql .= " LEFT JOIN {$wpdb->postmeta} AS tbldriver ON tblposts.ID = tbldriver.post_id";
    }elseif( in_array( 'wpcargo_employee', $user_roles ) ){
        $sql .= " LEFT JOIN {$wpdb->postmeta} AS tblemployee ON tblposts.ID = tblemployee.post_id";
    }
    return $sql;
}
function wpcie_sql_users_filter_data( $sql ){
    if( wpcfe_is_super_admin() ){
        return $sql;
    }
    global $wpdb;
    $current_user       = wp_get_current_user();
    $current_id         = $current_user->ID;
    $user_roles         = $current_user->roles;
    if( in_array( 'wpcargo_branch_manager', $user_roles ) ){ // wpcargo_branch_manager
        $sql .= " AND tblbranch.meta_key LIKE %s AND tblbranch.meta_value = %d";
    }elseif( in_array( 'cargo_agent', $user_roles ) ){
        $sql .= " AND tblagent.meta_key LIKE %s AND tblagent.meta_value = %d";
    }elseif( in_array( 'wpcargo_driver', $user_roles ) ){
        $sql .= " AND tbldriver.meta_key LIKE %s AND tbldriver.meta_value = %d";
    }elseif( in_array( 'wpcargo_employee', $user_roles ) ){
        $sql .= " AND tblemployee.meta_key LIKE %s AND tblemployee.meta_value = %d";
    }
    return $sql;
}
function wpcie_sql_users_filter_parameter( $parameter ){
    if( wpcfe_is_super_admin() ){
        return $parameter;
    }
    global $wpdb;
    $current_user       = wp_get_current_user();
    $current_id         = $current_user->ID;
    $user_roles         = $current_user->roles;
    if( in_array( 'wpcargo_branch_manager', $user_roles ) ){ // wpcargo_branch_manager
        $branch_id   = wpcc_get_manager_branch( $current_id );
        $parameter[] = 'shipment_branch';
        $parameter[] = $branch_id;
    }elseif( in_array( 'cargo_agent', $user_roles ) ){
        $parameter[] = 'agent_fields';
        $parameter[] = $current_id;
    }elseif( in_array( 'wpcargo_driver', $user_roles ) ){
        $parameter[] = 'wpcargo_driver';
        $parameter[] = $current_id;
    }elseif( in_array( 'wpcargo_employee', $user_roles ) ){
        $parameter[] = 'wpcargo_employee';
        $parameter[] = $current_id;
    }
    return $parameter;
}
add_filter( 'wpcie_get_shipments_sql_filter_table', 'wpcie_sql_users_filter_table', 100 );
add_filter( 'wpcie_get_shipments_sql_filter_data', 'wpcie_sql_users_filter_data', 100 );
add_filter( 'wpcie_get_shipments_sql_filter_parameter', 'wpcie_sql_users_filter_parameter', 100 );
// Export Form fields hooks
function wpcie_frontend_category_form_field(){
    $category_list = wpcie_category_list();
    ?>
    <?php if( !empty( $category_list ) ): ?>
        <section class="form-group">
            <label for="tax_cat"><?php _e( 'Category', 'wpc-import-export' ); ?></label>
            <select name="tax_cat" class="form-control browser-default custom-select" id="tax_cat">
                <option value=""><?php _e('-- Category --', 'wpc-import-export' ); ?></option>
                <?php foreach( $category_list as $tax ): ?>
                    <option value="<?php  echo $tax->term_id; ?>" ><?php echo $tax->name; ?></option>
                <?php endforeach; ?>      
            </select>
        </section>
    <?php endif; ?>
    <?php
}
function wpcie_frontend_tag_form_field(){
    $tag_list = wpcie_tags_list();
    ?>
    <?php if( !empty( $tag_list ) ): ?>
        <section class="form-group">
            <label for="tax_tag"><?php _e( 'Tag', 'wpc-import-export' ); ?></label>
            <select name="tax_tag" class="form-control browser-default custom-select" id="tax_tag">
                <option value=""><?php _e('-- Tag --', 'wpc-import-export' ); ?></option>
                <?php foreach( $tag_list as $tax ): ?>
                    <option value="<?php  echo $tax->term_id; ?>" ><?php echo $tax->name; ?></option>
                <?php endforeach; ?>      
            </select>
        </section>
    <?php endif; ?>
    <?php
}
add_action( 'wpcie_frontend_middle_export_form_field', 'wpcie_frontend_category_form_field');
add_action( 'wpcie_frontend_middle_export_form_field', 'wpcie_frontend_tag_form_field');