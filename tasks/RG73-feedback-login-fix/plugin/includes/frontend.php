<?php
namespace RGFS;
defined('ABSPATH') || exit;

final class Frontend {
    public static function boot():void {
        add_action('init',[self::class,'rewrite']);
        add_filter('query_vars',function($v){$v[]='rgfs_form';return $v;});
        add_action('template_redirect',[self::class,'gate'],1);
        add_filter('template_include',function($path){return self::current()?ROOT.'/templates/page.php':$path;},1000);
        add_filter('redirect_canonical',function($url){return self::current()?false:$url;});
        add_action('wp_enqueue_scripts',[self::class,'assets'],11000);
        add_action('rest_api_init',[self::class,'routes']);
        add_filter('wp_robots',function($r){if(self::current()){$r['noindex']=true;$r['nofollow']=true;}return $r;});
        add_filter('body_class',function($c){if(self::current())$c[]='rgfs-page';return $c;});
        add_filter('pre_get_document_title',function($t){return self::current()?'General feedback mk1 · Roun Groun':$t;});
    }
    public static function rewrite():void{add_rewrite_rule('^feedback/([a-z0-9-]+)/?$','index.php?rgfs_form=$matches[1]','top');}
    public static function current():?array{return Forms::get((string)get_query_var('rgfs_form',''));}
    public static function url($slug='general-feedback-mk1'):string{return get_option('permalink_structure')?home_url('/feedback/'.$slug.'/'):add_query_arg('rgfs_form',$slug,home_url('/'));}
    public static function gate():void {
        if(!self::current())return;
        if(!defined('DONOTCACHEPAGE'))define('DONOTCACHEPAGE',true);
        nocache_headers();header('Cache-Control: private, no-store, max-age=0');header('X-Robots-Tag: noindex, nofollow',true);header('Referrer-Policy: same-origin');
        if(!is_user_logged_in()){
            // RG73: use the existing customer-facing account login, then resume this survey.
            // An existing valid session already satisfies login; never log users out.
            $back=self::url(self::current()['slug']);
            if(isset($_GET['order_id']))$back=add_query_arg('order_id',absint($_GET['order_id']),$back);
            if(isset($_GET['campaign']))$back=add_query_arg('campaign',substr(sanitize_key(wp_unslash($_GET['campaign'])),0,64),$back);
            Customer_Login::begin($back);
        }
        if(!empty($_GET['order_id'])){
            $order=function_exists('wc_get_order')?wc_get_order(absint($_GET['order_id'])):null;
            if(!$order||(int)$order->get_customer_id()!==get_current_user_id())wp_die('This survey link is for a different order. Please use the general feedback link or sign in to the account that placed this order.','Order not available',['response'=>403]);
        }
        global $wp_query;$wp_query->is_404=false;status_header(200);
    }
    public static function token($uid,$form,$start):string{return hash_hmac('sha256',$uid.'|'.$form['slug'].'|'.$form['version'].'|'.$start,wp_salt('auth'));}
    public static function context($form){
        $uid=get_current_user_id();$user=wp_get_current_user();$orders=Store::orders($uid,25);$order=null;
        $requested=isset($_GET['order_id'])?absint($_GET['order_id']):0;
        if($requested){$order=function_exists('wc_get_order')?wc_get_order($requested):null;if(!$order||(int)$order->get_customer_id()!==$uid)$order=null;}
        if(!$order){foreach($orders as $o)if(Store::gift_order($o)){$order=$o;break;}$order=$order?:($orders[0]??null);}
        $old=Store::existing($uid,$form);$start=time();
        return ['form'=>Forms::client($form),'nonce'=>wp_create_nonce('wp_rest'),'endpoint'=>rest_url('rgfs/v1/responses'),'started'=>$start,'token'=>self::token($uid,$form,$start),'order_id'=>$order?$order->get_id():0,'campaign'=>isset($_GET['campaign'])?substr(sanitize_key(wp_unslash($_GET['campaign'])),0,64):'','is_test'=>Admin::can(),'existing'=>$old?['id'=>(int)$old['id'],'answers'=>json_decode($old['payload'],true),'message'=>self::receipt($old['id'])]:null];
    }
    public static function receipt($entry):string{
        $r=Store::reward($entry);
        if(!$r)return 'Your feedback is saved.';
        if($r['state']==='test')return 'Staff test saved. This entry does not count in customer reports or gift fulfilment.';
        if($r['state']==='waiting_order')return 'Your feedback is saved. Your gift is reserved for your next delivery.';
        if($r['state']==='privacy_erased')return 'Your feedback is saved.';
        return 'Your feedback is saved. Your surprise gift is linked to your delivery.';
    }
    public static function assets():void{
        $form=self::current();if(!$form)return;
        wp_enqueue_style('rgfs-form',plugins_url('assets/form.css',FILE),[],VERSION);
        wp_enqueue_script('rgfs-form',plugins_url('assets/form.js',FILE),[],VERSION,true);
        wp_add_inline_script('rgfs-form','const RGFS='.wp_json_encode(self::context($form),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).';','before');
    }
    public static function signed_in(){return is_user_logged_in()?true:new \WP_Error('rgfs_login','Please sign in to continue.',['status'=>401]);}
    public static function csrf($request){
        if(!is_user_logged_in())return self::signed_in();
        $nonce=$request->get_header('X-WP-Nonce')?:$request->get_param('_wpnonce');
        return is_string($nonce)&&wp_verify_nonce($nonce,'wp_rest')?true:new \WP_Error('rgfs_session','Your session needs a refresh. Reload this page and try again.',['status'=>403]);
    }
    public static function routes():void {
        register_rest_route('rgfs/v1','/context',['methods'=>'GET','permission_callback'=>[self::class,'signed_in'],'callback'=>function($req){$f=Forms::get(sanitize_key($req->get_param('form')?:'general-feedback-mk1'));return $f?self::response(self::context($f)):new \WP_Error('rgfs_form','Form not found.',['status'=>404]);}]);
        register_rest_route('rgfs/v1','/responses',['methods'=>'POST','permission_callback'=>[self::class,'csrf'],'callback'=>[self::class,'submit']]);
    }
    public static function response($data){$r=new \WP_REST_Response($data);$r->header('Cache-Control','private, no-store, max-age=0');return $r;}
    public static function submit($req){
        if(strlen($req->get_body())>16000)return new \WP_Error('rgfs_size','Please reload the form.',['status'=>413]);
        $f=Forms::get((string)$req->get_param('form'));$uid=get_current_user_id();
        if(!$f||(int)$req->get_param('version')!==$f['version']||$req->get_param('schema_hash')!==$f['hash'])return new \WP_Error('rgfs_version','This form has changed. Please reload it.',['status'=>409]);
        $snapshot=Forms::historical($f['slug'],$f['version']);
        if(!$snapshot||$snapshot['hash']!==$f['hash'])return new \WP_Error('rgfs_instrument','This form needs an update. Please try again later.',['status'=>503]);
        if($req->get_param('order_id')){
            $o=function_exists('wc_get_order')?wc_get_order(absint($req->get_param('order_id'))):null;
            if(!$o||(int)$o->get_customer_id()!==$uid)return new \WP_Error('rgfs_order','This order does not belong to your account.',['status'=>403]);
        }
        $start=$req->get_param('started');$token=$req->get_param('token');
        if(!is_numeric($start)||$start>time()||time()-(int)$start>86400||!is_string($token)||!hash_equals(self::token($uid,$f,(int)$start),$token))return new \WP_Error('rgfs_expired','Your session needs a refresh. Reload this page.',['status'=>403]);
        if($req->get_param('website'))return new \WP_Error('rgfs_invalid','Please reload the form.',['status'=>422]);
        $key='rgfs_rate_'.$uid;$rate=(int)get_transient($key);if($rate>=30)return new \WP_Error('rgfs_rate','Please wait a minute and try again.',['status'=>429]);set_transient($key,$rate+1,MINUTE_IN_SECONDS);
        $answers=Forms::validate($f,$req->get_param('answers'));if(is_wp_error($answers))return $answers;
        $device=$req->get_param('device');if(!in_array($device,['phone','tablet','desktop'],true))$device='unknown';
        $meta=['elapsed'=>max(0,time()-(int)$start),'device'=>$device,'campaign'=>substr(sanitize_key((string)$req->get_param('campaign')),0,64),'order_id'=>absint($req->get_param('order_id')),'is_test'=>Admin::can()];
        $saved=Store::submit($uid,$f,$answers,$meta);if(is_wp_error($saved))return $saved;
        return self::response($saved+['message'=>self::receipt($saved['id'])]);
    }
}
