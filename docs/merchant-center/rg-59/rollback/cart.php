<?php
defined('ABSPATH') || exit;
final class RGCK_Cart {
    public static function boot(){add_action('wc_ajax_rgck_cart',array(__CLASS__,'update'));}
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
