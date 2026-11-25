<?php defined('ABSPATH') || exit; ?>
<?php if (true === WC()->cart->needs_shipping_address()): ?>
<section class="rgck-card rgcm-delivery-section" aria-labelledby="rgcm-delivery-heading">
<div class="rgck-section-head"><h2 id="rgcm-delivery-heading"><span class="rgck-icon"><?php echo RGCK_Checkout::icon('pin'); ?></span>Delivery address</h2></div>
<p class="rgcm-address-hint">We’ll deliver to the address entered above. Sending your order somewhere else?</p>
<div class="woocommerce-shipping-fields">
<h3 id="ship-to-different-address"><label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox rgcm-address-choice"><input id="ship-to-different-address-checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" <?php checked(apply_filters('woocommerce_ship_to_different_address_checked', 0), 1); ?> type="checkbox" name="ship_to_different_address" value="1" aria-controls="rgcm-shipping-fields"><span>Ship to a different address<small>Enter the recipient’s name, address and phone number.</small></span></label></h3>
<div class="shipping_address" id="rgcm-shipping-fields">
<?php do_action('woocommerce_before_checkout_shipping_form', $checkout); ?>
<div class="woocommerce-shipping-fields__field-wrapper"><?php foreach ($checkout->get_checkout_fields('shipping') as $key=>$field) woocommerce_form_field($key, $field, $checkout->get_value($key)); ?></div>
<?php do_action('woocommerce_after_checkout_shipping_form', $checkout); ?>
</div></div>
</section>
<?php endif; ?>
<section class="rgck-card rgcm-order-details" aria-label="Additional order details"><div class="woocommerce-additional-fields">
<?php do_action('woocommerce_before_order_notes', $checkout); ?>
<?php if (apply_filters('woocommerce_enable_order_notes_field', 'yes'===get_option('woocommerce_enable_order_comments','yes'))): ?><h3>Additional order details</h3><div class="woocommerce-additional-fields__field-wrapper"><?php foreach ($checkout->get_checkout_fields('order') as $key=>$field) if (strpos($key,'rgck_custom_')!==0) woocommerce_form_field($key, $field, $checkout->get_value($key)); ?></div><?php endif; ?>
<?php RGCK_Fields::render_custom(); do_action('woocommerce_after_order_notes', $checkout); ?>
</div></section>
