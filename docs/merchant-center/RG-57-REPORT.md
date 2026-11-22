# RG-57 — Merchant Center enrichment and Merchant API migration

Agent: codex-merchant-visibility. Implementation 21–23 November 2026 (India). Coordination issue: #57. Branch: `agent/codex-merchant-visibility/rg-57-audit`. This is the coordination repository, not a website deployment repository. These changes were already applied through native administration and the staging connector; merging this documentation does not deploy them.

## Result and evidence

The existing Google for WooCommerce integration now uses Merchant API. Production's native log records `MapiProductInputsService::insert_many`, `productInputs.insert` for all 16 existing offers, then **16 succeeded, 0 failed** at 02:53:29 UTC on 22 November. The original primary product source ID and offer IDs were retained. Merchant Center may still label its source type Content API; this label does not identify the transport actually used.

All 16 offers received factual structured titles/descriptions, Google categories and hierarchical product types from the existing public product information. The 13 variants retain their three product-family groups. The updated band and stove titles name their selected colours, and pin titles name diameter and colour. Descriptions no longer expose old Flatsome shortcodes or repetitive marketing text. Unsupported venue-approval and capacity claims were not carried into the tote description. No fabricated identifiers, ratings or certifications were added.

The supplemental file processed with 16 matches/updates, all attribute names recognized and no file issues. Native draft-rule tests processed all 16 offers and retained 16 approved / 0 disapproved for both free listings and Shopping ads, introducing no new issues. Rules were then applied. After processing, all 16 enriched titles were observed in the product table. Detailed category, description and group readback covered all six product families. Final post-correction inventory: **17 approved, comprising 16 synced offers and one automatically discovered parent; zero processing/disapproved offers**. Needs attention showed no product issues, and setup/policy diagnostics stated there were no issues to fix. Band1101 and pin84 returned to Approved after the final corrections.

## What changed and why

| Surface | Change | Reason |
| --- | --- | --- |
| Existing Google for WooCommerce package, both sites | 2.9.9 → 3.10.1, official release | Supported Merchant API transport; no custom API credentials needed |
| Existing WooCommerce package, both sites | Code and database 10.1.4 → 11.3.1, official release | Required compatibility for current Google extension |
| GMC supplemental source | `roun-groun-merchant-enrichment.xml`, English / IN, linked only to existing primary API source | Durable enrichment without duplicating offers or controlling dynamic prices/stock |
| Primary source attribute rules | Enrichment first, existing API fallback; clear ordinary title/description only when the matching structured field exists | Ordinary text otherwise takes precedence over properly disclosed AI-assisted text; guard protects future unmatched offers |
| Pins 78–85 manual edits | Restore feed ownership only for Category, Description and Item group id | Old Gymnastics category, outdated 9.8 mm description and blank grouping overrode the feed |
| Chalk 938 manual edits | Restore only Title and Category | Remove long obsolete title and let appropriate grip-chalk category win |
| Tote 1063 manual edits | Restore only Description | Replace stale promotional claims with public factual specifications |
| Band 1101 manual edit | Restore only Color | Old Green/Beige/Black override conflicted with the actual Beige/Black selected variation |
| Pin 84 manual edits | Restore only Price and Sale price | Raw live source has price ₹1,099 with no sale price; manual ₹2,700 / ₹1,099 introduced a stale crossed-out discount |
| Google integration Markets → Primary Market, both sites | Existing free India delivery estimate 1–2 → 3–5 days | Match the existing live shipping policy, which states 1–2 handling + 2–3 transit days |

Merchant Center shipping service readback shows **Free / 3–5 days / India / All / Complete**. WooCommerce market readback is ₹0 and 3–5 days on both production and staging. This changes Google's estimate, not checkout shipping rates or the website's policy wording.

The native extension stores a combined delivery estimate and maps it to transit time with handling 0/0. It does not expose a separate handling/cutoff configuration in this market UI. Consequently, the customer-facing total is aligned, but separate handling 1–2, transit 2–3 and 10 AM cutoff were **not** configured individually. Vendor source inspected: `src/Shipping/GoogleAdapter/AbstractShippingSettingsAdapter.php`, `get_delivery_time()`. No vendor patch was made.

## Categories and groups

| Family | Offers | Google category ID | Group |
| --- | --- | --- | --- |
| Drop-set pins | gla_78–gla_85 | 3217 | 77 |
| Butane camping stoves | gla_1199, gla_1200, gla_1201 | 1015 | 1197 |
| Natural latex bands | gla_1100, gla_1101 | 5869 | 1099 |
| Liquid chalk | gla_938 | 3971 | none |
| PVC tote | gla_1063 | 5608 | none |
| Titanium whistle | gla_1044 | 6730 | none |

The XML intentionally omits price, availability, image, destination link, GTIN and MPN overrides. Existing native source/manual identifiers and approved images were retained. The pin84 correction restores existing source pricing rather than setting a new commercial price. Four current API offers were out of stock at audit: two stove colours, black 9.5 mm pin and whistle. Their stock was not changed to increase exposure.

## Compatibility and preservation checks actually performed

- Native database + plugins backups completed on staging and production before package updates. Media/themes excluded. Server archives retained privately; restore was not exercised.
- Staged migration first; temporarily deactivated staging Google extension, updated packages, inspected claimed-site/auth write safeguards, then reactivated. Staging URL was not claimed or reconnected to Google.
- Staging product inventory/prices/stock, order-status counts and existing general/shipping settings matched the baseline after migration. New vendor address-autocomplete setting defaulted to disabled. The later declared Google delivery-estimate correction is the sole intended settings difference from that baseline.
- Staging PHP error log had no new entries. Native production status confirmed both Woo code and database 11.3.1, Google extension 3.10.1. Fresh staging plugin listing confirms both active at those versions.
- Shop price sorting, Quick look, variation selection and a quantity-one ₹699 band cart were checked on staging. Checkout summary displayed expected product, variant, quantity and totals; required phone validation correctly stopped progression. No order/payment/customer-address change submitted. Test cart restored empty.
- Account preview had no horizontal overflow at 390/768/1280 preview widths (inner content 375/753/1265). This is a layout check, not certification of every mobile browser or every account action.
- Production band page rendered after updates; settled colour choice enabled Buy now with quantity 1. No production purchase or payment test was performed.
- Existing Google/WordPress.com/Merchant Center/Ads connections preserved. Fresh live product-feed UI reports six active families, no unsynced/disapproved families and account approved. Native log still shows the successful Merchant API batch. No campaigns activated or budgets modified.
- Approved layouts, policy copy, custom RG plugins and RG44/RG45 tracking code were not edited. Customer, order and review data were not migrated or replaced.

## Remaining observations and limits

1. Ads campaigns/ad groups are paused or removed. Therefore paid Shopping placement cannot be expected. No spending was authorized or enabled. Free-listing eligibility is separate and does not guarantee a position for an exact-name search.
2. No before/after ranking improvement is claimed. Measure free-listing impressions, clicks and relevant queries over a meaningful period after processing; don't repeatedly edit approved data merely because a personalized manual search does not show it.
3. Store quality is Good; image quality and image count are rated Exceptional. Google Customer Reviews is not enrolled. Enrolment may require terms/consent; it was not enabled, and store reviews must never be fabricated.
4. Native logs expose eight historical orphan variations 69–76 excluded from sync. They were not deleted. An automatically discovered parent offer is outside Woo's offer mapping and produces a non-fatal mapping diagnostic. These are separate from the 16 successfully synced current offers.
5. Automatically found inventory changed from two extra offers to one during normal Google reconciliation. No source or discovered item was manually deleted. Do not recreate duplicate parents simply to restore an old inventory count.
6. A rule-test snapshot reported one pre-existing missing shipping price subattribute. Detailed product readbacks showed Needs attention (0), and the later live diagnostic page was checked separately. The account has a free India shipping service; no per-product shipping price was invented to conceal that warning.
7. Old raw WordPress product descriptions still contain historical shortcodes. Current offers are cleaned by the supplemental structured-description source. This task did not rewrite stored product content or approved Product Studio layouts.

## Maintenance and publication

- The XML is a **manual supplemental file**, not a newly implemented automatic WordPress feed. Update its factual text/categories when specifications change; add corresponding rows when new offer IDs are created. New unmatched products safely use the ordinary API source until enriched.
- Keep API price/stock syncing. Do not add static price or availability overrides to this enrichment source.
- Preserve structured content's `trained_algorithmic_media` disclosure and paired rules. Reverting to unconditional title clearing can break new products that lack structured data.
- Keep both sites' official package versions and Google market estimate aligned. Do not reconnect a cloned staging site, claim its hostname, copy credentials between sites or publish staging robots.txt to production.
- GMC sources/rules are external account state and are not copied by WordPress publication. The exact source content and rule description are recorded here to make later audits possible.
- Do not import an entire staging database over live customers/orders/reviews. Back up fresh production data immediately before any future publication.

## Safe rollback

Revert only a confirmed faulty field after fresh readback. If removing structured enrichment, first restore ordinary title/description fallback rules, test approval impact, then unlink the supplemental source; never clear required text while removing its replacement. Do not delete stable offers or change IDs to force reapproval. Manual attribute corrections can be changed through the native editor if a new factual specification warrants it; do not reinstate known false discount/category/diameter claims.

Package rollback requires assessing the upgraded Woo database and preserving all new orders/customers. A pre-upgrade server archive exists but is not proof that a full production database restore is safe. Test the restoration/migration plan on staging before using it on production. Do not restore an old live database blindly.

## Official references

- https://woocommerce.com/document/google-for-woocommerce/ — supported Merchant API migration and legacy source-label explanation.
- https://support.google.com/merchants/answer/6324415?hl=en — ordinary/structured title precedence.
- https://support.google.com/merchants/answer/6324468?hl=en — descriptions and structured AI-assisted text.
- https://support.google.com/merchants/answer/14743464?hl=en — AI-assisted product data requirements.
- https://support.google.com/merchants/answer/14990942?hl=en — supplemental sources.
- https://support.google.com/merchants/answer/15000388?hl=en — rule testing.
- https://support.google.com/merchants/answer/12488713 — processing and approval states.
- https://support.google.com/merchants/answer/13889434?hl=en — free listings and no guaranteed display.

Account IDs, emails, credentials, private analytics, customer records, raw settings and backup URLs/tokens are intentionally excluded from the public repository.
