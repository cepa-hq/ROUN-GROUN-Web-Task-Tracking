<?php
// Fault injection into actual Store::submit. No WordPress/customer DB is used.
define('ABSPATH',__DIR__);define('ARRAY_A','ARRAY_A');
function get_option($key){return '1';}function wp_json_encode($v){return json_encode($v);}function is_wp_error($v){return $v instanceof WP_Error;}function get_current_user_id(){return 7;}function do_action(...$a){}
class WP_Error{public function __construct(public $code,$message,$data=[]){} }
final class FakeDB{
 public $prefix='wp_',$insert_id=0,$fail=0,$calls=0,$rows=[],$snapshot=[],$statements=[];
 function prepare($sql,...$values){return $sql;}
 function get_row($sql,$format=null){return $this->rows['wp_rgfs_entries'][0]??null;}
 function query($sql){$this->statements[]=$sql;if($sql==='START TRANSACTION')$this->snapshot=$this->rows;if($sql==='ROLLBACK')$this->rows=$this->snapshot;return 0;}
 function insert($table,$row){$this->calls++;if($this->calls===$this->fail)return false;$row['id']=++$this->insert_id;$this->rows[$table][]=$row;return 1;}
}
require dirname(__DIR__).'/includes/store.php';
$valid=['feel'=>['o4'],'source'=>['o0']];$form=['slug'=>'general-feedback-mk1','version'=>1,'hash'=>str_repeat('a',64)];$meta=['elapsed'=>10,'device'=>'phone','campaign'=>'test','is_test'=>true,'order_id'=>0];
function expect($ok,$label){if(!$ok)throw new Exception('FAIL '.$label);echo 'PASS '.$label."\n";}
// Entry + 2 answer inserts + reward + audit =5 writes; fail each one in turn.
foreach([1,2,3,4,5] as $fail){$wpdb=new FakeDB;$wpdb->fail=$fail;$r=RGFS\Store::submit(7,$form,$valid,$meta);expect($r instanceof WP_Error,'failure '.$fail.' returned error');expect(count($wpdb->rows)===0,'failure '.$fail.' left no partial response/reward');expect(in_array('ROLLBACK',$wpdb->statements,true),'failure '.$fail.' rolled back');}
$wpdb=new FakeDB;$r=RGFS\Store::submit(7,$form,$valid,$meta);expect(!($r instanceof WP_Error)&&$r['id']===1,'successful transaction returns durable entry ID');expect(count($wpdb->rows['wp_rgfs_rewards'])===1,'exactly one reward row');$calls=$wpdb->calls;$again=RGFS\Store::submit(7,$form,$valid,$meta);expect($again['id']===1&&$again['already_saved']===true,'retry recovers same response');expect($wpdb->calls===$calls,'retry inserts no response/answers/reward');
echo "19 transaction checks passed\n";
