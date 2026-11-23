<?php
defined('ABSPATH') || exit;
/** Private durable storage. No uninstall deletion: claims/analytics survive plugin updates and staging publication. */
final class RGCO_Storage {
 static function table($name){global $wpdb; return $wpdb->prefix.'rgco_'.$name;}
 static function key(){
  $key=get_option('rgco_secret');
  if(!$key || strlen((string)base64_decode($key,true))!==32) throw new RuntimeException('Coupon security key unavailable. Contact the store.');
  return base64_decode($key,true);
 }
 static function hash($scope,$value){return hash_hmac('sha256',$scope.'|'.$value,self::key());}
 static function encrypt($data){
  $iv=random_bytes(12);$tag='';$plain=wp_json_encode($data,JSON_INVALID_UTF8_SUBSTITUTE);
  $cipher=openssl_encrypt($plain,'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,$iv,$tag,'rgco-v1');
  if($cipher===false)throw new RuntimeException('Coupon analytics encryption unavailable.');
  return base64_encode($iv.$tag.$cipher);
 }
 static function decrypt($data){
  $raw=base64_decode($data,true);if(!$raw || strlen($raw)<29)return [];
  $text=openssl_decrypt(substr($raw,28),'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16),'rgco-v1');
  return $text ? (json_decode($text,true)?:[]) : [];
 }
 static function install(){
  if(!function_exists('openssl_encrypt') || !in_array('aes-256-gcm',openssl_get_cipher_methods(),true)) wp_die('Coupon Studio requires OpenSSL AES-256-GCM.');
  if(!get_option('rgco_secret')){
   global $wpdb;
   foreach(['attempts','claims','redemptions'] as $t){$table=self::table($t);if($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table))))wp_die('Existing Coupon Studio data has no security key. Restore rgco_secret from the same private database backup before activating.');}
   add_option('rgco_secret',base64_encode(random_bytes(32)),'',false);
  }
  self::key();
  global $wpdb;require_once ABSPATH.'wp-admin/includes/upgrade.php';$collate=$wpdb->get_charset_collate();
  $attempts=self::table('attempts');$claims=self::table('claims');$rate=self::table('rate');$redemptions=self::table('redemptions');
  dbDelta("CREATE TABLE $attempts (
 id bigint unsigned NOT NULL AUTO_INCREMENT,
 created_at datetime NOT NULL,
 coupon_id bigint unsigned NOT NULL DEFAULT 0,
 fingerprint char(64) NOT NULL,
 outcome varchar(24) NOT NULL,
 source varchar(24) NOT NULL,
 user_id bigint unsigned NOT NULL DEFAULT 0,
 email_hash char(64) NOT NULL DEFAULT '',
 payload longtext NOT NULL,
 PRIMARY KEY  (id),
 KEY coupon_time (coupon_id,created_at),
 KEY created_at (created_at),
 KEY email_hash (email_hash)
 ) ENGINE=InnoDB $collate;");
  dbDelta("CREATE TABLE $claims (
 coupon_id bigint unsigned NOT NULL,
 scope varchar(12) NOT NULL,
 identity_hash char(64) NOT NULL,
 order_id bigint unsigned NOT NULL,
 state varchar(12) NOT NULL,
 updated_at datetime NOT NULL,
 PRIMARY KEY  (coupon_id,scope,identity_hash),
 KEY order_id (order_id)
 ) ENGINE=InnoDB $collate;");
  dbDelta("CREATE TABLE $redemptions (
 coupon_id bigint unsigned NOT NULL,
 order_id bigint unsigned NOT NULL,
 created_at datetime NOT NULL,
 discount decimal(26,8) NOT NULL DEFAULT 0,
 currency varchar(8) NOT NULL,
 PRIMARY KEY  (coupon_id,order_id),
 KEY created_at (created_at)
 ) ENGINE=InnoDB $collate;");
  dbDelta("CREATE TABLE $rate (
 bucket char(64) NOT NULL,
 hits int unsigned NOT NULL DEFAULT 1,
 expires_at bigint unsigned NOT NULL,
 PRIMARY KEY  (bucket),
 KEY expires_at (expires_at)
 ) ENGINE=InnoDB $collate;");
  foreach([$attempts,$claims,$rate,$redemptions] as $table){
   $row=$wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name = %s',$table));
   if(!$row || strtolower($row->Engine)!=='innodb') wp_die('Coupon Studio requires InnoDB storage for atomic redemption limits.');
  }
  update_option('rgco_schema','1',false);
 }
 static function allow_attempt($ip){
  global $wpdb;$table=self::table('rate');$bucket=self::hash('rate',$ip.'|'.(int)floor(time()/60));
  $ok=$wpdb->query($wpdb->prepare("INSERT INTO $table (bucket,hits,expires_at) VALUES (%s,1,%d) ON DUPLICATE KEY UPDATE hits=hits+1",$bucket,time()+180));
  if($ok===false)return false;
  return (int)$wpdb->get_var($wpdb->prepare("SELECT hits FROM $table WHERE bucket=%s",$bucket))<=20;
 }
 static function log($code,$outcome,$source,$cart=null){
  global $wpdb;$id=wc_get_coupon_id_by_code($code);$user=wp_get_current_user();$customer=WC()->customer;
  $email=$customer ? $customer->get_billing_email() : '';if(!$email && $user->exists())$email=$user->user_email;
  // Native AJAX accepts billing_email; only used after our request nonce was validated.
  if(isset($_POST['billing_email']) && is_string($_POST['billing_email']) && is_email(wp_unslash($_POST['billing_email'])))$email=sanitize_email(wp_unslash($_POST['billing_email']));
  $lines=[];$cart=$cart?:WC()->cart;
  if($cart)foreach($cart->get_cart() as $item){$p=$item['data']??null;$lines[]=[
   'product_id'=>(int)($item['product_id']??0),'variation_id'=>(int)($item['variation_id']??0),
   'name'=>$p ? $p->get_name() : '', 'quantity'=>(int)$item['quantity'],
   'attributes'=>$item['variation']??[], 'subtotal'=>(string)($item['line_subtotal']??0), 'total'=>(string)($item['line_total']??0)
  ];}
  $payload=['email'=>$email,'account_name'=>$user->exists()?$user->display_name:'','cart'=>$lines,'currency'=>get_woocommerce_currency(),'cart_total'=>$cart?(string)$cart->get_total('edit'):'','truncated'=>false];
  // Limit each snapshot. This stores no address, raw IP, payment details or arbitrary request body.
  if(strlen(wp_json_encode($payload))>262144){$payload['cart']=array_slice($lines,0,100);$payload['truncated']=true;}
  $ok=$wpdb->insert(self::table('attempts'),['created_at'=>gmdate('Y-m-d H:i:s'),'coupon_id'=>$id,'fingerprint'=>self::hash('code',$code),'outcome'=>$outcome,'source'=>$source,'user_id'=>$user->ID,'email_hash'=>$email?self::hash('email',strtolower(trim($email))):'','payload'=>self::encrypt($payload)]);
  if($ok===false)throw new RuntimeException('Coupon activity could not be recorded. Please try again.');
  return $wpdb->insert_id;
 }
 static function outcome($id,$outcome){global $wpdb;return $wpdb->update(self::table('attempts'),['outcome'=>$outcome],['id'=>$id])!==false;}
 /** Atomic all-or-nothing claims; a unique PK plus row locks closes concurrent checkout races. */
 static function claim($claims,$order_id,$consume=false){
  global $wpdb;$table=self::table('claims');sort($claims);$wpdb->query('START TRANSACTION');
  try{
   foreach($claims as $claim){[$coupon,$scope,$hash]=$claim;
    $ok=$wpdb->query($wpdb->prepare("INSERT INTO $table (coupon_id,scope,identity_hash,order_id,state,updated_at) VALUES (%d,%s,%s,%d,'reserved',%s) ON DUPLICATE KEY UPDATE identity_hash=VALUES(identity_hash)",$coupon,$scope,$hash,$order_id,gmdate('Y-m-d H:i:s')));
    if($ok===false)throw new RuntimeException('Coupon reservation unavailable. Please try again.');
    $row=$wpdb->get_row($wpdb->prepare("SELECT order_id,state FROM $table WHERE coupon_id=%d AND scope=%s AND identity_hash=%s FOR UPDATE",$coupon,$scope,$hash));
    if(!$row || (int)$row->order_id!==$order_id)throw new RuntimeException('This coupon has already been used or is reserved by another unpaid order. Pay or cancel that order before trying again.');
    if($consume && $wpdb->query($wpdb->prepare("UPDATE $table SET state='consumed',updated_at=%s WHERE coupon_id=%d AND scope=%s AND identity_hash=%s",gmdate('Y-m-d H:i:s'),$coupon,$scope,$hash))===false)throw new RuntimeException('Coupon redemption could not be recorded.');
   }
   if($wpdb->query('COMMIT')===false)throw new RuntimeException('Coupon reservation could not be committed.');
  }catch(Throwable $e){$wpdb->query('ROLLBACK');throw $e;}
 }
 static function blocked($coupon,$ids,$own=0){
  global $wpdb;$table=self::table('claims');
  foreach($ids as $scope=>$hash){if($scope==='accountmail')$scope='email';$row=$wpdb->get_row($wpdb->prepare("SELECT order_id,state FROM $table WHERE coupon_id=%d AND scope=%s AND identity_hash=%s",$coupon,$scope,$hash));if($row && (int)$row->order_id!==(int)$own)return true;}
  return false;
 }
 static function release($order_id){global $wpdb;$table=self::table('claims');$wpdb->query($wpdb->prepare("DELETE FROM $table WHERE order_id=%d AND state='reserved'",$order_id));}
 static function cleanup(){
  global $wpdb;$attempts=self::table('attempts');$rate=self::table('rate');
  $days=max(7,min(730,(int)get_option('rgco_retention',180)));
  $wpdb->query($wpdb->prepare("DELETE FROM $attempts WHERE created_at < %s LIMIT 2000",gmdate('Y-m-d H:i:s',time()-DAY_IN_SECONDS*$days)));
  $wpdb->query($wpdb->prepare("DELETE FROM $rate WHERE expires_at < %d LIMIT 3000",time()));
  // Never expire live order reservations or consumed claims by age. That would permit duplicate use.
 }
}
