<?php
namespace RGFS;
defined('ABSPATH') || exit;

final class Store {
    const SCHEMA='1';
    public static function table($name): string {global $wpdb;return $wpdb->prefix.'rgfs_'.$name;}
    public static function install(): void {
        global $wpdb;
        require_once ABSPATH.'wp-admin/includes/upgrade.php';
        $collate=$wpdb->get_charset_collate();
        $e=self::table('entries');$a=self::table('answers');$r=self::table('rewards');$l=self::table('audit');
        // InnoDB is mandatory: response+normalised answers+reward must commit together.
        dbDelta("CREATE TABLE $e (
 id bigint unsigned NOT NULL AUTO_INCREMENT,
 form_slug varchar(80) NOT NULL,
 form_version int unsigned NOT NULL,
 schema_hash char(64) NOT NULL,
 user_id bigint unsigned DEFAULT NULL,
 order_id bigint unsigned NOT NULL DEFAULT 0,
 order_ids longtext NOT NULL,
 submitted_at datetime NOT NULL,
 elapsed_seconds int unsigned NOT NULL DEFAULT 0,
 device varchar(12) NOT NULL DEFAULT 'unknown',
 campaign varchar(64) NOT NULL DEFAULT '',
 payload longtext NOT NULL,
 is_test tinyint NOT NULL DEFAULT 0,
 PRIMARY KEY  (id),
 UNIQUE KEY one_reply (form_slug,form_version,user_id),
 KEY form_date (form_slug,form_version,is_test,submitted_at),
 KEY customer (user_id),
 KEY linked_order (order_id)
) ENGINE=InnoDB $collate;");
        dbDelta("CREATE TABLE $a (
 entry_id bigint unsigned NOT NULL,
 question_key varchar(64) NOT NULL,
 option_key varchar(40) NOT NULL,
 PRIMARY KEY  (entry_id,question_key,option_key),
 KEY distribution (question_key,option_key,entry_id)
) ENGINE=InnoDB $collate;");
        dbDelta("CREATE TABLE $r (
 id bigint unsigned NOT NULL AUTO_INCREMENT,
 entry_id bigint unsigned NOT NULL,
 order_id bigint unsigned NOT NULL DEFAULT 0,
 state varchar(24) NOT NULL DEFAULT 'waiting_order',
 updated_at datetime NOT NULL,
 PRIMARY KEY  (id),
 UNIQUE KEY one_reward (entry_id),
 KEY fulfilment (state,order_id)
) ENGINE=InnoDB $collate;");
        dbDelta("CREATE TABLE $l (
 id bigint unsigned NOT NULL AUTO_INCREMENT,
 entry_id bigint unsigned NOT NULL,
 actor_id bigint unsigned NOT NULL DEFAULT 0,
 action varchar(40) NOT NULL,
 detail varchar(190) NOT NULL DEFAULT '',
 created_at datetime NOT NULL,
 PRIMARY KEY  (id),
 KEY entry_history (entry_id,id)
) ENGINE=InnoDB $collate;");
        $ok=true;foreach(['entries','answers','rewards','audit'] as $n){
            $row=$wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name=%s',self::table($n)));
            if(!$row||strtolower($row->Engine)!=='innodb')$ok=false;
        }
        if($ok){update_option('rgfs_schema',self::SCHEMA,false);Frontend::rewrite();flush_rewrite_rules();}
        else update_option('rgfs_install_error','Feedback storage is unavailable. InnoDB tables are required.',false);
    }
    public static function ready():bool{return get_option('rgfs_schema')===self::SCHEMA;}
    public static function existing($uid,$form){global $wpdb;$t=self::table('entries');return $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE form_slug=%s AND form_version=%d AND user_id=%d",$form['slug'],$form['version'],$uid),ARRAY_A);}
    public static function orders($uid,$limit=10):array {
        if(!function_exists('wc_get_orders'))return [];
        return array_values(array_filter(wc_get_orders(['customer_id'=>(int)$uid,'limit'=>$limit,'orderby'=>'date','order'=>'DESC','type'=>'shop_order']),fn($o)=>is_object($o)&&method_exists($o,'get_customer_id')&&(int)$o->get_customer_id()===(int)$uid));
    }
    public static function gift_order($order):bool {
        if(!$order||!$order->has_status('processing'))return false;
        // Fulfilment staff can link any owned physical order still processing.
        // Completed/shipped orders are never silently promised an extra parcel.
        foreach($order->get_items() as $item){$product=$item->get_product();if($product&&!$product->is_virtual())return true;}
        return false;
    }
    public static function audit($entry,$action,$detail=''):bool{
        global $wpdb;return false!==$wpdb->insert(self::table('audit'),['entry_id'=>$entry,'actor_id'=>get_current_user_id(),'action'=>$action,'detail'=>$detail,'created_at'=>gmdate('Y-m-d H:i:s')]);
    }
    public static function submit($uid,$form,$answers,$meta){
        global $wpdb;
        if(!self::ready())return new \WP_Error('rgfs_storage','We could not save yet. Please try again later.',['status'=>503]);
        $old=self::existing($uid,$form);if($old)return ['id'=>(int)$old['id'],'already_saved'=>true];
        $orders=self::orders($uid,25);$eligible=null;$selected=null;
        foreach($orders as $o)if(self::gift_order($o)){ $eligible=$o;break; }
        if(!empty($meta['order_id'])){
            $selected=function_exists('wc_get_order')?wc_get_order($meta['order_id']):null;
            if(!$selected||(int)$selected->get_customer_id()!==(int)$uid)return new \WP_Error('rgfs_order','This order does not belong to your account.',['status'=>403]);
        }else{$selected=$eligible?:($orders[0]??null);}
        $is_test=!empty($meta['is_test']);
        // Administrators' smoke submissions are segregated, never inflate reports/gifts.
        if($is_test)$eligible=null;
        $e=self::table('entries');$a=self::table('answers');$r=self::table('rewards');
        $wpdb->query('START TRANSACTION');
        $ok=$wpdb->insert($e,['form_slug'=>$form['slug'],'form_version'=>$form['version'],'schema_hash'=>$form['hash'],'user_id'=>$uid,'order_id'=>$selected?$selected->get_id():0,'order_ids'=>wp_json_encode(array_map(fn($o)=>$o->get_id(),$orders)),'submitted_at'=>gmdate('Y-m-d H:i:s'),'elapsed_seconds'=>$meta['elapsed'],'device'=>$meta['device'],'campaign'=>$meta['campaign'],'payload'=>wp_json_encode($answers),'is_test'=>(int)$is_test]);
        if(false===$ok){$wpdb->query('ROLLBACK');$old=self::existing($uid,$form);return $old?['id'=>(int)$old['id'],'already_saved'=>true]:new \WP_Error('rgfs_save','Your answers were not saved. Please try again.',['status'=>503]);}
        $id=(int)$wpdb->insert_id;
        foreach($answers as $qid=>$opts)foreach($opts as $opt)if(false===$wpdb->insert($a,['entry_id'=>$id,'question_key'=>$qid,'option_key'=>$opt]))$ok=false;
        if(false===$wpdb->insert($r,['entry_id'=>$id,'order_id'=>$eligible?$eligible->get_id():0,'state'=>$is_test?'test':($eligible?'ready':'waiting_order'),'updated_at'=>gmdate('Y-m-d H:i:s')]))$ok=false;
        if(!self::audit($id,'submitted',$is_test?'Staff staging test':'Customer submission'))$ok=false;
        if(!$ok){$wpdb->query('ROLLBACK');return new \WP_Error('rgfs_save','Your answers were not saved. Please try again.',['status'=>503]);}
        if(false===$wpdb->query('COMMIT'))return new \WP_Error('rgfs_save','Please retry to confirm your answers were saved.',['status'=>503]);
        // Side effects AFTER commit; add-ons must be idempotent on entry ID.
        do_action('rgfs_response_saved',$id,$form['slug'],$uid);
        return ['id'=>$id,'already_saved'=>false];
    }
    /** Atomic queue allocation: one waiting reward is claimed once. Never change
     * line items, totals, payment state or stock. Admin dashboard is pick/pack source. */
    public static function claim_gift($order_id):void{
        global $wpdb;$order=wc_get_order($order_id);
        if(!self::gift_order($order)||!$order->get_customer_id())return;
        $e=self::table('entries');$r=self::table('rewards');
        $wpdb->query('START TRANSACTION');
        $row=$wpdb->get_row($wpdb->prepare("SELECT r.id,r.entry_id FROM $r r JOIN $e e ON e.id=r.entry_id WHERE e.user_id=%d AND e.is_test=0 AND r.state='waiting_order' AND r.order_id=0 ORDER BY r.id LIMIT 1 FOR UPDATE",$order->get_customer_id()));
        if($row){
            $ok=$wpdb->update($r,['order_id'=>$order_id,'state'=>'ready','updated_at'=>gmdate('Y-m-d H:i:s')],['id'=>$row->id,'state'=>'waiting_order']);
            if($ok!==1||!self::audit($row->entry_id,'gift_linked','Order '.(int)$order_id)){$wpdb->query('ROLLBACK');return;}
        }
        $wpdb->query('COMMIT');
    }
    public static function reward($entry):?array{global $wpdb;$r=self::table('rewards');return $wpdb->get_row($wpdb->prepare("SELECT * FROM $r WHERE entry_id=%d",$entry),ARRAY_A);}
}
