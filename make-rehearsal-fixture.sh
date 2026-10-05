#!/usr/bin/env bash
#
# make-rehearsal-fixture.sh — build a throwaway dump that looks like production.
#
# NOT a substitute for the real dump. This exists so the rehearsal harness can be
# proven to work before the real data arrives, and so the report can be read
# against numbers whose correct answer is already known.
#
# The schema is the PRE-RELEASE one. The 18 marketplace migrations are moved out of
# the way, `migrate` runs everything else, then they go back. Copying the current
# database instead would produce a schema that already has the new columns, so
# every migration would fail with "duplicate column" and the rehearsal would
# measure nothing. This is what makes the fixture a genuine "production at the
# commit before this release".
#
# The fixture rows hit the interesting paths on purpose:
#   - subscriptions past ends_at, inside and outside the expiry window
#   - a workspace over the free limit, so listings would actually be hidden
#   - activity_log rows holding plaintext contact data, so the scrub has work
#   - manual payments in several statuses, one carrying contact columns that only
#     exist after the manual-payment migrations run

set -euo pipefail

APP="${APP_ROOT:-$PWD}"
FIXTURE_DB="${FIXTURE_DB:-marketplace_fixture}"
OUT="${FIXTURE_OUT:-/tmp/rehearsal-fixture.sql.gz}"

cd "$APP"

[[ -f artisan && -d .ddev ]] || { echo "Run this from the project root."; exit 2; }

dx() { ddev exec --raw -- "$@"; }

# `ddev mysql` connects to whichever database the app is configured for, so
# SELECT DATABASE() is how this learns the app's database name without hardcoding
# it or parsing .env.
APP_DB="$(ddev mysql -e 'SELECT DATABASE();' 2>/dev/null | tail -1)"
[[ -n "$APP_DB" ]] || { echo "Could not determine the app database"; exit 1; }

mkdir -p storage/app/rehearsal

# ── the hold/restore helper ────────────────────────────────────────────────

# Moves the 18 marketplace migrations aside so the pre-release schema can be built,
# and puts them back afterwards. Generated rather than committed: it is a fixture
# tool, not product surface.
cat > storage/app/rehearsal/hold.php <<'HOLD'
<?php

// The marketplace migrations are exactly the 2026_10_* files. Verified by listing
// them: nothing pre-existing in this repo uses that prefix, so the prefix is a
// reliable marker for "arriving in this release" rather than a hardcoded list
// that would drift.

// storage/app/rehearsal -> three levels up is the project root. Two lands in
// storage/, which globs nothing and silently holds zero files.
$dir = __DIR__.'/../../../database/migrations';
$hold = '/tmp/held-migrations';

$new = static fn (string $name): bool => (bool) preg_match('/^2026_10_/', $name);

$action = $argv[1] ?? '';

if ($action === 'hold') {
    @mkdir($hold, 0777, true);

    $moved = 0;

    foreach (glob($dir.'/*.php') as $file) {
        if ($new(basename($file))) {
            rename($file, $hold.'/'.basename($file));
            $moved++;
        }
    }

    echo "held {$moved}\n";
    exit($moved > 0 ? 0 : 1);
}

if ($action === 'restore') {
    $restored = 0;

    foreach (glob($hold.'/*.php') as $file) {
        rename($file, $dir.'/'.basename($file));
        $restored++;
    }

    echo "restored {$restored}\n";
    exit(0);
}

if ($action === 'count') {
    printf('%d', count(glob($hold.'/*.php')));
    exit(0);
}

fwrite(STDERR, "usage: hold.php hold|restore|count\n");
exit(1);
HOLD

restore_migrations() {
    # Always put them back. A rehearsal that leaves the working tree without its
    # migrations is worse than one that failed outright.
    dx php storage/app/rehearsal/hold.php restore || true
}

# ── 1. the pre-release schema ──────────────────────────────────────────────

echo "Building ${FIXTURE_DB} at the pre-release schema (base: ${APP_DB})…"

ddev mysql -e "DROP DATABASE IF EXISTS \`${FIXTURE_DB}\`; CREATE DATABASE \`${FIXTURE_DB}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" \
    || { echo "could not create the fixture database"; exit 1; }

# The app connects as its own database user, which by default has privileges on
# the app's database and nothing else. Read the grantees from the server rather
# than from .env so this works whatever the username happens to be.
mapfile -t GRANTEES < <(ddev exec -s db --raw -- mysql -N -B \
    -e "SELECT DISTINCT GRANTEE FROM information_schema.SCHEMA_PRIVILEGES WHERE TABLE_SCHEMA='${APP_DB}';")

for grantee in "${GRANTEES[@]}"; do
    ddev exec -s db --raw -- mysql -e "GRANT ALL PRIVILEGES ON \`${FIXTURE_DB}\`.* TO ${grantee};" \
        || { echo "grant to ${grantee} failed"; exit 1; }
done

echo "held: $(dx php storage/app/rehearsal/hold.php hold)"
trap restore_migrations EXIT

echo "Running the pre-release migrations…"
dx env DB_DATABASE="$FIXTURE_DB" php artisan migrate --force --no-interaction > /tmp/pre-release-migrate.log 2>&1 \
    || { echo "pre-release migrate failed:"; tail -20 /tmp/pre-release-migrate.log; exit 1; }
tail -2 /tmp/pre-release-migrate.log | sed 's/^/  /'

restore_migrations
trap - EXIT
echo "migrations restored; $(dx php storage/app/rehearsal/hold.php count) still held"

FIXTURE_TABLES="$(ddev exec -s db --raw -- mysql -N -B -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${FIXTURE_DB}';")"
APP_TABLES="$(ddev exec -s db --raw -- mysql -N -B -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${APP_DB}';")"
echo "pre-release schema: ${FIXTURE_TABLES} tables (the current app has ${APP_TABLES})"

# ── 2. the fixture rows ────────────────────────────────────────────────────

cat > storage/app/rehearsal/fixture.php <<'FIXTURE'
<?php

/**
 * Rows chosen so the rehearsal report has known answers to check against.
 *
 * Only columns that exist are inserted, because at this point the manual-payment
 * columns do not exist yet — they arrive with the migrations the rehearsal is
 * about to run. That is deliberate: the report can then show "0 rows held a payer
 * phone" before and "1" after, which is the migration's real effect.
 */

// Raw PDO, so it needs credentials of its own; `ddev mysql` cannot be used from
// inside PHP. These are DDEV's documented local defaults for a container that is
// destroyed by `ddev delete`, in a throwaway script that is never committed. The
// committed rehearsal harness has no credentials at all.
$pdo = new PDO(
    'mysql:host=db;dbname='.getenv('FIXTURE_DB'),
    getenv('FIXTURE_USER'),
    getenv('FIXTURE_PASS'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);

$has = fn (string $table): bool => (bool) $pdo->query('SHOW TABLES LIKE '.$pdo->quote($table))->fetchColumn();

// use ($pdo): a plain closure does not capture the enclosing scope. $has is an
// arrow function, which captures implicitly — which is why only this one broke
// the first time.
$columns = function (string $table) use ($pdo): array {
    // SHOW COLUMNS returns a column's name under the key 'Field'. The key called
    // 'name' is the collation: asking for it yields an empty list, so every insert
    // is filtered out and the fixture comes out empty with no error anywhere.
    return array_column($pdo->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC), 'Field');
};

/** Insert only the columns that exist, so a schema that grew a NOT NULL column
 *  this fixture does not know about is a visible omission rather than a crash. */
$insert = function (string $table, array $rows) use ($pdo, $columns): int {
    $available = $columns($table);
    $inserted = 0;

    foreach ($rows as $row) {
        $use = array_intersect_key($row, array_flip($available));

        if ($use === []) {
            continue;
        }

        $sql = 'INSERT INTO `'.$table.'` ('
            .implode(', ', array_map(fn ($k) => "`{$k}`", array_keys($use))).') VALUES ('
            .implode(', ', array_fill(0, count($use), '?')).')';

        $pdo->prepare($sql)->execute(array_values($use));
        $inserted++;
    }

    return $inserted;
};

$now = date('Y-m-d H:i:s');
$d = fn (string $when): string => date('Y-m-d H:i:s', strtotime($when));
$day = fn (string $when): string => date('Y-m-d', strtotime($when));

$total = 0;

if ($has('users')) {
    $total += $insert('users', [
        ['id'=>1,'name'=>'Fixture Owner','email'=>'owner@fixture.test','email_verified_at'=>$now,'password'=>'x','created_at'=>$now,'updated_at'=>$now],
        ['id'=>2,'name'=>'Fixture Treasurer','email'=>'treasurer@fixture.test','email_verified_at'=>$now,'password'=>'x','created_at'=>$now,'updated_at'=>$now],
    ]);
}

if ($has('workspaces')) {
    $total += $insert('workspaces', [
        // Renewing soon. Monthly, one day out -> renewal_1d.
        ['id'=>1,'name'=>'Acme Freight','slug'=>'acme-freight','owner_id'=>1,'created_at'=>$now,'updated_at'=>$now],
        // Yearly with 20 hours left: inside the 1d, 3d AND 7d windows at once.
        ['id'=>2,'name'=>'Beta Logistics','slug'=>'beta-logistics','owner_id'=>1,'created_at'=>$now,'updated_at'=>$now],
        // Lapsed one day ago AND over the free limit: gets plan_expired, and its
        // listings would be hidden.
        ['id'=>3,'name'=>'Gamma Over Limit','slug'=>'gamma-over-limit','owner_id'=>1,'created_at'=>$now,'updated_at'=>$now],
        // Lapsed twenty days ago: must get NOTHING. The backlog case the expiry
        // window exists for, and the one worth having in the fixture.
        ['id'=>4,'name'=>'Delta Long Gone','slug'=>'delta-long-gone','owner_id'=>1,'created_at'=>$now,'updated_at'=>$now],
    ]);
}

if ($has('workspace_user')) {
    $total += $insert('workspace_user', [
        ['workspace_id'=>1,'user_id'=>1,'role'=>'owner','permissions'=>null,'created_at'=>$now,'updated_at'=>$now],
        // Granted billing explicitly: should be notified alongside the owner.
        ['workspace_id'=>1,'user_id'=>2,'role'=>'member','permissions'=>'["manage_billing"]','created_at'=>$now,'updated_at'=>$now],
        ['workspace_id'=>2,'user_id'=>1,'role'=>'owner','permissions'=>null,'created_at'=>$now,'updated_at'=>$now],
        ['workspace_id'=>3,'user_id'=>1,'role'=>'owner','permissions'=>null,'created_at'=>$now,'updated_at'=>$now],
        ['workspace_id'=>4,'user_id'=>1,'role'=>'owner','permissions'=>null,'created_at'=>$now,'updated_at'=>$now],
    ]);
}

if ($has('marketplace_plans')) {
    $total += $insert('marketplace_plans', [
        ['id'=>1,'name'=>'Standard','slug'=>'standard','description'=>'The one paid plan',
         'price_weekly_etb'=>3000,'price_monthly_etb'=>449,'price_yearly_etb'=>44900,
         'currency'=>'ETB','active'=>1,'created_at'=>$now,'updated_at'=>$now],
    ]);
}

if ($has('marketplace_subscriptions')) {
    $total += $insert('marketplace_subscriptions', [
        ['workspace_id'=>1,'plan_id'=>1,'user_id'=>1,'status'=>'active','billing_interval'=>'monthly',
         'current_period_start'=>$day('-29 days'),'current_period_ends_at'=>$day('+1 day'),
         'ends_at'=>$day('+1 day'),'chapa_tx_ref'=>'NF-FIX-0001','created_at'=>$now,'updated_at'=>$now],
        ['workspace_id'=>2,'plan_id'=>1,'user_id'=>1,'status'=>'active','billing_interval'=>'yearly',
         'current_period_start'=>$day('-345 days'),'current_period_ends_at'=>$d('+20 hours'),
         'ends_at'=>$d('+20 hours'),'chapa_tx_ref'=>'NF-FIX-0002','created_at'=>$now,'updated_at'=>$now],
        ['workspace_id'=>3,'plan_id'=>1,'user_id'=>1,'status'=>'active','billing_interval'=>'monthly',
         'current_period_start'=>$day('-31 days'),'current_period_ends_at'=>$day('-1 day'),
         'ends_at'=>$day('-1 day'),'chapa_tx_ref'=>'NF-FIX-0003','created_at'=>$now,'updated_at'=>$now],
        ['workspace_id'=>4,'plan_id'=>1,'user_id'=>1,'status'=>'active','billing_interval'=>'monthly',
         'current_period_start'=>$day('-50 days'),'current_period_ends_at'=>$day('-20 days'),
         'ends_at'=>$day('-20 days'),'chapa_tx_ref'=>'NF-FIX-0004','created_at'=>$now,'updated_at'=>$now],
    ]);
}

// Reference rows the freight_post foreign keys need.
if ($has('cargo_types')) {
    $total += $insert('cargo_types', [
        ['id'=>1,'name'=>'General Cargo','slug'=>'general-cargo','is_active'=>1,'created_at'=>$now,'updated_at'=>$now],
    ]);
}

if ($has('truck_types')) {
    $total += $insert('truck_types', [
        ['id'=>1,'name'=>'Rigid 20 Ton','slug'=>'rigid-20-ton','is_active'=>1,'created_at'=>$now,'updated_at'=>$now],
    ]);
}

if ($has('freight_posts')) {
    $rows = [];

    for ($i = 0; $i < 4; $i++) {
        $rows[] = [
            // A ULID is exactly 26 characters and the column enforces it.
            'ulid'=>sprintf('01FIXTURE%017d', $i),
            'user_id'=>1,'workspace_id'=>3,'status'=>'active','cargo_type_id'=>1,
            'item_name'=>'Cement','actual_weight'=>20,'weight_unit'=>'ton',
            'tariff_amount'=>15000,'tariff_currency'=>'ETB','truck_count'=>2,
            'required_truck_type_id'=>1,'schedule_type'=>'one_time',
            'pickup_date'=>$day('+5 days'),'created_at'=>$now,'updated_at'=>$now,
        ];
    }

    $total += $insert('freight_posts', $rows);
}

if ($has('activity_log')) {
    $total += $insert('activity_log', [
        // Plaintext phone in both the new and the old value bag.
        ['log_name'=>'profile.updated','description'=>'Profile updated','event'=>'updated',
         'subject_type'=>'App\\Models\\Profile','subject_id'=>1,'causer_type'=>'App\\Models\\User','causer_id'=>1,
         'properties'=>json_encode(['attributes'=>['phone'=>'+251911000001','name'=>'Fixture Owner'],'old'=>['phone'=>'+251911999999']]),
         'created_at'=>$now,'updated_at'=>$now],
        ['log_name'=>'profile.updated','description'=>'Profile updated','event'=>'updated',
         'subject_type'=>'App\\Models\\Profile','subject_id'=>1,'causer_type'=>'App\\Models\\User','causer_id'=>1,
         'properties'=>json_encode(['attributes'=>['phone'=>'+251911000002']]),
         'created_at'=>$now,'updated_at'=>$now],
        ['log_name'=>'profile.updated','description'=>'Profile updated','event'=>'updated',
         'subject_type'=>'App\\Models\\TransporterProfile','subject_id'=>1,'causer_type'=>'App\\Models\\User','causer_id'=>1,
         'properties'=>json_encode(['attributes'=>['whatsapp'=>'+251911000003','legal_name'=>'Fixture Haulage Ltd']]),
         'created_at'=>$now,'updated_at'=>$now],
        ['log_name'=>'interest.created','description'=>'Interest created','event'=>'created',
         'subject_type'=>'App\\Models\\FreightInterest','subject_id'=>1,'causer_type'=>'App\\Models\\User','causer_id'=>1,
         'properties'=>json_encode(['attributes'=>['note'=>'call +251911000004 about this load']]),
         'created_at'=>$now,'updated_at'=>$now],
        // Nothing sensitive. The scrub must leave this row byte-identical.
        ['log_name'=>'freight.updated','description'=>'Freight updated','event'=>'updated',
         'subject_type'=>'App\\Models\\FreightPost','subject_id'=>1,'causer_type'=>'App\\Models\\User','causer_id'=>1,
         'properties'=>json_encode(['attributes'=>['item_name'=>'Cement']]),
         'created_at'=>$now,'updated_at'=>$now],
    ]);
}

if ($has('marketplace_payment_transactions')) {
    $base = ['workspace_id'=>1,'plan_id'=>1,'user_id'=>1,'currency'=>'ETB','amount_etb'=>449,
             'billing_interval'=>'monthly','created_at'=>$now,'updated_at'=>$now];

    $total += $insert('marketplace_payment_transactions', [
        array_merge($base, ['status'=>'pending','provider'=>'chapa','tx_ref'=>'NF-FIX-TX-1']),
        // The manual_* columns do not exist before the manual-payment migrations,
        // so these are dropped on this run and appear once the rehearsal migrates.
        array_merge($base, ['status'=>'pending','provider'=>'manual','tx_ref'=>'NF-FIX-TX-2',
            'manual_method'=>'telebirr','manual_payer_phone'=>'+251911223344',
            'manual_reference'=>'FT123456789','manual_proof_path'=>'manual-payment-proofs/2026/10/fix.png']),
        array_merge($base, ['status'=>'verified','provider'=>'chapa','tx_ref'=>'NF-FIX-TX-3']),
        // Old enough that the manual-payment expiry sweep would claim it.
        array_merge($base, ['status'=>'pending','provider'=>'manual','tx_ref'=>'NF-FIX-TX-4',
            'manual_method'=>'bank','submitted_at'=>$d('-40 days')]),
    ]);
}

echo "inserted {$total} rows\n";
FIXTURE

echo "Inserting fixture rows…"
dx env FIXTURE_DB="$FIXTURE_DB" FIXTURE_USER="${FIXTURE_DB_USER:-root}" FIXTURE_PASS="${FIXTURE_DB_PASSWORD:-}" \
    php storage/app/rehearsal/fixture.php

rm -f storage/app/rehearsal/fixture.php storage/app/rehearsal/hold.php

# ── 3. dump ────────────────────────────────────────────────────────────────

echo "Dumping to ${OUT}…"
ddev exec -s db --raw -- mysqldump --routines --triggers "$FIXTURE_DB" | gzip -9 > "$OUT"

ls -lh "$OUT"
echo "Fixture dump ready."
