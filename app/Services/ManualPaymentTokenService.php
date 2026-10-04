<?php

namespace App\Services;

use App\Models\MarketplacePaymentTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

/**
 * The signed token that carries a manual payment's agreed terms.
 *
 * No row exists until the payer submits. The instructions page therefore has to
 * carry the agreed terms somewhere, and that somewhere must not be forgeable --
 * otherwise a payer could edit the amount in the token down to one birr and pay
 * that.
 *
 * The token holds the plan, the interval, the amount as shown, and the NF code.
 * It is encrypted and signed, so a tampered payload fails to decrypt rather than
 * decoding into something unexpected.
 *
 * Expiry is belt and braces: the encrypted blob carries a timestamp that
 * Crypt::decrypt() enforces, and manual_expires_at on the row is a separate
 * setting with a different, longer window. The 24 hours here is about not
 * letting an old instructions page sit around for months.
 */
class ManualPaymentTokenService
{
    /**
     * How long an instructions page stays valid before submission.
     */
    public const TTL_HOURS = 24;

    /**
     * Build the token for an instructions page.
     *
     * Carries the user and workspace it was issued to. Without that, a token is
     * a bearer credential: it appears in a browser history, in a shared link and
     * in a screenshot of the page, and anyone holding it could submit on
     * somebody else's behalf. The amount would be right but the workspace
     * wrong.
     *
     * @param  array{plan: string, plan_id: int, billing_interval: string, amount_etb: string, tx_ref: string}  $terms
     */
    public function issue(array $terms, int $userId, int $workspaceId): string
    {
        return Crypt::encryptString(json_encode([
            'plan' => $terms['plan'],
            'plan_id' => $terms['plan_id'],
            'billing_interval' => $terms['billing_interval'],
            // The amount as shown on screen. This is what gets frozen, and it
            // is deliberately NOT re-read from the plan at submit time: the
            // payer agreed to this number.
            'amount_etb' => (string) $terms['amount_etb'],
            'tx_ref' => $terms['tx_ref'],
            // Bound to the issuer. Read() takes these and refuses a mismatch.
            'user_id' => $userId,
            'workspace_id' => $workspaceId,
            'issued_at' => now()->toIso8601String(),
            'expires_at' => now()->addHours(self::TTL_HOURS)->toIso8601String(),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * Read a token back, or null when it is invalid, tampered with or expired.
     *
     * Every failure mode returns null rather than throwing, because they are all
     * the same thing to a payer: your instructions page went stale, look again.
     *
     * The issuer is required, so a token cannot be replayed by another user or
     * against another workspace. Pass null to inspect a token without binding.
     *
     * @return array{plan: string, plan_id: int, billing_interval: string, amount_etb: string, tx_ref: string, user_id: int, workspace_id: int}|null
     */
    public function read(?string $token, ?int $userId = null, ?int $workspaceId = null): ?array
    {
        if (blank($token)) {
            return null;
        }

        try {
            $decoded = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            // Invalid MAC, wrong key, malformed ciphertext, or past its TTL.
            return null;
        }

        if (! is_array($decoded)) {
            return null;
        }

        foreach (['plan', 'plan_id', 'billing_interval', 'amount_etb', 'tx_ref', 'user_id', 'workspace_id'] as $key) {
            if (! array_key_exists($key, $decoded)) {
                return null;
            }
        }

        // Binding. A token issued to one user must not submit against another,
        // even though the amounts and terms are perfectly valid.
        if ($userId !== null && (int) $decoded['user_id'] !== $userId) {
            return null;
        }

        if ($workspaceId !== null && (int) $decoded['workspace_id'] !== $workspaceId) {
            return null;
        }

        if (! in_array($decoded['billing_interval'], ['weekly', 'monthly', 'yearly'], true)) {
            return null;
        }

        // Expiry, enforced here because the encrypter does not.
        try {
            if (isset($decoded['expires_at'])
                && Carbon::parse($decoded['expires_at'])->isPast()) {
                return null;
            }
        } catch (\Throwable) {
            // An unparseable expiry is not a token worth trusting.
            return null;
        }

        // The NF code must still be unclaimed. A token issued for a reference
        // that a row now holds is stale, whatever its timestamp says.
        if (app(PaymentReferenceGenerator::class)->taken($decoded['tx_ref'])) {
            return null;
        }

        return [
            'plan' => (string) $decoded['plan'],
            'plan_id' => (int) $decoded['plan_id'],
            'billing_interval' => (string) $decoded['billing_interval'],
            'amount_etb' => (string) $decoded['amount_etb'],
            'tx_ref' => (string) $decoded['tx_ref'],
            'user_id' => (int) $decoded['user_id'],
            'workspace_id' => (int) $decoded['workspace_id'],
        ];
    }

    /**
     * Is this a method a payer may choose?
     */
    public function methodIsAllowed(?string $method): bool
    {
        if ($method === null || ! in_array($method, MarketplacePaymentTransaction::MANUAL_METHODS, true)) {
            return false;
        }

        return app(MarketplaceTextSettingsService::class)->methodIsAvailable($method);
    }
}
