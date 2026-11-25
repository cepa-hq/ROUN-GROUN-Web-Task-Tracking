# RG-77 — Checkout errors and opt-in delivery address

Staging completed 25 November 2026. Existing Roun Groun Checkout Messages upgraded in place1.0.0→1.1.1, same folder and active state. No new plugin was added or activated. Pre-change issue77 and branch agent/codex-checkout-clarity/rg-77 preserve the plan and checkpoints. Staging only; production unchanged.

## Change / reason / code parts

- `includes/clarity.php`: preserves native validation decisions/error codes/metadata, rewrites known field required/format/choice/length and terms/payment/delivery errors with field/section and corrective action. Account fields identify Create an account. Useful carrier explanations and unknown gateway errors remain unchanged. Default-off shipping checkbox affects main checkout only, respects explicit POST choice, never clears shipping values. Native template locator and enqueue integration is scoped to checkout, excluding order-pay/results.
- `templates/form-shipping.php`: compact Delivery address card with clear full-row checkbox/helper text, native address fields/hooks and a separate Additional order details card. No form processing/security/required-field/shipping eligibility changed.
- `assets/contextual-checkout.js`: current Checkout Studio1.3.5 UI controller adapted to remove ambiguous Please check Phone wording, use actionable summary title and field links, connect inline errors with aria-describedby, preserve unknown backend error text and provide clear coupon/delivery/network fallbacks. Hidden shipping fields are skipped when the option is off. Single-page browser validation now stops invalid submission rather than falling through; native backend validation remains authoritative.
- `assets/contextual-editor.js`: current editor controller adapted only for contextual wording. Error messages name product and Order summary and explain quantity/option/connection corrections.
- `assets/clarity.css`: scoped lightweight cards/checkbox/error links, existing colours/font, no new imagery/font/framework/animation.
- Existing bootstrap: version1.1.1 and require for clarity module. Original RG75 implementation body is unchanged, confirmed by exact comparison.

The existing `rgck` and `rgck-editor` script registrations point at adapted controllers while retaining localization, dependencies and single ownership. This avoids overwriting Checkout Studio or unrelated agents' assets. Maintenance contract: reconcile these adapted files when Checkout Studio controllers are upgraded. No duplicate controllers are enqueued.

## Validation and observed result

- 21 PHP contract checks and 9 browser wording/submit contract checks PASS. Includes reported missing shipping phone, distinct contact/address sections, default-off/explicit opt-in/out, disabled/unrelated endpoint scoping, preservation of error codes/data/order/unknown provider reasons, custom-field limits and country-specific PIN guidance.
- PHP7.4 parser and PHP8.4 lint, both JS syntax checks PASS. Staging remains PHP8.2.36/WooCommerce11.3.1/CheckoutStudio1.3.5.
- Same-plugin upgrade confirmed replaced=true, active=true, version1.1.1. Final ZIP 17155 bytes, SHA256 `6626870e63af072d49b55dcfd2715cd614b88f74ad3f22f98bbc1b6cccc7b482`.
- Actual browser initial checkout checkbox unchecked; after load aria-expanded=false, address fields hidden. Native totals refresh retained an explicit opt-in. Turning off hides shipping errors without clearing address fields.
- Actual missing shipping phone: summary and inline text both read `Enter the recipient’s phone number in “Ship to a different address”.` Clicking summary link focused shipping_phone. Invalid letters produced `Enter a valid phone number in “Ship to a different address”. Check the digits and country code.` Test text restored to original blank.
- With separate address off, valid existing contact/address details advanced through Delivery options and Review & pay. Original product-specific COD restriction and accurate Razorpay wording still rendered.
- Native Checkout Studio responsive iframe widths 375/768/1280px: new card widths 318/399/767px, no horizontal overflow. Phone checkbox target approx90px tall including helper. Delivery card and notes card do not overlap. Phone error summary width318px with no overflow. Desktop screenshot visually inspected.
- Order-summary quantity0 validation produced `For “Roun Groun Semi-Transparent PVC Tote Bag, White” in “Order summary”: Enter a whole quantity of at least 1.` Validation blocked the edit before network/save. Quantity control restored to1.
- Only the tote added for QA was removed. The pre-existing Matte White stove quantity1 and existing coupon were preserved. No order submitted, no payment launched, no coupon redemption or account profile save performed. Terms were not accepted.

## Scope / limits / rollback

Only the existing Checkout Messages plugin was updated. No Checkout Studio/account/header/theme/vendor/COD-rule/payment/stock source/settings changed. No new database tables, migrations or external services. Tests exercised browser validation and server message contracts; no real checkout/order/payment submission was used to test financial processing. Unknown future provider messages keep their original reason; they are not guessed at.

Rollback only this follow-up: replace the same plugin with retained 1.0.0 ZIP (also included in this checkpoint). Deactivating the plugin removes earlier RG75 behavior too, so the 1.0.0 rollback is preferred. Source, release, original controller snapshots, tests and this report are saved on the task branch. Work is complete; issue remains open for owner review.
