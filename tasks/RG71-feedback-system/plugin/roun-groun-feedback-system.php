<?php
/**
 * Plugin Name: Roun Groun Feedback system
 * Description: First-party, account-linked surveys, question analytics and surprise delivery gift fulfilment.
 * Version: 1.0.2
 * Author: Roun Groun
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Text Domain: roun-groun-feedback-system
 */
namespace RGFS;
defined('ABSPATH') || exit;
const VERSION = '1.0.2';
const ROOT = __DIR__;
const FILE = __FILE__;
foreach (['forms','store','analytics','frontend','admin','privacy'] as $part) { require_once ROOT.'/includes/'.$part.'.php'; }
register_activation_hook(__FILE__, [Store::class,'install']);
// Deactivation never erases replies, reward queues or historical form definitions.
register_deactivation_hook(__FILE__, function(){ flush_rewrite_rules(); });
add_action('before_woocommerce_init', function(){
    if (class_exists('Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', FILE, true);
    }
});
add_action('plugins_loaded', function(){
    if (get_option('rgfs_schema') !== Store::SCHEMA) { Store::install(); }
    Forms::snapshot();
    Frontend::boot(); Admin::boot(); Privacy::boot();
    add_action('woocommerce_order_status_processing', [Store::class,'claim_gift'],20);
});
