# Roun Groun Coupon Studio 1.0.0

Native WooCommerce integration, independent of Flatsome and CheckoutWC. Installed on staging only for RG63. No storefront assets. Public source contains no coupon data, customer records, secrets or site exports.

## Operator guide

WooCommerce → Coupon Studio → Create coupon (or edit any existing Woo coupon). Set its code, description and General discount type: Percentage discount or Fixed cart discount. Standard Woo product/category/excluded-sale/minimum-spend rules remain available. In Usage restriction, enable **Manage with Coupon Studio** and choose stacking, once-per-IP/account/email, opening and closing date/time. Store timezone applies, with second-accurate server validation; picker resolution is one minute. Blank opening means now; blank closing is permanent/indefinite. To pause availability, use draft status in native editor. Coupons are never automatically published or distributed to Google.

Every member of a stack must allow stacking. Applying a non-stackable coupon rejects the combination instead of silently deleting another coupon. Native individual-use coupons also prevent stacking. Saving a managed coupon clears native date-only expiry, individual-use and per-user usage count; Studio replaces those with exact schedule, stacking and successful-checkout rules. Native optional global usage limit remains a separate Woo limit and may include Woo pending reservations; leave it blank if only Studio successful-use semantics are desired.

Once-per-account requires sign-in. Once-per-email checks billing email plus the account email when different. Guest email can be unavailable during cart application; final identity is required/validated before payment. Selecting multiple identity guards rejects reuse if **any** selected identity was used. IP is the server peer (`REMOTE_ADDR`), canonicalized and HMACed; shared NAT users share an allowance, VPN/network/account/email changes cannot be proven to be the same human. Configure the server to supply the real trusted-proxy client address; never trust arbitrary X-Forwarded-For. A reviewed server-side `rgco_client_ip` filter is available for verified proxy deployments.

## Attempts versus redemption

An explicit entered/apply/check request in the existing cart, native classic coupon AJAX/form or Store API coupon route logs a snapshot **before** Woo validates the coupon, including invalid/rejected and abandoned checkout attempts. Coupon recalculations do not create events. Snapshot: UTC time (displayed in store timezone), coupon ID or HMAC of unknown code, outcome, source, user ID, available account name/email, cart product/variation IDs, names, attributes, quantities, line values, currency and cart total. Unknown code text is never stored. No raw IP/address/payment details are stored in attempt analytics. Available guest emails come from the Woo customer session/native validated request. No attempt can invent an unavailable email.

Allowed explicit requests are rate-limited to 20 per minute per server-observed connection. Rejected excess requests do not create unbounded PII snapshots. Nonces/session validation precedes logging. This is application-level rate limiting, not a replacement for server/WAF protection. Snapshots are AES-256-GCM encrypted; identity hashes are keyed HMAC-SHA256. Store managers only can inspect details. No coupon-list/analytics REST endpoint, public JS payload or frontend script is introduced. Extensions with page access can read codes entered by their user: no website can make those invisible. Store owners/admins/authorized Woo REST API credentials can access native coupon data as before.

## Strict limits and payment lifecycle

Four InnoDB tables (`<prefix>rgco_attempts`, `rgco_claims`, `rgco_redemptions`, `rgco_rate`). Claims have unique primary key `(coupon_id, scope, identity_hash)`; sorted atomic transactions lock all identities and roll back partial claims on conflict. Reservation retry is idempotent for the same order. There is no unsafe read-then-write redemption counter.

- Classic checkout: `woocommerce_checkout_order_created`, before gateway payment.
- Store API/Blocks: `woocommerce_store_api_checkout_order_processed`, before gateway payment.
- Existing-order payment retry: `woocommerce_before_pay_action`, after Woo order ownership/nonce checks, before gateway payment.
- Pending/on-hold and COD processing reserve the allowance but do not consume it. Cancel an unpaid pending order to release it. Do not expire live reservations merely by wall-clock time: a still-valid external payment could arrive late.
- `woocommerce_payment_complete` and completed order status consume the claim. Non-COD processing with a paid timestamp also does. Repeated callbacks create one distinct coupon/order redemption.
- Failed/cancelled **unpaid** orders release reservations; already consumed claims survive all refunds/cancellation changes because a successful checkout occurred.
- Delayed external success after a failed/cancelled order released a reservation: if another order has claimed that identity, preserve the original claim, add a private order note and move the newly paid order on hold for manager review. A plugin cannot undo an external payment already captured. This conflict is never silently allowed to overwrite a claim.
- Administrative/third-party gateways that bypass Woo checkout/order-pay lifecycle are outside this integration; they must call the reservation guard before payment. Existing Razorpay uses native `payment_complete` (source inspected).

Changing a coupon code keeps its native coupon ID and claims. Deleting/recreating a coupon creates a new promotion/ID; do not do that to reset a limited promotion accidentally. Existing orders/coupons before installation are not retroactively mined for private IP/identity data. New managed coupons record future successful checkouts. Historical native limits remain unchanged on unmanaged coupons.

## Retention, backup and migration

Attempt retention defaults to 180 days (admin 7–730); daily bounded cleanup removes old snapshots/rate buckets. Successful claims and redemptions remain durable, like order eligibility history. No uninstall routine deletes data. Deactivation stops enforcement and scheduled cleanup but preserves all records; restore the plugin before offering managed coupons again. Back up/restore all four tables, `rgco_secret` option, coupon metadata (`_rgco_*`) and order metadata (`_rgco_*`) **together**. Do not replace a production customer/order/claim database with an older staging copy; selective code/settings deployment must preserve production order/customer/claim tables and the same production key. The secret is non-autoloaded and must not be logged or committed. Losing it breaks encrypted snapshots and stable identity matching; restore it from a private backup, never auto-regenerate it for an existing table.

## Staging QA

Restricted by capability, nonce and exact staging hostname. Run from Coupon Studio. Creates disposable native QA coupons/orders only, disables Woo email/webhook delivery for the test request, never calls a payment gateway, checks encryption/schedules/stacking/database rollback/identity ownership/native paid/failed/cancelled/COD transitions. Synthetic ledger records are removed and test orders/coupons moved to Trash. Existing customer carts/orders are untouched. Test results contain no PII. Runtime tests exercise real staging Woo/DB; they are not a live external Razorpay charge.

## File responsibilities

- `rg-coupon-studio.php`: bootstrap, activation/cron, HPOS/Blocks declarations.
- `includes/storage.php`: schema, encryption, bounded retention/rate limit, atomic identity ledger.
- `includes/engine.php`: native coupon hooks, attempt adapters, schedule/stack/identity validation, checkout/order-pay/payment lifecycle.
- `includes/admin.php`: restricted responsive dashboard, native coupon fields, validated admin saves.
- `includes/qa.php`: isolated staging-only verification.
