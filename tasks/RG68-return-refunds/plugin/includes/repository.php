<?php
defined('ABSPATH') || exit;
/** RMA compatibility repository. Records a REQUEST, never cancels/refunds/restocks an order. */
final class RGR_Repository {
    private static $sandbox=false;private static $fail_item=false;
    private static function table($map=false){global $wpdb;return $wpdb->prefix.(self::$sandbox?($map?'rgr_qa_map':'rgr_qa_requests'):($map?'wcrw_request_product_map':'wcrw_warranty_requests'));}
    public static function reserved($order_id){
        global $wpdb;$map=self::table(true);$table=self::table();$rows=$wpdb->get_results($wpdb->prepare("SELECT m.item_id,m.quantity,r.status FROM {$map} m JOIN {$table} r ON r.id=m.request_id WHERE r.order_id=%d AND r.status<>'rejected'",$order_id),ARRAY_A);$out=array();
        foreach($rows?:array()as $row){$id=(int)$row['item_id'];if(!isset($out[$id]))$out[$id]=array('active'=>0,'closed'=>0);$out[$id][$row['status']==='completed'?'closed':'active']+=(int)$row['quantity'];}return $out;
    }
    public static function replay($order_id,$user,$token){
        global $wpdb;$table=self::table();return (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE order_id=%d AND customer_id=%d AND meta LIKE %s LIMIT 1",$order_id,$user,'%'.$wpdb->esc_like(hash('sha256',$token)).'%'));
    }
    public static function commit($order,$mode,$selection,$reason,$token,$type,$refund=null){
        global $wpdb;$id=$order->get_id();$user=get_current_user_id();$lock='rgr:'.get_current_blog_id().':'.$id;
        if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)',$lock))!==1)return new WP_Error('busy','Another request is being checked. Please try again shortly.');
        $started=false;$request_id=0;
        try{
            $previous=self::replay($id,$user,$token);if($previous)return $previous;
            $fresh=wc_get_order($id);if(!$fresh)return new WP_Error('order','Order unavailable.');
            $ctx=RGR_Policy::context($fresh,$mode,self::reserved($id));$valid=RGR_Policy::validate($ctx,$user,$selection,$reason,true);if(is_wp_error($valid))return $valid;
            $allowed=RGR_Centre::types($mode);if(!isset($allowed[$type]))return new WP_Error('type','This request type is no longer available.');
            $table=self::table();$map=self::table(true);
            // Fail closed without transactional storage: never create a header
            // without its selected item rows. Table names are trusted WP prefix.
            foreach(self::$sandbox?array():array($table,$map,$wpdb->options)as $t){$info=$wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS LIKE %s',$wpdb->esc_like($t)),ARRAY_A);if(!$info||strtolower($info['Engine']??'')!=='innodb')return new WP_Error('storage','Request storage needs verification. Please contact customer care.');}
            if($wpdb->query('START TRANSACTION')===false)throw new RuntimeException('transaction');$started=true;
            $meta=array(array('key'=>'request_reasons','label'=>'Reason for request','value'=>$reason),array('key'=>'rgr_submission','label'=>'Request reference','value'=>hash('sha256',$token)),array('key'=>'rgr_validation','label'=>'Review','value'=>'Confirmed selections; server eligibility rechecked before submission.'));
            $ok=$wpdb->insert($table,array('order_id'=>$id,'customer_id'=>$user,'type'=>$type,'status'=>'new','reasons'=>$reason,'meta'=>maybe_serialize($meta),'created_at'=>current_time('mysql')),array('%d','%d','%s','%s','%s','%s','%s'));
            if(!$ok)throw new RuntimeException('header');$request_id=(int)$wpdb->insert_id;
            foreach($valid as $item_id=>$qty){if(self::$fail_item)throw new RuntimeException('injected rollback test');$item=$fresh->get_item($item_id);if(!$wpdb->insert($map,array('request_id'=>$request_id,'product_id'=>$item->get_product_id(),'item_id'=>$item_id,'quantity'=>$qty),array('%d','%d','%d','%d')))throw new RuntimeException('item');}
            if($refund)RGR_Refund::create_for_request(['id'=>$request_id,'order_id'=>$id,'customer_id'=>$user],$refund);
            if($wpdb->query('COMMIT')===false)throw new RuntimeException('commit');$started=false;
        }catch(Throwable $e){if($started)$wpdb->query('ROLLBACK');return new WP_Error('storage','Your request was not saved. Please try again or contact customer care.');}
        finally{$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
        if(self::$sandbox)return $request_id;
        // Only AFTER durable commit: native RMA notifications/admin workflow.
        // A mail failure must never cause the browser to create a duplicate request.
        $data=array('order_id'=>$id,'customer_id'=>$user,'type'=>$type,'status'=>'new','reasons'=>$reason,'request_reasons'=>$reason,'meta'=>$meta,'items'=>array());
        foreach($selection as $item_id=>$qty){$item=$order->get_item($item_id);$data['items'][]=array('product_id'=>$item->get_product_id(),'item_id'=>$item_id,'quantity'=>$qty);}
        try{if($mode==='cancel')do_action('wcrw_created_cancel_order_request',$request_id,$data);else{do_action('wcrw_created_warranty_request',$request_id,$data,$data);do_action('wcrw_after_warranty_request_create',$data,true);}}catch(Throwable $e){wc_get_logger()->error('Request saved; notification hook failed.',array('source'=>'rgr-request-centre'));}
        return $request_id;
    }
    public static function sandbox_checks(){
        if(!is_admin()||!current_user_can('manage_woocommerce'))return array();
        global $wpdb;$orders=wc_get_orders(array('customer_id'=>get_current_user_id(),'status'=>'on-hold','limit'=>1));
        if(!$orders)return array(array('name'=>'Transactional sandbox','pass'=>false,'result'=>'No owned on-hold sample order available'));
        $o=$orders[0];$before=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wcrw_warranty_requests");$out=array();self::$sandbox=true;
        try{
            // Verify the REAL storage precondition too; temporary InnoDB clones
            // alone would not prove that production requests can commit safely.
            foreach(array('wcrw_warranty_requests','wcrw_request_product_map')as $suffix){$real=$wpdb->prefix.$suffix;$info=$wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS LIKE %s',$wpdb->esc_like($real)),ARRAY_A);$engine=$info['Engine']??'unavailable';$out[]=array('name'=>'Real RMA transactional storage: '.$suffix,'pass'=>strtolower($engine)==='innodb','result'=>$engine);}
            $table=self::table();$map=self::table(true);
            if($wpdb->query("CREATE TEMPORARY TABLE {$table} LIKE {$wpdb->prefix}wcrw_warranty_requests")===false||$wpdb->query("CREATE TEMPORARY TABLE {$map} LIKE {$wpdb->prefix}wcrw_request_product_map")===false)throw new RuntimeException('Temporary table permission unavailable');
            if($wpdb->query("ALTER TABLE {$table} ENGINE=InnoDB")===false||$wpdb->query("ALTER TABLE {$map} ENGINE=InnoDB")===false)throw new RuntimeException('Transactional sandbox unavailable');
            $ctx=RGR_Policy::context($o,'cancel');$rows=array_filter($ctx['items'],function($r){return $r['code']==='eligible';});$first=reset($rows);if(!$first||$ctx['blocked'])throw new RuntimeException('Sample order is not eligible');
            $selection=array($first['id']=>1);$token=wp_generate_uuid4();$a=self::commit($o,'cancel',$selection,'Isolated temporary-table test',$token,'cancel');$b=self::commit($o,'cancel',$selection,'Isolated temporary-table test',$token,'cancel');
            $count=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$table}");$maps=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$map}");
            $out[]=array('name'=>'Actual SQL commit + selected item mapping','pass'=>!is_wp_error($a)&&$a>0&&$count===1&&$maps===1,'result'=>'TEMPORARY tables only');
            $out[]=array('name'=>'Retry idempotency','pass'=>$a===$b&&$count===1,'result'=>'Same request ID, one row');
            $wpdb->query("DELETE FROM {$map}");$wpdb->query("DELETE FROM {$table}");self::$fail_item=true;$failed=self::commit($o,'cancel',$selection,'Isolated rollback failure test',wp_generate_uuid4(),'cancel');self::$fail_item=false;
            $out[]=array('name'=>'Atomic rollback on item-write failure','pass'=>is_wp_error($failed)&&(int)$wpdb->get_var("SELECT COUNT(*) FROM {$table}")===0,'result'=>'No orphan header');
            $returns=wc_get_orders(array('customer_id'=>get_current_user_id(),'status'=>'completed','limit'=>10));$return_ok=false;
            foreach($returns as $completed){$rc=RGR_Policy::context($completed,'return');if($rc['blocked'])continue;foreach($rc['items']as $line)if($line['code']==='eligible'){$rid=self::commit($completed,'return',array($line['id']=>1),'Isolated return workflow test',wp_generate_uuid4(),'refund');$return_ok=!is_wp_error($rid)&&$rid>0&&(int)$wpdb->get_var("SELECT COUNT(*) FROM {$map}")===1;break 2;}}
            $out[]=array('name'=>'Actual return SQL commit + selected mapping','pass'=>$return_ok,'result'=>$return_ok?'TEMPORARY tables; notifications disabled':'No eligible owned completed sample or return commit failed');
        }catch(Throwable $e){$out[]=array('name'=>'Transactional sandbox','pass'=>false,'result'=>$e->getMessage());}
        finally{self::$fail_item=false;foreach(array(self::table(),self::table(true))as $t)$wpdb->query("DROP TEMPORARY TABLE IF EXISTS {$t}");self::$sandbox=false;}
        $after=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wcrw_warranty_requests");$out[]=array('name'=>'Real RMA queue unchanged','pass'=>$before===$after,'result'=>'No real request, order change or notification');return $out;
    }

}
