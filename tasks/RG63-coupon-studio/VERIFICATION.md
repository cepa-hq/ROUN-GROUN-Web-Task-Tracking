# RG63 verification and deployment — 23 November 2026

## Scope and result

Roun Groun Coupon Studio 1.0.0 is installed and active on **testarea.roungroun.com only**. Existing Customer Studio, Checkout Studio, gateways, tracking scripts, theme and approved storefront designs were not edited. The existing published coupon was not changed. No external payment was made and no customer order was submitted by browser.

## Changes / rationale / code parts

1. New standalone plugin bootstrap: activate durable InnoDB tables, schedule bounded cleanup, declare HPOS and Blocks integration. No Flatsome dependency.
2. `includes/storage.php`: encrypted PII/cart attempt snapshots, keyed identities, atomic unique claims and idempotent paid-redemption ledger; prevent concurrent once-limit reuse without counting failed orders. Refuse missing-key recovery by regeneration if old tables exist. Explicit transaction-start failure is fail-closed. Keep code labels for historical known-coupon analytics, including archived coupons.
3. `includes/engine.php`: intercept existing cart/checkout coupon requests after nonce checks; log applied/rejected/incomplete attempts separately from paid uses. Native Woo validation, exact UTC schedules, symmetric stacking, account/email/IP guards. Reserve classic/Store API/order-pay before gateway. Paid callback/status consumption, failed/cancelled unpaid release, refund retention and late-success conflict handling. Original identity claim snapshots persist across coupon rule edits. Store API session snapshot deferred until Woo loads the correct token-owned cart; no anonymous private snapshot on an invalid nonce/token.
4. `includes/admin.php`: WooCommerce → Coupon Studio dashboard and native coupon-editor options, capability/nonces, escaped output/prepared queries, paginated detail table, retention settings. Responsive admin layout; no new frontend assets.
5. `includes/qa.php`: nonce/capability + exact staging hostname guard, disposable synthetic fixtures, no gateway calls, Woo emails/webhooks disabled during request, test orders/coupons moved to Trash, only own synthetic ledger rows cleaned.
6. README: operator instructions, schema, hooks, security boundaries, backups and selective migration requirements.

## Real staging checks

26 checks passed against actual staging Woo 11.3.1 and database:

- AES-GCM encryption round-trip and tamper rejection.
- Future opening, expired closing and exact exclusive closing boundary rejected; blank closing indefinite.
- Percentage/flat stack works; non-stackable rejected in either order; native individual use honored.
- Account-limited guest refused; email required before payment; alternate billing email cannot evade account-email identity.
- Same-order reservation retry idempotent; second-order claim refused; partial multi-identity transaction rolls back.
- Consumed claim survives release/refund semantics.
- Pending order has no successful redemption; native failed/cancelled unpaid statuses release reservations.
- Native payment completion and repeated callback create one successful redemption.
- COD processing not paid; COD completed consumes.
- Late success conflict retains prior claim and moves conflicting order on hold for review.
- Order retry retains original identity claims after coupon rule changes.
- Atomic attempt-rate limit enforced; no public analytics post type.

Browser integration checks:

- Created a disposable 1% QA coupon via native editor, enabled Studio/stacking/account guard and saved exact local opening/closing times; re-opened persisted values.
- Existing cart applied correctly (same one existing item/variation/quantity), discount arithmetic correct.
- Unknown entered code rejected; dashboard stores fingerprint, not unknown plaintext code.
- Checkout Studio applied the same code through native Woo coupon AJAX; source=checkout recorded.
- Dashboard customer/cart snapshot verified by boolean fields only: available email/account name, product/variation IDs, quantity, attributes, currency. Customer data excluded from Git.
- Trying codes without an order leaves successful redemption and reserved-order counters at zero.
- Changed QA opening time into future using native editor: checkout returned “This coupon is not open yet” and kept full total.
- QA browser coupon moved to Trash; original published coupon unchanged. Test discount removed; original cart item and quantity retained.

All PHP files linted with local PHP 8.4; plugin executed on staging PHP 8.2. No fatal errors in observed admin/cart/checkout output. Native editor is standard WordPress responsive UI; custom dashboard uses a two-column mobile statistics grid, single-column panels and a horizontally scrollable bounded activity table. No new storefront CSS/JS means no coupon-plugin layout changes in mobile cart/checkout.

## Important limits / follow-up

- A browser extension with permission to read a page can read entered coupon codes. Absolute invisibility to hackers/extensions cannot be promised. No public coupon-list API was added; native authorized manager/REST access remains.
- IP limits identify connections, not humans; VPNs/shared networks/new accounts/emails are identity limits. Trusted reverse proxies must normalize REMOTE_ADDR securely.
- Pending unpaid orders reserve an allowance, not a successful use. Cancel unpaid orders to free it. Never expire active external-payment reservations blindly.
- Late asynchronous payment after release cannot be undone by this plugin; conflict is explicitly held for manager review.
- No real Razorpay charge/refund or external gateway callback was triggered. Native Woo lifecycle was exercised with synthetic no-charge QA orders. Store API support follows inspected Woo hooks/adapters; no live Blocks layout replaced the current Checkout Studio page.
- Deployment to production is **not performed**. Preserve production orders/customers/claims and its key during any later migration. Move all rgco tables + secret + coupon/order metadata together for a full database restore. Never overwrite newer production redemption history with stale staging rows.
- Disable/remove the plugin only after withdrawing managed coupons, because deactivation preserves data but stops its custom enforcement.

Issue: https://github.com/cepa-hq/ROUN-GROUN-Web-Task-Tracking/issues/63
Branch: codex/coupon-studio-20261123

Final staging package SHA256: 7478bcc27bd19dd5a614563578375bdab5e6c5cbb4fa3f57fe46055c19543824 (21,170 bytes).
