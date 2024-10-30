<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! defined('WPFC_CACHE_QUERYSTRING') ){
    define( 'WPFC_CACHE_QUERYSTRING', false );
} else if ( WPFC_CACHE_QUERYSTRING === true ){
	add_action( 'admin_notices', function(){
        $settings = get_option( 'cjapi_tracking_settings', $default=false );
        $using_woo_sessions = empty($settings['storage_mechanism']) ? false : $settings['storage_mechanism'] === 'woo_session';
        if ( $using_woo_sessions ){
    		?>
    		<div class="notice notice-error">
    		    <p><b>CJ Tracking conflict:</b> WP Fastest Cache is ignoring query strings (The WPFC_CACHE_QUERYSTRING constant was true). <b>This will cause the website to frequently fail at recording the CJ Event.</b></p>
    		</div>
    		<?php
        }
	} );
}

if (is_admin()){

    if ( ! is_ssl() && CJAPI_IN_PROD ){
    	add_action( 'admin_notices', function(){
    		?>
    		<div class="notice notice-warning">
    		    <p><b>CJ Tracking SSL Warning:</b> We detected that you are logged in over HTTP!
                    The CJ Network Integration plugin will not work properly if you do not have a valid TLS certificate.
                    If HTTPS works fine, and you just happen to be loading this page over HTTP instead (why?!), then you are fine.
                    If not, you will need to setup HTTPS.</p>
    		</div>
    		<?php
    	} );
    }

    /* The built in getallheaders doesn't work good with NGINX on I believe anything less than PHP 7.3 */
    /* https://stackoverflow.com/q/13224615/786593 */
    function cjapi_tracking_getallheaders(){
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if ( ! empty($headers))
                return $headers;
        }
        if (!is_array($_SERVER)) {
            return array();
        }

        $headers = array();
        foreach ($_SERVER as $name => $val) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $header_name = str_replace(array('_', ' '), '-', substr($name, 5));
                $headers[$header_name] = sanitize_text_field($val);
            }
        }
        return $headers;
    }
    /* case insensitive check if request header exists */
    function cjapi_tracking_has_header_i($val){
        static $headers;
        // in PHP 7 could do
        //$headers = $headers ?? array_change_key_case(cjapi_tracking_getallheaders(), CASE_LOWER);
        $headers = isset($headers) ? $headers : array_change_key_case(cjapi_tracking_getallheaders(), CASE_LOWER);
        return isset($headers[strtolower($val)]);
    }

    if (cjapi_tracking_has_header_i('Content-Security-Policy')){
        add_action( 'admin_notices', function(){
    		?>
    		<div class="notice notice-error">
    		    <p><b>CJ tracking code compatability issue</b>: Detected that you are using a CSP (Content Security Policy).
                    Please contact your CIE for details and additional steps.</p>
    		</div>
    		<?php
    	} );
    }



    if ( ! CJAPI_IN_PROD){
        add_action( 'admin_notices', function(){
    		?>
    		<div class="notice notice-warning">
    		    <p><b>Dev/Staging site detected:</b> No data will be sent to CJ. The plugin will still add notes to orders/form submissions as if the data was sent to make debugging problems with the plugin possible.</p>
    		</div>
    		<?php
    	} );
    }
}
