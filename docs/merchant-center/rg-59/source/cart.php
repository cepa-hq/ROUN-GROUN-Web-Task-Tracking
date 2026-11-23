<?php
defined('ABSPATH') || exit;
final class RGCK_Cart {
    public static function boot(){add_action('wc_ajax_rgck_cart',array(__CLASS__,'update'));add_action('wp_loaded',array('RGCK_Merchant_Entry','entry'),19);add_filter('woocommerce_gla_product_attribute_values',array('RGCK_Merchant_Entry','feed'),100,3);}
    public static function update(){
        if(!check_ajax_referer('rgck_cart','nonce',false))wp_send_json_error(array('message'=>'Your checkout session expired. Refresh the page and try again.'),403);
        if(!RGCK_Settings::get('enabled')||!RGCK_Settings::get('cart_edit')||!WC()->cart)wp_send_json_error(array('message'=>'Cart editing is unavailable.'),400);
        $key=wc_clean(wp_unslash($_POST['key']??''));$item=WC()->cart->get_cart_item($key);
        if(!$item)wp_send_json_error(array('message'=>'This item is no longer in your cart. Refresh checkout.'),400);
        $qty=wc_stock_amount(wp_unslash($_POST['quantity']??$item['quantity']));
        if($qty<0)wp_send_json_error(array('message'=>'Choose a valid quantity.'),400);
        $product=$item['data'];$attrs=array();$variation_id=(int)$item['variation_id'];
        if($qty>0&&isset($_POST['attributes'])&&$product->is_type('variation')){
            $parent=wc_get_product($item['product_id']);
            foreach($parent->get_variation_attributes() as $name=>$options){$attr='attribute_'.sanitize_title($name);$value=wc_clean(wp_unslash($_POST['attributes'][$attr]??''));if($value==='')wp_send_json_error(array('message'=>'Choose all product options.'),400);$attrs[$attr]=$value;}
            $store=WC_Data_Store::load('product');$variation_id=$store->find_matching_product_variation($parent,$attrs);$product=wc_get_product($variation_id);
            if(!$product||!$product->is_purchasable()||!$product->variation_is_active()||!$product->is_in_stock())wp_send_json_error(array('message'=>'That option is unavailable. Choose another option.'),400);
        }
        if($qty>0&&(!$product->is_purchasable()||!$product->is_in_stock()||!$product->has_enough_stock($qty)))wp_send_json_error(array('message'=>'That quantity is unavailable.'),400);
        if($qty>0){$quantities=WC()->cart->get_cart_item_quantities();$stock_id=$product->get_stock_managed_by_id();$existing=(float)($quantities[$stock_id]??0);if($stock_id===$item['data']->get_stock_managed_by_id())$existing-=$item['quantity'];if(!$product->has_enough_stock(max(0,$existing)+$qty))wp_send_json_error(array('message'=>'There is not enough stock for this cart quantity.'),400);}
        if($product->is_sold_individually()&&$qty>1)wp_send_json_error(array('message'=>'Only one of this product can be ordered.'),400);
        if(!apply_filters('woocommerce_update_cart_validation',true,$key,$item,$qty))wp_send_json_error(array('message'=>'This cart change could not be applied.'),400);
        if($variation_id!==(int)$item['variation_id']&&$qty>0){
            if(!apply_filters('woocommerce_add_to_cart_validation',true,$item['product_id'],$qty,$variation_id,$attrs))wp_send_json_error(array('message'=>'That product option could not be added.'),400);
            $extra=$item;foreach(array('key','product_id','variation_id','variation','quantity','data','data_hash','line_tax_data','line_subtotal','line_subtotal_tax','line_total','line_tax') as $k)unset($extra[$k]);
            $new=WC()->cart->add_to_cart($item['product_id'],$qty,$variation_id,$attrs,apply_filters('rgck_cart_item_edit_data',$extra,$item));
            if(!$new)wp_send_json_error(array('message'=>'That option could not be added. Your original item is unchanged.'),400);
            WC()->cart->remove_cart_item($key);
        }else WC()->cart->set_quantity($key,$qty,true);
        WC()->cart->calculate_totals();WC()->cart->set_session();
        wp_send_json_success(array('empty'=>WC()->cart->is_empty(),'cart_url'=>wc_get_cart_url()));
    }
    public static function editor($key,$item){
        if(!RGCK_Settings::get('cart_edit'))return;
        $p=$item['data'];$uid='rgck-item-'.substr(md5($key),0,12);
        echo '<details class="rgck-item-editor"><summary><span>'.RGCK_Checkout::icon('edit').'Edit item</span>'.RGCK_Checkout::icon('chevron').'</summary><div class="rgck-item-controls" data-cart-key="'.esc_attr($key).'">';
        $max=$p->is_sold_individually()?1:-1; // New variant stock is checked by WooCommerce when saved.
        echo '<div class="rgck-quantity-row"><label for="'.esc_attr($uid).'-qty">Quantity</label><div class="rgck-stepper"><button type="button" data-qty-step="-1" aria-label="Decrease quantity">'.RGCK_Checkout::icon('minus').'</button><input id="'.esc_attr($uid).'-qty" type="number" min="1" step="1" inputmode="numeric" value="'.esc_attr($item['quantity']).'" '.($max>0?'max="'.esc_attr($max).'"':'').' data-quantity><button type="button" data-qty-step="1" aria-label="Increase quantity" '.($p->is_sold_individually()?'disabled':'').'>'.RGCK_Checkout::icon('plus').'</button></div></div>';
        if($p->is_type('variation')){
            $parent=wc_get_product($item['product_id']);
            foreach($parent->get_variation_attributes() as $name=>$options){$attr='attribute_'.sanitize_title($name);echo '<fieldset class="rgck-options"><legend>'.esc_html(wc_attribute_label($name)).'</legend><div class="rgck-option-list">';foreach($options as $value){$label=$value;if(taxonomy_exists($name)){$term=get_term_by('slug',$value,$name);if($term)$label=$term->name;}echo '<label class="rgck-option"><input type="radio" name="'.esc_attr($uid.'-'.$attr).'" data-attribute="'.esc_attr($attr).'" value="'.esc_attr($value).'" '.checked($item['variation'][$attr]??'',$value,false).'><span>'.esc_html($label).'</span></label>';}echo '</div></fieldset>';}
        }
        echo '<div class="rgck-editor-actions"><button type="button" class="rgck-edit-save">Save changes'.RGCK_Checkout::icon('check').'</button><button type="button" class="rgck-edit-remove">'.RGCK_Checkout::icon('trash').'Remove</button></div><p class="rgck-edit-hint">Save changes to update your total. Availability is checked when you save.</p><p class="rgck-item-feedback" role="status" aria-live="polite"></p></div></details>';
    }
}

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
