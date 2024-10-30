<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/*
    To add a new integration
        - Add to CJAPI_ALL_POSSIBLE_INTEGRATIONS and CJAPI_INTEGRATION_DESCRIPTIONS
        - Add new check in cj_get_installed_integrations
        - Add new setting on settings page
        - Extend CJApiTagInterface, adding an object of your class with cj_add_site_tag_obj
        - Add to cjapi_register_integrations

*/

const CJAPI_ALL_POSSIBLE_INTEGRATIONS = array('WooCommerce');
const CJAPI_INTEGRATION_DESCRIPTIONS = array(
    'WooCommerce'=>'The tracking code will be added to the thank you page.',
);

interface CJAPI_TagInterface {
    public function getPageType(): string;
    public function getReferringChannel(): string;
    public function getCartSubtotal();
    public function getOrderSubtotal();
    public function getItems();
    public function isThankYouPage(): bool;
    // for the conversion tag
    public function getOrderId(): string;
    public function getCurrency(): string;
    public function getDiscount();
    public function getCoupon(): string;

    // optional
    // the following will only be used if they are present in your class
    // public function turnOnManualOrderSending(): bool // when true, you must add your own JS to send the order,
        // can be done with cjAPI.sendOrder(cj.order), otherwise it is automatically sent onLoad

}

function cjapi_register_integrations(){

    static $already_registered;
    if ($already_registered)
        return;
    $already_registered = true;

    // TODO remove this
    $settings = cjapi_get_cj_settings();
    $use_deprecated = ! isset($settings['enterprise_id']) || ! $settings['enterprise_id'];
    if ($use_deprecated)
        return;

    $integrations = cjapi_get_integrations();

    if (in_array('WooCommerce', $integrations)){
        include_once CJAPI_PLUGIN_PATH . 'woocommerce/cjapi_wc_tags.php';
        cjapi_add_site_tag_obj( new CJAPI_WC_Tag() );
    }

    // This should always be last
    cjapi_add_site_tag_obj(new CJAPI_Site_Tag_Defaults());
}

function cjapi_make_url_friendly($input){
    $url_friendly = strtolower( str_replace(array(' ', '&'), '', $input) );
    return $url_friendly;
}

$cj_url_friendly_integration_mapping = array();
foreach (CJAPI_ALL_POSSIBLE_INTEGRATIONS as $integration){
    $cj_url_friendly_integration_mapping[cjapi_make_url_friendly($integration)] = $integration;
}

function cjapi_get_integrations(){
    return array_intersect( cjapi_get_installed_integrations(), cjapi_get_integrations_enabled_not_necessarily_installed() );
}

/* If the plugin is installed, but the setting is still enabled */
function cjapi_get_installed_integrations(){
    $active_plugins = apply_filters('active_plugins', get_option('active_plugins'));
    $gf_plugin_active = false;
    $wc_plugin_active = false;
    foreach($active_plugins as $p){

        if ( cjapi_endsWith($p, '/woocommerce.php') || cjapi_endsWith($p, '\\woocommerce.php') ){
            $wc_plugin_active = true;
        }
    }

    $ret = array();
    if ($wc_plugin_active)
        $ret[] = 'WooCommerce';
    return $ret;
}

/* Use get_integrations to find integrations that where both enabled in the settings menu and have an active plugin associated with them */
function cjapi_get_integrations_enabled_not_necessarily_installed(){
    global $cj_url_friendly_integration_mapping;
    $opts = get_option( 'cjapi_tracking_settings' );

    if ( ! isset($opts['integrations']) || ! is_array($opts['integrations'])){
        return array();
    }

    $ret = array();
    foreach($opts['integrations'] as $url_friendly => $enabled){
        if ($enabled and isset($cj_url_friendly_integration_mapping))
            $ret[] = $cj_url_friendly_integration_mapping[$url_friendly];
    }

    return $ret;
}

function cjapi_get_uninstalled_and_installed_integrations(){
    return CJAPI_ALL_POSSIBLE_INTEGRATIONS;
}
