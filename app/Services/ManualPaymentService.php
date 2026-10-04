<?php

namespace App\Services;

use App\Models\MarketplacePaymentTransaction;
use App\Models\MarketplacePlan;
use App\Models\User;
use App\Notifications\ManualPaymentSubmitted;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creates a manual payment request from a signed instructions token.
 *
 * Everything the payer agreed to comes from the token, never from the plan at
 * submit time. If the admin raised the price between showing the instructions
 * and pressing submit, the row still records the number that was on screen.
 *
 * Two things are enforced by the database rather than by a check here: the
 * duplicate (method, reference) index and the one-open-request-per-workspace
 * index. A read-then-write test passes twice under concurrency; a unique index
 * does not.
 */
class ManualPaymentService
{
    /**
     * Proof file limits. A screenshot is evidence, not an upload channel.
     */
    public const PROOF_MAX_KILOBYTES = 5120;

    /**
     * @var list<string>
     */
    public const PROOF_MIMES = ['jpg', 'jpeg', 'png', 'pdf'];

    public function __construct(
        protected ManualPaymentTokenService $tokens,
        protected ManualPaymentExpiryService $expiry,
        protected PhoneNumberService $phones,
        protected PaymentReferenceGenerator $references,
    ) {}

    /**
     * The review window in days.
     */
    public function expiryDays(): int
    {
        return $this->expiry->windowDays();
    }

    /**
     * @return array<string, array{label: string, fields: array<string, string>}>
     */
    public function availableMethods(): array
    {
        return app(MarketplaceTextSettingsService::class)->availableMethods();
    }

    /**
     * Issue a token for the instructions page.
     *
     * No row is written. The NF code is generated and carried in the token; the
     * unique tx_ref index is what stops two people from ever being issued the
     * same one.
     *
     * @return array{token: string, terms: array<string, mixed>}
     */
    public function issueInstructions(User $user, string $planSlug, string $interval): array
    {
        $plan = MarketplacePlan::where('slug', $planSlug)
            ->where('is_active', true)
            ->first();

        if (! $plan) {
            throw ValidationException::withMessages([
                'plan' => 'That plan is not available.',
            ]);
        }

        if (! in_array($interval, ['weekly', 'monthly', 'yearly'], true)) {
            throw ValidationException::withMessages([
                'billing_interval' => 'Choose weekly, monthly or yearly.',
            ]);
        }

        $amount = match ($interval) {
            'weekly' => $plan->price_weekly_etb,
            'yearly' => $plan->price_yearly_etb,
            default => $plan->price_monthly_etb,
        };

        // The Free state is implicit, so a zero-priced duration must never be
        // "bought" by transfer.
        if ((float) $amount <= 0.0) {
            throw ValidationException::withMessages([
                'plan' => 'This duration has no price. Choose Weekly, Monthly or Yearly.',
            ]);
        }

        // Refuse early and legibly rather than letting the submit fail on a
        // unique index.
        if ($this->openRequestFor($user) !== null) {
            throw ValidationException::withMessages([
                'manual_payment' => 'You already have a payment request awaiting confirmation.',
            ]);
        }

        $terms = [
            'plan' => $plan->slug,
            'plan_id' => $plan->id,
            'billing_interval' => $interval,
            'amount_etb' => (string) $amount,
            'tx_ref' => $this->references->generate(),
        ];

        return [
            'token' => $this->tokens->issue($terms),
            'terms' => $terms,
        ];
    }

    /**
     * The workspace's one open request, if any.
     */
    public function openRequestFor(User $user): ?MarketplacePaymentTransaction
    {
        $workspace = $user->currentWorkspace;

        if ($workspace === null) {
            return null;
        }

        return MarketplacePaymentTransaction::query()
            ->where('open_manual_workspace_id', $workspace->id)
            ->first();
    }

    /**
     * Create the request.
     *
     * @param  array{method: string, reference: string, payer_phone: string, proof?: UploadedFile|null}  $input
     */
    public function submit(User $user, string $token, array $input): MarketplacePaymentTransaction
    {
        $terms = $this->tokens->read($token);

        if ($terms === null) {
            throw ValidationException::withMessages([
                'token' => 'Your payment instructions have expired. Please start again.',
            ]);
        }

        $method = $input['method'];

        // A method that was configured when the page was shown may have been
        // switched off since.
        if (! $this->tokens->methodIsAllowed($method)) {
            throw ValidationException::withMessages([
                'method' => 'Choose one of the available payment methods.',
            ]);
        }

        $reference = $this->references->normalizeReference($input['reference']);

        if ($reference === null) {
            throw ValidationException::withMessages([
                'reference' => 'Enter the transaction reference from your transfer.',
            ]);
        }

        $payerPhone = $this->phones->normalize($input['payer_phone']);

        if ($payerPhone === null) {
            throw ValidationException::withMessages([
                'payer_phone' => PhoneNumberService::INVALID_MESSAGE,
            ]);
        }

        $workspace = $user->currentWorkspace;

        if ($workspace === null) {
            throw ValidationException::withMessages([
                'manual_payment' => 'No active workspace.',
            ]);
        }

        $proofPath = null;

        if (isset($input['proof']) && $input['proof'] instanceof UploadedFile) {
            $proofPath = $this->storeProof($input['proof']);
        }

        try {
            $transaction = DB::transaction(function () use ($user, $workspace, $terms, $method, $reference, $payerPhone, $proofPath): MarketplacePaymentTransaction {
                $transaction = MarketplacePaymentTransaction::create([
                    'workspace_id' => $workspace->id,
                    'plan_id' => $terms['plan_id'],
                    'user_id' => $user->id,
                    // The NF code: what the payer typed into the transfer note.
                    'tx_ref' => $terms['tx_ref'],
                    // The amount as shown, not re-read from the plan.
                    'amount_etb' => $terms['amount_etb'],
                    'currency' => 'ETB',
                    'billing_interval' => $terms['billing_interval'],
                    'status' => MarketplacePaymentTransaction::MANUAL_OPEN_STATUS,
                    'mode' => 'manual',
                    'provider' => MarketplacePaymentTransaction::PROVIDER_MANUAL,
                    'manual_method' => $method,
                    // The bank or Telebirr transaction id: what dedupes.
                    'manual_reference' => $reference,
                    'manual_payer_phone' => $payerPhone,
                    'manual_proof_path' => $proofPath,
                    'manual_submitted_at' => now(),
                    'manual_expires_at' => $this->expiry->expiryFromNow(),
                    // Claims the workspace's single open slot. The unique index
                    // makes this atomic: two concurrent submits cannot both win.
                    'open_manual_workspace_id' => $workspace->id,
                ]);

                return $transaction;
            });
        } catch (UniqueConstraintViolationException $e) {
            // Either the NF code or the workspace slot. Tell the payer which in
            // terms they can act on, and clean up any file we already wrote.
            if ($proofPath !== null) {
                Storage::disk(TransporterDocumentStorage::DISK)->delete($proofPath);
            }

            $open = $this->openRequestFor($user);

            throw ValidationException::withMessages([
                'manual_payment' => $open !== null
                    ? 'You already have a payment request awaiting confirmation.'
                    : 'This payment reference has already been submitted.',
            ]);
        }

        $this->notifyAdmins($transaction);

        return $transaction;
    }

    /**
     * Store a proof file under a random name on the private disk.
     *
     * The name is random and carries no user input, so a filename can never be
     * used to traverse or to infer whose document it is.
     */
    public function storeProof(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $this->extensionFromMime($file->getMimeType()));

        $path = 'manual-payment-proofs/'
            .now()->format('Y/m')
            .'/'.Str::uuid().'.'.$extension;

        Storage::disk(TransporterDocumentStorage::DISK)->put($path, file_get_contents($file->getRealPath()));

        return $path;
    }

    private function extensionFromMime(?string $mime): string
    {
        return match ($mime) {
            'image/png' => 'png',
            'image/jpeg', 'image/jpg' => 'jpg',
            'application/pdf' => 'pdf',
            default => 'bin',
        };
    }

    /**
     * Tell the superadmins there is something to review.
     */
    private function notifyAdmins(MarketplacePaymentTransaction $transaction): void
    {
        $admins = User::query()
            ->where('is_superadmin', true)
            ->whereNotNull('email_verified_at')
            ->get();

        if ($admins->isEmpty()) {
            return;
        }

        Notification::send($admins, new ManualPaymentSubmitted($transaction));
    }
}
