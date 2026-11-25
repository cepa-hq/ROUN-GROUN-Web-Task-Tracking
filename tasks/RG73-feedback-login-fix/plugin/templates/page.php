<?php
defined('ABSPATH') || exit;
$user=wp_get_current_user();$name=$user->display_name?:$user->user_login;
$initial=function_exists('mb_substr')?mb_substr($name,0,1):substr($name,0,1);
$ctx=\RGFS\Frontend::context(\RGFS\Frontend::current());
$order=$ctx['order_id']&&function_exists('wc_get_order')?wc_get_order($ctx['order_id']):null;
$products=[];if($order)foreach($order->get_items() as $item){$products[]=$item->get_name().' × '.$item->get_quantity();if(count($products)>=3)break;}
$order_text=$order?'Order #'.$order->get_order_number().' · '.implode(' + ',$products):'Signed in to your Roun Groun account';
?><!doctype html><html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><?php wp_head(); ?></head><body <?php body_class(); ?>><?php wp_body_open(); ?>
<?php if(\RGFS\Admin::can()): ?><p style="margin:0;background:#402c48;color:white;text-align:center;padding:8px;font:13px Arial">Staff test mode · Excluded from customer results and gifts</p><?php endif; ?>
<?php require __DIR__.'/form.php'; ?>
<?php wp_footer(); ?></body></html>
