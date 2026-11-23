<?php
/**
 * RG-59 (23 Nov 2026): Merchant Center checkout bridge.
 *
 * GMC offer IDs are gla_PRODUCT_OR_VARIATION_ID, not Woo numeric IDs. The old
 * /checkout/?add-to-cart={id} template was rejected. Never absint an arbitrary
 * offer string, choose a variable parent's first variation, empty a cart, or
 * implement a second AJAX + native add (the historical RG-7 quantity-2 bug).
 *
 * Entry: /?rg_checkout_offer=gla_1101&quantity=1
 * Also accepts a real numeric product/variation ID. Variable parents require
 * every attribute_* selection. Fixed variation IDs derive selections from Woo.
 * Optional rg_destination=cart; default checkout. No login/nonces are required
 * for this public cart-only GET, like native Woo add-to-cart links. It cannot
 * create/pay/cancel an order or redirect to a caller-supplied location.
 *
 * Repeated entry ensures a minimum requested quantity; it never reduces an
 * existing quantity or adds another copy when that quantity already exists.
 * The 303 destination drops all mutation parameters, making reload safe.
 * Product-level feed URLs deliberately exclude unselected variable parents
 * and unavailable products. Do NOT simultaneously set an account URL template.
 */
final class RGCK_Merchant_Entry {
    public static function resolve($offer, $request = array()) {
        if (!is_string($offer) || !preg_match('/^(?:gla_)?([1-9][0-9]{0,9})$/D', $offer, $match)) {
            return new WP_Error('rgck_offer', 'This product link is invalid. Please choose an item from the shop.');
        }
        $product = wc_get_product((int) $match[1]);
        if (!$product || $product->get_status() !== 'publish') {
            return new WP_Error('rgck_unavailable', 'This item is unavailable. Please choose another item from the shop.');
        }
        $parent = $product->is_type('variation') ? wc_get_product($product->get_parent_id()) : $product;
        if (!$parent || $parent->get_status() !== 'publish' || $parent->get_catalog_visibility() === 'hidden'
            || get_post_field('post_password', $parent->get_id()) !== '') {
            return new WP_Error('rgck_parent', 'This item is unavailable. Please choose another item from the shop.');
        }
        $attributes = array();
        if ($product->is_type(array('variable', 'variation'))) {
            $fixed = $product->is_type('variation') ? $product->get_variation_attributes() : array();
            foreach ($parent->get_variation_attributes() as $name => $options) {
                $key = 'attribute_'.sanitize_title($name);
                $stored = (string) ($fixed[$key] ?? '');
                if (isset($request[$key]) && !is_string($request[$key])) {
                    return new WP_Error('rgck_attribute', 'Please choose valid product options before checking out.');
                }
                $selected = isset($request[$key]) ? wc_clean(wp_unslash($request[$key])) : $stored;
                if ($selected === '' || ($stored !== '' && $selected !== $stored) || !in_array($selected, $options, true)) {
                    return new WP_Error('rgck_selection', 'Please choose all product options on the product page before checking out.');
                }
                $attributes[$key] = $selected;
            }
            if ($product->is_type('variable')) {
                $variation_id = WC_Data_Store::load('product')->find_matching_product_variation($product, $attributes);
                $product = $variation_id ? wc_get_product($variation_id) : false;
            }
        }
        if (!$product || !$product->is_type(array('simple', 'variation')) || $product->get_status() !== 'publish'
            || ($product->is_type('variation') && ((int) $product->get_parent_id() !== (int) $parent->get_id()
                || !$product->variation_is_active() || !$product->variation_is_visible()))
            || !$product->is_purchasable() || !$product->is_in_stock()) {
            return new WP_Error('rgck_stock', 'This product option is currently unavailable. Please choose another item or option.');
        }
        return array('product' => $product, 'product_id' => $parent->get_id(),
            'variation_id' => $product->is_type('variation') ? $product->get_id() : 0, 'attributes' => $attributes);
    }

    /** Testable cart operation: native Woo stock/filter hooks remain authoritative. */
    public static function ensure_item($resolved, $quantity) {
        if (!is_int($quantity) || $quantity < 1 || $quantity > 99) {
            return new WP_Error('rgck_quantity', 'Choose a whole-number quantity between 1 and 99.');
        }
        $product = $resolved['product'];
        if (!$product->has_enough_stock($quantity) || ($product->is_sold_individually() && $quantity > 1)) {
            return new WP_Error('rgck_quantity_stock', 'The requested quantity is unavailable. Please review your cart.');
        }
        $attributes = $resolved['attributes']; ksort($attributes);
        $existing_quantity = 0;
        foreach (WC()->cart->get_cart() as $item) {
            $item_attributes = $item['variation'] ?? array(); ksort($item_attributes);
            if ((int) $item['product_id'] === (int) $resolved['product_id']
                && (int) ($item['variation_id'] ?? 0) === (int) $resolved['variation_id']
                && $item_attributes === $attributes) {
                $existing_quantity += (int) $item['quantity'];
            }
        }
        $add = max(0, $quantity - $existing_quantity);
        if (!$add) { return true; }
        if (!apply_filters('woocommerce_add_to_cart_validation', true, $resolved['product_id'], $add,
            $resolved['variation_id'], $resolved['attributes'])) {
            return new WP_Error('rgck_validation', 'This item could not be added. Please review your product options.');
        }
        $key = WC()->cart->add_to_cart($resolved['product_id'], $add, $resolved['variation_id'], $resolved['attributes']);
        return $key ? true : new WP_Error('rgck_cart', 'This item could not be added. Please review your cart.');
    }

    public static function entry() {
        if (!isset($_GET['rg_checkout_offer']) || is_admin() || wp_doing_ajax()
            || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') { return; }
        if (!defined('DONOTCACHEPAGE')) { define('DONOTCACHEPAGE', true); }
        wc_nocache_headers();
        if (!headers_sent()) { header('X-Robots-Tag: noindex, nofollow, noarchive', true); }
        if (!WC()->cart || !WC()->session) { wc_load_cart(); }
        $resolved = self::resolve(wp_unslash($_GET['rg_checkout_offer']), $_GET);
        $raw_quantity = $_GET['quantity'] ?? '1';
        $result = is_wp_error($resolved) ? $resolved :
            ((!is_string($raw_quantity) || !preg_match('/^[1-9][0-9]?$/D', $raw_quantity))
                ? new WP_Error('rgck_quantity', 'Choose a whole-number quantity between 1 and 99.')
                : self::ensure_item($resolved, (int) $raw_quantity));
        if (is_wp_error($result)) {
            wc_add_notice($result->get_error_message(), 'error');
            $destination = wc_get_cart_url();
        } else {
            WC()->cart->calculate_totals();
            WC()->cart->set_session();
            WC()->session->set_customer_session_cookie(true);
            $destination = isset($_GET['rg_destination']) && $_GET['rg_destination'] === 'cart'
                ? wc_get_cart_url() : wc_get_checkout_url();
        }
        // Preserve standard incoming attribution, never arbitrary redirects/queries.
        $attribution = array();
        foreach (array('gclid','dclid','gbraid','wbraid','gad_source','utm_source','utm_medium',
            'utm_campaign','utm_term','utm_content','utm_id') as $key) {
            if (isset($_GET[$key]) && is_string($_GET[$key]) && strlen($_GET[$key]) <= 256) {
                $attribution[$key] = sanitize_text_field(wp_unslash($_GET[$key]));
            }
        }
        wp_safe_redirect($attribution ? add_query_arg($attribution, $destination) : $destination, 303);
        exit;
    }

    /** Supported GLA 3.10.1 extension point; no Google/vendor files are patched. */
    public static function feed($attributes, $product, $adapter) {
        if (!$product instanceof WC_Product || !$product->is_type(array('simple','variation'))
            || !is_object($adapter) || !method_exists($adapter, 'add_custom_attribute')
            || !method_exists($adapter, 'get_custom_attributes') || !method_exists($adapter, 'set_custom_attributes')) {
            return $attributes;
        }
        $current = array_values(array_filter($adapter->get_custom_attributes(), static function($attribute) {
            return ($attribute['name'] ?? '') !== 'checkout_link_template';
        }));
        $adapter->set_custom_attributes($current);
        $resolved = self::resolve('gla_'.$product->get_id());
        if (!is_wp_error($resolved)) {
            $adapter->add_custom_attribute(array('name' => 'checkout_link_template',
                'value' => add_query_arg(array('rg_checkout_offer' => 'gla_'.$product->get_id(), 'quantity' => 1), home_url('/'))));
        }
        return $attributes;
    }
}
