<?php
// Run with PHP CLI. Exercises actual production validator/statistical code.
define('ABSPATH',__DIR__);define('RGFS\\ROOT',dirname(__DIR__));
function wp_json_encode($v){return json_encode($v);}function apply_filters($n,$v){return $v;}
class WP_Error{public $code;public function __construct($c,$m,$d=[]){$this->code=$c;}}
require dirname(__DIR__).'/includes/forms.php';require dirname(__DIR__).'/includes/analytics.php';
$tests=0;function check($ok,$label){global $tests;$tests++;if(!$ok)throw new Exception('FAIL '.$label);echo 'PASS '.$label."\n";}
$f=RGFS\Forms::get('general-feedback-mk1');
$valid=['feel'=>['o4'],'source'=>['o0'],'find'=>['o0'],'clear'=>['o5'],'checkout'=>['o1'],'choose'=>['o2'],'next'=>['o0'],'friend'=>['o0'],'checkout_part'=>[]];
check(is_array(RGFS\Forms::validate($f,$valid)),'complete valid response');
$bad=$valid;unset($bad['feel']);check(RGFS\Forms::validate($f,$bad) instanceof WP_Error,'missing required rejected');
$bad=$valid;$bad['feel']=['o9'];check(RGFS\Forms::validate($f,$bad) instanceof WP_Error,'unknown option rejected');
$bad=$valid;$bad['feel']=['o4','o4'];check(RGFS\Forms::validate($f,$bad) instanceof WP_Error,'duplicate option rejected');
$bad=$valid;$bad['feel']=['o4','o3'];check(RGFS\Forms::validate($f,$bad) instanceof WP_Error,'multiple radio choices rejected');
$bad=$valid;$bad['clear']=['o0','o5'];check(RGFS\Forms::validate($f,$bad) instanceof WP_Error,'exclusive answer rejected with another');
$bad=$valid;$bad['choose']=['o0','o1','o2'];check(RGFS\Forms::validate($f,$bad) instanceof WP_Error,'selection maximum enforced');
$bad=$valid;$bad['checkout_part']=['o0'];check(RGFS\Forms::validate($f,$bad) instanceof WP_Error,'hidden checkout branch rejected');
$bad['checkout']=['o2'];check(is_array(RGFS\Forms::validate($f,$bad)),'visible optional checkout branch accepted');
$bad=$valid;$bad['user_id']=['o0'];check(RGFS\Forms::validate($f,$bad) instanceof WP_Error,'unknown answer field rejected');
$bad=$valid;$bad['friend']=[0];check(RGFS\Forms::validate($f,$bad) instanceof WP_Error,'numeric coercion rejected');
check(RGFS\Analytics::wilson(0,0)===null,'zero-denominator no invented percentage');
check(RGFS\Analytics::wilson(5,10)===[23.7,76.3],'Wilson reference 5 of10');
check(RGFS\Analytics::wilson(0,10)===[0.0,27.8],'Wilson zero positive boundary');
check(RGFS\Analytics::wilson(10,10)===[72.2,100.0],'Wilson all positive boundary');
foreach([1,2,10,100,10000] as $n)foreach([0,(int)($n/2),$n] as $yes){$r=RGFS\Analytics::wilson($yes,$n);check($r[0]>=0&&$r[1]<=100&&$r[0]<=$yes/$n*100+.1&&$r[1]>=$yes/$n*100-.1,'Wilson bounds n'.$n.' yes'.$yes);}
echo "$tests checks passed\n";
