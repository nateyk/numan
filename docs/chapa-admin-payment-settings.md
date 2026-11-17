# Numan Freight — Chapa v1 payment settings

## Configure from the admin dashboard

Open **Admin → Marketplace → Chapa Settings** (`/admin/chapa-settings`). Only an authenticated superadmin can access or update the page.

1. In the Chapa dashboard's **Settings → API Keys**, copy the public key and secret key for your selected environment. Test keys must be selected for **Test mode**; live keys require the **Live mode** selection. Switching modes requires a complete new set of keys and a webhook secret.
2. In the Chapa dashboard's **Webhooks** section, register the URL shown in Numan's **Webhook endpoint** field (normally `https://YOUR_HOST/webhooks/chapa`). Set a long random webhook secret hash in Chapa, and enter **the identical value** in Numan's Webhook secret field.
3. Save settings. Saved keys are encrypted in the database using Laravel's `APP_KEY` encryption key and are never returned to the settings page. The status indicators show only **Saved securely**, **From server environment**, or **Not configured**.
4. Leave a key field empty when editing to **keep** the current value. A filled key overrides the corresponding `CHAPA_* ` environment fallback. Existing `.env` credentials continue working until you save an override.
5. Test using Chapa's **Test mode** hosted checkout, webhook delivery and independent transaction verification. **Saving a key does not prove it is valid**; there is no real payment/test transfer triggered by clicking Save.

The existing Numan integration remains **Chapa v1**, with the API base URL from `CHAPA_BASE_URL` (default `https://api.chapa.co/v1`). Do not paste v2 credentials and change the host hoping the integration will become v2. Checkout and webhook processing still use the existing v1 contract.

## How webhook authentication works

Numan accepts a Chapa-signed POST on `/webhooks/chapa` with no buyer session, after verifying a valid `x-chapa-signature` (HMAC-SHA256 of raw payload) **or** `chapa-signature` (HMAC-SHA256 of the shared signing secret). Missing/wrong signatures are rejected.

A signature alone never activates a plan: Numan re-checks Chapa v1's verification endpoint, ensures amount/currency/reference/mode match the stored transaction, and fulfills idempotently.

## Security and operational notes

- Save no API keys or webhook secrets in source code, GitHub PRs, log files or screenshots.
- Keep the application's `APP_KEY` secure and backed up. Losing it will prevent decrypting the stored overrides.
- When rotating the webhook secret, update Chapa's dashboard and Numan together. In-flight events signed with the old secret can be rejected; background reconciliation can verify outstanding payments.
- The public key is stored for configuration completeness, but Numan's existing **hosted checkout authenticates with the secret key only**. The public key is **not** injected into browser JavaScript.
- If database settings are not yet present, Numan uses the existing `CHAPA_PUBLIC_KEY`, `CHAPA_SECRET_KEY`, `CHAPA_WEBHOOK_SECRET`, and `CHAPA_MODE` environment configuration.
- Do not activate Live mode without first completing genuine Chapa Test Mode checkout-to-renewal acceptance (see `docs/chapa-test-mode-acceptance-and-refunds.md`).

Reference: [Chapa webhook signature documentation](https://developer.chapa.co/integrations/webhooks).
