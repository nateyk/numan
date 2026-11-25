# Numan Freight — release and payment-settlement safety

These checks operate on the **single-profile freight product**. The workspace row remains an internal billing/locking record; there are no workspace UI or reporting requirements.

## Before deploying the one-connection-per-freight migration

After updating the application code, and **before** running `php artisan migrate`:

```bash
php artisan freight:check-connections
```

Exit code 0 means no accepted freight post has multiple connection rows. Exit code 1 lists conflicting post IDs and their connection IDs. Compare each affected agreement and its audit trail and determine how to reconcile it. **Do not automatically delete one of the connections**: doing so could erase evidence of a real agreement.

The migration itself checks for duplicates and stops with an actionable message if the preflight was skipped. The unique index is a permanent database backstop against simultaneous acceptance.

## Unresolved manual transfers and profile deletion

A user with an open bank/wallet payment request cannot delete the profile until an administrator confirms or rejects that request. This avoids silently canceling a transfer that may have already moved money. A rejected, confirmed, or expired request no longer blocks deletion, but the financial evidence is retained for support and regulatory decisions.

Manual-payment review keeps its database lock until the complete decision and subscription fulfillment are committed. Notification sending and rejection-proof cleanup occur after commit.

## Chapa payments that may have arrived after an account was deleted

Deleting an account cancels its pending local attempts and prevents any late callback from silently granting a plan. **Canceling a local transaction does not refund a real payment at Chapa.**

Use the read-only review command to identify charges needing human financial follow-up:

```bash
php artisan chapa:audit-canceled --ref=NF-your-reference
php artisan chapa:audit-canceled --limit=100
```

A verified, amount-matched canceled charge is printed as **PAID AFTER CANCELLATION**. Investigate Chapa's dashboard/provider settlement and decide whether a refund or other remedy is appropriate. The command does not write to the database, grant a subscription, or call a refund endpoint.

The scheduled Chapa reconciliation deliberately limits itself to recent attempts. For a manual review of still-pending older attempts:

```bash
php artisan chapa:reconcile --all
```

This may verify and fulfill **active-account pending payments** that are older than two days. It skips locally canceled attempts. Run it only during an intentional financial review; it can call the Chapa API for every outstanding historical pending reference.

## Remaining operational decisions

- Agree on a formal refunds/disputes process with the payment provider and business owner; automated refunds are **not** implemented.
- Review the proposed PII retention policy in `docs/provider-data-retention.md` before enabling irreversible data scrubbing.
- CI verifies sequential request behavior and database constraints, but a real database concurrency rehearsal is still advisable before accepting high traffic.
