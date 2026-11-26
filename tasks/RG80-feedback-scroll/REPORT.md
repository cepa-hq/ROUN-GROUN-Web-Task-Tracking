# RG80 — Smooth feedback navigation

27 November 2026 India. Implemented on staging only. Existing Feedback system1.0.6→1.0.7, same plugin/folder. No new library/plugin/global CSS. Source baseline7430882579cfe8b33cf7f001a47fea8c9f7a88c5 from completed RG73.

## Problem and change

Start the form and eight desktop progress links used ordinary fragment jumps; document scroll-behavior was auto. Existing smooth helper was called only by validation and successful receipt. Added a delegated click handler within .rg-feedback for ordinary fragment anchors. It preserves modifier/download/new-target behavior, safely decodes IDs, checks targets exist/are visible inside the component, updates meaningful URL hash without the instant native jump, moves keyboard focus with preventScroll, then calls existing scrollToEl. Existing reduced-motion behavior and CSS header scroll margins retained. Bootstrap header + VERSION constant now1.0.7 to invalidate asset caches. No CSS or backend/data/auth/schema/submission/analytics/gift logic changed.

Exact changed files: wp-content/plugins/roun-groun-feedback-system/assets/form.js and roun-groun-feedback-system.php. Native plugin editor used because connection has no file-writing capability; both saves returned File edited successfully, full JS readback equals candidate. OriginalJS13831characters/FNV4064315996; deployed14974/FNV2388255640. Native bootstrap readback1.0.7; live form.js?ver=1.0.7 verified.

## Actual checks

- JS syntax via Node --check passed.
- Live signed-in desktop1363px, reduced motion false: final-question link moved through scrollY572 before settling2784, proving movement rather than initial instant jump. Final question top157.44px vs header bottom107px.
- Reverse tracker navigation to question4 moved through2502 before settling1487; question top157.44px, below header.
- Start the form moved to section#feedback; settledscrollY521/top156.89px. Keyboard Enter on question2 updated hash/focus and settledtop156.94px.
- Saved staff entry remains8of8answered; no feedback submission, customer/order/reward writes or payment actions. No horizontal overflow.
- Browser error entries observed were browser-extension metadata errors; no feedback-controller error observed. This is not a guarantee about unrelated extensions.
- Existing reduced-motion branch inspected/preserved, not live-emulated. No new physical mobile/device test; handler applies to component anchors at all widths and no layout styles changed.
- JS source exact readback verified after final navigation. Production untouched.

## Rollback and publishing

before/ contains exact prior two files; after/ contains deployed counterparts. Check current files for concurrent work before restoring only these two. Do not deactivate/reinstall an old whole-site snapshot, restore a database, or touch another plugin to undo this UI change. Feedback1.0.7 supersedes1.0.6 in the previous release checklist. Original publication safeguards for production data and staging robots still apply; this task does not authorize production publishing.

Issue80; unique branch agent/codex-feedback-scroll/rg-80. Native staging implementation already applied; PR is source/checkpoint record for owner review. Leave issue open.
