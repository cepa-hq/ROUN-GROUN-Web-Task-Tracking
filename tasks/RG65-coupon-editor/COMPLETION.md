# RG65 — Unified Coupon Studio editor

24 November 2026, India. Staging only: https://testarea.roungroun.com/

## Problem and result

Create coupon opened the native WooCommerce editor, separating discount controls from Studio scheduling/stacking/identity settings. Coupon Studio 1.1.0 now owns a single create/edit form backed by the same WC_Coupon record. Existing supported native Add/Edit links route to it. An explicit advanced WooCommerce editor remains available for product/category/spend/email/global-use restrictions and extension coupon types.

## Changed code and rationale

- `plugin/rg-coupon-studio/includes/editor.php` (under the existing RG63 source directory): unified create/edit form, paginated coupon list, native navigation adapter, validation-before-write, native CRUD persistence, capabilities/nonces, concurrent-edit revision, per-manager failed-form recovery. Native published-only code lookup is supplemented with a query covering draft/pending/private/future coupons to prevent duplicate paused codes. Legacy native per-user limit conversion requires explicit confirmation.
- `includes/admin.php`: dashboard button/list and editor routing.
- `rg-coupon-studio.php`: version 1.1.0 and editor include/boot.
- `includes/qa.php`: 14 meaningful disposable editor checks added to existing 26 lifecycle checks.
- `README.md`: current operator guide, preserved restrictions, legacy-limit conversion and rollback.

`includes/engine.php` and `includes/storage.php` remain byte-identical to RG63 source. No schema, secret, storefront assets, shared plugins, tracking, themes, payment configuration or production modifications. Existing coupon IDs, usage counters, advanced restrictions, attempts and claims remain. Historical Woo usage is not retroactively converted into Studio identity claims.

## Validation

- All six PHP files parse with PHP 8.2 grammar using php-parser. PHP CLI is unavailable; this is not a php-lint claim.
- Real staging Woo/database suite: **40 PASS, 0 FAIL**. Covers editor draft CRUD, indefinite/scheduled duration, flags, native restrictions and usage counts, stable/changed revision hashes, duplicate draft codes, invalid amounts/dates/status rejected before persistence, same-ID flat-discount update, plus existing encryption/tamper/schedule/stacking/identity/atomic reservation/payment/COD/late-conflict/rate-limit tests.
- Browser form rejected 101% with entered code and checkbox retained. Corrected 1% coupon saved active with blank expiry; native cart reduced 2,899 by 28.99 to 2,870.01. Editing the same ID persisted flat20, future opening/closing and paused status.
- Native Add new coupon and Edit links reach the unified form. Explicit advanced native editor opens normally. Existing coupon was inspected without save; native per-user conversion confirmation is visible.
- Disposable browser coupon trashed. Original cart item/variation/quantity1/total2,899 restored, no coupon remains. Suite uses disposable identities/fixtures, trashes fixture orders/coupons and cleans its synthetic claims. No external gateway call or real checkout submission.
- Fresh installed source compared against candidate in all seven files before final docs/whitespace-only package replacement. Installation reports active 1.1.0, expected hash and size. No later functional source changes after passing suite.
- Error log checked as baseline; it was last modified 21 November and is not proof of current error freedom. No physical mobile-device certification or real gateway transaction claimed.

## Release and rollback

Final installed ZIP: 27,405 bytes; SHA256 `b5a399d24afa5885e9793daa029e76590579511e59cf6d8cdcc33ec9c98052a7`.

Source recorded in `tasks/RG63-coupon-studio/plugin/rg-coupon-studio/` on continuation branch `codex/coupon-studio-editor-20261123`, based on RG63 commit `077c4fddbf187f43e0894fcf6fb6fbc5cce66a19`. Repository remains the established coordination/source-handoff repository, not a newly inferred production source repository. No merge performed. RG65 remains open for owner review.

Rollback uses RG63 1.0.0 source/ZIP, replacing only this plugin's files. Preserve all coupon/order metadata, rgco tables and the private encryption option; never restore an old database or deactivate unrelated plugins.
