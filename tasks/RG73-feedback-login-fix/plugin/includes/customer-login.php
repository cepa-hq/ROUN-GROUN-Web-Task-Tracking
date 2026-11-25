<?php
namespace RGFS;
defined('ABSPATH') || exit;

/** RG73: transport ONLY a survey destination through the existing customer login.
 * Credentials, nonces, account creation and authentication remain owned by Woo /
 * Customer Studio / Nextend. No credentials or user IDs are stored in this cookie.
 */
final class Customer_Login {
    const COOKIE='rgfs_survey_return';
    const TTL=1800;
    public static function boot():void {
        add_action('init',[self::class,'prepare'],20);
        add_action('template_redirect',[self::class,'resume'],0);
        add_action('woocommerce_login_form_start',[self::class,'fields'],30);
        add_action('woocommerce_register_form_start',[self::class,'fields'],30);
        add_filter('woocommerce_login_redirect',[self::class,'after_login'],100,2);
        add_filter('woocommerce_registration_redirect',[self::class,'after_registration'],100);
        // Installed Nextend final redirect hook, after its fixed/default decisions.
        // Keep other providers extensible without editing their source or OAuth state.
        foreach(['google','facebook','twitter'] as $provider)
            add_filter('nsl_'.$provider.'last_location_redirect',[self::class,'after_social'],100,2);
    }
    private static function same_origin(array $url):bool {
        $home=wp_parse_url(home_url('/'));
        return !isset($url['user'])&&!isset($url['pass'])
            &&strtolower($url['scheme']??'')===strtolower($home['scheme']??'')
            &&strtolower($url['host']??'')===strtolower($home['host']??'')
            &&($url['port']??null)===($home['port']??null);
    }
    /** Canonical allowlist, not merely an allowed-host check. Only registered
     * survey routes and bounded order/campaign context may survive authentication.
     * Reject array values, foreign origins, admin paths and unknown instruments. */
    public static function destination($value):string {
        if(!is_string($value)||strlen($value)>2048||preg_match('/[\x00-\x20\\\\]/',$value))return '';
        $url=wp_parse_url($value);
        if(!is_array($url)||!self::same_origin($url)||isset($url['fragment']))return '';
        parse_str($url['query']??'',$query);$chosen=null;
        foreach(Forms::all() as $form){
            $canonical=wp_parse_url(Frontend::url($form['slug']));
            if(rtrim($url['path']??'','/')!==rtrim($canonical['path']??'','/'))continue;
            if(get_option('permalink_structure')){
                if(isset($query['rgfs_form'])&&$query['rgfs_form']!==$form['slug'])continue;
            }elseif(($query['rgfs_form']??null)!==$form['slug'])continue;
            $chosen=$form;break;
        }
        if(!$chosen)return '';
        $args=[];
        if(isset($query['order_id'])){
            if(!is_string($query['order_id'])||!preg_match('/^[0-9]{1,18}$/D',$query['order_id'])||absint($query['order_id'])<1)return '';
            $args['order_id']=absint($query['order_id']);
        }
        if(isset($query['campaign'])){
            if(!is_string($query['campaign']))return '';
            $args['campaign']=substr(sanitize_key($query['campaign']),0,64);
        }
        return add_query_arg($args,Frontend::url($chosen['slug']));
    }
    public static function account():string {
        if(!function_exists('wc_get_page_permalink'))return '';
        $url=wc_get_page_permalink('myaccount');$parts=wp_parse_url($url);
        return is_array($parts)&&self::same_origin($parts)?$url:'';
    }
    private static function account_location($url):bool {
        if(!is_string($url))return false;$parts=wp_parse_url($url);$account=wp_parse_url(self::account());
        if(!is_array($parts)||!is_array($account)||!self::same_origin($parts))return false;
        $path=rtrim($parts['path']??'','/');$base=rtrim($account['path']??'','/');
        return $path===$base||($base!==''&&strpos($path,$base.'/')===0);
    }
    private static function at_account():bool {
        $uri=$_SERVER['REQUEST_URI']??'';
        if(!is_string($uri))return false;
        $path=rtrim(wp_parse_url($uri,PHP_URL_PATH)?:'','/');
        $base=rtrim(wp_parse_url(self::account(),PHP_URL_PATH)?:'','/');
        return $base!==''&&($path===$base||strpos($path,$base.'/')===0);
    }
    public static function signed(string $url,int $expires):string {
        $payload=rtrim(strtr(base64_encode(wp_json_encode(['url'=>$url,'expires'=>$expires])),'+/','-_'),'=');
        return $payload.'.'.hash_hmac('sha256',$payload,wp_salt('auth'));
    }
    public static function unpack($value):string {
        if(!is_string($value)||strlen($value)>4096)return '';
        $pieces=explode('.',$value);if(count($pieces)!==2||!hash_equals(hash_hmac('sha256',$pieces[0],wp_salt('auth')),$pieces[1]))return '';
        $data=json_decode(base64_decode(strtr($pieces[0],'-_','+/'),true)?:'',true);
        if(!is_array($data)||!is_int($data['expires']??null)||$data['expires']<time()||$data['expires']>time()+self::TTL)return '';
        return self::destination($data['url']??null);
    }
    private static function cookie(string $value,int $expires):void {
        if(headers_sent())return;
        $path=wp_parse_url(home_url('/'),PHP_URL_PATH)?:'/';
        setcookie(self::COOKIE,$value,['expires'=>$expires,'path'=>$path,'secure'=>is_ssl(),'httponly'=>true,'samesite'=>'Lax']);
    }
    public static function remember(string $url):void {
        $value=self::signed($url,time()+self::TTL);self::cookie($value,time()+self::TTL);$_COOKIE[self::COOKIE]=$value;
    }
    public static function clear():void {self::cookie('',time()-3600);unset($_COOKIE[self::COOKIE]);}
    public static function pending(bool $social=false):string {
        foreach([$_POST,$_GET] as $source){
            if(isset($source['rgfs_return']))return self::destination(wp_unslash($source['rgfs_return']));
        }
        return $social||self::at_account()?self::unpack($_COOKIE[self::COOKIE]??null):'';
    }
    private static function no_cache():void {
        if(!defined('DONOTCACHEPAGE'))define('DONOTCACHEPAGE',true);
        nocache_headers();
    }
    public static function prepare():void {
        // Runs before native wp_loaded login handling, but does not process a login.
        if(!self::at_account())return;$target=self::pending();if(!$target)return;
        self::no_cache();
        if(!is_user_logged_in())self::remember($target);
    }
    public static function begin(string $target):void {
        $target=self::destination($target);$account=self::account();
        if(!$target||!$account)wp_die('Customer sign-in is unavailable. Please try again later.','Sign-in unavailable',['response'=>503]);
        self::remember($target);
        wp_safe_redirect(add_query_arg('rgfs_return',$target,$account));exit;
    }
    public static function fields():void {
        $target=self::pending();if(!$target)return;
        echo '<input type="hidden" name="rgfs_return" value="'.esc_attr($target).'"><input type="hidden" name="redirect" value="'.esc_attr($target).'">';
    }
    public static function after_login($redirect,$user){
        // This filter is called by Woo ONLY after wp_signon succeeded. Never
        // authenticate a user here or route failed credential checks to a survey.
        if(is_wp_error($user)||!is_object($user)||empty($user->ID))return $redirect;
        return self::after_registration($redirect);
    }
    public static function after_registration($redirect){
        $target=self::pending();if(!$target)return $redirect;self::clear();return $target;
    }
    public static function after_social($redirect,$requested=''){
        if(!is_user_logged_in())return $redirect;
        // An explicit unrelated destination (including wp-admin) keeps ownership.
        if($requested&&!self::account_location($requested)&&!self::destination($requested))return $redirect;
        $target=self::pending(true);if(!$target)return $redirect;self::clear();return $target;
    }
    public static function resume():void {
        if(is_user_logged_in()&&self::at_account()){
            $target=self::pending();if(!$target)return;self::no_cache();self::clear();wp_safe_redirect($target);exit;
        }
        if(Frontend::current()&&is_user_logged_in()&&isset($_COOKIE[self::COOKIE]))self::clear();
    }
}
