# RG-53 — Production SEO integration and verification

21 November 2026 (India). Owner authorized direct production SEO work after staging publication. Live corrections are deployed; this PR records the work in the task-tracking repository. It does not deploy WordPress files. See issue #53 for pre-change scopes/checkpoints.

## Outcome

The live store now serves one page title, useful page/product/category metadata, canonical URLs and factual branded Product offers. Anonymous verification covered39 requested URLs, all17 unique sitemap targets and84 assertions. Google's live smartphone inspection successfully fetched a product and detected valid product and merchant-listing items. Homepage and category are available for indexing; account remains correctly noindex. Google reports no manual actions or security issues.

**Rank Math is adequate for core SEO; no replacement plugin was created.** Optional merchant-policy and variant automation gaps are recorded below. Historical server-connectivity failures and Google's indexing-request submission error remain external follow-ups; this is not a claim that every Google report has already refreshed or every page is indexed.

## Changes, rationale and code/data scope

| Change | Why | Exact scope |
| --- | --- | --- |
| Prevent duplicate document titles | WordPress block-template resolution added a late title callback alongside Rank Math's title | Existing `roun-groun-core-header/includes/shell.php`: `boot()` adds priority0 `wp_head` callback; new `single_title()` removes only `_block_template_render_title_tag` priority1 when Rank Math owns `_wp_render_title_tag`. No core/theme/vendor edit. Patch in `patches/RG-53-shell-single-title.patch` |
| Four policy metadata pairs | Generic/legal-page snippets were poor descriptions | Native Rank Math title/description on IDs10,212,509,520 only; legal content unchanged |
| Six product metadata pairs | Overlong/marketing-heavy titles and weak descriptions | IDs77,938,1044,1063,1099,1197; public names/slugs, prices, stock and attributes unchanged |
| Three category metadata pairs | All three indexable category descriptions were missing | Product category terms18,20,43; native archive name/slug/body not intentionally edited |
| Brand source | Six Product nodes had no brand | Woo native `product_brand` term47 `roun-groun`, “Roun Groun”, assigned to six existing branded products; Rank Math General `product_brand=product_brand`. Future products require truthful brand assignment |
| Product schema default name | Avoid SEO-title separator/branding being the default for new schema | Titles `pt_product_default_snippet_name=%title%`; existing per-product schema overrides can still use `%seo_title%`. Live existing names became concise through product metadata fixes |
| Organization social profiles | Link official accounts in organization identity | Facebook `https://www.facebook.com/profile.php?id=61595907878250`, Instagram `https://www.instagram.com/roun.groun/` |
| Native term SEO controls | Needed native per-term metadata/robots editing | Titles `tax_product_cat_add_meta_box=on`, `tax_product_brand_add_meta_box=on`; no blanket category/brand robots change |
| Exclude redundant archives | Uncategorized/single-brand results add no distinct landing content | Individual term15 Uncategorized and term47 Roun Groun brand noindex. Future brand archives unaffected |
| Stove breadcrumb primary category | It had no selected primary term | Product1197 `rank_math_primary_product_cat=20` (Camping), an existing assigned category |

Global import was Titles-only, guarded against a fresh native production export, with importer success and backup confirmation. Later native General/Titles saves materialized22 previously unstored defaults in addition to the6 intended key changes. Full diff is recorded. Empty RSS strings became absent values; dormant floating-element defaults/breadcrumb/UI defaults were serialized. Sitemap and module groups are byte-equivalent as decoded data. Frontend SEO score remainsoff, breadcrumbs remainoff, headless support remainsoff; no new script/assets/schema owner installed.

## Preservation checks

- Native production before/after Pages-only + Products-only WXR exports, containing21 published records, were compared privately. Raw exports and account/customer/comment data are not committed.
- All15 page raw content/excerpt hashes, including all four legal pages, match. All21 public post names/slugs and all6 product excerpts match.
- Five legacy product HTML bodies were normalized by the classic editor (whitespace, entities and equivalent markup); normalized displayed wording matches all6 products. Stove raw content also matches. We do **not** claim all raw product body hashes unchanged.
- Woo price, regular/sale price, stock status/count, stock-management settings, SKU, attributes/default attributes match full original product export for all6 products. Initial comparison using an allowlisted baseline omitted some commerce keys; final verification used full native WXR, correcting that false-positive comparison.
- Native Product Studio save expanded63 keys to89 by materializing26 existing UI defaults and normalized paragraph/newline formatting. Comparing every previously stored field after HTML/whitespace normalization found **no semantic value changes**. Added-key names and changed meta-key names are recorded; no rollback of another agent's plugin/config was attempted.
- Native exports normalize CRLF in serialized strings. String byte lengths were repaired **only in scratch analysis copies** to decode and compare them; no such repair was written to WordPress.
- No orders, customers, review records, tracking configurations, published layouts, prices or slugs were intentionally changed. Existing plugin save hooks updated their usual cache/history/feed synchronization metadata.

## Verification

| Check | Result |
| --- | --- |
| PHP parse + isolated hook behavior | PHP8.2.35 WASM parsing and4 assertions passed; native WordPress editor syntax/loopback accepted patch. Not a full WordPress integration test |
| Public crawl |39 anonymous requests;17 unique sitemap pages all200, indexable, one HEAD title, one description, self-canonical |
| Structured data | All inspected JSON-LD parses. One Product node per product, Roun Groun brand, INR positive current offer, canonical offer URL; whistle accuratelyOutOfStock; no fabricated0-review aggregate ratings |
| Utility exclusion | Cart, checkout, account, wishlist, cancellation/return forms, search, Uncategorized, Sportswear empty archive, brand archive allnoindex; excluded sitemap |
| Canonical/redirects | HTTP/www toHTTPS/nonwww; oldAbout and refund slug aliases retained; shop sorting canonicalShop; legacy sitemap redirects retained |
| Identity/assets | Approved multicolour512px RG organization logo and32px favicon reachable; official social links in organization; no staging-host references or old apparel-only description in inspectedHTML |
| Missing URLs | Nonexistent URL returns404/noindex |
| Google live tests | Homepage/product/category can be indexed; account fetch succeeds but is excluded bynoindex. Product and merchant-listing items valid with optional warnings |
| Audit assertions |84 passed, see verification-results.json; these are scoped technical assertions, not Google certification |

The audit is source/SEO focused, not a new mobile-layout redesign or a Lighthouse/CWV pass. Existing product-gallery clones may use empty alt text; no missing `alt` attributes found on the17 sitemap targets. Do not fill decorative duplicate thumbnail alt text indiscriminately. Product structured data is present in initialHTML.

## Remaining findings and solution path

1. **Historical server connectivity:** GSC (19Nov) says production had a high server-connectivity failure rate last week; DNS/robots acceptable. Fresh public HTTP and live Google fetches succeed. Investigate hosting/CDN/origin logs, timeouts and resource limits for that period. Browser audit fetch durations are not reliable end-user speed scores; no current origin root cause claimed. No security/network protections weakened.
2. **Google queue/report lag:**16Nov report shows14indexed; Discovery queue includes3 intended public policy URLs (returns, shipping, terms), plusAbout alias/cart/checkout. Current policies are200, indexable, self-canonical and in successful sitemap. One fresh product request-indexing submission failed with Google's generic error. Do not claim acceptance, repeat indiscriminately or request indexing for utility pages. Await crawl/report refresh and recheck current exclusions.
3. **Optional shipping/returns schema:** Merchant Centre association and1shipping/1returns policy exist. Exact Merchant Centre policy values and product exceptions were not independently audited there. Google supports Merchant Centre settings as an alternative; do not hardcode blanket return windows or pincode-dependent rates. A future integration should use Product Studio's actual per-product return eligibility/window and checkout's shipping rules, validate against Merchant Centre, and enrich Rank Math's existing graph with matching stableIDs rather than create competing Product markup.
4. **Optional variants/identifiers:** Current variable products emit valid basicProduct/Offer rather thanProductGroup/hasVariant. Future version can emit truthful variant identifiers, attributes, prices, stock and selectable URLs using Woo data. Add GTIN/MPN only when manufacturer data exists; SKU is not GTIN. Never fabricate reviews to clear optional review/aggregateRating notices.
5. **Offer dates:** Existing Rank Math output generates `priceValidUntil=2027-12-31` without a matching sale schedule proven in this audit. No dates were invented or changed in this task. Future schema integration should retain actual Woo sale end dates and omit unsupported fallback expiry; `validFrom` should be supplied only from a reliable offer-start source. Record as data-quality improvement, not an invalid-item claim.
6. **Plugin maintenance:** Production Rank Math1.0.238; native panel advertises stable1.0.282. Do a staged compatibility/security update with backups and rerun this audit before promoting. No untested broad plugin update on the newly published store.
7. **Breadcrumbs:** Native breadcrumbs globallyoff. GSC17valid reflects older crawls; current live category has no breadcrumb enhancement. Do not restore the old identical global breadcrumb trail. If adding breadcrumbs later, reflect each actual page/category path and visible navigation.
8. **Access:** Current GSC account is not a verified owner; exact delegation unknown. No verification/DNS/access changes made. Existing access sufficed for reports/live tests. Ownership administration must use the owner's normal process if needed later.

A new all-purpose SEO plugin is unnecessary. If custom work is approved later, prefer a narrowly scoped extension of existing Product Studio or Rank Math integration for product-policy/variant data, with one schema owner, server-rendered JSON-LD and regression checks. No extra animation, analytics or catalogue changes required for SEO.

## Future publishing and rollback

**This production task did not sync these new changes back to staging. A future full staging database/plugin overwrite can undo them.** The previous RG-52 sync predates RG-53. Production is now the SEO source of truth. Before the next publish:

1. Rebase staging from current production using a privacy-safe migration workflow, or mirror only these allowlisted SEO option/postmeta/termmeta/brand assignments and the single source patch after fresh guards. Match by post/term slug and taxonomy; IDs are this production snapshot, not universal IDs. See reference/settings and metadata manifests.
2. Preserve current production customers, orders, payments, reviews/media and reporting records. Do not replace live business tables with staging data. Google associations live outside WordPress, but site verification and identity settings still need preserving.
3. Preserve environment-specific robots: staging blocked; production crawlallowed. Do not copy a physical stagingrobots.txt or `blog_public=0` to production. Preserve existing verification tags/tokens privately; none are stored in this public repository.
4. Publish only reviewed files/selected settings/content changes; re-run public source assertions and verify sitemap/noindex/logo/canonical/product offers. If any SEO baseline differs unexpectedly, stop that deployment and reconcile, rather than resetting it.

Rollback only a confirmed regression. Check current source first for concurrent agent changes; reverse the small shell patch, not a whole old plugin. For metadata use metadata-before-after.json and restore only the affected Rank Math keys, not content/commerce data. Term18/20/43 snippets originally inherited `%term% %sep% %sitename%` with emptydescription; individual term15/47 robots inheritedIndex before edits. Global intended values before/after and native defaults are in settings-diff.json. Brand term can remain present; unassign only task-added memberships if rollback is required and no subsequent work relies on them. Do not import a full old database/settings export over live user data.

## Primary references

- [Google ecommerce best practices](https://developers.google.com/search/docs/specialty/ecommerce)
- [Merchant listing / shipping and returns alternatives](https://developers.google.com/search/docs/appearance/structured-data/merchant-listing)
- [Return-policy markup and product exceptions](https://developers.google.com/search/docs/appearance/structured-data/return-policy)
- [Product variants](https://developers.google.com/search/docs/appearance/structured-data/product-variants)
- [URL Inspection and limits](https://support.google.com/webmasters/answer/9012289)
- [Sitemaps report](https://support.google.com/webmasters/answer/7451001)
- [WordPress block-template title callback source](https://github.com/WordPress/wordpress-develop/blob/trunk/src/wp-includes/block-template.php)
