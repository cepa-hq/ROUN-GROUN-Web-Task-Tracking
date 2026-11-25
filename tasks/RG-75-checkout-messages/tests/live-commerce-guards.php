<?php
defined('ABSPATH') || exit;
/** Compatibility fixes, intentionally independent of Flatsome and account rendering. */
final class RGA_Commerce_Guards {
    private static $pay_order;
    public static function boot(){
        add_action('wp_loaded',array(__CLASS__,'single_cart_handler'),18);
        add_action('wp_enqueue_scripts',array(__CLASS__,'assets'),150);
        add_filter('woocommerce_add_to_cart_redirect',array(__CLASS__,'buy_redirect'),PHP_INT_MAX);
        // WPFactory uses PHP_INT_MAX too: register after its constructor, not before.
        add_filter('woocommerce_available_payment_gateways',array(__CLASS__,'gateways'),PHP_INT_MAX);
        add_action('woocommerce_before_pay_action',array(__CLASS__,'pay_context'),1);
        add_action('woocommerce_after_checkout_validation',array(__CLASS__,'checkout_validation'),100,2);
    }
    public static function single_cart_handler(){
        // Incident RG-7, 14 Nov 2026: quantity=1 on resistance-band Buy now
        // became TWO sets. Customer Studio forwards form.cart FormData, including
        // hidden add-to-cart. WC_Form_Handler at wp_loaded20 adds once, then the
        // rgcs_cart AJAX handler adds again. Give this exact AJAX operation one
        // owner; its own nonce, cart-hash, stock and variation validation remain.
        // Do NOT clamp quantity, clear the cart, or disable native purchases globally.
        if(wp_doing_ajax()&&($_SERVER['REQUEST_METHOD']??'')==='POST'
            &&isset($_POST['action'],$_POST['operation'])
            &&is_string($_POST['action'])&&$_POST['action']==='rgcs_cart'
            &&is_string($_POST['operation'])&&$_POST['operation']==='add'){
            remove_action('wp_loaded',array('WC_Form_Handler','add_to_cart_action'),20);
        }
    }
    public static function assets(){
        if(function_exists('is_product')&&is_product())wp_enqueue_script('rga-purchase',plugins_url('../assets/purchase-guard.js',__FILE__),array(),RGA_Refinement::VERSION,true);
    }
    public static function buy_redirect($url){
        return !wp_doing_ajax()&&($_SERVER['REQUEST_METHOD']??'')==='POST'&&isset($_POST['ux-buy-now'])&&is_scalar($_POST['ux-buy-now'])&&is_numeric($_POST['ux-buy-now'])?wc_get_checkout_url():$url;
    }
    public static function pay_context($order){self::$pay_order=$order;}
    private static function current_order(){
        if(self::$pay_order instanceof WC_Order)return self::$pay_order;
        if(!is_wc_endpoint_url('order-pay'))return null;
        $order=wc_get_order(absint(get_query_var('order-pay')));
        $key=isset($_GET['key'])&&is_string($_GET['key'])?wc_clean(wp_unslash($_GET['key'])):'';
        if(!$order||!$key||!hash_equals($order->get_order_key(),$key))return null;
        return $order;
    }
    /**
     * COD INCIDENT RECORD — RG-7, owner report and code audit, 14 Nov 2026.
     * A customer left an order unpaid/incomplete. It appeared in My orders with
     * a Pay action. That native action opens WooCommerce's order-pay endpoint,
     * which is a separate payment flow from ordinary cart checkout. COD appeared
     * there although the ordered product was excluded from COD. The customer
     * selected COD and WooCommerce ACCEPTED it; no online payment was made.
     * WPFactory Conditional Payment Gateways 2.5.1 product module obtains its
     * IDs only from WC()->cart. An empty/unrelated cart therefore cannot enforce
     * the saved order's exclusions. Its leave_at_least_one_gateway=yes option
     * can also retain COD when it is the final gateway. Hiding a radio in CSS/JS
     * would leave a forged POST vulnerable and is NOT the fix.
     * Read the SAME enabled product inclusion/exclusion options administrators
     * already maintain. Evaluate parent AND variation IDs from saved line items
     * on order-pay, and real cart items on normal checkout. Remove COD server-side
     * even if no alternative remains. Core pay_action then rejects COD before
     * set_payment_method/save/process_payment. before_pay_action supplies its
     * key/nonce-validated order context; we never trust posted product IDs.
     * Existing orders are not modified or retrospectively marked as paid. Tests
     * must not accept COD/pay real orders merely to reproduce this incident.
     */
    public static function blocked($items){
        if(!function_exists('alg_wc_cpg')||get_option('alg_wc_cpg_plugin_enabled','yes')!=='yes')return false;
        $ids=array();
        foreach($items as $item){
            if($item instanceof WC_Order_Item_Product){$ids[]=$item->get_product_id();$ids[]=$item->get_variation_id();}
            elseif(is_array($item)){$ids[]=absint($item['product_id']??0);$ids[]=absint($item['variation_id']??0);}
        }
        $ids=array_values(array_unique(array_filter(array_map('absint',$ids))));
        foreach(array('excl','incl')as $kind){
            if(get_option('alg_wc_cpg_product_'.$kind.'_enabled','no')!=='yes')continue;
            $rules=get_option('alg_wc_cpg_product_'.$kind,array());
            $configured=is_array($rules)&&isset($rules['cod'])&&is_array($rules['cod'])?array_filter(array_map('absint',$rules['cod'])):array();
            if(!$configured)continue;
            $matches=(bool)array_intersect($ids,$configured);
            if(($kind==='excl'&&$matches)||($kind==='incl'&&!$matches))return true;
        }
        return false;
    }
    public static function gateways($gateways){
        if(!isset($gateways['cod']))return $gateways;
        $order=self::current_order();
        $items=$order?$order->get_items():(WC()->cart?WC()->cart->get_cart():array());
        if(self::blocked($items))unset($gateways['cod']);
        return $gateways;
    }
    public static function checkout_validation($data,$errors){
        if(($data['payment_method']??'')==='cod'&&WC()->cart&&self::blocked(WC()->cart->get_cart()))$errors->add('rga_cod_restricted','Cash on delivery is unavailable for one or more items. Please choose an online payment method.');
    }
}
add_action('plugins_loaded',array('RGA_Commerce_Guards','boot'),90);

