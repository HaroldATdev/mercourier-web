<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

add_action( 'wp_ajax_wpcfe_delete_shipment', 'wpcfe_delete_shipment_callback' );
// add_action( 'wp_ajax_nopriv_wpcfe_delete_shipment', 'wpcfe_delete_shipment_callback' );
function wpcfe_delete_shipment_callback(){
	global $wpdb;
	$shipment_id 	= (int)$_POST['shipmentID'];
	$shipment_title = get_the_title( $shipment_id );
	$message 	 = array(
		'status' => 'warning',
		'icon'	 => 'ti-alert',
		'message' => esc_html__( 'Something went wrong during process, Please try again.', 'wpcargo-frontend-manager' )
	);

	if( !can_wpcfe_delete_shipment() ){
		echo json_encode( array(
			'status' => 'danger',
			'icon'	 => 'ti-alert',
			'message' => __('Opsss! Sorry you are not allow to delete shipments', 'wpcargo-frontend-manager')
		)  );
		wp_die();
	}

	if( is_wpcfe_shipment( $shipment_id ) && can_wpcfe_delete_shipment() && is_user_shipment( $shipment_id ) ){
		$delete_post = wp_trash_post( $shipment_id, false );
		if( $delete_post ){
			$message 	 = array(
				'status' => 'success',
				'icon'	 => 'ti-check',
				'message' => esc_html__( 'Shipment', 'wpcargo-frontend-manager' ).' '.$shipment_title.' '.esc_html__( 'successfully deleted.', 'wpcargo-frontend-manager' )
			);
		}
	}
	echo json_encode( $message  );
	wp_die();
}
add_action( 'wp_ajax_bulk_assign_shipment', 'bulk_assign_shipment_callback' );
// add_action( 'wp_ajax_nopriv_bulk_assign_shipment', 'bulk_assign_shipment_callback' );
function bulk_assign_shipment_callback(){
	global $wpdb;
	$shipment_ids 	= $_POST['updateShipmentID'];
	$updateFields 	= $_POST['updateFields'];
	$shipments 		= array();
	if( !empty( $shipment_ids ) ){
		foreach ($shipment_ids as $shipment_id ) {
			if( empty( $updateFields ) ){
				break;
			}
			foreach ( $updateFields as $field ) {
				if( empty( $field['value'] ) || empty( $field['name'] ) ){
					continue;
				}
				$value 		= sanitize_text_field( $field['value'] );
				$metakey 	= sanitize_text_field( $field['name'] );
				update_post_meta( $shipment_id, $metakey, $value );
				$shipments[] = get_the_title( $shipment_id );
			}
		}
	}
	do_action( 'wpcfe_after_save_bulk_assign_shipment', $shipment_ids, $_POST );
	echo json_encode( array_unique($shipments) );
	wp_die();
}
add_action( 'wp_ajax_wpcfe_bulk_delete', 'wpcfe_bulk_delete_callback' );
// add_action( 'wp_ajax_nopriv_wpcfe_bulk_delete', 'wpcfe_bulk_delete_callback' );
function wpcfe_bulk_delete_callback(){
	global $wpdb;
	if( !can_wpcfe_delete_shipment() ){
		wp_send_json( array(
			'status' => 'error',
			'message' => __('Opsss! Sorry you are not allow to delete shipments', 'wpcargo-frontend-manager')
		) );
		wp_die();
	}
	$shipment_ids 	= $_POST['selectedShipment'];
	$message		= array();
	$counter 		= 0;
	foreach( $shipment_ids as $shipment_id ){
		$shipment_title = get_the_title( $shipment_id );
		if( is_wpcfe_shipment( $shipment_id ) && can_wpcfe_delete_shipment() && is_user_shipment( $shipment_id ) ){
			$delete_post = wp_trash_post( $shipment_id, false );
			$counter++;
		}
	}
	wp_send_json( array(
		'status' => 'success',
		'message' => sprintf( _n( 'You successfully deleted %s shipment.', 'You successfully deleted %s shipments.', $counter, 'wpcargo-frontend-manager' ), number_format_i18n( $counter ) )
	) );
	wp_die();
}
add_action( 'wp_ajax_wpcfe_get_option', 'wpcfe_get_option_callback' );
add_action( 'wp_ajax_nopriv_wpcfe_get_option', 'wpcfe_get_option_callback' );
function wpcfe_get_option_callback(){
	$filter 		= $_REQUEST['filter'];
	$param 			= $_REQUEST['q'];
    $filter_key     = wpcfe_table_header($filter);  
	$options 		= wpcfe_get_meta_values( $filter_key['field_key'], $param );
	echo json_encode( $options );
	wp_die();
}
add_action( 'wp_ajax_wpcfe_upload_avatar', 'wpcfe_upload_avatar_callback' );

function wpcfe_upload_avatar_callback(){
	error_log('🚀 CALLBACK INICIADO - wpcfe_upload_avatar_callback');
	
	// Asegurar que DOING_AJAX está definida para excepto del acceso control
	if ( !defined('DOING_AJAX') ) {
		define('DOING_AJAX', true);
	}
	
	// Limpiar cualquier output anterior
	@ob_end_clean();
	
	// Verificar que el usuario está logueado
	if ( !is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => 'User not authenticated' ) );
	}
	
	// Validar nonce
	if ( !isset( $_POST['nonce'] ) || !wp_verify_nonce( $_POST['nonce'], 'wpcfe_upload_avatar_action' ) ) {
		wp_send_json_error( array( 'message' => 'Security verification failed' ) );
	}
	
	// Validar campo de imagen
	if( empty( $_POST['imageData'] ) ){
		wp_send_json_error( array( 'message' => 'No image data provided' ) );
	}
	
	// Obtener directorio de cargas
	$upload_dir = wp_upload_dir();
	if( isset( $upload_dir['error'] ) && $upload_dir['error'] ){
		wp_send_json_error( array( 'message' => 'Upload directory error' ) );
	}
	
	// Procesar imagen base64
	$img_data = sanitize_text_field( $_POST['imageData'] );
	$img_data = str_replace('data:image/png;base64,', '', $img_data);
	$img_data = str_replace('data:image/jpeg;base64,', '', $img_data);
	$img_data = str_replace(' ', '+', $img_data);
	
	$decoded = base64_decode( $img_data, true );
	if( false === $decoded ){
		wp_send_json_error( array( 'message' => 'Failed to decode image data' ) );
	}
	
	// Crear nombre de archivo
	$user_id = get_current_user_id();
	if( !$user_id ){
		wp_send_json_error( array( 'message' => 'User not authenticated' ) );
	}
	
	$filename = $user_id . '.png';
	$hashed_filename = md5( $filename . microtime() ) . '_' . $filename;
	$upload_path = trailingslashit( $upload_dir['path'] );
	
	// Guardar archivo
	$saved = file_put_contents( $upload_path . $hashed_filename, $decoded );
	if( false === $saved ){
		wp_send_json_error( array( 'message' => 'Failed to save image file' ) );
	}
	
	// Requieres funciones necesarias
	if( !function_exists( 'wp_handle_sideload' ) ) {
		require_once( ABSPATH . 'wp-admin/includes/file.php' );
	}
	
	// Preparar archivo para sideload
	$file = array(
		'error'    => '',
		'tmp_name' => $upload_path . $hashed_filename,
		'name'     => $hashed_filename,
		'type'     => 'image/png',
		'size'     => filesize( $upload_path . $hashed_filename )
	);
	
	// Realizar sideload
	$file_return = wp_handle_sideload( $file, array( 'test_form' => false ) );
	
	if( isset( $file_return['error'] ) && $file_return['error'] ){
		wp_send_json_error( array( 'message' => $file_return['error'] ) );
	}
	
	if( !isset( $file_return['file'] ) || empty( $file_return['file'] ) ){
		wp_send_json_error( array( 'message' => 'File upload failed without error message' ) );
	}
	
	// Crear attachment
	$attachment_file = $file_return['file'];
	$attachment_args = array(
		'post_mime_type' => $file_return['type'],
		'post_title'     => sanitize_file_name( $user_id . '-avatar' ),
		'post_content'   => '',
		'post_status'    => 'inherit'
	);
	
	$attach_id = wp_insert_attachment( $attachment_args, $attachment_file );
	
	if( is_wp_error( $attach_id ) || !$attach_id ){
		wp_send_json_error( array( 'message' => 'Failed to create attachment' ) );
	}
	
	// Generar metadata
	if( file_exists( ABSPATH . 'wp-admin/includes/image.php' ) ){
		require_once( ABSPATH . 'wp-admin/includes/image.php' );
		$attach_data = wp_generate_attachment_metadata( $attach_id, $attachment_file );
		wp_update_attachment_metadata( $attach_id, $attach_data );
	}
	
	// Obtener URL del avatar
	$avatar_url = wp_get_attachment_url( $attach_id );
	if( !$avatar_url ){
		wp_send_json_error( array( 'message' => 'Could not retrieve attachment URL' ) );
	}
	
	// Guardar en meta del usuario
	update_user_meta( $user_id, 'wpcargo_user_avatar', $avatar_url );
	
	error_log('✅ A PUNTO DE ENVIAR RESPUESTA EXITOSA - Avatar URL: ' . $avatar_url);
	
	// Respuesta exitosa
	wp_send_json_success( array(
		'avatar_url' => $avatar_url,
		'attachment_id' => $attach_id,
		'message' => 'Avatar uploaded successfully'
	) );
}
add_filter ('get_avatar', 'wpcfe_override_avatar', 10, 6 );
function wpcfe_override_avatar ($avatar_html, $userid, $size, $default, $alt, $args ) {
	if( !is_admin() ){
		$size = '128';
	}
	$wpcdm_user_avatar = get_user_meta( $userid, 'wpcargo_user_avatar', true );
	if( $wpcdm_user_avatar ){
		$avatar_html = '<img alt="" src="'.$wpcdm_user_avatar.'" srcset="'.$wpcdm_user_avatar.'" class="avatar avatar-'.$size.' photo photo-inner" height="'.$size.'" width="'.$size.'">';
	}
   	return $avatar_html;
}
// Registration AJAX
add_action( 'wp_ajax_wpcfe_check_email', 'wpcfe_check_email_callback' );
add_action( 'wp_ajax_nopriv_wpcfe_check_email', 'wpcfe_check_email_callback' );
function wpcfe_check_email_callback(){
    $email 	= sanitize_text_field( $_POST['email'] );
	$result = email_exists( $email );
	echo $result;
    wp_die();
}
add_action( 'wp_ajax_wpcfe_shipment_title_checker', 'wpcfe_shipment_title_checker_callback' );
add_action( 'wp_ajax_nopriv_wpcfe_shipment_title_checker', 'wpcfe_shipment_title_checker_callback' );
function wpcfe_shipment_title_checker_callback(){
	global $wpdb;
	$shipment_title   	= $_POST['shipment_title'];
	$sql 				= "SELECT count(*) FROM `{$wpdb->prefix}posts` WHERE `post_status` LIKE 'publish' 
	AND `post_type` LIKE 'wpcargo_shipment' AND `post_title` LIKE %s";
	$result = $wpdb->get_var( $wpdb->prepare( $sql, $shipment_title ) );
	if( $result > 0 ){
		?>
		<div class="title-checker alert alert-danger"><span class="existing"><?php esc_html_e( 'This is an existing shipment number.', 'wpcargo-frontend-manager' ); ?></span></div>
		<?php
	}
    wp_die( );
}
// Reset sequence number
add_action( 'wp_ajax_reset_nsequence', 'wpcfe_reset_nsequence_callback' );
function wpcfe_reset_nsequence_callback(){
	$return = delete_option( '_nsequence_started' );
	if( $return || !get_option( '_nsequence_started' ) ){
		update_option( 'wpcfe_nsequence_start', 0 );
		wp_send_json( array(
			'status' => 'success',
			'message' => __('N Sequence reset success, please update the Shipment Number Start for your new shipment number starting sequence.', 'wpcargo-frontend-manager' )
		) );
		wp_die();
	}
	wp_send_json( array(
		'status' => 'error',
		'message' => __('N Sequence reset failed, please reload the page and try again.', 'wpcargo-frontend-manager' )
	) );
	wp_die();
}

