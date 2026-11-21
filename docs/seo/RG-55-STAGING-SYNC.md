# RG-55 — Production SEO mirrored to staging

21 November 2026 (India). Owner explicitly authorized syncing every completed RG-53 production SEO correction to staging. This task changes staging only; production and Search Console were read-only. Source records: [RG-53 / PR #54](https://github.com/cepa-hq/ROUN-GROUN-Web-Task-Tracking/pull/54). Coordination: [issue 55](https://github.com/cepa-hq/ROUN-GROUN-Web-Task-Tracking/issues/55).

## Scope and result

All functional SEO settings, metadata and the title compatibility patch from RG-53 are now mirrored. Staging's environment URLs and crawl protection remain appropriate for staging. No new plugin, database replacement, theme change or tracking work.

| Surface | Action | Verification |
| --- | --- | --- |
| Existing Core Header `includes/shell.php` | Applied exactly the deployed RG-53 title hunk, with original full-file equality guard | Native editor success; full readback and read-only file tool confirm 4,275 bytes, exact production source |
| Rank Math General + Titles | Native guarded two-group import; production defaults included; native backup created | Complete fresh after-export equals target exactly |
| Sitemap + modules | Already matched production; omitted from import | Before/after groups equal production |
| Policy SEO | Four title/description pairs | Stored and rendered readback equals production |
| Product SEO | Six title/description pairs | Stored and rendered readback equals production |
| Brands | Created `product_brand` Roun Groun by slug `roun-groun`; resulting staging ID 47; appended to six products; primary brand 47 | Six memberships verified; no category memberships replaced |
| Stove primary category | Existing Camping term 20 selected as primary | Stored 20; memberships preserved |
| Category SEO | Gym 18, Camping 20, Home Gym 43 title/description pairs | All six stored values equal fresh native production snippet fields, including encoded ampersands |
| Archive exclusion | Individual Uncategorized 15 and brand 47 `rank_math_robots=[noindex]` | Stored and rendered readback; no global future-brand exclusion |
| Existing identity/security baseline | Logo/favicon, official socials, utility noindex, canonical aliases and legacy-markup removal checked | Public crawl and exact option parity; no credential/account or external Google changes |

All 21 published public records were matched by slug, including unchanged Shop/Home/About/Contact and utility pages. IDs happen to match; identity was not inferred solely from ID. All functional Rank Math postmeta keys were compared, not only the ten edited snippets. Calculated SEO scores, Content AI scores, analytics-object IDs and internal-link processing counters were deliberately excluded from synchronization; they are local caches/analysis, not search metadata.

## Exact environment differences

Four Rank Math URL values are mapped from production to staging:

- `general.attachment_redirect_default`
- `general.breadcrumbs_home_link`
- `titles.url`
- `titles.knowledgegraph_logo`

They use `https://testarea.roungroun.com` here; normal publication must map site URLs back to `https://roungroun.com`. Logo media was identified by the approved multicolour filename and matching local attachment 1532, not blindly replaced. Existing post social-image URLs were already correctly mapped. No production origin was forced into staging page canonicals.

Staging serves a physical robots.txt containing exactly:

```text
User-agent: *
Disallow: /
```

This file was not edited. Native Reading's “Discourage search engines” checkbox remains **unchecked** (its previous state); no save was made on that screen. Page metadata therefore retains its correct per-page index/noindex decisions for later publication, while the physical staging robots blocks crawling. Never copy this physical robots file to production. No Search Console sitemap submission, URL Inspection/indexing request, property, verification, DNS, association or permission changes were made.

## Verification and preservation

- Fresh production native General/Titles/Sitemap/Modules export and Pages-only/Products-only WXR snapshots confirmed that the RG-53 source had not drifted. Raw exports remain private; no customer/account/comment data was copied.
- Staging source originally 3,662 bytes matched the RG-53 pre-patch file. A fresh equality check guarded the edit; patched source exactly matched the 4,275-byte production source. SHA256 of production/staging source: `262e1bed8e36b5d8fa1b03fe58f9e0b60a7a0e0a2a895ab094cce2e5af587e92`.
- Native Rank Math import succeeded with a backup notice. General/Titles match the complete environment-mapped target; sitemap/modules remain unchanged. Credentials/OAuth/licenses/Google account connections were not copied. Native defaults and empty RSS key normalization match production as recorded in the settings diff.
- Stored SEO comparison: **zero functional postmeta differences across 21 public records** after hostname mapping; all 8 category/brand metadata writes and 6 memberships verified.
- Raw staging body, excerpt, title, slug, publish date and status remain byte-identical across all 21 records. Prices, stock, SKUs, attributes, default attributes and Product Studio `_rgps` remain unchanged. Targeted MCP writes avoided the classic editor's HTML/default normalization observed in RG-53.
- Only unrelated metadata change observed was generated homepage `_uag_page_assets.uag_version` timestamp; exact remainder of that serialized cache field is unchanged. It was not rolled back.
- Anonymous audit: **37 requests**,18 sitemap entries /17 unique public targets; **110 assertions passed**. All31 HTML responses have one HEAD title. All eligible pages return 200, one description and self-canonical. Production head parity passes after hostname mapping. All six Product nodes have factual Roun Groun brand/current INR offers/accurate stock; no fabricated aggregate ratings. Logo/favicon reachable. Correct utility/empty/redundant archives remain noindex; real404 and canonical sorting retained.
- Google field reports are not rerun for staging because it is intentionally crawl-blocked. Production read-only regression and browser results are recorded in the completion checkpoint.

The public HTTP audit is an SEO/source regression check, not a new Lighthouse/CWV score or a mobile redesign test. No approved CSS/JS/layout was edited.

## Checkpoints and branch

1. Read root AGENTS/workflow and inspected open issues through 54, two existing branches, PR #54 and recent main commits. No newly declared overlapping task. Website source repository still absent under the established native-admin/MCP workflow; this is the coordination repository. Unpublished concurrent changes cannot be seen.
2. Opened issue 55 before writes and branch `agent/codex-seo-staging-sync/rg-55-mirror` from main `ff256000f810d06e2db68bfae0105044153c7606`.
3. Captured full before settings/source and public-record/term metadata; declared exact 27 postmeta changes, 8 term values, 6 memberships and one-file patch. General/Titles only import excludes sitemap/modules and preserves environment behavior. Preparation's string-vs-number logoID assertion stopped file creation before any mutation; normalized type and verified both IDs 1532.
4. Applied source with fresh guard; snippet/brand/primary/category metadata writes; native import confirmed once. Its JavaScript confirmation was accepted after a short expected input timeout, not resubmitted.
5. Fresh export/readback/preservation checks; investigated and classified generated cache timestamp; public crawl and assertions passed. Checkpoints/manifests committed while work continued.
6. Final browser preview and production read-only regression; completion comment and review PR. Issue remains open for owner review. No PR merged and no new production deployment.

## Files and rollback

- [Metadata changes](RG-55-metadata-changes.json): public SEO values before/after; 27 key differences.
- [Settings comparison](RG-55-settings-diff-before.json): staging-before vs production reference; environment exceptions described above.
- [Term changes](RG-55-term-changes.json): six snippets and two robots fields.
- [Stored preservation](RG-55-preservation-results.json): record hashes and parity result.
- [Public audit](RG-55-public-audit-manifest.json) and [assertions](RG-55-public-verification-results.json).
- [Read-only production regression](RG-55-production-regression.json).
- [Exact deployed source hunk](patches/RG-55-shell-single-title.patch).

If a regression is confirmed, guard current values first and reverse only this source hunk or task-specific metadata/options. Native settings backup is available in staging Rank Math Import & Export. Do not restore an old whole database or overwrite another agent's later work. Newly added term snippet/robots keys were absent before this task; remove only those keys if rolling back. Brand assignments were empty; unassign only task-added memberships if still appropriate. Current source/plugin version remains Core Header 1.3.4; future whole-plugin packages must retain the hunk even when the displayed version number matches.

## Next publication

RG-53's warning that its corrections had not reached staging is now superseded by this verified sync. Future deployments still must preserve current live customers, orders, payments, reviews and media, and apply environment-aware URL/robots handling. Do not migrate staging business tables or its physical robots.txt over production. Keep live Google account verification/connections private and intact. SEO parity at this checkpoint is not a promise that an arbitrary future whole-database overwrite is safe.

Historical hosting connectivity, Google's failed extra recrawl submission, optional policy/variant/date enrichment and staged Rank Math maintenance remain the same documented follow-ups in PR #54; this sync does not implement unapproved future enhancements or change Google report history.
