<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

function cjapi_site_tag_ajax_callback(){
    global $CJ_Site_tag_objects;
    header('content-type: application/json; charset=utf-8');

    include_once 'cjapi_tag_functions.php';
    cjapi_register_integrations();

    $ret = cjapi_get_shared_tag_data();

    $subtotal = 0.0;
    foreach($CJ_Site_tag_objects as $obj){
        $subtotal += (float)$obj->getCartSubtotal();
    }

    $ret['cartSubtotal'] = $subtotal;

    //TODO USELESS CODE
   // $ret = apply_filters('cj_data_layer', $ret, 'sitePage');

    wp_send_json($ret);
    exit;
}
add_action('wp_ajax_cj_site_tag_data', 'cjapi_site_tag_ajax_callback');
add_action('wp_ajax_nopriv_cj_site_tag_data', 'cjapi_site_tag_ajax_callback');
