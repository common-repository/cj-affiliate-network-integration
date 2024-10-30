<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

//-------------------------------------------
// CJ CONVERSION TRACKING PIXEL
//-------------------------------------------

function cjapi_concurrent_ss( $order_id ) {

    if (!CJAPI_IN_PROD)
        return;
    //-----------------
    // Get order items
    //-----------------

    if ( is_object($order_id) ){
      // Accept a WooCommerce order object in place of the order ID.
      // I don't think we will ever encounter this situation under normal circumstances,
      // but it makes unit testing possible
      $order = $order_id;
      $order_id = $order->get_id();
    } else {
      $order = wc_get_order( $order_id );
    }
    $order_items = $order->get_items();
    $total_tax_amount = $order->get_total_tax();
    $customer_country = $order->get_shipping_country();

    if ( CJAPI_RUN_UNIT_TESTS === true ){

    $tag_id = 'XXXXX';
    $cid = 'XXXXXXX';
    $type = 'XXXXXXX';
    $cj_tracking_note_urls = false;
    $other_params = '';
    $use_cookies = false;

  } else {

    // Get account specific info from DB
    $justInfo = cjapi_get_cj_settings();
    $account_info = apply_filters('cj_settings', cjapi_get_cj_settings(), 'woocommerce', $order_items, $order_id);
    // Don't add the tracking code if we got back false (While allowing an error to naturally happen if we get back null as null could mistakenly get returned if the return statement is forgotten)
    if ( $account_info === false )
        return false;

    $cid = $account_info['enterprise_id'];
    $type = $account_info['action_tracker_id'];
    $cj_tracking_note_urls = $account_info['notate_order_data'];
    $other_params = $account_info['other_params'];
    $use_cookies = $account_info['storage_mechanism'] === 'cookies';

  }

  //---------------------
  // Create tracking code
  //---------------------
  $cj_url = 'https://www.emjcd.com/u?';
  // The documentation says to use container_tag_id, but containerTagId seems to be working so I'm leaving this as is for now
  // $cj_url = "https://www.emjcd.com/tags/c?containerTagId=$tag_id&";

  // Skip products that are part of a product bundle
  $skip_these = array();
  //error_log( "\n\n\n\nCart Data:\n" . var_export($order_items, true), 3, ABSPATH . 'cj_debug_log_dkrjtwk.txt' );
  $order_items = array_filter($order_items);
  foreach ( $order_items as $item_id => $item_data ) {
      $product = $item_data->get_product();
      if ( $product->is_type('bundle') && class_exists('WC_PB_DB') && $bundled = WC_PB_DB::query_bundled_items( array('bundled_id' => $product->get_id()) )  ){
          foreach($bundled as $data){
              $skip_these[] = $data['product_id'];
          }
      } else if ( get_class($product) === 'WC_Product_Yith_Bundle' ){
          // TODO support yith product bundles
          $cj_url .= 'warning=yith_product_bundles_not_yet_supported_please_submit_a_ticket_using_the_form_on_the_cj_plugin_settings_page&';
      } else if ( strpos(get_class($product), 'Bundle') !== false ) {
          $cj_url .= 'warning=unsupported_bundling_plugin_detected_please_submit_a_ticket_using_the_form_on_the_cj_plugin_settings_page&';
      }
  }

  // Add info about each item in the order
  $i = 1;
  $total_price = 0;
  $total_item_level_discount = 0;
  global $WooCommerce;

    $basket_discount = $order ? $order->get_subtotal() - $order->get_discount_total() : 0.0;

    $cj_url .= "amount=$basket_discount&";

    foreach ($order_items as $item) {
        $per_product_discount = 0;
        $item_discount = 0;
        $product = $item->get_product();
        $product_id = $product->get_id();
        $qty = $item->get_quantity();
        $item_discounted_price = (float)$item->get_total()/$qty;

        $item_total = (float)($item->get_subtotal()/$qty);
        $product_sku_or_name = (string)($product->get_sku() ? $product->get_sku() : 'nosku-' . $product_id);
        $item_quantity = (int)$qty;

        if(! ($item_total == $item_discounted_price)) {
            $per_product_discount = $item_total - $item_discounted_price;
        }

        if ($per_product_discount != 0) {
            $item_discount = $qty*(float)$per_product_discount;
        }

        if($item_discount != 0)
            $cj_url .= "item$i=$product_sku_or_name&amt$i=$item_total&dcnt$i=$item_discount&qty$i=$item_quantity&";
        else
            $cj_url .= "item$i=$product_sku_or_name&amt$i=$item_total&qty$i=$item_quantity&";

        $total_item_level_discount += $item_discount;
        $i++;
    }

  // Get discount info
  $coupon_codes = array();
  foreach ( $order->get_items('coupon') as $coupon_item ) {
    $coupon_codes[] = $coupon_item->get_code();
  }
  $coupon_codes = implode( ",", $coupon_codes ) ?: '';
  $discount_total = $order->get_discount_total();

  // Info stored in the affiliate link clicked on to get to our site
  if ($use_cookies){
      $publisherCID = htmlspecialchars( isset($_COOKIE['publisherCID']) ? sanitize_text_field($_COOKIE['publisherCID']) : '');
      $cjevent = htmlspecialchars( isset($_COOKIE['cje']) ? sanitize_text_field($_COOKIE['cje']) : '');
  } else {
      $publisherCID = htmlspecialchars( WC()->session->get('publisherCID') );
      $cjevent = htmlspecialchars( WC()->session->get('cjevent') );
  }

  // format other query params
  $other_params = str_replace("\n", '&', $other_params);
  $other_params = str_replace('&&', '&', $other_params);
  // I would prefer to just urlencode everything between the ampersands and spaces
  // perhaps in the future I'll add a better solution that uses preg_split
  // and then after urlencode()ing, implodes everything back together
  $other_params = str_replace(" ", '+', $other_params);
  $other_params = filter_var($other_params, FILTER_SANITIZE_URL);
  if ($other_params && substr($other_params, 0, 1) !== '&')
    $other_params = '&' . $other_params;

  //Get Currency
  $currency = $order->get_currency();

  // Add info about the order
  $cj_url .= "type=$type&cid=$cid&oid=$order_id&cjevent=$cjevent&currency=$currency&customerCountry=$customer_country&taxAmount=$total_tax_amount";
  if ($coupon_codes)
    $cj_url .= "&coupon=$coupon_codes";
  if ($discount_total){
      if($total_item_level_discount != 0){
          $total_item_level_discount = round($total_item_level_discount, 2);
          $discount_total = round($discount_total,2);
          $discount_total = round(($discount_total - $total_item_level_discount),2);
      }
  }
  $cj_url .= "&discount=$discount_total";
  $cj_url .= $other_params;

  global $ret_cust;
  global $customer_status;
  //Add info about customer status
    $order = wc_get_order( $order_id );
    if($order){
        if(method_exists($order, 'is_returning_customer')){
            $ret_cust = $order->is_returning_customer();
        }
        else{
            $ret_cust = '';
        }
    }
    else
        $ret_cust = '';
    if($ret_cust === true)
        $customer_status = "Return";
    elseif($ret_cust === false)
        $customer_status = "New";
    else{
        $query_arguments = [
            'return'      => 'ids',
            'post_status' => wc_get_is_paid_statuses(),
        ];

        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            $query_arguments['customer'] = $current_user ? $current_user->user_email : '';
        }
        else {
            $query_arguments['billing_email'] = $order ? $order->get_billing_email() : '';
        }
        $orders = wc_get_orders($query_arguments);
        $order_count = $orders ? count($orders) : '';
        $retrieved_order_id = $orders ? $orders[0] : '';

        if($orders){
            if($order_count > 1)
                $customer_status = "Return";
            elseif($order_count == 1){
                if($retrieved_order_id != $order_id)
                    $customer_status = "Return";
                else
                    $customer_status = "New";
            }
            else
                $customer_status = "New";
        }
        else
            $customer_status = '';
    }

   $cj_url .= "&customerStatus=$customer_status";

  $cj_url .= "&cjPlugin=WOOCOMMERCE";
  $cj_url .= "&method=S2S";

  wp_remote_retrieve_body(wp_remote_get($cj_url));
}
add_action( 'woocommerce_thankyou', 'cjapi_concurrent_ss' );
