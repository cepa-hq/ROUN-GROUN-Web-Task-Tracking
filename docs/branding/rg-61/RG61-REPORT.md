# RG-61: Approved transparent RG favicon at 115%

Deployed to production and staging on 23 November 2026. Owner explicitly authorized media upload and native favicon/site-icon changes on both sites. No website source, header wordmark, Rank Math organization logo, tracking, commerce, customer data or global image-size settings changed.

## Asset and framing

Used the approved revised RG from the existing reusable logo-testing HTML. Applied a deterministic centered 115% transform, baked into the raster rather than relying on page CSS. Preserved transparent surrounding space; no solid matte, circle, border or shadow was added. PNG was selected for Google's documented supported favicon formats.

Local optimized 512 PNG was 97,416 bytes before upload. Native exact-512 selection initially skipped site-icon-specific derivative generation, causing 100/300px images to be used in links declaring 32/192. Corrected through the native WordPress site-icon crop workflow with a 1024 square master of the same 115% artwork. Confirmed the crop selected the FULL square: 764x764 selection equaled the 764x764 displayed image. This generated real 32/192/180 sizes without further framing changes. No code patch or new plugin needed.

The existing Imagify plugin automatically optimizes uploaded/generated media. Final public encodings differ from local lossless encoding and slightly between environments; do not claim bit-identical or lossless final delivery. Visible framing is the same approved 115% design. Larger source files are retained in Media Library; the actual page favicon links fetch the small derivatives below.

## Final production

Native General Settings returned Settings saved. Live browser homepage DOM and anonymous HTTP checks point to:

- 32px: https://roungroun.com/wp-content/uploads/2026/11/cropped-roun-groun-rg-favicon-115-master-32x32.png — 1,291 bytes.
- 192px: https://roungroun.com/wp-content/uploads/2026/11/cropped-roun-groun-rg-favicon-115-master-192x192.png — 8,356 bytes.
- Apple 180px: https://roungroun.com/wp-content/uploads/2026/11/cropped-roun-groun-rg-favicon-115-master-180x180.png — 7,490 bytes.

## Final staging

Native General Settings returned Settings saved; MCP site_icon readback is 1656.

- 32px: https://testarea.roungroun.com/wp-content/uploads/2026/11/cropped-roun-groun-rg-favicon-115-master-32x32.png — 1,288 bytes.
- 192px: https://testarea.roungroun.com/wp-content/uploads/2026/11/cropped-roun-groun-rg-favicon-115-master-192x192.png — 7,485 bytes.
- Apple 180px: https://testarea.roungroun.com/wp-content/uploads/2026/11/cropped-roun-groun-rg-favicon-115-master-180x180.png — 6,784 bytes.

Initial staging 512 upload is attachment1654, retained. Final native crop is attachment1656. Old staging site_icon1532 retained. Production prior icon filename was cropped-Multicolour-Gradient-RG-Logo.webp; its hidden setting ID was redacted by the browser and was not bypassed. Roll back through General Settings > Site Icon by choosing that retained original file; staging may select1532. No media was deleted.

## Validation and limits

All six final public image requests returned HTTP200 image/png, with exact declared pixel dimensions and an alpha channel including fully transparent pixels. Public favicon declarations now reference the approved asset on the correct respective hostname. Source IDs and generated paths are available in sanitized public-verification.json with SHA256 values. Both native settings saves were observed; production homepage was retained for review. No Google Search Console action was performed and staging was not submitted for indexing.

Google controls recrawl timing, favicon selection and any interface-supplied circular frame/background. Updating native rel=icon links makes the file available for Google, but does not prove Google's cached search result has refreshed or guarantee identical presentation. Browser cached favicons may also persist until refreshed. Do not promise frame removal, instant Google propagation or identical pixels after automatic image optimization.

Issue: https://github.com/cepa-hq/ROUN-GROUN-Web-Task-Tracking/issues/61
Documentation branch: agent/codex-favicon/rg-61-deployment. Repository is coordination only; no website-code repository edits.
