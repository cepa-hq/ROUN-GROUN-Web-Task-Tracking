<?php
namespace RGFS;
defined('ABSPATH') || exit;

/** Immutable instrument registry. Add a new slug/version, NEVER change existing
 * option meaning or order: submissions contain stable qid/oN keys and a schema hash.
 * Registry filters are for code-owned future instruments, not customer input.
 */
final class Forms {
    public static function all(): array {
        $qs=json_decode(file_get_contents(ROOT.'/includes/general-mk1.json'),true);
        foreach ($qs as &$q) {
            $q['keys']=array_map(fn($i)=>'o'.$i,array_keys($q['options']));
            $q['required']=true;
        } unset($q);
        $qs[]=['id'=>'checkout_part','title'=>'What got in the way?','help'=>'Optional checkout follow-up','multi'=>true,'required'=>false,'options'=>['Address','Delivery cost','Coupon','Payment','Something else'],'keys'=>['o0','o1','o2','o3','o4'],'condition'=>['checkout',['o2','o3']]];
        $form=['slug'=>'general-feedback-mk1','title'=>'General feedback mk1','version'=>1,'questions'=>$qs];
        $form['hash']=hash('sha256',wp_json_encode($form));
        return apply_filters('rgfs_forms',['general-feedback-mk1'=>$form]);
    }
    public static function get(string $slug): ?array { return self::all()[$slug]??null; }
    public static function snapshot():void{
        $saved=get_option('rgfs_instruments',[]);$changed=false;
        foreach(self::all() as $f){$key=$f['slug'].':'.$f['version'];if(!isset($saved[$key])){$saved[$key]=$f;$changed=true;}}
        if($changed)update_option('rgfs_instruments',$saved,false);
    }
    public static function historical(string $slug,int $version):?array{
        $saved=get_option('rgfs_instruments',[]);return $saved[$slug.':'.$version]??null;
    }
    public static function client(array $form): array {
        $form['questions']=array_values(array_filter($form['questions'],fn($q)=>$q['id']!=='checkout_part'));
        return $form;
    }
    /** Reject coercions, unknown keys, duplicate options, hidden followups and
     * exclusive+other choices. Client validation improves UX; this is authority. */
    public static function validate(array $form,$input) {
        if (!is_array($input)) return new \WP_Error('rgfs_answers','Choose answers for each question.',['status'=>422]);
        $known=array_column($form['questions'],null,'id'); $out=[];
        if (array_diff(array_keys($input),array_keys($known))) return new \WP_Error('rgfs_unknown','An answer was not recognised. Please reload.',['status'=>422]);
        foreach($known as $id=>$q) {
            $v=$input[$id]??[];
            if(!is_array($v)||count($v)>count($q['options'])) return self::invalid($id);
            foreach($v as $o) if(!is_string($o)||!in_array($o,$q['keys'],true)) return self::invalid($id);
            if(count(array_unique($v))!==count($v))return self::invalid($id);
            if(!empty($q['required'])&&!count($v))return self::invalid($id);
            if(empty($q['multi'])&&count($v)>1)return self::invalid($id);
            if(isset($q['max'])&&count($v)>$q['max'])return self::invalid($id);
            if(isset($q['exclusive'])){
                $key='o'.array_search($q['exclusive'],$q['options'],true);
                if(in_array($key,$v,true)&&count($v)>1)return self::invalid($id);
            }
            if(isset($q['condition'])&&count($v)&&!array_intersect($input[$q['condition'][0]]??[],$q['condition'][1])) return self::invalid($id);
            sort($v);$out[$id]=$v;
        }
        return $out;
    }
    private static function invalid($id){return new \WP_Error('rgfs_invalid','Check your choices and try again.',['status'=>422,'question'=>$id]);}
}
