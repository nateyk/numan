# Provider data retention — design, not implemented

Status: **proposal for a decision.** No command exists yet, no migration, no flag.
Nothing in the running system depends on this document.

It settles §12b of `docs/production-runbook.md`, which records the decision as
still open.

---

## What is actually being kept

Two columns store raw provider payloads. Both are `longtext` and both are written
whole, with no filtering at write time:

| Table | Column | Written by | Contains |
|---|---|---|---|
| `chapa_webhook_events` | `payload` | `ChapaWebhookController::handle()` — `json()->all()` | The whole webhook body: event name, tx_ref, Chapa reference, amount, status, and the payer's **first name, last name, email and phone number** |
| `marketplace_payment_transactions` | `provider_response` | the Chapa verify call inside the same handler | The verify response: status, reference, amount, and again the payer's **name, email and phone number** |

The controller is explicit about this. It refuses to log either body, and its
comments say why: *"A Chapa webhook carries the payer's first name, last name, email
and phone number"* and *"the payer's name, email and phone number, which have no
business in a log file that outlives the request."*

So the write path already treats this data as sensitive. It is stored anyway, without
a stated retention period, indefinitely. That is the gap this closes.

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
- `payload_hash` needs adding at receipt time, so this design is only fully
  implementable before the scrub exists.
- The volumes are unknown. The dev database has zero rows in any of these tables, so
  nobody can currently say how much space this reclaims or how long the first run
  takes. The rehearsal against the real dump answers both, and the harness already
  reports the byte totals.
