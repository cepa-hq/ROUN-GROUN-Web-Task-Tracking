# Roun Groun Search Console reference — RG-53

Observed 21 November 2026 (India). This is a dated configuration and technical-report snapshot, not a replacement for current Google data. Repository is public: credentials, account emails, verification tokens, cookies, customer records, raw exports and private query analytics are omitted. Issue #53; production https://roungroun.com/.

## Property and settings

| Setting | Observed state | Action |
| --- | --- | --- |
| Property | Domain property `sc-domain:roungroun.com` | Preserved; includes www and staging subdomains |
| Canonical store origin | `https://roungroun.com/` | HTTP/www redirect here |
| Access | Current signed-in account is **not a verified owner**, according to Settings | No ownership, DNS, verification or permission changes; exact delegated role not confirmed |
| Property added to current account | 21 Nov 2026 | Reference only |
| Associations | GA4 production site/data stream and Merchant Centre “Roun Groun” | Preserved; no pending association requests |
| Search generative AI | Include | Preserved |
| Shipping/returns | 1 delivery policy and 1 returns policy; managed in Merchant Centre | Preserved; GSC says this settings screen will disappear in 30 days. Individual policy details were not independently audited in Merchant Centre |
| Robots | All files valid in GSC; live production robots permits public crawling and advertises Rank Math XML index | Staging remains blocked; never copy staging robots to production |
| Manual actions | No issues detected | No changes |
| Security issues | No issues detected | No changes |
| Temporary removals | No requests in last 6 months | No removal requests created |

The current account could inspect reports and live URLs. A product indexing request returned “We had a problem submitting your indexing request. Please try again later.” It was **not accepted**. This is not evidence of a permissions failure or a bot block. Do not repeatedly submit or claim a recrawl was accepted. Existing sitemap submission is successful and remains the discovery mechanism.

## Sitemaps

| Submitted URL | Submitted | Last read shown | State / historical count |
| --- | --- | --- | --- |
| https://roungroun.com/sitemap_index.xml | 21 Nov 2026 | 21 Nov 2026 | Success; 20 discovered pages, 0 videos |
| https://roungroun.com/wp-sitemap-posts-page-1.xml | 17 Dec 2024 | 17 Jan 2025 | Historical Success; 11 |
| https://roungroun.com/wp-sitemap-posts-product-1.xml | 17 Dec 2024 | 22 Jan 2025 | Historical Success; 2 |
| https://roungroun.com/wp-sitemap.xml | 17 Dec 2024 | 25 Jan 2025 | Historical Success; 20 |

The three legacy endpoints redirect to current Rank Math XML; kept rather than deleting history. The fresh HTTP crawl found **18 entries / 17 unique eligible URLs**: 8 public pages, 6 products, 3 nonempty product categories. Shop also appears in the product sitemap; it is the duplicate entry. GSC's 20 is a delayed discovered count, not the current unique URL count. All 17 URLs returned200, indexable, self-canonical, with one title/description. No cart/account/wishlist/request/empty taxonomy URLs in these sitemaps.

## Indexing report

Last updated **16 Nov 2026**, before this publication: 14 indexed, 28 not indexed. Domain property includes staging. Exclusion counts are not automatically faults.

| Reason | Count | Interpretation / observed examples |
| --- | --- | --- |
| Noindex | 8 | Expected utility exclusions; account specifically confirmed in stored and live inspection |
| Redirect | 3 | http://roungroun.com/, http://www.roungroun.com/, https://www.roungroun.com/ — canonical redirects are intentional |
| Alternative with proper canonical | 3 | Historical report; no redirect/canonical removal justified solely by count |
| Other4xx | 1 | `/wp-admin/admin-ajax.php?action=rest-nonce`; private utility endpoint, must not expose for indexing |
| Robots blocked | 1 | `https://testarea.roungroun.com/`; intentional staging protection |
| Discovered not indexed | 6 | Historical Google discovery queue; check example appendix / future report before treating as current store failures |
| Crawled not indexed | 6 | Uncategorized archive, shop feed, Uncategorized feed, bands feed, tote feed, wishlist — utility/feed URLs; not six lost product pages |
| Notfound404 | 0 | Fresh nonexistent test URL correctly returns404/noindex; do not redirect all404s to home |

Individual `Uncategorized` archive now explicitly noindex. Empty Sportswear archive automatically noindex. The newly created single-brand listing is also individually noindex; future brands were not globally blocked.

## Experience, crawl, rich results

| Report | Last update | Observed |
| --- | --- | --- |
| HTTPS | 21 Nov 2026 | 13 HTTPS, 0 non-HTTPS; no issues in90days |
| Core Web Vitals | 20 Nov 2026 | Insufficient Chrome UX field data for mobile **and** desktop. No pass/fail or Lighthouse score claimed |
| Crawl stats, whole domain | 19 Nov 2026 | 5,121 requests, 188,375,231 bytes, average928ms; 96%200, 2%301, others each<1% in visible top rows |
| Crawl stats, production host | 19 Nov 2026 | 5,037 requests, 185,716,747 bytes, average916ms; 97%200; historical **server connectivity high fail rate last week** |
| Host availability | 19 Nov 2026 | Robots fetch and DNS acceptable; server connectivity warning. Requires dated hosting/CDN/origin logs, not an SEO metadata change |
| Other hosts | 19 Nov 2026 | www65 requests; staging19; no host problems shown |
| Product snippets | 20 Nov 2026 | 6 valid, 0 invalid. Optional missing review6 and aggregateRating6; do not invent reviews |
| Merchant listings | 20 Nov 2026 | 6 valid, 0 invalid. Optional shippingDetails6, hasMerchantReturnPolicy6, validFrom6, identifiers/brand6, string-length name2 |
| Breadcrumbs | 20 Nov 2026 | 17 valid, 0 invalid, no issues90days. Historical data; current Rank Math breadcrumbs setting off and live category has no breadcrumb enhancement. Do not restore the old identical global breadcrumb schema |

Brand is now present in all6 live Product nodes and names are concise. These corrections require Google's next recrawl before historic warning counts update. Optional shipping/returns details are currently managed through Merchant Centre; public page markup does not supply these fields. See integration report for the integration gap.

## Representative Google live tests

Times below are copied exactly from GSC's display; its timezone was not labelled. Do not reinterpret them as India local time.

| URL | Tested21Nov2026 | Result |
| --- | --- | --- |
| Homepage | 15:02 | Available to Google; can be indexed; video detected; no enhancements |
| `/product/roun-groun-drop-set-pin-steel-construction/` | 14:56:41 | Smartphone inspection, crawl allowedYes, fetchSuccessful, indexingYes, self-declared canonical, 1 valid product snippet +1 valid merchant listing, optional warnings |
| `/product-category/gym-tools-equipments/` | 14:58 | Available to Google; can be indexed; no enhancements |
| `/my-account/` | 15:00:17 | FetchSuccessful/crawlYes, indexingNo due to robots noindex — expected, not a failure |

Stored Google index already lists homepage, drop-set pin and Gym category as indexed. Account stored last crawl15Nov2026 also excluded bynoindex. Live tests establish current accessibility/markup eligibility, **not guaranteed indexing, canonical selection, ranking or rich-result display**.

## Future checks / access minimization

Use `rankmath-reference.json`, metadata manifests and public audit before requesting another login. Search Console still needs occasional current report checks after recrawls, deployments and incidents. Investigate production connectivity against hosting logs around the period shown in Crawl Stats; confirm origin availability, resource limits and CDN/WAF handling of genuine Googlebot without weakening protections. Do not make DNS/firewall/access changes based solely on the aggregate warning.

Google references: [URL Inspection](https://support.google.com/webmasters/answer/9012289), [Sitemaps report](https://support.google.com/webmasters/answer/7451001), [Crawl stats/availability](https://support.google.com/webmasters/answer/9679690), [merchant listing structured data](https://developers.google.com/search/docs/appearance/structured-data/merchant-listing).
