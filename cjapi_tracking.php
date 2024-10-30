<?php
/**
 * Main plugin file
 * This plugin is based off the documentation found at https://developers.cj.com/docs
 *
 * Plugin Name:       CJ Network Integration
 * Description:       Tracks and posts back referred transactions to CJ
 * Version:           1.1.7
 * Author:            CJ
 * Author URI:        https://www.cj.com
 * Text Domain:       cjnetwork
 * Contributors:      CJ
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

// Define our constants
define( 'CJAPI_PLUGIN_VERSION', "1.1.7" );
define( 'CJAPI_RUN_UNIT_TESTS', false );
define( 'CJAPI_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

// version check
if ( version_compare( PHP_VERSION, '5.6.0' ) === -1 ){
    function cjapi_tracking_check_php_version() {
        if( is_admin() ){
            ?>
            <div class="error notice">
                <p><?php printf( __( 'The CJ Network Integration plugin requires PHP version %1$s.'
                                   . ' You are currently running version: %2$s', 'cjtracking'),
                                "5.6.0", PHP_VERSION );
                ?></p>
            </div>
            <?php
        }
    }
    add_action( 'admin_notices', 'cjapi_tracking_check_php_version' );
    return;
}

require CJAPI_PLUGIN_PATH . 'inc/cjapi_in_prod.php';

//TODO USELESS CODE
/*add_action('parse_request', function( $wp ){
    $current_relative_url = add_query_arg( $_SERVER['QUERY_STRING'], '', $wp->request);
    if ( preg_match( '#^cj-proxy/(.+)#', $current_relative_url, $matches ) ) {
        global $cj_proxy_path;
        $cj_proxy_path = $matches[1];
        require CJAPI_PLUGIN_PATH . 'inc/proxy.php';

        exit;
    }
});*/

// If we're on the backend, register admin settings and return
if ( is_admin() ){
    require CJAPI_PLUGIN_PATH . 'inc/cjapi_settings_page.php';
    if (wp_doing_ajax()){
        require CJAPI_PLUGIN_PATH . 'inc/cjapi_inc.php';
        require CJAPI_PLUGIN_PATH . 'inc/cjapi_ajax.php';
        require CJAPI_PLUGIN_PATH . 'inc/cjapi_ajax_get_site_tag_data.php';
        require CJAPI_PLUGIN_PATH . 'inc/cjapi_ajax_get_conversion_tag_data.php';
        require CJAPI_PLUGIN_PATH . 'inc/cjapi_ajax_get_source_data.php';

        return;
    }
    include CJAPI_PLUGIN_PATH . 'inc/cjapi_compatability_and_notices.php';

    // Add a settings link to the plugins page
    function cjapi_affiliate_settings_link( $links ) {
        $links[] = '<a href="options-general.php?page=cj-affiliate-tracking-settings">' . __( 'Settings' ) . '</a>';
      	return $links;
    }
    add_filter( "plugin_action_links_".plugin_basename( __FILE__ ), 'cjapi_affiliate_settings_link' );

    return; // Return from this script

} else {
    $settings = get_option('cjapi_tracking_settings', $default=false);
    //TODO remove dead code
    function cjapi_tracking_enqueue_js(){
        $duration = empty($settings['cookie_duration']) ? 120 : (int)$settings['cookie_duration'];
        wp_register_script( 'cj_tracking_cookie_duration', '', array(), null );
        wp_enqueue_script( 'cj_tracking_cookie_duration'  );
        wp_add_inline_script( 'cj_tracking_cookie_duration', "cj_tracking_cookie_duration=$duration" ,'before');
    }
    add_filter('wp_enqueue_scripts', 'cjapi_tracking_enqueue_js');
}

require CJAPI_PLUGIN_PATH . 'inc/cjapi_integrations.php';
require CJAPI_PLUGIN_PATH . 'inc/cjapi_tag_functions.php';
require CJAPI_PLUGIN_PATH . 'inc/cjapi_inc.php';
include CJAPI_PLUGIN_PATH . 'inc/cjapi_compatability_and_notices.php';

$cj_integrations = cjapi_get_integrations();

$settings = cjapi_get_cj_settings();

if (in_array('WooCommerce', $cj_integrations)){

    include_once CJAPI_PLUGIN_PATH . 'woocommerce/cjapi_concurrent.php';
    include_once CJAPI_PLUGIN_PATH . 'woocommerce/cjapi_set_ss_cookie.php';
    // Maybe run unit tests
    if ( true === CJAPI_RUN_UNIT_TESTS ){
      require CJAPI_PLUGIN_PATH . 'woocommerce/cjapi_unit_tests.php';
    }

    // mostly redundant since cookies are now being saved with JS,
    // although it still has the advantage that it may save the cjevent (if we get past the cache)
    // before the site tag is fired while the JS version happens later
    // which would cause false statistics about the first page people landed on
    // include_once CJAPI_PLUGIN_PATH . 'woocommerce/legacy_save_affiliate_referral_info.php';
}


cjapi_register_integrations();

if (CJAPI_IN_PROD){
    add_filter('wp_enqueue_scripts', function() use ($settings){
         $tag_js_url = plugins_url('/assets/cjapi-tag.js', CJAPI_PLUGIN_PATH.'/placeholder');
         if (strpos($tag_js_url, 'http://') === 0 )
             $tag_js_url = substr_replace($tag_js_url, 'https://', 0, strlen('http://'));
        wp_enqueue_script('cj-tracking-code', $tag_js_url, array(), CJAPI_PLUGIN_VERSION, true);

        wp_localize_script( 'cj-tracking-code', 'cj_from_php', array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'tag_type' => cjapi_is_conversion_tracking_page() ? 'conversion_tag' : 'site_tag',
            'woo_order_id' => get_query_var('order-received', false),
            'post_id' => get_the_ID(),
            'action_tracker_id' => isset($settings['action_tracker_id']) ? $settings['action_tracker_id'] : '',
            'tag_id' => isset($settings['tag_id']) ? $settings['tag_id'] : ''
        ));
    });
}
