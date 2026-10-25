<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Marketing copy for the seeded Standard plan now describes the board.
 *
 * Plan limits still include interests for flag-on installs; this migration only
 * touches the exact defaults the seeder wrote. An admin who has rewritten the
 * description or features keeps their wording.
 */
return new class extends Migration
{
    private const OLD_DESCRIPTION = 'Full access — unlimited posts, interests and contact reveals';

    private const NEW_DESCRIPTION = 'Full access — unlimited posts and contact reveals';

    private const OLD_FEATURES = [
        'Unlimited active freight posts',
        'Unlimited interests',
        'Unlimited contact reveals',
    ];

    private const NEW_FEATURES = [
        'Unlimited active freight posts',
        'Unlimited contact reveals',
    ];

    public function up(): void
    {
        $this->rewrite(self::OLD_DESCRIPTION, self::OLD_FEATURES, self::NEW_DESCRIPTION, self::NEW_FEATURES);
    }

    public function down(): void
    {
        $this->rewrite(self::NEW_DESCRIPTION, self::NEW_FEATURES, self::OLD_DESCRIPTION, self::OLD_FEATURES);
    }

    /**
     * @param  list<string>  $fromFeatures
     * @param  list<string>  $toFeatures
     */
    private function rewrite(string $fromDescription, array $fromFeatures, string $toDescription, array $toFeatures): void
    {
        $plan = DB::table('marketplace_plans')->where('slug', 'standard')->first();

        if ($plan === null) {
            return;
        }

        $updates = [];

        if ($plan->description === $fromDescription) {
            $updates['description'] = $toDescription;
        }

        if (json_decode((string) $plan->features, true) === $fromFeatures) {
            $updates['features'] = json_encode($toFeatures);
        }

        if ($updates !== []) {
            DB::table('marketplace_plans')->where('id', $plan->id)->update($updates);
        }
    }
};
