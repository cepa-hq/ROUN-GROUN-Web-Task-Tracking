/* RG-77: contextual error wording only; adapted from Checkout Studio 1.3.5.
 * Native WooCommerce remains responsible for stock, options and totals. */
(($)=>{'use strict';
 const form=document.querySelector('form.rgck-checkout');if(!form)return;
 let pending=false,returnKey='';
 const notice=(controls,text,error=false)=>{
  controls.dataset.kind=error?'error':'status';
  if(error){
   const copies={
    'Choose a valid quantity.':'Enter a whole-number quantity of at least 1, then choose Save changes.',
    'Choose all product options.':'Choose an option such as colour or size for every product choice, then choose Save changes.',
    'That option is unavailable. Choose another option.':'The selected colour or size is unavailable. Choose another option, then choose Save changes.',
    'That quantity is unavailable.':'The requested quantity is unavailable. Reduce the quantity, then choose Save changes.',
    'There is not enough stock for this cart quantity.':'There is not enough stock for that quantity. Reduce the quantity, then choose Save changes.',
    'This cart change could not be applied.':'The store could not apply that change. Check the quantity and product options, then choose Save changes.',
    'That product option could not be added.':'The selected product option could not be added. Choose another colour or size, then choose Save changes.',
    'Cart editing is unavailable.':'Item editing is currently unavailable here. Use Back to cart to change your items.',
    'We could not update your order. Please try again.':'The item could not be updated because checkout could not connect to the store. Choose Save changes again.'
   };
   const name=controls.closest('tr')?.querySelector('.rgck-product-title')?.textContent.trim()||'this item';
   text='For “'+name+'” in “Order summary”: '+(copies[text]||text);
  }
  const feedback=controls.querySelector('.rgck-item-feedback');feedback.setAttribute('role',error?'alert':'status');feedback.textContent=text;
 };
 const sync=controls=>{const input=controls.querySelector('[data-quantity]');const value=Number(input.value);controls.querySelector('[data-qty-step="-1"]').disabled=value<=1;controls.querySelector('[data-qty-step="1"]').disabled=input.max!==''&&value>=Number(input.max);};
 const init=()=>form.querySelectorAll('.rgck-item-controls').forEach(sync);init();
 form.addEventListener('input',event=>{const controls=event.target.closest('.rgck-item-controls');if(controls)sync(controls);});
 form.addEventListener('click',event=>{
  const step=event.target.closest('[data-qty-step]');
  if(step){const controls=step.closest('.rgck-item-controls'),input=controls.querySelector('[data-quantity]');if(pending)return;const max=input.max?Number(input.max):Infinity;input.value=String(Math.min(max,Math.max(1,(Number(input.value)||1)+Number(step.dataset.qtyStep))));input.dispatchEvent(new Event('input',{bubbles:true}));return;}
  const action=event.target.closest('.rgck-edit-save,.rgck-edit-remove');if(!action||pending)return;
  const controls=action.closest('.rgck-item-controls'),input=controls.querySelector('[data-quantity]'),remove=action.classList.contains('rgck-edit-remove');
  if(!remove&&!input.checkValidity()){notice(controls,'Enter a whole quantity of at least 1.',true);input.focus();return;}
  const attributes={};controls.querySelectorAll('[data-attribute]:checked').forEach(i=>{attributes[i.dataset.attribute]=i.value;});
  pending=true;controls.setAttribute('aria-busy','true');notice(controls,remove?'Removing item…':'Checking options and updating your total…');
  const disabled=[...controls.querySelectorAll('button,input')].map(e=>[e,e.disabled]);disabled.forEach(([e])=>{e.disabled=true;});
  document.dispatchEvent(new CustomEvent('rgck:cart-busy',{detail:true}));
  $.post(rgck.ajax.replace('%%endpoint%%','rgck_cart'),{nonce:rgck.cartNonce,key:controls.dataset.cartKey,quantity:remove?0:input.value,attributes})
   .done(result=>{if(!result.success){notice(controls,result.data?.message||rgck.error,true);return;}if(result.data.empty){location.assign(result.data.cart_url);return;}returnKey=controls.dataset.cartKey;notice(controls,'Item saved. Updating totals…');$(document.body).trigger('update_checkout');})
   .fail(xhr=>notice(controls,xhr.responseJSON?.data?.message||rgck.error,true))
   .always(()=>{pending=false;controls.removeAttribute('aria-busy');disabled.forEach(([e,was])=>{e.disabled=was;});sync(controls);document.dispatchEvent(new CustomEvent('rgck:cart-busy',{detail:false}));});
 });
 $(document.body).on('updated_checkout.rgckEditor',()=>{init();if(returnKey){const control=[...form.querySelectorAll('[data-cart-key]')].find(e=>e.dataset.cartKey===returnKey);const summary=control?.closest('details')?.querySelector('summary');summary?.focus({preventScroll:true});const message=form.querySelector('.rgck-cart-message');if(message){message.dataset.kind='status';message.textContent='Your item and total have been updated.';}returnKey='';}});
})(jQuery);

