# RG-57 progress — 22 November 2026

Merchant listing enrichment: prepared 16-offer supplemental XML and committed before upload. Prior upload never executed because automatic browser review hit usage limit. Today browser sessions GMC and production are authenticated; staging native admin is not authenticated and redirects to /404/ due to protected login. MCP still reaches staging.

Current GMC inventory 18 approved: same16 API offers +2 automatically discovered (whistle, newly discovered drop-set pin parent). Preserve additional entries pending deduplication investigation. New supplemental file uploaded and Create data source clicked today; verify processing, errors, effective attributes and manual override precedence before reporting complete.

Migration: official Woo docs say Google for WooCommerce3.8.1+ uses Merchant API while source name may remain Content API. Existing plugin2.9.9 on production/staging, Woo10.1.4. Vendor header3.8.1 requiresWoo10.7+, latest3.10.1 requiresWoo11.1+. Updating Google alone is invalid. Stage supported Woo+Google updates with backups and checkout regression before live. No updates performed yet. Do not invent custom Cloud credentials or enable Ads.

TrackingRG44/45, commerce data, approved page layouts and existing identifiers/prices/stock remain excluded. Gitissue57 and branch agent/codex-merchant-visibility/rg-57-audit are current. Source XML and planned attributes already committed34770543 /8e0eac76.

Update: source processing now 16 updated/16 matched, all names recognized/no file issues. Regular text still takes precedence over structured text; conditional attribute rules and manual override cleanup not yet deployed. Latest plugin requirements confirmed from official ZIP headers. No Woo or plugin changes yet.
