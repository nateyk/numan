# Production runbook — marketplace subscriptions, payments and listing visibility

Everything needed to deploy Steps 1–6 in order. Read top to bottom the first
time; after that, jump to the section you need.

The marketplace work in this repository replaced a single-plan model with one paid
plan in three durations, an admin-editable Free state, ETB-only payments by Chapa
or manual transfer, metered contact reveals, and automatic hiding of listings that
exceed the plan. Most of it is inert until you turn flags on. **Nothing in Steps
1–6 changes behaviour on deploy except the migrations.**

| Step | What it is | Inert until |
|---|---|---|
| 1 | Payment integrity bugs (`ends_at`, `billing_interval`) | deploy |
| 2 | Unique constraints on `chapa_tx_ref` and `receipts.transaction_id` | deploy |
| 3 | One `standard` plan, admin-editable limits, status normalisation, `hidden_at` | deploy |
| 4 | Contact-reveal ledger and metering; server-side contact stripping | backfill (§4) |
| 5 | Manual payment (Telebirr / CBE Birr / bank transfer) | configure an account (§7) |
| 6 | Renewal and expiry reminders | **not built — design only** |

---

## 1. Before you touch anything: back up

The migrations in §3 write to real rows. Take a database backup *and* a copy of
`storage/app/private` — the second is the uploaded payment proofs and transporter
documents, which no database dump contains.

```bash
# database — pick the one that matches DB_CONNECTION
pg_dump  -Fc "$DATABASE_URL"  > nf-pre-marketplace-$(date +%Y%m%d%H%M).dump
mysqldump --single-transaction --routines "$DB_NAME" > nf-pre-marketplace-$(date +%Y%m%d%H%M).sql

# uploaded documents
tar czf nf-private-$(date +%Y%m%d%H%M).tar.gz storage/app/private

# confirm both are non-empty before continuing
ls -lh nf-pre-marketplace-* nf-private-*
```

Verify the dump restores somewhere before you rely on it. A backup you have not
restored is a hypothesis.

## 2. Flags that must stay off

There is **one flag, not two.** Worth stating explicitly, because believing a
command is scheduled when it is not is the failure mode this section exists to
prevent:

```dotenv
# One flag gates all three scheduled marketplace commands:
#   marketplace:expire-subscriptions        (hourly)
#   marketplace:expire-manual-payments      (daily 03:30)
#   marketplace:send-reminders              (Step 6, not built yet)
MARKETPLACE_EXPIRY_ENABLED=false
```

There is no separate manual-expiry flag. They were deliberately kept behind a
single switch so there is exactly one thing to forget.

**Keep it `false` through this entire runbook.** With it off:

- `php artisan schedule:list` does not show any of them, and the scheduler never
  runs them. This is the check, not the comment.
- The commands still work when invoked by hand, so §4–§6 can all be run for real
  without the flag.

```bash
# marketplace only — chapa:reconcile IS scheduled regardless of the flag
php artisan schedule:list | grep marketplace || echo "OK: nothing marketplace is scheduled"
```

`chapa:reconcile` runs every five minutes and is deliberately **not** gated: it
only inspects and never changes entitlement. So `schedule:list` showing a chapa
entry tells you nothing about the flag.

A DDEV or local scheduler runs `schedule:work` continuously. Before this flag
existed the hourly expiry sweep ran unattended against the development database
and flipped a demo subscription to `expired`. Leave the flag off in development
unless you mean it.

## 3. Migrations, in order

Deploy the code first, then migrate. The migrations are ordered by filename and
**the order matters** — two of them read a column that a later one creates.

```bash
php artisan migrate --force
php artisan migrate:status | tail -30
```

### Migrations that change data

Everything else is `CREATE TABLE`, `ADD COLUMN` or `ADD UNIQUE INDEX`. These
five rewrite rows. Read this table before you run it.

| Migration | What it changes | Reversible? |
|---|---|---|
| `2026_10_02_000002_backfill_marketplace_subscription_ends_at` | Fills `ends_at` from `current_period_ends_at` on every marketplace subscription, and backfills `billing_interval` on every payment transaction | No — `down()` is deliberately empty |
| `2026_10_03_000004_archive_legacy_marketplace_plans` | Sets `is_active = false` on every plan except `standard` | Yes |
| `2026_10_03_000005_normalize_status_columns_to_enum_values` | Rewrites `status` values on marketplace tables to the lowercase enum cases | Yes |
| `2026_10_04_000001_scrub_contact_data_from_activity_log` | Removes contact values from existing `activity_log.properties` | Idempotent; rows kept |
| `2026_10_04_000003_backfill_payment_transaction_provider` | Sets `provider = 'chapa'` on transactions created before the column existed | Yes |

Notes on the four that surprise people:

- **`ends_at` backfill.** Runs *after* the column migration, which is why the
  order is fixed. `ends_at` is what every expiry decision reads. Before this
  backfilled, paid subscriptions had a null `ends_at` and never expired.
- **Status normalisation.** MySQL/MariaDB compares strings case-insensitively,
  which is why `Active` and `active` both worked until something compared them in
  PHP. Any value that does not map to a known enum case is **left alone**, not
  deleted. Count the rows afterwards (§4).
- **Activity-log scrub.** `Profile` and `TransporterProfile` used `logFillable()`,
  which logs the value of every fillable attribute, and both are fillable with
  `phone`; the transporter profile also with `whatsapp`, `email` and
  `legal_name`. Every profile write had been copying contact data into
  `activity_log.properties`. This removes the values and **keeps the rows** — the
  log still records that a profile changed, by whom and when. Deleting the rows
  would destroy the audit history, which is the opposite of what an audit log is
  for.
- **Provider backfill.** `provider` is `NOT NULL DEFAULT 'chapa'`, so it cannot
  be nulled to simulate the pre-migration state. Every transaction that predates
  manual payment *was* Chapa, so `chapa` is the correct value for all of them.

### Verify after migrating

```bash
php artisan tinker --execute="echo 'subs null ends_at: '.DB::table('marketplace_subscriptions')->whereNull('ends_at')->count();"
php artisan tinker --execute="echo 'tx null interval: '.DB::table('marketplace_payment_transactions')->whereNull('billing_interval')->count();"
php artisan tinker --execute="echo 'active plans: '.DB::table('marketplace_plans')->where('is_active',true)->pluck('slug')->implode(',');"
php artisan tinker --execute="echo DB::table('marketplace_subscriptions')->distinct()->pluck('status')->implode(',');"
```

The status check is not optional: `marketplace_subscriptions.status` drives
`activeMarketplaceSubscription()` and therefore entitlement, and the normalisation
migration leaves any value it cannot map untouched rather than deleting it. A
status you have not seen before means a row that behaves as if it had no
subscription — investigate before going live.

Expected: both counts `0`; every status lowercase. Active plans must read
`standard` — see the seeding step below, because `migrate` will not create it.

### Seed the plan — do not skip this

**`migrate` does not create the plan, and `php artisan db:seed` is not the way to
do it.** `DatabaseSeeder` builds a full demo dataset — demo users, demo
workspaces, a demo subscription — and must not be run against production. The
plan seeder is nested inside that, which left no safe way to install the one row
the marketplace cannot run without.

Use the dedicated command:

```bash
php artisan marketplace:install --dry-run    # report only
php artisan marketplace:install
```

It creates the `standard` plan and tops up any missing `marketplace_settings`
rows. It creates **no** users, workspaces, posts or anything else. The settings and
payment-instruction tables seed themselves in their own migrations, so those are
verified rather than re-created.

```bash
php artisan tinker --execute="echo 'all: '.DB::table('marketplace_plans')->pluck('slug')->implode(',').' | active: '.DB::table('marketplace_plans')->where('is_active',1)->pluck('slug')->implode(',');"
```

Expected `active: standard`.

**Watch the `all:` column, it is diagnostic.** A realistic bad state, and the one
this development database was actually in, is:

```
all: business,free,pro,weekly | active:
```

Four archived plans, no `standard`, nothing active. That is what happens when the
archive-legacy-plans migration runs before anything creates `standard`: it
deactivates every plan that is not `standard`, and `standard` does not exist yet.
The plans page renders empty with **no error anywhere**, and nothing in the
marketplace can work.

`marketplace:install` is safe to re-run and will not revert an admin's work:

- an existing plan is left **entirely** alone, prices included — so re-running
  never reverts a price change
- an archived plan stays archived. Un-archiving is a decision for a person; an
  installer that flipped it would silently re-open sales at whatever prices are
  in the row
- settings use first-or-create, so an edited limit is never overwritten
- it **exits non-zero** if there is still no active plan, so a deploy script
  notices

## 4. The CSV report: read it before you trust the backfill

The `ends_at` backfill is the one migration that can make a historical
subscription *wrong* rather than merely missing, so it writes a report rather
than fixing anything silently.

**The report is written by the migration itself** — there is no command that
regenerates it. Check for the file immediately after §3:

```bash
ls -l storage/app/reports/ 2>/dev/null || echo "No report: nothing disagreed, nothing to read"
```

An absent or empty `reports/` directory is the good outcome. The backfill returns
early and logs `Marketplace backfill: subscriptions granted the wrong duration`
only when it found a real disagreement, so **check the log too**, in case the
write itself failed:

```bash
grep -n "wrong duration" storage/logs/laravel.log | tail -5
```

When there is a disagreement, the file is:

```
storage/app/reports/wrong-duration-subscriptions-YYYYMMDD.csv
```

dated from the migration's run time, so migrating again on a later day writes a
second file rather than overwriting the first.

Columns: `subscription_id`, `workspace_id`, `plan`, `tx_ref`, and the granted
versus expected period. **Read every row.** Each one is a workspace that paid for
a duration and was granted a different one, which means somebody's access is
wrong right now and no command will fix it. The migration is deliberately not
allowed to guess. The fix is per row: either correct `ends_at` by hand or refund.

Keep the file. It is the only record of who was affected.

## 5. Contact reveals: backfill before you let anybody pay

`contact_reveals` is new and starts empty. Without a backfill, everyone who
already had a counterparty's contact — because they had accepted an interest
under the old flow — would find themselves locked out of a number they already
had. That is a regression dressed as a feature.

```bash
php artisan migrate --force                                             # already done in §3
php artisan marketplace:backfill-contact-reveals --dry-run
php artisan marketplace:backfill-contact-reveals
```

Idempotent: the unique `(revealer_id, revealed_id)` key means a pair that
already exists is skipped, so a second run writes nothing.

**Order matters.** Run this before any paid subscriber uses a reveal. A paying
user who reveals before the backfill records that pair has it counted against
their weekly quota.

Backfilled rows carry the **original** relationship's `created_at`, not the time
of the backfill — a reveal written today would consume quota from a window that
has partly elapsed, a reveal written when the connection was made does not.

```bash
php artisan tinker --execute="echo 'total: '.DB::table('contact_reveals')->count();"
php artisan tinker --execute="echo 'written today: '.DB::table('contact_reveals')->whereDate('created_at', now()->toDateString())->count();"
```

`written today` must be `0`. Anything else means rows were stamped with the
current time and quota has been consumed retroactively.

## 6. Dry-run every command before running it for real

Both of the first two write to production data. Run each with `--dry-run` first,
read the output, then run for real once — all still with
`MARKETPLACE_EXPIRY_ENABLED=false`, because the flag only gates the *scheduler*.

```bash
# a. expire lapsed subscriptions, and hide excess listings
php artisan marketplace:expire-subscriptions --dry-run

# b. lapse unreviewed manual payment requests
php artisan marketplace:expire-manual-payments --dry-run
```

For **(a)** the dry run prints, per workspace: the plan, the expiry date, how many
live posts are visible, the keep limit, and how many would be hidden. Check the
keep limit against what an admin has set in **Admin → Marketplace → Plans**, not
against `config/marketplace.php` — the table wins and a stale default would hide
the wrong number of listings.

Then, for real:

```bash
php artisan marketplace:expire-subscriptions
php artisan marketplace:expire-manual-payments
```

Both are idempotent. Running either twice changes nothing the second time.

### `chapa:reconcile` has no dry run — and it does grant

Worth calling out because it is the one command in this runbook that
**cannot be inspected before it acts**:

- `chapa:reconcile` accepts **no options at all**. Passing `--dry-run` fails with
  `The "--dry-run" option does not exist`. Do not put it in a deploy script that
  expects a dry-run first.
- It is **not** inert: for each pending Chapa transaction it calls Chapa's verify
  API and, on a genuine success, calls `MarketplaceFulfillmentService::fulfill()`.
  Running it grants subscriptions.
- It **is** already running unattended every five minutes via cron (§2), is
  already scoped to `provider = 'chapa'` and transactions younger than two days,
  and fulfils through the same single idempotent path as the webhook.

So you do not need to run it as part of this deploy, and you should not run it
manually "just to check". Let the schedule handle it. If you do run it, the only
safe pre-check is to look, not to execute:

```bash
php artisan tinker --execute="echo DB::table('marketplace_payment_transactions')->where('provider','chapa')->whereIn('status',['pending','initialized'])->where('created_at','>',now()->subDays(2))->count();"
```

## 7. Manual payment: configure before you enable

Off by default. "Pay manually" only appears when at least one method is **fully
configured and enabled**, so there is no window where somebody starts a transfer
with nothing to transfer to.

1. **Admin → Marketplace → Payment Instructions.**
2. For each method to offer: fill every field it needs, tick **Enabled**, save.
3. It appears on the plans page immediately. No restart.

A method is offered only when every field it needs is filled in *and* enabled. A
half-configured account is worse than no account, because somebody will transfer
money to it.

Nothing secret lives in that table — it holds the account details a payer is
meant to read off screen, and there is deliberately nowhere to put an API key or
a webhook secret.

Unreviewed requests lapse after `free_manual_payment_expiry_days` (default 14,
admin-editable). A lapsed request grants nothing; the payer starts again and the
workspace's single open slot is freed.

**Rate limits are already on** and need no configuration: 5 submissions per hour
per user, 20 instructions pages per hour per user.

### Before an admin confirms anything, know this

- **No refunds or reversal.** A wrongly confirmed payment is undone by hand: set
  the subscription's `ends_at` to now and mark the transaction. There is no
  automated path.
- **No defence against a fabricated screenshot.** Manual payment is trust-based.
  The confirm dialog tells the admin to verify the amount in the banking app
  rather than the screenshot. The payer phone and bank reference are recorded so
  a real transfer can be matched, but nothing here can tell a genuine proof from
  a forged one.
- An admin **cannot confirm their own** payment request. The button is disabled
  and the row is labelled.

## 8. The private disk

Proofs and transporter documents are stored on a `private` disk whose root is
`storage/app/private`, with `serve => false` and no `url`. Nothing is served
statically; every read goes through an authorised route.

`config/filesystems.php` declares `private` **explicitly** rather than relying on
`FILESYSTEM_DISK`, because the default was `local` and pointing the default at a
private root broke every existing upload.

Check it after deploy:

```bash
php artisan tinker --execute="echo config('filesystems.disks.private.root');"
php artisan tinker --execute="echo 'serve: '; var_export(config('filesystems.disks.private.serve'));"
# both expect: .../storage/app/private   and   false

# and that the directory exists and is writable by the web user
ls -ld storage/app/private
sudo -u www-data test -w storage/app/private && echo WRITABLE || echo "NOT WRITABLE"

# nothing should be reachable over HTTP, whatever the file name
ls storage/app/public | grep -i "manual-payment-proofs" && echo "LEAK" || echo "OK: not in the public root"
```

# expect the serve check to print: serve: false
# (the root ends in storage/app/private; the full path is deployment-specific --
# in the DDEV container above it is /var/www/html/storage/app/private)

If `test -w` fails, that is the whole cause of a mysterious "upload failed" — and
it will fail silently, because the disk is configured with `throw => false`.

## 9. Cron and the queue worker

Both are required. Neither is optional, and neither is configured by the deploy.

### Scheduler (cron)

```cron
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

Without it nothing scheduled ever fires — not the expiry sweep, not
`freight:expire`, not `chapa:reconcile`, not the Stripe billing reminders.

### Queue worker (supervisor)

Manual payment notifications, Chapa notifications and (in Step 6) reminders are
queued. **Without a worker nothing is lost but nobody is told a payment arrived**,
and in Step 6 a claimed-but-undelivered reminder is permanent.

`QUEUE_CONNECTION=database` by default, so the worker is not optional.

```ini
; /etc/supervisor/conf.d/numanfreight-worker.conf
[program:numanfreight-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/app/artisan queue:work --sleep=3 --tries=3 --timeout=90 --max-time=3600
directory=/path/to/app
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/numanfreight/worker.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
stopwaitsecs=120
```

```bash
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl status numanfreight-worker:*
php artisan queue:monitor default --max=100   # expect: 0
```

`--max-time=3600` is deliberate: it recycles the worker hourly, which bounds the
effect of a memory leak in a long-lived Octane-era process.

## 10. Mail

```dotenv
MAIL_MAILER=smtp        # NOT `log` on production
```

Configured mailers: `smtp`, `ses`, `postmark`, `resend`, `sendmail`, plus the
non-delivering `log`, `array` and `failover`.

`.env.example` ships `MAIL_MAILER=log`, which **silently succeeds** while writing
to a log file. Forgetting this is the most likely reason reminders or payment
notifications never arrive.

```bash
php artisan tinker --execute="Mail::raw('deploy check', fn($m)=>$m->to('ops@yourdomain')->subject('x'));"
```

Send a real one and confirm it lands. A `log` mailer will not error here.

## 11. Manual smoke-test checklist

Run these on a staging database seeded with a workspace, a second user, and at
least one freight listing, before pointing production traffic at the new code.
`→` marks what "correct" looks like; anything else is a stop.

**Chapa, test mode**

1. As the workspace owner, **Billing → Plans**, choose Standard → Monthly → Chapa.
   → `chapa_tx_ref` set, `amount_etb` = the monthly price, `status = pending`.
2. Complete the checkout. → `marketplace_subscriptions` row with
   `status = active`, `ends_at` exactly 30 days out, `billing_interval = monthly`.
3. Call `php artisan chapa:reconcile`. → no duplicate row, no double grant.
4. Renew early. → **a new row** appears, with `current_period_start` equal to the
   old `ends_at` and `ends_at` 30 days beyond it, so no paid days are lost. The
   **prior row becomes `superseded` and keeps its original `ends_at`** — not
   overwritten, not deleted. This is also what makes Step 6's reminder log safe to
   key on subscription id: a superseded row is excluded by status alone, with no
   date arithmetic.

**Manual payment**

5. **"Pay manually"** must be visible on the plans page. → only if an admin
   configured at least one method fully.
6. Choose a method, fill the one-field phone step, submit with a reference and a
   screenshot. → status page shows the NF code; **no transaction row existed
   before submit**.
7. Try a second submit from the same workspace. → refused, one open request only.
8. As admin, open **Admin → Marketplace → Manual Payments**, confirm.
   → subscription created, slot freed, payer notified, **double-clicking Confirm
   grants once**.
9. Try to confirm your own payment request. → refused, and the row is labelled.
10. Open a proof. → image renders inline; `X-Content-Type-Options: nosniff` is
    present. Three refusals, and they are three different answers: a **logged-out**
    request is **redirected to `/login`** (the auth middleware runs first), a
    signed-in non-superadmin gets **403**, and an admin asking for a row with no
    proof gets **404**. Nothing serves the file.
11. Submit one and leave it. Confirm nothing grants. → still `pending`.

**Reveal quota**

12. With the Free plan, reveal contacts until the quota is spent. → the next one
    is blocked server-side, and **the counterparty's contact is not in the
    response at all** — no name, no phone, no email, no partial data.
13. Reveal the same counterparty again. → free, does not consume quota.
14. Renew. → quota is unlimited and the limit is enforced in the response, not by
    a hidden button.

**Hidden listing**

15. Free plan, exceed `free_active_posts`. → the excess are hidden, the newest
    stay visible.
16. `php artisan marketplace:expire-subscriptions --dry-run` and check the keep
    limit matches what the admin set. → then run it for real.
17. Pay. → `hidden_at` clears for that workspace and the listings come back.

**Logged-out leak check**

18. Logged out, hit every marketplace and freight detail page. → **no contact
    field anywhere in the HTML or the JSON.** Check the JSON payload, not just
    the rendered page.
19. `grep -rn "contact_phone\|payer_phone\|phone_number" storage/logs/laravel.log`
    → the only hits should be old lines predating the log fix. A new one means
    something started logging a payload again; `LogHygieneTest` is the standing
    check for that and must be in CI.

**Reminders (Step 6, once built)**

20. Not applicable yet. When it lands, `marketplace:send-reminders --dry-run`
    lists recipients; run it for real twice and confirm the second run sends
    nothing.

## 12. Enabling the flag

Only after §3's seeding step is done, §4's CSV has been read, §5's backfill has
run, §6's dry runs looked right, §9's **queue worker is supervised and running**,
and §11 has passed.

The worker is a hard gate here, not a nice-to-have: the expiry sweep sends
`FreightPostsHiddenNotification`, which is queued. Enabling the flag with no worker
means workspaces get silently hidden with nobody told.

```dotenv
MARKETPLACE_EXPIRY_ENABLED=true
```

```bash
php artisan schedule:list | grep marketplace   # now present
php artisan schedule:run                      # prove it fires
php artisan queue:monitor default --max=100    # expect: 0
```

### Rollback

Set `MARKETPLACE_EXPIRY_ENABLED=false` and reload the scheduler. Nothing needs
unwinding:

- a paid payment clears `hidden_at` on fulfilment
- `ends_at` governs entitlement, so flipping `status` early never changes what a
  user may do — the status column is bookkeeping
- the `contact_reveals` ledger is append-only and untouched by the flag

You do **not** need to roll back the migrations. Every one of them is either
additive or idempotent, and the two irreversible ones (`ends_at` backfill,
activity-log scrub) both made the data *more* correct.

## 12b. Data retention: two columns hold payer contact data

`marketplace_payment_transactions.provider_response` and
`chapa_webhook_events.payload` store Chapa's own responses verbatim, and those
contain the payer's **first name, last name, email and phone number**.

**Nothing sends them anywhere.** Verified, and now asserted by two tests:

| Destination | Exposure |
|---|---|
| Admin payment screen (`admin/marketplace-payments`) | explicit arrays only — no `provider_response`, no `payload` |
| Payer screens (`marketplace.manual.*`) | explicit columns only — no `provider_response`, no payer phone, proof path reduced to a boolean |
| Revenue export | never reads either column |
| API resources, jobs, notifications | never reference either column |
| The log file | not logged (see `LogHygieneTest`) |

They are kept because they are the reconciliation record: when a payment goes
missing, that JSON is the only evidence of what Chapa actually said.

### Retention decision — yours

These columns are **not** deleted, and nothing prunes them. That is a deliberate
gap, not an oversight, and it needs a decision rather than a default:

- `provider_response` and `payload` have **no** expiry. They accumulate one row
  per transaction forever.
- Each holds personal data, so "we keep it for audit" is a weaker answer here
  than it is for the activity log — that log records *that* a profile changed,
  not the phone number that was entered.
- The reconciliation value expires when the payment is no longer in dispute.
  Ninety days is a defensible floor; a year is not obviously justified.

Two options, both one piece of work:

1. **Add a retention sweep** — a command that nulls out `provider_response` and
   `payload` on rows older than N days, keeping `status`, `chapa_tx_ref`,
   `verified_at` and `amount_etb`, which is everything reconciliation actually
   needs. Follow the `MARKETPLACE_EXPIRY_ENABLED` pattern: disabled by default,
   `--dry-run` first.
2. **Redact on write** — store only the fields used (`status`, `amount`,
   `currency`, `reference`) and drop the rest at the boundary. Cleaner, but it
   loses the evidence, and it cannot be applied retroactively.

Whichever you pick, the test that asserts neither reaches a page stays either way.

Until then: do not put either column into a support export, a debug dump, or a
`Log::` line.

## 13. CI must stay green

Three pre-existing failures are unrelated to this work and are present on
`de06bcd`: one in `FreightCompletionServiceTest`, two in `RoutePreviewServiceTest`.

```bash
php artisan test --compact tests/Feature/Marketplace/     # 412 passing
php artisan test --compact tests/Feature/ContactRevealLeakTest.php   # 20 passing
vendor/bin/pint --dirty
npm run types && npm run build
```

Two test files carry weight disproportionate to their size:

- **`ContactRevealLeakTest`** (20 tests) is the only thing covering the seven
  public paths that used to expose contact and bank data — the consignor interest
  list, the public freight show page, the discovery listing, the transporter
  directory and profile, accepting an interest, the connection page and index,
  the transporter interest list, and the interest-submitted notification. It lives
  outside `tests/Feature/Marketplace/`, so it is easy to forget when running
  "just the marketplace tests".
- **`LogHygieneTest`** parses every logger call in `app/` and fails on one that
  dumps a request body, a session, a provider payload, or a field named like
  contact data. Every leak found so far was a "just add the payload while
  debugging" edit, and this is what stops the next one. Do not mute it.

## 14. Step 6 is not built

Renewal and expiry reminders are **designed and not implemented.** There is no
`marketplace:send-reminders` command and no `marketplace_reminder_log` table yet,
so §11 item 20 cannot be run and nothing in this runbook depends on them. They
will reuse `MARKETPLACE_EXPIRY_ENABLED` — one flag, as above — which is why §2
lists three commands against it.
