<?php
namespace RGFS;
defined('ABSPATH') || exit;

final class Admin {
    public static function can():bool{return current_user_can('manage_options')||current_user_can('manage_woocommerce');}
    public static function capability():string{return current_user_can('manage_options')?'manage_options':'manage_woocommerce';}
    public static function boot():void {
        add_action('admin_menu',[self::class,'menu']);
        add_action('admin_enqueue_scripts',[self::class,'assets']);
        add_action('rest_api_init',[self::class,'routes']);
        add_action('add_meta_boxes',[self::class,'order_box']);
        add_filter('plugin_action_links_'.plugin_basename(FILE),function($links){array_unshift($links,'<a href="'.esc_url(self::url()).'">Dashboard</a>');return $links;});
    }
    public static function url($slug='general-feedback-mk1'):string{return admin_url('admin.php?page=rgfs-'.$slug);}
    public static function menu():void {
        add_menu_page('Roun Groun Feedback system','Roun Groun Feedback system',self::capability(),'rgfs',[self::class,'home'],'dashicons-feedback',58);
        add_submenu_page('rgfs','Feedback forms','Forms',self::capability(),'rgfs',[self::class,'home']);
        foreach(Forms::all() as $form)add_submenu_page('rgfs',$form['title'],$form['title'],self::capability(),'rgfs-'.$form['slug'],function()use($form){self::dashboard($form);});
    }
    public static function home():void{
        if(!self::can())wp_die('You cannot view feedback.');
        echo '<div class="wrap rgfs-admin"><h1>Roun Groun Feedback system</h1><p>Your forms, customer insights and gift fulfilment.</p><div class="rgfs-grid">';
        foreach(Forms::all() as $form)echo '<section class="rgfs-card"><span class="rgfs-tag">FORM · V'.absint($form['version']).'</span><h2>'.esc_html($form['title']).'</h2><p>Account-linked feedback, question results and delivery gifts.</p><a class="button button-primary" href="'.esc_url(self::url($form['slug'])).'">Open dashboard</a></section>';
        echo '</div></div>';
    }
    public static function dashboard($form):void{
        if(!self::can())wp_die('You cannot view feedback.');
        $url=Frontend::url($form['slug']);
        ?><div class="wrap rgfs-admin" id="rgfs-admin"><header class="rgfs-heading"><div><span class="rgfs-tag">ROUN GROUN FEEDBACK SYSTEM</span><h1><?php echo esc_html($form['title']); ?></h1><p>Customer feedback, clear results and a gift with their delivery.</p></div><button type="button" class="button" id="rgfs-refresh">Refresh results</button></header>
        <section class="rgfs-card rgfs-links"><h2>Share this form</h2><p>Customers sign in first. A valid signed-in session takes them straight to the form. Links are not added to the website menus.</p><label for="rgfs-link">Form link</label><div class="rgfs-link-row"><input readonly id="rgfs-link" value="<?php echo esc_attr($url); ?>"><button class="button" data-copy="rgfs-link">Copy link</button><a class="button" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener">Open form</a></div><details><summary>Make an order or campaign link</summary><p>Order links work only for the customer who owns that order.</p><div class="rgfs-filter-row"><label>Order ID (optional)<input type="number" min="1" id="rgfs-link-order"></label><label>Campaign name (optional)<input id="rgfs-link-campaign" maxlength="64" placeholder="delivery-email"></label><button class="button" id="rgfs-build-link">Make link</button></div><input readonly id="rgfs-made-link" aria-label="Generated survey link"><button class="button" data-copy="rgfs-made-link">Copy made link</button></details></section>
        <?php if(!Store::ready()): ?><div class="notice notice-error inline"><p>Feedback storage needs attention. Submissions are disabled until storage is ready.</p></div><?php endif; ?>
        <form class="rgfs-card" id="rgfs-filters"><h2>Filter results</h2><div class="rgfs-filter-row"><label>From<input type="date" name="from"></label><label>To<input type="date" name="to"></label><label>Device<select name="device"><option value="">All devices</option><option value="phone">Phone</option><option value="tablet">Tablet</option><option value="desktop">Desktop</option><option value="unknown">Unknown</option></select></label><label>Campaign<input name="campaign" maxlength="64"></label><label>Name, username, email or order ID<input name="search" type="search"></label><label>Gift<select name="gift"><option value="">All gifts</option><option value="waiting_order">Waiting for order</option><option value="ready">Ready to pack</option><option value="packed">Packed</option></select></label><label>Data<select name="data"><option value="customers">Customer results</option><option value="tests">Staff tests</option></select></label><label>Question<select name="question" id="rgfs-question"><option value="">All questions</option></select></label><label>Answer<select name="option" id="rgfs-option"><option value="">All answers</option></select></label><button class="button button-primary" type="submit">Apply filters</button><button class="button" type="reset">Clear filters</button></div></form>
        <p class="rgfs-status" id="rgfs-status" role="status" aria-live="polite">Loading results…</p><div id="rgfs-report" aria-busy="true"></div>
        <section class="rgfs-card rgfs-method"><h2>How to read these results</h2><p>Every percentage uses the number of people who answered that question. People can choose more than one answer for some questions, so those percentages can add up to more than 100%. The optional checkout follow-up counts only people who answered it.</p><p>Website ease uses a 1–5 average. Findability and checkout use a 1–4 average with higher meaning easier. “Didn’t try it” is excluded from checkout ratings and shown separately. Positive = the two easiest choices. The recommendation question is a three-choice question, not NPS.</p><p>95% Wilson ranges show sampling uncertainty for answer shares and positive rates. Small samples and customers choosing to reply may not represent all visitors. Ratings are descriptive; they do not prove why something happened. All charts and the list use the same filters. Dates in the filters use the website timezone; the daily chart is labelled UTC.</p><p>Each signed-in account can submit once per form version. Gift eligibility does not depend on any answer. Ready gifts must be checked against dispatch before packing; this plugin tracks the task, and does not insert a product or send another shipment.</p></section></div><?php
    }
    public static function assets($hook):void {
        $page=isset($_GET['page'])?sanitize_key($_GET['page']):'';if($page!=='rgfs'&&strpos($page,'rgfs-')!==0)return;
        wp_enqueue_style('rgfs-admin',plugins_url('assets/admin.css',FILE),[],VERSION);
        if($page==='rgfs')return;$form=Forms::get(substr($page,5));if(!$form)return;
        wp_enqueue_script('rgfs-admin',plugins_url('assets/admin.js',FILE),[],VERSION,true);
        wp_add_inline_script('rgfs-admin','const RGFSAdmin='.wp_json_encode(['root'=>rest_url('rgfs/v1/'),'nonce'=>wp_create_nonce('wp_rest'),'form'=>$form,'share'=>Frontend::url($form['slug'])],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).';','before');
    }
    public static function permitted(){return self::can()?true:new \WP_Error('rgfs_forbidden','You cannot view customer feedback.',['status'=>403]);}
    public static function write_permitted($req){$ok=self::permitted();return is_wp_error($ok)?$ok:Frontend::csrf($req);}
    public static function routes():void {
        register_rest_route('rgfs/v1','/dashboard',['methods'=>'GET','permission_callback'=>[self::class,'permitted'],'callback'=>function($req){$form=Forms::get((string)($req->get_param('form')?:'general-feedback-mk1'));return $form?Frontend::response(Analytics::report($req,$form)):new \WP_Error('rgfs_form','Form not found.',['status'=>404]);}]);
        register_rest_route('rgfs/v1','/entries/(?P<id>\d+)',['methods'=>'GET','permission_callback'=>[self::class,'permitted'],'callback'=>[self::class,'detail']]);
        register_rest_route('rgfs/v1','/rewards/(?P<id>\d+)',['methods'=>'POST','permission_callback'=>[self::class,'write_permitted'],'callback'=>[self::class,'gift']]);
        register_rest_route('rgfs/v1','/diagnostics',['methods'=>'GET','permission_callback'=>[self::class,'permitted'],'callback'=>function(){global $wpdb;$tables=[];foreach(['entries','answers','rewards','audit'] as $n){$row=$wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name=%s',Store::table($n)));$tables[$n]=['exists'=>(bool)$row,'engine'=>$row?$row->Engine:null,'rows'=>$row?(int)$wpdb->get_var('SELECT COUNT(*) FROM '.Store::table($n)):null];}return Frontend::response(['version'=>VERSION,'storage_ready'=>Store::ready(),'tables'=>$tables,'form_hash'=>Forms::get('general-feedback-mk1')['hash'],'authenticated_user'=>get_current_user_id()]);}]);
    }
    public static function detail($req){
        global $wpdb;$e=Store::table('entries');$r=Store::table('rewards');$l=Store::table('audit');$id=absint($req['id']);
        $row=$wpdb->get_row($wpdb->prepare("SELECT e.*,r.state gift_state,r.order_id gift_order_id FROM $e e LEFT JOIN $r r ON r.entry_id=e.id WHERE e.id=%d",$id),ARRAY_A);
        if(!$row)return new \WP_Error('rgfs_entry','Entry not found.',['status'=>404]);
        $data=Analytics::entry($row);$data['orders']=[];
        foreach(Store::orders((int)$row['user_id']) as $o)$data['orders'][]=['id'=>$o->get_id(),'number'=>$o->get_order_number(),'status'=>wc_get_order_status_name($o->get_status()),'url'=>$o->get_edit_order_url()];
        $data['history']=$wpdb->get_results($wpdb->prepare("SELECT actor_id,action,detail,created_at FROM $l WHERE entry_id=%d ORDER BY id DESC LIMIT 30",$id),ARRAY_A);
        return Frontend::response($data);
    }
    public static function gift($req){
        global $wpdb;$id=absint($req['id']);$state=(string)$req->get_param('state');$order_id=absint($req->get_param('order_id'));
        if(!in_array($state,['ready','packed','waiting_order'],true))return new \WP_Error('rgfs_state','Choose a gift status.',['status'=>422]);
        $e=Store::table('entries');$r=Store::table('rewards');$wpdb->query('START TRANSACTION');
        $row=$wpdb->get_row($wpdb->prepare("SELECT e.user_id,e.is_test,r.* FROM $r r JOIN $e e ON e.id=r.entry_id WHERE r.entry_id=%d FOR UPDATE",$id),ARRAY_A);
        if(!$row||!$row['user_id']||$row['is_test']){$wpdb->query('ROLLBACK');return new \WP_Error('rgfs_reward','This entry has no customer gift task.',['status'=>409]);}
        if($row['state']==='packed'){$wpdb->query('ROLLBACK');return new \WP_Error('rgfs_packed','This gift is already marked packed.',['status'=>409]);}
        if($state==='packed')$order_id=(int)$row['order_id'];
        if($state==='waiting_order')$order_id=0;
        else{$order=function_exists('wc_get_order')?wc_get_order($order_id):null;if(!$order||(int)$order->get_customer_id()!==(int)$row['user_id']||!Store::gift_order($order)||($state==='packed'&&$row['state']!=='ready')){$wpdb->query('ROLLBACK');return new \WP_Error('rgfs_gift_order','Choose this customer’s physical order that is still processing. Check dispatch before marking packed.',['status'=>422]);}}
        $ok=$wpdb->update($r,['state'=>$state,'order_id'=>$order_id,'updated_at'=>gmdate('Y-m-d H:i:s')],['entry_id'=>$id]);
        if($ok===false||!Store::audit($id,'gift_'.$state,$row['state'].' → '.$state.'; order '.$order_id)){$wpdb->query('ROLLBACK');return new \WP_Error('rgfs_gift_save','The gift task was not updated. Try again.',['status'=>503]);}
        $wpdb->query('COMMIT');return Frontend::response(['updated'=>true,'state'=>$state,'order_id'=>$order_id]);
    }
    public static function order_box():void{
        $screen=class_exists('Automattic\\WooCommerce\\Utilities\\OrderUtil')&&\Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()?'woocommerce_page_wc-orders':'shop_order';
        add_meta_box('rgfs-order-gift','Feedback surprise gifts',[self::class,'order_content'],$screen,'side','default');
    }
    public static function order_content($object):void{
        if(!self::can())return;$order=$object instanceof \WP_Post?wc_get_order($object->ID):$object;if(!$order)return;
        global $wpdb;$r=Store::table('rewards');$e=Store::table('entries');
        $rows=$wpdb->get_results($wpdb->prepare("SELECT r.entry_id,r.state,e.form_slug FROM $r r JOIN $e e ON e.id=r.entry_id WHERE r.order_id=%d AND e.is_test=0 AND e.user_id=%d",$order->get_id(),$order->get_customer_id()));
        if(!$rows)echo '<p>No feedback gift linked to this order.</p>';
        foreach($rows as $row)echo '<p><strong>'.esc_html(ucwords(str_replace('_',' ',$row->state))).'</strong> · Feedback #'.absint($row->entry_id).'</p><a href="'.esc_url(self::url($row->form_slug)).'">Open feedback dashboard</a>';
        echo '<p>Check gift status in the feedback dashboard before dispatch.</p>';
    }
}
