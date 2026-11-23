# Resume RG-59

Issue 59; branch `agent/codex-merchant-checkout/rg-59-links`.

Completed in production and staging: Checkout Studio 1.3.5, exact-variant checkout/cart entry, dynamic Merchant API checkout_link_template hook, and six factual Rank Math SEO title/focus updates. Production product bodies/excerpts preserved exactly. Eight anonymous cases per environment, 35 standalone assertions and final source parity passed. No orders/payments; test carts cleared.

GMC: old rejected account template removed; Include URL + blank textbox + Show in ads saved as official feed-only instructions specify. Empty account URL and persisted Ads eligibility verified after reopening. **Business info still says opted out; actual checkout-button display/Google approval unverified.** Do not claim a live checkout button. No campaigns activated. Product-level raw URLs confirmed for gla_938 and gla_1101. Updated product-detail titles confirmed; 16 supplemental rows matched with no issues; all 17 existing records approved.

Merchant API was already migrated in RG-57: original full batch of 16 succeeded; fresh inserts for the 12 in-stock offers succeeded in RG-59. Four out-of-stock offers unchanged. Content API label is legacy dashboard text per official Woo documentation. One automatically discovered Google parent remains separate; do not create a duplicate API parent.

Read RG59-REPORT.md and RG59-verification.json before further mutations. Do not recreate offer/source IDs, delete existing records, add static checkout URLs to unavailable products, enable ad spend, alter tracking or restore full databases over new customer data. Native GMC tables virtualize rows: scroll before concluding attributes are missing. The existing Google UI summary does not reliably represent the feed-only configuration; investigate Google-controlled activation using current official documentation/native UI if needed.

Rollback originals are in rollback/. Updated supplemental XML is tracked. Private backups, logs and authentication data are excluded from public Git. Source changes are scoped to existing Checkout Studio cart.php plus main-file version. No new plugin. PR58 remains separate and unmerged.
