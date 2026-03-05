<?php
if ( ! defined( 'WPINC' ) ) {
	die;
}
function wpcargo_import_export_scripts(){
    global $post;
    if( !$post ){
        return false;
    }
    // Make sure to run the script if "wpcie_import_export" exist in the page content
    if( !is_a( $post, 'WP_Post' ) || !has_shortcode( $post->post_content, 'wpcie_import_export') ){
        return false;
    }

    // Register Script
    wp_register_script( 'wpc-import-export-multiselect_js', WPC_IMPORT_EXPORT_URL . 'assets/js/jquery.multiselect.js', array('jquery'), WPC_IMPORT_EXPORT_VERSION, TRUE );
    wp_register_script( 'wpc-import-export_script', WPC_IMPORT_EXPORT_URL . 'assets/js/scripts.js', array( 'jquery', 'wpc-import-export-multiselect_js' ), WPC_IMPORT_EXPORT_VERSION, TRUE );		
    // Enqueue Scripts
	wp_enqueue_script( 'wpc-import-export-multiselect_js' );
    wp_enqueue_script( 'wpc-import-export_script' );
    // Localize Script
    wp_localize_script( 'wpc-import-export_script', 'wpcieAjaxHandler', array( 
        'ajaxURL'   => admin_url( 'admin-ajax.php' ),
        'ajaxNonce' => wp_create_nonce( "wpcargo_import_export_ajaxnonce" ),
        'processRequestLabel' => wpcie_lang_process_request(),
        'dateRequired' => wpcie_lang_daterange_required(),
        'uploadingFile' => wpcie_lang_upload_file(),
        'processingData' => wpcie_lang_process_data(),
        'processComplete' => wpcie_lang_process_data_complete()
    ) );
}
add_action( 'wp_enqueue_scripts', 'wpcargo_import_export_scripts' );
function wpcie_registered_styles( $styles ){
    // $styles[] = 'wpcie_styles';
    return $styles;
}
add_filter('wpcfe_registered_styles', 'wpcie_registered_styles', 10, 1 );
function wpcie_registered_scripts( $scripts ){
    $scripts[] = 'wpc-import-export-multiselect_js';
	$scripts[] = 'wpc-import-export_script';
    return $scripts;
}
add_filter('wpcfe_registered_scripts', 'wpcie_registered_scripts', 10, 1 );