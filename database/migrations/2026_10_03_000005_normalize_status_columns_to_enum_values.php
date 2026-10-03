<?php

use App\Enums\FreightInterestStatus;
use App\Enums\FreightPostStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Normalize status columns to the lowercase values declared by the enums.
     *
     * Historic rows were written with capitalised literals such as 'Active',
     * while App\Enums\FreightPostStatus::Active->value is 'active'. Queries
     * only matched because MySQL/MariaDB compare case-insensitively; the same
     * code silently matched nothing on Postgres or SQLite.
     *
     * This only rewrites casing to the canonical enum value. Nothing is
     * deleted, and any value that does not map to a known enum case is left
     * untouched and reported.
     */
    public function up(): void
    {
        $this->normalize('freight_posts', 'status', FreightPostStatus::values());
        $this->normalize('freight_interests', 'status', FreightInterestStatus::values());
        $this->normalize('freight_interest_status_histories', 'to_status', FreightInterestStatus::values());
        $this->normalize('freight_interest_status_histories', 'from_status', FreightInterestStatus::values());
    }

    public function down(): void
    {
        // Casing is not restored: the lowercase enum values are canonical.
    }

    /**
     * @param  list<string>  $valid
     */
    private function normalize(string $table, string $column, array $valid): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $before = DB::table($table)->selectRaw("{$column} as v, COUNT(*) as c")
            ->groupBy($column)->pluck('c', 'v')->all();

        $lookup = array_change_key_case(array_combine($valid, $valid), CASE_LOWER);

        foreach ($before as $value => $count) {
            if ($value === null || $value === '') {
                continue;
            }

            $canonical = $lookup[strtolower($value)] ?? null;

            if ($canonical === null) {
                $this->warn("Unknown value '{$value}' ({$count} row(s)) left untouched in {$table}.{$column}.");

                continue;
            }

            if ($canonical === $value) {
                continue;
            }

            DB::table($table)->where($column, $value)->update([$column => $canonical]);

            $this->info("{$table}.{$column}: '{$value}' ({$count}) -> '{$canonical}'");
        }
    }

    private function info(string $message): void
    {
        if (isset($this->command)) {
            $this->command->info($message);
        }
    }

    private function warn(string $message): void
    {
        if (isset($this->command)) {
            $this->command->warn($message);
        }
    }
};
