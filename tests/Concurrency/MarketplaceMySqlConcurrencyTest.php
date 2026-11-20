<?php

use App\Enums\FreightInterestStatus;
use App\Enums\FreightPostStatus;
use App\Models\FreightInterest;
use App\Models\FreightPost;
use App\Models\MarketplaceConnection;
use App\Models\MarketplacePaymentReceipt;
use App\Models\MarketplacePaymentTransaction;
use App\Models\MarketplacePlan;
use App\Models\MarketplaceSubscription;
use App\Models\Profile;
use App\Models\User;
use App\Services\MarketplaceSettingsService;
use App\Services\WorkspaceService;
use Database\Seeders\MarketplacePlanSeeder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

beforeEach(function () {
    // This suite runs in its own MySQL-backed CI job. SQLite cannot exercise
    // InnoDB row locking, and RefreshDatabase hides fixtures from workers.
    expect(DB::getDriverName())->toBe('mysql');

    $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
});

/**
 * Open two real, independent PHP/MySQL connections while the parent owns the
 * shared row lock. Release the lock after both children signal readiness.
 *
 * @param  list<array{id: int, actor: int}>  $operations
 */
function raceMySqlWorkers(string $mode, string $lockTable, int $lockRowId, array $operations): void
{
    $connection = config('database.connections.mysql');
    $environment = [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'mysql',
        'DB_HOST' => (string) $connection['host'],
        'DB_PORT' => (string) $connection['port'],
        'DB_DATABASE' => (string) $connection['database'],
        'DB_USERNAME' => (string) $connection['username'],
        'DB_PASSWORD' => (string) $connection['password'],
        'MAIL_MAILER' => 'array',
        'QUEUE_CONNECTION' => 'sync',
        'SESSION_DRIVER' => 'array',
        'CACHE_STORE' => 'array',
        'BROADCAST_CONNECTION' => 'null',
    ];

    $prefix = sys_get_temp_dir().'/numan-mysql-race-'.bin2hex(random_bytes(8));
    $children = [];
    $markers = [];

    DB::beginTransaction();

    try {
        // One shared parent lock queues both competing operations until both
        // child processes are alive. This is real MySQL locking, not sequential
        // HTTP requests against SQLite.
        $locked = DB::table($lockTable)
            ->where('id', $lockRowId)
            ->lockForUpdate()
            ->first();

        expect($locked)->not->toBeNull();

        foreach ($operations as $index => $operation) {
            $marker = $prefix.'-'.$index;
            $markers[] = $marker;
            $child = new Process([
                PHP_BINARY,
                base_path('tests/Support/mysql-concurrency-worker.php'),
                $operation['mode'] ?? $mode,
                (string) $operation['id'],
                (string) $operation['actor'],
                $marker,
            ], base_path(), $environment);

            $child->setTimeout(45);
            $child->start();
            $children[] = $child;
        }

        $deadline = microtime(true) + 12;

        while (count(array_filter($markers, 'file_exists')) !== count($markers)) {
            foreach ($children as $child) {
                if (! $child->isRunning()) {
                    throw new RuntimeException('Concurrency worker failed before ready: '.$child->getErrorOutput());
                }
            }

            if (microtime(true) > $deadline) {
                throw new RuntimeException('Concurrency workers never became ready.');
            }

            usleep(50000);
        }

        // Give both clients an opportunity to enter the locked action.
        usleep(150000);
    } finally {
        DB::commit();
    }

    try {
        foreach ($children as $child) {
            $child->wait();

            expect($child->isSuccessful())->toBeTrue(
                'Child process failed: '.$child->getErrorOutput().' '.$child->getOutput(),
            );
        }
    } finally {
        foreach ($markers as $marker) {
            @unlink($marker);
        }
    }
}

function mysqlPaymentFixture(User $payer, int $workspaceId, int $planId, string $reference): MarketplacePaymentTransaction
{
    return MarketplacePaymentTransaction::create([
        'workspace_id' => $workspaceId,
        'plan_id' => $planId,
        'user_id' => $payer->id,
        'tx_ref' => $reference,
        'amount_etb' => 449,
        'currency' => 'ETB',
        'billing_interval' => 'monthly',
        'status' => 'initialized',
        'mode' => 'test',
        'provider' => 'chapa',
    ]);
}

it('permits only one competing transporter acceptance against the same freight', function () {
    $consignor = User::factory()->create();
    $firstCarrier = User::factory()->create();
    $secondCarrier = User::factory()->create();

    Profile::factory()->consignor()->create(['user_id' => $consignor->id]);
    Profile::factory()->transporter()->approved()->create(['user_id' => $firstCarrier->id]);
    Profile::factory()->transporter()->approved()->create(['user_id' => $secondCarrier->id]);

    $post = FreightPost::factory()->for($consignor)->active()->create();
    $first = FreightInterest::factory()->for($post)->for($firstCarrier)->create();
    $second = FreightInterest::factory()->for($post)->for($secondCarrier)->create();

    raceMySqlWorkers('accept', 'freight_posts', $post->id, [
        ['id' => $first->id, 'actor' => $consignor->id],
        ['id' => $second->id, 'actor' => $consignor->id],
    ]);

    expect(MarketplaceConnection::where('freight_post_id', $post->id)->count())->toBe(1);
    expect(FreightInterest::where('freight_post_id', $post->id)
        ->where('status', FreightInterestStatus::Selected->value)
        ->count())->toBe(1);
    expect($post->fresh()->status)->toBe(FreightPostStatus::Closed->value);
    expect($post->moderationLogs()->where('reason', 'Accepted by consignor')->count())->toBe(1);
});

it('grants a single receipt and subscription for concurrent duplicate payment callbacks', function () {
    $this->seed(MarketplacePlanSeeder::class);

    $payer = User::factory()->create();
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($payer);
    $plan = MarketplacePlan::where('slug', 'standard')->firstOrFail();
    $payment = mysqlPaymentFixture($payer, $workspace->id, $plan->id, 'NF-mysql-duplicate-settlement');

    raceMySqlWorkers('fulfill', 'workspaces', $workspace->id, [
        ['id' => $payment->id, 'actor' => 0],
        ['id' => $payment->id, 'actor' => 0],
    ]);

    expect($payment->fresh()->status)->toBe('verified');
    expect(MarketplaceSubscription::where('chapa_tx_ref', $payment->tx_ref)->count())->toBe(1);
    expect(MarketplacePaymentReceipt::where('transaction_id', $payment->id)->count())->toBe(1);
});

it('serializes two separate renewals of one payer without losing paid days', function () {
    $this->seed(MarketplacePlanSeeder::class);

    $payer = User::factory()->create();
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($payer);
    $plan = MarketplacePlan::where('slug', 'standard')->firstOrFail();

    $first = mysqlPaymentFixture($payer, $workspace->id, $plan->id, 'NF-mysql-early-renewal-a');
    $second = mysqlPaymentFixture($payer, $workspace->id, $plan->id, 'NF-mysql-early-renewal-b');

    raceMySqlWorkers('fulfill', 'workspaces', $workspace->id, [
        ['id' => $first->id, 'actor' => 0],
        ['id' => $second->id, 'actor' => 0],
    ]);

    $subscriptions = MarketplaceSubscription::where('workspace_id', $workspace->id)
        ->orderBy('current_period_start')
        ->get();

    expect($subscriptions)->toHaveCount(2);
    expect(MarketplacePaymentReceipt::where('workspace_id', $workspace->id)->count())->toBe(2);
    expect($first->fresh()->status)->toBe('verified');
    expect($second->fresh()->status)->toBe('verified');
    expect($subscriptions[0]->ends_at->toDateTimeString())
        ->toBe($subscriptions[1]->current_period_start->toDateTimeString());
    expect((int) $subscriptions[0]->current_period_start->diffInDays($subscriptions[1]->ends_at))
        ->toBe(60);
});

it('serializes acceptance and owner cancellation on the same freight row', function () {
    $consignor = User::factory()->create();
    $carrier = User::factory()->create();

    Profile::factory()->consignor()->create(['user_id' => $consignor->id]);
    Profile::factory()->transporter()->approved()->create(['user_id' => $carrier->id]);

    $post = FreightPost::factory()->for($consignor)->active()->create();
    $interest = FreightInterest::factory()->for($post)->for($carrier)->create();

    raceMySqlWorkers('accept', 'freight_posts', $post->id, [
        ['id' => $interest->id, 'actor' => $consignor->id],
        ['id' => $post->id, 'actor' => $consignor->id, 'mode' => 'close'],
    ]);

    $post->refresh();
    $connections = MarketplaceConnection::where('freight_post_id', $post->id)->get();

    expect($post->status)->toBe(FreightPostStatus::Closed->value)
        ->and($connections->count())->toBeLessThanOrEqual(1)
        ->and($post->moderationLogs()->where('to_status', FreightPostStatus::Closed->value)->count())->toBe(1);

    if ($connections->isEmpty()) {
        expect($post->closure_reason)->toBe('cancelled')
            ->and($interest->fresh()->status)->not->toBe(FreightInterestStatus::Selected->value);
    } else {
        expect($post->closure_reason)->toBe('matched')
            ->and($interest->fresh()->status)->toBe(FreightInterestStatus::Selected->value);
    }
});

it('does not create two Chapa payment attempts from simultaneous checkout requests', function () {
    $this->seed(MarketplacePlanSeeder::class);

    $payer = User::factory()->create();
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($payer);
    $payer->switchWorkspace($workspace);

    // Hold the owner's workspace row so both independent PHP processes enter
    // the checkout race together. Provider initialization is fake in workers.
    raceMySqlWorkers('checkout', 'workspaces', $workspace->id, [
        ['id' => $workspace->id, 'actor' => $payer->id],
        ['id' => $workspace->id, 'actor' => $payer->id],
    ]);

    $attempts = MarketplacePaymentTransaction::query()
        ->where('workspace_id', $workspace->id)
        ->where('provider', MarketplacePaymentTransaction::PROVIDER_CHAPA)
        ->get();

    expect($attempts)->toHaveCount(1)
        ->and($attempts->first()->status)->toBe('initialized')
        ->and(MarketplaceSubscription::where('workspace_id', $workspace->id)->exists())->toBeFalse()
        ->and(MarketplacePaymentReceipt::where('workspace_id', $workspace->id)->exists())->toBeFalse();
});

it('never leaves paid freight hidden when expiry sweep races with Chapa renewal', function () {
    $this->seed(MarketplacePlanSeeder::class);

    $payer = User::factory()->create();
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($payer);
    $plan = MarketplacePlan::where('slug', 'standard')->firstOrFail();

    // Give expiry a real reason to hide posts while a new receipt restores
    // them. Both operations lock the same workspace row.
    app(MarketplaceSettingsService::class)
        ->setMany(['free_active_posts' => 2]);

    FreightPost::factory()->count(5)->for($payer)->active()->create([
        'workspace_id' => $workspace->id,
        'hidden_at' => null,
    ]);

    MarketplaceSubscription::create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $payer->id,
        'status' => 'expired',
        'billing_interval' => 'monthly',
        'current_period_start' => now()->subDays(31),
        'current_period_ends_at' => now()->subDay(),
        'ends_at' => now()->subDay(),
        'provider' => 'chapa',
        'chapa_tx_ref' => 'NF-mysql-expired-before-renewal',
    ]);

    $payment = mysqlPaymentFixture($payer, $workspace->id, $plan->id, 'NF-mysql-expiry-renewal-race');

    raceMySqlWorkers('hide-expired', 'workspaces', $workspace->id, [
        ['id' => $workspace->id, 'actor' => 0, 'mode' => 'hide-expired'],
        ['id' => $payment->id, 'actor' => 0, 'mode' => 'fulfill'],
    ]);

    expect($payment->fresh()->status)->toBe('verified')
        ->and(MarketplacePaymentReceipt::where('transaction_id', $payment->id)->count())->toBe(1)
        ->and(MarketplaceSubscription::where('chapa_tx_ref', $payment->tx_ref)->count())->toBe(1)
        ->and(FreightPost::where('workspace_id', $workspace->id)->whereNotNull('hidden_at')->count())->toBe(0);
});
