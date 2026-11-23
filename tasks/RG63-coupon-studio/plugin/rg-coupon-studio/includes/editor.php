<?php
defined('ABSPATH') || exit;
final class RGCO_Editor {
 static function url($id=0){return add_query_arg(['page'=>'rg-coupon-studio','view'=>'editor','coupon_id'=>$id],admin_url('admin.php'));}
 static function boot(){
  add_action('admin_init',[__CLASS__,'route']);
  add_action('admin_post_rgco_coupon_save',[__CLASS__,'submit']);
 }
 static function supported($c){return in_array($c->get_discount_type(),['percent','fixed_cart','fixed_product'],true);}
 static function route(){
  global $pagenow;
  if(!current_user_can('manage_woocommerce') || isset($_GET['rgco_native']))return;
  if($pagenow==='post-new.php' && ($_GET['post_type']??'')==='shop_coupon') {wp_safe_redirect(self::url());exit;}
  if($pagenow==='post.php' && ($_GET['action']??'')==='edit'){
   $id=absint($_GET['post']??0);
   if(get_post_type($id)==='shop_coupon' && current_user_can('edit_post',$id)){
    $c=new WC_Coupon($id);if(self::supported($c)){wp_safe_redirect(self::url($id));exit;}
   }
  }
 }
 static function defaults($c){
  $managed=$c->get_meta('_rgco_enabled')==='yes';$expires=$c->get_date_expires();
  return ['code'=>$c->get_code(),'description'=>$c->get_description(),'discount_type'=>$c->get_discount_type()?:'percent','amount'=>$c->get_amount(),'status'=>$c->get_id()?$c->get_status():'draft','start'=>RGCO_Admin::time_input($c->get_meta('_rgco_start')),'end'=>RGCO_Admin::time_input($managed?$c->get_meta('_rgco_end'):($expires?$expires->getTimestamp():0)),'stack'=>$managed?$c->get_meta('_rgco_stack')==='yes':!$c->get_individual_use(),'once_ip'=>$c->get_meta('_rgco_once_ip')==='yes','once_account'=>$c->get_meta('_rgco_once_account')==='yes','once_email'=>$c->get_meta('_rgco_once_email')==='yes' || (!$managed && (int)$c->get_usage_limit_per_user()===1)];
 }
 static function validate($raw,$id){
  $d=[];foreach(['code','description','discount_type','amount','status','start','end'] as $k){if(isset($raw[$k]) && !is_scalar($raw[$k]))throw new InvalidArgumentException('Invalid form field.');$d[$k]=sanitize_text_field($raw[$k]??'');}
  $d['description']=sanitize_textarea_field($raw['description']??'');
  $d['code']=wc_format_coupon_code($d['code']);
  if($d['code']==='' || strlen($d['code'])>200)throw new InvalidArgumentException('Enter a coupon code of up to 200 bytes.');
  $existing=wc_get_coupon_id_by_code($d['code']);if($existing && (int)$existing!==$id)throw new InvalidArgumentException('This coupon code already exists. Choose another code.');
  $duplicates=get_posts(['post_type'=>'shop_coupon','post_status'=>['publish','draft','pending','private','future'],'title'=>$d['code'],'post__not_in'=>$id?[$id]:[],'posts_per_page'=>1,'fields'=>'ids']);
  if($duplicates)throw new InvalidArgumentException('This coupon code already exists, including paused coupons. Choose another code.');
  if(!in_array($d['discount_type'],['percent','fixed_cart','fixed_product'],true))throw new InvalidArgumentException('Choose a supported discount type.');
  if(!preg_match('/^(?:[0-9]+(?:[.,][0-9]+)?|[.,][0-9]+)$/D',$d['amount']))throw new InvalidArgumentException('Enter a numeric discount amount.');
  $amount=wc_format_decimal($d['amount']);
  if(!is_numeric($amount) || (float)$amount<0 || !is_finite((float)$amount) || ($d['discount_type']==='percent' && (float)$amount>100))throw new InvalidArgumentException('Enter a valid discount amount; percentage must be between 0 and 100.');
  $d['amount']=$amount;
  if(!in_array($d['status'],['publish','draft'],true))throw new InvalidArgumentException('Choose Active or Paused.');
  $d['from']=RGCO_Admin::parse_time($d['start']);$d['until']=RGCO_Admin::parse_time($d['end']);
  if($d['from'] && $d['until'] && $d['until']<=$d['from'])throw new InvalidArgumentException('Closing time must be after opening time.');
  foreach(['stack','once_ip','once_account','once_email'] as $k)$d[$k]=!empty($raw[$k]);
  return $d;
 }
 static function persist($c,$d){
  // Existing native product/category/spend/email/global-use restrictions and usage history are untouched.
  $c->set_code($d['code']);$c->set_description($d['description']);$c->set_discount_type($d['discount_type']);$c->set_amount($d['amount']);$c->set_status($d['status']);
  $c->update_meta_data('_rgco_enabled','yes');
  foreach(['stack','once_ip','once_account','once_email'] as $k)$c->update_meta_data('_rgco_'.$k,$d[$k]?'yes':'no');
  $c->update_meta_data('_rgco_start',$d['from']);$c->update_meta_data('_rgco_end',$d['until']);
  $c->set_date_expires(null);$c->set_individual_use(false);$c->set_usage_limit_per_user(0);
  return $c->save();
 }
 static function submit(){
  RGCO_Admin::access();check_admin_referer('rgco_coupon_save');
  $id=absint($_POST['coupon_id']??0);$type=get_post_type_object('shop_coupon');
  if(!$type || ($id?!current_user_can('edit_post',$id):!current_user_can($type->cap->create_posts)))wp_die('You cannot edit this coupon.', '', ['response'=>403]);
  if($id && (get_post_type($id)!=='shop_coupon' || get_post_status($id)==='trash'))wp_die('Coupon unavailable.');
  $raw=wp_unslash($_POST);
  try{
   $c=new WC_Coupon($id);
   if($id && !self::supported($c))throw new InvalidArgumentException('Use the advanced native editor for this coupon type.');
   if($id && !hash_equals(self::revision($c),sanitize_text_field($raw['revision']??'')))throw new InvalidArgumentException('This coupon changed in another window. Reload before saving.');
   $d=self::validate($raw,$id);
   // Converting unusual legacy per-user limits is explicit, never silently discarded.
   if($c->get_meta('_rgco_enabled')!=='yes' && $c->get_usage_limit_per_user()>0 && empty($raw['replace_legacy']))throw new InvalidArgumentException('Confirm replacing the legacy per-user limit, or use the advanced editor.');
   if($d['status']==='publish' && !current_user_can($type->cap->publish_posts))wp_die('You cannot activate coupons.','',['response'=>403]);
   $id=self::persist($c,$d);wp_safe_redirect(add_query_arg('coupon_saved',1,self::url($id)));exit;
  }catch(Throwable $e){
   set_transient('rgco_form_'.get_current_user_id(),['id'=>$id,'raw'=>array_map('sanitize_textarea_field',array_filter(array_intersect_key($raw,array_flip(['code','description','discount_type','amount','status','start','end','stack','once_ip','once_account','once_email','replace_legacy'])),'is_scalar')),'error'=>$e->getMessage()],300);
   wp_safe_redirect(add_query_arg('form_error',1,self::url($id)));exit;
  }
 }
 static function revision($c){return hash('sha256',wp_json_encode([$c->get_data(),$c->get_meta_data()]));}
 static function render(){
  RGCO_Admin::access();nocache_headers();$id=absint($_GET['coupon_id']??0);
  if($id && (get_post_type($id)!=='shop_coupon' || !current_user_can('edit_post',$id) || get_post_status($id)==='trash'))wp_die('Coupon unavailable.');
  $c=new WC_Coupon($id);$d=self::defaults($c);$error='';
  if(isset($_GET['form_error'])){$failure=get_transient('rgco_form_'.get_current_user_id());if($failure && $failure['id']===$id){$d=array_merge($d,array_fill_keys(['stack','once_ip','once_account','once_email'],false),$failure['raw']);$error=$failure['error'];delete_transient('rgco_form_'.get_current_user_id());}}
  ?><div class="wrap rgco-editor"><style>.rgco-editor{max-width:1000px;color:#322d29;padding:16px 8px}.rgco-editor .rgco-panel{background:white;border:1px solid #e4dcd4;border-radius:12px;padding:24px;margin:20px 0}.rgco-editor h1{font-size:28px}.rgco-editor p,.rgco-editor label{font-size:14px;line-height:1.6}.rgco-editor .rgco-fields{display:grid;grid-template-columns:1fr 1fr;gap:20px}.rgco-editor .rgco-field label{display:block;font-weight:600;margin-bottom:6px}.rgco-editor input:not([type=checkbox]),.rgco-editor select,.rgco-editor textarea{width:100%;max-width:100%;min-height:40px}.rgco-editor .rgco-check{display:block;margin:14px 0}.rgco-editor .button-primary{background:#72383d;border-color:#72383d;padding:5px 20px}.rgco-editor small{display:block;color:#665950;margin-top:6px}@media(max-width:650px){.rgco-editor .rgco-fields{grid-template-columns:1fr}.rgco-editor .rgco-panel{padding:16px}}</style>
  <h1><?php echo $id?'Edit coupon':'Create coupon';?></h1><p><a href="<?php echo esc_url(admin_url('admin.php?page=rg-coupon-studio'));?>">Back to Coupon Studio</a></p>
  <?php if($error)echo '<div class="notice notice-error"><p>'.esc_html($error).' Nothing was saved.</p></div>';if(isset($_GET['coupon_saved']))echo '<div class="notice notice-success"><p>Coupon saved.</p></div>';?>
  <p>All coupon controls in one form. Times use <?php echo esc_html(wp_timezone_string());?>. Active coupons follow their opening and closing times.</p>
  <?php if(!self::supported($c)){echo '<p>This coupon uses an extension discount type. Open its advanced editor below.</p>';}else{?>
  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>">
  <input type="hidden" name="action" value="rgco_coupon_save"><input type="hidden" name="coupon_id" value="<?php echo (int)$id;?>"><input type="hidden" name="revision" value="<?php echo esc_attr(self::revision($c));?>"><?php wp_nonce_field('rgco_coupon_save');?>
  <section class="rgco-panel"><h2>Coupon and discount</h2><div class="rgco-fields">
  <?php self::input('code','Coupon code',$d['code'],'text','required maxlength="200"');self::select('discount_type','Discount type',$d['discount_type'],['percent'=>'Percentage discount','fixed_cart'=>'Flat discount on cart','fixed_product'=>'Flat discount per product']);self::input('amount','Discount amount',$d['amount'],'text','required inputmode="decimal"');self::select('status','Availability',$d['status'],['draft'=>'Paused / draft','publish'=>'Active']);?>
  </div><p><label for="rgco-description">Internal description</label><textarea id="rgco-description" name="description" rows="2"><?php echo esc_textarea($d['description']);?></textarea></p></section>
  <section class="rgco-panel"><h2>Schedule</h2><div class="rgco-fields"><?php self::input('start','Opens at',$d['start'],'datetime-local');self::input('end','Closes at',$d['end'],'datetime-local');?></div><p>Leave opening blank to start immediately. Leave closing blank for a permanent coupon with no expiry.</p></section>
  <section class="rgco-panel"><h2>Stacking and successful-use limits</h2>
  <?php foreach(['stack'=>'Allow stacking with other stackable coupons','once_ip'=>'Only once per IP address','once_account'=>'Only once per account (requires sign-in)','once_email'=>'Only once per email address'] as $k=>$label)echo '<label class="rgco-check"><input type="checkbox" name="'.esc_attr($k).'" value="1" '.checked(!empty($d[$k]),true,false).'> '.esc_html($label).'</label>';?>
  <p>Every coupon in a combination must allow stacking. Applying or checking a code is logged but does not consume these limits. Pending payment reserves the allowance; successful payment or completed COD consumes it.</p>
  <?php if($c->get_meta('_rgco_enabled')!=='yes' && $c->get_usage_limit_per_user()>0):?><label class="rgco-check"><input type="checkbox" name="replace_legacy" value="1" <?php checked(!empty($d['replace_legacy']));?>> Replace the existing native per-user limit of <?php echo (int)$c->get_usage_limit_per_user();?> with the successful-use choices above.</label><?php endif;?>
  </section><p><button class="button button-primary" type="submit">Save coupon</button></p></form><?php }?>
  <?php if($id):?><section class="rgco-panel"><h2>Advanced restrictions</h2><p>Existing product, category, spend, email and global usage restrictions remain attached to this coupon. Configure these in the advanced editor.</p><p><a href="<?php echo esc_url(add_query_arg(['post'=>$id,'action'=>'edit','rgco_native'=>1],admin_url('post.php')));?>">Advanced WooCommerce editor</a> · <a href="<?php echo esc_url(add_query_arg('coupon_id',$id,admin_url('admin.php?page=rg-coupon-studio')));?>">View coupon activity</a></p></section><?php endif;?></div><?php
 }
 static function input($key,$label,$value,$type='text',$extra=''){echo '<div class="rgco-field"><label for="rgco-'.esc_attr($key).'">'.esc_html($label).'</label><input id="rgco-'.esc_attr($key).'" name="'.esc_attr($key).'" type="'.esc_attr($type).'" value="'.esc_attr($value).'" '.$extra.'></div>';}
 static function select($key,$label,$value,$options){echo '<div class="rgco-field"><label for="rgco-'.esc_attr($key).'">'.esc_html($label).'</label><select id="rgco-'.esc_attr($key).'" name="'.esc_attr($key).'">';foreach($options as $k=>$v)echo '<option value="'.esc_attr($k).'" '.selected($value,$k,false).'>'.esc_html($v).'</option>';echo '</select></div>';}
 static function listing(){
  $paged=max(1,absint($_GET['coupons_page']??1));$q=new WP_Query(['post_type'=>'shop_coupon','post_status'=>['publish','draft','pending','private'],'posts_per_page'=>20,'paged'=>$paged,'orderby'=>'date','order'=>'DESC']);
  echo '<div class="panel"><h2>Your coupons</h2><div class="scroll"><table><thead><tr><th>Code</th><th>Discount</th><th>Availability</th><th>Manage</th></tr></thead><tbody>';
  foreach($q->posts as $p){if(!current_user_can('edit_post',$p->ID))continue;$c=new WC_Coupon($p->ID);echo '<tr><td>'.esc_html($c->get_code()).'</td><td>'.esc_html($c->get_amount().($c->get_discount_type()==='percent'?'%':' '.get_woocommerce_currency())).'</td><td>'.esc_html($c->get_status()==='publish'?'Active (schedule applies)':'Paused').'</td><td><a href="'.esc_url(self::url($p->ID)).'">Edit coupon</a></td></tr>';}
  if(!$q->posts)echo '<tr><td colspan="4">No coupons yet. Create your first coupon above.</td></tr>';
  echo '</tbody></table></div>';echo wp_kses_post(paginate_links(['base'=>add_query_arg('coupons_page','%#%',admin_url('admin.php?page=rg-coupon-studio')),'format'=>'','current'=>$paged,'total'=>$q->max_num_pages]));echo '</div>';
 }
}
