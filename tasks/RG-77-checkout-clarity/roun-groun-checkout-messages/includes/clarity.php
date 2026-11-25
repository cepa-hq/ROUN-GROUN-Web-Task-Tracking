<?php
defined('ABSPATH') || exit;
/** RG-77: wording and delivery-address presentation only. Native Woo validation
 * still decides validity; preserve error codes, metadata and all provider reasons.
 * The original Checkout Studio controller is adapted under the SAME script handle
 * to retain its editor/analytics dependencies and localized configuration.
 */
final class RGCM_Clarity {
    const VERSION = '1.1.1';
    public static function boot() {
        add_filter('woocommerce_locate_template', array(__CLASS__, 'template'), 4000, 3);
        add_filter('woocommerce_ship_to_different_address_checked', array(__CLASS__, 'shipping_default'), 100);
        add_action('wp_enqueue_scripts', array(__CLASS__, 'assets'), 10001);
        add_action('woocommerce_after_checkout_validation', array(__CLASS__, 'errors'), PHP_INT_MAX, 2);
    }
    public static function context() {
        if (!class_exists('RGCK_Settings') || !RGCK_Settings::get('enabled')) return false;
        if (is_wc_endpoint_url('order-pay') || is_order_received_page()) return false;
        return is_checkout() || (defined('WC_DOING_AJAX') && WC_DOING_AJAX
            && isset($_GET['wc-ajax']) && in_array($_GET['wc-ajax'], array('checkout','update_order_review'), true));
    }
    public static function template($path, $name, $base) {
        return self::context() && $name === 'checkout/form-shipping.php'
            ? dirname(__DIR__).'/templates/form-shipping.php' : $path;
    }
    public static function shipping_default($checked) {
        if (!self::context()) return $checked;
        // Initial GET is off. Never override a shopper's explicit POST selection.
        if (isset($_POST['ship_to_different_address']) && is_scalar($_POST['ship_to_different_address']))
            return in_array((string) $_POST['ship_to_different_address'], array('1','yes','true'), true) ? 1 : 0;
        return 0;
    }
    public static function section($key) {
        if (strpos($key, 'shipping_') === 0) return 'Ship to a different address';
        if ($key === 'billing_email' || $key === 'billing_phone') return RGCK_Settings::get('contact_heading');
        if (strpos($key, 'billing_') === 0) return RGCK_Settings::get('address_heading');
        if (strpos($key, 'account_') === 0) return 'Create an account';
        return 'Additional order details';
    }
    public static function field_message($key, $field, $reason, $data = array()) {
        $label = trim(wp_strip_all_tags($field['label'] ?? str_replace('_',' ', $key)));
        $where = ' in “'.self::section($key).'”';
        $type = $field['type'] ?? 'text';
        if ($reason === 'required') {
            if (substr($key, -6) === '_phone') return 'Enter '.(strpos($key,'shipping_')===0 ? 'the recipient’s' : 'your').' phone number'.$where.'.';
            if ($type === 'checkbox') return 'Select “'.$label.'”'.$where.' to continue.';
            return (in_array($type,array('select','radio','country','state'),true) ? 'Choose' : 'Enter').' “'.$label.'”'.$where.'.';
        }
        if ($reason === 'length') return 'Shorten “'.$label.'”'.$where.' to '.absint($field['maxlength'] ?? 200).' characters or fewer.';
        if ($reason === 'choice' || in_array($type,array('select','radio','country','state'),true)) return 'Choose an available option for “'.$label.'”'.$where.'.';
        if (substr($key,-6)==='_email' || in_array('email', $field['validate']??array(), true)) return 'Enter a valid email address'.$where.', such as name@example.com.';
        if (substr($key,-6)==='_phone' || in_array('phone', $field['validate']??array(), true)) return 'Enter a valid phone number'.$where.'. Check the digits and country code.';
        if (substr($key,-9)==='_postcode') {
            $country = $data[strpos($key,'shipping_')===0 ? 'shipping_country' : 'billing_country'] ?? '';
            return $country === 'IN' ? 'Enter a 6-digit PIN code'.$where.'.' : 'Enter a valid postcode or ZIP code'.$where.' for the selected country.';
        }
        return 'Check “'.$label.'”'.$where.' and enter a valid value.';
    }
    public static function errors($data, $errors) {
        if (!self::context() || !$errors instanceof WP_Error) return;
        $fields = array(); foreach (WC()->checkout()->get_checkout_fields() as $rows) $fields += $rows;
        foreach ($errors->get_error_codes() as $code) {
            $replacement = null; $field_key = null;
            foreach ($fields as $key => $field) {
                $reason = null;
                if ($code === $key.'_required') $reason = 'required';
                elseif ($code === $key.'_validation') $reason = 'validation';
                // Checkout Studio custom-field error codes differ from native Woo.
                elseif (strpos($key,'rgck_custom_')===0) {
                    if ($code === $key) $reason = 'required';
                    elseif ($code === $key.'_length') $reason = 'length';
                    elseif ($code === $key.'_choice') $reason = 'choice';
                }
                if ($reason) { $replacement = self::field_message($key, $field, $reason, $data); $field_key = $key; break; }
            }
            $known = array(
                'terms' => 'In “Choose payment method”, tick the terms and conditions box before placing your order.',
                'payment' => 'In “Choose payment method”, select one of the payment options currently shown. Your earlier choice is no longer available.',
                'billing_country_not_allowed' => 'The country selected in “Your address” is outside our ordering area. Choose a supported country to continue.',
                'rga_cod_restricted' => 'Cash on delivery is unavailable for an item in your order. In “Choose payment method”, choose an online payment option.'
            );
            if (isset($known[$code])) $replacement = $known[$code];
            // The shipping code can also carry a useful country/provider explanation.
            // Rewrite ONLY the two exact native generic messages, retain all others.
            foreach ($errors->get_error_messages($code) as $index => $message) {
                $copy = $replacement;
                if ($code === 'shipping' && in_array(wp_strip_all_tags($message), array('Please enter an address to continue.','No shipping method has been selected. Please double check your address, or contact us if you need any help.'), true)) {
                    $address = empty($data['ship_to_different_address']) ? 'Your address' : 'Ship to a different address';
                    $copy = 'Check the street address, city, state and PIN code in “'.$address.'”. Then choose an available delivery option in “Choose delivery”.';
                }
                if ($copy !== null) $errors->errors[$code][$index] = esc_html($copy);
            }
            // Keep existing IDs/metadata (and therefore native invalid-field styling).
            if ($field_key && !$errors->get_error_data($code)) $errors->add_data(array('id'=>$field_key), $code);
        }
    }
    public static function assets() {
        if (!self::context()) return;
        global $wp_scripts;
        if (isset($wp_scripts->registered['rgck'])) {
            $wp_scripts->registered['rgck']->src = plugins_url('../assets/contextual-checkout.js', __FILE__);
            $wp_scripts->registered['rgck']->ver = self::VERSION;
        }
        if (isset($wp_scripts->registered['rgck-editor'])) {
            $wp_scripts->registered['rgck-editor']->src = plugins_url('../assets/contextual-editor.js', __FILE__);
            $wp_scripts->registered['rgck-editor']->ver = self::VERSION;
        }
        wp_enqueue_style('rgcm-clarity', plugins_url('../assets/clarity.css', __FILE__), array('rgck-editor'), self::VERSION);
    }
}
add_action('plugins_loaded', array('RGCM_Clarity','boot'), 311);
