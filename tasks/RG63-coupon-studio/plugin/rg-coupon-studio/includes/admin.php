<?php
defined('ABSPATH') || exit;
final class RGCO_Admin {
 static function boot(){
  add_action('admin_menu',function(){add_submenu_page('woocommerce','Roun Groun Coupon Studio','Coupon Studio','manage_woocommerce','rg-coupon-studio',[__CLASS__,'page']);});
  add_action('woocommerce_coupon_options_usage_restriction',[__CLASS__,'fields'],20,2);
  add_action('woocommerce_coupon_options_save',[__CLASS__,'save'],20,2);
  add_action('admin_post_rgco_settings',[__CLASS__,'settings']);
  add_action('admin_post_rgco_qa',[__CLASS__,'qa']);
  add_filter('plugin_action_links_'.plugin_basename(RGCO_FILE),function($links){array_unshift($links,'<a href="'.esc_url(admin_url('admin.php?page=rg-coupon-studio')).'">Coupon Studio</a>');return $links;});
 }
 static function access(){if(!current_user_can('manage_woocommerce'))wp_die('You do not have permission to access coupon analytics.',403);}
 static function time_input($ts){return $ts?wp_date('Y-m-d\TH:i',(int)$ts,wp_timezone()):'';}
 static function fields($id,$coupon){
  if(!current_user_can('manage_woocommerce'))return;
  echo '<div class="options_group"><h3 style="padding:0 12px">Roun Groun Coupon Studio</h3>';
  foreach(['_rgco_enabled'=>'Manage with Coupon Studio','_rgco_stack'=>'Allow stacking with other stackable coupons','_rgco_once_ip'=>'Once per IP (successful checkout)','_rgco_once_account'=>'Once per account (requires sign-in)','_rgco_once_email'=>'Once per email (billing and account email)'] as $key=>$label){
   woocommerce_wp_checkbox(['id'=>$key,'label'=>$label,'value'=>($key==='_rgco_enabled' && (!$id || $coupon->get_status()==='auto-draft'))?'yes':$coupon->get_meta($key),'description'=>$key==='_rgco_stack'?'Every coupon in a combination must allow stacking. Native individual-use restrictions still apply.':'']);
  }
  foreach(['_rgco_start'=>'Opens at','_rgco_end'=>'Closes at'] as $key=>$label)woocommerce_wp_text_input(['id'=>$key,'label'=>$label,'type'=>'datetime-local','value'=>self::time_input($coupon->get_meta($key)),'description'=>'Store timezone: '.wp_timezone_string().'. '.($key==='_rgco_start'?'Blank = immediately.':'Blank = permanent / no expiry.'),'desc_tip'=>false]);
  echo '<p style="padding:0 12px">Use General for coupon code, percentage or fixed cart discount. For managed coupons, the exact closing time above replaces General’s date-only expiry; leave native “Individual use only” and “Usage limit per user” blank. Failed/unpaid checkouts do not consume Studio limits. Pending orders reserve them until paid or cancelled.</p></div>';
 }
 static function parse_time($raw){
  if($raw==='')return 0;
  $date=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$raw,wp_timezone());
  if(!$date || $date->format('Y-m-d\TH:i')!==$raw)throw new InvalidArgumentException('Enter a valid opening/closing date and time.');
  return $date->getTimestamp();
 }
 static function save($id,$coupon){
  // WooCommerce has already verified coupon edit nonce. Also enforce capabilities here.
  if(!current_user_can('manage_woocommerce') || !current_user_can('edit_post',$id))return;
  if(!isset($_POST['woocommerce_meta_nonce']) || !wp_verify_nonce(wp_unslash($_POST['woocommerce_meta_nonce']),'woocommerce_save_data'))return;
  $enabled=isset($_POST['_rgco_enabled']);
  try{
   $from=self::parse_time(sanitize_text_field(wp_unslash($_POST['_rgco_start']??'')));
   $until=self::parse_time(sanitize_text_field(wp_unslash($_POST['_rgco_end']??'')));
   if($from && $until && $until<=$from)throw new InvalidArgumentException('Closing time must be after opening time. Coupon Studio rules were not changed.');
   if($enabled && !in_array($coupon->get_discount_type(),['percent','fixed_cart','fixed_product'],true))throw new InvalidArgumentException('Use a percentage or fixed discount for Coupon Studio.');
   if($enabled && $coupon->get_discount_type()==='percent' && ((float)$coupon->get_amount()>100 || (float)$coupon->get_amount()<0))throw new InvalidArgumentException('Percentage must be between 0 and 100.');
   foreach(['_rgco_enabled','_rgco_stack','_rgco_once_ip','_rgco_once_account','_rgco_once_email'] as $key)$coupon->update_meta_data($key,isset($_POST[$key])?'yes':'no');
   $coupon->update_meta_data('_rgco_start',$from);$coupon->update_meta_data('_rgco_end',$until);
   if($enabled){$coupon->set_individual_use(false);$coupon->set_usage_limit_per_user(0);$coupon->set_date_expires(null);}
   $coupon->save();
  }catch(Throwable $e){WC_Admin_Meta_Boxes::add_error(esc_html($e->getMessage()));}
 }
 static function settings(){
  self::access();check_admin_referer('rgco_settings');
  update_option('rgco_retention',max(7,min(730,absint($_POST['retention']??180))),false);
  wp_safe_redirect(admin_url('admin.php?page=rg-coupon-studio&saved=1'));exit;
 }
 static function date($utc){return wp_date('d M Y, H:i:s',strtotime($utc.' UTC'),wp_timezone());}
 static function page(){
  self::access();nocache_headers();global $wpdb;
  $attempts=RGCO_Storage::table('attempts');$claims=RGCO_Storage::table('claims');$redemptions=RGCO_Storage::table('redemptions');
  $coupon=absint($_GET['coupon_id']??0);$page=max(1,absint($_GET['paged']??1));$offset=($page-1)*25;
  $where=$coupon?$wpdb->prepare('WHERE coupon_id=%d',$coupon):'';
  $total=(int)$wpdb->get_var("SELECT COUNT(*) FROM $attempts $where");
  $applied=(int)$wpdb->get_var("SELECT COUNT(*) FROM $attempts ".($where?$where.' AND': 'WHERE')." outcome='applied'");
  $paid=(int)$wpdb->get_var("SELECT COUNT(*) FROM $redemptions $where");
  $reserved=(int)$wpdb->get_var("SELECT COUNT(DISTINCT order_id) FROM $claims ".($where?$where.' AND':'WHERE')." state='reserved'");
  $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM $attempts $where ORDER BY id DESC LIMIT %d OFFSET %d",25,$offset));
  $coupons=get_posts(['post_type'=>'shop_coupon','post_status'=>['publish','draft','pending'],'posts_per_page'=>100,'orderby'=>'date','order'=>'DESC']);
  ?><div class="wrap rgco"><style>
 .rgco{--rg:#72383d;--ink:#322d29;--line:#e4dcd4;color:var(--ink);max-width:1320px;padding:14px 8px 40px}.rgco *{box-sizing:border-box}.rgco h1{font-size:29px;line-height:1.2;font-weight:600;color:var(--ink)}.rgco p,.rgco td,.rgco th,.rgco label,.rgco select,.rgco input{font-size:14px;line-height:1.6}.rgco .lead{max-width:820px;color:#665950}.rgco .rgco-actions{display:flex;gap:9px;flex-wrap:wrap;align-items:center}.rgco .button-primary{background:var(--rg);border-color:var(--rg);border-radius:8px;padding:3px 15px;box-shadow:none}.rgco .rgco-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:24px 0}.rgco .stat,.rgco .panel{border:1px solid var(--line);border-radius:14px;background:#fff;padding:20px}.rgco .stat strong{display:block;font-size:31px;line-height:1.3;font-weight:600}.rgco .stat span{color:#6a5f56}.rgco h2{font-size:19px;font-weight:600;margin:0 0 13px}.rgco .panel{margin:16px 0}.rgco .scroll{overflow:auto;max-width:100%}.rgco table{width:100%;border-collapse:collapse;min-width:720px}.rgco th{text-align:left;background:#f6f2ed;font-weight:600}.rgco td,.rgco th{padding:11px 12px;border-bottom:1px solid #eee8e2;vertical-align:top}.rgco .tag{display:inline-block;background:#efe9e1;color:var(--ink);border-radius:999px;padding:2px 9px;white-space:nowrap;font-size:12px}.rgco .tag.applied{background:#e7f3e9;color:#225733}.rgco details summary{cursor:pointer;color:var(--rg);font-weight:600}.rgco pre{white-space:pre-wrap;overflow-wrap:anywhere;font:12px/1.6 ui-monospace,monospace;max-width:600px}.rgco small{color:#776b60}.rgco .rgco-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.rgco select{max-width:100%}.rgco .qa-result{border-left:3px solid #578268;padding:7px 12px;background:#f1f7f1}.rgco a:focus-visible,.rgco summary:focus-visible{outline:2px solid var(--rg);outline-offset:3px}@media(max-width:782px){.rgco{padding:8px 0 30px}.rgco h1{font-size:25px}.rgco .rgco-stats{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.rgco .stat,.rgco .panel{padding:14px}.rgco .stat strong{font-size:27px}.rgco .rgco-grid{grid-template-columns:1fr}.rgco .rgco-actions>*{max-width:100%}}
 </style><h1>Coupon Studio</h1><p class="lead">One coupon system for your cart and checkout. Review attempted codes separately from successful checkouts. All times below use <?php echo esc_html(wp_timezone_string());?>.</p>
 <div class="rgco-actions"><a class="button button-primary" href="<?php echo esc_url(admin_url('post-new.php?post_type=shop_coupon'));?>">Create coupon</a><a class="button" href="<?php echo esc_url(admin_url('edit.php?post_type=shop_coupon'));?>">All coupons</a></div>
 <div class="rgco-stats"><?php foreach([[$total,'Attempts'],[$applied,'Applied to cart'],[$paid,'Successful redemptions'],[$reserved,'Orders with reserved limits']] as [$n,$label])echo '<div class="stat"><strong>'.esc_html(number_format_i18n($n)).'</strong><span>'.esc_html($label).'</span></div>';?></div>
 <div class="panel"><h2>Coupon activity</h2><form method="get" class="rgco-actions"><input type="hidden" name="page" value="rg-coupon-studio"><label for="rgco-filter">Coupon</label><select id="rgco-filter" name="coupon_id"><option value="0">All entered codes</option><?php foreach($coupons as $post)echo '<option value="'.(int)$post->ID.'" '.selected($coupon,$post->ID,false).'>'.esc_html($post->post_title).'</option>';?></select><button class="button">Filter</button></form><p><small>Attempts include rejected and incomplete checkouts. Invalid code text is hashed; customer snapshots are encrypted in the database. Details are visible only to store managers.</small></p>
 <div class="scroll" tabindex="0" aria-label="Coupon activity table"><table><thead><tr><th>Time</th><th>Coupon</th><th>Result</th><th>Customer and cart</th><th>Source</th></tr></thead><tbody><?php
 foreach($rows as $r){$data=RGCO_Storage::decrypt($r->payload);$name=$r->coupon_id?wc_get_coupon_code_by_id($r->coupon_id):'';
 echo '<tr><td>'.esc_html(self::date($r->created_at)).'</td><td>'.esc_html($name?:'Unknown code · '.substr($r->fingerprint,0,8)).'</td><td><span class="tag '.esc_attr($r->outcome).'">'.esc_html($r->outcome).'</span></td><td><details><summary>'.esc_html($data['account_name']?:($data['email']?:'Guest · email unavailable')).' · '.count($data['cart']??[]).' cart lines</summary><pre>'.esc_html(wp_json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)).'</pre></details></td><td>'.esc_html($r->source).'</td></tr>';
 }
 if(!$rows)echo '<tr><td colspan="5">No coupon attempts recorded yet.</td></tr>';
 ?></tbody></table></div><p><?php echo wp_kses_post(paginate_links(['base'=>add_query_arg(['page'=>'rg-coupon-studio','coupon_id'=>$coupon,'paged'=>'%#%'],admin_url('admin.php')),'format'=>'','current'=>$page,'total'=>max(1,ceil($total/25))]));?></p></div>
 <div class="rgco-grid"><div class="panel"><h2>How redemption limits work</h2><p>Only a confirmed payment or completed cash-on-delivery order counts. Trying or applying a code does not count. Pending payments reserve the allowance; cancel the unpaid order to release it. Paid refunds keep the successful-use record.</p><p>IP restrictions apply to a connection, not a person. Shared networks may share an allowance; changing networks is possible. Combine account and email restrictions for stronger protection.</p><p>Coupons are never listed publicly. An extension with access to the shopper’s browser can still read a code they enter. This cannot be prevented by a website.</p></div>
 <div class="panel"><h2>Analytics retention</h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="rgco_settings"><?php wp_nonce_field('rgco_settings');?><label for="rgco-retention">Keep attempt snapshots for</label> <input id="rgco-retention" name="retention" type="number" min="7" max="730" value="<?php echo (int)get_option('rgco_retention',180);?>" style="width:80px"> days <button class="button">Save</button></form><p>Successful-use records stay in the database to enforce limits. Back up all <code>rgco_*</code> tables, coupon/order metadata and the private <code>rgco_secret</code> option together. Updates and deactivation do not erase them.</p><p>No new scripts, fonts or pictures load in your storefront.</p></div></div>
 <?php if(self::staging()):?><div class="panel"><h2>Staging verification</h2><p>Runs isolated coupon and database checks with disposable QA identities. No payment gateway is called. Existing customer orders and carts are not changed.</p><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="rgco_qa"><?php wp_nonce_field('rgco_qa');?><button class="button">Run staging checks</button></form><?php $result=get_option('rgco_qa_result');if($result){echo '<p>Last run: '.esc_html(self::date($result['time'])).'</p>';foreach($result['checks'] as $c)echo '<p class="qa-result">'.esc_html($c).'</p>';}?></div><?php endif;?></div><?php
 }
 static function staging(){return wp_parse_url(home_url(),PHP_URL_HOST)==='testarea.roungroun.com';}
 static function qa(){
  self::access();check_admin_referer('rgco_qa');if(!self::staging())wp_die('QA is restricted to staging.');
  require_once __DIR__.'/qa.php';$checks=RGCO_QA::run();update_option('rgco_qa_result',['time'=>gmdate('Y-m-d H:i:s'),'checks'=>$checks],false);
  wp_safe_redirect(admin_url('admin.php?page=rg-coupon-studio'));exit;
 }
}
