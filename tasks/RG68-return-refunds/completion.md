# RG-68 — Staging return redesign and refund destinations

Agent: Codex. Date: 24 November 2026 (India). Status: implemented on staging; source PR for review. Coordination issue: https://github.com/cepa-hq/ROUN-GROUN-Web-Task-Tracking/issues/68

The owner approved the finalized Calm Collection design and explicitly requested staging implementation plus original-payment, UPI and bank refund instructions and customer collection links. Production was not changed. This repository is the coordination/archive hub; no separate website source repository exists. The installed plugin was recovered and edited as a scoped package, following the established MCP staging deployment workflow.

## Actual source changes

| File in plugin/ | Change and reason |
|---|---|
| roun-groun-request-centre.php | Boot refund and Calm adapters; validate and encrypt refund instructions before review storage; pass decrypted choice to the existing confirmed commit; PHP 8.1 minimum and version 1.1.1. |
| includes/repository.php | Include encrypted destination in the request/header/item SQL transaction; require InnoDB options storage; retain per-order locking and idempotent request retry. |
| templates/centre.php | Destination fields for refund requests, masked review summary and Calm receipt with actual requested products/photos/quantities. Existing eligibility and review process retained. |
| includes/refunds.php | New validation, authenticated encryption, request ownership checks, compare-and-swap versioning, masked summaries, customer forms, native backend integration, operator list, expiring links, audited reveal and payout lock, decision notes; staging-only isolated QA controls. |
| includes/calm.php | Replace only existing account requests content block and detail content; preserve account shell/menu/header/footer. Read native RMA data and order-item images/quantities; New, Processing, Reviewing, Completed and Rejected treatment; owner-only destination form. |
| assets/calm.css | Scope finalized Calm preview CSS to Request Centre/account blocks, local animations with reduced-motion support, destination fields and mobile layout. |
| assets/refund-form.js | Conditional destination fields, invitation fragment handoff and login redirect, normal navigation through existing account SPA, submit feedback. No bank fields in browser storage. |

Unchanged baseline files are included for a reproducible complete plugin. No vendor RMA, WooCommerce core, theme, account-sidebar, checkout, coupon or other agents' plugin source was edited. No production deployment or payouts.

## Compatibility fixes found during QA

- Native RMA hooks provide formatted request arrays. Reload the canonical request by ID before decrypting, so order/customer authenticated binding is preserved. An intermediate adapter error was detected and fixed; native request detail then rendered normally.
- Pre-render Calm content before the final output-buffer callback; avoid starting a nested buffer inside that callback.
- Register the submenu after the vendor parent menu, avoiding an intermediate permission-screen failure.
- Preserve Reviewing as an active review status with the correct label; do not mislabel it New.
- Bump 1.1.1 to invalidate intermediate 1.1.0 browser asset caches; final browser loaded the 1.1.1 stylesheet with transparent block background.
- Disposable fixture needed a completion-date property and native warranty metadata for the vendor item expiry renderer. Only the marked test order was amended. No vendor patch was needed.

Pre-edit checkpoints and deployment corrections are recorded chronologically on issue 68.

## Verification and evidence

- PHP 8.2 grammar parser: all PHP files accepted; both JavaScript assets accepted by Node vm.Script. PHP CLI was unavailable locally. Actual PHP execution also verified on staging.
- Final functional build: 26 isolated server-side assertions passed on staging. Coverage: COD original-method rejection; non-COD original acceptance; UPI and bank validation; account-confirmation, IFSC, consent and malformed-field refusal; AES-GCM roundtrip and versioned salt-derived key; customer-context replay/tamper rejection; encrypted persistence and masking; stale-save refusal; matching, expired and consumed links; closed/locked editing refusal; guest/missing-request denial; request nonce scope; encrypted review; InnoDB storage; transaction write and rollback cleanup.
- Browser: real account request list and detail displayed actual variant images and requested quantities, including a two-quantity item. Existing sidebar still showed the original 8 orders and 5 wishlist items after cleanup.
- Isolated fixture: generated 48-hour invitation; fragment removed from address bar; fictional bank details submitted as the owning account; backend list immediately showed the same masked account; manager reveal and audit event worked; payout lock closed customer editing. Native request detail showed the refund postbox and management link.
- Fixture statuses New, Processing, Reviewing, Completed and Rejected verified; rejection displayed the operator's fictional customer-visible reason. Processing/Reviewing retained the animated review treatment.
- Responsive check: 390px iframe with 375px content viewport; scrollWidth equaled clientWidth (375px), requested product image loaded. No horizontal overflow in the checked detail.
- Fixture cleanup verified in backend list: disposable request absent; dummy order moved to trash; its encrypted destination and tracking option removed. Random security-check records removed by their own finally block. Real requests and destinations were not modified.
- Installer confirmed final active version 1.1.1 and exact SHA-256 in release-manifest.json. Browser confirmed 1.1.1 CSS loaded, transparent content-block background, three original account request cards.

Limits: no real payment or gateway refund was executed. UPI/bank syntax checks do not verify beneficiary ownership. The full new-return browser confirmation path was not exercised with a real customer order; integration was reviewed and transactional destination rollback was executed separately. Signed-out/password/social-login invitation continuity was implemented but not end-to-end authenticated in this session; owner/guest checks were tested server-side. Mobile verification used an iframe, not a physical device. A native-list navigation attempt failed on an invalid browser URL; native detail and the separate operator list were verified, and list mode integration is source-backed. The 26 assertions are targeted checks, not a penetration test. Earlier QA errors remain in historical logs; do not interpret them as current failures or claim historical logs are clean.

## Deployment and rollback

Only testarea.roungroun.com was deployed. Final: Roun Groun Request Centre 1.1.1, active. Authorization: user's request to implement the finalized design on staging and add the refund ecosystem.

release/ contains the exact installed ZIP and the recovered pre-change 1.0.5 rollback ZIP. Use the operator guide for backups and rollback. Keep refund encryption key, ciphertext and original WordPress authentication salts together in a secure backup; never commit them. Neither package contains keys or customer financial data.

Base archive commit: ff256000f810d06e2db68bfae0105044153c7606. Task branch: agent/codex-return-implementation/rg-68. Commit and PR links are recorded in issue 68 after publication. Keep issue open for the owner's review; no merge performed.
