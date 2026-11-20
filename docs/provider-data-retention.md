# Provider data retention — pending business decision

Status: **new-write minimization implemented; historical retention and deletion NOT approved or enabled.** A migration stores SHA-256 fingerprints for new signed webhook deliveries, and model write boundaries strip payer identity and nonessential nested data before persisting new Chapa webhook/verification summaries. Existing rows with old raw payloads remain unchanged.

There is still **no automatic scrub command, purge schedule or enabled retention flag**. The proposed day limits below are recommendations awaiting review, not an active policy.

It settles §12b of `docs/production-runbook.md`, which records the decision as
still open.

---

## What is actually being kept

Historically, two columns stored raw provider payloads, including personal data. New model writes now store only whitelisted accounting fields, but historical rows may still contain the original full payloads:

| Table | Column | Written by | Contains |
|---|---|---|---|
| `chapa_webhook_events` | `payload` | `ChapaWebhookController::handle()` | Legacy rows may contain the full webhook body, including payer identity. New rows store event/transaction/status/amount/currency summary fields only. |
| `marketplace_payment_transactions` | `provider_response` | Chapa initialize/verify and fulfillment | Legacy rows may contain payer identity and checkout URLs. New Eloquent writes retain only safe accounting fields. |

The controller is explicit about this. It refuses to log either body, and its
comments say why: *"A Chapa webhook carries the payer's first name, last name, email
and phone number"* and *"the payer's name, email and phone number, which have no
business in a log file that outlives the request."*

A third, new `chapa_webhook_deliveries.payload` column stores **only sanitized payment/refund metadata** for distinct signed deliveries. Its `payload_hash` is a fingerprint of the original authenticated bytes, not raw payer data. This table does not backfill historical event deliveries. Its retention and legal-hold behavior are also awaiting approval; the 90/180-day proposal below does not silently authorize purging this new evidence.

The write path now minimizes newly stored provider content. A separately approved retention process is still needed to deal with already-stored personal data and implement a maximum lifetime.

## Open refund/dispute cases are a legal-hold decision

The admin review queue records one case per transaction and its append-only status history. **Do not turn on historical payload scrubbing for transactions with open or provider-pending cases** until finance/legal owners approve the applicable hold conditions. Cases marked `provider_reported_refunded` contain a human attestation and a provider reference, not automatic refund verification.

The current case queue does not activate or change this retention policy, and it does not alter original receipts or charge data. Do not treat this page as authorization to delete financial evidence.

## The important distinction

**The payload is not the accounting record.**

The financial record of a payment is already denormalised into
`marketplace_payment_transactions`: `amount_etb`, `tx_ref`, `provider_reference`,
`status`, `provider`, `billing_interval`, `created_at`, `verified_at`. That row is
small, structured, and holds no third-party personal data — it is the thing a tax
authority or an accountant would ever ask for.

The payload is a debugging and dispute artifact. Once a webhook has been processed
and the transaction settled, the useful fields in the payload are the ones already
copied out of it.

**This is what makes an aggressive retention window defensible.** You are not
proposing to delete financial records on a timer. You are proposing to stop keeping a
verbatim copy of somebody's identity long after it stopped being useful.

If someone proposes a much shorter window than below, the reason to refuse is not
regulation — it is that the blob is all that remains if a customer's chargeback
arrives and the denormalised fields were wrong.

## Proposed windows

| Column | Proposed | Reasoning |
|---|---|---|
| `marketplace_payment_transactions.provider_response` | **90 days** after `verified_at` (or `created_at` where null) | Its only job is to evidence that Chapa confirmed the payment at the moment of fulfilment. After the settlement period closes that evidence lives in the transaction row. Shortest window of the two because the value is already fully denormalised. |
| `chapa_webhook_events.payload` | **180 days** after `created_at` | Longer, because an unparsed or disputed webhook may need investigating well after the fact, and a chargeback arriving late is the realistic scenario. Still short compared with how long these rows otherwise sit. |
| `chapa_webhook_events.signature` | **never** | See below — this is the audit evidence. |
| `chapa_webhook_events.status`, `processed_at` | **never** | Cheap, and the record of whether an event was handled. |
| `marketplace_payment_transactions` row itself | **never, by this command** | This is the financial record. Retention for it is a finance decision, not an engineering one, and it is out of scope here. |

The 180/90 split is a judgement, not a derivation from a regulation. I am
deliberately not citing a statutory retention period for Ethiopian commercial records
from memory — get the number confirmed by whoever owns that decision, and treat the
windows above as the recommendation to put to them.

## Redact, do not delete

Deleting the column throws away the shape of the payload, which is the part that
makes a bug diagnosable. Proposing instead: **replace the payload with the keys and
non-identifying values, keeping the structure.**

```jsonc
// before
{"event":"payment.success","tx_ref":"NF-…","reference":"ch-…",
 "first_name":"Abebe","last_name":"Kebede",
 "email":"abebe@example.com","phone":"+251911234567","amount":"449.00"}

// after 90 or 180 days
{"event":"payment.success","tx_ref":"NF-…","reference":"ch-…",
 "amount":"449.00",
 "_redacted":["first_name","last_name","email","phone"],
 "_redacted_at":"2026-10-05T00:00:00Z","_schema":"chapa.payment.success/1"}
```

What this buys:

- The event name, transaction reference, amount and shape survive, so "why did this
  webhook not fulfil a subscription" is still answerable a year later.
- The identity fields do not.
- An attacker who dumps the table gets transaction references and amounts, not a
  phone-number list.

Delete-as-null is the fallback if redacting proves fiddly against a payload shape
that keeps changing. It is strictly worse and should be a deliberate choice, not the
easy one.

## The signature problem

`chapa_webhook_events.signature` is kept forever, but signature verification needs
the payload. Once the payload is redacted, **a webhook can no longer be
re-verified** — which means the signature alone is only evidence for someone who
already trusts this database.

If the ability to re-verify matters (it should, if you will ever be asked "was this
webhook really from Chapa?"), the fix is cheap and belongs in the same design:

```php
// written at receipt, never redacted
$webhookEvent->update(['payload_hash' => hash('sha256', $rawBody)]);
```

A hash is not reversible, so it costs nothing in privacy, and it lets a stored
signature be checked against the original bytes for as long as you keep the row.
**This should be added when the scrub command is built, not after** — once the
payloads are gone, hashes cannot be retrofitted.

## The command, if built

Shape only. Not implemented.

```
marketplace:scrub-provider-data [--dry-run] [--days-webhook=180] [--days-response=90]
```

Requirements, in the order they matter:

1. **`--dry-run` always works**, regardless of any flag. Same rule as every other
   data-changing command here: the flag gates the scheduler registration only.
2. **Disabled by default**, behind its own flag — `MARKETPLACE_RETENTION_ENABLED`.
   Separate from `MARKETPLACE_EXPIRY_ENABLED` and
   `MARKETPLACE_REMINDERS_ENABLED`, for the same reason: this one irreversibly
   destroys data, which is a different kind of decision from either of those.
3. **Daily, `withoutOverlapping()`.** Retention is a background chore. Hourly would
   mean an hourly scan of a table that changes a few times a day.
4. **Chunked by primary key**, not by `LIMIT`. A redacting UPDATE over a whole table
   holds locks and bloats the redo log; walking ids in blocks of ~500 keeps each
   transaction short.
5. **Idempotent.** Redacting an already-redacted row is a no-op, and running it twice
   must not double-append to `_redacted`.
6. **Logs counts and column names only.** No payload fragments, no email prefixes.
   The standing `LogHygieneTest` parses every `Log::` call in `app/` and will reject
   anything that looks like contact data — keep it that way.
7. **Bounded per run.** Process at most N rows per invocation and say so in the
   output, so a first run over a large table does not run for hours and hold a
   scheduler slot.

## Why not a migration

A migration runs once. Retention is a recurring obligation, and a recurring process
written as a migration gives you a rollback that cannot work and a second run that
does nothing.

The counter-argument is real, though: a migration is the only way to act on data that
already exists, and the command will act on it the first time it runs anyway. If the
decision is "get rid of this now, once", a migration is the honest expression of
that. If it is "never hold this for longer than N days", it is a scheduled command.

My recommendation is the command, with `--dry-run` run against the production copy
first — which the rehearsal harness can do without touching production.

## What this depends on

- The retention numbers need a business owner's sign-off, not an engineer's.
- `payload_hash` is captured on new signed deliveries. Existing rows cannot be retroactively assigned a verifiable raw-body hash once the exact original bytes are unavailable.
- The volumes are unknown. The dev database has zero rows in any of these tables, so
  nobody can currently say how much space this reclaims or how long the first run
  takes. The rehearsal against the real dump answers both, and the harness already
  reports the byte totals.
