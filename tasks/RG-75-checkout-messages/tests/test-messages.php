<?php
// Standalone contract checks against the real, unmodified staging COD guard.
define('ABSPATH', __DIR__); define('WC_DOING_AJAX', true);
$options = array(); $checkout = true; $endpoint = ''; $enabled = true; $notices = array(); $removed = array();
function add_action(...$args){} function add_filter(...$args){}
function alg_wc_cpg(){} function absint($v){return abs((int)$v);}
function get_option($key,$default=null){global $options;return $options[$key]??$default;}
function is_checkout(){global $checkout;return $checkout;}
function is_wc_endpoint_url($key){global $endpoint;return $endpoint===$key;}
function is_order_received_page(){global $endpoint;return $endpoint==='order-received';}
function wp_strip_all_tags($s){return strip_tags($s);}
function esc_html($s){return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}
function wc_get_notices(){global $notices;return $notices;}
function wc_set_notices($n){global $notices;$notices=$n;}
function remove_action(...$a){global $removed;$removed[]=$a;}
class RGCK_Settings{static function get($k){global $enabled;return $enabled;}}
class Session{public $data=array();function get($k,$d=null){return $this->data[$k]??$d;}function set($k,$v){$this->data[$k]=$v;}function __unset($k){unset($this->data[$k]);}}
class Cart{public $items=array(); public $pay=true;function get_cart(){return $this->items;}function needs_payment(){return $this->pay;}}
class Product{private $name;function __construct($n){$this->name=$n;}function get_name(){return $this->name;}}
$wc=(object)array('session'=>new Session,'cart'=>new Cart);
function WC(){global $wc;return $wc;}
require __DIR__.'/live-commerce-guards.php';
require __DIR__.'/../roun-groun-checkout-messages/roun-groun-checkout-messages.php';
$checks=0;function check($ok,$why){global $checks;$checks++;if(!$ok)throw new Exception($why);}
function item($id,$vid,$name){return array('product_id'=>$id,'variation_id'=>$vid,'data'=>new Product($name));}
$a=item(10,11,'Band <script>bad</script> & kit');$b=item(20,21,'Gloves');$wc->cart->items=array($a,$b);
$html='<div id="payment" class="woocommerce-checkout-payment"><ul class="payment_methods"><li id="payment_method_razorpay">Online</li></ul><div class="form-row place-order"><input name="nonce"><button>Order</button></div></div>';
$options=array('alg_wc_cpg_product_excl_enabled'=>'yes','alg_wc_cpg_product_excl'=>array('cod'=>array(10)));
check(RGCM_Messages::restricted_names(array($a,$b))===array('Band bad & kit'),'Parent exclusion names only offending item');
$out=RGCM_Messages::payment_html($html);
check(strpos($out,'Band bad &amp; kit')!==false && strpos($out,'<script>')===false,'Escape product names');
check(strpos($out,'are not available for COD transaction.')!==false,'Requested copy');
check(strpos($out,'</ul>')<strpos($out,'rgcm-cod-note') && strpos($out,'rgcm-cod-note')<strpos($out,'class="form-row place-order"'),'Position immediately after payment choices');
check(strpos($out,'name="nonce"')!==false && strpos($out,'<button>Order</button>')!==false,'Native security and submit retained');
check(RGCM_Messages::payment_html($out)===$out,'No duplicate insertion');
$options['alg_wc_cpg_product_excl']['cod']=array(21);
check(RGCM_Messages::restricted_names(array($a,$b))===array('Gloves'),'Variation exclusion');
$options=array('alg_wc_cpg_product_incl_enabled'=>'yes','alg_wc_cpg_product_incl'=>array('cod'=>array(99)));
check(count(RGCM_Messages::restricted_names(array($a,$b)))===2,'Missing inclusion names cart');
$options['alg_wc_cpg_product_incl']['cod']=array(10);
check(RGCM_Messages::restricted_names(array($a,$b))===array(),'One included item satisfies whole-cart semantics');
$options['alg_wc_cpg_product_excl_enabled']='yes';$options['alg_wc_cpg_product_excl']=array('cod'=>array(21));
check(RGCM_Messages::restricted_names(array($a,$b))===array('Gloves'),'Inclusion plus exclusion');
check(RGCM_Messages::restricted_names(array($b,$b))===array('Gloves'),'Deduplicate');
check(RGCM_Messages::payment_html(str_replace('payment_method_razorpay','payment_method_cod',$html))===str_replace('payment_method_razorpay','payment_method_cod',$html),'Respect visible COD');
$options['alg_wc_cpg_plugin_enabled']='no';check(RGCM_Messages::payment_html($html)===$html,'Disabled rules no message');unset($options['alg_wc_cpg_plugin_enabled']);
$wc->cart->pay=false;check(RGCM_Messages::payment_html($html)===$html,'Free orders no message');$wc->cart->pay=true;
$checkout=false;check(RGCM_Messages::payment_html($html)===$html,'Other pages unchanged');
$_GET['wc-ajax']='update_order_review';check(strpos(RGCM_Messages::fragments(array('.woocommerce-checkout-payment'=>$html))['.woocommerce-checkout-payment'],'rgcm-cod-note')!==false,'AJAX updates payment fragment');unset($_GET['wc-ajax']);$checkout=true;
foreach(array('order-pay','order-received') as $endpoint)check(RGCM_Messages::privacy('original','checkout')==='original','Endpoint copy unchanged');$endpoint='';
check(RGCM_Messages::privacy('original','registration')==='original','Registration unchanged');
check(strpos(RGCM_Messages::privacy('original','checkout'),'Razorpay securely')===0,'Scoped honest privacy copy');
$native='<a href="cart">View cart</a> Product added';check(RGCM_Messages::remember_cart_notice($native)===$native,'Cart notice unchanged at source');
$notices=array('success'=>array(array('notice'=>$native),array('notice'=>'Coupon applied')), 'error'=>array(array('notice'=>$native)), 'notice'=>array(array('notice'=>'Login needed')));
RGCM_Messages::prepare();
check(count($notices['success'])===1 && $notices['success'][0]['notice']==='Coupon applied','Only exact native cart success removed');
check(count($notices['error'])===1 && count($notices['notice'])===1,'Errors and other notices retained');
check($wc->session->get(RGCM_Messages::SESSION_KEY)===null,'Hashes consumed');
check($removed[0]===array('woocommerce_before_checkout_form','woocommerce_checkout_coupon_form',10),'Only redundant coupon callback removed');
RGCM_Messages::remember_cart_notice($native);$checkout=false;$notices=array('success'=>array(array('notice'=>$native)));RGCM_Messages::prepare();check(count($notices['success'])===1,'Shop/cart success preserved');$checkout=true;
RGCM_Messages::begin_payment();echo $html;ob_start();RGCM_Messages::end_payment();$nested=ob_get_clean();check($nested==='','Foreign nested buffer not closed');RGCM_Messages::end_payment();
echo "\nPASS $checks contract checks\n";
