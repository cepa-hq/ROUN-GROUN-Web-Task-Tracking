# Roun Groun Checkout Messages 1.0.0 — RG-75

Presentation companion for Checkout Studio 1.3.5 and Account Refinement's existing COD guard. No settings migration, order write, gateway change, dependency download or new front-end JS.

- Native payment HTML is buffered between its existing render hooks. A paragraph names products blocked by enabled WPFactory COD product rules, using RGA's final cart decision. Parent and variation IDs are supported. Whole-cart inclusion semantics are preserved. Names are stripped of tags and escaped. No paragraph when COD is rendered, the order is free, rules are disabled, or no product restriction applies.
- Native update_order_review payment fragments receive the same transformation. This makes removal/quantity changes authoritative. Native gateway controls, nonce, terms and submit remain unchanged.
- Only the new paragraph has CSS, using current checkout colour variables and font. No existing selectors/assets are overwritten.
- The checkout privacy filter explains Razorpay's handling of full card number, CVV, UPI PIN and banking passwords. It separately explains contact/delivery details. It does not promise that transaction status/reference data is absent. Registration, order-pay and result pages retain their existing copy.
- Removes only Woo's duplicate top coupon callback. The Checkout Studio summary coupon form continues to work.
- Captures hashes of native add-to-cart success HTML (maximum20, one-hour matching window) in the existing WC session. On checkout, removes only exactly matching success notices and clears the marker. Errors, stock/payment validation, login notices and coupon confirmations are retained; shop/cart messages are unchanged. No customer/product text is added to this session marker.

Future integration: RGA_Commerce_Guards remains the source of truth. If product eligibility rules move, update the explanatory name mapper in this companion. Do not weaken COD validation to match the UI. If Woo changes the payment container marker, fail silently and review the insertion; no template override is maintained. Checkout Studio must remain enabled for all effects. No admin UI is necessary.

Rollback: deactivate Roun Groun Checkout Messages. Existing plugins and all their settings are untouched. No data cleanup or order rollback required.
