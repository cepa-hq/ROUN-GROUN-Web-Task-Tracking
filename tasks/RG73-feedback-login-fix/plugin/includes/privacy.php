<?php
namespace RGFS;
defined('ABSPATH') || exit;

/** WP verified privacy-request workflow owns consent/confirmation. No arbitrary
 * customer IDs or email addresses are accepted by public survey endpoints. */
final class Privacy {
    public static function boot():void{
        add_filter('wp_privacy_personal_data_exporters',function($v){$v['rgfs']=['exporter_friendly_name'=>'Roun Groun Feedback system','callback'=>[self::class,'export']];return $v;});
        add_filter('wp_privacy_personal_data_erasers',function($v){$v['rgfs']=['eraser_friendly_name'=>'Roun Groun Feedback system','callback'=>[self::class,'erase']];return $v;});
        add_action('admin_init',function(){if(function_exists('wp_add_privacy_policy_content'))wp_add_privacy_policy_content('Roun Groun Feedback system','<p>When you complete a feedback form, we save your choices, submission time, broad device type, campaign tag, account ID and linked order IDs. We use this to improve the website and track your surprise delivery gift. Feedback is held in our website database, visible to authorised staff. No feedback collection service receives these answers. Contact us through our Contact Us page for access or erasure requests.</p>');});
    }
    public static function export($email,$page=1):array {
        global $wpdb;$u=get_user_by('email',$email);if(!$u)return ['data'=>[],'done'=>true];$t=Store::table('entries');$offset=(max(1,(int)$page)-1)*50;
        $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE user_id=%d ORDER BY id LIMIT 50 OFFSET %d",$u->ID,$offset),ARRAY_A);$out=[];
        foreach($rows as $row){$data=[];foreach(['form_slug','form_version','submitted_at','order_id','device','campaign','payload'] as $key)$data[]=['name'=>$key,'value'=>$row[$key]];$gift=Store::reward($row['id']);if($gift)$data[]=['name'=>'Gift status','value'=>$gift['state']];$out[]=['group_id'=>'rgfs','group_label'=>'Roun Groun feedback','item_id'=>'rgfs-'.$row['id'],'data'=>$data];}
        return ['data'=>$out,'done'=>count($rows)<50];
    }
    public static function erase($email,$page=1):array {
        global $wpdb;$u=get_user_by('email',$email);$base=['items_removed'=>false,'items_retained'=>false,'messages'=>[],'done'=>true];if(!$u)return $base;
        $t=Store::table('entries');$r=Store::table('rewards');$a=Store::table('audit');
        $ids=$wpdb->get_col($wpdb->prepare("SELECT id FROM $t WHERE user_id=%d LIMIT 50",$u->ID));if(!$ids)return $base;
        $wpdb->query('START TRANSACTION');$ok=true;
        foreach($ids as $id){
            // Leave only anonymous answer aggregates. NULL permits multiple erased
            // users without unique-key collisions. Gift/customer links are removed.
            if(false===$wpdb->update($t,['user_id'=>null,'order_id'=>0,'order_ids'=>'[]','campaign'=>'','device'=>'unknown','elapsed_seconds'=>0],['id'=>$id]))$ok=false;
            if(false===$wpdb->update($r,['order_id'=>0,'state'=>'privacy_erased','updated_at'=>gmdate('Y-m-d H:i:s')],['entry_id'=>$id]))$ok=false;
            if(false===$wpdb->delete($a,['entry_id'=>$id]))$ok=false;
        }
        if(false===$wpdb->update($a,['actor_id'=>0],['actor_id'=>$u->ID]))$ok=false;
        if(!$ok){$wpdb->query('ROLLBACK');return ['items_removed'=>false,'items_retained'=>true,'messages'=>['Feedback erasure could not complete. Please retry.'],'done'=>true];}
        $wpdb->query('COMMIT');return ['items_removed'=>true,'items_retained'=>false,'messages'=>['Account and order links removed; anonymous answer totals retained.'],'done'=>count($ids)<50];
    }
}
