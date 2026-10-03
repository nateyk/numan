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
