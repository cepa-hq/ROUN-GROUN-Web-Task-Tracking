<?php
/**
 * Plugin Name: Roun Groun Request Centre
 * Description: Item-level cancellation and return selection, server review and verified RMA submission.
 * Version: 1.1.1
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce, wc-return-warrranty
 * Author: Roun Groun
 * License: GPL-2.0-or-later
 */
defined('ABSPATH') || exit;
require_once __DIR__.'/includes/policy.php';
require_once __DIR__.'/includes/repository.php';
require_once __DIR__.'/includes/admin.php';
require_once __DIR__.'/includes/refunds.php';
require_once __DIR__.'/includes/calm.php';
final class RGR_Centre {
    const VERSION='1.1.1';
    public static $error='';public static $review=null;
    public static function boot(){
        if(!class_exists('WooCommerce'))return;
        add_shortcode('rgr_request_centre',array(__CLASS__,'render'));
        add_action('wp_loaded',array(__CLASS__,'disable_legacy_writes'),1);
        add_action('template_redirect',array(__CLASS__,'route'),1);
        add_filter('woocommerce_my_account_my_orders_actions',array(__CLASS__,'actions'),PHP_INT_MAX,2);
        add_filter('woocommerce_get_cancel_order_url',array(__CLASS__,'cancel_url'),PHP_INT_MAX,2);
        add_filter('woocommerce_get_cancel_order_url_raw',array(__CLASS__,'cancel_url'),PHP_INT_MAX,2);
        add_filter('woocommerce_get_endpoint_url',array(__CLASS__,'endpoint'),90,4);
        add_filter('template_include',function($t){return self::mode()?__DIR__.'/templates/page.php':$t;},2000);
        add_action('wp_enqueue_scripts',array(__CLASS__,'assets'),180);
        add_filter('wcrw_create_warranty_request_error',function($errors){if(!is_admin())$errors[]='Please use the verified Roun Groun request pages.';return $errors;},PHP_INT_MAX);
        RGR_Admin::boot(); RGR_Refund::boot(); RGR_Calm::boot();
    }
    public static function types($mode){
        $settings=get_option('wcrw_basic',array());$enabled=$settings['default_return_request_type']??array('refund','replacement');$out=array();
        foreach($mode==='cancel'?array('cancel'=>'Cancellation'):array('refund'=>'Refund','replacement'=>'Replacement')as $k=>$label)if(in_array($k,$enabled,true))$out[$k]=$label;
        return $out;
    }
    public static function mode(){return is_page('cancellation-request')?'cancel':(is_page('return-request')?'return':'');}
    public static function url($mode,$id=0){$url=home_url($mode==='cancel'?'/cancellation-request/':'/return-request/');return $id?add_query_arg('order_id',(int)$id,$url):$url;}
    public static function cancel_url($url,$order){return $order instanceof WC_Order?self::url('cancel',$order->get_id()):$url;}
    public static function endpoint($url,$endpoint,$value,$permalink){
        // WCRW also builds links by appending an ID to an empty endpoint URL.
        // Keep that legacy BASE intact; route() redirects its finished URL.
        return $endpoint==='new-warranty-request'&&absint($value)>0?self::url('return',absint($value)):$url;
    }
    public static function legacy_return_path($uri){
        $base=rtrim((string)wp_parse_url(wc_get_page_permalink('myaccount'),PHP_URL_PATH),'/');
        $path=(string)wp_parse_url($uri,PHP_URL_PATH);$matches=array();
        return preg_match('~^'.preg_quote($base,'~').'/new-warranty-request(?:/([0-9]+))?/?$~',$path,$matches)?(int)($matches[1]??0):null;
    }
    public static function actions($actions,$order){
        foreach(array('cancel','cancel_order_request')as $key)if(isset($actions[$key]))$actions[$key]['url']=self::url('cancel',$order->get_id());
        if(isset($actions['cancel_order_request']))unset($actions['cancel']);
        if(isset($actions['warranty_request']))$actions['warranty_request']['url']=self::url('return',$order->get_id());
        if(!isset($actions['warranty_request'])&&$order->has_status(array('processing','completed','shipped','in-transit','delivered'))&&self::types('return'))$actions['warranty_request']=array('url'=>self::url('return',$order->get_id()),'name'=>'Request return');
        return $actions;
    }
    public static function disable_legacy_writes(){
        if(is_admin())return;
        // INCIDENT RG-9: WCRW's cancel GET immediately wrote a whole-order
        // request; core WC's GET can cancel immediately. Disable these PRESENTERS
        // and old customer submission handlers, retaining all admin RMA handlers.
        // Early registration matters: core cancel_order runs at wp_loaded20.
        remove_action('wp_loaded',array('WC_Form_Handler','cancel_order'),20);
        global $wp_filter;
        if(isset($wp_filter['template_redirect']))foreach($wp_filter['template_redirect']->callbacks as $priority=>$callbacks)foreach($callbacks as $callback){$f=$callback['function'];if(is_array($f)&&$f[0] instanceof WCRW_Handle_Form&&in_array($f[1],array('handle_cancel_order_request','handle_request_submission_form'),true))remove_action('template_redirect',$f,$priority);}
    }
    public static function route(){
        // Old bookmarks/buttons/POSTs all land on selection; no state-changing
        // parameters, nonce or posted item arrays are forwarded. Fresh review required.
        $legacy_cancel=isset($_REQUEST['cancel_order'])||(isset($_GET['action'])&&is_string($_GET['action'])&&$_GET['action']==='wcrw_cancel_order');
        // Custom WP rewrite endpoints are not always in WC_Query's endpoint
        // registry, so is_wc_endpoint_url alone misses historical RMA links.
        $legacy_id=self::legacy_return_path($_SERVER['REQUEST_URI']??'');
        $legacy_return=$legacy_id!==null||is_wc_endpoint_url('new-warranty-request')||isset($_POST['wcrw_request_form_nonce']);
        if($legacy_cancel||$legacy_return){$id=absint($_REQUEST['order_id']??($legacy_id!==null?$legacy_id:get_query_var('new-warranty-request')));wp_safe_redirect(self::url($legacy_cancel?'cancel':'return',$id),303);exit;}
        $mode=self::mode();if(!$mode)return;
        nocache_headers();header('X-Robots-Tag: noindex, nofollow',true);
        if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')return;
        $id=absint($_POST['order_id']??0);$user=get_current_user_id();
        if(!self::verified_nonce($mode,$id,$_POST['rgr_nonce']??null)){self::$error='Your secure form expired. Refresh the page and try again.';return;}
        $order=wc_get_order($id);if(!$order||(int)$order->get_customer_id()!==$user){self::$error='This order is not available in your account.';return;}
        if(($_POST['rgr_step']??'')==='review'){
            $selection=array();$posted=isset($_POST['selected'])&&is_array($_POST['selected'])?wp_unslash($_POST['selected']):array();$quantities=isset($_POST['quantity'])&&is_array($_POST['quantity'])?wp_unslash($_POST['quantity']):array();
            foreach($posted as $key=>$checked)if(is_scalar($checked)&&(string)$checked==='1')$selection[$key]=$quantities[$key]??0;
            $reason=isset($_POST['reason'])&&is_string($_POST['reason'])?sanitize_textarea_field(wp_unslash($_POST['reason'])):'';
            $type=isset($_POST['request_type'])&&is_string($_POST['request_type'])?sanitize_key($_POST['request_type']):'';
            $ctx=RGR_Policy::context($order,$mode,RGR_Repository::reserved($id));$clean=RGR_Policy::validate($ctx,$user,$selection,$reason,true);
            if(is_wp_error($clean)){self::$error=$clean->get_error_message();return;}
            if(!isset(self::types($mode)[$type])){self::$error='Choose an available request type.';return;}
            $refund=null;if($mode==='return'&&$type==='refund'){try{$refund=RGR_Refund::seal(RGR_Refund::validate(wp_unslash($_POST),$order),'draft:'.$id.':'.$user);}catch(Throwable $e){self::$error=$e->getMessage();return;}}
            $token=wp_generate_uuid4();$payload=array('order_id'=>$id,'user'=>$user,'mode'=>$mode,'selection'=>$clean,'reason'=>$reason,'type'=>$type,'refund'=>$refund,'token'=>$token,'expires'=>time()+15*MINUTE_IN_SECONDS);
            set_transient(self::token_key($token),$payload,15*MINUTE_IN_SECONDS);self::$review=$payload;
        }elseif(($_POST['rgr_step']??'')==='confirm'){
            $token=isset($_POST['review_token'])&&is_string($_POST['review_token'])?sanitize_text_field(wp_unslash($_POST['review_token'])):'';$payload=get_transient(self::token_key($token));
            if(!$payload||$payload['user']!==$user||$payload['order_id']!==$id||$payload['mode']!==$mode||$payload['expires']<time()){self::$error='This review expired. Select your products and review the request again.';return;}
            if(($_POST['confirm_items']??'')!=='1'){self::$error='Please confirm that these are the products and quantities you want to request.';self::$review=$payload;return;}
            $refund=null;if(!empty($payload['refund'])){try{$refund=RGR_Refund::open($payload['refund'],'draft:'.$id.':'.$user);}catch(Throwable $e){self::$error='Your secure refund review expired. Please start again.';return;}}
            $saved=RGR_Repository::commit($order,$mode,$payload['selection'],$payload['reason'],$token,$payload['type'],$refund);
            if(is_wp_error($saved)){self::$error=$saved->get_error_message();return;}
            // Keep the review token briefly so a network retry resolves to the
            // existing request ID under the per-order lock, never another write.
            wp_safe_redirect(add_query_arg('request_sent',$saved,self::url($mode,$id)),303);exit;
        }else self::$error='Select your products before confirming a request.';
    }
    public static function nonce($mode,$id){return 'rgr:'.$mode.':'.$id.':'.get_current_user_id();}
    public static function verified_nonce($mode,$id,$value){return get_current_user_id()>0&&is_string($value)&&wp_verify_nonce(wp_unslash($value),self::nonce($mode,$id))!==false;}
    public static function token_key($token){return 'rgr_review_'.hash('sha256',get_current_user_id().'|'.$token);}
    public static function assets(){
        if(!self::mode()&&!is_account_page())return;
        wp_enqueue_style('rgr-centre',plugins_url('assets/centre.css',__FILE__),array(),self::VERSION);
        if(self::mode())wp_enqueue_script('rgr-centre',plugins_url('assets/centre.js',__FILE__),array(),self::VERSION,true);
    }
    public static function icon($type='shield'){
        $p=$type==='arrow'?'<path d="M20 12H4m6-6-6 6 6 6"/>':($type==='cancel'?'<path d="m8 8 8 8M16 8l-8 8"/><circle cx="12" cy="12" r="9"/>':($type==='return'?'<path d="M7 8h9a5 5 0 0 1 0 10h-4M7 8l4-4M7 8l4 4"/>':'<path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6Z"/><path d="m8 12 3 3 5-6"/>'));
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$p.'</svg>';
    }
    public static function render(){
        $mode=self::mode()?:'return';$id=absint($_POST['order_id']??$_GET['order_id']??0);$user=get_current_user_id();$order=$id?wc_get_order($id):null;$ctx=null;$success=0;$fixture=false;
        if($order&&(!$user||(int)$order->get_customer_id()!==$user)){$order=null;self::$error='This order is not available in your account.';}
        if($order)$ctx=RGR_Policy::context($order,$mode,RGR_Repository::reserved($id));
        if($user&&$order&&isset($_GET['request_sent'])){global $wpdb;$success=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}wcrw_warranty_requests WHERE id=%d AND order_id=%d AND customer_id=%d",absint($_GET['request_sent']),$id,$user));}
        ob_start();include __DIR__.'/templates/centre.php';return ob_get_clean();
    }
}
add_action('plugins_loaded',array('RGR_Centre','boot'),110);
