<?php
if ( ! defined( 'WPINC' ) ) {
	die;
}
function wpcie_lang_import_export_menu(){
    return apply_filters( 'wpcie_lang_import_export_menu_label', __('Import/Export', 'wpc-import-export') );
}
function wpcie_lang_permission_denied(){
    return __('Permission denied!', 'wpc-import-export' );
}
function wpcie_lang_no_records(){
    return __('No record found.', 'wpc-import-export' );
}
function wpcie_lang_process_request(){
    return __('Processing request', 'wpc-import-export' );
}
function wpcie_lang_process_completed(){
    return __('Processing data for export completed. Please wait to downloading file.', 'wpc-import-export' );
}
function wpcie_lang_process_data( ){
    return __('Processing data', 'wpc-import-export' );
}
function wpcie_lang_record_count_note( ){
    return '<span class="record_count-notice">'.__( 'This process will take a while to complete due to number of records', 'wpc-import-export' ).'</span>';
}
function wpcie_lang_record_count( $record_count ){
    if( $record_count > 500 ){
        return sprintf(__( 'Preparing %d record(s)', 'wpc-import-export' ), $record_count ).'</br>'.wpcie_lang_record_count_note();
    }
    return sprintf(__( 'Preparing %d record(s)', 'wpc-import-export' ), $record_count );
}
function wpcie_lang_process_data_complete(){
    return __('Import data completed', 'wpc-import-export' );
}
function wpcie_lang_download_file(){
    return __('Please wait to downloading file.', 'wpc-import-export' );
}
function wpcie_lang_upload_file(){
    return __('Uploading file.', 'wpc-import-export' );
}
function wpcie_lang_daterange_required(){
    return __('Date range required.', 'wpc-import-export' );
}
function wpcie_registered_shipper_id_label(){
	return __( 'Assigned Client ID', 'wpc-import-export' );
}
function wpcie_shipment_category_id_label(){
	return __( 'Shipment Category ID', 'wpc-import-export' );
}
function wpcie_shipment_number_label(){
	return esc_html__( 'Shipment Number', 'wpc-import-export' );
}
function wpcie_file_extention_error_label(){
	return esc_html__( 'File type is not allowed, required .CSV file format.', 'wpc-import-export' );
}
function wpcie_file_header_error_label(){
	return esc_html__( 'Error file header is required for data mapping. Please download import template.', 'wpc-import-export' );
}
function wpcie_file_meta_error_label(){
	return esc_html__( 'Error file data to process, please check file header', 'wpc-import-export' );
}
function wpcie_import_save_shipment_label( $shipment_number ){
	return sprintf( '%s %s %s', __( 'Shipment number', 'wpc-import-export' ), $shipment_number,  __( 'successfull saved', 'wpc-import-export' ) );
}
function wpcie_no_import_page_message( ){
	return sprintf( __( 'Error:  No Import/Export page found for WPCargo Frontend Manager dashbaord. Please create page with a shortcode %s and set the template to WPCargo Dashbaord', 'wpc-import-export' ), '<strong>[wpcie_import_export]</strong>' );
}
function wpcie_field_license_required_message(){
    return __( 'WPCargo Import and Export Add-ons License key is required.', 'wpc-import-export' ).' '.sprintf( '<a class="button button-small" href="'.admin_url( 'admin.php?page=wptaskforce-helper' ).'">%s</a>', __( 'Activate License Key', 'wpc-import-export' ) );
}