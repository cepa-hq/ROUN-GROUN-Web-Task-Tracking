<?php
// Exercise the production auth-destination adapter without credentials/database.
define('ABSPATH',__DIR__);define('RGFS\\ROOT',dirname(__DIR__));
$base='https://testarea.roungroun.com';$pretty=true;$logged=false;$hooks=[];
function home_url($p='/'){global $base;return $base.'/'.ltrim($p,'/');}
function wc_get_page_permalink($p){return home_url('/my-account/');}
function get_option($k,$default=null){global $pretty;return $k==='permalink_structure'?($pretty?'/%postname%/':''):$default;}
function wp_parse_url($v,$c=-1){return parse_url($v,$c);}
function wp_json_encode($v){return json_encode($v);}
function wp_unslash($v){return is_array($v)?array_map('wp_unslash',$v):stripslashes($v);}
function apply_filters($k,$v){return $v;}
function sanitize_key($v){return preg_replace('/[^a-z0-9_\-]/','',strtolower($v));}
function absint($v){return abs((int)$v);}
function wp_salt($s){return 'offline-test-key';}
function is_ssl(){return true;}
function is_user_logged_in(){global $logged;return $logged;}
function is_wp_error($v){return $v instanceof WP_Error;}
function esc_attr($v){return htmlspecialchars($v,ENT_QUOTES);}
function add_query_arg($a,$b=null,$url=null){if(is_array($a)){$url=$b;$args=$a;}else{$args=[$a=>$b];}$p=parse_url($url);parse_str($p['query']??'',$q);$q=array_merge(array_map('urlencode',$q),$args);return ($p['scheme']??'https').'://'.$p['host'].(isset($p['port'])?':'.$p['port']:'').($p['path']??'/').($q?'?'.implode('&',array_map(fn($k)=>$k.'='.$q[$k],array_keys($q))):'');}
function add_action($hook,$cb,...$rest){global $hooks;$hooks[$hook]=$cb;}
function add_filter($hook,$cb,...$rest){add_action($hook,$cb);}
class WP_Error{}
require dirname(__DIR__).'/includes/forms.php';require dirname(__DIR__).'/includes/frontend.php';require dirname(__DIR__).'/includes/customer-login.php';
use RGFS\Customer_Login as Login;
$checks=[];function expect($ok,$label){global $checks;if(!$ok)throw new Exception('FAIL '.$label);$checks[]=$label;}
$_GET=$_POST=$_COOKIE=[];$_SERVER['REQUEST_URI']='/my-account/';
$survey=home_url('/feedback/general-feedback-mk1/');
expect(Login::destination($survey)===$survey,'recognised survey accepted');
$context=$survey.'?campaign=Feedback_Email&order_id=1613&redirect_to=https%3A%2F%2Fevil.test';
expect(Login::destination($context)===$survey.'?order_id=1613&campaign=feedback_email','only bounded order/campaign fields retained');
$nested=Login::login_url($context);parse_str(parse_url($nested,PHP_URL_QUERY),$nestedQuery);expect(($nestedQuery['rgfs_return']??'')===$survey.'?order_id=1613&campaign=feedback_email','nested survey order/campaign URL roundtrips through customer login');expect(!isset($nestedQuery['campaign'],$nestedQuery['order_id']),'survey query does not escape to outer login query');
foreach(['https://evil.test/feedback/general-feedback-mk1/','//evil.test/feedback/general-feedback-mk1/','http://testarea.roungroun.com/feedback/general-feedback-mk1/','https://user:pass@testarea.roungroun.com/feedback/general-feedback-mk1/',home_url('/wp-admin/'),home_url('/feedback/unknown/'),$survey.'#fragment',$survey.'?order_id[]=1613',$survey.'?order_id=bad',$survey.'?campaign[]=x',$survey."\nX:evil",['url'=>$survey]] as $bad)expect(Login::destination($bad)==='','unsafe/unknown destination rejected '.count($checks));
$signed=Login::signed($survey,time()+300);expect(Login::unpack($signed)===$survey,'signed return accepted');expect(Login::unpack($signed.'x')==='','tampered signature rejected');expect(Login::unpack(Login::signed($survey,time()-1))==='','expired context rejected');expect(Login::unpack(Login::signed($survey,time()+4000))==='','overlong expiry rejected');
$_COOKIE[Login::COOKIE]=$signed;expect(Login::pending()===$survey,'account login retains signed pending survey');$_SERVER['REQUEST_URI']='/shop/';expect(Login::pending()==='','unrelated page does not inherit survey login');$_SERVER['REQUEST_URI']='/my-account/';
$_GET=['rgfs_return'=>$context];ob_start();Login::fields();$html=ob_get_clean();expect(str_contains($html,'name="rgfs_return"')&&str_contains($html,'name="redirect"'),'native customer form receives return fields');
$_GET=[];$_POST=['rgfs_return'=>$survey,'rgcs_login_form'=>'1'];$user=(object)['ID'=>123];expect(Login::after_login(home_url('/my-account/'),new WP_Error())===home_url('/my-account/'),'failed authentication keeps native destination');expect(Login::after_login(home_url('/my-account/'),$user)===$survey,'successful native login resumes survey');expect(!isset($_COOKIE[Login::COOKIE]),'consumed cookie cleared');
$_POST=[];$_COOKIE=[];expect(Login::after_login(home_url('/shop/'),$user)===home_url('/shop/'),'ordinary login untouched');
$_POST=['rgfs_return'=>$survey];expect(Login::after_registration(home_url('/my-account/'))===$survey,'successful registration return preserved');$_POST=[];
$_COOKIE[Login::COOKIE]=$signed;$logged=false;expect(Login::after_social(home_url('/'),home_url('/my-account/'))===home_url('/'),'failed social authentication never resumes survey');$logged=true;expect(Login::after_social(home_url('/wp-admin/'),home_url('/wp-admin/'))===home_url('/wp-admin/'),'explicit admin social destination untouched');expect(Login::after_social(home_url('/'),home_url('/my-account/'))===$survey,'successful customer social callback resumes survey');
$pretty=false;$fallback=home_url('/').'?rgfs_form=general-feedback-mk1';expect(Login::destination($fallback)===$fallback,'plain permalink installation supported');$pretty=true;$base='https://testarea.roungroun.com/store';$_COOKIE=[];$_POST=[];$_GET=['rgfs_return'=>home_url('/feedback/general-feedback-mk1/')];$_SERVER['REQUEST_URI']='/store/my-account/';ob_start();Login::fields();$html=ob_get_clean();expect(str_contains($html,'/store/feedback/'),'subdirectory installation preserves destination');
Login::boot();expect(isset($hooks['woocommerce_login_redirect'],$hooks['woocommerce_registration_redirect'],$hooks['nsl_googlelast_location_redirect']),'native success hooks registered');expect(!isset($hooks['authenticate'],$hooks['wp_authenticate'],$hooks['login_redirect']),'no credential/authentication or global WP redirect override');
foreach($checks as $label)echo 'PASS '.$label."\n";echo count($checks)." customer login checks passed\n";
