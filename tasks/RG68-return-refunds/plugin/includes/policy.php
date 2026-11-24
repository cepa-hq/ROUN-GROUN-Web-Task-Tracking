<?php
defined('ABSPATH') || exit;
/** RG-9: one server policy for page display, review and final POST. No UI-only eligibility. */
final class RGR_Policy {
    public static function required_fields_supported(){
        // Reason is the installed RMA form's standard request_reasons field.
        // Never silently bypass future required custom fields added by staff.
        if(!function_exists('wcrw_get_form_fields_data'))return false;
        foreach(wcrw_get_form_fields_data()as $field)if(($field['type']??'')!=='html'&&!empty($field['settings']['required'])&&($field['name']??'')!=='request_reasons')return false;
        return true;
    }
    public static function stage($order){
        $saved=$order->get_meta('_rgr_fulfilment_state',true);
        $stage=in_array($saved,array('unshipped','shipped','delivered'),true)?$saved:'unknown';
        // Positive tracking evidence wins over an old manual "unshipped" flag.
        $tracking=$order->get_meta('_wc_shipment_tracking_items',true);
        if(is_array($tracking)&&$tracking)$stage='shipped';
        if(in_array($order->get_status(),array('shipped','in-transit','out-for-delivery'),true))$stage='shipped';
        if($order->has_status(array('completed','delivered'))||$order->get_meta('_rgr_delivered_at',true))$stage='delivered';
        if($stage==='unknown'&&$order->has_status(array('pending','on-hold','failed')))$stage='unshipped';
        /** Courier adapter must return unknown/unshipped/shipped/delivered from TRUSTED server data. */
        $stage=apply_filters('rgr_fulfilment_state',$stage,$order);
        return in_array($stage,array('unshipped','shipped','delivered'),true)?$stage:'unknown';
    }
    public static function context($order,$mode,$reserved=array()){
        $stage=self::stage($order);$status=$order->get_status();
        $ctx=array('id'=>$order->get_id(),'owner'=>(int)$order->get_customer_id(),'mode'=>$mode,'status'=>$status,'stage'=>$stage,'blocked'=>'','items'=>array());
        if($mode==='cancel'){
            if(in_array($stage,array('shipped','delivered'),true))$ctx['blocked']='Shipped orders cannot be cancelled. Please use the return option instead.';
            elseif(!in_array($status,array('pending','processing','on-hold','failed'),true))$ctx['blocked']='This order is no longer eligible for cancellation.';
            elseif($stage==='unknown')$ctx['blocked']='We cannot confirm whether this order has shipped. Please contact customer care before requesting cancellation.';
        }elseif(in_array($status,array('cancelled','refunded','failed','pending','checkout-draft','trash'),true))$ctx['blocked']='This order is not eligible for a return request. Contact customer care if you need assistance.';
        elseif(!in_array($status,array('processing','completed','shipped','in-transit','out-for-delivery','delivered'),true)&&!in_array($stage,array('shipped','delivered'),true))$ctx['blocked']='This order is not on its way yet. Use cancellation for an unshipped order, or contact customer care.';
        if($mode==='return'&&!self::required_fields_supported())$ctx['blocked']='Additional return details need verification. Please contact customer care to complete your request.';
        foreach($order->get_items() as $item){
            $id=$item->get_id();$qty=(int)$item->get_quantity();$r=$reserved[$id]??array('active'=>0,'closed'=>0);
            $refunded=abs((float)$order->get_qty_refunded_for_item($id));
            $remaining=max(0,$qty-max($refunded,(int)$r['closed'])-(int)$r['active']);
            $row=array('id'=>$id,'product_id'=>$item->get_product_id(),'name'=>$item->get_name(),'quantity'=>$qty,'remaining'=>(int)$remaining,'code'=>'eligible','message'=>'Available to request','window'=>'');
            if($remaining<1){$row['code']='used';$row['message']='This quantity is already refunded or included in another request.';}
            if($mode==='return'&&$remaining>0){
                // Use the PURCHASED line snapshot, not a generic seven-day constant.
                $w=$item->get_meta('_wcrw_item_warranty',true);
                if(!is_array($w)||!$w){$row['code']='unknown';$row['message']='Return eligibility could not be confirmed. Please contact customer care.';}
                elseif(($w['type']??'')==='no_warranty'){$row['code']='nonreturnable';$row['message']='This product cannot be returned under its product-specific return policy.';}
                else{
                    if(($w['type']??'')==='addon_warranty'){
                        $selected=$item->get_meta('_wcrw_item_warranty_selected',true);$addon=$w['addon_settings'][$selected]??array();
                        $w=array('type'=>'included_warranty','length'=>'limited','length_value'=>$addon['length']??0,'length_duration'=>$addon['duration']??'');
                    }
                    $n=(int)($w['length_value']??0);$unit=$w['length_duration']??'';
                    if(($w['length']??'')==='lifetime')$row['window']='No fixed expiry';
                    elseif(($w['type']??'')!=='included_warranty'||$n<1||!in_array($unit,array('days','weeks','months','years'),true)){$row['code']='nonreturnable';$row['message']='This product does not have a valid return window. Please contact customer care.';}
                    else{
                        // Installed WCRW policy uses completion date as its clock.
                        // A real delivered timestamp overrides that proxy when supplied.
                        $base=(int)$order->get_meta('_rgr_delivered_at',true);
                        if(!$base&&$order->get_date_completed())$base=$order->get_date_completed()->getTimestamp();
                        if($base){$d=(new DateTimeImmutable('@'.$base))->setTimezone(wp_timezone());$expiry=$d->modify('+'.$n.' '.$unit)->getTimestamp();$row['window']='Return window ends '.wp_date(get_option('date_format').' · '.get_option('time_format'),$expiry);if(time()>=$expiry){$row['code']='expired';$row['message']='The return window for this product has ended.';}}
                        elseif($status==='completed'||$stage==='delivered'){$row['code']='unknown';$row['message']='The delivery date needs verification. Please contact customer care.';}
                        else $row['window']=$n.' '.$unit.' from recorded completion; you can request a return while it is on its way.';
                    }
                }
            }
            if($ctx['blocked']){$row['code']='blocked';$row['message']=$ctx['blocked'];}
            $ctx['items'][$id]=$row;
        }
        return $ctx;
    }
    public static function validate($ctx,$user,$selection,$reason,$confirmed){
        if(!$user||$ctx['owner']!==$user)return new WP_Error('ownership','This order is not available in your account.');
        if($ctx['blocked'])return new WP_Error('stage',$ctx['blocked']);
        if(!$confirmed)return new WP_Error('confirmation','Please confirm the selected products and quantities.');
        if(!is_string($reason)||mb_strlen(trim($reason))<5||mb_strlen($reason)>2000)return new WP_Error('reason','Add a reason between 5 and 2,000 characters.');
        if(!is_array($selection)||!$selection||count($selection)>100)return new WP_Error('selection','Select at least one eligible product.');
        $clean=array();
        foreach($selection as $id=>$qty){
            if(!ctype_digit((string)$id)||!isset($ctx['items'][(int)$id]))return new WP_Error('item','A selected product does not belong to this order.');
            $row=$ctx['items'][(int)$id];
            if(!is_scalar($qty)||!preg_match('/^[1-9][0-9]*$/',(string)$qty)||(int)$qty>$row['remaining'])return new WP_Error('quantity','Choose a valid quantity from the remaining units.');
            if($row['code']!=='eligible')return new WP_Error($row['code'],$row['message']);
            $clean[(int)$id]=(int)$qty;
        }
        ksort($clean);return $clean;
    }
}
