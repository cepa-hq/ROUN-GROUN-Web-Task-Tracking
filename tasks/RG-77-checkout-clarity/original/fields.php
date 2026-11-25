<?php
defined('ABSPATH') || exit;
final class RGCK_Fields {
    public static function boot(){
        add_filter('woocommerce_checkout_fields',array(__CLASS__,'fields'),90);
        add_action('woocommerce_checkout_update_order_review',array(__CLASS__,'session'));
        add_action('woocommerce_cart_calculate_fees',array(__CLASS__,'fees'),30);
        add_action('woocommerce_after_checkout_validation',array(__CLASS__,'validate'),20,2);
        add_action('woocommerce_checkout_create_order',array(__CLASS__,'save'),20,2);
        add_action('woocommerce_admin_order_data_after_billing_address',array(__CLASS__,'display'));
        add_action('woocommerce_order_details_after_order_table',array(__CLASS__,'display'));
        add_action('woocommerce_email_order_meta',array(__CLASS__,'email'),20,4);
    }
    public static function applicable($f){
        if(!WC()->cart)return false;
        if(WC()->cart->get_subtotal()<(float)$f['min_total'])return false;
        $ids=array_filter(array_map('absint',explode(',',$f['product_ids'])));
        if(!$ids)return true;
        foreach(WC()->cart->get_cart() as $item)if(in_array($item['product_id'],$ids,true)||in_array($item['variation_id'],$ids,true))return true;
        return false;
    }
    public static function custom(){return RGCK_Settings::get('custom_fields');}
    public static function render_custom(){
        echo '<div class="rgck-custom-fields rgck-fields">';$fields=WC()->checkout()->get_checkout_fields('order');$values=WC()->session?(array)WC()->session->get('rgck_fields',array()):array();
        foreach($fields as $key=>$field)if(strpos($key,'rgck_custom_')===0)woocommerce_form_field($key,$field,$values[$key]??WC()->checkout()->get_value($key));echo '</div>';
    }
    public static function fields($fields){
        if(!RGCK_Settings::get('enabled'))return $fields;
        foreach((array)RGCK_Settings::get('fields') as $key=>$rule)foreach($fields as $group=>&$rows){
            if(!isset($rows[$key]))continue;
            // A presentation setting cannot silently remove a field WC or another extension requires.
            if(!empty($rule['hidden'])&&empty($rows[$key]['required'])){unset($rows[$key]);continue;}
            if($rule['label']!=='')$rows[$key]['label']=$rule['label'];
            $rows[$key]['placeholder']=$rule['placeholder'];$rows[$key]['priority']=$rule['priority'];
            $rows[$key]['class']=array_values(array_filter((array)($rows[$key]['class']??array()),static function($c){return !in_array($c,array('form-row-first','form-row-last','form-row-wide'),true);}));
            $rows[$key]['class'][]='form-row-'.$rule['width'];
        }unset($rows);
        foreach((array)self::custom() as $key=>$f){if(!self::applicable($f))continue;
            $options=array(''=>'Choose an option');foreach(preg_split('/\r?\n/',$f['options']) as $o)if($o!=='')$options[$o]=$o;
            $label=$f['label'];if($f['fee']>0)$label.=' (+'.($f['fee_type']==='percent'?$f['fee'].'%':wp_strip_all_tags(wc_price($f['fee']))).')';
            $fields['order']['rgck_custom_'.$key]=array('type'=>$f['type'],'label'=>$label,'required'=>$f['required'],'class'=>array('form-row-wide'),'priority'=>100,'options'=>$options,'maxlength'=>$f['max'],'custom_attributes'=>array('maxlength'=>$f['max']));
        }
        return $fields;
    }
    public static function values($data){$v=array();foreach((array)self::custom() as $k=>$f){if(!self::applicable($f))continue;$key='rgck_custom_'.$k;$raw=$data[$key]??'';$value=$f['type']==='textarea'?sanitize_textarea_field($raw):sanitize_text_field($raw);if($f['type']==='checkbox')$value=!empty($raw)?'1':'';if(in_array($f['type'],array('select','radio'),true)&&!in_array($value,preg_split('/\r?\n/',$f['options']),true))$value='';$v[$key]=$value;}return $v;}
    public static function session($posted){if(!RGCK_Settings::get('enabled')||!WC()->session)return;parse_str($posted,$data);WC()->session->set('rgck_fields',self::values($data));}
    public static function fees($cart){
        if(!RGCK_Settings::get('enabled')||!WC()->session)return;
        $v=(array)WC()->session->get('rgck_fields',array());
        // The final validated POST is authoritative if a shopper submits before an AJAX refresh.
        if(isset($_POST['woocommerce-process-checkout-nonce']))$v=self::values(wp_unslash($_POST));
        foreach((array)self::custom() as $key=>$f)if(self::applicable($f)&&!empty($v['rgck_custom_'.$key])&&$f['fee']>0){
            $amount=$f['fee_type']==='percent'?$cart->get_subtotal()*$f['fee']/100:$f['fee'];
            $cart->add_fee($f['label'],wc_format_decimal($amount),false);
        }
    }
    public static function validate($data,$errors){
        if(!RGCK_Settings::get('enabled'))return;$v=self::values($data);
        foreach((array)self::custom() as $key=>$f){if(!self::applicable($f))continue;$key='rgck_custom_'.$key;$value=$v[$key]??'';
            if($f['required']&&trim($value)==='')$errors->add($key,esc_html($f['label']).' is required.',array('id'=>$key));
            if(function_exists('mb_strlen')?mb_strlen($value)>$f['max']:strlen($value)>$f['max'])$errors->add($key.'_length',esc_html($f['label']).' is too long.',array('id'=>$key));
            if(in_array($f['type'],array('select','radio'),true)&&!empty($data[$key])&&$value==='')$errors->add($key.'_choice','Choose a valid '.esc_html($f['label']).'.',array('id'=>$key));
        }
    }
    public static function save($order,$data){if(!RGCK_Settings::get('enabled'))return;$v=self::values($data);$saved=array();foreach((array)self::custom() as $key=>$f){$value=$v['rgck_custom_'.$key]??'';if($value!=='')$saved[]=array('label'=>$f['label'],'value'=>$f['type']==='checkbox'?'Yes':$value);}if($saved)$order->update_meta_data('_rgck_checkout_fields',$saved);}
    public static function display($order){$fields=$order->get_meta('_rgck_checkout_fields');if(!is_array($fields)||!$fields)return;echo '<section class="rgck-order-fields"><h3>Additional order details</h3><dl>';foreach($fields as $f)echo '<dt>'.esc_html($f['label']).'</dt><dd>'.nl2br(esc_html($f['value'])).'</dd>';echo '</dl></section>';}
    public static function email($order,$admin,$plain,$email){if(!$plain){self::display($order);return;}foreach((array)$order->get_meta('_rgck_checkout_fields') as $f)echo esc_html($f['label']).': '.esc_html($f['value'])."\n";}
}

