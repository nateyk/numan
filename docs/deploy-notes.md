# Deploy notes

## Marketplace expiry — disabled by default

`marketplace:expire-subscriptions` does two things that write to real data:

1. flips `marketplace_subscriptions.status` to `expired` once `ends_at` has passed
2. sets `freight_posts.hidden_at` on the excess live listings of workspaces
   whose paid plan ended, keeping the newest `free_active_posts` visible

It is **not registered with the scheduler unless explicitly enabled**:

```dotenv
MARKETPLACE_EXPIRY_ENABLED=false   # default
```

With the flag off, `php artisan schedule:list` will not show the command and the
scheduler will never run it. The command still works when invoked by hand, so
you can always inspect before acting.

### Rollout

1. Deploy with the flag off. Nothing changes automatically.
2. Inspect:

   ```bash
   MARKETPLACE_EXPIRY_ENABLED=true php artisan marketplace:expire-subscriptions --dry-run
   ```

   It prints, per workspace, the plan, the expiry date, how many live posts are
   visible, the keep limit and how many would be hidden. It writes nothing.
3. Read the output and confirm the numbers are sane.
4. Run it for real, once:

   ```bash
   MARKETPLACE_EXPIRY_ENABLED=true php artisan marketplace:expire-subscriptions
   ```

5. Only then set `MARKETPLACE_EXPIRY_ENABLED=true` and restart/reload the
   scheduler to let it run hourly.

### Rollback

Set `MARKETPLACE_EXPIRY_ENABLED=false` and reload the scheduler. Rows already
flipped stay `expired` and hidden posts stay hidden, because both are
recoverable without the command:

- a new paid payment clears `hidden_at` for that workspace on fulfilment
- `ends_at` still governs entitlement, so flipping the status early does not
  change what a user is allowed to do — the status column is bookkeeping

### A note on local development

The DDEV scheduler runs `schedule:work` continuously. Before this flag existed,
the hourly command ran unattended against the development database and flipped
the demo subscription to `expired`. The flag prevents that recurring.

## Contact reveal — run the backfill before enabling metering

Contact reveals are metered against a ledger, `contact_reveals`. A pair being
present in that ledger is what makes a re-reveal free.

The table is new, so it starts empty. Without a backfill, everyone who already
had a counterparty's contact — because they had accepted an interest, and the old
accept flow released it — would find themselves locked out of a number they
already had. That is a regression dressed as a feature.

```bash
# 1. migrate
php artisan migrate --force

# 2. inspect
php artisan marketplace:backfill-contact-reveals --dry-run

# 3. seed
php artisan marketplace:backfill-contact-reveals
```

The command is idempotent: the unique `(revealer_id, revealed_id)` key means a
pair that already exists is skipped rather than duplicated, so running it twice
writes nothing the second time.

Backfilled rows carry the **original** relationship's `created_at`, not the time
of the backfill. A reveal written today would consume quota for a window that has
already partly elapsed; a reveal written at the moment the connection was made
does not. So the backfill adds no quota pressure on anybody.

### Order of operations

The backfill must run before any paid subscriber uses a reveal. It is safe to
run at any point after the migration, and safe to run again later, but a paying
user who reveals a partner before the backfill records that pair will have it
counted against their weekly limit.

### Verifying

```bash
php artisan tinker --execute="echo DB::table('contact_reveals')->count();"
php artisan tinker --execute="echo DB::table('contact_reveals')->whereDate('created_at', now()->toDateString())->count();"
```

The second number should be `0` immediately after a backfill of historical
relationships. Anything else means rows were written with the current timestamp.

## Scheduler requirement

The scheduler only runs if something invokes it. On a server that means cron:

```cron
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

Without it, nothing scheduled ever fires — not this command, not
`freight:expire`, not `chapa:reconcile`, not the billing reminders.

## Wayfinder generated directories

`resources/js/routes` and `resources/js/actions` are generated and gitignored.
If `php artisan wayfinder:generate` fails with a permission error, they were
left owned by root by an earlier container process:

```bash
sudo chown -R "$(id -un):$(id -gn)" resources/js/routes resources/js/actions
```

See `docs/wayfinder-permissions.md`.
