<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Generates the NF code a payer types into their transfer note.
 *
 * Deliberately short and deliberately typed on a phone keyboard: someone is
 * entering this into a Telebirr or banking app by hand, so a full ULID would
 * be a support ticket.
 *
 * The alphabet omits I, O, 0 and 1, because these codes get read aloud, copied
 * from screenshots and typed by people who will inevitably mistype an O for a
 * zero. What remains is 32 characters, so 8 of them is 40 bits.
 *
 * The status page is never addressed by this code -- access is by ownership --
 * so guessing one reveals nothing.
 */
class PaymentReferenceGenerator
{
    /**
     * No I, O, 0 or 1.
     */
    public const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public const LENGTH = 8;

    public const PREFIX = 'NF-';

    /**
     * Attempts before giving up. 32^8 is about a trillion, so a collision is
     * effectively impossible; the retry exists because "effectively" is not
     * "never" and tx_ref carries a unique index.
     */
    public const MAX_ATTEMPTS = 8;

    /**
     * A reference nobody is using yet.
     *
     * Checks both the in-memory model and the unique index: the model catches
     * collisions inside one transaction, the index is the real guarantee.
     */
    public function generate(): string
    {
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $candidate = self::PREFIX.$this->randomSegment();

            if (! $this->taken($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException(
            'Could not generate a unique payment reference after '.self::MAX_ATTEMPTS.' attempts.'
        );
    }

    private function randomSegment(): string
    {
        $alphabet = self::ALPHABET;
        $max = strlen($alphabet) - 1;

        return implode('', array_map(
            fn () => $alphabet[random_int(0, $max)],
            range(1, self::LENGTH),
        ));
    }

    /**
     * Is this reference already in use by any payment transaction?
     *
     * A closure parameter keeps the table name in one place.
     */
    public function taken(string $reference, ?string $exceptTransactionId = null): bool
    {
        return DB::table('marketplace_payment_transactions')
            ->where('tx_ref', $reference)
            ->when(
                $exceptTransactionId !== null,
                fn ($query) => $query->where('id', '!=', $exceptTransactionId),
            )
            ->exists();
    }

    /**
     * Normalise a bank or Telebirr transaction reference before storing it.
     *
     * Trimmed and uppercased so "abc1" and "ABC1 " are the same reference. The
     * unique index on (manual_method, manual_reference) is only as good as the
     * normalisation feeding it -- an unnormalised column would let the same
     * transfer be claimed twice with different casing.
     */
    public function normalizeReference(?string $reference): ?string
    {
        if ($reference === null) {
            return null;
        }

        $normalized = Str::upper(trim($reference));

        return $normalized === '' ? null : $normalized;
    }
}
