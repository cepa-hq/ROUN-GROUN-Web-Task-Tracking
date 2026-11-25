# Roun Groun Feedback system 1.0.4 — RG71 / RG73

First-party WordPress plugin for the approved General feedback mk1 instrument. No SaaS feedback service, external font, chart library or frontend build is required. The existing Roun Groun native header remains the header owner. Only this plugin's CSS and JavaScript are added on survey/dashboard routes.

## Staff guide

WordPress → **Roun Groun Feedback system → General feedback mk1**. Links are above the dashboard. Copy the general link, or create an optional order/campaign link. No link is inserted into website navigation and this plugin sends no emails. Guests go to the existing customer My account sign-in page with a strictly allowlisted survey return URL. Customers already signed in need not enter credentials again. Order-specific links require ownership after login.

Every account may submit once per instrument version. Reopening shows the saved response receipt. The form keeps choices in the current page while retrying a failed save; it never shows success before durable storage confirms. Unsent choices clear on reload. Staff-capability submissions are always marked test and excluded from customer reports and fulfilment; choose Staff tests to inspect them.

Filter dates (site timezone), device, campaign, account/name/email/order, gift state and question/answer. Bars filter the entire report and entries to that answer. All aggregates and the paginated list use identical predicates. Open View answers to see choices, the user's latest orders, profile links and gift change history. Account/order screens still enforce their own WordPress/Woo permissions.

## Gift fulfilment contract

A completed customer submission creates one unique gift task, regardless of positive/negative answers. A physical order still processing is eligible; otherwise the task waits for the next delivery. A future processing-order hook can atomically claim one waiting task. Staff can link/relink an eligible owned order or mark a ready gift packed; packed tasks cannot be accidentally repeated. A read-only order sidebar shows linked gift tasks. **Warehouse staff must actually include the surprise gift** and check dispatch status; no gift SKU, stock, charge, shipping label, order item or payment is created/changed. Completed orders are not reopened and no separate shipment is promised. This is an operational packing queue, not an automated warehouse integration. Staff tests never create customer gifts. Gift contents remain unspecified.

## Storage and reliability

Four namespaced, InnoDB tables created additively through dbDelta: `rgfs_entries`, `rgfs_answers`, `rgfs_rewards`, `rgfs_audit` (actual WordPress prefix supplied at runtime). Entries reference native user/order IDs, never copy emails, bank details, credentials or IP addresses. `order_ids` snapshots up to25 recent owned order IDs; details show latest10 and link all account orders. Device is a supplied coarse UI hint, not fingerprinting. Timing uses a signed server-issued start, not a guarantee of human attention.

A unique `(form_slug, form_version, user_id)` index is the concurrency authority. Answers are unique per entry/question/option; one reward per entry. Response, answers, reward and initial audit commit in one transaction. Validation precedes writes. DB failure rolls back; retries recover the original ID. Postcommit extension hooks must be idempotent by entry ID. Indexed answer rows permit exact SQL aggregation and EXISTS cohort filters without deserialising every response. Lists are bounded20/page. Charts use latest60 active UTC dates. No unbounded public export or public result endpoint exists.

Native customer login/cookie identity, explicit REST CSRF nonce for writes, capability checks, prepared SQL, strict question/option/cardinality/branch validation, user-owned order validation and no-store responses/pages protect the boundary. Token binds user+instrument+version+start; expires24h. A soft per-account request limiter protects repeated invalid attempts; the database unique index remains the strict duplicate guard. Hosting/CDN must honour no-store and bypass `/feedback/*`, `?rgfs_form=*` and `/wp-json/rgfs/*`; avoid any whole-page caching of logged-in survey content. Unknown route slugs use normal404. No forced logout or custom credential storage.

## Reporting methods

Counts and respondent-based shares for every question and optional checkout branch. Multiple-choice shares may total over100%. Website feel:1–5 ordinal mean; findability/checkout:1–4 with easier=larger. Checkout did-not-try excluded from average and positive denominator, but retained in distributions. Positive=two easiest choices. Three-choice recommendation is not NPS. No causal/predictive claim, psychometric calibration or automatic sentiment model is invented.

95% Wilson score intervals for individual option shares and positive proportions; zero denominator=null, never0%. Reference: https://www.itl.nist.gov/div898/handbook/prc/section2/prc241.htm . Samples below30 flagged as early signals (UI heuristic, not a statistical validity threshold). Wilson addresses binomial sampling uncertainty, not self-selection bias or correlated/overlapping cohorts. Do not compare filtered groups as an experiment. Versioned instruments and raw selections support later cohort models without rewriting historical answers.

Official implementation references: https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/ ; https://developer.woocommerce.com/docs/features/orders/wc-get-orders/ . Native Woo APIs preserve HPOS compatibility. No algorithm can remove response bias or turn this three-choice question into NPS without changing the instrument.

## Extending safely

`includes/general-mk1.json` is the immutable approved8-question wording/UI. `Forms::all()` adds stable `oN` keys and the optional branch. `rgfs_forms` permits trusted plugin code to register future instruments; supply separate renderer/assets for new layouts. Never alter option meaning/order within an existing version. `rgfs_instruments` saves immutable per-version schemas/hash at first registration. Hash drift fails new submission until a new version is created. Current frontend is explicitly the approved mk1 eight-question renderer; do not blindly reuse its eight-count progression for different instruments.

`rgfs_response_saved($entry_id,$slug,$uid)` fires only after commit. Consumers must not throw or synchronously call fragile remote systems; queue durable outbox jobs keyed by entry ID for future email/warehouse systems. Future features should add versioned migrations, not truncate tables or replace user/order data. Every new mutation needs existing capability/nonce/ownership rules and audit comments. No activation seeds demo submissions or rewrites other plugins.

## Privacy, lifecycle and publishing

Native verified WordPress personal-data export includes answers/context; erasure removes user/order/campaign/device/timing links and cancels linked gift tasks, retaining de-identified aggregate choices. Audit actor IDs are scrubbed by the same verified eraser. No public erasure endpoint. Deactivation/uninstall do not drop tables; deliberate data destruction needs a separate reviewed workflow. This plugin adds suggested policy text to the native privacy tool, not a change to the published policy.

**Staging is the only deployment authorised by RG71.** Future production release must be files-only installation into the live site's own database; never copy staging entries/users/orders/tables/options wholesale. Numeric IDs are installation-specific. Preserve live feedback on every release, back up first, and exclude staff test rows from reports. All data stays until an authorised privacy/retention process acts; no unapproved automatic purge interval.

## Verification and rollback

Run `php tests/unit.php` and `php tests/transactions.php` for real validator/Wilson edge cases, lint PHP and both JavaScript files. Live checks should cover login return, no-store, invalid/unknown/exclusive/branch/order/nonce input, repeat response/reward identity, filtered denominators and staff test isolation. Never seed fake customer feedback. Deactivate only `roun-groun-feedback-system/roun-groun-feedback-system.php` to remove routes/menu/UI; retain tables and reactivation restores access. No other plugin/theme changes required.

## RG73 customer login correction

The original1.0.2guest gate incorrectly used `wp_login_url`, showing the WordPress/admin-style login.1.0.4uses the existing WooCommerce My account customer page and its existing native sign-in/register forms, including the existing Google sign-in integration. No credential handler is added or replaced. The duplicate survey brand/time miniheader was removed from `templates/form.php`; the main native website header remains.

`includes/customer-login.php` owns only return-address transport. Only registered, same-origin survey paths are accepted; query context is reduced to bounded order ID and campaign. A30minute signed Secure/HttpOnly/SameSite=Lax first-party cookie plus native form hidden fields preserves the destination through failed sign-in, registration and the existing Nextend final return hook. The cookie contains no credentials, account ID or answers, and is cleared when consumed. Native Woo success filters run only after native authentication/registration succeeds. Explicit unrelated social/admin destinations and ordinary website sign-in remain unchanged. Pending cookies are not injected into unrelated-page login dialogs. Account-page refresh after successful social/customer login resumes the survey. The survey's existing current-user/order ownership/CSRF checks remain authoritative after authentication.

Use `php tests/customer-login.php` for canonical destination, tampering/expiry, scope, native success/failure, social-return, plain-permalink and subdirectory checks. No external feedback/authentication service is added.
