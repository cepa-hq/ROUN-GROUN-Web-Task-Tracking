<?php
defined('ABSPATH') || exit;
final class RGR_Calm {
 static $screen='';
 static function boot(){
  add_action('template_redirect',[__CLASS__,'start'],2000);
  add_action('wp_enqueue_scripts',function(){if(is_account_page()||RGR_Centre::mode()){wp_enqueue_style('rgr-calm',plugins_url('../assets/calm.css',__FILE__),[],RGR_Centre::VERSION);wp_enqueue_script('rgr-refund-form',plugins_url('../assets/refund-form.js',__FILE__),[],RGR_Centre::VERSION,true);}},2000);
  add_action('wp_head',function(){if(isset($_GET['rgr_refund']))echo '<script>(function(){try{var id=new URLSearchParams(location.search).get("rgr_refund"),key="rgr-refund-invite:"+id,m=location.hash.match(/^#rgr_token=([a-f0-9]{64})$/);if(m){sessionStorage.setItem(key,JSON.stringify({token:m[1],until:Date.now()+172800000}));history.replaceState(null,"",location.pathname+location.search);}var saved=JSON.parse(sessionStorage.getItem(key)||"null");if(saved&&saved.until>Date.now())window.rgrInvitation=saved.token;if(new URLSearchParams(location.search).has("rgr_saved"))sessionStorage.removeItem(key);}catch(e){}})();</script>';},-999);
 }
 static function start(){
  if(!is_account_page())return;
  $path=(string)wp_parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH);$old=preg_match('~/view-warranty-request/([0-9]+)/?$~',$path,$m);
  if(!$old && !str_contains($path,'/warranty-requests'))return;
  nocache_headers();header('X-Robots-Tag: noindex, nofollow');header('Referrer-Policy: no-referrer');
  if($old){$_GET['rgr_request']=(int)$m[1];global $wp;$wp->query_vars['warranty-requests']='';unset($wp->query_vars['view-warranty-request']);}
  ob_start();try{self::screen();}catch(Throwable $e){echo '<div class="rgr-calm"><section class="panel"><h2>Request details are temporarily unavailable</h2><p>Please reload or contact customer care.</p></section></div>';}self::$screen=ob_get_clean();
  ob_start([__CLASS__,'replace']);
 }
 static function replace($html){
  if(!is_user_logged_in())return $html;
  $start=strpos($html,'<div id="rga-screen">');$end=$start===false?false:strpos($html,'</div><footer class="account-footer">',$start);
  if($start===false||$end===false)return $html;
  $body=self::$screen;$start+=strlen('<div id="rga-screen">');return substr($html,0,$start).$body.substr($html,$end);
 }
 static function url($id=0){return $id?add_query_arg('rgr_request',(int)$id,wc_get_account_endpoint_url('warranty-requests')):wc_get_account_endpoint_url('warranty-requests');}
 static function state($r){if($r['status']==='reviewing')return 'processing';return in_array($r['status'],['new','processing','completed','rejected'],true)?$r['status']:'new';}
 static function badge($r){$s=self::state($r);return '<span class="badge '.$s.'"><span class="dot" aria-hidden="true"></span>'.esc_html(ucfirst($r['status']==='reviewing'?'reviewing':$s)).'</span>';}
 static function type($r){return ['cancel'=>'Cancellation','refund'=>'Refund','replacement'=>'Replacement'][$r['type']]??'Request';}
 static function items($r){global $wpdb;return $wpdb->get_results($wpdb->prepare("SELECT item_id, product_id, quantity FROM {$wpdb->prefix}wcrw_request_product_map WHERE request_id=%d",$r['id']),ARRAY_A);}
 static function products($r){$order=wc_get_order($r['order_id']);foreach(self::items($r) as $map){$item=$order?$order->get_item($map['item_id']):false;$p=$item?$item->get_product():false;$name=$item?$item->get_name():'Product no longer available';$img=$p?wp_get_attachment_image_url($p->get_image_id()?:($p->get_parent_id()?get_post_thumbnail_id($p->get_parent_id()):0),'woocommerce_thumbnail'):false;echo '<div class="product"><img class="product-image" loading="lazy" width="96" height="96" src="'.esc_url($img?:wc_placeholder_img_src()).'" alt="'.esc_attr($name).'"><div class="product-info"><h3>'.esc_html($name).'</h3></div><span class="quantity">Qty '.(int)$map['quantity'].'</span></div>';}}
 static function heading($title,$desc){echo '<header class="page-heading"><div><p class="eyebrow">HERE TO HELP</p><h1>'.esc_html($title).'</h1><p>'.esc_html($desc).'</p></div><a class="btn secondary" href="'.esc_url(home_url('/refund_returns/')).'">Return policy</a></header>';}
 static function screen(){
  echo '<div class="rgr-calm">';$id=absint($_GET['rgr_refund']??$_GET['rgr_request']??0);
  if($id){$r=RGR_Refund::request($id);if(!RGR_Refund::owned($r,get_current_user_id())){echo '<section class="panel"><h2>Request unavailable</h2><p>This request is not available in your account.</p></section>';}else self::detail($r);}
  else self::listing();echo '</div>';
 }
 static function listing(){
  global $wpdb;$page=max(1,absint($_GET['rgr_page']??1));$filter=sanitize_key($_GET['rgr_status']??'all');$where=$filter==='open'?" AND status IN ('new','processing','reviewing')":($filter==='closed'?" AND status IN ('completed','rejected')":'');
  $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}wcrw_warranty_requests WHERE customer_id=%d $where ORDER BY id DESC LIMIT 10 OFFSET %d",get_current_user_id(),($page-1)*10),ARRAY_A);
  self::heading('Return requests','Your products, quantities and request updates, clearly laid out.');echo '<div class="filters">';foreach(['all'=>'All requests','open'=>'Open','closed'=>'Closed'] as $key=>$label)echo '<a class="filter" aria-pressed="'.($filter===$key?'true':'false').'" href="'.esc_url(add_query_arg('rgr_status',$key,self::url())).'">'.esc_html($label).'</a>';echo '</div><div class="cards">';
  foreach($rows as $r){if(!RGR_Refund::owned($r,get_current_user_id()))continue;echo '<article class="request-card"><header class="card-head"><div class="request-ref"><strong>Request #'.(int)$r['id'].'</strong><span class="type-tag">'.esc_html(self::type($r)).'</span></div>'.self::badge($r).'</header><div class="card-body">';self::products($r);$d=RGR_Refund::read($r);if(in_array($r['type'],['refund','cancel'],true))echo '<p class="small muted" style="padding-bottom:16px">Refund destination: '.esc_html(RGR_Refund::summary($d)).'</p>';echo '</div><footer class="card-foot"><span>Order #'.(int)$r['order_id'].' · '.esc_html(mysql2date('d M Y',$r['created_at'])).'</span><a class="btn quiet" href="'.esc_url(self::url($r['id'])).'">View details '.RGR_Centre::icon('arrow').'</a></footer></article>';}
  if(!$rows)echo '<section class="panel"><h2>No requests in this view</h2><p>Try another filter or start from your order.</p></section>';echo '</div><div class="detail-action">';if($page>1)echo '<a class="btn secondary" href="'.esc_url(add_query_arg(['rgr_page'=>$page-1,'rgr_status'=>$filter],self::url())).'">Previous</a>';if(count($rows)===10)echo '<a class="btn secondary" href="'.esc_url(add_query_arg(['rgr_page'=>$page+1,'rgr_status'=>$filter],self::url())).'">Next</a>';echo '</div><p class="subnote">Submitting a request does not automatically approve a return, cancel an order or issue a refund.</p>';
 }
 static function detail($r){
  $s=self::state($r);$d=RGR_Refund::read($r);$titles=['new'=>'Your request is with us.','processing'=>'A little care. A closer look.','completed'=>'This request is complete.','rejected'=>'This request was not approved.'];$copy=['new'=>'Your request is waiting for our team to review it.','processing'=>'Our team is reviewing the selected products. Next steps will appear here when confirmed.','completed'=>'This request is closed. Any refund payment confirmation is provided separately.','rejected'=>'Review the decision below. Our customer care team can help if something needs another look.'];
  echo '<a class="backbtn" href="'.esc_url(self::url()).'">'.RGR_Centre::icon('arrow').' All requests</a>';self::heading('Request #'.$r['id'],self::type($r).' · Order #'.$r['order_id'].' · '.mysql2date('d M Y',$r['created_at']));
  echo '<section class="status-hero '.$s.'"><div class="status-symbol">'.RGR_Centre::icon($s==='rejected'?'cancel':'shield').'</div><div>'.self::badge($r).'<h2>'.esc_html($titles[$s]).'</h2><p>'.esc_html($copy[$s]).'</p>'.($s==='processing'?'<div class="progressbar" aria-hidden="true"><span></span></div>':'').'</div></section><div class="detail-grid"><div><section class="panel detail-items"><h2>Products in this request</h2>';self::products($r);echo '</section><section class="panel"><h2>Request information</h2><h3 style="margin-top:18px">Your reason</h3><p>'.nl2br(esc_html($r['reasons']?:'No reason recorded.')).'</p>';
  if($s==='rejected')echo '<div class="rejection-note"><strong>Why this request was rejected</strong><p>'.nl2br(esc_html(($d['public_note']??'')?:'Please contact customer care with your request reference for the decision details.')).'</p></div>';
  elseif(!empty($d['public_note']))echo '<div class="notice"><strong>Team update</strong><p>'.nl2br(esc_html($d['public_note'])).'</p></div>';echo '</section>';
  if(in_array($r['type'],['refund','cancel'],true))RGR_Refund::customer($r);echo '</div><aside class="detail-aside"><section class="panel"><h2>Your request journey</h2><ol class="track"><li class="done"><span class="node">1</span><div><strong>Request received</strong><small>'.esc_html(mysql2date('d M Y',$r['created_at'])).'</small></div></li><li class="'.($s==='processing'?'current':(in_array($s,['completed','rejected'],true)?'done':'')).'"><span class="node">2</span><div><strong>Team review</strong><small>'.esc_html($s==='new'?'Waiting for review':($s==='processing'?'Under review':'Review finished')).'</small></div></li><li><span class="node">3</span><div><strong>'.esc_html($s==='rejected'?'Decision shared':($s==='completed'?'Request closed':'Resolution')).'</strong><small>'.esc_html(in_array($s,['new','processing'],true)?'Next steps when confirmed':ucfirst($s)).'</small></div></li></ol></section><section class="panel"><h3>A question about this request?</h3><p>Quote request #'.(int)$r['id'].' when contacting our team.</p><a class="btn secondary" href="'.esc_url(home_url('/contact/')).'">Contact customer care</a></section></aside></div>';
 }
}
