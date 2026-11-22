# RG-57 resume — 22 November 2026

IN PROGRESS. GMC and production/staging browser sessions authenticated. Native backups completed on both sites: database + plugins, excluding media/themes. Private archives remain on server; restore not exercised. Do not publish archive URLs/account identifiers/private settings.

## Completed
- Supplemental enrichment XML uploaded:16 matched/updated, all attribute names recognized, no file issues. Six product families,16 current offers. Does not override prices,availability,images,links,GTIN or MPN.
- Paired conditional title/description rules tested:16 processed,16 approved,0 disapproved for both Free listings and Shopping ads, no new issues. One pre-existing missing shipping price subattribute remains.
- Tested rules APPLIED; draft indicators gone. Only clear ordinary title/description if corresponding structured attribute has a value. This permits properly disclosed trained_algorithmic_media text to supply display values; future unmatched products retain ordinary source fallback.
- Staging WooCommerce10.1.4→11.3.1, Google for WooCommerce2.9.9→3.10.1 updated; code+DB11.3.1 verified. Claimed-URL mismatch guard preserved; no staging reconnection/claim.
- Staging preservation: products/prices/stock,order-status counts,existing general/shipping settings unchanged; new vendor address-autocomplete option disabled; no new PHP errors.
- Shop sorting/Quick look/variable choice/quantity-one cart/checkout summary checked. Checkout stopped at required phone validation; no order/payment. Test cart restored empty. Account preview no horizontal overflow at390/768/1280 widths.
- Production same official updates completed; code+DB11.3.1 confirmed. Existing connections unchanged, campaigns remain off. Native log MapiProductInputsService productInputs.insert all16 stable gla_ IDs, batch16 succeeded/0 failed at02:53:29UTC. Merchant API migration VERIFIED. Existing primary source ID retained, automatically renamed Google for WooCommerce(en/IN).
- Logs expose8 historic orphan variations69–76 excluded from sync, plus2 Google-found offers outside Woo mapping. No deletion.
- Selective native Undo edits applied to pin78,79,80,81: ONLY Category,Description,Item group id. Other edits retained.78/80/81 confirmed enriched title and correct category after processing;79 await final table readback. Original visible field backup held browser memory. No price/stock/MPN/images changed.

## Continue
1. Current GMC edit view pin82. Selectively restore Category,Description,Item group id for82–85 after capturing current main text and reading Undo edits list. Preserve all unrelated manual fields.
2. Inspect chalk938 manual Title/Category conflict and tote1063 Description; restore only actual conflicting fields. Effective product readback for all16 and approval checks.
3. Inspect pre-existing missing shipping price issue; use existing actual shipping policy/settings, no invented commercial terms.
4. Production storefront final regression (read-only or restore cart); no transactions. Logs and supported sync all16 verified.
5. Document effective before/after, static feed maintenance, safe rollback, residual orphan/discovery notes. Commit report, update this resume, create PR for issue57.

No Ads spend/activation, trackingRG44/45 changes, fake identifiers or credentials. Public repo excludes raw account/customer/settings/backup data. Never claim rank improvement guaranteed or processing indexed immediately.
