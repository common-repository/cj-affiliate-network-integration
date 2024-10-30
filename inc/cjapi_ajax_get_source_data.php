<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

function cjapi_source_callback(){

    $ret = array(
    'version' => CJAPI_PLUGIN_VERSION,
    'name' => 'WOOCOMMERCE');

    wp_send_json($ret);
    exit;
}
add_action('wp_ajax_cj_source_data', 'cjapi_source_callback');
add_action('wp_ajax_nopriv_cj_source_data', 'cjapi_source_callback');

