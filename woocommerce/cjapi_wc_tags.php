<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

use Automattic\Jetpack\Constants;

/* Because the conditional tags provided don't accept a post ID,
and since this is an Ajax request that's a problem */
class CJAPI_WooConditionalTags{
    public function __construct($post_id){
        if ( ! $post_id){
            $this->pid = false;
            $this->post = false;
        }
        $this->pid = (int)$post_id;
        $this->post = get_post($this->pid);
    }
    public function post_content_has_shortcode($tag){
        return $this->post->post_content && has_shortcode( $this->post->post_content, $tag );
    }
    public function is_cart(){
        // https://woocommerce.wp-a2z.org/oik_api/is_cart/
        if ($page_id = $this->pid)
            return ( $page_id && is_page( $page_id ) ) || Constants::is_defined( 'WOOCOMMERCE_CART' ) || $this->post_content_has_shortcode( 'woocommerce_cart' );
        return false;
    }
    function is_checkout() {
        if ($page_id = $this->pid)
            return ( $page_id && is_page( $page_id ) ) || $this->post_content_has_shortcode( 'woocommerce_checkout' ) || apply_filters( 'woocommerce_is_checkout', false ) || Constants::is_defined( 'WOOCOMMERCE_CHECKOUT' );
        return false;
    }
    function is_order_received_page() {
		global $wp;

		if ($page_id = $this->pid)
            return apply_filters( 'woocommerce_is_order_received_page', ( $page_id && is_page( $page_id ) && isset( $wp->query_vars['order-received'] ) ) );
        return false;
	}
    function is_account_page() {
		if ($page_id = $this->pid)
            return ( $page_id && is_page( $page_id ) ) || $this->post_content_has_shortcode( 'woocommerce_my_account' ) || apply_filters( 'woocommerce_is_account_page', false );
        return false;
	}
    function is_product() {
        if ($this->pid)
            return is_single($this->pid) && get_post_type($this->pid) === 'product';
        return false;
	}
}

class CJAPI_WC_Tag implements CJAPI_TagInterface{
    /* Functions should return a falsey value as a default.
        code that use this class should then proceed to the next registered integration upon receiving a falsey value */

    public function __construct(){
        global $wp;
        $this->order = false;

        $actionData = isset( $_GET['action'] ) ? sanitize_text_field($_GET['action']) : '';
        if ( $actionData ==='cj_site_tag_data' ){
            return;
        }

        if (defined('DOING_AJAX')){
            $order_id = $this->getOrderId();
            $this->order = wc_get_order( absint($order_id) );
        } else {
            // I think wp might be the earliest we can use get_query_var
            add_action('wp', function(){
                global $wp;
                if ($order_id = get_query_var('order-received', false))
                    $this->order = wc_get_order( absint($order_id) );
            });
        }
    }

    public function isThankYouPage(): bool{
        static $res;

        $actionConversionData = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : '';
        if ($res === null)
            $res = (function_exists('is_order_received_page') && is_order_received_page())
                || (defined('DOING_AJAX') && $actionConversionData ==='cj_conversion_tag_data');
        return $res;
    }
    public function getOrderId(): string{
        $receivedOrder = isset($_GET['order-received']) ? (string)absint($_GET['order-received']) : '';
        if ($receivedOrder === '')
            throw new Exception('please pass in the order-received parameter when getting the conversion code data');

        return $receivedOrder;
    }
    public function getCurrency(): string{
        if ($order = $this->order){
            return $order->get_currency();
        }
        return '';
    }
    public function getDiscount(){
        if ($order = $this->order){
            return $order->get_discount_total();
        }
        return 0;
    }
    public function getCoupon(): string{
        if ($order = $this->order){
            $coupon_codes = array();
            foreach ( $order->get_items('coupon') as $coupon_item ) {
              $coupon_codes[] = $coupon_item->get_code();
            }
            $coupon_codes = implode( ",", $coupon_codes ) ?: '';
            return $coupon_codes;
        }
        return '';
    }

    public function getPageType(): string{
        $postId = isset( $_GET['post_id'] ) ? sanitize_text_field( $_GET['post_id'] ) : '';
        if ( $postId==='' || ! is_post_publicly_viewable($postId))
            return '';
        $page_is = new CJAPI_WooConditionalTags($postId);

        if ($this->isThankYouPage()) return 'conversionConfirmation'; // must be before is_cart/is_checkout
        if ($page_is->is_cart() || $page_is->is_checkout()) return 'cart';
        if ($page_is->is_account_page()) return 'accountCenter';
        if ($page_is->is_product()) return 'productDetail';

        return '';
    }
    public function getReferringChannel(): string{
        return '';
    }
    public function getCartSubtotal(){
        $subtotal = WC()->cart->get_subtotal() - WC()->cart->get_discount_total();
        return max((float)$subtotal, 0.0);
        // if ( ! $this->isThankYouPage()){
        //     return WC()->cart->get_cart_subtotal();
        //     // also `get_cart_contents_total` does not include discounts/fees
        //     // https://woocommerce.wp-a2z.org/oik_api/wc_cartget_cart_contents_total/
        //     // https://woocommerce.wp-a2z.org/oik_api/wc_cartget_cart_subtotal/
        //     // and get_total includes shipping
        //     // looking at source clearly shows what is and is not included in the total
        // }
        // return 0;
    }
    public function getOrderSubtotal(){
        if ($this->order)
            return (float)($this->order->get_subtotal() - $this->order->get_discount_total());
            // See: https://stackoverflow.com/questions/40711160/woocommerce-getting-the-order-item-price-and-quantity
        return 0.0;
    }
    public function getItems(){
        if ( ! $this->isThankYouPage()){
            $ret = [];
            $cart = WC()->cart;
            $cart_items = $cart->get_cart();
            $quantities = $cart->get_cart_item_quantities();
            $qty_total = array_sum($quantities);
            $discount_total = $cart->get_discount_total() - $cart->get_fee_total();

                foreach($cart_items as $line_item) {
                    $product_id = $line_item['product_id'];
                    $product =  wc_get_product( $product_id );
                    $qty = $quantities[$product->get_stock_managed_by_id()] ?? $line_item['quantity'];
                    $product_discount = $discount_total * ($qty / $qty_total);

                    array_push($ret, array(
                        'unitPrice' => $product->get_price() - $product_discount,
                        'itemId' => $product->get_sku() ?: $product_id,
                        'quantity' => $qty,
                        //'discount' => $product->is_on_sale() ? $product->get_regular_price() - $product->get_sale_price() : 0.0,
                        'discount' => $product_discount,
                    ));
                }
            return $ret;
        }

        if ( ! $this->order)
            return array();
        $ret = [];
        $order_items = $this->order->get_items();
        $qty_total = array_reduce($order_items, function($accumulator, $order_item){ return $accumulator + $order_item->get_quantity(); }, 0);
        $qty_total = $qty_total ?: 1; // make sure we never end up with division by zero errors
        $discount_total = (float)$this->order->get_discount_total(); // - $cart->get_fee_total();
        //there is no per product discount in woocommerce without extra modules and annual payments

        
        foreach($order_items as $item) {

            $per_product_discount = 0;
            $product = $item->get_product();
            $product_id = $product->get_id();
            $qty = $item->get_quantity();
            $item_discounted_price = (float)$item->get_total()/$qty;
        
            $add_me = array(
            'unitPrice' => (float)($item->get_subtotal()/$qty),
            'itemId' => (string)($product->get_sku() ? $product->get_sku() : 'nosku-' . $product_id),
            'quantity' => (int)$qty,
            );

            if(! ($add_me['unitPrice'] == $item_discounted_price)) {
                $per_product_discount = $add_me['unitPrice'] - $item_discounted_price;
            }
//
            if ($per_product_discount != 0) {
                $add_me['discount'] = $qty*(float)$per_product_discount;
            }

            array_push($ret, $add_me);
        }
        return $ret;
    }

    function notateOrder($data, $debug_mode){
        $order_id = $this->getOrderId();
        $order = wc_get_order($order_id);
        if ($debug_mode){
            $order ? $order->add_order_note(
                sprintf( __('Data prepared for sending to CJ.com: %s', 'cjtracking'),
                wp_json_encode($data))
            ) : '';
        }
        if(isset($data['cjeventOrder']) && $data['cjeventOrder'] != '')
            $order ? $order->add_order_note( sprintf( __("This order was from a CJ.com referral.<br/>---------<br/>DETAILS<p style='margin-left: 0px;'>Action Tracker ID: %s </p><p style='margin-left: 0px;'>CJ Event: %s</p></span>", 'cjtracking'), $data['actionTrackerId'], $data['cjeventOrder']) ) : '';
    }

    function getTax() {
        $order = wc_get_order( $this->getOrderId() );
        $total_tax = $order ? $order->get_total_tax() : 0.0;
        return $total_tax;
    }

    function getShippingCountry() {
        $order_id = $this->getOrderId();
        $order = wc_get_order( $order_id );
        $country = $order ? $order->get_shipping_country() : '';
        return $country;
    }


    function getCustomerStatus() {
        global $customer_status;
        $order_id = $this->getOrderId();
        $order = wc_get_order( $order_id );

        //is_returning_customer() might not be compatible with latest WooCommerce versions so later this can be removed
        if($order){
            if(method_exists($order, 'is_returning_customer')){
                $customer_status = $order->is_returning_customer();
            }
            else{
                $customer_status = '';
            }
        }
        else
            $customer_status = '';

        if($customer_status === true)
            return "Return";
        elseif ($customer_status === false)
            return "New";
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
                    return "Return";
                elseif($order_count == 1){
                    if($retrieved_order_id != $order_id)
                        return "Return";
                    else
                        return "New";
                }
                else
                    return "New";
            }
            return '';
        }
        return '';
    }

    function getCartDiscount($discount) {

        $items = $this->getItems();

        $item_level_total = 0;
        $discount = round($discount,2);
        $basket_discount = 0;

        foreach ($items as $item) {
            if(isset($item['discount']) && $item['discount']) {
                $item_level_total+=$item['discount'];
            }
        }

        $item_level_total = round($item_level_total,2);

        if($item_level_total) {
            $basket_discount = round($discount - $item_level_total,2);
        }
        return $basket_discount;
    }
}
