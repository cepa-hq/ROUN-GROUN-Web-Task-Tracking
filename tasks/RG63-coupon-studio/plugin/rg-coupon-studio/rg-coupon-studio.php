<?php
/**
 * Plugin Name: Roun Groun Coupon Studio
 * Description: Native WooCommerce coupons, exact scheduling, private attempt analytics and atomic successful-checkout limits.
 * Version: 1.0.0
 * Requires at least: 6.7
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * Author: Roun Groun
 * License: GPL-2.0-or-later
 */
defined('ABSPATH') || exit;
define('RGCO_VERSION', '1.0.0');
define('RGCO_FILE', __FILE__);
require_once __DIR__.'/includes/storage.php';
require_once __DIR__.'/includes/engine.php';
require_once __DIR__.'/includes/admin.php';
register_activation_hook(__FILE__, ['RGCO_Storage','install']);
register_deactivation_hook(__FILE__, function(){wp_clear_scheduled_hook('rgco_cleanup');});
add_action('before_woocommerce_init', function(){
 if(class_exists('Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
  \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', RGCO_FILE, true);
  \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', RGCO_FILE, true);
 }
});
add_action('plugins_loaded', function(){
 if(!class_exists('WooCommerce')) return;
 RGCO_Engine::boot(); RGCO_Admin::boot();
 add_action('rgco_cleanup', ['RGCO_Storage','cleanup']);
 if(!wp_next_scheduled('rgco_cleanup')) wp_schedule_event(time()+3600,'daily','rgco_cleanup');
});
