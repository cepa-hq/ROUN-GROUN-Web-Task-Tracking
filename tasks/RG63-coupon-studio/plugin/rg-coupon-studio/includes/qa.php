<?php
defined('ABSPATH') || exit;
/** Disposable staging fixtures only. No gateways, real customers, email or webhook delivery. */
final class RGCO_QA {
 static function run(){
  $checks=[];$coupons=[];$orders=[];$attempt_ids=[];global $wpdb;
  $assert=function($condition,$message)use(&$checks){if(!$condition)throw new RuntimeException($message);$checks[]='PASS · '.$message;};
  $emails=['new_order','cancelled_order','failed_order','customer_on_hold_order','customer_processing_order','customer_completed_order','customer_refunded_order','customer_invoice','customer_note','customer_new_account'];
  foreach($emails as $email)add_filter('woocommerce_email_enabled_'.$email,'__return_false',PHP_INT_MAX);
  add_filter('woocommerce_webhook_should_deliver','__return_false',PHP_INT_MAX);
  try{
   $payload=['email'=>'coupon-qa@example.invalid','account_name'=>'QA only','cart'=>[['product_id'=>0,'quantity'=>2]]];
   $cipher=RGCO_Storage::encrypt($payload);
   $assert(RGCO_Storage::decrypt($cipher)===$payload && !str_contains($cipher,'example.invalid'),'Encrypted snapshot round-trip; plaintext is not stored');
   $assert(RGCO_Storage::decrypt(substr($cipher,0,-8).'AAAAAAAA')===[],'Tampered encrypted snapshots are rejected');
   $c=new WC_Coupon();$c->set_code('rgco-qa-'.strtolower(wp_generate_password(12,false)));$c->set_discount_type('percent');$c->set_amount(10);$c->update_meta_data('_rgco_enabled','yes');$c->update_meta_data('_rgco_stack','yes');$c->update_meta_data('_rgco_once_ip','yes');$c->update_meta_data('_rgco_once_email','yes');$c->save();$coupons[]=$c;
   $c->update_meta_data('_rgco_start',time()+120);$assert(RGCO_Engine::window_error($c)!=='','Future opening is rejected');
   $c->update_meta_data('_rgco_start',0);$c->update_meta_data('_rgco_end',time()-1);$assert(RGCO_Engine::window_error($c)!=='','Expired coupon is rejected');
   $c->update_meta_data('_rgco_end',time());$assert(RGCO_Engine::window_error($c)!=='','Exact closing boundary is exclusive');
   $c->update_meta_data('_rgco_end',0);$c->save();$assert(RGCO_Engine::window_error($c)==='','Blank expiry means indefinite duration');
   $d=new WC_Coupon();$d->set_code('rgco-qa-'.strtolower(wp_generate_password(12,false)));$d->set_discount_type('fixed_cart');$d->set_amount(50);$d->update_meta_data('_rgco_enabled','yes');$d->update_meta_data('_rgco_stack','yes');$d->save();$coupons[]=$d;
   $assert(RGCO_Engine::stack_error([$c,$d])==='','Percentage and flat stackable coupons combine');
   $d->update_meta_data('_rgco_stack','no');
   $assert(RGCO_Engine::stack_error([$c,$d])!=='' && RGCO_Engine::stack_error([$d,$c])!=='','Non-stacking policy is symmetric in both application orders');
   $d->update_meta_data('_rgco_stack','yes');$d->set_individual_use(true);$assert(RGCO_Engine::stack_error([$c,$d])!=='','Native individual-use rule is respected');
   $anonymous=new WC_Order();$anonymous->set_billing_email('anonymous@example.invalid');$c->update_meta_data('_rgco_once_account','yes');$refused=false;
   try{RGCO_Engine::identities($c,$anonymous,true);}catch(Throwable $e){$refused=true;}
   $assert($refused,'Account-limited coupon refuses guest checkout');$c->update_meta_data('_rgco_once_account','no');
   $blank=new WC_Order();$refused=false;try{RGCO_Engine::identities($c,$blank,true);}catch(Throwable $e){$refused=true;}
   $assert($refused,'Email-limited coupon requires email before payment');
   $testip='198.51.100.'.random_int(1,250);$hash=RGCO_Storage::hash('qa',wp_generate_uuid4());
   $pair=[[$c->get_id(),'ip',$hash],[$c->get_id(),'email',RGCO_Storage::hash('qa-email',$hash)]];
   RGCO_Storage::claim($pair,910000001);RGCO_Storage::claim($pair,910000001);
   $assert(true,'Reservation retry is idempotent for its owning order');
   $blocked=false;try{RGCO_Storage::claim($pair,910000002);}catch(Throwable $e){$blocked=true;}
   $assert($blocked,'A second order cannot claim a reserved identity');
   // Insert a new identity first, then conflict later: transaction must roll it back.
   $newhash=RGCO_Storage::hash('qa-rollback',$hash);$blocked=false;
   try{RGCO_Storage::claim([[$c->get_id(),'aaa',$newhash],$pair[0]],910000002);}catch(Throwable $e){$blocked=true;}
   $table=RGCO_Storage::table('claims');$left=$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE coupon_id=%d AND scope='aaa'",$c->get_id()));
   $assert($blocked && (int)$left===0,'Multi-identity claim conflict rolls back all partial reservations');
   RGCO_Storage::release(910000001);RGCO_Storage::claim($pair,910000002,true);RGCO_Storage::release(910000002);
   $assert(RGCO_Storage::blocked($c->get_id(),['ip'=>$hash]),'Consumed successful-use claim survives cancellation/refund release');
   $emailhash=$pair[1][2];$assert(RGCO_Storage::blocked($c->get_id(),['accountmail'=>$emailhash]),'Alternate billing email cannot bypass the account-email identity');
   // Native Woo CRUD and lifecycle, never process_payment or an external gateway.
   $make=function($method)use($c,&$orders,$testip){$o=new WC_Order();$o->set_created_via('rgco-staging-qa');$o->set_status('pending');$o->set_billing_email('coupon-qa-'.wp_generate_password(8,false).'@example.invalid');$o->set_customer_ip_address($testip);$o->set_payment_method($method);$o->set_total(100);$o->update_meta_data('_rgco_qa','yes');$o->save();$orders[]=$o;
    $item=new WC_Order_Item_Coupon();$item->set_code($c->get_code());$item->set_discount(10);$o->add_item($item);$o->save();return $o;};
   $o=$make('rgco-qa');RGCO_Engine::reserve($o);$redeem=RGCO_Storage::table('redemptions');
   $assert((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $redeem WHERE order_id=%d",$o->get_id()))===0,'Pending checkout reserves but does not count as a successful redemption');
   $o->update_status('failed');$assert((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE order_id=%d",$o->get_id()))===0,'Native failed order releases unpaid reservations');
   $o->update_status('pending');RGCO_Engine::reserve($o);$o->update_status('cancelled');
   $assert((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE order_id=%d",$o->get_id()))===0,'Native cancelled unpaid order releases reservations');
   $o->update_status('pending');RGCO_Engine::reserve($o);$o->payment_complete('rgco-qa-no-charge');RGCO_Engine::paid($o->get_id());
   $assert((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $redeem WHERE order_id=%d",$o->get_id()))===1,'Native payment completion creates exactly one successful redemption, including repeated callback');
   $other=$make('cod');$other->update_meta_data('_rgco_ip_hash',RGCO_Storage::hash('qa-cod',wp_generate_uuid4()));$other->save_meta_data();RGCO_Engine::reserve($other);$other->update_status('processing');
   $assert((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $redeem WHERE order_id=%d",$other->get_id()))===0,'Cash-on-delivery processing does not count as paid');
   $other->update_status('completed');$assert((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $redeem WHERE order_id=%d",$other->get_id()))===1,'Completed cash-on-delivery order consumes its allowance');
   // Late payment after released reservation: a different paid order owns the identity.
   $late=$make('rgco-qa');$late->update_meta_data('_rgco_ip_hash',RGCO_Storage::hash('qa-late',wp_generate_uuid4()));$late->save_meta_data();RGCO_Engine::reserve($late);$late->update_status('failed');
   $lateclaims=$late->get_meta('_rgco_claims');RGCO_Storage::claim($lateclaims,910000003,true);$late->payment_complete('rgco-qa-late-no-charge');
   $fresh=wc_get_order($late->get_id());$assert($fresh->get_meta('_rgco_conflict')==='yes' && $fresh->has_status('on-hold'),'Late paid conflict preserves prior claim and puts the order on hold for review');
   // Saved order claims remain enforced even when promotion settings change later.
   $c->update_meta_data('_rgco_once_email','no');$c->update_meta_data('_rgco_once_ip','no');$c->save();$refused=false;
   try{RGCO_Engine::reserve($fresh);}catch(Throwable $e){$refused=true;}
   $assert($refused,'Order retry retains original identity claims after coupon rule edits');

   // Rate limit uses a synthetic identity, not a shopper's IP.
   for($i=1;$i<=20;$i++)$allowed=RGCO_Storage::allow_attempt('qa-'.$hash);
   $assert($allowed && !RGCO_Storage::allow_attempt('qa-'.$hash),'Atomic per-connection coupon attempt limit is enforced');
   $assert(!post_type_exists('rgco_attempt'),'No public analytics post type is registered');
  }catch(Throwable $e){$checks[]='FAIL · '.$e->getMessage();}
  finally{
   // Only fixtures created in this one staging run are touched. No real order IDs.
   foreach($orders as $order){$id=$order->get_id();foreach(['claims','redemptions'] as $t){$table=RGCO_Storage::table($t);$wpdb->delete($table,['order_id'=>$id]);}$order->delete(false);}
   foreach($coupons as $coupon){foreach(['claims','redemptions'] as $t){$table=RGCO_Storage::table($t);$wpdb->delete($table,['coupon_id'=>$coupon->get_id()]);}wp_trash_post($coupon->get_id());}
   foreach($emails as $email)remove_filter('woocommerce_email_enabled_'.$email,'__return_false',PHP_INT_MAX);
   remove_filter('woocommerce_webhook_should_deliver','__return_false',PHP_INT_MAX);
   $checks[]='INFO · Only this run’s disposable fixtures cleaned; test orders/coupons moved to Trash. No gateway called.';
  }
  return $checks;
 }
}
