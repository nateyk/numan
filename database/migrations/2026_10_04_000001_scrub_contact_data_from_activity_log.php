<?php

use App\Services\ActivityPropertyScrubber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Removes plaintext contact data from the activity log.
 *
 * Profile and TransporterProfile both used logFillable(), which logs the value
 * of every fillable attribute. Both models are fillable with phone, and the
 * transporter profile additionally with whatsapp, email and legal_name, so
 * every profile write copied contact data into activity_log.properties.
 *
 * The audit trail is permanent and exportable, so stopping the writes is not
 * the same as the data not being there. This removes the values from rows that
 * already exist.
 *
 * Rows are kept. The log still records that a profile was updated, by whom and
 * when; only the attribute values are gone. Deleting the rows would destroy the
 * audit history, which is the opposite of what an audit log is for.
 *
 * Idempotent: a second run finds nothing to remove and reports zero.
 */
return new class extends Migration
{
    public function __construct(
        private readonly ?ActivityPropertyScrubber $scrubber = null,
    ) {}

    public function up(): void
    {
        $scrubber = $this->scrubber ?? app(ActivityPropertyScrubber::class);

        $rowsChanged = 0;
        $keysRemoved = 0;

        foreach (ActivityPropertyScrubber::SUBJECT_TYPES as $subjectType) {
            // subject_type is stored as the FQCN, so a suffix match covers both
            // App\Models\Profile and a bare Profile.
            DB::table('activity_log')
                ->where('subject_type', 'like', '%'.$subjectType)
                ->orderBy('id')
                ->each(function ($row) use ($scrubber, &$rowsChanged, &$keysRemoved): void {
                    [$cleaned, $removed] = $scrubber->scrubJson($row->properties ?? null);

                    if ($cleaned === null) {
                        return;
                    }

                    DB::table('activity_log')
                        ->where('id', $row->id)
                        ->update(['properties' => $cleaned]);

                    $rowsChanged++;
                    $keysRemoved += $removed;
                });
        }

        $this->report($rowsChanged, $keysRemoved);
    }

    public function down(): void
    {
        // Not reversible. The removed values were plaintext contact data and
        // there is no source to restore them from, which is exactly why they
        // should not have been written.
        $this->report(0, 0, restored: true);
    }

    private function report(int $rowsChanged, int $keysRemoved, bool $restored = false): void
    {
        if ($restored) {
            Log::notice('Activity log scrub rollback: nothing to restore. The removed values were never stored anywhere else.');

            return;
        }

        Log::notice(
            "Activity log scrub: {$rowsChanged} row(s) updated, {$keysRemoved} sensitive key(s) removed. "
            .'Rows kept; only attribute values were removed, so who changed what and when is intact.'
        );
    }
};
