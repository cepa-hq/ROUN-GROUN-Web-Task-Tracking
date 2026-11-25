# RG-75 — Checkout payment and notice clarity

Completed on staging https://testarea.roungroun.com/ on 25 November2026.

## Change / reason / code parts

Pre-edit coordination issue: https://github.com/cepa-hq/ROUN-GROUN-Web-Task-Tracking/issues/75 . A scope update was recorded before implementation: use a companion plugin rather than replacing existing Checkout Studio. Existing checkout assets and templates are preserved byte-for-byte because none were deployed or edited.

New active plugin: `roun-groun-checkout-messages/roun-groun-checkout-messages.php`, version1.0.0. Checkout Studio remains1.3.5. No other plugin source, site settings, COD rules or existing styling changed.

1. Product restrictions were enforced but not explained at Choose payment. `restricted_names`, `payment_html`, `begin_payment`, `end_payment`, `fragments` now add a quiet status paragraph immediately after the native gateway list. The actual enabled COD product rules and existing RGA guard supply eligibility. Parent/variation exclusions and whole-cart inclusions are respected. Only offending names are shown; variation name included. Displayed COD, free orders and disabled rules suppress the explanatory message. Native AJAX refresh regenerates it. All names are escaped. `style` adds a single selector for the new paragraph, current colours/font,13px text and wrapping. No JS or font download added.
2. `privacy` replaces only main checkout's standard privacy explanation. It says Razorpay processes online payments and Roun Groun does not collect/store full card numbers, CVV, UPI PIN or banking passwords. It separately explains contact/delivery data and links the existing privacy policy. It deliberately avoids claiming transaction status/reference records do not exist. Registration, account and order-pay/result pages are untouched.
3. `prepare` removes only Woo's duplicate top coupon toggle; existing summary coupon control remains. `remember_cart_notice` records bounded hashes of native cart-add HTML in WC session. Only exact matching success notices are removed on checkout, within1hour. Other success, error, stock, login and payment notices remain. Hash markers are consumed. Shop/cart confirmations are preserved.

No credentials, orders, payment status or customer account details are written by the plugin. Native payment/checkout validation remains authoritative.

## Verification

- PHP8.4 syntax lint PASS and PHP7.4 parser PASS; staging runs PHP8.2.36 / WooCommerce11.3.1.
- 27 standalone contract checks PASS against an unmodified snapshot of live `RGA_Commerce_Guards`: parent/variation/mixed-cart exclusion, inclusion intersection, inclusion plus exclusion, deduplication, escaping, payment list placement, native nonce/submit retention, duplicate prevention, disabled rules, free cart, endpoint scoping, native AJAX fragment refresh, exact notice cleanup and preservation of errors/other confirmations.
- Installed ZIP SHA256: `2f4f882c440db413a85bbf2b30301dc0ff1169800abc781f66ec9a194a7ee045`,4668bytes. Installer confirmed same hash; activation/readback succeeded.
- Read-only admin inspection confirmed active rules exclude all three camping-stove variations; product category/tag/title/shipping-class rules are disabled. No restriction settings were saved/changed.
- Live Buy now from tote product: quantity1; no redundant top coupon or native cart-add success message. Native summary coupon remains. COD available, no COD explanation for allowed product.
- Live mixed cart: tote + Matte White stove, quantity1each. COD absent, Razorpay retained. Message names only `Roun Groun Portable Butane Camping Stove - MATTE WHITE`. Privacy copy and link rendered correctly.
- Native Checkout Studio responsive preview widths375,768,1280px: message13px throughout, wraps to approx3/2/1lines, no horizontal overflow. Usable inner viewport widths358/751/1263px; message widths284/365/715px. Actual desktop payment step visually inspected.
- Live checkout removal of restricted stove: native AJAX returned COD, removed the explanation (zero matching paragraphs), retained tote. This verifies the current cart drives the message rather than stale cached eligibility.
- QA products removed; native checkout confirmed empty. No order submission, payment, coupon redemption or account details update performed. Temporary ship-to-different-address UI toggle restored before cleanup.
- Existing Checkout Studio1.3.5 and COD guard source remained read-only. Unrelated feedback/account/header/coupon work was not altered.

## Future maintenance / rollback

Deactivate only Roun Groun Checkout Messages to roll back. There are no migrations/settings to undo. The companion intentionally requires enabled Checkout Studio for UI effects and the existing RGA guard for named restrictions. If eligibility ownership or Woo's `place-order` container changes later, update the name mapper/insertion respectively. Do not weaken backend eligibility to match UI. The source and README contain implementation comments.

Resume: implementation and staging QA completed. Future work begins from issue75 and this source; no pending checkout change is needed for this request.
