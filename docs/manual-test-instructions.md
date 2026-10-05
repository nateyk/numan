# Manual test instructions — marketplace release

Everything in this release, and how to check it by hand.

**Read this before you trust anything in the automated suite.** The tests prove the
logic; they do not prove a human can complete a payment, that an email arrives, or
that a banner shows the right number. Those are the items below.

---

## Before you start

```bash
ddev start
ddev exec php artisan migrate:fresh --seed      # local only; see §13 of the runbook
ddev exec php artisan marketplace:install
```

Create accounts (local only — the seeders refuse to run in production):

| Role | Email |
|---|---|
| Superadmin | `admin@numanfreight.com` |
| Shipper | `shipper.ethiopia@numanfreight.com` |
| Verified transporter | `transporter.express@numanfreight.com` |
| Pending transporter | `transporter.pending@numanfreight.com` |

Password: whatever `DEMO_PASSWORD` is set to in your `.env`. There is no default
and it is not written down anywhere — see §26 of the build guide.

```bash
# The two marketplace flags start OFF. Everything in section 3 and 4 is inert
# until you turn them on, deliberately.
grep MARKETPLACE .env
```

---

## 1. The rehearsal harness

Run this first. It is the only way to see what the release would do to real data
without doing it.

```bash
./rehearse-release.sh /path/to/prod-dump.sql.gz
```

**Expected:** all guards pass, 18 migrations applied one at a time with a row delta
each, `marketplace:install` reports the plan already present, every dry run reports,
and the post-run checks confirm the dump is byte-identical and both flags still off.

**Read the report** at `storage/app/rehearsal/report.txt`. Six numbers decide whether
you can ship:

| Report line | Question it answers | Stop shipping if |
|---|---|---|
| rows changed, by migration | What does each migration actually rewrite? | Any migration changes a table you did not expect |
| rows scrubbed | How many `activity_log` rows lost plaintext contact data? | Higher than you expect — those were real phone numbers in a log |
| workspaces_with_posts_hidden | How many customers lose listings? | Large enough to need a warning email first |
| posts_that_would_be_hidden | Same, in posts | Keep limit looks wrong for your real data |
| renewal_reminders_by_type | Who gets emailed, and when? | More than one per workspace per run |
| wrong-duration CSV | Whose payment got the wrong period? | **Any row at all** — that is a customer whose access is wrong right now |

The last one is the one to read every row of.

### No dump to hand

```bash
FIXTURE_DB_PASSWORD="$(your ddev db password)" ./make-rehearsal-fixture.sh
./rehearse-release.sh /tmp/rehearsal-fixture.sql.gz
```

Synthetic data with known-correct answers. Proves the harness works; proves nothing
about your production data.

---

## 2. Freight posting — the manual path

A shipper posts freight end to end. This is the path a paying customer uses, and it
changed in this release.

| # | Do this | Expect |
|---|---|---|
| 1 | Sign in as the shipper, **My Freight → Post freight** | Step 1 asks for **origin** and **destination** |
| 2 | In Origin, click the field and type a town name, e.g. `Adama` | A dropdown of saved locations appears |
| 3 | Pick **Adama** | The field fills in, and the saved-location badge shows |
| 4 | Clear it and type `Bishoftu`, then search again | No saved match — a **"Search OpenStreetMap"** option appears |
| 5 | Pick an OSM result | Location resolves; **Save a new location** is offered |
| 6 | Save it, then continue | The post can continue — a place typed as free text is accepted |
| 7 | Reach the **Cargo** step | **Item name** is required, e.g. `Cement` |
| 8 | Clear Item name and try to continue | Validation blocks you. **Cargo description is optional and separate** |
| 9 | Complete every step and submit | A **receipt modal** appears with the reference. Not a silent redirect |
| 10 | Close the receipt, open **My Freight** | The new post is listed with its item name |
| 11 | Open the post as the verified transporter, **Interests** | A route preview appears between your two locations |

### Route preview when the router has no answer

Some Ethiopian routes have no OSRM coverage.

| # | Do this | Expect |
|---|---|---|
| 12 | Find a post between two places OSRM cannot route | A **straight-line estimate** with distance and duration |
| 13 | Check the label | It says the estimate is straight-line. It must not claim to be a road distance |

If the label is missing, you are looking at a post whose route genuinely failed —
that is a different case and it should show an error instead.

---

## 3. Hidden listings — behind the flag

**Off by default.** A lapsed workspace keeps its extra listings until you turn this
on. Turn it on only after section 1's numbers are acceptable.

```bash
# .env
MARKETPLACE_EXPIRY_ENABLED=true
ddev exec php artisan config:clear
ddev exec php artisan schedule:list | grep expire    # expect two commands
```

| # | Do this | Expect |
|---|---|---|
| 14 | Sign in as the shipper with a workspace over the Free post limit | **My Freight** shows a banner naming how many listings are hidden |
| 15 | Look at the listings list | Each affected row is badged. On **mobile** too — check the card list, not just the table |
| 16 | Open **Browse freight** in a private window | Hidden posts are **absent**, not greyed |
| 17 | Sign back in as the shipper | Hidden posts are still visible to their owner, with the badge |
| 18 | Run the dry run | `php artisan marketplace:expire-subscriptions --dry-run` — lists workspaces and `keep=N`. Nothing is written |
| 19 | Run it for real, then reload | Banner and badges appear |
| 20 | Run it again | `0` hidden. Idempotent |
| 21 | Renew the workspace | Hidden listings come back. Nothing else changes |

Step 21 is the one that proves the sweep only ever *sets* `hidden_at`.

---

## 4. Renewal and expiry reminders — behind a second flag

**Also off by default, and separate from section 3 on purpose.**

```bash
# .env
MARKETPLACE_REMINDERS_ENABLED=true
ddev exec php artisan config:clear
ddev exec php artisan schedule:list | grep reminders   # hourly
```

**No queue worker is needed for this.** The notification sends inline. (The runbook
used to say otherwise and was wrong.)

| # | Do this | Expect |
|---|---|---|
| 22 | `php artisan marketplace:send-reminders --dry-run` | Works **with the flag off**. One line per reminder: `would send, new` or `would send, already decided` |
| 23 | Read that list | One reminder per workspace. A yearly plan with 20 hours left gets `renewal_1d` only |
| 24 | Run for real, then run again | Second run reports `Sent 0` |
| 25 | Open the recipient's notifications | The reminder is in the app, with plan, end date and today's price |
| 26 | Check their email | Same content, plus a **View plans** button linking to `/billing/plans` |
| 27 | Look at the end date | Rendered in **their** timezone, not UTC |
| 28 | Sign out, sign in as a French user, check the reminder | French throughout — including the word "monthly". Not English words in a French sentence |
| 29 | Repeat for Spanish and Arabic | Same |
| 30 | Wait for a lapsed workspace | `plan_expired` fires **only within 3 days** of the end date |
| 31 | Check a workspace that lapsed 20 days ago | **Nothing.** No historical backlog on first run |
| 32 | Check a renewed workspace | Nothing about the old period |
| 33 | Force a plan to lapse with listings over the Free limit | The expiry email mentions hidden listings |
| 34 | Repeat with a workspace **under** the limit | The expiry email does **not** mention hidden listings. It must agree with the banner |

Steps 33 and 34 together are the important pair: the same sentence must appear in one
and not the other, or the email contradicts the banner on the same screen.

### Recovering a crashed run

```bash
# simulate a process that died between claiming the reminder and sending it
ddev exec mysql -u db -p db <db> -e "
  UPDATE marketplace_reminder_logs
     SET sent_at = NULL, status = 'claimed', created_at = NOW() - INTERVAL 3 HOUR
   WHERE sent_at IS NOT NULL LIMIT 1;"

php artisan marketplace:send-reminders     # re-sends that one row
php artisan marketplace:send-reminders     # sends nothing
```

Expect exactly one send per claimed row, then nothing. Verified by
`ReminderCrashRecoveryTest` and worth doing once by hand.

---

## 5. Manual payment

| # | Do this | Expect |
|---|---|---|
| 35 | As the shipper, open **Billing → Plans** | A payment-instructions entry point, if one is configured |
| 36 | Open it | Telebirr and/or CBE instructions with account details |
| 37 | **Back-date the token**, then reload the page | It is rejected. The token is bound to you and your workspace |
| 38 | Submit the payment reference and upload a proof | A pending request appears in the admin queue. **Nothing is granted yet** |
| 39 | Reload and resubmit fast | The button disables after one click |
| 40 | Try 6 submissions in an hour | Blocked after 5 (rate limit) |
| 41 | As admin, **Admin → Manual payments** | The queue, with ETB revenue totals |
| 42 | Confirm the request | Goes through `fulfil()`: the plan is granted and **not** through a second code path |
| 43 | Check the audit log | No payer phone, no bank reference, no proof path |
| 44 | As a non-admin, try to reach the confirm action | Forbidden |
| 45 | Upload a proof as a transporter | Stored on the **private** disk, not publicly reachable |
| 46 | `php artisan marketplace:expire-manual-payments --dry-run` | Lists requests past the 14-day window, granting nothing |
| 47 | Back-date a pending request 40 days, run the command for real | It lapses. **No plan is granted** |

Step 47 is the one that catches a double-grant: an expired manual payment must grant
nothing, or you have a free subscription.

---

## 6. Billing and payments, general

| # | Do this | Expect |
|---|---|---|
| 48 | Buy the monthly plan with Chapa **test mode** | Subscription granted with the right end date |
| 49 | Renew early, extending the current period | The end date moves out by the full new period |
| 50 | Try to submit the same Chapa reference twice | The second is refused at the database level |
| 51 | Two browser tabs, submit the same reference | One succeeds. One |
| 52 | Complete a Chapa payment but block the webhook | `chapa:reconcile` finds it. Read §6 of the runbook first — it grants |
| 53 | Compare prices on the plans page with the plan record | Identical. No price is hardcoded in a screen |

---

## 7. Contact reveals — the leak fixes

This is the part that was wrong before, so it is worth checking by hand.

| # | Do this | Expect |
|---|---|---|
| 54 | As the verified transporter, express interest, then reveal contact | The owner's contact appears |
| 55 | Reveal the same pair again this week | **Metered.** The weekly limit applies |
| 56 | Reveal a contact with **no phone number** | Not hard-blocked. "Request reveal" instead of a dead end |
| 57 | Check which contact is shown first when both exist | Ordered consistently — phone before email, or whatever the rule is |
| 58 | Search freight as an unrelated transporter | No contact data anywhere in the response. Check the page source, not just the UI |
| 59 | Same via the API | Same. `<Profile>` and `<TransporterProfile>` are excluded |
| 60 | Grep the log file for a phone number | `grep -c "+2519" storage/logs/laravel.log` — expect 0 |
| 61 | Update your profile, then grep again | Still 0 |
| 62 | Admin → Activity log, any entry | No contact data, no password hashes |

Steps 58–62 are the regression tests that matter most. They passed before and were
wrong.

---

## 8. Admin

| # | Do this | Expect |
|---|---|---|
| 63 | Admin → Marketplace → Plans, edit a price | Saves. **The public plans page shows the new price** |
| 64 | Edit a limit, e.g. Free active posts | Takes effect for new posts, not retroactively |
| 65 | Admin → Freight | A sortable-shaped table, one row per post |
| 66 | Look for the sort arrows | **Not there.** The table does not sort, so it does not pretend to |
| 67 | Look for the green ✓ / red ✗ | **Not there.** One action, not two that do the same thing |
| 68 | Open a post from the table | Reaches the right place |
| 69 | Double-click a manual payment decision | The button disables after one click |

---

## 9. What must not have happened

Check these by looking, not by testing:

| Look at | Expect |
|---|---|
| `grep -rn "Password123!" --exclude-dir=node_modules --exclude-dir=vendor .` | Nothing outside tests |
| `grep -rn "agrigebeya.com" .github/` | Still there — pre-existing, needs rotating, not deleting |
| Any log line containing `@` or `+251` | None |
| `git log -p <this-branch>~1..HEAD \| grep '^+' \| grep -iE "password|secret|api_key"` | Nothing |
| Any screen showing a phone number to a non-owner | None |
| `config/marketplace.php` | Prices and limits are config, not literals |

---

## 10. If you are deploying

```bash
# 1. back up the database AND storage/app/private — see §1
# 2. deploy with both flags off
# 3. php artisan migrate --force
# 4. read the CSV in storage/app/reports/ — every row
# 5. php artisan marketplace:install --dry-run, then for real
# 6. php artisan marketplace:backfill-contact-reveals --dry-run, then for real
# 7. both expiry commands, dry run then for real
# 8. php artisan marketplace:send-reminders --dry-run
# 9. turn on MARKETPLACE_EXPIRY_ENABLED
# 10. turn on MARKETPLACE_REMINDERS_ENABLED — separately, later
```

Stop after any step that surprises you. Steps 4 through 8 are all idempotent and safe
to re-run; steps 9 and 10 are the only ones that change what customers experience.

**Roll back:** §15. Short version — kill the schedule first (instant, reversible),
then the code, and only consider a restore if a migration corrupted something.
Restoring is *not* how you undo the contact-data scrub: that brings plaintext phone
numbers back.

---

## Known gaps

Not defects in this release — things that were already true and were left alone, so
you are not surprised by them.

- **No Amharic.** `en`, `fr`, `es`, `ar` only. `SetLocale` does not accept it and
  there is no `am.json`. Worth doing for Ethiopia; it is not done.
- **Older marketplace notifications are English-only.** `ManualPaymentConfirmed` and
  friends are hardcoded English. Only the new reminders are translated.
- **`AdminFreightController::index()` passes whole Eloquent models to Inertia**,
  which includes the owner's phone and email in the payload. Not visible in the UI,
  but it is in the network tab. Worth fixing before a penetration test.
- **65 eslint unused-variable errors** repo-wide.
- **`transporter-profile/index.tsx` has a dead import.**
- **Payment-instruction rows are verified by `marketplace:install`, not created by
  it** — their migration seeds them.
- **`chapa_webhook_events.payload` and `provider_response` are still retained
  indefinitely.** The proposal is `docs/provider-data-retention.md`; nothing is
  implemented.
- **The demo password** is whatever `DEMO_PASSWORD` says, and both seeders refuse to
  run in production.
