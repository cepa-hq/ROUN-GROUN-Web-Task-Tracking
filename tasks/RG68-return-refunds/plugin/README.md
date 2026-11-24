# Roun Groun Request Centre 1.0.4

Staging-only deployment for RG-9. Coordination issue:
https://github.com/cepa-hq/ROUN-GROUN-Web-Task-Tracking/issues/9

## Pages and ownership
- /cancellation-request/ (WordPress page1517)
- /return-request/ (WordPress page1518)
Both use [rgr_request_centre]. The plugin supplies the page content template with get_header()/get_footer(), retaining the website's default header/footer. No Flatsome builder/shortcode/JS dependency. WooCommerce supplies order identity/data and the existing wc-return-warrranty plugin supplies the staff RMA inbox. Existing Account Refinement1.2.3 files are not modified.

## Customer workflow
1. Sign in. Only the exact order customer can see/use the order; an admin account does not bypass ownership on customer pages.
2. Select individual purchased order lines and positive integer quantities from remaining eligible units. Add a5–2000character reason and choose an enabled request type.
3. Review the exact selected products, quantities, request type and reason. Review creates only a short-lived private transient, not an RMA request.
4. Explicitly confirm the reviewed selection. The final POST repeats ownership, dispatch/status, item membership, remaining quantity, product-window and request-type checks using fresh order data.
5. Save a staff-review request and selected item mappings atomically. Confirmation is not an automatic order cancellation, stock adjustment, refund or payment action. Staff must fulfil approved item-level requests appropriately. This deliberately avoids cancelling an entire multi-product order when only one product was selected.

## Eligibility / sources of truth
Cancellation list: pending, processing, on-hold and failed. Completed, refunded and cancelled orders are excluded. Direct links still undergo the same policy. Positive dispatch/delivery evidence blocks cancellation and directs customers to returns.

Dispatch: _rgr_fulfilment_state; positive _wc_shipment_tracking_items; shipped/in-transit/out-for-delivery order status; completed/delivered status or verified delivery timestamp. Pending/on-hold/failed are treated as not dispatched unless positive evidence says otherwise. A processing order with unknown dispatch is blocked, not assumed unshipped. WooCommerce order editor includes a capability/nonce-protected fulfilment confirmation box. Staff must verify from actual fulfilment records. A PIN-code checker is not shipment evidence.

Courier integrations can implement filter rgr_fulfilment_state($stage,$order), returning unknown/unshipped/shipped/delivered from trusted server data. Do not trust query parameters or customer-submitted dispatch claims. If some items are shipped, cancellation is conservatively blocked for the order because reliable line-level shipment data is not currently available.

Returns: processing/in-transit and completed/delivered items may be requested. Read the PURCHASED line-item _wcrw_item_warranty and _wcrw_item_warranty_selected snapshots. No generic seven-day constant or current-product policy substitution. Included limited periods, purchased add-ons and lifetime policies are recognised. Missing policy/date data requires assistance. Explicitly nonreturnable and expired items have separate messages.

The installed RMA plugin starts its period at order completion. This implementation retains completion as the fallback clock; a verified _rgr_delivered_at Unix timestamp takes precedence. Period arithmetic uses store timezone and the purchased days/weeks/months/years duration. In-transit requests are permitted before that clock starts and reviewed before collection. Future required custom RMA fields beyond the standard request_reasons fail closed rather than silently bypassing a new business requirement; add a reviewed renderer/validator adapter before introducing such fields.

Remaining units: purchased quantity minus max(refunded units, completed-request units), minus active-request units. Rejected requests do not reserve units. Both cancellation and return requests reserve their selected units to avoid conflicting requests. No quantities greater than the purchased balance, fractions, negative/zero values, foreign line IDs or empty selections accepted.

## Security / persistence / incident documentation
INCIDENT RG-9: the previous WCRW cancellation GET immediately inserted a request for every order line, without a separate item-selection/confirmation screen. Its return POST reconstructed products from mismatched checkbox/hidden arrays and did not sufficiently bind ownership and quantities. Native WooCommerce cancellation GET could also cancel an order immediately. Do not restore those public write handlers while this flow is active.

At wp_loaded priority1 the frontend removes core cancel_order(priority20) and the existing RMA customer cancel/return handlers. Admin handlers remain registered. Old cancellation URLs, native cancel_order requests, old new-warranty-request endpoints and old RMA POSTs redirect303 to selection. No posted selections/nonces/action flags are forwarded. Standard WooCommerce/RMA account links are normalised to the new pages. The native Order again href/nonce and all payment protections are preserved. A frontend legacy create-function filter prevents alternate direct RMA submissions; this repository writes validated requests itself.

POST nonces bind user+order+flow. Review UUID payloads are stored server-side for15minutes. Final requests require that reviewed payload, matching user/order/mode, an unexpired token and explicit confirmation. Order item/product identities always come from WooCommerce, not hidden form product IDs. Outputs are escaped; reasons sanitised as plain textarea content.

MySQL per-order GET_LOCK serialises competing Request Centre submissions. Re-read the order and reserved quantities within that lock. A SHA256 token marker provides idempotency for retry/double-submit. Both existing RMA tables must be InnoDB; otherwise fail closed with assistance message. Header and item mappings are one checked transaction with rollback on error. Native RMA notification hooks run only AFTER durable commit; notification failures do not cause duplicate customer requests. Native status is new (staff review). Original order status/totals/stock are not modified.

Custom code that writes directly to RMA SQL tables or separately changes shipment data does not participate in this lock. Courier/webhook fulfilment races and compatibility with arbitrary third-party RMA extensions cannot be guaranteed. Integrators should coordinate locks and final dispatch checks. Existing saved requests are retained.

## Structure
- roun-groun-request-centre.php: registration, legacy routing, action URLs, CSRF/review orchestration, assets.
- includes/policy.php: single display/review/final eligibility and selection validator.
- includes/repository.php: RMA reservations, locked atomic selected-item commit, idempotency, isolated SQL QA.
- includes/admin.php: fulfilment confirmation, read-only/sample QA and responsive preview.
- templates/centre.php: accessible selection/review/success states, scoped markup and customer care links.
- templates/page.php: default website wrapper.
- assets/centre.css: scoped responsive brand styles, all native order-detail action styles.
- assets/centre.js: optional selection counter/busy label. All essential submission validation is server-side and works without JS.

No product photos, remote UI/icon libraries, animation bundles or new third-party services. Inline SVG icons; local cached Geist font reuses Customer Studio asset (Arial fallback). Current CSS/JS source is small; existing site/theme scripts still load through the standard header/footer. No claims of whole-site performance improvement are implied.

## Verification and safe previews
WooCommerce > Request centre checks (manage_woocommerce only): policy/nonce tests and SQL TEMPORARY-table tests. The SQL test clones the RMA table structures into connection-local temporary tables; production RMA rows are not touched, no notifications fire, and tables are dropped. Requires an owned eligible on-hold sample order and temporary-table permissions. It tests real commit logic, selected mapping, duplicate retry and rollback on item-write failure, and compares the real queue count before/after.

Sample cancellation/return previews use synthetic product labels, no customer data and a disabled submit button. Responsive inspector offers320/360/390/430/768/1024/1280px iframe widths and the real pages/templates. Live review testing must stop before final confirmation unless a specific disposable test request is authorised. Do not test by cancelling/refunding customer orders.

## Rollback / maintenance
Keep this plugin active while the new customer links/pages are in use. Simply deactivating it re-enables legacy public GET/POST handlers and old unsafe paths; rollback requires a reviewed safe replacement of those handlers first. Deactivation does not delete pages/requests. Do not edit vendor RMA/WooCommerce/Flatsome files. Remove published workflow pages only after all entry points point to a replacement. No production deployment was performed.


1.0.5 RG-28: document wrapper uses existing RGHC_Shell under native hosts; block-theme fallback included. Request forms, validation, ownership, nonces, policies, order/RMA mutations unchanged.
