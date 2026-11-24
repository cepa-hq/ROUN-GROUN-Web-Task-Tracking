# RG71 — General feedback mk1 implementation

25 November 2026, India. Status: implemented and active on **staging only**. Plugin **Roun Groun Feedback system 1.0.2**. Production unchanged.

## Where it is

- Admin: https://testarea.roungroun.com/wp-admin/admin.php?page=rgfs-general-feedback-mk1
- Survey: https://testarea.roungroun.com/feedback/general-feedback-mk1/
- WordPress menu: Roun Groun Feedback system → General feedback mk1; Forms overview supports future instruments.
- Issue: https://github.com/cepa-hq/ROUN-GROUN-Web-Task-Tracking/issues/71

## Delivered

Approved HTML v3 integrated with native site font/header and preserved colourful questions, gift artwork/motion, desktop tracker clearance, thin mobile left rail and finite completion confetti. New scoped assets only. Removed preview data/sample reset; account name and owned order/product quantities now server-rendered. Submitted choices are sent to first-party WordPress REST storage and success requires a confirmed save. Guests reach the site's native /rg-login/ with safe survey return address; valid signed-in sessions proceed directly. No forced logout. Form remains unlinked from public website menus.

Admin links above dashboard include canonical link and optional owned-order/campaign link builder. Exact filters: dates in site timezone, saved instrument version, device, campaign, customer/name/email/order search, gift state, question/option cohort and customer-vs-staff-test data. Clickable distribution bars change the same cohort for every chart and the 20-row paginated entry list. Nine distribution blocks: 8 required questions plus optional checkout follow-up. Website 1–5 and find/checkout1–4 descriptive means, positive rates, excluded-checkout count, Wilson 95% answer-share ranges, sample counts and latest 60 active UTC dates. No invented NPS or predictive model. Native account/profile/order links, latest customer orders, selected answer detail and audit history for every entry.

Additive indexed InnoDB response/answer/reward/audit tables; fixed instrument snapshots/hash; server-side validation for required/max/exclusive/conditional choices; CSRF, identity, owned-order checks, expiry, rate limiting and no-store boundaries. Unique account/form/version and reward/entry keys, atomic transaction and idempotent retry. Native WP privacy export and verified erasure callbacks. No published policy wording changed.

Gift queue: waiting for order / ready to pack / packed, with ownership/status checks and audited staff actions. A future processing physical order can claim a waiting gift task. Order sidebar shows linked gifts. Staff tests cannot trigger packing. Fulfilment staff must physically add the surprise gift and mark packed; no product/stock/order-total/payment or shipping-label automation. No email is sent by this release. Reward is independent of answer sentiment.

## Actual verification

- PHP8.4 syntax lint all 11 PHP files; PHP8.2 syntax parser compatible (10 initial files, 11 final files).
- JavaScript parser checks both assets pass.
- 30 production-code validator/Wilson assertions pass (unknown/missing/type/cardinality/exclusive/branch options,zero denominator,edge proportions/reference interval).
- 19 transaction fault-injection assertions pass against actual Store::submit: each of 5 write stages fails→rollback with no partial rows; successful save produces one reward; duplicate retry performs no inserts. These are injected adapter tests, not a DB-load benchmark.
- Live staff test completed through approved form: entry 1, 8 answer rows, 1 test-reward row, 1 audit. Actual order association belongs to the authenticated staff account; no native order/customer record changed. Reopening restores receipt, repeat POST returns same ID 1 / already_saved=true. All upgrades1.0.0→1.0.1→1.0.2 preserve these counts. Customer report remains 0.
- Missingnonce→403; unknown option/exclusive collision/too many choices/hidden checkout branch→422; foreign/nonexistent order→403; packing staff-test reward→409. Invalid submissions create no rows.
- Guest HTTP survey→functioning native loginform/password field and correct redirect_to, with no staff/customer survey data in response. Guest dashboard API→403. Fresh credential login roundtrip and a separate real customer submission were not performed; existing authenticated session and actual guest redirect were exercised.
- Report friend=yes cohort → 0; friend=notyet → 1. Device phone → 0 / desktop → 1;25 Nov local date → 1;SQL-like search→0. Checkout did-not-try→1 respondent / 100% share, 0 rated / 1 excluded,average and positive=null. Optional followup unfilled→0 respondents / null shares. Bars update question/option filters; Clearfilters restores customer report. Link builder yields correct order/campaign URL. Entry detail resolves real native user/orders/history. Staff test isolated and marked throughout.
- Actual same-origin survey iframe viewports320/390/768/1280: document scroll width equals client width (305/375/753/1265 after scrollbars),0 overflowing answer tiles, 14px answer font; mobile/tablet 7px fixed left rail,desktop 184px sticky tracker. Rail appears when survey is in view. Existing header remains outside survey CSS. Saved receipt restores on every frame. No screenshot export/customer data artifact retained.
- Final sitehealth unchanged 17 good / 4 recommended / 0 critical. PHP errorlog remains 409932 bytes, modified 21 Nov 2026 15:27:29 UTC; no new PHP log entries. Existing unrelated admin license notice was not altered.

## Scope / files

Only NEW `wp-content/plugins/roun-groun-feedback-system/`: bootstrap; includes/forms.php, general-mk1.json, store.php, analytics.php, frontend.php, admin.php, privacy.php; assets/form.css/form.js/admin.css/admin.js; templates/page.php/form.php; README and offline tests. Hidden capability-protected admin layout harness is read-only and uses actual survey pages. No existing plugin/theme/menu/footer/tracking/payment/refund/coupon source changed.

Release ZIP: 48126 bytes; SHA256 c202ad518b09eb607b5befb9666d69ea5019c083b2cb731aaa952c8f82d19e68. Baseline approved HTML remains unchanged. Source/verification/checkpoints committed under tasks/RG71-feedback-system on isolated coordination branch, not website-source main. Source code repository is absent under owner-established MCP plugin workflow.

## Recovery / future work

Deactivate only this plugin to remove its UI/routes; response data remains. Reinstall saved 1.0.2 ZIP/reactivate to restore. Do not copy staging databases or user/order IDs to production; later live release is files-only against native live data. Staff tests must never be counted as customer feedback. New forms require new registry instrument and renderer where question layout/count differs; do not mutate old option meaning/order/version. Keep capability/nonce/ownership/audit/transaction boundaries and use idempotent outbox integrations for later warehouse/email features. See plugin README and source comments.

Not performed: production publication, real customer survey/packing, automatic email delivery, external warehouse integration, real privacy erasure/account deletion or concurrency/load benchmark. Gift transitions validated through server guards and transaction tests without changing actual order fulfilment.
