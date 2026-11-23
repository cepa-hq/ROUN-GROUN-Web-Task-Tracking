<?php
/**
 * Plugin Name: Roun Groun Checkout Studio
 * Description: Theme-independent, editable WooCommerce checkout with native payments, coupons and shipping. No payment credentials stored here.
 * Version: 1.3.4
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Author: Roun Groun
 * License: GPL-2.0-or-later
 */
defined('ABSPATH') || exit;
define('RGCK_VERSION', '1.3.4');
define('RGCK_DIR', __DIR__.'/');
define('RGCK_URL', plugin_dir_url(__FILE__));
require_once RGCK_DIR.'includes/settings.php';
require_once RGCK_DIR.'includes/admin.php';
require_once RGCK_DIR.'includes/results.php';
require_once RGCK_DIR.'includes/checkout.php';
require_once RGCK_DIR.'includes/fields.php';
require_once RGCK_DIR.'includes/cart.php';
add_action('before_woocommerce_init', static function(){
    if(class_exists('Automattic\WooCommerce\Utilities\FeaturesUtil')){
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables',__FILE__,true);
    }
});
add_action('plugins_loaded', static function(){
    RGCK_Admin::boot();
    if(!class_exists('WooCommerce'))return;
    RGCK_Checkout::boot();RGCK_Results::boot();RGCK_Fields::boot();RGCK_Cart::boot();
}, 300);
