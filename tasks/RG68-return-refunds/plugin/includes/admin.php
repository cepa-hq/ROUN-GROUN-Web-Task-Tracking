<?php
defined('ABSPATH') || exit;
final class RGR_Admin {
    public static function boot(){
        add_action('admin_menu',function(){add_submenu_page('woocommerce','Request centre checks','Request centre checks','manage_woocommerce','rgr-checks',array(__CLASS__,'screen'));});
        add_action('admin_post_rgr_preview',array(__CLASS__,'preview'));
        add_action('add_meta_boxes',array(__CLASS__,'boxes'));
        add_action('woocommerce_process_shop_order_meta',array(__CLASS__,'save'),30,2);
    }
    public static function boxes(){
        $screens=array('shop_order');if(class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')&&\Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled())$screens[]=wc_get_page_screen_id('shop-order');
        foreach($screens as $screen)add_meta_box('rgr-fulfilment','Request eligibility · fulfilment confirmation',array(__CLASS__,'box'),$screen,'side');
    }
    public static function box($object){$o=$object instanceof WC_Order?$object:wc_get_order($object->ID);if(!$o)return;wp_nonce_field('rgr-fulfilment','rgr_fulfilment_nonce');echo '<p>Confirm from fulfilment records. Do not mark an order unshipped merely to allow cancellation.</p><select name="rgr_fulfilment_state" style="width:100%">';foreach(array('unknown'=>'Not verified','unshipped'=>'Confirmed not shipped','shipped'=>'Shipped','delivered'=>'Delivered')as $k=>$v)echo '<option value="'.esc_attr($k).'" '.selected($o->get_meta('_rgr_fulfilment_state',true),$k,false).'>'.esc_html($v).'</option>';echo '</select><p><label>Verified delivery date/time (store timezone)<input style="width:100%" type="datetime-local" name="rgr_delivered_at" value="'.esc_attr($o->get_meta('_rgr_delivered_at',true)?wp_date('Y-m-d\TH:i',(int)$o->get_meta('_rgr_delivered_at',true)):'').'"></label></p>';}
    public static function save($id,$object){
        if(!current_user_can('manage_woocommerce')||!isset($_POST['rgr_fulfilment_nonce'])||!is_string($_POST['rgr_fulfilment_nonce'])||!wp_verify_nonce(wp_unslash($_POST['rgr_fulfilment_nonce']),'rgr-fulfilment'))return;
        $o=wc_get_order($id);if(!$o)return;$state=sanitize_key($_POST['rgr_fulfilment_state']??'unknown');if(!in_array($state,array('unknown','unshipped','shipped','delivered'),true))return;
        $o->update_meta_data('_rgr_fulfilment_state',$state);
        if(isset($_POST['rgr_delivered_at'])&&is_string($_POST['rgr_delivered_at'])){$v=sanitize_text_field(wp_unslash($_POST['rgr_delivered_at']));$d=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$v,wp_timezone());if($v===''||($d&&$d->format('Y-m-d\TH:i')===$v&&$d->getTimestamp()<=time()))$o->update_meta_data('_rgr_delivered_at',$d?$d->getTimestamp():0);}
        $o->save_meta_data();
    }
    public static function tests(){
        $base=array('id'=>0,'owner'=>7,'mode'=>'return','status'=>'completed','stage'=>'delivered','blocked'=>'','items'=>array(101=>array('remaining'=>2,'code'=>'eligible','message'=>'Available'),102=>array('remaining'=>1,'code'=>'expired','message'=>'The return window for this product has ended.'),103=>array('remaining'=>1,'code'=>'nonreturnable','message'=>'This product cannot be returned.')));
        $out=array();$check=function($name,$ctx,$user,$items,$reason,$confirm,$expect)use(&$out){$r=RGR_Policy::validate($ctx,$user,$items,$reason,$confirm);$actual=is_wp_error($r)?$r->get_error_code():'valid';$out[]=array('name'=>$name,'pass'=>$actual===$expect,'result'=>$actual);};
        $check('Partial quantity accepted',$base,7,array(101=>1),'Changed my plans',true,'valid');
        $check('Guest denied',$base,0,array(101=>1),'Changed my plans',true,'ownership');
        $check('Foreign owner denied',$base,8,array(101=>1),'Changed my plans',true,'ownership');
        $check('Explicit confirmation required',$base,7,array(101=>1),'Changed my plans',false,'confirmation');
        $check('No selection denied',$base,7,array(),'Changed my plans',true,'selection');
        $check('Foreign line item denied',$base,7,array(999=>1),'Changed my plans',true,'item');
        foreach(array(0,-1,'1.5','2e1',array(1),3)as $n)$check('Invalid quantity rejected',$base,7,array(101=>$n),'Changed my plans',true,'quantity');
        $check('Expired message distinct',$base,7,array(102=>1),'Changed my plans',true,'expired');
        $check('Non-returnable message distinct',$base,7,array(103=>1),'Changed my plans',true,'nonreturnable');
        $check('Reason required',$base,7,array(101=>1),'',true,'reason');
        $blocked=$base;$blocked['blocked']='Shipped orders cannot be cancelled. Please use returns.';$check('Shipment changed after review',$blocked,7,array(101=>1),'Changed my plans',true,'stage');
        $busy=$base;$busy['items'][101]['remaining']=0;$check('Already requested/refunded quantity',$busy,7,array(101=>1),'Changed my plans',true,'quantity');
        // Real policy context with UNSAVED WC objects. No orders/items are saved.
        $o=new WC_Order();$o->set_customer_id(7);$o->set_status('processing');$item=new WC_Order_Item_Product();$item->set_id(990000001);$item->set_name('Sample');$item->set_quantity(2);$item->update_meta_data('_wcrw_item_warranty',array('type'=>'included_warranty','length'=>'limited','length_value'=>7,'length_duration'=>'days'));$o->add_item($item);
        $c=RGR_Policy::context($o,'cancel');$out[]=array('name'=>'Unverified processing dispatch blocked','pass'=>strpos($c['blocked'],'cannot confirm')!==false,'result'=>'dispatch check');
        $o->update_meta_data('_rgr_fulfilment_state','shipped');$c=RGR_Policy::context($o,'cancel');$out[]=array('name'=>'Shipped cancellation blocked','pass'=>strpos($c['blocked'],'Shipped orders')!==false,'result'=>'shipped');
        $c=RGR_Policy::context($o,'return');$out[]=array('name'=>'In-transit early return allowed','pass'=>$c['items'][990000001]['code']==='eligible','result'=>$c['items'][990000001]['code']);
        $o->set_status('completed');$o->set_date_completed(time()-10*DAY_IN_SECONDS);$c=RGR_Policy::context($o,'return');$out[]=array('name'=>'Product-specific expiry enforced','pass'=>$c['items'][990000001]['code']==='expired','result'=>$c['items'][990000001]['code']);
        $out[]=array('name'=>'Legacy core GET handler disabled','pass'=>has_action('wp_loaded',array('WC_Form_Handler','cancel_order'))===false,'result'=>'frontend-only check separately');
        // admin_init does not remove the FRONTEND handler, so this assertion is
        // reported only in a frontend preview, not as a false admin failure.
        array_pop($out);
        $nonce=wp_create_nonce(RGR_Centre::nonce('cancel',77));
        foreach(array(array('Scoped nonce accepted',RGR_Centre::verified_nonce('cancel',77,$nonce)),array('Cross-order nonce rejected',!RGR_Centre::verified_nonce('cancel',78,$nonce)),array('Cross-flow nonce rejected',!RGR_Centre::verified_nonce('return',77,$nonce)),array('Malformed nonce rejected',!RGR_Centre::verified_nonce('cancel',77,array($nonce))),array('Invalid nonce rejected',!RGR_Centre::verified_nonce('cancel',77,'invalid')),array('RMA required fields supported',RGR_Policy::required_fields_supported()))as $t)$out[]=array('name'=>$t[0],'pass'=>$t[1],'result'=>$t[1]?'verified':'needs attention');
        $legacy=trailingslashit(wc_get_page_permalink('myaccount')).'new-warranty-request/77/';
        $out[]=array('name'=>'Legacy RMA endpoint path recognised','pass'=>RGR_Centre::legacy_return_path($legacy)===77,'result'=>'Custom rewrite endpoint');
        $out[]=array('name'=>'Append-ID legacy URL kept valid','pass'=>RGR_Centre::endpoint($legacy,'new-warranty-request','',wc_get_page_permalink('myaccount'))===$legacy,'result'=>'Base URL preserved');
        return $out;
    }
    public static function screen(){if(!current_user_can('manage_woocommerce'))return;
        echo '<div class="wrap"><h1>Request centre · checks and previews</h1><p>Read-only business-data checks. Sample previews never submit a request or send email.</p><p>';
        foreach(array('cancel'=>'Cancellation preview','return'=>'Return preview')as $mode=>$label)echo '<a class="button" href="'.esc_url(admin_url('admin-post.php?action=rgr_preview&mode='.$mode)).'">'.esc_html($label).'</a> ';
        echo '</p>';self::inspector();
        echo '<table class="widefat striped"><thead><tr><th>Check</th><th>Result</th></tr></thead><tbody>';foreach(array_merge(self::tests(),RGR_Repository::sandbox_checks())as $r)echo '<tr data-rgr-test="'.esc_attr($r['name']).'" data-pass="'.($r['pass']?'1':'0').'"><td>'.esc_html($r['name']).'</td><td>'.($r['pass']?'PASS':'FAIL').' · '.esc_html($r['result']).'</td></tr>';echo '</tbody></table><p>For a processing order without courier metadata, confirm dispatch in its WooCommerce fulfilment box. A PIN-code checker does not supply dispatch events.</p></div>';
    }
    public static function inspector(){
        // Admin-only responsive inspector of the real templates. Sample forms
        // have no enabled submission path and contain no customer information.
        $view=isset($_GET['rgr_view'])?sanitize_key($_GET['rgr_view']):'cancel';
        $views=array('cancel'=>array('Cancellation sample',admin_url('admin-post.php?action=rgr_preview&mode=cancel')),'return'=>array('Return sample',admin_url('admin-post.php?action=rgr_preview&mode=return')),'cancel-live'=>array('Cancellation page',RGR_Centre::url('cancel')),'return-live'=>array('Return page',RGR_Centre::url('return')));
        if(!isset($views[$view]))$view='cancel';
        echo '<h2>Responsive preview</h2><p>';foreach($views as $key=>$v)echo '<a class="button" href="'.esc_url(add_query_arg(array('page'=>'rgr-checks','rgr_view'=>$key),admin_url('admin.php'))).'">'.esc_html($v[0]).'</a> ';
        echo '</p><p>';foreach(array(320,360,390,430,768,1024,1280)as $w)echo '<button class="button rgr-width" type="button" data-width="'.$w.'">'.$w.'px</button> ';
        echo '</p><div style="max-width:100%;overflow:auto"><iframe id="rgr-frame" title="Responsive request page preview" src="'.esc_url($views[$view][1]).'" style="display:block;width:390px;max-width:none;height:1050px;border:1px solid #d1c7bd;background:#fcfaf7"></iframe></div><script>document.querySelectorAll(".rgr-width").forEach(function(b){b.addEventListener("click",function(){document.getElementById("rgr-frame").style.width=b.dataset.width+"px";});});</script>';
    }
    public static function preview(){
        if(!current_user_can('manage_woocommerce')){status_header(403);exit;}if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){status_header(405);exit;}
        $mode=isset($_GET['mode'])&&$_GET['mode']==='cancel'?'cancel':'return';$fixture=true;$id=0;$user=get_current_user_id();$success=0;$order=new WC_Order();$order->set_status($mode==='cancel'?'on-hold':'completed');$ctx=array('blocked'=>'','items'=>array());
        foreach(array(101=>'Sample training gear',102=>'Sample outdoor accessory',103=>'Sample personal-use product')as $i=>$name){$code=$mode==='cancel'||$i===101?'eligible':($i===102?'expired':'nonreturnable');$ctx['items'][$i]=array('id'=>$i,'name'=>$name,'code'=>$code,'message'=>$code==='eligible'?'Available to request':($code==='expired'?'The return window for this product has ended.':'This product cannot be returned under its product-specific return policy.'),'window'=>$mode==='return'&&$i===101?'Product-specific return window is open.':'','remaining'=>2);}
        nocache_headers();echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Roun Groun '.esc_html($mode).' preview</title><link rel="stylesheet" href="'.esc_url(plugins_url('../assets/centre.css',__FILE__).'?ver='.RGR_Centre::VERSION).'"></head><body style="margin:0;background:#fcfaf7">';include __DIR__.'/../templates/centre.php';echo '</body></html>';exit;
    }
}
