<?php
// Standalone behavior tests; does not connect to WordPress or create orders.
class WP_Error {public $code;function __construct($code,$message){$this->code=$code;}}
function is_wp_error($x){return $x instanceof WP_Error;}
function wc_get_product($id){return $GLOBALS['products'][$id]??false;}
function sanitize_title($x){return strtolower(str_replace(' ','-',$x));}
function wc_clean($x){return trim(strip_tags($x));}
function wp_unslash($x){return stripslashes($x);}
function get_post_field($f,$id){return $GLOBALS['passwords'][$id]??'';}
function home_url($x){return 'https://example.test'.$x;}
function add_query_arg($a,$url){return $url.'?'.http_build_query($a);}
function apply_filters($hook,$value,...$args){return $GLOBALS['validation']??$value;}
function WC(){return $GLOBALS['wc'];}
class WC_Product {
 public $id,$type,$parent=0,$attrs=[],$options=[],$status='publish',$stock=true,$active=true,$visible=true,$purchasable=true,$max=10,$sold=false,$visibility='visible';
 function __construct($id,$type){$this->id=$id;$this->type=$type;}
 function get_id(){return $this->id;} function get_parent_id(){return $this->parent;}
 function get_status(){return $this->status;} function is_type($t){return in_array($this->type,(array)$t,true);}
 function get_variation_attributes(){return $this->type==='variable'?$this->options:$this->attrs;}
 function variation_is_active(){return $this->active;} function variation_is_visible(){return $this->visible;}
 function is_purchasable(){return $this->purchasable;} function is_in_stock(){return $this->stock;}
 function has_enough_stock($n){return $n<=$this->max;}function is_sold_individually(){return $this->sold;}
 function get_catalog_visibility(){return $this->visibility;}
}
class WC_Data_Store {
 static function load($x){return new self;}
 function find_matching_product_variation($p,$attrs){foreach($GLOBALS['products'] as $v){if($v->type==='variation'&&$v->parent===$p->id&&$v->attrs===$attrs)return $v->id;}return 0;}
}
class Cart {
 public $items=[],$calls=0;
 function get_cart(){return $this->items;}
 function add_to_cart($p,$n,$v,$a){$this->calls++;$this->items[]=['product_id'=>$p,'quantity'=>$n,'variation_id'=>$v,'variation'=>$a];return 'key';}
}
class Adapter {public $a=[];function get_custom_attributes(){return $this->a;}function set_custom_attributes($x){$this->a=$x;}function add_custom_attribute($x){$this->a[]=$x;}}
require __DIR__.'/merchant-entry.php';
$checks=0;function ok($v,$why){global $checks;if(!$v)throw new Exception($why);$checks++;}
$s=new WC_Product(938,'simple');$p=new WC_Product(1099,'variable');$p->options=['pa_colour'=>['beige-black','pink-red']];
$v=new WC_Product(1101,'variation');$v->parent=1099;$v->attrs=['attribute_pa_colour'=>'beige-black'];
$GLOBALS['products']=[938=>$s,1099=>$p,1101=>$v];$GLOBALS['wc']=(object)['cart'=>new Cart];
foreach(['gla_1101','1101'] as $id){$r=RGCK_Merchant_Entry::resolve($id);ok(!is_wp_error($r)&&$r['variation_id']===1101&&$r['attributes']===$v->attrs,'exact variation');}
foreach(['gla_1101oops','gla_0','-1','1e3','https://evil.test',[],null,'99999'] as $id)ok(is_wp_error(RGCK_Merchant_Entry::resolve($id)),'bad offer');
ok(is_wp_error(RGCK_Merchant_Entry::resolve('1099')),'variable missing option');
ok(!is_wp_error(RGCK_Merchant_Entry::resolve('1099',$v->attrs)),'selected variable parent');
ok(is_wp_error(RGCK_Merchant_Entry::resolve('1101',['attribute_pa_colour'=>'pink-red'])),'cannot change fixed variation');
ok(is_wp_error(RGCK_Merchant_Entry::resolve('1101',['attribute_pa_colour'=>[]])),'array option rejected');
$p->status='draft';ok(is_wp_error(RGCK_Merchant_Entry::resolve('1101')),'draft parent');$p->status='publish';
$p->visibility='hidden';ok(is_wp_error(RGCK_Merchant_Entry::resolve('1101')),'hidden parent');$p->visibility='visible';
$GLOBALS['passwords'][1099]='secret';ok(is_wp_error(RGCK_Merchant_Entry::resolve('1101')),'password protected');$GLOBALS['passwords']=[];
$v->stock=false;ok(is_wp_error(RGCK_Merchant_Entry::resolve('1101')),'out of stock');$v->stock=true;
$v->active=false;ok(is_wp_error(RGCK_Merchant_Entry::resolve('1101')),'disabled variant');$v->active=true;
$r=RGCK_Merchant_Entry::resolve('gla_1101');
foreach([0,100,1.5,'1',-1] as $q)ok(is_wp_error(RGCK_Merchant_Entry::ensure_item($r,$q)),'invalid quantity');
ok(RGCK_Merchant_Entry::ensure_item($r,1)===true,'first add');ok(RGCK_Merchant_Entry::ensure_item($r,1)===true&&WC()->cart->calls===1,'repeat safe');
ok(RGCK_Merchant_Entry::ensure_item($r,3)===true&&WC()->cart->items[1]['quantity']===2,'only quantity delta');
$before=WC()->cart->items;ok(RGCK_Merchant_Entry::ensure_item($r,1)===true&&WC()->cart->items===$before,'never reduce');
ok(is_wp_error(RGCK_Merchant_Entry::ensure_item($r,11)),'stock cap');
$v->sold=true;ok(is_wp_error(RGCK_Merchant_Entry::ensure_item($r,2)),'sold individually');$v->sold=false;
$GLOBALS['validation']=false;ok(is_wp_error(RGCK_Merchant_Entry::ensure_item(RGCK_Merchant_Entry::resolve('938'),1)),'native custom filter');$GLOBALS['validation']=true;
$original=WC()->cart->items;RGCK_Merchant_Entry::ensure_item(RGCK_Merchant_Entry::resolve('938'),1);ok(array_slice(WC()->cart->items,0,2)===$original,'preserve unrelated lines');
$a=new Adapter;$a->a=[['name'=>'other','value'=>'keep'],['name'=>'checkout_link_template','value'=>'stale']];
$attributes=['title'=>'unchanged'];ok(RGCK_Merchant_Entry::feed($attributes,$v,$a)===$attributes&&count($a->a)===2&&strpos($a->a[1]['value'],'gla_1101')!==false,'supported adapter adds exact link');
$v->stock=false;RGCK_Merchant_Entry::feed($attributes,$v,$a);ok(count($a->a)===1&&$a->a[0]['name']==='other','out of stock removes link only');
$a=new Adapter;RGCK_Merchant_Entry::feed($attributes,$p,$a);ok(!$a->a,'variable parent excluded');
echo "$checks behavior checks passed\n";
