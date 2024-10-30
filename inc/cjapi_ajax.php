<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/* callback for uninstall button */
function cjapi_tracking_uninstall_ajax_callback() {
    if ( check_ajax_referer( 'cj-tracking-uninstall', 'nonce', false) ) {

        if (! get_option('cjapi_tracking_settings'))
            exit("  Success   ");

       $res = delete_option('cjapi_tracking_settings');

       if ($res)
          exit('success');
       exit('failed to delete plugin data');

 } else {
   wp_die('Could not process uninstall: security check failed');
 }
}
add_action( 'wp_ajax_cjapi_tracking_uninstall', 'cjapi_tracking_uninstall_ajax_callback' );
