<?php
/**
 * Plugin Name: Roun Groun Checkout Messages
 * Description: Product-specific COD explanations and clear payment wording for Checkout Studio. RG-75.
 * Version: 1.0.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Author: Roun Groun
 * License: GPL-2.0-or-later
 */
defined('ABSPATH') || exit;

/**
 * RG-75, 25 November 2026. Presentation only: never adds/removes a gateway,
 * changes eligibility, processes a payment, or writes order/customer details.
 * RGA_Commerce_Guards and WPFactory remain the eligibility authorities.
 * Deactivate this companion plugin to roll back all of these UI changes.
 */
final class RGCM_Messages {
    private static $buffer_level = null;
    const SESSION_KEY = 'rgcm_cart_notice_hashes';

    public static function boot() {
        add_action('wp', array(__CLASS__, 'prepare'), 60);
        add_filter('wc_add_to_cart_message_html', array(__CLASS__, 'remember_cart_notice'), PHP_INT_MAX);
        add_filter('woocommerce_get_privacy_policy_text', array(__CLASS__, 'privacy'), 100, 2);
        // Native payment.php skips these hooks during AJAX; fragments covers that path.
        add_action('woocommerce_review_order_before_payment', array(__CLASS__, 'begin_payment'), PHP_INT_MAX);
        add_action('woocommerce_review_order_after_payment', array(__CLASS__, 'end_payment'), -PHP_INT_MAX);
        add_filter('woocommerce_update_order_review_fragments', array(__CLASS__, 'fragments'), 100);
        add_action('wp_enqueue_scripts', array(__CLASS__, 'style'), 10000);
    }
    private static function enabled() {
        return class_exists('RGCK_Settings') && RGCK_Settings::get('enabled');
    }
    private static function main() {
        return self::enabled() && function_exists('is_checkout') && is_checkout()
            && !is_wc_endpoint_url('order-pay') && !is_order_received_page();
    }
    private static function context() {
        // An AJAX total refresh has no page query; recognize only Woo's own endpoint.
        return self::main() || (self::enabled() && defined('WC_DOING_AJAX') && WC_DOING_AJAX
            && isset($_GET['wc-ajax']) && $_GET['wc-ajax'] === 'update_order_review');
    }
    public static function remember_cart_notice($message) {
        if (!self::enabled() || !WC()->session || !is_string($message) || $message === '') return $message;
        // Record hashes, not product/customer text. Keep normal shop/cart notices intact.
        // The exact native success message is removed only on arrival at checkout.
        $hashes = (array) WC()->session->get(self::SESSION_KEY, array());
        $hashes[hash('sha256', $message)] = time();
        WC()->session->set(self::SESSION_KEY, array_slice($hashes, -20, null, true));
        return $message;
    }
    public static function prepare() {
        if (!self::main()) return;
        // Checkout Studio already supplies the functional coupon panel in the summary.
        // Remove only Woo's duplicate top toggle, preserving login and real errors.
        remove_action('woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10);
        if (!WC()->session) return;
        $hashes = (array) WC()->session->get(self::SESSION_KEY, array());
        $notices = wc_get_notices(); $changed = false;
        foreach ($notices['success'] ?? array() as $key => $notice) {
            $stamp = $hashes[hash('sha256', (string) ($notice['notice'] ?? ''))] ?? 0;
            if ($stamp && $stamp >= time() - 3600) { unset($notices['success'][$key]); $changed = true; }
        }
        if ($changed) { $notices['success'] = array_values($notices['success']); wc_set_notices($notices); }
        WC()->session->__unset(self::SESSION_KEY);
    }
    public static function privacy($text, $type) {
        if ($type !== 'checkout' || !self::context()) return $text;
        // Sensitive credentials and merchant order records are different data.
        // Do not promise that payment status/reference information is never retained.
        return 'Razorpay securely processes online payments. Roun Groun does not collect or store your full card number, CVV, UPI PIN or banking password. Your contact and delivery details are used to process your order. Read our [privacy_policy].';
    }
    /** Product names follow the same parent/variation and whole-cart rules as RGA. */
    public static function restricted_names($items) {
        if (!class_exists('RGA_Commerce_Guards') || !RGA_Commerce_Guards::blocked($items)) return array();
        $all_ids = array();
        foreach ($items as $item) {
            $all_ids[] = absint($item['product_id'] ?? 0);
            $all_ids[] = absint($item['variation_id'] ?? 0);
        }
        $excluded = self::rule('excl'); $included = self::rule('incl');
        // WPFactory inclusion is satisfied by ANY matching cart item, not every item.
        $missing_inclusion = $included && !array_intersect($all_ids, $included);
        $names = array();
        foreach ($items as $item) {
            $ids = array(absint($item['product_id'] ?? 0), absint($item['variation_id'] ?? 0));
            if (!$missing_inclusion && !array_intersect($ids, $excluded)) continue;
            $product = $item['data'] ?? null;
            if (!$product || !is_callable(array($product, 'get_name'))) continue;
            $name = trim(wp_strip_all_tags($product->get_name()));
            if ($name !== '') $names[$name] = $name;
        }
        return array_values($names);
    }
    private static function rule($kind) {
        if (get_option('alg_wc_cpg_product_'.$kind.'_enabled', 'no') !== 'yes') return array();
        $rules = get_option('alg_wc_cpg_product_'.$kind, array());
        return is_array($rules) && isset($rules['cod']) && is_array($rules['cod'])
            ? array_filter(array_map('absint', $rules['cod'])) : array();
    }
    public static function payment_html($html) {
        if (!self::context() || !WC()->cart || !WC()->cart->needs_payment()) return $html;
        // Respect the final gateway output. Never claim COD is unavailable if displayed.
        if (strpos($html, 'payment_method_cod') !== false || strpos($html, 'rgcm-cod-note') !== false) return $html;
        $names = self::restricted_names(WC()->cart->get_cart());
        if (!$names) return $html;
        $quoted = array();
        foreach ($names as $name) $quoted[] = '“'.esc_html($name).'”';
        $message = '<p class="rgcm-cod-note" role="status">'.implode(', ', $quoted).' are not available for COD transaction.</p>';
        // Native payment.php owns gateways, terms, nonce and submit. Insert only our
        // paragraph after its payment list, without maintaining a template override.
        $count = 0;
        $result = preg_replace_callback('/(<div\b[^>]*class=["\'][^"\']*\bplace-order\b[^"\']*["\'][^>]*>)/i',
            static function($match) use ($message) { return $message.$match[0]; }, $html, 1, $count);
        return $count ? $result : $html; // Fail quietly if a future WC template changes.
    }
    public static function begin_payment() {
        if (self::main() && self::$buffer_level === null) { self::$buffer_level = ob_get_level(); ob_start(); }
    }
    public static function end_payment() {
        if (self::$buffer_level === null || ob_get_level() !== self::$buffer_level + 1) return;
        $html = ob_get_clean(); self::$buffer_level = null;
        echo self::payment_html($html); // Native trusted markup plus escaped product names.
    }
    public static function fragments($fragments) {
        if (isset($fragments['.woocommerce-checkout-payment']))
            $fragments['.woocommerce-checkout-payment'] = self::payment_html($fragments['.woocommerce-checkout-payment']);
        return $fragments;
    }
    public static function style() {
        if (!self::main()) return;
        // Only our new paragraph: no existing checkout selectors or font downloads.
        wp_add_inline_style('rgck', '.rgck-page .rgcm-cod-note{margin:12px 0 16px;padding:10px 12px;border-left:2px solid var(--rgck-accent);background:var(--rgck-bg);color:var(--rgck-ink);font:inherit;font-size:13px;line-height:1.55;overflow-wrap:anywhere}');
    }
}
add_action('plugins_loaded', array('RGCM_Messages', 'boot'), 310);
