<?php
defined('ABSPATH') || exit;
final class RGCO_Engine {
 static $attempt=0,$code='',$source='',$context=null,$rest=false;
 static function boot(){
  add_action('wc_ajax_apply_coupon',[__CLASS__,'native_attempt'],0);
  add_action('wp_ajax_woocommerce_apply_coupon',[__CLASS__,'native_attempt'],0);
  add_action('wp_ajax_nopriv_woocommerce_apply_coupon',[__CLASS__,'native_attempt'],0);
  add_action('wp_ajax_rgcs_cart',[__CLASS__,'cart_attempt'],0);
  add_action('wp_ajax_nopriv_rgcs_cart',[__CLASS__,'cart_attempt'],0);
  add_action('wp_loaded',[__CLASS__,'form_attempt'],15);
  add_filter('rest_request_before_callbacks',[__CLASS__,'rest_before'],10,3);
  add_filter('rest_request_after_callbacks',[__CLASS__,'rest_after'],10,3);
  add_action('woocommerce_applied_coupon',[__CLASS__,'applied']);
  add_action('shutdown',[__CLASS__,'finish_attempt']);
  add_filter('woocommerce_coupon_is_valid',[__CLASS__,'valid'],20,3);
  add_action('woocommerce_checkout_order_created',[__CLASS__,'reserve'],20);
  add_action('woocommerce_store_api_checkout_order_processed',[__CLASS__,'reserve'],20);
  add_action('woocommerce_before_pay_action',[__CLASS__,'pay'],20);
  add_action('woocommerce_checkout_order_exception',function($o){RGCO_Storage::release($o->get_id());});
  add_action('woocommerce_payment_complete',[__CLASS__,'paid'],20);
  add_action('woocommerce_order_status_changed',[__CLASS__,'status'],20,4);
 }
 static function managed($c){return $c && $c->get_meta('_rgco_enabled')==='yes';}
 /** Trust the server's peer address, never arbitrary client-supplied forwarding headers.
  * A reverse-proxy installation MUST normalize REMOTE_ADDR at the server/trusted proxy,
  * or provide rgco_client_ip from a verified proxy configuration. VPNs are not human identity.
  */
 static function ip(){
  $ip=apply_filters('rgco_client_ip',$_SERVER['REMOTE_ADDR']??'');
  return is_string($ip) && filter_var($ip,FILTER_VALIDATE_IP) ? strtolower((string)inet_ntop(inet_pton($ip))) : '';
 }
 static function start($code,$source,$skip_rate=false){
  if(self::$attempt || !is_string($code))return true;
  $code=wc_format_coupon_code(substr($code,0,200));if(!$code)return true;
  self::$code=$code;self::$source=$source;
  try{
   $allowed=$skip_rate || RGCO_Storage::allow_attempt(self::ip()?:'unavailable');
   if(!$allowed)return false;
   self::$attempt=RGCO_Storage::log($code,'attempted',$source);
  }catch(Throwable $e){return false;}
  return true;
 }
 static function native_attempt(){
  if(!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['security']??'')),'apply-coupon'))return;
  if(!self::start(wp_unslash($_POST['coupon_code']??''),'checkout')){
   wc_add_notice('Too many coupon attempts, or coupon service unavailable. Please wait a minute and try again.','error');wc_print_notices();wp_die();
  }
 }
 static function cart_attempt(){
  if(($_POST['operation']??'')!=='coupon' || !class_exists('RGCS_Cart'))return;
  RGCS_Cart::init();
  if(!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce']??'')),RGCS_Cart::nonce_action()))return;
  $hash=sanitize_text_field(wp_unslash($_POST['hash']??''));
  if($hash && $hash!==WC()->cart->get_cart_hash())return;
  if(!self::start(wp_unslash($_POST['code']??''),'cart'))wp_send_json_error(['message'=>'Too many coupon attempts, or coupon service unavailable. Please wait a minute and try again.'],429);
 }
 static function form_attempt(){
  if(empty($_POST['apply_coupon']) || !is_string($_POST['coupon_code']??null))return;
  $nonce=wp_unslash($_POST['woocommerce-cart-nonce']??$_POST['_wpnonce']??'');
  if(!wp_verify_nonce($nonce,'woocommerce-cart'))return;
  if(!self::start(wp_unslash($_POST['coupon_code']),'cart-form')){wc_add_notice('Please wait a minute before trying another coupon.','error');unset($_POST['apply_coupon'],$_POST['coupon_code']);}
 }
 static function rest_before($response,$handler,$request){
  if($response!==null || $request->get_method()!=='POST' || !preg_match('#^/wc/store/v[0-9]+/cart/(apply-coupon|coupons)/?$#',$request->get_route()))return $response;
  // Store API loads its token-owned cart INSIDE get_response, after this WP filter.
  // Authenticate here; defer snapshot until its callback has loaded the correct session.
  $auth=wp_verify_nonce($request->get_header('Nonce')?:'','wc_store_api');
  $utils='Automattic\\WooCommerce\\StoreApi\\Utilities\\CartTokenUtils';
  if(!$auth && class_exists($utils))$auth=$utils::validate_cart_token($utils::get_request_cart_token());
  if(!$auth)return $response; // Woo emits its own nonce/token error, without logging private data.
  $code=$request->get_param('code');if(!is_string($code) || !$code)return $response;
  if(!RGCO_Storage::allow_attempt(self::ip()?:'unavailable'))return new WP_Error('rgco_rate_limit','Please wait a minute before trying another coupon.',['status'=>429]);
  self::$rest=wc_format_coupon_code(substr($code,0,200));return $response;
 }
 static function rest_after($response,$handler,$request){
  if(!self::$rest)return $response;
  $code=self::$rest;self::$rest=false;
  $bad=is_wp_error($response) || ($response instanceof WP_REST_Response && $response->get_status()>=400);
  if(!self::start($code,'store-api',true)){
   if(!$bad && WC()->cart){WC()->cart->remove_coupon($code);WC()->cart->calculate_totals();WC()->cart->set_session();}
   return new WP_Error('rgco_audit_unavailable','Coupon service unavailable. Please try again.',['status'=>503]);
  }
  if(self::$attempt){RGCO_Storage::outcome(self::$attempt,$bad?'rejected':'applied');self::$attempt=0;}
  return $response;
 }
 static function applied($code){if(self::$attempt && wc_is_same_coupon($code,self::$code)){RGCO_Storage::outcome(self::$attempt,'applied');self::$attempt=0;}}
 static function finish_attempt(){if(self::$attempt){RGCO_Storage::outcome(self::$attempt,'rejected');self::$attempt=0;}}
 static function window_error($c,$now=null){
  $now=$now??time();$from=(int)$c->get_meta('_rgco_start');$until=(int)$c->get_meta('_rgco_end');
  if($from && $now<$from)return 'This coupon is not open yet.';
  if($until && $now>=$until)return 'This coupon has ended.';
  return '';
 }
 static function identities($c,$order=null,$strict=false){
  $ids=[];$uid=$order?$order->get_customer_id():get_current_user_id();
  $email=$order?$order->get_billing_email():(WC()->customer?WC()->customer->get_billing_email():'');
  if(!$email && $uid){$user=get_userdata($uid);$email=$user?$user->user_email:'';}
  // Include both billing email and account email, closing alternate-billing-email reuse.
  if($c->get_meta('_rgco_once_account')==='yes'){
   if(!$uid)throw new RuntimeException('Please sign in to use this coupon.');
   $ids['account']=RGCO_Storage::hash('account',(string)$uid);
  }
  if($c->get_meta('_rgco_once_email')==='yes'){
   if(!$email && $strict)throw new RuntimeException('Enter a valid billing email to use this coupon.');
   if($email)$ids['email']=RGCO_Storage::hash('email',strtolower(trim($email)));
   if($uid && ($u=get_userdata($uid)) && strcasecmp($u->user_email,$email)!==0)$ids['accountmail']=RGCO_Storage::hash('email',strtolower(trim($u->user_email)));
  }
  if($c->get_meta('_rgco_once_ip')==='yes'){
   $ip=$order?$order->get_meta('_rgco_peer_ip'):self::ip();
   if(!$ip && $order)$ip=$order->get_customer_ip_address();
   if(!$ip && !($order && $order->get_meta('_rgco_ip_hash')))throw new RuntimeException('Your connection could not be verified for this coupon. Please contact the store.');
   // Order stores a keyed fingerprint only; never persist the raw peer IP in our metadata.
   $ids['ip']=$order && $order->get_meta('_rgco_ip_hash') ? $order->get_meta('_rgco_ip_hash') : RGCO_Storage::hash('ip',$ip);
  }
  return $ids;
 }
 static function stack_error($coupons){
  if(count($coupons)<2)return '';
  foreach($coupons as $c)if((self::managed($c) && $c->get_meta('_rgco_stack')!=='yes') || $c->get_individual_use())return 'This coupon cannot be combined with another coupon. Remove the other coupon first.';
  return '';
 }
 static function valid($valid,$coupon,$discounts){
  if(!$valid)return $valid;
  try{
   if(self::managed($coupon)){
    if($err=self::window_error($coupon))throw new RuntimeException($err);
    $object=$discounts->get_object();$context=self::$context?:($object instanceof WC_Order?$object:null);
    $ids=self::identities($coupon,$context,false);$own=$context?$context->get_id():0;
    // Permit checkout's own pending order to be retried, never somebody else's claim.
    if(!$own && WC()->session)$own=(int)WC()->session->get('order_awaiting_payment');
    if(RGCO_Storage::blocked($coupon->get_id(),$ids,$own))throw new RuntimeException('This coupon has already been used or is reserved by an unpaid order.');
   }
   if(!self::$context && $discounts->get_object() instanceof WC_Cart && WC()->cart){
    $all=WC()->cart->get_coupons();$all[$coupon->get_code()]=$coupon;
    $has_managed=false;foreach($all as $c)if(self::managed($c))$has_managed=true;
    if($has_managed && ($err=self::stack_error(array_values($all))))throw new RuntimeException($err);
   }
  }catch(Throwable $e){throw new Exception($e->getMessage(),109);}
  return true;
 }
 /** Called after Woo validation and before any gateway. Native order CRUD works with HPOS.
  * Reservation != redemption. Pending/on-hold (including COD processing) reserve a place,
  * failed/cancelled UNPAID orders release it, a payment completion consumes it permanently.
  * Never release a consumed claim on refund: a successful checkout already occurred.
  */
 static function reserve($order){
  if(!$order instanceof WC_Order)return;
  $all=[];$claims=$order->get_meta('_rgco_claims')?:[];$rules=array_fill_keys($order->get_meta('_rgco_managed')?:[],[]);self::$context=$order;
  try{
   foreach($order->get_coupon_codes() as $code){$c=new WC_Coupon($code);$all[]=$c;if(!self::managed($c))continue;
    if($err=self::window_error($c))throw new RuntimeException($err);
    if(!$order->get_meta('_rgco_ip_hash')){
     $ip=self::ip();if($ip)$order->update_meta_data('_rgco_ip_hash',RGCO_Storage::hash('ip',$ip));
    }
    $ids=self::identities($c,$order,true);
    foreach($ids as $scope=>$hash){if($scope==='accountmail')$scope='email';$claims[]=[$c->get_id(),$scope,$hash];}
    $rules[$c->get_id()]=$ids;
   }
   if(!$rules)return;
   if($err=self::stack_error($all))throw new RuntimeException($err);
   $claims=array_values(array_unique($claims,SORT_REGULAR));
   RGCO_Storage::claim($claims,$order->get_id());
   foreach($order->get_items('coupon') as $item){$cid=wc_get_coupon_id_by_code($item->get_code());if(isset($rules[$cid])){$item->update_meta_data('_rgco_coupon_id',$cid);$item->save();}}
   $order->update_meta_data('_rgco_claims',$claims);$order->update_meta_data('_rgco_managed',array_keys($rules));$order->save_meta_data();
  }finally{self::$context=null;}
 }
 static function pay($order){
  try{self::reserve($order);}catch(Throwable $e){wc_add_notice($e->getMessage(),'error');wp_safe_redirect($order->get_checkout_payment_url());exit;}
 }
 static function paid($id){$order=wc_get_order($id);if($order)self::consume($order);}
 static function status($id,$from,$to,$order){
  if($to==='completed' || ($to==='processing' && $order->get_payment_method()!=='cod' && $order->get_date_paid()))self::consume($order);
  elseif(in_array($to,['failed','cancelled'],true) && !$order->get_date_paid())RGCO_Storage::release($id);
 }
 static function consume($order){
  $managed=$order->get_meta('_rgco_managed');if(!$managed)return;
  try{
   RGCO_Storage::claim($order->get_meta('_rgco_claims')?:[],$order->get_id(),true);
   global $wpdb;$table=RGCO_Storage::table('redemptions');
   foreach($order->get_items('coupon') as $item){$coupon_id=(int)$item->get_meta('_rgco_coupon_id')?:wc_get_coupon_id_by_code($item->get_code());if(!in_array($coupon_id,array_map('intval',$managed),true))continue;
    $ok=$wpdb->query($wpdb->prepare("INSERT IGNORE INTO $table (coupon_id,order_id,created_at,discount,currency) VALUES (%d,%d,%s,%s,%s)",$coupon_id,$order->get_id(),gmdate('Y-m-d H:i:s'),$item->get_discount(),$order->get_currency()));
    if($ok===false)throw new RuntimeException('Redemption audit write failed.');
   }
   $order->update_meta_data('_rgco_consumed','yes');$order->save_meta_data();
  }catch(Throwable $e){
   // Rare late-payment race: a failed/cancelled reservation may have been released,
   // then another order paid. Never overwrite its claim or silently accept duplicate use.
   // Payment happened outside this plugin; retain funds/order and flag for admin resolution.
   if(!$order->get_meta('_rgco_conflict')){
    $order->update_meta_data('_rgco_conflict','yes');$order->save_meta_data();
    $order->add_order_note('Coupon Studio: payment received but identity claim conflict/storage failure. Review this order before fulfilment. Original coupon claims preserved.');
    if(!$order->has_status(['refunded','cancelled']))$order->update_status('on-hold','Coupon redemption requires manual review.');
   }
  }
 }
}
