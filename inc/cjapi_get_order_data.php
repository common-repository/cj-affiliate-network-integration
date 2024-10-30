<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/* Used in both ajax requests and appended to Gravity Form confirmation messages to
    get data to eventually add in JavaScript into window.cj.order */
function cjapi_get_order_data(){
    global $CJ_Site_tag_objects;

    include_once 'cjapi_tag_functions.php';

    cjapi_register_integrations();

    $ret = cjapi_get_shared_tag_data();

    $cj_acct_info = cjapi_get_cj_settings();

    $order_ids = array();
    $currencies = array();
    $coupons = array();
    $discount = 0;
    $cart_discount = 0;
    $subtotal = 0;
    $manuallySendingOrderRequested = false;
    foreach($CJ_Site_tag_objects as $obj){
        if ($obj->isThankYouPage()){

            $order_id = $obj->getOrderId();
            if ($order_id){
                array_push($order_ids, $order_id);
            }

            $currency = $obj->getCurrency();
            if ($currency){
                array_push($currencies, $currency);
            }

            $coupon = $obj->getCoupon();
            if ($coupon){
                array_push($coupons, $coupon);
            }

            $cust_status = $obj->getCustomerStatus();

            $cart_discount += (float)$obj->getDiscount();
            $discount = $obj->getCartDiscount($cart_discount);

            $tax_amount = $obj->getTax();
            $customer_country = $obj->getShippingCountry();

//            $discount += (float)$obj->getDiscount();
            $subtotal += (float)$obj->getOrderSubtotal();

            if ( ! $manuallySendingOrderRequested && method_exists($obj, 'turnOnManualOrderSending') ){
                $manuallySendingOrderRequested = $obj->turnOnManualOrderSending();
            }
        }
    }
    $order_ids = array_unique(array_filter($order_ids));
    $order_id = empty($order_ids) ? '' : implode(', ', $order_ids);
    $order_id = cjapi_sanitize_order_id($order_id);
    $coupon = empty($coupons) ? '' : implode(', ', array_unique(array_filter($coupons)));

    $currencies = array_unique(array_filter($currencies));
    if (count($currencies) > 1){
        trigger_error('Received multiple conflicting currencies for a CJ Tracking: ' . implode(', ', $currencies), E_USER_WARNING);
    }
    $currency = empty($currencies) ? 'USD' : $currencies[0];

    $use_cookies = $cj_acct_info['storage_mechanism'] === 'cookies';
    if ($use_cookies){
        $cjevent = htmlspecialchars( isset($_COOKIE['cje']) ? sanitize_text_field($_COOKIE['cje']) : '' );
    } else {
        $cjevent = htmlspecialchars( WC()->session->get('cjevent') );
    }

    if ($ret['pageType'] !== 'conversionConfirmation'){
        throw new Exception('Expected a page type of \'conversionConfirmation\' when using the conversion tag ajax endpoint.');
    }
//this is the object that gives top level order details (not the item level)
    $ret = array_merge($ret, array(
         'orderId' => $order_id,
         'actionTrackerId' => $cj_acct_info['action_tracker_id'],
         'currency' => $currency,
         'customerCountry' => $customer_country,
         'customerStatus' => $cust_status,
         'amount' => $subtotal,
         'discount' => (float)$discount,
         'taxAmount' => $tax_amount,
         'cjeventOrder' => $cjevent,
         'cjPlugin'=> 'WOOCOMMERCE',
         'sendOrderOnLoad' => ! $manuallySendingOrderRequested,
    ));
    if ($coupon){
        $ret['coupon'] = $coupon;
    }

   //TODO USELESS CODE
  //  $ret = apply_filters('cj_data_layer', $ret, 'order');

    foreach($CJ_Site_tag_objects as $obj){
        if ($obj->isThankYouPage() && method_exists($obj, 'notateOrder')){
            $obj->notateOrder($ret, $cj_acct_info['notate_order_data'] );
        }
    }

    return $ret;
}
