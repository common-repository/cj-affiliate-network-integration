<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

function cjapi_set_cookie() {
    if (isset($_GET['cjevent'])){
        $cjevent = sanitize_text_field($_GET['cjevent']);
        $days_in_month = 31;
        $days = empty($settings['cookie_duration']) ? $days_in_month*13 : (int)$settings['cookie_duration'];
        $domain = parse_url(home_url())['host'];
        $domain = preg_replace('/^www\./', '', $domain);
        setcookie( "cje", $cjevent, time() + DAY_IN_SECONDS*$days, "/", $domain, true, false);
    }
}

add_action( 'init', 'cjapi_set_cookie' );
