# RG73 — customer-facing survey sign-in and header correction

25 November 2026 (India). Staging only. Active release: Roun Groun Feedback system **1.0.4**.

## Problem and fix

The survey guest gate used `wp_login_url($back)`, which takes customers to the WordPress/admin-style sign-in screen. That was the wrong entry point for this website. The corrected gate uses the existing customer **My account** sign-in page and its existing RGCS/RGA native WooCommerce login and registration forms. No new credential handler, account creation or authentication bypass was added.

The account form posts to its clean My account URL, so a GET return address alone would be lost on submission or error. New `includes/customer-login.php` transports a strictly canonical survey destination through native form hidden fields and a signed, 30-minute, first-party return cookie (Secure over HTTPS, HttpOnly, SameSite=Lax). Native successful-login/registration hooks return to the survey; installed Nextend final provider-return hooks retain the customer journey. No global WordPress login/authenticate filter is replaced. Ordinary account login and explicit unrelated social/admin destinations keep their normal destination. Failed authentication cannot advance into the survey.

The return allowlist accepts only registered survey paths on the exact current origin, plus bounded order ID/campaign parameters. It rejects external/protocol-relative/admin/unknown paths, arrays, changed signatures and expired context. A live test caught a nested-URL encoding issue in intermediate1.0.3: its campaign became an outer login query parameter. Final1.0.4explicitly encodes the nested URL before adding it to the login URL; a parse/decode roundtrip regression test covers it. Existing survey identity/order ownership checks still run after sign-in.

Removed the complete `.rg-feedback .top` pseudoheader (small brand/tagline/YOUR FEEDBACK/About2minutes) from `templates/form.php`. Main website header and survey hero/gift/questions/progress/footer remain unchanged.

## Exact code scope

Only the existing `roun-groun-feedback-system` plugin:

- `includes/customer-login.php`: new scoped destination transport and native customer-login integration.
- `includes/frontend.php`: guest gate delegates to customer login.
- `templates/form.php`: removes miniheader markup.
- `roun-groun-feedback-system.php`: loads/boots helper and version1.0.4.
- README and targeted offline tests describe behaviour and extension boundaries.

No existing customer/account/header/social plugin source, database schema, instrument definition, response/order/customer/gift record or production site changed. Baseline release1.0.2retained for rollback. Use files-only updates; do not replace databases.

## Verification

- PHP lint and PHP8.2syntax parser pass all13PHPfiles.
-35new production-helper checks pass: canonical destination, nested order/campaign query roundtrip, reject malicious inputs, signed-cookie tampering/expiry, scoped form fields, successful/failed native login paths, consumed context, ordinary login untouched, registration/social return, explicit admin destination, plain permalinks and subdirectory installations.
-49existing validator/statistics/transaction assertions still pass (84total).
- Browser confirms main native header exists; miniheader count0and time label absent. Actual same-origin widths320/390/768/1280have no document or answer-tile overflow; native header present and pseudoheader absent in all four. Saved staff-test receipt still restores.
- Actual guest general-link HTTP flow reaches website `/my-account/`, not WordPress login; correct native login form, nonce and both return fields; no-store headers and Secure/HttpOnly/SameSite=Lax return cookie confirmed.
- Empty username follows native validation and remains on customer login with return intact; no real credentials were entered. Ordinary My account login has no survey-return fields; external return input creates neither return fields nor return cookie.
- Final order/campaign live HTTP roundtrip: see `http-results.json` saved with this report.
- Diagnostics1.0.4: storage ready; existing1staff-test entry/8answer rows/1test reward/1audit and identical instrument hash. PHP error log remains409932bytes, modified21Nov2026at15:27:29UTC, with no new log entry.

A new password/Google credential sign-in was not performed. Native successful-authentication hook behaviour was exercised against the actual helper in offline tests; actual customer login markup, return transport, native validation, existing authenticated survey and guest redirect were checked live. No real account, order or survey was created/changed for testing.

## Checkpoints and release

Issue73and isolated branch `agent/codex-feedback-login/rg-73-customer-signin`, based on prior source328036af280de46b8b8f5eda7defdeff051aaa59. Pre-deploy checkpointff5d1cebf1deabfa4d25e4ec667cd87d56a2cd77. Stacked review against the prior feedback branch keeps the follow-up review scoped. ZIP1.0.4:51660bytes, SHA256d9acbdaa37a72252a959ee5a46a5ffdf5acc8662b4f11ac55eb7d02145100961. Source/tests/checkpoints/report/installable ZIP retained in the task branch. No merge/production deployment performed. Rollback reinstall own1.0.2ZIP or deactivate own feedback plugin, retaining data.
