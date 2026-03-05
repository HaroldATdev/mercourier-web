<?php
if ( ! defined( 'WPINC' ) ) {
	die;
}
function wpcie_get_all_users( $role = null ){
	global $wpdb;
	$users = array();
	$sql = "SELECT tbluser.ID AS ID,";
	$sql .= " CASE";
	$sql .= " WHEN  tblfname.meta_value = '' AND tbllname.meta_value = ''";
	$sql .= " THEN tbluser.user_nicename";
	$sql .= " ELSE CONCAT( tblfname.meta_value, ' ', tbllname.meta_value)";
	$sql .= " END";
	$sql .= " AS fullname";
	$sql .= " FROM {$wpdb->users} AS tbluser";
	$sql .= " INNER JOIN {$wpdb->usermeta} AS tblfname ON tbluser.ID = tblfname.user_id";
	$sql .= " INNER JOIN {$wpdb->usermeta} AS tbllname ON tbluser.ID = tbllname.user_id";
	if( $role ){
		$sql .= " INNER JOIN {$wpdb->usermeta} AS tblcap ON tbluser.ID = tblcap.user_id";
	}
	$sql .= " WHERE tblfname.meta_key LIKE 'first_name'";
	$sql .= " AND tbllname.meta_key LIKE 'last_name'";
	if( $role ){
		$sql .= " AND tblcap.meta_key LIKE 'wp_capabilities'";
		$sql .= " AND tblcap.meta_value LIKE %s";
	}
	$sql .= " ORDER BY tblfname.meta_value";
	if( $role ){
		$sql = $wpdb->prepare( $sql, '%'.$role.'%' );
	}
	$result = $wpdb->get_results( $sql, ARRAY_A );
	if( !empty( $result ) ){
		foreach ($result as $data ) {
			$users[$data['ID']] = $data['fullname'];
		}
	}
	return $users;
}
function wpcie_get_shipment_id( $shipment_number ){
	global $wpdb;
	$sql = "SELECT tblposts.ID";
    $sql .= " FROM {$wpdb->posts} AS tblposts";
	$sql .= " WHERE tblposts.post_status LIKE 'publish' AND tblposts.post_type LIKE 'wpcargo_shipment'";  
	$sql .= " AND tblposts.post_title LIKE %s LIMIT 1";
	return $wpdb->get_var( $wpdb->prepare( $sql, $shipment_number ) );
}
function wpcie_get_shipments( $data ){
    global $wpdb;
    $search_data    = wpcie_search_shipper_meta();
	$term_taxonomy  = array();
    $parameter      = array();

	if( array_key_exists( 'tax_cat', $data) && (int)$data['tax_cat'] ){
		$term_taxonomy[] = (int)$data['tax_cat']; 
	}
	if( array_key_exists( 'tax_tag', $data) && (int)$data['tax_tag'] ){
		$term_taxonomy[] = (int)$data['tax_tag']; 
	}

	$term_taxonomy = apply_filters( 'term_taxonomy_values', $term_taxonomy, $data );

    $sql = "SELECT tblposts.ID, tblposts.post_title";
    $sql .= " FROM {$wpdb->posts} AS tblposts";   
    // Check Registered Shipper
    if( array_key_exists( 'registered_shipper', $data) && (int)$data['registered_shipper'] ){
        $sql .= " LEFT JOIN {$wpdb->postmeta} AS tblshipper ON tblposts.ID = tblshipper.post_id";
    }
    // Shipment Status
    if( array_key_exists( 'wpcargo_status', $data) && !empty($data['wpcargo_status']) ){
        $sql .= " LEFT JOIN {$wpdb->postmeta} AS tblstatus ON tblposts.ID = tblstatus.post_id";
    }
    // Searched Name - Set From Custom Field add on
    if( array_key_exists( $search_data['metakey'], $data) && !empty( $data[ $search_data['metakey'] ] ) ){
        $sql .= " LEFT JOIN {$wpdb->postmeta} AS tblsearch ON tblposts.ID = tblsearch.post_id";
    }
    // Category - wpcargo_shipment_cat
    if( $term_taxonomy ){
        $sql .= " LEFT JOIN {$wpdb->term_relationships} AS tblterm ON tblposts.ID = tblterm.object_id";
    }
	$sql  = apply_filters( 'wpcie_get_shipments_sql_filter_table', $sql, $data );
    $sql .= " WHERE tblposts.post_status LIKE 'publish' AND tblposts.post_type LIKE 'wpcargo_shipment'";
    if( array_key_exists( 'date-from', $data) && $data['date-from'] && array_key_exists( 'date-to', $data) && $data['date-to'] ){
        $parameter[] = $data['date-from'] ;
        $parameter[] = $data['date-to'] . ' 23:59:29';
        $sql .= " AND tblposts.post_date BETWEEN %s AND %s";
    }
    // Check Registered Shipper
    if( array_key_exists( 'registered_shipper', $data) && (int)$data['registered_shipper'] ){
        $parameter[] = 'registered_shipper' ;
        $parameter[] = (int)$data['registered_shipper'];
        $sql .= " AND tblshipper.meta_key LIKE %s AND tblshipper.meta_value = %d";
    }
    // Shipment Status
    if( array_key_exists( 'wpcargo_status', $data) && !empty($data['wpcargo_status']) ){
        $parameter[] = 'wpcargo_status' ;
        $parameter[] = $data['wpcargo_status'];
        $sql .= " AND tblstatus.meta_key LIKE %s AND tblstatus.meta_value LIKE %s";
    }
    // Searched Name - Set From Custom Field add on
    if( array_key_exists( $search_data['metakey'], $data) && !empty( $data[ $search_data['metakey'] ] ) ){
        $parameter[] = $search_data['metakey'];
        $parameter[] = $data[ $search_data['metakey'] ];
        $sql .= " AND tblsearch.meta_key LIKE %s AND tblsearch.meta_value LIKE %s";
    }
    // Category - wpcargo_shipment_cat
    if( !empty( $term_taxonomy ) ){
        $parameter[] = implode(",",$term_taxonomy);
        $sql .= " AND tblterm.term_taxonomy_id IN ( %s )";
    }
	$sql  		= apply_filters( 'wpcie_get_shipments_sql_filter_data', $sql, $data );
	$parameter  = apply_filters( 'wpcie_get_shipments_sql_filter_parameter', $parameter, $data );
    $sql .= " GROUP BY tblposts.ID";
    $sql .= " ORDER BY tblposts.post_date DESC";
    $sql = apply_filters( 'wpcie_get_shipments_sql', $wpdb->prepare( $sql, $parameter ), $parameter );
    return $wpdb->get_results( $sql, ARRAY_A );
}
function wpcie_get_frontend_page(){
	global $wpdb;
	$sql  = "SELECT `ID` FROM {$wpdb->prefix}posts WHERE `post_content` LIKE '%[wpcie_import_export]%' AND `post_status` LIKE 'publish' LIMIT 1";
	return $wpdb->get_var( $sql );
}
function wpcie_email_notification(){
	return get_option( 'wpcie_email_notification' ) ? true : false ;
}
function wpcie_default_status(){
	$status = get_option( 'wpcfe_default_status' ) ? get_option( 'wpcfe_default_status' ) : __( 'Pending', 'wpc-import-export' ) ;
	return apply_filters( 'wpcie_default_status', $status );
}
function wpcie_search_shipper_meta(){
	$meta   = get_option('shipper_column');
	if( !$meta || !function_exists('wpccf_get_field_by_metakey') ){
		return array(
			'metakey' 	=> 'wpcargo_shipper_name',
			'label' 	=> __('Shipper Name', 'wpc-import-export' )
		);
	}
	$field_info 	= wpccf_get_field_by_metakey( $meta );
	return array(
		'metakey' 	=> $meta,
		'label' 	=> $field_info['label']
	);
}
function wpcie_category_list(){
	$category_list = get_categories( array(
		'taxonomy' 	=> 'wpcargo_shipment_cat',
		'orderby' 	=> 'name',
		'order'   	=> 'ASC',
		'hide_empty' => true,
	) );
	return apply_filters( 'wpcie_category_list', $category_list );
}
function wpcie_tags_list(){
	$tags_list = get_categories( array(
		'taxonomy' 	=> 'post_tag',
		'orderby' 	=> 'name',
		'order'   	=> 'ASC',
		'hide_empty' => true,
	) );
	return apply_filters( 'wpcie_tags_list', $tags_list );
}
function wpcie_get_string_between($string, $start, $end){
    $string = ' ' . $string;
    $ini = strrpos($string, $start);
    if ($ini == 0) return '';
    $ini += strlen($start);
    $len = strrpos($string, $end, $ini) - $ini;
    return substr($string, $ini, $len);
}
function wpcie_upload_errors(){
	$phpFileUploadErrors 	= array(
		0 => __('There is no error, the file uploaded with success.', 'wpc-import-export' ),
		1 => __('The uploaded file exceeds the upload_max_filesize directive in php.ini.', 'wpc-import-export' ),
		2 => __('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.', 'wpc-import-export' ),
		3 => __('The uploaded file was only partially uploaded.', 'wpc-import-export' ),
		4 => __('No file was uploaded.', 'wpc-import-export' ),
		6 => __('Missing a temporary folder.', 'wpc-import-export' ),
		7 => __('Failed to write file to disk.', 'wpc-import-export' ),
		8 => __('A PHP extension stopped the file upload.', 'wpc-import-export' ),
	);
	return $phpFileUploadErrors;
}
function wpcie_export_file_format_list(){
	$extension = array(
		'xls' => ",", 
		'xlt' => ",", 
		'xla' => ",", 
		'xlw' => ",",
		'csv' => ","
	);
	return apply_filters( 'wpcie_export_file_format_list', $extension );
}
function wpcie_clean_dir( $directory ){
	$files = glob( $directory.'*'); // get all file names
	foreach($files as $file){ // iterate files
	if(is_file($file))
		unlink($file); // delete file
	}
}
function wpcie_is_client(){
	$current_user = wp_get_current_user();
	$roles 		  =  $current_user->roles;
	if( in_array( 'wpcargo_client', $roles ) ){
		return true;
	}
	return false;
}
function wpcie_is_employee(){
	$current_user = wp_get_current_user();
	$roles 		  =  $current_user->roles;
	if( in_array( 'wpcargo_employee', $roles ) ){
		return true;
	}
	return false;
}
function wpcie_is_manager(){
	$current_user = wp_get_current_user();
	$roles 		  =  $current_user->roles;
	if( in_array( 'wpcargo_branch_manager', $roles ) ){
		return true;
	}
	return false;
}
function wpcie_is_agent(){
	$current_user = wp_get_current_user();
	$roles 		  =  $current_user->roles;
	if( in_array( 'cargo_agent', $roles ) ){
		return true;
	}
	return false;
}
function wpcie_is_driver(){
	$current_user = wp_get_current_user();
	$roles 		  =  $current_user->roles;
	if( in_array( 'wpcargo_driver', $roles ) ){
		return true;
	}
	return false;
}
function wpcie_restricted_role(){
	return get_option( 'wpcie_restricted_role' ) ? get_option( 'wpcie_restricted_role' ) : array() ;
}
function is_wpcie_restricted_role(){
	$current_user = wp_get_current_user();
	$status = false;
	if( array_intersect( $current_user->roles, wpcie_restricted_role() ) ){
		$status = true;
	}
	return apply_filters( 'is_wpcie_restricted_role', $status );
}
function wpcie_disable(){
	return get_option( 'wpcie_disable' ) ? true : false ;
}
function can_wpcie_import(){
	return apply_filters( 'can_wpcie_import', can_wpcfe_add_shipment() );
}
function can_wpcie_export(){
	$current_user = wp_get_current_user();
	$status = true;
	if( array_intersect( $current_user->roles, wpcie_restricted_role() ) ){
		$status = false;
	}
	return apply_filters( 'can_wpcie_export', $status );
}
function wpcie_include_template( $file_name ){
    $file_slug              = strtolower( preg_replace('/\s+/', '_', trim( str_replace( '.tpl', '', $file_name ) ) ) );
    $file_slug              = preg_replace('/[^A-Za-z0-9_]/', '_', $file_slug );
    $custom_template_path   = get_stylesheet_directory().'/wpcargo/wpcargo-import-export/'.$file_name.'.php';
    if( file_exists( $custom_template_path ) ){
        $template_path = $custom_template_path;
    }else{
        $template_path  = WPC_IMPORT_EXPORT_PATH.'templates/'.$file_name.'.php';
        $template_path  = apply_filters( "wpc_ie_locate_template_{$file_slug}", $template_path );
    }
	return $template_path; 
}
function wpcie_admin_include_template( $file_name ){
    $file_slug              = strtolower( preg_replace('/\s+/', '_', trim( str_replace( '.tpl', '', $file_name ) ) ) );
    $file_slug              = preg_replace('/[^A-Za-z0-9_]/', '_', $file_slug );
    $custom_template_path   = get_stylesheet_directory().'/wpcargo/wpcargo-import-export/admin/'.$file_name.'.php';
    if( file_exists( $custom_template_path ) ){
        $template_path = $custom_template_path;
    }else{
        $template_path  = WPC_IMPORT_EXPORT_PATH.'admin/templates/'.$file_name.'.php';
        $template_path  = apply_filters( "wpc_ie_locate_admin_template_{$file_slug}", $template_path );
    }
	return $template_path; 
}
function wpcie_package_key_value_pair(){
	$pairs = [];
	if( !empty( wpcargo_package_fields() ) ){
		foreach( wpcargo_package_fields() as $key => $value ){
			$pairs[] = array(
				'key' => $key,
				'label' => $value['label']
			);
		}
	}
	return $pairs;
}
function wpcie_history_key_value_pair(){
	$pairs = [];
	if( !empty( wpcargo_history_fields() ) ){
		foreach( wpcargo_history_fields() as $key => $value ){
			$pairs[] = array(
				'key' => $key,
				'label' => $value['label']
			);
		}
	}
	return $pairs;
}
function wpcie_registered_form_fields(){
	$fields = array(
		array(
			'meta_key' 	=> 'wpcargo_shipper_name',
			'label' 	=> esc_html__( 'Shipper Name', 'wpc-import-export' ),
			'fields' 	=> array()
		),
		array(
			'meta_key' 	=> 'wpcargo_shipper_phone',
			'label' 	=> esc_html__( 'Shipper Phone Number', 'wpc-import-export' ),
			'fields' 	=> array()
		),
		array(
			'meta_key' 	=> 'wpcargo_shipper_address',
			'label' 	=> esc_html__( 'Shipper Address', 'wpc-import-export' ),
			'fields' 	=> array()
		),
		array(
			'meta_key' 	=> 'wpcargo_shipper_email',
			'label' 	=> esc_html__( 'Shipper Email', 'wpc-import-export' ),
			'fields' 	=> array()
		),
		array(
			'meta_key' 	=> 'wpcargo_receiver_name',
			'label' 	=> esc_html__( 'Receiver Name', 'wpc-import-export' ),
			'fields' 	=> array()
		),
		array(
			'meta_key' 	=> 'wpcargo_receiver_phone',
			'label' 	=> esc_html__( 'Receiver Phone Number', 'wpc-import-export' ),
			'fields' 	=> array()
		),
		array(
			'meta_key' 	=> 'wpcargo_receiver_address',
			'label' 	=> esc_html__( 'Receiver Address', 'wpc-import-export' ),
			'fields' 	=> array()
		),
		array(
			'meta_key' 	=> 'wpcargo_receiver_email',
			'label' 	=> esc_html__( 'Receiver Email', 'wpc-import-export' ),
			'fields' 	=> array()
		),
		array(
			'meta_key' 	=> 'agent_fields',
			'label' 	=> esc_html__( 'Agent Name', 'wpc-import-export' ),
			'fields' 	=> array()
		),
		array(
			'meta_key' 	=> 'wpcargo_type_of_shipment',
			'label' 	=> esc_html__( 'Type of Shipment', 'wpc-import-export' ),
			'fields' 	=> array()
		),
		array(
			'meta_key' 	=> 'wpcargo_courier',
			'label' 	=> esc_html__( 'Courier', 'wpc-import-export' ),
			'fields' 	=> array()
		),
		array(
			'meta_key' 	=> 'wpcargo_mode_field',
			'label' 	=> esc_html__( 'Mode', 'wpc-import-export' ),
			'fields' 	=> array()
		),
		array(
			'meta_key' 	=> 'wpcargo_qty',
			'label' 	=> esc_html__( 'Quantity', 'wpc-import-export' ),
			'fields' 	=> array()
		),
		array(
			'meta_key' 	=> 'wpcargo_total_freight',
			'label' 	=> esc_html__( 'Total Freight', 'wpc-import-export' ),
			'fields' 	=> array()
		),
		array(
			'meta_key' 	=> 'wpcargo_carrier_ref_number',
			'label' 	=> esc_html__( 'Carrier Reference No.', 'wpc-import-export' ),
			'fields' 	=> array()
		),
		array(
			'meta_key' 	=> 'wpcargo_origin_field',
			'label' 	=> esc_html__( 'Origin', 'wpc-import-export' ),
			'fields' 	=> array()
		),
		array(
			'meta_key' 	=> 'wpcargo_pickup_date_picker',
			'label' 	=> esc_html__( 'Pickup Date', 'wpc-import-export' ),
			'fields' 	=> array()
		),
		array(
			'meta_key' 	=> 'wpcargo_comments',
			'label' 	=> esc_html__( 'Comments', 'wpc-import-export' ),
			'fields' 	=> array()
		),
		array(
			'meta_key' 	=> 'wpcargo_weight',
			'label' 	=> esc_html__( 'Weight', 'wpc-import-export' ),
			'fields' 	=> array()
		),
		array(
			'meta_key' 	=> 'wpcargo_packages',
			'label' 	=> esc_html__( 'Packages', 'wpc-import-export' ),
			'fields' 	=> array()
		),
		array(
			'meta_key' 	=> 'wpcargo_product',
			'label' 	=> esc_html__( 'Product', 'wpc-import-export' ),
			'fields' 	=> array()
		),
		array(
			'meta_key' 	=> 'payment_wpcargo_mode_field',
			'label' 	=> esc_html__( 'Payment Mode', 'wpc-import-export' ),
			'fields' 	=> array()
		),
		array(
			'meta_key' 	=> 'wpcargo_carrier_field',
			'label' 	=> esc_html__( 'Carrier', 'wpc-import-export' ),
			'fields' 	=> array()
		),
		array(
			'meta_key' 	=> 'wpcargo_departure_time_picker',
			'label' 	=> esc_html__( 'Departure Time', 'wpc-import-export' ),
			'fields' 	=> array()
		),
		array(
			'meta_key' 	=> 'wpcargo_destination',
			'label' 	=> esc_html__( 'Destination', 'wpc-import-export' ),
			'fields' 	=> array()
		),
		array(
			'meta_key' 	=> 'wpcargo_pickup_time_picker',
			'label' 	=> esc_html__( 'Pickup Time', 'wpc-import-export' ),
			'fields' 	=> array()
		),
		array(
			'meta_key' 	=> 'wpcargo_expected_delivery_date_picker',
			'label' 	=> esc_html__( 'Expected Delivery Date', 'wpc-import-export' ),
			'fields' 	=> array()
		)
	);
	$addition_fields = array(
		array(
			'meta_key' 	=> 'wpc-multiple-package',
			'label' 	=> esc_html__( 'Package Details', 'wpc-import-export' ),
			'fields' 	=> wpcie_package_key_value_pair()
		),
		array(
			'meta_key' 	=> 'wpcargo_shipments_update',
			'label' 	=> esc_html__( 'Shipment History', 'wpc-import-export' ),
			'fields' 	=> wpcie_history_key_value_pair()
		),
		array(
			'meta_key' 	=> 'wpcargo_status',
			'label' 	=> esc_html__( 'Shipment Status', 'wpc-import-export' ),
			'fields' 	=> array()
		),
	);
	$form_fields = apply_filters( 'ie_registered_fields', $fields );
	/*
	 * Merge the meta fields to the shipment history and multiple packages fields
	 */
	$form_fields = array_merge( $form_fields, $addition_fields );
	foreach ( array_reverse( wpcie_default_headers() ) as $value ) {
		array_unshift($form_fields, $value );
	}
	return $form_fields;
}
function wpcie_registered_headers(){	
	$headers = [];
	if( !empty( wpcie_registered_form_fields() ) ){
		foreach ( wpcie_registered_form_fields() as $fields ) {
			$headers[$fields['meta_key']] = $fields['label'];
		}
	}
	return $headers;
}
function wpcie_default_headers(){
	$default_headers = array(
		array(
			'meta_key' 	=> 'shipment_id',
			'label' 	=> __( 'ShipmentID', 'wpc-import-export' ),
			'fields' 	=> array()
		),
		array(
			'meta_key' 	=> 'shipment_title',
			'label' 	=> __( 'Shipment Title', 'wpc-import-export' ),
			'fields' 	=> array()
		),
		array(
			'meta_key' 	=> 'wpcargo_shipment_cat',
			'label' 	=> __( 'Shipment Category ID', 'wpc-import-export' ),
			'fields' 	=> array()
		),
		array(
			'meta_key' 	=> 'registered_shipper',
			'label' 	=> __( 'Assigned Client ID', 'wpc-import-export' ),
			'fields' 	=> array()
		)
	);
	return $default_headers;
}
function wpcie_get_headers(){
	return array_map( 'trim',  array_values( wpcie_registered_headers() ) );
}
function wpcie_get_meta_keys(){
	return array_map( 'trim',  array_keys( wpcie_registered_headers() ) );
}
function wpcie_get_key_value_pairs(){
	return wpcie_registered_headers();
}
function wpcie_csv_template_headers(){
	$selected_headers = get_option( 'multiselect_settings', wpcie_registered_headers() );
	if( empty( $selected_headers ) ){
		return false;
	}
	$headers = array();
	foreach ( $selected_headers as $key => $value) {
		// Exclude the file uploaded data 
		if( !in_array( $key, wpcie_registered_headers() ) ){
			continue;
		}
		if( wpcie_check_field_type( $key, 'file' ) ){
			continue;
		}
		$headers[$key] = $value.' ('.$key.')';
	}
	return apply_filters( 'wpcie_csv_template_headers', $headers );
}
function wpcie_check_field_type( $meta_key, $type ){
	if( !function_exists('wpccf_get_field_by_metakey') ){
		return false;
	}
	$meta_info = wpccf_get_field_by_metakey( $meta_key );
	if( !empty($meta_info) && array_key_exists('field_type', $meta_info ) && $meta_info['field_type'] === $type ){
		return true;
	}
	return false;
}
function wpcie_check_header( $custom_header, $default_header ){
	$result = false;
	if( !empty( $custom_header ) ){
		foreach ( $custom_header as $value ) {
			if( !in_array( $value, $default_header ) ){
				$result = false;
				break;
			}
			$result = true;
		}
	}
	return $result;
}
function wpcie_prepare_import_multidem_data( $data, $map_fields ){
	$formatted_data = array();
	$multidem_data 	= array();
	$data_counter 	= 0;
	$multidem_counter = 0;
	// Format passed string data to array 
	foreach ( $data as $row ) {
		$arr_row = explode( '|', $row );
		foreach ( $arr_row as $value) {
			$arr_value = array_map( 'trim', explode( '=', $value ) );
			if( count( $arr_value ) < 2 ){
				continue;
			}
			$formatted_data[$data_counter][$arr_value[0]] = $arr_value[1];
		}
		$data_counter++;
	}
	// Mapped data to multi deminsion array field
	foreach ($formatted_data as $data_row ) {
		$group_array = array();
		foreach( $map_fields as $label => $metakey ){
			if( !array_key_exists(  $label, $data_row ) ){
				$group_array[$metakey] = '';
				continue;
			}
			$group_array[$metakey] = $data_row[$label];
		}
		$multidem_data[$multidem_counter] = $group_array;
		$multidem_counter ++;
	}
	return $multidem_data;
}