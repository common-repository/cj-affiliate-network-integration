<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

function cjapi_conversion_tag_ajax_callback(){
    header('content-type: application/json; charset=utf-8');
    include 'cjapi_get_order_data.php';
    wp_send_json(cjapi_get_order_data());
    exit;
}
add_action('wp_ajax_cj_conversion_tag_data', 'cjapi_conversion_tag_ajax_callback');
add_action('wp_ajax_nopriv_cj_conversion_tag_data', 'cjapi_conversion_tag_ajax_callback');
