<?php
define('ABSPATH',__DIR__);define('WC_DOING_AJAX',true);
$checkout=true;$endpoint='';$enabled=true;
function add_action(...$x){} function add_filter(...$x){}
function is_checkout(){global $checkout;return $checkout;}
function is_wc_endpoint_url($k){global $endpoint;return $endpoint===$k;}
function is_order_received_page(){global $endpoint;return $endpoint==='order-received';}
function wp_strip_all_tags($s){return strip_tags($s);}function absint($s){return abs((int)$s);}
function esc_html($s){return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}
class RGCK_Settings{static function get($key){global $enabled;return array('enabled'=>$enabled,'contact_heading'=>'Account & contact details','address_heading'=>'Your address')[$key]??'';}}
class WP_Error{public $errors=array();private $data=array();function add($k,$s,$d=null){$this->errors[$k][]=$s;if($d!==null)$this->data[$k]=$d;}function get_error_codes(){return array_keys($this->errors);}function get_error_messages($k){return $this->errors[$k]??array();}function get_error_data($k){return $this->data[$k]??null;}function add_data($d,$k){$this->data[$k]=$d;}}
$fields=array('billing'=>array('billing_email'=>array('label'=>'Email address','validate'=>array('email')),'billing_postcode'=>array('label'=>'PIN Code')),'shipping'=>array('shipping_phone'=>array('label'=>'Phone','validate'=>array('phone')),'shipping_state'=>array('label'=>'State','type'=>'state'),'shipping_country'=>array('label'=>'Country / Region','type'=>'country')),'order'=>array('rgck_custom_note'=>array('label'=>'Gift note','maxlength'=>30)));
class Checkout{function get_checkout_fields(){global $fields;return $fields;}}
function WC(){return new class {function checkout(){return new Checkout;}};}
require __DIR__.'/../roun-groun-checkout-messages/includes/clarity.php';
$n=0;function check($b,$why){global $n;$n++;if(!$b)throw new Exception($why);}
check(RGCM_Clarity::shipping_default(1)===0,'Initial main checkout off');
$_POST['ship_to_different_address']='1';check(RGCM_Clarity::shipping_default(0)===1,'Explicit posted opt-in retained');
$_POST['ship_to_different_address']='0';check(RGCM_Clarity::shipping_default(1)===0,'Explicit opt-out retained');
$_POST=array();$checkout=false;check(RGCM_Clarity::shipping_default(1)===1,'Other pages unchanged');
$_GET['wc-ajax']='checkout';check(RGCM_Clarity::context(),'Native validation AJAX supported');unset($_GET['wc-ajax']);$checkout=true;
foreach(array('order-pay','order-received')as $endpoint)check(!RGCM_Clarity::context(),'Unrelated endpoint untouched');$endpoint='';
$e=new WP_Error;$meta=array('id'=>'shipping_phone','custom'=>array('keep'=>true));
$e->add('shipping_phone_required','Abstract',$meta);$e->add('billing_email_validation','Abstract',array('id'=>'billing_email'));$e->add('billing_postcode_validation','Abstract',array('id'=>'billing_postcode'));$e->add('shipping_state_validation','Abstract');$e->add('rgck_custom_note_length','Abstract',array('id'=>'rgck_custom_note'));$e->add('payment','Invalid payment method.');$e->add('terms','Abstract',array('id'=>'terms'));$e->add('shipping','Please enter an address to continue.');$e->add('shipping','Carrier: this destination cannot receive this item.');$e->add('provider_declined','Issuer declined the card. Use another card.');
$before=$e->get_error_codes();RGCM_Clarity::errors(array('billing_country'=>'IN','ship_to_different_address'=>'1'),$e);
check($e->get_error_codes()===$before,'Native error codes and order preserved');
check($e->get_error_data('shipping_phone_required')===$meta,'Existing metadata unchanged');
check($e->get_error_messages('shipping_phone_required')[0]==='Enter the recipient’s phone number in “Ship to a different address”.','Exact reported phone case');
check(strpos($e->get_error_messages('billing_email_validation')[0],'Account &amp; contact details')!==false,'Email identifies contact section, escaped');
check($e->get_error_messages('billing_postcode_validation')[0]==='Enter a 6-digit PIN code in “Your address”.','Country-specific PIN guidance');
check($e->get_error_data('shipping_state_validation')===array('id'=>'shipping_state'),'Restore missing native field id');
check(strpos($e->get_error_messages('rgck_custom_note_length')[0],'30 characters or fewer')!==false,'Custom length limit');
check(strpos($e->get_error_messages('payment')[0],'Choose payment method')!==false,'Payment action identifies step');
check(strpos($e->get_error_messages('terms')[0],'tick the terms')!==false,'Terms action');
check(strpos($e->get_error_messages('shipping')[0],'Ship to a different address')!==false,'Delivery error uses selected address');
check($e->get_error_messages('shipping')[1]==='Carrier: this destination cannot receive this item.','Meaningful shipping cause retained');
check($e->get_error_messages('provider_declined')[0]==='Issuer declined the card. Use another card.','Unknown gateway reason retained');
$enabled=false;$x=new WP_Error;$x->add('terms','Untouched');RGCM_Clarity::errors(array(),$x);check($x->get_error_messages('terms')[0]==='Untouched','Disabled checkout untouched');
check(RGCM_Clarity::section('account_password')==='Create an account','Account fields identify Create an account');
echo "PASS $n PHP contract checks\n";
