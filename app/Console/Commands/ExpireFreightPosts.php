<?php

namespace App\Console\Commands;

use App\Enums\FreightPostStatus;
use App\Models\FreightPost;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireFreightPosts extends Command
{
    protected $signature = 'freight:expire';

    protected $description = 'Expire active freight posts past pickup window';

    public function handle(): int
    {
        $count = 0;

        FreightPost::where('status', FreightPostStatus::Active->value)
            ->whereNotNull('pickup_date')
            ->chunkById(100, function ($posts) use (&$count): void {
                foreach ($posts as $post) {
                    $count += DB::transaction(function () use ($post): int {
                        // A transporter may have been selected or an owner may
                        // have closed this post after the chunk was loaded.
                        $current = FreightPost::whereKey($post->id)
                            ->lockForUpdate()->first();

                        if (! $current
                            || $current->status !== FreightPostStatus::Active->value
                            || ! $current->isPastPickupWindow()) {
                            return 0;
                        }

                        $current->update(['status' => FreightPostStatus::Expired->value]);
                        $current->moderationLogs()->create([
                            'from_status' => FreightPostStatus::Active->value,
                            'to_status' => FreightPostStatus::Expired->value,
                            'reason' => 'Expired past pickup window',
                            'moderator_id' => null,
                        ]);

                        return 1;
                    });
                }
            });

        $this->info("Expired {$count} freight posts.");

        return self::SUCCESS;
    }
}
