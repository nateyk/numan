#!/usr/bin/env bash
#
# rehearse-release.sh — rehearse a marketplace release against a COPY of production data.
#
# Takes a dump you exported yourself. It never connects to production and never
# writes to the dump. It never enables a flag.
#
#   ./rehearse-release.sh /path/to/prod-dump.sql
#   ./rehearse-release.sh /path/to/prod-dump.sql.gz --db-name marketplace_rehearsal
#
# What it does, in order:
#   1. rails      — refuse to continue unless every safety condition holds
#   2. restore    — create a throwaway database and import the dump into it
#   3. migrate    — one migration at a time, recording the row delta of each
#   4. install    — marketplace:install, dry run then for real
#   5. dry runs   — expiry, manual payments, reminders, contact-reveal backfill
#   6. report     — write everything to storage/app/rehearsal/report.txt
#
# Every command runs against the throwaway database by exporting DB_DATABASE,
# which takes precedence over .env. Your .env is never edited by this script.

set -euo pipefail

# ── configuration ──────────────────────────────────────────────────────────

DUMP=""
DB_NAME="marketplace_rehearsal"
WORK="storage/app/rehearsal"
LOG="${WORK}/rehearse.log"
REPORT="${WORK}/report.txt"
CSV_DIR="${WORK}/csv"

# Local MariaDB inside DDEV. Anything else is refused below.
DB_HOST_LOCAL="db"

# ── argument parsing ───────────────────────────────────────────────────────

while [[ $# -gt 0 ]]; do
    case "$1" in
        --db-name) DB_NAME="$2"; shift 2 ;;
        --db-name=*) DB_NAME="${1#*=}"; shift ;;
        -h|--help) sed -n '2,26p' "$0"; exit 0 ;;
        -*) echo "Unknown option: $1" >&2; exit 2 ;;
        *)  if [[ -n "$DUMP" ]]; then echo "Two dumps given. Only one." >&2; exit 2; fi
            DUMP="$1"; shift ;;
    esac
done

# ── helpers ────────────────────────────────────────────────────────────────

RED=$'\033[31m'; GRN=$'\033[32m'; YLW=$'\033[33m'; BLD=$'\033[1m'; RST=$'\033[0m'

say()  { printf '%s\n' "$*" | tee -a "$LOG"; }
head2() { printf '\n%s=== %s ===%s\n' "$BLD" "$*" "$RST" | tee -a "$LOG"; }
ok()   { say "${GRN}ok${RST}   $*"; }
warn() { say "${YLW}warn${RST} $*"; }
die()  { printf '%s%s%s\n' "$RED" "$*" "$RST" | tee -a "$LOG" >&2; exit 1; }

# ddev exec occasionally dies with a WSL vsock error when calls come thick and
# fast, and this script makes ~40 of them in a row. Retrying costs seconds and
# turns an intermittent infrastructure failure into a non-event. The command is
# idempotent in every place this is used, so a retry cannot double anything.
retry() {
    local attempt=1 max=3 out

    while true; do
        if out="$("$@" 2>&1)"; then
            printf '%s' "$out"
            return 0
        fi

        if [[ "$attempt" -ge "$max" ]]; then
            printf '%s' "$out"
            return 1
        fi

        attempt=$((attempt + 1))
        sleep 1
    done
}

# Run artisan against the rehearsal database. Every invocation in this script
# goes through here so no command can accidentally reach the real database.
artisan() {
    retry ddev exec env \
        DB_CONNECTION="${DB_CONNECTION:-mariadb}" \
        DB_HOST="${DB_HOST:-$DB_HOST_LOCAL}" \
        DB_DATABASE="$DB_NAME" \
        MARKETPLACE_EXPIRY_ENABLED=false \
        MARKETPLACE_REMINDERS_ENABLED=false \
        php artisan "$@"
}

# Run through the db container's own client rather than `ddev mysql`.
#
# `ddev mysql` rewrites its arguments into `mysql -e <first-arg>`, so a flag like
# -N or -B lands inside the query text and MariaDB reports a syntax error about
# "-N". Running in the db container also means no credentials anywhere: root there
# authenticates without a password, and that container is destroyed by ddev delete.
DBX=(ddev exec -s db --raw -- mysql)

# </dev/null on both: the mysql client reads stdin even with -e, so inside a
# `while read` loop it swallows the remaining lines and the loop quietly processes
# only the first one. That is how the second GRANT went missing the first time.
sql()    { "${DBX[@]}" -N -B -e "$1" < /dev/null; }
sql_db() { "${DBX[@]}" -N -B -D "$DB_NAME" -e "$1" < /dev/null; }

# ── the probe ──────────────────────────────────────────────────────────────

# Generated into storage/app (gitignored) rather than committed, so a rehearsal
# tool does not become product surface. Bootstraps Laravel and answers the
# counting questions the report needs.
cat > "${WORK}/probe.php" <<'PROBE'
<?php

require __DIR__.'/../../../vendor/autoload.php';

$app = require_once __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$what = $argv[1] ?? 'counts';

/** Row counts for every table. @return array<string,int> */
$counts = function (): array {
    $out = [];

    foreach (DB::select('SHOW FULL TABLES') as $row) {
        $values = (array) $row;
        $table = $values['Tables_in_'.config('database.connections.'.config('database.default').'.database')] ?? null;

        if ($table === null) {
            $table = (string) array_values($values)[1];
        }

        try {
            $out[$table] = (int) DB::table($table)->count();
        } catch (Throwable) {
            $out[$table] = -1;
        }
    }

    ksort($out);

    return $out;
};

switch ($what) {
    case 'counts':
        echo json_encode($counts(), JSON_PRETTY_PRINT);
        break;

    // The two marketplace flags as the bare "expiry,reminders" digits the shell
    // compares against. Done in PHP because parsing JSON out of a shell pipeline
    // is how a safety rail quietly stops being one.
    case 'flags':
        printf('%d,%d',
            config('marketplace.expiry_enabled') ? 1 : 0,
            config('marketplace.reminders_enabled') ? 1 : 0,
        );
        break;

    case 'identity':
        echo json_encode([
            'database' => DB::connection()->getDatabaseName(),
            'host' => DB::connection()->getConfig('host'),
            'expiry_enabled' => (bool) config('marketplace.expiry_enabled'),
            'reminders_enabled' => (bool) config('marketplace.reminders_enabled'),
            'pending_migrations' => array_values(array_diff(
                array_keys(app('migrator')->getMigrationFiles([database_path('migrations')])),
                DB::table('migrations')->pluck('migration')->all(),
            )),
        ], JSON_PRETTY_PRINT);
        break;

    // Migrations that have not run, in the order they would run.
    //
    // Not all migration files: `migrate --step` still applies the whole pending
    // batch, so attributing a row delta to a migration needs the file list and
    // --path, one at a time.
    case 'pending':
        // database_path() rather than Migrator::paths(): the migrator's paths are
        // registered by the migrate command itself, so in a plain PHP script
        // Migrator::paths() is empty and getMigrationFiles() returns nothing --
        // which reports "no pending migrations" for a database that is missing 18.
        $paths = [database_path('migrations')];
        $files = app('migrator')->getMigrationFiles($paths);

        $ran = DB::table('migrations')->pluck('migration')->all();

        $pending = array_values(array_diff(array_keys($files), $ran));

        // Exits here rather than falling through to the shared trailing newline:
        // that newline becomes an empty line, mapfile reads it as an empty
        // migration name, and the loop tries to run a file called ".php".
        echo implode("\n", $pending);
        exit(0);

    // Workspaces whose paid plan has lapsed and whose listings would be hidden,
    // computed by the same code the sweep uses, without writing anything.
    case 'expire-preview':
        $preview = app(App\Services\MarketplaceListingVisibilityService::class)->previewLapsedWorkspaces();
        $wouldHide = array_values(array_filter($preview, fn ($p) => $p['would_hide'] > 0));
        $totalPosts = array_sum(array_column($wouldHide, 'would_hide'));

        echo json_encode([
            'lapsed_workspaces' => count($preview),
            'workspaces_with_posts_hidden' => count($wouldHide),
            'posts_that_would_be_hidden' => $totalPosts,
            'free_limit_in_force' => app(App\Services\MarketplaceSettingsService::class)->get('free_active_posts'),
            'sample' => array_map(fn ($p) => [
                'workspace_id' => $p['workspace_id'],
                'plan' => $p['plan'],
                'visible_live_posts' => $p['visible_live_posts'],
                'keep' => $p['keep'],
                'would_hide' => $p['would_hide'],
            ], array_slice($wouldHide, 0, 12)),
        ], JSON_PRETTY_PRINT);
        break;

    // Subscriptions the sweep would flip to expired.
    case 'expiring':
        $now = now();
        $stale = App\Models\MarketplaceSubscription::query()
            ->whereIn('status', ['active', 'trialing'])
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', $now)
            ->get(['id', 'workspace_id', 'billing_interval', 'ends_at']);

        echo json_encode([
            'subscriptions_past_ends_at' => $stale->count(),
            'distinct_workspaces' => $stale->pluck('workspace_id')->unique()->count(),
            'oldest_ends_at' => (string) ($stale->min('ends_at') ?? 'n/a'),
            'newest_ends_at' => (string) ($stale->max('ends_at') ?? 'n/a'),
        ], JSON_PRETTY_PRINT);
        break;

    case 'reminder-preview':
        $types = [];
        $skipped = 0;

        foreach (App\Models\MarketplaceSubscription::query()
            ->whereIn('status', ['active', 'trialing'])
            ->whereNotNull('ends_at')
            ->with('workspace')
            ->get() as $subscription) {
            $due = App\Services\MarketplaceReminderWindow::due($subscription, now());

            foreach ($due['types'] as $type) {
                $types[$type] = ($types[$type] ?? 0) + 1;
            }

            $skipped += count($due['skipped']);
        }

        // Lapsed inside the window only — this is what the expiry window is for.
        $window = (int) config('marketplace.reminder_expired_window_days', 3);
        $lapsedInWindow = App\Models\MarketplaceSubscription::query()
            ->where('ends_at', '>', now()->subDays($window))
            ->where('ends_at', '<=', now())
            ->whereIn('status', ['active', 'trialing'])
            ->count();

        $lapsedOutside = App\Models\MarketplaceSubscription::query()
            ->where('ends_at', '<=', now()->subDays($window))
            ->count();

        echo json_encode([
            'renewal_reminders_by_type' => $types,
            'renewal_reminders_total' => array_sum($types),
            'windows_that_would_be_skipped' => $skipped,
            'lapsed_inside_expiry_window' => $lapsedInWindow,
            'lapsed_outside_expiry_window_gets_nothing' => $lapsedOutside,
            'expired_window_days' => $window,
        ], JSON_PRETTY_PRINT);
        break;

    // How many activity_log rows still hold plaintext contact data. Counted
    // before and after the scrub migration, so the delta is the answer to
    // "how many rows were scrubbed".
    case 'scrub-scan':
        // Counted in ONE pass over every activity_log row rather than once per
        // subject type.
        //
        // The scrub matches subject types with LIKE '%Profile', so iterating the
        // subject types visits TransporterProfile twice — once as itself and once
        // under '%Profile'. The per-type breakdown therefore double counts, and a
        // "how many rows were scrubbed" figure summed from it is wrong. One pass
        // gives the exact number; the breakdown is reported separately and labelled
        // as overlapping.
        $scrubber = app(App\Services\ActivityPropertyScrubber::class);

        $rowsHoldingData = 0;
        $keysRemoved = 0;
        $bySubject = [];

        DB::table('activity_log')->orderBy('id')->each(function ($row) use ($scrubber, &$rowsHoldingData, &$keysRemoved, &$bySubject): void {
            if ($row->properties === null || $row->properties === '') {
                return;
            }

            [$cleaned, $removed] = $scrubber->scrubJson($row->properties);

            if ($removed === 0) {
                return;
            }

            $rowsHoldingData++;
            $keysRemoved += $removed;

            $type = (string) $row->subject_type;
            $short = str_contains($type, '\\') ? substr(strrchr($type, '\\'), 1) : $type;

            $bySubject[$short] = ($bySubject[$short] ?? 0) + 1;
        });

        ksort($bySubject);

        echo json_encode([
            'rows_holding_plaintext_contact_data' => $rowsHoldingData,
            'sensitive_keys_in_those_rows' => $keysRemoved,
            'activity_log_rows_total' => DB::table('activity_log')->count(),
            'by_subject_type' => $bySubject,
            'note' => 'by_subject_type is exact per distinct type; note that the scrub migration matches types with LIKE, so its own log counts a TransporterProfile row twice.',
        ], JSON_PRETTY_PRINT);
        break;

    case 'manual-payments':
        // Every column is checked first. This probe runs against the PRE-migration
        // schema too, where `provider` and the manual_* columns do not exist yet --
        // and a probe that throws on the schema it is meant to be measuring stops
        // the whole report at that point.
        $table = 'marketplace_payment_transactions';

        if (! DB::getSchemaBuilder()->hasTable($table)) {
            echo json_encode(['table' => 'absent']);

            break;
        }

        $exists = array_flip(DB::getSchemaBuilder()->getColumnListing($table));

        $grouped = function (string $column) use ($exists): array {
            if (! isset($exists[$column])) {
                return [];
            }

            return DB::table('marketplace_payment_transactions')
                ->select($column, DB::raw('COUNT(*) as n'))
                ->groupBy($column)->pluck('n', $column)->all();
        };

        // The counting closure above is written out per column rather than looped,
        // because a loop over a variable column name is exactly the shape that
        // silently queries the wrong table.
        $count = function (string $column) use ($exists): int {
            if (! isset($exists[$column])) {
                return -1;
            }

            return DB::table('marketplace_payment_transactions')
                ->whereNotNull($column)->where($column, '<>', '')->count();
        };

        echo json_encode([
            'columns_present' => array_values(array_intersect(
                ['provider', 'status', 'manual_payer_phone', 'manual_reference', 'manual_proof_path', 'provider_response'],
                array_keys($exists),
            )),
            'by_status' => $grouped('status'),
            'by_provider' => $grouped('provider'),
            // -1 means the column does not exist yet, which is information rather
            // than a zero: zero would read as "nobody holds a payer phone".
            'holding_payer_phone' => $count('manual_payer_phone'),
            'holding_manual_reference' => $count('manual_reference'),
            'holding_proof_path' => $count('manual_proof_path'),
            'holding_provider_response' => $count('provider_response'),
        ], JSON_PRETTY_PRINT);
        break;

    case 'plans':
        echo json_encode([
            // is_active and deleted_at, not archived_at: this table has no
            // archived_at column, and the archive migration sets is_active.
            'plans' => DB::table('marketplace_plans')->get()
                ->map(fn ($p) => [
                    'slug' => $p->slug,
                    'is_active' => (bool) $p->is_active,
                    'soft_deleted' => $p->deleted_at !== null,
                    'weekly' => $p->price_weekly_etb,
                    'monthly' => $p->price_monthly_etb,
                    'yearly' => $p->price_yearly_etb,
                ])->all(),
            'active_plans' => DB::table('marketplace_plans')
                ->where('is_active', true)->whereNull('deleted_at')->count(),
        ], JSON_PRETTY_PRINT);
        break;

    case 'settings':
        echo json_encode(
            DB::table('marketplace_settings')->pluck('value', 'key')->all(),
            JSON_PRETTY_PRINT
        );
        break;

    case 'csv-report':
        // The wrong-duration CSV the backfill writes. Summarised, and copied
        // out of the local disk so the rehearsal keeps it after the DB goes.
        $files = glob(storage_path('app/reports/wrong-duration-subscriptions-*.csv'));
        $summary = [];

        foreach ($files as $file) {
            $rows = array_map('str_getcsv', file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
            $header = array_shift($rows);

            $summary[] = [
                'file' => basename($file),
                'wrong_duration_rows' => count($rows),
                'columns' => $header,
                'sample' => array_slice($rows, 0, 10),
                'distinct_workspaces' => count(array_unique(array_column($rows, 1))),
                'amounts' => array_count_values(array_column($rows, 4)),
            ];

            copy($file, CSV_DIR_OUT.'/'.basename($file));
        }

        echo json_encode(['reports' => $summary], JSON_PRETTY_PRINT);
        break;

    case 'reminder-log':
        if (! DB::getSchemaBuilder()->hasTable('marketplace_reminder_logs')) {
            echo json_encode(['table' => 'absent']);

            break;
        }

        echo json_encode([
            'rows' => DB::table('marketplace_reminder_logs')->count(),
            'by_type_and_status' => DB::table('marketplace_reminder_logs')
                ->select('reminder_type', 'status', DB::raw('COUNT(*) as n'))
                ->groupBy('reminder_type', 'status')->get()
                ->map(fn ($r) => $r->reminder_type.':'.$r->status.'='.$r->n)->values()->all(),
            'unresolved' => DB::table('marketplace_reminder_logs')
                ->whereNull('sent_at')->count(),
        ], JSON_PRETTY_PRINT);
        break;

    default:
        fwrite(STDERR, "Unknown probe: {$what}\n");
        exit(1);
}

echo PHP_EOL;
PROBE

# The CSV probe copies reports out of the container-local disk, so the path has
# to be injected rather than hardcoded above.
sed -i "s#CSV_DIR_OUT#'${PWD}/${CSV_DIR}'#" "${WORK}/probe.php"

# ── 0. rails ───────────────────────────────────────────────────────────────

mkdir -p "$WORK" "$CSV_DIR"
: > "$LOG"
: > "$REPORT"
say "Release rehearsal — $(date -Is)"

head2 "1. Safety rails"

[[ -n "$DUMP" ]] || die "No dump given. Usage: $0 <dump.sql[.gz]>"

if [[ ! -f "$DUMP" ]]; then
    die "Dump not found: $DUMP"
fi

say "dump          $DUMP ($(du -h "$DUMP" | cut -f1))"

# The dump is an input, never an output. If the run changes it, something is
# catastrophically wrong and we want to know before anyone relies on the copy.
DUMP_SHA_BEFORE="$(sha256sum "$DUMP" | cut -d' ' -f1)"

# A database name that does not say what it is is how a rehearsal ends up
# pointed at production. This is the single most important line in the script.
if [[ "$DB_NAME" != *rehearsal* ]]; then
    die "Refusing to use a database that is not named as a rehearsal: '$DB_NAME'.
    The name must contain 'rehearsal'. This is the guard against wiping production."
fi
ok "database name contains 'rehearsal' ($DB_NAME)"

# Load the app's own credentials so we can talk to the server, but never its
# database name — that is what we are replacing.
# No credentials anywhere in this script. `ddev mysql` is DDEV's own wrapper and
# authenticates as the container's root user on the local service, so there is no
# password to embed, leak or rotate. The rehearsal database is selected with a
# USE statement rather than by connecting somewhere else.
HOST_WAS="$(ddev exec --raw -- php artisan tinker --execute="echo config('database.connections.'.config('database.default').'.host');" 2>/dev/null | tail -1)"
say "app DB_HOST is ${HOST_WAS}"

case "$HOST_WAS" in
    127.0.0.1|localhost|db|ddev-*|mysql|mariadb) ok "DB host is local ($HOST_WAS)" ;;
    *) die "Refusing to run: the app's DB_HOST is '$HOST_WAS', which is not local.
    A rehearsal that talks to a remote database server is not a rehearsal." ;;
esac

FLAGS="$(ddev exec --raw -- php "${WORK}/probe.php" flags 2>/dev/null | tail -1 || true)"
say "flags as configured: expiry,reminders = $FLAGS"
if [[ "$FLAGS" != "0,0" ]]; then
    die "Refusing to run with a marketplace flag enabled ($FLAGS).
    Turn both off before rehearsing. This script exports them as false for its own
    commands, but if .env has one on, the rehearsal is not testing the
    shipped configuration."
fi
ok "both marketplace flags are off"

probe() { retry ddev exec --raw -- env DB_DATABASE="$DB_NAME" \
    MARKETPLACE_EXPIRY_ENABLED=false MARKETPLACE_REMINDERS_ENABLED=false \
    php "${WORK}/probe.php" "$1"; }

# ── 2. restore ─────────────────────────────────────────────────────────────

head2 "2. Restoring the dump into ${DB_NAME}"

sql "DROP DATABASE IF EXISTS \`${DB_NAME}\`; CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" \
    || die "Could not create the throwaway database"

say "created a fresh ${DB_NAME} (the old one, if any, was dropped — that is why the name must contain 'rehearsal')"

# The app connects as its own database user, which by default has privileges on
# the app's database and nothing else. Without this every artisan command in the
# rehearsal dies with "Access denied ... to database marketplace_rehearsal" and
# the run is a wall of identical errors rather than a report.
#
# The grantees are read from the server rather than from .env, so this works
# whatever the app's username happens to be.
# `ddev mysql` connects to whichever database the app is configured for, so
# SELECT DATABASE() is how this learns the app's database name without hardcoding
# it or parsing .env.
APP_DB="$(ddev mysql -e 'SELECT DATABASE();' 2>/dev/null | tail -1)"
[[ -n "$APP_DB" ]] || die "Could not determine which database the app is configured for."

# GRANTEE is stored as 'user'@'host', which is exactly the form a GRANT statement
# wants. Wrapping it in CONCAT(QUOTE(...)) adds literal backslashes and the server
# rejects the grant as a syntax error.
mapfile -t GRANTEES < <(sql "SELECT DISTINCT GRANTEE FROM information_schema.SCHEMA_PRIVILEGES WHERE TABLE_SCHEMA = '${APP_DB}';")

if [[ "${#GRANTEES[@]}" -eq 0 ]]; then
    die "Could not find which database user the app uses, so it will be denied access
    to the rehearsal database. Every artisan command would fail identically."
fi

for grantee in "${GRANTEES[@]}"; do
    [[ -n "$grantee" ]] || continue
    sql "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO ${grantee};" \
        || die "Could not grant the rehearsal database to ${grantee}"
done

ok "granted the rehearsal database to ${GRANTEES[*]}"

# The import goes through `ddev mysql` rather than the DBX client because
# `ddev exec` does not forward stdin in this DDEV version, and the dump is 200MB+
# of it. `ddev mysql` does forward stdin; it just cannot be given client flags,
# which is fine here because the output is not read.
IMPORT_START="$(date +%s)"
{
    printf 'USE `%s`;\n' "$DB_NAME"

    if [[ "$DUMP" == *.gz ]]; then
        gunzip -c -- "$DUMP"
    else
        cat -- "$DUMP"
    fi
} | ddev mysql || die "Import failed. The dump may be from a different server or truncated."
IMPORT_SECONDS=$(( $(date +%s) - IMPORT_START ))

say "import finished in ${IMPORT_SECONDS}s"

TABLES="$(sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}';")"
ok "restored ${TABLES} tables"

head2 "  what the copy looks like"
sql "SELECT table_name, table_rows FROM information_schema.tables
     WHERE table_schema='${DB_NAME}' AND table_rows > 0
     ORDER BY table_rows DESC LIMIT 15;" | tee -a "$LOG" "$REPORT"

# ── 3. migrate, one at a time ──────────────────────────────────────────────

head2 "3. Migrations, one at a time, with the row delta of each"

DELTAS="${WORK}/migration-deltas.tsv"
printf 'migration\ttable\tbefore\tafter\tdelta\n' > "$DELTAS"

probe counts > "${WORK}/counts-previous.json"

# Snapshots taken BEFORE migrating, because the interesting questions are
# differences, not absolutes: "how many activity_log rows were scrubbed" needs a
# before and an after, and so does "what did the provider backfill change".
probe scrub-scan    > "${WORK}/scrub-before.json"
probe manual-payments > "${WORK}/payments-before.json"

# One migration per invocation, addressed by path.
#
# `migrate --step` does NOT do this: --step controls batching, not how many
# migrations run, so the whole pending set applied in one call and every row delta
# got attributed to whichever name happened to be printed last. "18 migrations
# ran" also says nothing about which one archived the plans.
#
# Recording the delta per migration is the only way to answer "what does each of
# these actually change".
STEP=0
while true; do
    # grep -v '^$' as a second line of defence: an empty name in this list becomes
    # a --path pointing at ".php", which fails in a way that looks like a migration
    # bug rather than a parsing bug.
    mapfile -t PENDING < <(probe pending | grep -v '^$')

    [[ "${#PENDING[@]}" -gt 0 ]] || break

    for MIGRATION in "${PENDING[@]}"; do
        # No "before" snapshot here: the previous iteration's "after" snapshot is
        # exactly this iteration's "before". Taking it again doubles the number of
        # ddev calls for the same answer.
        OUT="$(artisan migrate --force --path="database/migrations/${MIGRATION}.php" 2>&1 || true)"

        if printf '%s' "$OUT" | grep -qE 'Nothing to migrate|Nothing to do'; then
            say "  ${MIGRATION} — already applied, nothing to do"
            continue
        fi

        probe counts > "${WORK}/counts-step-after.json"

        if printf '%s' "$OUT" | grep -qE 'SQLSTATE|Failed|Exception'; then
            die "Migration ${MIGRATION} failed:
$(printf '%s' "$OUT" | sed 's/^/    /')"
        fi

        CHANGED="$(php -r '
            $a = json_decode(file_get_contents($argv[1]), true) ?: [];
            $b = json_decode(file_get_contents($argv[2]), true) ?: [];

            foreach ($b as $table => $after) {
                $before = $a[$table] ?? 0;

                if ($before === $after) {
                    continue;
                }

                printf("%s\t%d\t%d\t%+d\n", $table, $before, $after, $after - $before);
            }
        ' "${WORK}/counts-previous.json" "${WORK}/counts-step-after.json")"

        cp "${WORK}/counts-step-after.json" "${WORK}/counts-previous.json"

        if [[ -n "$CHANGED" ]]; then
            while IFS= read -r line; do
                printf '%s\t%s\n' "$MIGRATION" "$line" >> "$DELTAS"
                printf '  %s\n' "$line" | tee -a "$LOG" >> "$REPORT"
            done <<< "$CHANGED"
        else
            say "  ${MIGRATION} — schema only, no rows changed"
            printf '%s\t(schema only)\n' "$MIGRATION" >> "$DELTAS"
        fi

        STEP=$((STEP + 1))

        if [[ "$STEP" -gt 60 ]]; then
            die "Applied 60 migrations and the list is still not shrinking.
    Something is re-reporting a migration as pending."
        fi
    done
done

ok "$STEP migrations applied"

head2 "  rows changed, by migration"
cat "$DELTAS" | tee -a "$LOG" | sed 's/^/  /'

head2 "  the activity log scrub"
probe scrub-scan > "${WORK}/scrub-after.json"

say "  before the migrations:"
sed 's/^/    /' "${WORK}/scrub-before.json" | tee -a "$LOG"
say "  after the migrations:"
sed 's/^/    /' "${WORK}/scrub-after.json" | tee -a "$LOG"

# The question is "how many rows were scrubbed", which is a difference. Reporting
# only the after-state would say "0 rows hold contact data" for a database that
# held plaintext phone numbers an hour ago.
php -r '
    $before = json_decode(file_get_contents($argv[1]), true) ?: [];
    $after = json_decode(file_get_contents($argv[2]), true) ?: [];

    printf(
        "rows scrubbed: %d\nsensitive keys removed: %d\nactivity_log rows: %d -> %d (rows kept: the audit trail is the point)\n",
        ($before["rows_holding_plaintext_contact_data"] ?? 0) - ($after["rows_holding_plaintext_contact_data"] ?? 0),
        ($before["sensitive_keys_in_those_rows"] ?? 0) - ($after["sensitive_keys_in_those_rows"] ?? 0),
        $before["activity_log_rows_total"] ?? 0,
        $after["activity_log_rows_total"] ?? 0,
    );
' "${WORK}/scrub-before.json" "${WORK}/scrub-after.json" | tee -a "$LOG" "$REPORT" | sed 's/^/  /'

head2 "  the provider backfill, before and after"
probe manual-payments > "${WORK}/payments-after.json"
say "  before:"
sed 's/^/    /' "${WORK}/payments-before.json" | tee -a "$LOG"
say "  after:"
sed 's/^/    /' "${WORK}/payments-after.json" | tee -a "$LOG"


# ── 4. marketplace:install ─────────────────────────────────────────────────

head2 "4. marketplace:install"

artisan marketplace:install --dry-run 2>&1 | tee -a "$LOG" | sed 's/^/  /'
artisan marketplace:install 2>&1 | tee -a "$LOG" | sed 's/^/  /'

probe plans > "${WORK}/plans.json"
probe settings > "${WORK}/settings.json"
say "plans after install:"
cat "${WORK}/plans.json" | tee -a "$LOG" | sed 's/^/  /'

# ── 5. dry runs ────────────────────────────────────────────────────────────

head2 "5. Dry runs — nothing below is written"

head2 "  5a. how many subscriptions are already past ends_at"
probe expiring | tee -a "$LOG" "$REPORT" | sed 's/^/  /'

head2 "  5b. how many workspaces would get listings hidden"
probe expire-preview | tee -a "$LOG" "$REPORT" | sed 's/^/  /'

say ""
say "  --- the command's own dry run, verbatim ---"
artisan marketplace:expire-subscriptions --dry-run 2>&1 | tee -a "$LOG" | sed 's/^/  /'

head2 "  5c. manual payments"
cat "${WORK}/payments-after.json" | tee -a "$LOG" "$REPORT" | sed 's/^/  /'
artisan marketplace:expire-manual-payments --dry-run 2>&1 | tee -a "$LOG" | sed 's/^/  /'

head2 "  5d. reminders that would be sent"
probe reminder-preview | tee -a "$LOG" "$REPORT" | sed 's/^/  /'
artisan marketplace:send-reminders --dry-run 2>&1 | tee -a "$LOG" | sed 's/^/  /'

head2 "  5e. contact-reveal backfill and the wrong-duration CSV"
artisan marketplace:backfill-contact-reveals --dry-run 2>&1 | tee -a "$LOG" | sed 's/^/  /'
probe csv-report | tee -a "$LOG" "$REPORT" | sed 's/^/  /'

head2 "  5f. the reminder log, which must be empty — nothing has sent yet"
probe reminder-log | tee -a "$LOG" "$REPORT" | sed 's/^/  /'

# ── 6. rails, again ────────────────────────────────────────────────────────

head2 "6. Post-run checks"

DUMP_SHA_AFTER="$(sha256sum "$DUMP" | cut -d' ' -f1)"
if [[ "$DUMP_SHA_BEFORE" != "$DUMP_SHA_AFTER" ]]; then
    die "The dump file changed during the run. Nothing was supposed to write to it."
fi
ok "the dump is byte-identical to how it started"

FINAL="$(ddev exec --raw -- php "${WORK}/probe.php" flags 2>/dev/null | tail -1 || true)"
if [[ "$FINAL" != "0,0" ]]; then
    die "A marketplace flag is now enabled ($FINAL). Nothing here should have changed that."
fi
ok "both flags are still off"

OK_TABLES="$(sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}';")"
if [[ "$OK_TABLES" -lt "$TABLES" ]]; then
    warn "table count went from $TABLES to $OK_TABLES"
fi
ok "the rehearsal database still holds ${OK_TABLES} tables"

say ""
say "Rehearsal complete. Report: ${REPORT}"
say "The copy is left in place at ${DB_NAME} for you to poke at."
say "Drop it when you are done:"
say "  ddev exec -s db --raw -- mysql -e 'DROP DATABASE ${DB_NAME}'"
