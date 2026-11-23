# RG-59 — Merchant API, search wording and exact-variant checkout links

Checkpoint: 23 November 2026. Production and staging implementation completed; Google-controlled checkout display remains unverified.

Issue: https://github.com/cepa-hq/ROUN-GROUN-Web-Task-Tracking/issues/59

## Merchant API status

Google for WooCommerce 3.10.1 already uses Merchant API. RG-57 verified a full 16-offer `MapiProductInputsService::insert_many` batch: 16 succeeded, zero failed. This task also observed fresh Merchant API insert successes for all 12 currently in-stock offers. Four out-of-stock offers remain approved existing records; stock was not changed to force resubmission.

The existing source and stable `gla_` offer IDs were preserved. Its GMC **Content API** source-type label is legacy dashboard text, not evidence that transport is still Content API. WooCommerce explicitly documents this behavior: https://woocommerce.com/document/google-for-woocommerce/

GMC still has one separately Google-discovered variable-parent record in addition to the 16 WooCommerce offers. It was not recreated, archived or converted into a duplicate API parent. An unselected variable parent cannot safely receive a direct-checkout link.

## Website change and incident rationale

The rejected account template was `/checkout/?add-to-cart={id}&quantity=1`. Google substitutes an offer such as `gla_1101`, whereas WooCommerce's native action expects a numeric product/variation ID. In addition, native mutation-query reloads can add another quantity. This is related to the historical RG-7 concern about multiple add-to-cart handlers adding quantity two; the new flow has one cart writer and exits before WooCommerce's native priority-20 handler.

Edited only the existing Checkout Studio:

- `includes/cart.php`: two registration hooks added to `RGCK_Cart::boot`; isolated `RGCK_Merchant_Entry` class appended. Existing AJAX cart operations unchanged.
- `roun-groun-checkout-studio.php`: version 1.3.4 → 1.3.5. No other main-file changes.

No new plugin, payment integration, theme, visual asset or tracking-script changes.

### Entry contract

Example checkout URL: `https://roungroun.com/?rg_checkout_offer=gla_1101&quantity=1`.

For cart instead: append `&rg_destination=cart`. Numeric Woo IDs are also accepted. A variable parent requires all of its `attribute_*` selections; an exact variation ID derives its fixed attributes from WooCommerce. For example, the band parent 1099 can receive `attribute_colour=Beige%2FBlack`; exact offer `gla_1101` does not need that additional input. Missing, conflicting or invalid selections fail instead of guessing a variation.

The handler rejects malformed IDs/arrays, non-public/hidden/password-protected items, unavailable variations, invalid quantities, insufficient stock and failed native add-to-cart validation. Quantity is a whole number from 1–99. It preserves unrelated cart lines and ensures at least the requested quantity of the exact matching item; reopening the link does not keep incrementing an already-present item or reduce an existing higher quantity. Native cart/checkout stock and payment validation remain authoritative.

Successful requests issue a local HTTP303 redirect to a clean cart/checkout URL. Mutation parameters disappear; standard allowlisted Google/UTM attribution survives. Entry responses are no-store and noindex. Arbitrary redirect destinations are never accepted. No login is needed, and this endpoint cannot create, cancel or pay for an order.

### Merchant product attributes

The supported `woocommerce_gla_product_attribute_values` hook appends `checkout_link_template` through the Merchant API adapter's custom-attribute methods. It retains unrelated custom attributes and normal feed attributes. Exact eligible simple/variation products receive their own URL; unavailable offers and unselected variable parents do not. This is dynamic, so future supported products and stock changes follow the same logic without maintaining a static ID list.

Verified native GMC raw-source rows for both simple `gla_938` and variation `gla_1101` contain the correct checkout URL. GMC tables virtualize rows: initially missing lower rows are not proof that an attribute is absent. Scrolling the relevant table into view exposed the checkout and ID rows.

## GMC checkout configuration and remaining uncertainty

Removed the rejected account-wide URL. Saved Google's documented feed-only configuration: Include URL, blank account URL textbox, Show in ads selected. Reopening confirmed the empty textbox and persisted Show in ads selection; Save was disabled because there were no further changes. Existing ad campaigns remain paused/removed; none were activated.

The Business info summary nevertheless says **opted out**, and the initial radio presentation is derived from the blank account URI. Therefore this report does **not** assert that a checkout button is live or approved. The website behavior and product attributes are verified; Google's rendering/review state is not. Do not add an account template alongside product-level URLs just to change this summary.

Official instructions expressly require the account textbox to remain blank when product feed URLs are supplied: https://support.google.com/merchants/answer/13580733?hl=en

Google determines checkout-button display and listing placement. Product approval does not guarantee impressions, ranking or a checkout button. No private API credentials were created or broadened for this work.

## Search wording

Updated Rank Math SEO titles and focus terms on all six parent products in both environments. Product bodies and excerpts were preserved exactly in the native production editor, and public HTTP checks confirmed all six return200 with the new title, index/follow robots, original canonical and original description. Focus terms are editorial guidance; they are not a Google meta-keyword ranking signal.

| Product | SEO title | Focus terms |
|---|---|---|
| 77 | Gym Drop-Set Pin for Weight-Stack Machines &#124; Roun Groun | drop set pin; gym drop set pin; weight stack pin |
| 938 | Liquid Chalk for Gym & Weightlifting, 50 ml &#124; Roun Groun | liquid chalk; gym liquid chalk; weightlifting chalk |
| 1044 | Titanium Safety Whistle for Hiking & Camping &#124; Roun Groun | titanium safety whistle; hiking whistle; camping whistle |
| 1063 | White PVC Beach & Shopping Tote Bag &#124; Roun Groun | PVC tote bag; beach tote bag; shopping tote bag |
| 1099 | Latex Resistance Bands Set of 5 for Home Gym &#124; Roun Groun | latex resistance bands; resistance bands set of 5; home gym resistance bands |
| 1197 | Portable Butane Gas Camping Stove &#124; Roun Groun | butane camping stove; portable gas stove; camping stove |

Updated the existing 16-row supplemental XML with factual structured-title improvements while preserving variant diameter/colour. AI-assisted structured-title disclosure retained. Verified that no other item field changed: IDs, categories, groups and descriptions identical to RG-57. No price, availability, image, link, GTIN or MPN override was added.

Uploaded to the existing enrichment source. GMC confirmed 16 updated /16 matched, all attribute names recognized, no file issues. Updated structured titles were observed in the actual chalk and band product details; the product list can lag behind detail output. Final grid inspection showed all17 existing records approved.

## Validation

- PHP syntax passed. Standalone tests: 35 behavior assertions passed.
- Staging: eight anonymous HTTP cases passed; production: the same eight passed. Exact variation/simple add; repeat quantity1; unrelated-item preservation; zero quantity, malformed ID, missing attributes and out-of-stock rejection; guest checkout and attribution passthrough.
- All entry responses303 +no-store/noindex; clean destinations verified.
- Production band retested after SEO saves: Beige/Black quantity1; its disposable test-cart line removed.
- Test carts emptied; no order/payment submitted and no customer/order/review data changed.
- Final staging MCP and production native-editor reads matched deployed source. No new staging PHP error mentioning this class.
- No visual changes were made. Existing responsive cart/checkout presentation is reused; real-device browser testing was not newly performed in this task.

Machine-readable evidence and source SHA256 values: `RG59-verification.json`. Test harness: `source/test-merchant-entry.php`. Production/runtime dependencies and private logs are not committed.

## Rollback and continuity

Restore the two original plugin files under `rollback/` to remove this bridge. Re-upload RG-57's enrichment XML to revert Merchant titles. Revert only the six Rank Math title/focus fields if required; preserve canonicals, descriptions and robots. Do not restore a full database over new orders/reviews/customers, and do not restore the broken account checkout template.

Prior SEO/migration work remains on PR58; this branch is a separate checkpoint from main and does not merge or overwrite that PR. No automatic follow-up or scheduled monitoring was created.
