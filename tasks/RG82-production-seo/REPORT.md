# RG-82 — Production SEO safeguards after publication

Date: 27 November 2026 (Asia/Kolkata). Agent: codex-postpublish-seo.
Owner explicitly authorized production SEO verification/corrections after publishing staging.
Coordination issue: https://github.com/cepa-hq/ROUN-GROUN-Web-Task-Tracking/issues/82

## Outcome and actual changes

No publish-related SEO blocker was found in the inspected production settings or public responses. Production already had its correct environment settings when first checked; no WordPress setting, plugin source, database record, robots file, or server configuration was changed by this task.

Google Search Console actions completed:
1. Resubmitted https://roungroun.com/sitemap_index.xml. Google displayed "Sitemap submitted successfully"; submitted date updated to 26 November in Google's browser timezone. Previous last-read date/discovery count remained historical, not a claim of an immediate full recrawl.
2. Inspected https://roungroun.com/. Indexed view reported "URL is on Google".
3. Ran a new live test. Google reported "URL is available to Google", crawl allowed Yes, fetch Successful, indexing allowed Yes, and user-declared canonical https://roungroun.com/. Live crawl shown at 26 November 2026 19:24:01 in the browser timezone (27 November in India).
4. Requested indexing once. Google displayed "Indexing requested" and confirmed addition to a priority crawl queue.

## Native production settings inspected

| Surface | Observed production state |
| --- | --- |
| WordPress General | Home and site URL are https://roungroun.com; ROUN GROUN identity retained |
| WordPress Reading | Discourage search engines checkbox unchecked; homepage page 17 |
| Rank Math General | 66 inspected non-secret/non-htaccess fields, no staging hostname values; custom robots text blank |
| Rank Math Titles & Meta | 312 fields inspected, no staging hostname values; Organization identity; default page schema off and WooCommerce product schema selected |
| Rank Math Sitemap | 45 fields inspected; product category sitemap enabled; excluded posts 7,8,9,286,1445,1517,1518 and term 15 retained |
| WPCode Header/Footer | Verification-only header retained; body/footer empty; no duplicate global schema or staging hostname |
| Journey tracking settings | Enabled; existing analytics and Ads purchase configuration retained (identifiers intentionally omitted from this public checkpoint) |
| Published versions | Feedback 1.0.7; Checkout Studio 1.3.5; Checkout Messages 1.1.1; Coupon Studio 1.1.0; Performance 1.2.3; Request Centre 1.1.1; Review Studio 1.1.2; Rank Math 1.0.238 |

WordPress MCP connection was confirmed to target staging and was not used for production writes. Production administration was inspected through the owner's authenticated native dashboard. Review Studio remains active despite its directory name containing ".disabled"; it was not altered.

## Fresh public verification

39 distinct production URLs fetched, with no fetch errors. The three sitemap children contain 17 unique intended URLs (Shop is listed in both page/product sitemap).
203 assertions passed; zero failed. Full assertion results accompany this report.

- All 17 sitemap destinations return HTTP 200, one title, one nonempty description, production self canonical, and no noindex directive.
- JSON-LD parses, no PHP fatal text, and image alt attributes are present on those pages.
- All six product pages expose one Product entity, Roun Groun brand, a positive INR offer, and the correct production offer URL.
- Cart, checkout, account, wishlist, cancellation and return routes remain noindex, excluded from sitemap and private/no-cache.
- Search results, uncategorized and brand archives remain noindex and outside the sitemap.
- Sorting/price filter URLs canonicalize to Shop. This does not independently test mobile filter interaction.
- Old /about/ redirects HTTP 301. Unknown URLs and the empty Sportswear category return HTTP 404 rather than a soft 404.
- Homepage Google verification and existing tracking identifiers are present. This does not claim a new end-to-end purchase conversion test.
- No testarea.roungroun.com references found in the inspected production responses.
- Live robots.txt allows public root crawling, protects WooCommerce private upload locations/admin and add-to-cart URLs, and declares the production sitemap. No staging Disallow-all survived publication.

### Branding assets

All approved multicolour icon assets return HTTP 200 at their declared dimensions, retain transparency, and match the pre-publish production asset hashes:

- 32 × 32 favicon
- 192 × 192 favicon
- 180 × 180 Apple touch icon

Organization logo returns HTTP 200 as a 512 × 512 WebP:
https://roungroun.com/wp-content/uploads/2026/11/cropped-Multicolour-Gradient-RG-Logo.webp

Social sharing image returns HTTP 200 as a 1024 × 654 PNG:
https://roungroun.com/wp-content/uploads/2025/01/transparent-02-e1735810536628-1024x654.png

### Comparison with pre-publish production

The 17 sitemap destinations retain their previously reviewed titles, descriptions, canonical tags and robots settings. The six product JSON-LD differences are confined to WebPage dateModified values restored from staging; Product offer/brand entities are unchanged. Dates were not artificially rewritten.

Anonymous feedback access now reaches the customer account gate with noindex and private/no-cache, consistent with the newly published customer-login feedback workflow. The previously reviewed public feedback URL was unavailable on old production; it is not intended for Google indexing.

## Boundaries and remaining limitations

- No website code or production settings changed because the checked safeguards were already correct. Only GSC sitemap resubmission and the homepage recrawl request changed external state.
- No plugin installations, bulk updates, restores, orders, customer data, reviews, payment settings, or other agents' source changes were touched.
- This is an SEO smoke audit, not a guarantee of rankings or the absence of unrelated website defects. Google's search snippets/favicon and report counts update on Google's schedule.
- No new payment, review submission, customer sign-in, analytics event delivery, or full Core Web Vitals field test was performed.
- The audit does not reconcile orders, accounts, reviews, or other business records before/after the owner's publication.
- Previously documented optional Merchant schema enhancements and performance maintenance remain outside this task; no claim they were fixed here.
- Legacy GSC sitemap submissions were left intact; the current Rank Math sitemap is the authoritative submitted index.
- Source checkpoint is documentation only in the coordination repository. No PR merge or additional deployment is required to apply the already completed GSC actions.

## Coordination checkpoint

Read AGENTS.md and coordination workflow; checked open issues, active branches and PRs before starting, and rechecked open issues before completing. RG-79 mobile filter work remains outside this task. Existing unmerged task branches/PRs were not changed.

Base: main @ ff256000f810d06e2db68bfae0105044153c7606.
Task branch: agent/codex-postpublish-seo/rg-82.
Changed repository paths: tasks/RG82-production-seo/REPORT.md and tasks/RG82-production-seo/verification.json.
Issue remains open for owner review.
