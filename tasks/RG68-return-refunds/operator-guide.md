# Return and refund destination operations

On staging, go to **Return Request → Refund destinations**. The original native Requests list also has a refund-destination management link, and each native detail page has a destination postbox. Lists and customer pages show masked values. Old requests correctly show “Not provided” until the customer supplies instructions; no payment method or bank account is inferred.

## Customer flow

1. Choose refund destination while reviewing a new refund request: original payment (subject to availability), UPI or bank account. COD requires UPI/bank.
2. UPI asks for beneficiary name and UPI ID. Bank asks for beneficiary name, bank, account number twice, IFSC and confirmation of beneficiary details.
3. The confirmed instructions are encrypted and saved in the same SQL transaction as the request and selected item quantities. The customer sees their masked destination on request list/detail pages and can update it while the request is active and unlocked.
4. Account sign-in and ownership of both request and order are required. Customer changes are refused after completion/rejection or payout lock. Replacement requests do not collect refund details.

## When original-payment refund cannot be used

1. Open the request under Refund destinations.
2. Click **Generate customer form link**. Copy the displayed URL and send it through your normal customer-support channel. This screen does not send emails/messages automatically.
3. Link expires after 48 hours; generating another invalidates the earlier link. It requires the owning account to sign in. The secret is carried in a fragment and removed early from the address bar, with only a hash persisted server-side. It is consumed on successful submission.
4. The submitted choice immediately becomes the request's destination in backend and customer views. Reload the list to see it. Customers can use their normal authenticated request page to correct details while editing is open.

## Before and after a payout

- **Reveal beneficiary details** is a nonce-protected manager action recorded in the audit trail. Default views remain masked. Check the actual beneficiary before proceeding; validation does not verify ownership of a UPI/bank account.
- **Lock destination before payout** invalidates invitation links and closes customer editing. This release deliberately has no unlock action. If a locked destination needs correction, stop payout and arrange a reviewed technical correction; do not modify encrypted values by hand.
- Original payment selection is an instruction, not a gateway transaction. Issue gateway refunds through the existing WooCommerce/gateway controls, or make the approved UPI/bank payout through the normal verified payment process. This plugin never moves money or marks a refund paid.
- Use the existing native request status controls. Add a clear **Customer-visible decision / resolution** note here, especially for rejection. This note is visible to the customer, unlike internal admin notes.
- The encrypted audit retains the latest 30 destination events. This is a support audit, not an immutable accounting ledger or payment reconciliation system.

## Storage, access and backup

Full beneficiary details live only inside authenticated AES-256-GCM encrypted request records in private WordPress options (autoload disabled), tied to native request ID, Woo order ID and customer ID. New v2 writes derive their encryption key with HKDF from a random site-specific refund secret and the WordPress authentication salt. Legacy initial-format records remain readable and upgrade on their next write. Review payloads are encrypted too. Forms use server validation, ownership and CSRF checks; writes use version/CAS checks to refuse stale overwrites. No new public REST endpoint is added.

The database backup must preserve `rgr_refund_secret` and numbered `rgr_refund_*` records, and a separately protected configuration backup must preserve the original WordPress auth keys/salts used by `wp_salt('auth')`. Do not rotate these salts or copy ciphertext to another site's different salts without a tested decrypt-and-reencrypt migration. Do not delete/recreate the refund secret if records exist. A missing or wrong key fails closed. No bank details, invitation secrets or keys belong in GitHub tickets, emails, application debug logs or screenshots.

Destination records are retained with their request; this release does not automatically reuse them on another order, expose a general customer payment wallet or add a financial-data erasure schedule. Apply the store's reviewed retention/privacy policy when removing historic records. Staff access uses `manage_woocommerce`; restrict that role to trusted operators. Protect database/configuration backups and server access as well as the UI.

## Rollback

Back up the current database and original WordPress keys/salts first. Replace only the existing `roun-groun-request-centre` folder with the provided 1.0.5 rollback ZIP, retaining plugin activation. This restores old request presentation and removes the new UI; it does not delete native RMA requests or encrypted destinations. Existing saved destinations remain encrypted and require the newer code/key/configuration to read again. Restore 1.1.1 if those records are needed. Do not restore a whole-site database over other agents' work merely to roll back this plugin.

Staging manager-only diagnostic buttons can run isolated security checks and create a clearly marked disposable fixture. Cleanup validates its stored identity, removes only its request/destination and trashes its dummy order. These controls are hard-limited to the exact staging hostname; no real payout or customer email is part of this test workflow.
