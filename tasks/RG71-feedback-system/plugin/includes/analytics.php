<?php
namespace RGFS;
defined('ABSPATH') || exit;

/** Reporting uses exact counts, not sampled JSON scans. Every chart and entry
 * list uses the SAME WHERE clause. SQL values are prepared; identifiers are owned.
 * Multi-select shares use unique respondents per question, never total selections.
 */
final class Analytics {
    public static function wilson(int $yes,int $n):?array{
        if($n<=0)return null;$yes=max(0,min($yes,$n));$p=$yes/$n;$z=1.959963984540054;$z2=$z*$z;
        $den=1+$z2/$n;$mid=($p+$z2/(2*$n))/$den;
        $half=$z*sqrt($p*(1-$p)/$n+$z2/(4*$n*$n))/$den;
        return [round(max(0,$mid-$half)*100,1),round(min(1,$mid+$half)*100,1)];
    }
    public static function filter($req,$form):array {
        $get=fn($k)=>(string)($req->get_param($k)??'');
        $f=['from'=>$get('from'),'to'=>$get('to'),'device'=>$get('device'),'campaign'=>substr(sanitize_key($get('campaign')),0,64),'search'=>substr(sanitize_text_field($get('search')),0,100),'question'=>$get('question'),'option'=>$get('option'),'gift'=>$get('gift'),'data'=>$get('data')==='tests'?'tests':'customers','page'=>max(1,min(100000,absint($get('page'))?:1))];
        foreach(['from','to'] as $key){$date=\DateTimeImmutable::createFromFormat('!Y-m-d',$f[$key],wp_timezone());if(!$date||$date->format('Y-m-d')!==$f[$key])$f[$key]='';}
        if(!in_array($f['device'],['phone','tablet','desktop','unknown'],true))$f['device']='';
        if(!in_array($f['gift'],['waiting_order','ready','packed','test','privacy_erased'],true))$f['gift']='';
        $questions=array_column($form['questions'],null,'id');
        if(!isset($questions[$f['question']])||!in_array($f['option'],$questions[$f['question']]['keys'],true)){$f['question']='';$f['option']='';}
        return $f;
    }
    public static function where($form,$f):string {
        global $wpdb;$s=$wpdb->prepare('e.form_slug=%s AND e.form_version=%d AND e.is_test=%d',$form['slug'],$form['version'],$f['data']==='tests'?1:0);
        if($f['from']){$d=new \DateTimeImmutable($f['from'],wp_timezone());$s.=$wpdb->prepare(' AND e.submitted_at >= %s',$d->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'));}
        if($f['to']){$d=(new \DateTimeImmutable($f['to'],wp_timezone()))->modify('+1 day');$s.=$wpdb->prepare(' AND e.submitted_at < %s',$d->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'));}
        if($f['device'])$s.=$wpdb->prepare(' AND e.device=%s',$f['device']);
        if($f['campaign'])$s.=$wpdb->prepare(' AND e.campaign=%s',$f['campaign']);
        if($f['search']){$like='%'.$wpdb->esc_like($f['search']).'%';$s.=$wpdb->prepare(" AND (e.user_id=%d OR e.order_id=%d OR EXISTS (SELECT 1 FROM {$wpdb->users} u WHERE u.ID=e.user_id AND (u.display_name LIKE %s OR u.user_login LIKE %s OR u.user_email LIKE %s)))",absint($f['search']),absint($f['search']),$like,$like,$like);}
        if($f['question']){$a=Store::table('answers');$s.=$wpdb->prepare(" AND EXISTS (SELECT 1 FROM $a af WHERE af.entry_id=e.id AND af.question_key=%s AND af.option_key=%s)",$f['question'],$f['option']);}
        if($f['gift']){$r=Store::table('rewards');$s.=$wpdb->prepare(" AND EXISTS (SELECT 1 FROM $r rf WHERE rf.entry_id=e.id AND rf.state=%s)",$f['gift']);}
        return $s;
    }
    public static function report($req,$form):array {
        global $wpdb;$f=self::filter($req,$form);$where=self::where($form,$f);$e=Store::table('entries');$a=Store::table('answers');$r=Store::table('rewards');
        $summary=$wpdb->get_row("SELECT COUNT(*) total,COUNT(DISTINCT e.user_id) customers,AVG(e.elapsed_seconds) avg_seconds FROM $e e WHERE $where",ARRAY_A);
        $counts=$wpdb->get_results("SELECT a.question_key,a.option_key,COUNT(*) n FROM $a a JOIN $e e ON e.id=a.entry_id WHERE $where GROUP BY a.question_key,a.option_key",ARRAY_A);
        $denoms=$wpdb->get_results("SELECT a.question_key,COUNT(DISTINCT a.entry_id) n FROM $a a JOIN $e e ON e.id=a.entry_id WHERE $where GROUP BY a.question_key",ARRAY_A);
        $dist=[];$ns=[];foreach($counts as $c)$dist[$c['question_key']][$c['option_key']]=(int)$c['n'];foreach($denoms as $d)$ns[$d['question_key']]=(int)$d['n'];
        $questions=[];
        foreach($form['questions'] as $q){
            $n=$ns[$q['id']]??0;$opts=[];
            foreach($q['options'] as $i=>$label){$key=$q['keys'][$i];$count=$dist[$q['id']][$key]??0;$opts[]=['key'=>$key,'label'=>$label,'count'=>$count,'percent'=>$n?round($count/$n*100,1):null,'interval'=>self::wilson($count,$n)];}
            $metric=null;$map=[];$positive=[];
            if($q['id']==='feel'){$map=['o0'=>1,'o1'=>2,'o2'=>3,'o3'=>4,'o4'=>5];$positive=['o3','o4'];}
            if($q['id']==='find'){$map=['o0'=>4,'o1'=>3,'o2'=>2,'o3'=>1];$positive=['o0','o1'];}
            if($q['id']==='checkout'){$map=['o0'=>4,'o1'=>3,'o2'=>2,'o3'=>1];$positive=['o0','o1'];}
            if($map){$rated=0;$sum=0;$yes=0;foreach($map as $k=>$score){$c=$dist[$q['id']][$k]??0;$rated+=$c;$sum+=$c*$score;if(in_array($k,$positive,true))$yes+=$c;}$metric=['average'=>$rated?round($sum/$rated,2):null,'scale'=>max($map),'rated'=>$rated,'excluded'=>$n-$rated,'positive'=>$rated?round($yes/$rated*100,1):null,'interval'=>self::wilson($yes,$rated)];}
            $questions[]=['id'=>$q['id'],'title'=>$q['title'],'multi'=>!empty($q['multi']),'optional'=>empty($q['required']),'respondents'=>$n,'options'=>$opts,'metric'=>$metric];
        }
        // Daily activity is UTC, explicitly labelled. Limit to latest60 active dates.
        $trend=$wpdb->get_results("SELECT DATE(e.submitted_at) day,COUNT(*) count FROM $e e WHERE $where GROUP BY DATE(e.submitted_at) ORDER BY day DESC LIMIT 60",ARRAY_A);
        $gifts=$wpdb->get_results("SELECT r.state,COUNT(*) count FROM $r r JOIN $e e ON e.id=r.entry_id WHERE $where GROUP BY r.state",ARRAY_A);
        $per=20;$offset=($f['page']-1)*$per;
        $rows=$wpdb->get_results($wpdb->prepare("SELECT e.*,r.state gift_state,r.order_id gift_order_id FROM $e e LEFT JOIN $r r ON r.entry_id=e.id WHERE $where ORDER BY e.id DESC LIMIT %d OFFSET %d",$per,$offset),ARRAY_A);
        $entries=array_map([self::class,'entry'],$rows);
        return ['summary'=>['total'=>(int)$summary['total'],'customers'=>(int)$summary['customers'],'avg_seconds'=>round((float)$summary['avg_seconds']),'small_sample'=>(int)$summary['total']<30],'questions'=>$questions,'trend'=>array_reverse($trend),'gifts'=>$gifts,'entries'=>$entries,'filters'=>$f,'pages'=>max(1,(int)ceil((int)$summary['total']/$per)),'form'=>['slug'=>$form['slug'],'title'=>$form['title'],'version'=>$form['version']]];
    }
    public static function entry($row):array {
        $uid=(int)$row['user_id'];$u=$uid?get_userdata($uid):null;
        $order=function_exists('wc_get_order')&&$row['order_id']?wc_get_order($row['order_id']):null;
        // A reassigned/deleted order must not leak a different customer's context.
        if($order&&(int)$order->get_customer_id()!==$uid)$order=null;
        return ['id'=>(int)$row['id'],'user_id'=>$uid,'name'=>$u?$u->display_name:'Deleted account','login'=>$u?$u->user_login:'','account_url'=>$u?admin_url('user-edit.php?user_id='.$uid):'','orders_url'=>$u?admin_url('admin.php?page=wc-orders&customer_id='.$uid):'','order'=>$order?['id'=>$order->get_id(),'number'=>$order->get_order_number(),'status'=>wc_get_order_status_name($order->get_status()),'url'=>$order->get_edit_order_url()]:null,'gift_state'=>$row['gift_state']??'','gift_order_id'=>(int)($row['gift_order_id']??0),'date'=>get_date_from_gmt($row['submitted_at'],'M j, Y H:i'),'device'=>$row['device'],'campaign'=>$row['campaign'],'is_test'=>(bool)$row['is_test'],'answers'=>json_decode($row['payload'],true),'elapsed'=>(int)$row['elapsed_seconds']];
    }
}
