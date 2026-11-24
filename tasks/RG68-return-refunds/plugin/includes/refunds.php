<?php
defined('ABSPATH') || exit;
/** Request-bound encrypted refund destinations. This module never initiates a payment. */
final class RGR_Refund {
 static $message='';
 static function boot(){
  add_action('admin_head',function(){if(current_user_can('manage_woocommerce')&&($_GET['page']??'')==='wc-return-warranty')echo '<style>.request-list-table .row-actions:has(.rgr_refund){left:auto;visibility:visible}</style>';});
  add_action('template_redirect',[__CLASS__,'post'],0);
  add_action('admin_menu',function(){add_submenu_page('wc-return-warranty','Refund destinations','Refund destinations','manage_woocommerce','rgr-refunds',[__CLASS__,'admin']);},99);
  add_filter('wcrw_request_table_row_actions',function($a,$r){if(current_user_can('manage_woocommerce')){$a['rgr_refund']='<a href="'.esc_url(self::admin_url($r['id'])).'">Refund destination: '.esc_html(self::native_summary($r)).'</a>';}return $a;},20,2);
  add_action('wcrw_request_postbox_left_section',function($r){if(current_user_can('manage_woocommerce'))echo '<div class="postbox"><div class="inside"><h2>Refund destination</h2><p>'.esc_html(self::native_summary($r)).'</p><a class="button" href="'.esc_url(self::admin_url($r['id'])).'">Manage destination and customer link</a></div></div>';});
 }
 static function native_summary($formatted){try{$r=self::request(absint($formatted['id']??0));if(!$r)return 'Request unavailable';return self::summary(self::read($r));}catch(Throwable $e){return 'Record needs verification';}}
 static function fixture($clean=false){
  if(wp_parse_url(home_url(),PHP_URL_HOST)!=='testarea.roungroun.com'||!current_user_can('manage_woocommerce'))throw new RuntimeException('Staging manager only.');
  add_filter('woocommerce_webhook_should_deliver','__return_false',PHP_INT_MAX);foreach(['new_order','cancelled_order','failed_order','customer_on_hold_order','customer_processing_order','customer_completed_order','customer_refunded_order','customer_invoice','customer_note','customer_new_account'] as $email)add_filter('woocommerce_email_enabled_'.$email,'__return_false',PHP_INT_MAX);
  global $wpdb;$key='rgr_refund_fixture_'.get_current_user_id();$fixture=get_option($key);
  if($clean){if(!$fixture)return;$r=self::request($fixture['request']);$o=wc_get_order($fixture['order']);if(!$r||!$o||$o->get_created_via()!=='rgr-refund-qa'||$r['reasons']!==$fixture['marker'])throw new RuntimeException('Fixture identity mismatch; cleanup refused.');$wpdb->delete($wpdb->prefix.'wcrw_request_product_map',['request_id'=>$r['id']]);$wpdb->delete($wpdb->prefix.'wcrw_warranty_requests',['id'=>$r['id']]);delete_option(self::name($r['id']));$o->delete(false);delete_option($key);return;}
  if($fixture)return $fixture;
  $o=new WC_Order();$o->set_customer_id(get_current_user_id());$o->set_created_via('rgr-refund-qa');$o->set_status('pending');$o->set_date_completed(time());$o->set_payment_method('cod');$o->set_billing_email('refund-qa@example.invalid');$o->save();$item=new WC_Order_Item_Product();$item->set_product(wc_get_product(938));$item->set_quantity(1);$item->update_meta_data('_wcrw_item_warranty',['type'=>'included_warranty','length'=>'limited','length_value'=>7,'length_duration'=>'days']);$o->add_item($item);$o->save();$marker='Disposable refund QA '.wp_generate_uuid4();
  if(!$wpdb->insert($wpdb->prefix.'wcrw_warranty_requests',['order_id'=>$o->get_id(),'customer_id'=>get_current_user_id(),'type'=>'refund','status'=>'new','reasons'=>$marker,'meta'=>'','created_at'=>current_time('mysql')]))throw new RuntimeException('Fixture request creation failed.');$id=$wpdb->insert_id;
  if(!$wpdb->insert($wpdb->prefix.'wcrw_request_product_map',['request_id'=>$id,'product_id'=>938,'item_id'=>$item->get_id(),'quantity'=>1]))throw new RuntimeException('Fixture mapping failed.');
  $fixture=['request'=>$id,'order'=>$o->get_id(),'marker'=>$marker];add_option($key,$fixture,'',false);return $fixture;
 }
 static function fixture_state($status){
  if(!in_array($status,['new','processing','reviewing','completed','rejected'],true))throw new RuntimeException('Invalid fixture status.');$fx=get_option('rgr_refund_fixture_'.get_current_user_id());$r=$fx?self::request($fx['request']):null;
  if(wp_parse_url(home_url(),PHP_URL_HOST)!=='testarea.roungroun.com'||!current_user_can('manage_woocommerce')||!$r||$r['reasons']!==$fx['marker'])throw new RuntimeException('No disposable fixture.');$o=wc_get_order($fx['order']);if(!$o||$o->get_created_via()!=='rgr-refund-qa')throw new RuntimeException('Fixture order mismatch.');if(!$o->get_date_completed()){$o->set_date_completed(time());$o->save();}foreach($o->get_items() as $item){$item->update_meta_data('_wcrw_item_warranty',['type'=>'included_warranty','length'=>'limited','length_value'=>7,'length_duration'=>'days']);$item->save();}global $wpdb;$wpdb->update($wpdb->prefix.'wcrw_warranty_requests',['status'=>$status],['id'=>$r['id'],'reasons'=>$fx['marker']]);return $fx;
 }
 static function request($id){global $wpdb;return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}wcrw_warranty_requests WHERE id=%d",$id),ARRAY_A);}
 static function owned($r,$user){$o=$r?wc_get_order($r['order_id']):false;return $o && $user>0 && (int)$r['customer_id']===$user && (int)$o->get_customer_id()===$user;}
 static function name($id){return 'rgr_refund_'.absint($id);}
 static function key(){
  $key=get_option('rgr_refund_secret','');
  if(!$key){global $wpdb;$count=$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name REGEXP '^rgr_refund_[0-9]+$'");if($count)throw new RuntimeException('Refund encryption key is unavailable. Restore the original key.');add_option('rgr_refund_secret',base64_encode(random_bytes(32)),'',false);$key=get_option('rgr_refund_secret','');}
  $bytes=base64_decode($key,true);if(strlen((string)$bytes)!==32)throw new RuntimeException('Refund encryption is unavailable.');return $bytes;
 }
 static function encryption_key(){return hash_hkdf('sha256',self::key(),32,'rgr-refund-v2',wp_salt('auth'));}
 static function seal($data,$context){$iv=random_bytes(12);$tag='';$cipher=openssl_encrypt(wp_json_encode($data),'aes-256-gcm',self::encryption_key(),OPENSSL_RAW_DATA,$iv,$tag,$context);if($cipher===false)throw new RuntimeException('Encryption failed.');return 'v2:'.base64_encode($iv.$tag.$cipher);}
 static function open($blob,$context){$v2=str_starts_with($blob,'v2:');$raw=base64_decode($v2?substr($blob,3):$blob,true);if($raw===false||strlen($raw)<29)throw new RuntimeException('Encrypted refund record is invalid.');$plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',$v2?self::encryption_key():self::key(),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16),$context);$data=$plain===false?null:json_decode($plain,true);if(!is_array($data))throw new RuntimeException('Refund record could not be verified.');return $data;}
 static function context($r){return 'rgr-refund-v1:'.(int)$r['id'].':'.(int)$r['order_id'].':'.(int)$r['customer_id'];}
 static function raw($id){global $wpdb;return $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s",self::name($id)));}
 static function read($r){$raw=self::raw($r['id']);return $raw?self::open($raw,self::context($r)):['version'=>0,'mode'=>'','details'=>[],'locked'=>false,'audit'=>[]];}
 static function commit($r,$record,$expected,$event){
  global $wpdb;$before=self::raw($r['id']);$old=$before?self::open($before,self::context($r)):['version'=>0];
  if((int)$old['version']!==$expected)throw new RuntimeException('These details changed in another window. Reload before saving.');
  $record['version']=$expected+1;$record['updated']=gmdate('c');$record['audit']=array_slice(array_merge($record['audit']??[],[['at'=>gmdate('c'),'actor'=>get_current_user_id(),'event'=>$event]]),-30);
  $blob=self::seal($record,self::context($r));$name=self::name($r['id']);
  if($before===null){$ok=$wpdb->insert($wpdb->options,['option_name'=>$name,'option_value'=>$blob,'autoload'=>'off'],['%s','%s','%s'])===1;wp_cache_delete($name,'options');wp_cache_delete('notoptions','options');}else{$ok=$wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value=%s WHERE option_name=%s AND BINARY option_value=BINARY %s",$blob,$name,$before))===1;wp_cache_delete($name,'options');}
  if(!$ok)throw new RuntimeException('Refund details were not saved. Reload and try again.');return $record;
 }
 static function validate($raw,$order){
  $value=function($key)use($raw){$v=$raw[$key]??'';if(!is_scalar($v))throw new InvalidArgumentException('Invalid refund field.');return trim(sanitize_text_field($v));};
  $mode=$value('refund_mode');if(!in_array($mode,['original','upi','bank'],true))throw new InvalidArgumentException('Choose a refund destination.');$details=[];
  if($mode==='original' && $order->get_payment_method()==='cod')throw new InvalidArgumentException('Cash on delivery requires UPI or bank details for a refund.');
  if($mode!=='original'){$details['holder']=$value('holder');if(mb_strlen($details['holder'])<2||mb_strlen($details['holder'])>100)throw new InvalidArgumentException('Enter the beneficiary name (2–100 characters).');}
  if($mode==='upi'){$details['upi']=strtolower($value('upi'));if(!preg_match('/^[a-z0-9._-]{2,128}@[a-z0-9.-]{2,64}$/D',$details['upi']))throw new InvalidArgumentException('Enter a valid UPI ID, such as name@bank.');}
  if($mode==='bank'){
   $details['account']=$value('account');$details['ifsc']=strtoupper($value('ifsc'));$details['bank']=$value('bank');
   if(!preg_match('/^[0-9]{6,20}$/D',$details['account']) || !hash_equals($details['account'],$value('account_confirm')))throw new InvalidArgumentException('Enter matching account numbers of 6–20 digits.');
   if(!preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/D',$details['ifsc']))throw new InvalidArgumentException('Enter a valid 11-character IFSC.');
   if(mb_strlen($details['bank'])<2||mb_strlen($details['bank'])>100)throw new InvalidArgumentException('Enter the bank name.');
  }
  if($mode!=='original' && $value('consent')!=='1')throw new InvalidArgumentException('Confirm that these details belong to the intended refund beneficiary.');
  return ['mode'=>$mode,'details'=>$details];
 }
 static function summary($d){if(empty($d['mode']))return 'Not provided';if($d['mode']==='original')return 'Original payment method';$v=$d['details']??[];return $d['mode']==='bank'?'Bank account ending '.substr($v['account']??'',-4):'UPI ID ending '.substr($v['upi']??'',-4);}
 static function editable($r,$d){return in_array($r['status'],['new','processing','reviewing'],true) && empty($d['locked']) && in_array($r['type'],['refund','cancel'],true);}
 static function invite_valid($d,$token){return !empty($d['invite_hash']) && ($d['invite_until']??0)>time() && is_string($token) && strlen($token)===64 && hash_equals($d['invite_hash'],hash('sha256',$token));}
 static function url($id,$invited=false){return add_query_arg(['rgr_refund'=>absint($id)]+($invited?['rgr_invited'=>1]:[]),wc_get_account_endpoint_url('warranty-requests'));}
 static function admin_url($id=0){return add_query_arg(['page'=>'rgr-refunds','request_id'=>absint($id)],admin_url('admin.php'));}
 static function form_fields($order,$selected='original'){
  echo '<fieldset class="rgr-destination"><legend>Where should an approved refund go?</legend><p>Providing details does not approve or initiate a refund. Never enter a UPI PIN, OTP, card number or online banking password.</p><label>Refund method<select name="refund_mode" data-refund-mode>';foreach(['original'=>'Original payment method (subject to availability)','upi'=>'UPI ID','bank'=>'Bank account'] as $k=>$label){if($k==='original' && $order->get_payment_method()==='cod')continue;echo '<option value="'.esc_attr($k).'" '.selected($selected,$k,false).'>'.esc_html($label).'</option>';}echo '</select></label>';
  echo '<div data-refund-for="upi bank"><label>Beneficiary name<input name="holder" maxlength="100" autocomplete="off"></label></div><div data-refund-for="upi"><label>UPI ID<input name="upi" maxlength="193" placeholder="name@bank" autocomplete="off"></label></div><div data-refund-for="bank">';
  foreach(['bank'=>'Bank name','account'=>'Account number','account_confirm'=>'Confirm account number','ifsc'=>'IFSC code'] as $k=>$label)echo '<label>'.esc_html($label).'<input name="'.esc_attr($k).'" maxlength="100" autocomplete="off" '.(strpos($k,'account')===0?'inputmode="numeric"':'').'></label>';
  echo '</div><label data-refund-for="upi bank" class="rgr-refund-consent"><input name="consent" type="checkbox" value="1"> I confirm that the beneficiary details are correct and may be used for this order’s refund.</label></fieldset>';
 }
 static function post(){
  if(empty($_POST['rgr_refund_save']))return;
  nocache_headers();header('Referrer-Policy: no-referrer');
  try{
   if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||!is_user_logged_in())throw new RuntimeException('Sign in to your account.');
   $id=absint($_POST['request_id']??0);if(!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce']??'')),'rgr-refund-'.$id))throw new RuntimeException('This secure form expired. Reload and try again.');
   $r=self::request($id);if(!self::owned($r,get_current_user_id()))throw new RuntimeException('This request is not available in your account.');
   $d=self::read($r);if(!self::editable($r,$d))throw new RuntimeException('Refund details are closed for changes. Contact customer care.');
   $raw=wp_unslash($_POST);if(!empty($raw['invited']) && !self::invite_valid($d,$raw['invite_token']??''))throw new RuntimeException('This link expired or was already used. Ask customer care for a new link.');
   $choice=self::validate($raw,wc_get_order($r['order_id']));$d=array_merge($d,$choice);unset($d['invite_hash'],$d['invite_until']);self::commit($r,$d,absint($raw['version']??0),'customer_destination_saved');
   wp_safe_redirect(add_query_arg('rgr_saved',1,self::url($id)));exit;
  }catch(Throwable $e){self::$message=$e->getMessage();}
 }
 static function customer($r){
  if(!self::owned($r,get_current_user_id()))return;
  $d=self::read($r);echo '<section class="panel"><h2>Refund destination</h2><p>'.esc_html(self::summary($d)).'</p><p class="muted">Linked to request #'.(int)$r['id'].', order #'.(int)$r['order_id'].' and your account.</p>';
  if(self::$message)echo '<p role="alert" class="rgr-alert rgr-error">'.esc_html(self::$message).' No new details were saved.</p>';
  if(isset($_GET['rgr_saved']))echo '<p role="status" class="rgr-alert">Refund details saved securely.</p>';
  if(self::editable($r,$d)){
   echo '<details '.(isset($_GET['rgr_refund'])?'open':'').'><summary>Choose or update refund details</summary><form method="post" autocomplete="off">';wp_nonce_field('rgr-refund-'.$r['id']);
   echo '<input type="hidden" name="rgr_refund_save" value="1"><input type="hidden" name="request_id" value="'.(int)$r['id'].'"><input type="hidden" name="version" value="'.(int)$d['version'].'"><input type="hidden" name="invited" value="'.(!empty($_GET['rgr_invited'])?'1':'0').'"><input type="hidden" name="invite_token" data-refund-token value="">';self::form_fields(wc_get_order($r['order_id']),$d['mode']?:'original');echo '<button class="btn" type="submit">Save refund details</button></form></details>';
  }else echo '<p>Changes are closed. Contact customer care if these details need correction.</p>';
  echo '</section>';
 }
 static function create_for_request($r,$choice){if(!$choice)return;self::commit($r,array_merge(['locked'=>false,'audit'=>[]],$choice),0,'destination_recorded_with_request');}
 static function admin(){
  if(!current_user_can('manage_woocommerce'))wp_die('Access denied.');nocache_headers();header('Referrer-Policy: no-referrer');$id=absint($_GET['request_id']??0);$r=$id?self::request($id):null;$link='';$reveal=false;$error='';
  if(($_POST['operation']??'')==='fixture_state'){check_admin_referer('rgr-refund-qa');$fx=self::fixture_state(sanitize_key($_POST['fixture_status']??''));wp_safe_redirect(self::admin_url($fx['request']));exit;}
  if(in_array($_POST['operation']??'',['fixture','fixture_clean'],true)){check_admin_referer('rgr-refund-qa');$fx=self::fixture($_POST['operation']==='fixture_clean');wp_safe_redirect(self::admin_url($fx['request']??0));exit;}
  if(($_POST['operation']??'')==='qa'){check_admin_referer('rgr-refund-qa');if(wp_parse_url(home_url(),PHP_URL_HOST)!=='testarea.roungroun.com')wp_die('Staging only.');echo '<div class="wrap"><h1>Refund security checks</h1>';foreach(self::qa() as $line)echo '<p class="rgr-qa-result">'.esc_html($line).'</p>';echo '<a href="'.esc_url(self::admin_url()).'">Back to refund destinations</a></div>';return;}
  try{
   if($r && !self::owned($r,(int)$r['customer_id']))throw new RuntimeException('Order/customer ownership cannot be verified.');
   if($r && ($_SERVER['REQUEST_METHOD']??'')==='POST'){
    check_admin_referer('rgr-admin-'.$id);$d=self::read($r);$op=sanitize_key($_POST['operation']??'');
    if($op==='invite'){
     if(!self::editable($r,$d))throw new RuntimeException('Request is closed or its destination is locked.');
     $token=bin2hex(random_bytes(32));$d['invite_hash']=hash('sha256',$token);$d['invite_until']=time()+2*DAY_IN_SECONDS;self::commit($r,$d,absint($_POST['version']??0),'customer_link_generated');$link=self::url($id,true).'#rgr_token='.$token;
    }elseif($op==='reveal'){$reveal=true;self::commit($r,$d,absint($_POST['version']??0),'manager_destination_viewed');}
    elseif($op==='lock'){$d['locked']=true;unset($d['invite_hash'],$d['invite_until']);self::commit($r,$d,absint($_POST['version']??0),'destination_locked_for_payout');}
    elseif($op==='note'){$d['public_note']=substr(sanitize_textarea_field(wp_unslash($_POST['public_note']??'')),0,2000);self::commit($r,$d,absint($_POST['version']??0),'customer_resolution_updated');}
   }
  }catch(Throwable $e){$error=$e->getMessage();}
  echo '<div class="wrap"><h1>Refund destinations</h1><p>Original payment, UPI and bank instructions are stored per request. This screen does not send money.</p>';if($error)echo '<div class="notice notice-error"><p>'.esc_html($error).'</p></div>';
  if($r){
   try{$d=self::read($r);}catch(Throwable $e){echo '<div class="notice notice-error"><p>Refund record could not be verified. Restore the original encryption key and record backup; no destination has been changed.</p></div></div>';return;}echo '<h2>Request #'.$id.' · Order #'.(int)$r['order_id'].'</h2><p><a href="'.esc_url(admin_url('admin.php?page=wc-return-warranty&request_id='.$id)).'">Native request and status controls</a> · <a href="'.esc_url(get_edit_user_link($r['customer_id'])).'">Customer account</a></p><p><strong>'.esc_html(self::summary($d)).'</strong> · '.(!empty($d['locked'])?'Locked for payout':'Not locked').'</p>';
   if($link)echo '<p><strong>Customer form link · expires in 48 hours · account sign-in required</strong></p><input style="width:100%" readonly aria-label="Customer refund form link" value="'.esc_attr($link).'"><p>Copy and send this link to the customer. Generating another link invalidates the previous one. No message has been sent automatically.</p>';
   if($reveal){echo '<div style="background:white;padding:18px"><h3>Customer-supplied beneficiary details</h3>';foreach($d['details'] as $k=>$v)echo '<p><strong>'.esc_html(ucfirst($k)).':</strong> '.esc_html($v).'</p>';echo '<p>Check the beneficiary before initiating a manual payout. No UPI or bank ownership verification is implied.</p></div>';}
   foreach(['invite'=>'Generate customer form link','reveal'=>'Reveal beneficiary details','lock'=>'Lock destination before payout'] as $op=>$label){echo '<form method="post" style="display:inline-block;margin:10px 8px 10px 0">';wp_nonce_field('rgr-admin-'.$id);echo '<input type="hidden" name="version" value="'.(int)$d['version'].'"><input type="hidden" name="operation" value="'.$op.'"><button class="button" '.($op==='lock'&&!$d['mode']?'disabled':'').'>'.esc_html($label).'</button></form>';}
   echo '<h3>Customer-visible decision / resolution</h3><form method="post">';wp_nonce_field('rgr-admin-'.$id);echo '<input type="hidden" name="version" value="'.(int)$d['version'].'"><input type="hidden" name="operation" value="note"><textarea name="public_note" rows="4" maxlength="2000" style="width:100%">'.esc_textarea($d['public_note']??'').'</textarea><p><button class="button">Save customer update</button></p></form><h3>Audit trail</h3><ul>';foreach(array_reverse($d['audit']??[]) as $event)echo '<li>'.esc_html($event['at'].' · '.$event['event'].' · staff/customer ID '.$event['actor']).'</li>';echo '</ul>';
  }else{
   global $wpdb;$page=max(1,absint($_GET['paged']??1));$rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}wcrw_warranty_requests ORDER BY id DESC LIMIT 25 OFFSET %d",($page-1)*25),ARRAY_A);echo '<table class="widefat striped"><thead><tr><th>Request</th><th>Order</th><th>Status</th><th>Refund destination</th><th>Updated</th></tr></thead><tbody>';foreach($rows as $row){try{$d=self::read($row);$summary=self::summary($d);}catch(Throwable $e){$d=[];$summary='Record needs verification';}echo '<tr><td><a href="'.esc_url(self::admin_url($row['id'])).'">#'.(int)$row['id'].'</a></td><td>#'.(int)$row['order_id'].'</td><td>'.esc_html($row['status']).'</td><td>'.esc_html($summary).'</td><td>'.esc_html($d['updated']??'—').'</td></tr>';}echo '</tbody></table><p>';if($page>1)echo '<a class="button" href="'.esc_url(add_query_arg('paged',$page-1,self::admin_url())).'">Previous</a> ';if(count($rows)===25)echo '<a class="button" href="'.esc_url(add_query_arg('paged',$page+1,self::admin_url())).'">Next</a>';echo '</p>';
  }if(wp_parse_url(home_url(),PHP_URL_HOST)==='testarea.roungroun.com'){foreach(['qa'=>'Run isolated security checks','fixture'=>'Create disposable QA fixture','fixture_clean'=>'Clean disposable QA fixture'] as $op=>$label){echo '<form method="post" style="margin:12px 0">';wp_nonce_field('rgr-refund-qa');echo '<input type="hidden" name="operation" value="'.esc_attr($op).'"><button class="button">'.esc_html($label).'</button></form>';}
   if(get_option('rgr_refund_fixture_'.get_current_user_id())){echo '<form method="post">';wp_nonce_field('rgr-refund-qa');echo '<input type="hidden" name="operation" value="fixture_state"><select name="fixture_status">';foreach(['new','processing','reviewing','completed','rejected'] as $s)echo '<option>'.esc_html($s).'</option>';echo '</select><button class="button">Set disposable fixture status</button></form>';}
   if($r&&isset($_GET['rgr_layout'])&&self::owned($r,get_current_user_id()))echo '<h2>390px responsive check</h2><iframe id="rgr-mobile-check" title="Return detail mobile check" style="width:390px;height:1100px;border:1px solid #ccc" src="'.esc_url(RGR_Calm::url($r['id'])).'"></iframe>';
  }echo '</div>';
 }
 static function qa(){
  $checks=[];$id=random_int(1900000000,2000000000);$r=['id'=>$id,'order_id'=>987654321,'customer_id'=>get_current_user_id(),'status'=>'new','type'=>'refund'];$name=self::name($id);$created=false;
  $assert=function($v,$text)use(&$checks){if(!$v)throw new RuntimeException($text);$checks[]='PASS · '.$text;};
  try{
   if(self::raw($id)!==null)throw new RuntimeException('Fixture collision; no record altered.');
   $o=new WC_Order();$o->set_payment_method('cod');$bad=false;try{self::validate(['refund_mode'=>'original'],$o);}catch(Throwable $e){$bad=true;}$assert($bad,'COD refuses an original-method destination');$o->set_payment_method('qa');$assert(self::validate(['refund_mode'=>'original'],$o)['mode']==='original','Original payment accepted for non-COD orders');
   $upi=self::validate(['refund_mode'=>'upi','holder'=>'QA Beneficiary','upi'=>'qa.refund@bank','consent'=>'1'],$o);$assert($upi['details']['upi']==='qa.refund@bank','Valid UPI details accepted');
   $bank=['refund_mode'=>'bank','holder'=>'QA Beneficiary','bank'=>'QA Bank','account'=>'123456789012','account_confirm'=>'123456789012','ifsc'=>'SBIN0001234','consent'=>'1'];$v=self::validate($bank,$o);$assert($v['details']['ifsc']==='SBIN0001234','Bank fields and account confirmation accepted');
   foreach([['account_confirm'=>'1'],['ifsc'=>'INVALID'],['consent'=>''],['account'=>['invalid']]] as $change){$bad=false;try{self::validate(array_merge($bank,$change),$o);}catch(Throwable $e){$bad=true;}$assert($bad,'Invalid '.implode('/',array_keys($change)).' refused');}
   $record=array_merge($upi,['audit'=>[],'locked'=>false]);$sealed=self::seal($record,self::context($r));$assert(self::open($sealed,self::context($r))===$record,'Authenticated encryption round trip');$assert(str_starts_with($sealed,'v2:') && !hash_equals(self::key(),self::encryption_key()),'Versioned encryption key includes external WordPress salt');
   $bad=false;try{self::open($sealed,self::context(array_merge($r,['customer_id'=>99999999])));}catch(Throwable $e){$bad=true;}$assert($bad,'Cross-customer ciphertext binding rejects replay');
   $bad=false;try{self::open(substr($sealed,0,-8).'AAAAAAAA',self::context($r));}catch(Throwable $e){$bad=true;}$assert($bad,'Ciphertext tampering rejected');
   $saved=self::commit($r,$record,0,'qa_created');$created=true;$fresh=self::read($r);$assert($fresh['version']===1 && $fresh['mode']==='upi','Encrypted record persists with request and version');
   $assert(strpos(self::raw($id),'qa.refund@bank')===false && strpos(self::raw($id),'QA Beneficiary')===false,'No beneficiary plaintext in database record');
   $assert(strpos(self::summary($fresh),'qa.refund')===false,'Customer and list summary masks destination');
   $bad=false;try{self::commit($r,$record,0,'qa_stale');}catch(Throwable $e){$bad=true;}$assert($bad && self::read($r)['version']===1,'Stale concurrent save refused without overwriting');
   $token=bin2hex(random_bytes(32));$fresh['invite_hash']=hash('sha256',$token);$fresh['invite_until']=time()+300;$fresh=self::commit($r,$fresh,1,'qa_invite');
   $assert(self::invite_valid($fresh,$token) && !self::invite_valid($fresh,str_repeat('0',64)),'Only matching invitation accepted');$expired=$fresh;$expired['invite_until']=time()-1;$assert(!self::invite_valid($expired,$token),'Expired invitation rejected');
   unset($fresh['invite_hash'],$fresh['invite_until']);$fresh=self::commit($r,$fresh,2,'qa_consumed');$assert(!self::invite_valid(self::read($r),$token),'Consumed invitation cannot be replayed');
   $assert(self::editable($r,$fresh) && !self::editable(array_merge($r,['status'=>'completed']),$fresh) && !self::editable($r,array_merge($fresh,['locked'=>true])),'Completed or payout-locked records refuse customer changes');
   $assert(!self::owned(null,get_current_user_id()) && !self::owned($r,0),'Missing requests and guests fail ownership checks');
   $nonce=wp_create_nonce('rgr-refund-'.$id);$assert(wp_verify_nonce($nonce,'rgr-refund-'.$id) && !wp_verify_nonce($nonce,'rgr-refund-'.($id+1)),'Form nonce is bound to its request');
   $draft=self::seal($v,'draft:1:2');$assert(strpos($draft,$bank['account'])===false && self::open($draft,'draft:1:2')===$v,'Review payload keeps bank details encrypted');
   global $wpdb;$info=$wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS LIKE %s',$wpdb->esc_like($wpdb->options)),ARRAY_A);$assert(strtolower($info['Engine']??'')==='innodb','Refund record storage supports atomic transactions');
   $second=array_merge($r,['id'=>$id+1]);if(self::raw($second['id'])!==null)throw new RuntimeException('Transaction fixture collision.');$wpdb->query('START TRANSACTION');try{self::commit($second,$record,0,'qa_rollback');$assert(self::raw($second['id'])!==null,'Destination is written within active SQL transaction');}finally{$wpdb->query('ROLLBACK');}$assert(self::raw($second['id'])===null,'Request transaction rollback also removes destination');
  }catch(Throwable $e){$checks[]='FAIL · '.$e->getMessage();}
  finally{if($created)delete_option($name);$checks[]='INFO · Disposable encrypted option removed; no real requests, orders, payouts or emails altered.';}
  return $checks;
 }
}
