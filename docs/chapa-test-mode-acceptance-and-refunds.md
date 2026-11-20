# Numan Freight — Chapa Test Mode acceptance and refund/dispute operations

**Status:** Test Mode acceptance tooling and admin case management are implemented. **Genuine Chapa provider acceptance remains pending until two actual sandbox checkouts and webhook deliveries have been observed.** CI uses HTTP mocks and cannot substitute for that evidence.

## Important: the current Numan integration is Chapa v1

This repository currently calls `https://api.chapa.co/v1/transaction/initialize` and `/transaction/verify/{tx_ref}`, with ETB decimal amounts and v1-style `data.status`. It is **not** a Chapa v2 integration. The newer v2 host is `https://api.chapa.global` with `/v2/payments` APIs, amounts in integer cents, short merchant references, different status casing, and required idempotency keys. Do not switch the base URL alone or interpret a v2 refund event as a successful v1 payment.

A future versioned v2 migration requires separate initialization, verification, webhook-signature, idempotency and currency-unit acceptance, ideally behind a deliberately enabled flag. Do not use a live private key for this acceptance.

## Run genuine Test Mode acceptance

1. Use an isolated **test-mode** Numan environment/database with a real Chapa **test private key** in its server-side secret store. Set `CHAPA_MODE=test`, `CHAPA_BASE_URL=https://api.chapa.co/v1` for this **current v1 integration**, and configure a Test Mode webhook secret and externally reachable callback endpoint. Do not place a secret in git, screenshots, logs, command arguments or CI artifacts. Never use production subscriptions as test fixtures.
2. Log in as one single-profile test payer, open Numan Billing > Plans, purchase the Standard monthly duration through the real Chapa Test Mode hosted checkout, and complete Chapa's **simulated** payment flow. Save the local Numan `tx_ref`, not the secret key.
3. Confirm the provider callback or signed webhook arrived. Check the provider's Test Mode dashboard, that Numan stored a `verified` transaction, **one** subscription, **one** receipt, and restored any valid subscription-hidden freight posts. Never accept the browser redirect itself as evidence of payment.
4. Before the first subscription ends, buy the same plan/duration again with a **new** reference. Complete another real Test Mode hosted checkout. Confirm both transactions have receipts, the earlier subscription became `superseded`, and the new subscription begins at the old `ends_at` boundary (no lost paid days).
5. On the test environment, run the **read-only** provider-backed check:

   ```bash
   php artisan chapa:accept-renewal NF-FIRST-REFERENCE NF-RENEWAL-REFERENCE
   ```

   This command refuses live-mode configuration or non-test keys, verifies **both references directly against Chapa**, checks amount/currency/mode and local receipts, and asserts contiguous subscription periods. A successful run is evidence of genuine Test Mode verification **only if those checkouts really occurred through Chapa**. The command cannot independently prove a browser click or webhook delivery, so keep their dashboard and delivery IDs as separate QA evidence.
6. Repeat edge cases in Test Mode: declined payment, abandoned checkout, callback retried, duplicate webhook, Chapa network outage, account deletion, and renewal after expiry. Failed/pending attempts must not create receipts or entitlement. Record observed provider reference(s), case IDs and non-personal timestamps in the acceptance record.

**Release gate:** all acceptance steps and external provider evidence must pass before saying “Chapa sandbox accepted.” A green unit-test run or successful cPanel deploy is insufficient.

## Outstanding payment reconciliation

The scheduled `chapa:reconcile` checks recent pending/initialized Chapa payments every five minutes. It uses the same provider-verification and receipt-confirmation rules as the payer status check. A returned provider success without a committed verified transaction **and receipt** is never reported as fulfilled.

For an operator's **read-only** view of outstanding attempts, run:

```bash
php artisan chapa:reconcile --all --dry-run
```

The preview reads local payment records only: it **does not call Chapa**, decide whether a payment succeeded, activate a subscription, or update the database. It includes older attempts excluded from the normal 48-hour scheduled window. Treat the listed transaction references as payment data.

After reviewing the pending list and confirming an appropriate environment, use `php artisan chapa:reconcile --all` for an intentional provider-backed financial reconciliation. **This is not a dry run**: verified old payments can activate subscriptions and issue receipts. Chapa-confirmed terminal failures will remain failed without activating a subscription; ambiguous API failures remain pending. Canceled account charges require the separate `chapa:audit-canceled` human-settlement workflow.

## Refund and dispute review (superadmin)

Open **Admin → Marketplace Payments**, locate the Chapa transaction and open exactly one review case with a classified reason. The case is unique by transaction and has append-only decision history.

Case states:

- `open` → `investigating`
- `investigating` → `submitted_to_chapa` or `closed_no_refund`
- `submitted_to_chapa` → `provider_reported_refunded`, back to `investigating`, or `closed_no_refund`

`provider_reported_refunded` requires the Chapa refund reference. **It is a human attestation from the provider dashboard, not an API-verified settlement.** A provider refund request being accepted does *not* prove that the customer received funds; check the refund reference and final state in the provider dashboard or Verify Refund API before recording an outcome. Keep cumulative refunded amounts separate from the original payment receipt for partial refunds.

The review workflow **does not** initiate a refund, credit a customer, update a subscription, reverse a ledger entry, or automatically send a support email. Provider reversals, partial refunds, chargebacks, disputed paid periods, and manual account corrections need the business owner's explicit policy. Reviewers cannot review their own charges. Duplicate case-opening requests do not create duplicate histories. Do not paste payer names, phone numbers, bank details or screenshot paths into a refund reference.

For late charges after account deletion, start with the existing **read-only** reconciliation check:

```bash
php artisan chapa:audit-canceled --ref=NF-REFERENCE
```

Then open a `paid_after_cancellation` review case. Never turn a canceled account back on just because Chapa reports a late payment.

## Historical payment data retention

The proposed 90-day response and 180-day webhook-payload retention windows in `docs/provider-data-retention.md` are **not approved and not enabled**. Before activating any scrubber, finance/legal owners must approve jurisdiction-specific retention durations and rules for open disputes, provider investigations, refunds, chargebacks, tax records, privacy access controls and legal holds. Pending review cases must be excluded from irreversible scrubbing until a retention decision is made. Financial transaction/receipt rows are not candidates for automatic deletion in the proposed scrubber.

Do not activate a production retention flag or run destructive historical migrations on the basis of this document alone.
