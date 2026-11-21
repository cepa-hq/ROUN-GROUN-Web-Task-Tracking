# RG-53 production SEO checkpoints

Observed 21 November 2026 (India). Owner authorized direct production corrections after staging publication. Task #53; documentation branch `agent/codex-production-seo/rg-53-integration`; initial main `ff256000f810d06e2db68bfae0105044153c7606`. This repository is the task ledger, not deployed website source. Live changes are already applied through native production administration; this branch records them for review.

## Checkpoints

1. **Access and backup:** production WordPress + Search Console authenticated by owner. WordPress MCP connector targets staging, so no connector writes were mistaken for live changes. Native Rank Math, Pages-only and Products-only backups captured privately.
2. **Baseline:** production sitemap works after publication. Anonymous crawl identified duplicate document titles, missing category descriptions and generic/overlong snippets. Google security/manual-action reports clean. GSC data lags publication.
3. **Title fix:** logged exact source scope before editing existing Core Header `includes/shell.php`; preserved Rank Math as title owner and removed only the late duplicate WordPress block title callback. Native success/readback, PHP parse and four hook checks passed. Patch committed.
4. **Global metadata:** imported guarded Titles-only values for Product schema default name and official social profiles. Import confirmation completed, success/backup observed. No other groups imported.
5. **Policy snippets:** native SEO title/description updates saved for four policy pages. Their complete raw content and excerpts remain identical in final native exports.
6. **Product snippets/brand:** all six product title/description pairs and native Roun Groun Brand47 assignments saved. Stove primary category20 saved. Native product-update confirmations received.
7. **Term controls and archives:** Rank Math's native JS Save Changes required a click; keyboard Enter left selections unsaved. Corrected with explicit Settings updated notices and fresh export. Enabled category/brand SEO editor controls, saved three category snippets, individually noindexed Uncategorized15 and Brand47. General brand source saved as product_brand. Sitemap/modules unchanged.
8. **Save integrity:** native settings saves materialized defaults; full diff committed. Full before/after WXR comparison confirms unchanged prices, stock, SKU, attributes, names, slugs and excerpts. Five legacy product HTML bodies normalized by classic editor, but displayed wording matches. Product Studio materialized26 existing UI defaults; every previously stored field has unchanged normalized meaning. No silent blanket assertion of raw product-body equality.
9. **Public QA:**39 anonymous requested URLs,17 unique eligible sitemap targets,84 assertions passed. One HEAD title/description/canonical on each eligible page; current branded Product offers; utilities excluded; redirects/404/favicon retained. Three additional homepage category-filter URLs each canonicalize to homepage.
10. **Google QA:** final homepage and category live tests available/indexable; smartphone product test successful with valid Product and merchant-listing items; account live fetch successful and excluded by noindex. One request-indexing submission failed with Google's generic error; not accepted, not repeatedly retried. Current sitemap remains Success.
11. **Search Console inventory:** settings/associations/sitemaps/security/manual actions/HTTPS/CWV/crawl/merchant/product/breadcrumb/indexing examples recorded in the reference. Historical production server-connectivity warning, DNS/robots acceptable; hosting logs needed. Discovery queue includes three intended public policies plus alias/cart/checkout. Canonical examples are intentional home category filters. Noindex examples are utility/search/login/cart actions; action tokens omitted from public Git.
12. **Final handoff:** documentation, safe settings/metadata manifests, audit results, source patch and reproducible read-only audit scripts committed. No new plugin, analytics changes, customer/order/review edits or staging mirror. Leave issue open for owner review and external follow-ups.

## Read these first

- [Integration report, limitations and rollback](RG-53-INTEGRATION-REPORT.md)
- [Search Console configuration and dated reports](RG-53-SEARCH-CONSOLE-REFERENCE.md)
- [Rank Math reference (no credentials)](RG-53-rankmath-reference.json)
- [Exact global settings diff](RG-53-settings-diff.json)
- [Public metadata before/after](RG-53-metadata-before-after.json)
- [Planned category metadata](RG-53-metadata-plan.json)
- [Public audit manifest](RG-53-public-audit-manifest.json)
- [Verification results](RG-53-verification-results.json)
- [Content hashes](RG-53-content-integrity.json)
- [Product save normalization](RG-53-product-save-normalization.json)
- [Product Studio field comparison](RG-53-studio-save-comparison.json)
- [Extra filter canonical checks](RG-53-filter-canonical-check.json)
- [Deployed source patch](patches/RG-53-shell-single-title.patch)
- [Read-only audit](scripts/audit_public.py) and [assertions](scripts/verify_audit.py)

## Publishing guard — mandatory for next deployment

Production is now the SEO source of truth. RG-52 staging sync predates RG-53. This task did not mirror these new corrections to staging. A full staging database/plugin overwrite can undo them. Rebase staging from current production or mirror only reviewed SEO keys/term memberships/source patch before another publish. Match by slug/taxonomy after a fresh guard. Preserve live orders, customers, payments, reviews and media; keep staging robots blocked and production crawlable. Never migrate a staging database over live business records. Preserve verification/access material privately; it is intentionally absent from this public Git repository.
