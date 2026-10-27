<?php

use App\Models\FreightPost;
use App\Models\MarketplacePaymentReceipt;
use App\Models\MarketplacePaymentTransaction;
use App\Models\MarketplacePlan;
use App\Models\MarketplaceSubscription;
use App\Models\Profile;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\ManualPaymentConfirmed;
use App\Notifications\ManualPaymentRejected;
use App\Services\ManualPaymentExpiryService;
use App\Services\ManualPaymentReviewService;
use App\Services\WorkspaceService;
use Database\Seeders\MarketplacePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The admin's decisions on a manual payment request.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MarketplacePlanSeeder::class);
    Notification::fake();
});

/**
 * @return array{0: User, 1: Workspace, 2: User}
 */
function reviewUsers(): array
{
    $payer = User::factory()->create(['email_verified_at' => now(), 'onboarded_at' => now()]);
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($payer);
    $payer->update(['current_workspace_id' => $workspace->id]);
    Profile::firstOrCreate(['user_id' => $payer->id], [
        'phone' => '+251911000111',
    ]);

    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'onboarded_at' => now(),
        'is_superadmin' => true,
    ]);
    app(WorkspaceService::class)->createPersonalWorkspace($admin);

    return [$payer->fresh(), $workspace, $admin->fresh()];
}

/**
 * An open manual request, optionally with a proof file.
 *
 * Returns the payer and workspace it created, because calling reviewUsers()
 * again would create a *different* workspace and every assertion about the
 * subscription would be looking at the wrong one.
 *
 * @return array{0: MarketplacePaymentTransaction, 1: User, 2: Workspace, 3: User}
 */
function reviewFixture(
    string $status = 'pending_confirmation',
    ?UploadedFile $proof = null,
): array {
    [$payer, $workspace, $admin] = reviewUsers();

    $path = null;

    if ($proof !== null) {
        $path = 'manual-payment-proofs/'.now()->format('Y/m').'/'.uniqid().'.png';
        Storage::disk('private')->put($path, 'fake screenshot bytes');
    }

    $transaction = MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => MarketplacePlan::where('slug', 'standard')->first()->id,
        'user_id' => $payer->id,
        'tx_ref' => 'NF-REVIEW'.str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT),
        'amount_etb' => 449,
        'currency' => 'ETB',
        'billing_interval' => 'monthly',
        'status' => $status,
        'mode' => 'manual',
        'provider' => 'manual',
        'manual_method' => 'telebirr',
        'manual_reference' => 'FT'.random_int(100000, 999999),
        'manual_payer_phone' => '+251911223344',
        'manual_proof_path' => $path,
        'manual_submitted_at' => now()->subDay(),
        'manual_expires_at' => now()->addDays(13),
        'open_manual_workspace_id' => $status === 'pending_confirmation' ? $workspace->id : null,
    ]);

    return [$transaction, $payer, $workspace, $admin];
}

// ── the queue ─────────────────────────────────────────────────────────────

it('lists pending requests with everything needed to decide', function () {
    [$transaction, , , $admin] = reviewFixture();

    $this->actingAs($admin)
        ->get(route('admin.marketplace.manual.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/marketplace-manual-payments')
            ->where('counts.pending', fn (int $n) => $n >= 1)
            ->where('pending', fn ($rows) => collect($rows)->contains(
                fn (array $row) => $row['id'] === $transaction->id
                    && $row['amount_etb'] === '449.00'
                    && $row['method'] === 'telebirr'
                    && $row['reference'] === $transaction->manual_reference
                    && $row['payer_phone'] === '+251911223344'
                    && $row['submitted_at'] !== null
            ))
        );
});

it('the queue row states the amount, method, reference, payer phone and date', function () {
    [$transaction] = reviewFixture();
    $row = app(ManualPaymentReviewService::class)->present($transaction);

    expect($row['amount_etb'])->toBe('449.00');
    expect($row['currency'])->toBe('ETB');
    expect($row['method'])->toBe('telebirr');
    expect($row['reference'])->toBe($transaction->manual_reference);
    expect($row['payer_phone'])->toBe('+251911223344');
    expect($row['submitted_at'])->not->toBeNull();
    expect($row['workspace']['name'])->not->toBeEmpty();
    expect($row['user']['name'])->not->toBeEmpty();
    expect($row['can_confirm'])->toBeTrue();
    expect($row['can_reject'])->toBeTrue();
});

it('never puts the proof path in the queue payload', function () {
    [$transaction] = reviewFixture(proof: UploadedFile::fake()->image('shot.png'));
    $row = app(ManualPaymentReviewService::class)->present($transaction);

    // A URL to an authorised route, never a disk path.
    expect($row['has_proof'])->toBeTrue();
    expect($row['proof_url'])->toBe(route('admin.marketplace.manual.proof', ['transaction' => $transaction->id]));
    expect($row)->not->toHaveKey('manual_proof_path');
});

it('refuses the queue to a non-superadmin', function () {
    [$transaction, $payer] = reviewFixture();

    $this->actingAs($payer)
        ->get(route('admin.marketplace.manual.index'))
        ->assertForbidden();
});

it('refuses the queue to a guest', function () {
    reviewFixture();

    $this->get(route('admin.marketplace.manual.index'))->assertRedirect(route('login'));
});

// ── D + confirm ───────────────────────────────────────────────────────────

it('confirm grants the plan through the shared fulfilment path', function () {
    [$transaction, $payer, $workspace, $admin] = reviewFixture();

    $this->actingAs($admin)
        ->post(route('admin.marketplace.manual.confirm', ['transaction' => $transaction->id]))
        ->assertRedirect()
        ->assertSessionHas('success');

    $subscription = MarketplaceSubscription::where('workspace_id', $workspace->id)->first();

    expect($subscription)->not->toBeNull();
    expect($subscription->status)->toBe('active');
    expect($subscription->billing_interval)->toBe('monthly');
    expect($subscription->ends_at)->not->toBeNull();
    expect($subscription->ends_at->toDateTimeString())
        ->toBe($subscription->current_period_ends_at->toDateTimeString());

    // Same single path as Chapa: the row is verified and a receipt exists.
    expect($transaction->fresh()->status)->toBe(MarketplacePaymentTransaction::MANUAL_CONFIRMED);
    expect($transaction->fresh()->verified_at)->not->toBeNull();
    expect(MarketplacePaymentReceipt::where('transaction_id', $transaction->id)->count())->toBe(1);
});

it('confirm records the reviewer and the time', function () {
    [$transaction, , , $admin] = reviewFixture();

    $this->actingAs($admin)
        ->post(route('admin.marketplace.manual.confirm', ['transaction' => $transaction->id]));

    $fresh = $transaction->fresh();

    expect($fresh->manual_reviewed_by)->toBe($admin->id);
    expect($fresh->manual_reviewed_at)->not->toBeNull();
    expect($fresh->reviewer)->not->toBeNull();
});

it('confirm frees the open slot', function () {
    [$transaction, , , $admin] = reviewFixture();

    expect($transaction->open_manual_workspace_id)->not->toBeNull();

    $this->actingAs($admin)
        ->post(route('admin.marketplace.manual.confirm', ['transaction' => $transaction->id]));

    expect($transaction->fresh()->open_manual_workspace_id)->toBeNull();
});

it('confirm is idempotent against a double-click', function () {
    [$transaction, $payer, $workspace, $admin] = reviewFixture();

    $this->actingAs($admin)
        ->post(route('admin.marketplace.manual.confirm', ['transaction' => $transaction->id]))
        ->assertSessionHas('success');

    $this->actingAs($admin)
        ->post(route('admin.marketplace.manual.confirm', ['transaction' => $transaction->id]))
        ->assertSessionHas('info');

    // One subscription, one receipt. Not two.
    expect(MarketplaceSubscription::where('workspace_id', $workspace->id)->count())->toBe(1);
    expect(MarketplacePaymentReceipt::where('transaction_id', $transaction->id)->count())->toBe(1);
});

it('confirm not three times still grants one', function () {
    [$transaction, , $workspace, $admin] = reviewFixture();

    for ($i = 0; $i < 3; $i++) {
        $this->actingAs($admin)
            ->post(route('admin.marketplace.manual.confirm', ['transaction' => $transaction->id]));
    }

    expect(MarketplaceSubscription::where('workspace_id', $workspace->id)->count())->toBe(1);
});

it('confirm notifies the payer', function () {
    [$transaction, $payer, , $admin] = reviewFixture();

    $this->actingAs($admin)
        ->post(route('admin.marketplace.manual.confirm', ['transaction' => $transaction->id]));

    Notification::assertSentTo($payer, ManualPaymentConfirmed::class);
});

it('confirm honours the snapshotted plan, interval and amount', function () {
    [$transaction, , , $admin] = reviewFixture();

    // Price and plan change after submission.
    MarketplacePlan::where('slug', 'standard')->update([
        'price_monthly_etb' => 9999,
        'name' => 'Standard Renamed',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.marketplace.manual.confirm', ['transaction' => $transaction->id]));

    $subscription = MarketplaceSubscription::where('chapa_tx_ref', $transaction->tx_ref)->first();

    expect($subscription->plan_id)->toBe($transaction->plan_id);
    expect($subscription->billing_interval)->toBe('monthly');

    // And the receipt records the agreed amount, not the new price.
    expect((float) MarketplacePaymentReceipt::where('transaction_id', $transaction->id)->value('amount_etb'))
        ->toBe(449.0);
});

// ── 2: an archived plan is still honoured, with a warning ────────────────

it('confirms a request whose plan was archived after submission', function () {
    [$transaction, , $workspace, $admin] = reviewFixture();

    MarketplacePlan::where('slug', 'standard')->update(['is_active' => false]);

    expect($transaction->fresh()->planIsArchived())->toBeTrue();

    $this->actingAs($admin)
        ->post(route('admin.marketplace.manual.confirm', ['transaction' => $transaction->id]))
        ->assertSessionHas('success');

    expect(MarketplaceSubscription::where('workspace_id', $workspace->id)->count())->toBe(1);
});

it('flags an archived plan on the queue row', function () {
    [$transaction] = reviewFixture();
    MarketplacePlan::where('slug', 'standard')->update(['is_active' => false]);

    $row = app(ManualPaymentReviewService::class)->present($transaction->fresh());

    expect($row['plan_archived'])->toBeTrue();
});

it('does not flag an archived plan when it is active', function () {
    [$transaction] = reviewFixture();

    expect(app(ManualPaymentReviewService::class)->present($transaction)['plan_archived'])->toBeFalse();
});

// ── F: an admin cannot confirm their own payment ──────────────────────────

it('refuses an admin confirming their own payment', function () {
    // The admin is also the payer.
    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'onboarded_at' => now(),
        'is_superadmin' => true,
    ]);
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($admin);
    $admin->update(['current_workspace_id' => $workspace->id]);

    $transaction = MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => MarketplacePlan::where('slug', 'standard')->first()->id,
        'user_id' => $admin->id,
        'tx_ref' => 'NF-SELFPAY',
        'amount_etb' => 449,
        'currency' => 'ETB',
        'billing_interval' => 'monthly',
        'status' => 'pending_confirmation',
        'mode' => 'manual',
        'provider' => 'manual',
        'manual_method' => 'telebirr',
        'manual_reference' => 'FT-SELF',
        'manual_expires_at' => now()->addDays(13),
        'open_manual_workspace_id' => $workspace->id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.marketplace.manual.confirm', ['transaction' => $transaction->id]))
        ->assertSessionHasErrors('manual_payment');

    // Nothing granted.
    expect(MarketplaceSubscription::where('workspace_id', $workspace->id)->count())->toBe(0);
    expect($transaction->fresh()->status)->toBe('pending_confirmation');
});

it('marks the queue row unconfirmable when the admin is the payer', function () {
    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'onboarded_at' => now(),
        'is_superadmin' => true,
    ]);
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($admin);

    $transaction = MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => MarketplacePlan::where('slug', 'standard')->first()->id,
        'user_id' => $admin->id,
        'tx_ref' => 'NF-SELFPAY2',
        'amount_etb' => 449,
        'currency' => 'ETB',
        'billing_interval' => 'monthly',
        'status' => 'pending_confirmation',
        'mode' => 'manual',
        'provider' => 'manual',
        'manual_method' => 'telebirr',
        'manual_reference' => 'FT-SELF2',
        'manual_expires_at' => now()->addDays(13),
        'open_manual_workspace_id' => $workspace->id,
    ]);

    $this->actingAs($admin);

    $row = app(ManualPaymentReviewService::class)->present($transaction);

    expect($row['can_confirm'])->toBeFalse();
    expect($row['can_reject'])->toBeTrue();
});

// ── D + reject ────────────────────────────────────────────────────────────

it('reject requires a reason', function () {
    [$transaction, , , $admin] = reviewFixture();

    $this->actingAs($admin)
        ->post(route('admin.marketplace.manual.reject', ['transaction' => $transaction->id]), [])
        ->assertSessionHasErrors('manual_rejection_reason');

    expect($transaction->fresh()->status)->toBe('pending_confirmation');
});

it('reject requires a reason long enough to be useful', function () {
    [$transaction, , , $admin] = reviewFixture();

    $this->actingAs($admin)
        ->post(route('admin.marketplace.manual.reject', ['transaction' => $transaction->id]), [
            'manual_rejection_reason' => 'no',
        ])
        ->assertSessionHasErrors('manual_rejection_reason');
});

it('reject grants nothing and records the reason', function () {
    [$transaction, , $workspace, $admin] = reviewFixture();

    $this->actingAs($admin)
        ->post(route('admin.marketplace.manual.reject', ['transaction' => $transaction->id]), [
            'manual_rejection_reason' => 'No matching transfer found for that reference.',
        ])
        ->assertSessionHas('success');

    $fresh = $transaction->fresh();

    expect($fresh->status)->toBe(MarketplacePaymentTransaction::MANUAL_REJECTED);
    expect($fresh->manual_rejection_reason)->toBe('No matching transfer found for that reference.');
    expect($fresh->open_manual_workspace_id)->toBeNull();
    expect($fresh->manual_reviewed_by)->toBe($admin->id);
    expect($fresh->manual_reviewed_at)->not->toBeNull();

    expect(MarketplaceSubscription::where('workspace_id', $workspace->id)->count())->toBe(0);
    expect(MarketplacePaymentReceipt::count())->toBe(0);
});

it('reject notifies the payer with the reason', function () {
    [$transaction, $payer, , $admin] = reviewFixture();

    $this->actingAs($admin)
        ->post(route('admin.marketplace.manual.reject', ['transaction' => $transaction->id]), [
            'manual_rejection_reason' => 'Amount did not match.',
        ]);

    Notification::assertSentTo($payer, ManualPaymentRejected::class, function (ManualPaymentRejected $n) {
        return $n->transaction->manual_rejection_reason === 'Amount did not match.';
    });
});

it('reject deletes the proof file', function () {
    [$transaction, , , $admin] = reviewFixture(proof: UploadedFile::fake()->image('shot.png'));

    expect(Storage::disk('private')->exists($transaction->manual_proof_path))->toBeTrue();

    $this->actingAs($admin)
        ->post(route('admin.marketplace.manual.reject', ['transaction' => $transaction->id]), [
            'manual_rejection_reason' => 'Nothing to confirm here.',
        ]);

    expect(Storage::disk('private')->exists($transaction->fresh()->manual_proof_path))->toBeFalse();
});

// ── D: every transition requires an open row ──────────────────────────────

it('will not confirm a rejected row', function () {
    [$transaction, , $workspace, $admin] = reviewFixture('rejected');

    $this->actingAs($admin)
        ->post(route('admin.marketplace.manual.confirm', ['transaction' => $transaction->id]))
        ->assertSessionHasErrors('manual_payment');

    expect(MarketplaceSubscription::where('workspace_id', $workspace->id)->count())->toBe(0);
    expect($transaction->fresh()->status)->toBe('rejected');
});

it('will not confirm an expired row', function () {
    [$transaction, , $workspace, $admin] = reviewFixture('expired');

    $this->actingAs($admin)
        ->post(route('admin.marketplace.manual.confirm', ['transaction' => $transaction->id]))
        ->assertSessionHasErrors('manual_payment');

    expect(MarketplaceSubscription::where('workspace_id', $workspace->id)->count())->toBe(0);
});

it('will not reject a confirmed row', function () {
    [$transaction, , , $admin] = reviewFixture();

    $this->actingAs($admin)
        ->post(route('admin.marketplace.manual.confirm', ['transaction' => $transaction->id]));

    $this->actingAs($admin)
        ->post(route('admin.marketplace.manual.reject', ['transaction' => $transaction->id]), [
            'manual_rejection_reason' => 'Changed my mind about this one.',
        ])
        ->assertSessionHasErrors('manual_payment');

    expect($transaction->fresh()->status)->toBe(MarketplacePaymentTransaction::MANUAL_CONFIRMED);
});

it('will not confirm or reject twice', function () {
    [$transaction, , , $admin] = reviewFixture();

    $this->actingAs($admin)
        ->post(route('admin.marketplace.manual.reject', ['transaction' => $transaction->id]), [
            'manual_rejection_reason' => 'No matching transfer found.',
        ]);

    $this->actingAs($admin)
        ->post(route('admin.marketplace.manual.reject', ['transaction' => $transaction->id]), [
            'manual_rejection_reason' => 'Trying again with a different reason.',
        ])
        ->assertSessionHas('info');

    // The original reason stands.
    expect($transaction->fresh()->manual_rejection_reason)->toBe('No matching transfer found.');
});

it('confirm racing expire resolves to exactly one outcome', function () {
    // Sequential on purpose: the suite is on SQLite, where lockForUpdate is a
    // no-op, so this cannot test real concurrency. What it proves is that each
    // transition requires an open row, so whichever runs first closes it and the
    // second finds nothing to do.
    [$transaction, , $workspace, $admin] = reviewFixture();

    // Expiry first.
    $transaction->update([
        'manual_expires_at' => now()->subDay(),
        'status' => MarketplacePaymentTransaction::MANUAL_OPEN_STATUS,
    ]);

    expect(app(ManualPaymentExpiryService::class)->expire($transaction->fresh()))->toBeTrue();
    expect($transaction->fresh()->status)->toBe('expired');

    $this->actingAs($admin)
        ->post(route('admin.marketplace.manual.confirm', ['transaction' => $transaction->id]))
        ->assertSessionHasErrors('manual_payment');

    expect(MarketplaceSubscription::where('workspace_id', $workspace->id)->count())->toBe(0);
});

it('confirm racing reject resolves to exactly one outcome', function () {
    [$transaction, , $workspace, $admin] = reviewFixture();

    $this->actingAs($admin)
        ->post(route('admin.marketplace.manual.reject', ['transaction' => $transaction->id]), [
            'manual_rejection_reason' => 'No matching transfer found.',
        ]);

    $this->actingAs($admin)
        ->post(route('admin.marketplace.manual.confirm', ['transaction' => $transaction->id]))
        ->assertSessionHasErrors('manual_payment');

    expect(MarketplaceSubscription::where('workspace_id', $workspace->id)->count())->toBe(0);
    expect($transaction->fresh()->status)->toBe('rejected');
});

// ── D: reopen ─────────────────────────────────────────────────────────────

it('reopens an expired row with a fresh window', function () {
    [$transaction, , , $admin] = reviewFixture('expired');
    $transaction->update(['manual_expires_at' => now()->subDays(3)]);

    $this->actingAs($admin)
        ->post(route('admin.marketplace.manual.reopen', ['transaction' => $transaction->id]))
        ->assertSessionHas('success');

    $fresh = $transaction->fresh();

    expect($fresh->status)->toBe(MarketplacePaymentTransaction::MANUAL_OPEN_STATUS);
    expect($fresh->open_manual_workspace_id)->toBe($fresh->workspace_id);
    expect($fresh->manual_expires_at->isFuture())->toBeTrue();
});

it('reopening grants nothing on its own', function () {
    [$transaction, , $workspace, $admin] = reviewFixture('expired');

    $this->actingAs($admin)
        ->post(route('admin.marketplace.manual.reopen', ['transaction' => $transaction->id]));

    // Back in the queue, still unconfirmed.
    expect(MarketplaceSubscription::where('workspace_id', $workspace->id)->count())->toBe(0);
    expect($transaction->fresh()->status)->toBe(MarketplacePaymentTransaction::MANUAL_OPEN_STATUS);
});

it('will not reopen a rejected row', function () {
    [$transaction, , , $admin] = reviewFixture('rejected');

    $this->actingAs($admin)
        ->post(route('admin.marketplace.manual.reopen', ['transaction' => $transaction->id]))
        ->assertSessionHasErrors('manual_payment');

    expect($transaction->fresh()->status)->toBe('rejected');
});

it('will not reopen a confirmed row', function () {
    [$transaction, , , $admin] = reviewFixture();

    $this->actingAs($admin)
        ->post(route('admin.marketplace.manual.confirm', ['transaction' => $transaction->id]));

    $this->actingAs($admin)
        ->post(route('admin.marketplace.manual.reopen', ['transaction' => $transaction->id]))
        ->assertSessionHasErrors('manual_payment');
});

it('will not reopen when the workspace already has an open request', function () {
    [$expired, $payer, $workspace, $admin] = reviewFixture(MarketplacePaymentTransaction::MANUAL_EXPIRED);

    // A second request from the same workspace, already open.
    MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => MarketplacePlan::where('slug', 'standard')->first()->id,
        'user_id' => $payer->id,
        'tx_ref' => 'NF-OTHEROPEN',
        'amount_etb' => 449,
        'currency' => 'ETB',
        'billing_interval' => 'monthly',
        'status' => 'pending_confirmation',
        'mode' => 'manual',
        'provider' => 'manual',
        'manual_method' => 'telebirr',
        'manual_reference' => 'FT-OTHER',
        'manual_expires_at' => now()->addDays(13),
        'open_manual_workspace_id' => $workspace->id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.marketplace.manual.reopen', ['transaction' => $expired->id]))
        ->assertSessionHasErrors('manual_payment');

    expect($expired->fresh()->status)->toBe(MarketplacePaymentTransaction::MANUAL_EXPIRED);
});

// ── G: the proof file ─────────────────────────────────────────────────────

it('serves a proof image inline with nosniff', function () {
    [$transaction, , , $admin] = reviewFixture(proof: UploadedFile::fake()->image('shot.png'));

    $response = $this->actingAs($admin)
        ->get(route('admin.marketplace.manual.proof', ['transaction' => $transaction->id]));

    $response->assertOk();

    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
    expect($response->headers->get('Content-Disposition'))->toContain('inline');
    expect($response->headers->get('Content-Type'))->toBe('image/png');
});

it('serves a PDF as an attachment, not inline', function () {
    [$transaction] = reviewFixture();
    $path = 'manual-payment-proofs/'.now()->format('Y/m').'/'.uniqid().'.pdf';
    Storage::disk('private')->put($path, '%PDF-1.4 fake');
    $transaction->update(['manual_proof_path' => $path]);

    [, , $admin] = reviewUsers();

    $response = $this->actingAs($admin)
        ->get(route('admin.marketplace.manual.proof', ['transaction' => $transaction->id]));

    $response->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('attachment');
    expect($response->headers->get('Content-Disposition'))->not->toContain('inline');
    // Never served as a real PDF inline: the content type is forced opaque.
    expect($response->headers->get('Content-Type'))->toBe('application/octet-stream');
});

it('always sets nosniff on a proof', function () {
    [$transaction, , , $admin] = reviewFixture(proof: UploadedFile::fake()->image('shot.png'));

    // A file named to look like HTML must still be served as an image and never
    // sniffed as markup.
    Storage::disk('private')->put($transaction->manual_proof_path, '<script>alert(1)</script>');

    $response = $this->actingAs($admin)
        ->get(route('admin.marketplace.manual.proof', ['transaction' => $transaction->id]));

    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
    expect($response->headers->get('Content-Type'))->toBe('image/png');
});

it('refuses the proof to a non-superadmin', function () {
    [$transaction, $payer] = reviewFixture(proof: UploadedFile::fake()->image('shot.png'));

    $this->actingAs($payer)
        ->get(route('admin.marketplace.manual.proof', ['transaction' => $transaction->id]))
        ->assertForbidden();
});

it('refuses the proof to a guest', function () {
    [$transaction] = reviewFixture(proof: UploadedFile::fake()->image('shot.png'));

    $this->get(route('admin.marketplace.manual.proof', ['transaction' => $transaction->id]))
        ->assertRedirect(route('login'));
});

it('404s a request with no proof', function () {
    [$transaction, , , $admin] = reviewFixture();

    $this->actingAs($admin)
        ->get(route('admin.marketplace.manual.proof', ['transaction' => $transaction->id]))
        ->assertNotFound();
});

it('reports a proof whose file has gone missing', function () {
    [$transaction, , , $admin] = reviewFixture(proof: UploadedFile::fake()->image('shot.png'));

    Storage::disk('private')->delete($transaction->manual_proof_path);

    $this->actingAs($admin)
        ->from(route('admin.marketplace.manual.index'))
        ->get(route('admin.marketplace.manual.proof', ['transaction' => $transaction->id]))
        ->assertRedirect(route('admin.marketplace.manual.index'))
        ->assertSessionHasErrors('proof');
});

// ── the count for the badge ───────────────────────────────────────────────

it('counts only open manual requests', function () {
    reviewFixture('pending_confirmation');
    reviewFixture('pending_confirmation');
    reviewFixture(MarketplacePaymentTransaction::MANUAL_CONFIRMED);
    reviewFixture('rejected');
    reviewFixture('expired');

    expect(app(ManualPaymentReviewService::class)->pendingCount())->toBe(2);
});

it('ignores Chapa transactions in the count', function () {
    [, $payer, $workspace] = reviewFixture(MarketplacePaymentTransaction::MANUAL_EXPIRED);

    MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => MarketplacePlan::where('slug', 'standard')->first()->id,
        'user_id' => $payer->id,
        'tx_ref' => 'NF-CHAPA-PENDING',
        'amount_etb' => 149,
        'currency' => 'ETB',
        'billing_interval' => 'weekly',
        'status' => 'pending',
        'mode' => 'test',
        'provider' => 'chapa',
    ]);

    expect(app(ManualPaymentReviewService::class)->pendingCount())->toBe(0);
});

// ── renewal extends, exactly as Chapa does ────────────────────────────────

it('a confirmed renewal extends rather than overwrites', function () {
    [$transaction, $payer, $workspace, $admin] = reviewFixture();

    // An existing paid subscription with time left on it.
    $existing = MarketplaceSubscription::create([
        'workspace_id' => $workspace->id,
        'plan_id' => MarketplacePlan::where('slug', 'standard')->first()->id,
        'user_id' => $payer->id,
        'status' => 'active',
        'billing_interval' => 'monthly',
        'current_period_start' => now()->subDays(10),
        'current_period_ends_at' => now()->addDays(20),
        'ends_at' => now()->addDays(20),
        'chapa_tx_ref' => 'NF-PRIOR',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.marketplace.manual.confirm', ['transaction' => $transaction->id]));

    $new = MarketplaceSubscription::where('chapa_tx_ref', $transaction->tx_ref)->first();

    expect($new)->not->toBeNull();
    // Starts where the old one ended, so no paid days are lost.
    expect($new->current_period_start->toDateString())->toBe($existing->ends_at->toDateString());
    expect($new->ends_at->toDateString())->toBe($existing->ends_at->copy()->addDays(30)->toDateString());

    // The prior row keeps its end date; only the status changes.
    expect($existing->fresh()->status)->toBe('superseded');
    expect($existing->fresh()->ends_at->toDateString())->toBe($existing->ends_at->toDateString());
});

it('confirm restores hidden listings', function () {
    [$transaction, $payer, $workspace, $admin] = reviewFixture();

    $post = FreightPost::create([
        'ulid' => (string) Str::ulid(),
        'user_id' => $payer->id,
        'workspace_id' => $workspace->id,
        'status' => 'active',
        'cargo_description' => 'hidden then restored',
        'hidden_at' => now()->subDay(),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.marketplace.manual.confirm', ['transaction' => $transaction->id]));

    expect($post->fresh()->hidden_at)->toBeNull();
});

it('records the provider on the granted subscription', function () {
    // The column keeps its historical name, but a manual grant must not be
    // labelled Chapa or the revenue split by provider is wrong.
    [$transaction, , , $admin] = reviewFixture();

    $this->actingAs($admin)
        ->post(route('admin.marketplace.manual.confirm', ['transaction' => $transaction->id]));

    $subscription = MarketplaceSubscription::where('chapa_tx_ref', $transaction->tx_ref)->first();

    expect($subscription->provider)->toBe('manual');
});
