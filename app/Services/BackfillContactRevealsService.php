<?php

namespace App\Services;

use App\Models\ContactReveal;
use App\Models\FreightInterest;
use App\Models\MarketplaceConnection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the reveal ledger for relationships that already existed.
 *
 * Metering starts counting from the moment the ledger exists. Anyone who could
 * already see a counterparty's contact — because they had accepted an interest,
 * and 4a's accept flow released it — would suddenly find themselves locked out
 * of a number they already had. That is a regression dressed as a feature, so
 * their pair is written into the ledger first.
 *
 * created_at is taken from the relationship's own timestamp, not from now(), so
 * a backfilled row falls outside a window that has already partly elapsed
 * rather than consuming fresh quota.
 */
class BackfillContactRevealsService
{
    /**
     * Count what run() would do, without writing.
     *
     * @return array{connections: int, interests: int, rows_created: int, pairs_skipped: int}
     */
    public function preview(): array
    {
        return $this->walk(dryRun: true);
    }

    /**
     * @return array{
     *     connections: int,
     *     interests: int,
     *     rows_created: int,
     *     pairs_skipped: int
     * }
     */
    public function run(): array
    {
        return $this->walk(dryRun: false);
    }

    /**
     * @return array{connections: int, interests: int, rows_created: int, pairs_skipped: int}
     */
    private function walk(bool $dryRun): array
    {
        $connections = 0;
        $interests = 0;
        $created = 0;
        $skipped = 0;

        // Every connection, in both directions. Both parties could see the
        // other before metering, so both get a row.
        MarketplaceConnection::query()
            ->orderBy('id')
            ->each(function (MarketplaceConnection $connection) use (&$connections, &$created, &$skipped, $dryRun): void {
                $connections++;

                $created += $this->record(
                    (int) $connection->consignor_user_id,
                    (int) $connection->transporter_user_id,
                    (int) $connection->freight_post_id,
                    (int) $connection->freight_interest_id,
                    (int) $connection->id,
                    $connection->created_at,
                    $dryRun,
                );

                $created += $this->record(
                    (int) $connection->transporter_user_id,
                    (int) $connection->consignor_user_id,
                    (int) $connection->freight_post_id,
                    (int) $connection->freight_interest_id,
                    (int) $connection->id,
                    $connection->created_at,
                    $dryRun,
                );
            });

        // Live interests with no connection. Accepted or shortlisted interests
        // showed the contact under the old 4a behaviour; plain submitted ones
        // did not, so they are not backfilled — they were never entitled to it.
        FreightInterest::query()
            ->whereNotIn('status', ['rejected', 'withdrawn'])
            ->whereIn('status', ['shortlisted', 'selected', 'accepted'])
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('marketplace_connections')
                    ->whereColumn('marketplace_connections.freight_interest_id', 'freight_interests.id');
            })
            ->orderBy('id')
            ->each(function (FreightInterest $interest) use (&$interests, &$created, &$skipped, $dryRun): void {
                $postOwnerId = (int) DB::table('freight_posts')
                    ->where('id', $interest->freight_post_id)
                    ->value('user_id');

                if ($postOwnerId === 0 || $postOwnerId === (int) $interest->user_id) {
                    return;
                }

                $interests++;

                $timestamp = $interest->accepted_at ?? $interest->updated_at ?? $interest->created_at;

                $created += $this->record(
                    $postOwnerId,
                    (int) $interest->user_id,
                    (int) $interest->freight_post_id,
                    (int) $interest->id,
                    null,
                    $timestamp,
                    $dryRun,
                );

                $created += $this->record(
                    (int) $interest->user_id,
                    $postOwnerId,
                    (int) $interest->freight_post_id,
                    (int) $interest->id,
                    null,
                    $timestamp,
                    $dryRun,
                );
            });

        return [
            'connections' => $connections,
            'interests' => $interests,
            'rows_created' => $created,
            'pairs_skipped' => $skipped,
        ];
    }

    /**
     * Write one ledger row, tolerating a pair that already exists.
     */
    private function record(
        int $revealerId,
        int $revealedId,
        int $freightPostId,
        ?int $interestId,
        ?int $connectionId,
        mixed $createdAt,
        bool $dryRun = false,
    ): int {
        if ($revealerId === 0 || $revealedId === 0 || $revealerId === $revealedId) {
            return 0;
        }

        $exists = ContactReveal::query()
            ->where('revealer_id', $revealerId)
            ->where('revealed_id', $revealedId)
            ->exists();

        if ($exists) {
            return 0;
        }

        if ($dryRun) {
            return 1;
        }

        $timestamp = $createdAt ? Carbon::parse($createdAt) : now();

        // A query-builder insert rather than ContactReveal::create(), because
        // created_at is not fillable and Eloquent's timestamp trait would
        // overwrite it with now() -- which is precisely the value this row must
        // not have. A backfilled reveal has to land at the moment the
        // relationship was created, or it consumes quota for a window that has
        // already elapsed.
        DB::table('contact_reveals')->insert([
            'revealer_id' => $revealerId,
            'revealed_id' => $revealedId,
            'freight_post_id' => $freightPostId,
            'interest_id' => $interestId,
            'connection_id' => $connectionId,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        return 1;
    }
}
