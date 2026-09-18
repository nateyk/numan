<?php

namespace App\Console\Commands;

use App\Enums\FreightPostStatus;
use App\Models\FreightPost;
use Carbon\Carbon;
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
                    $pickup = $post->pickup_date instanceof Carbon ? $post->pickup_date : Carbon::parse($post->pickup_date);
                    $flex = (int) ($post->pickup_date_flexibility_days ?? 0);
                    $expiresAt = $pickup->copy()->addDays($flex)->endOfDay();

                    if ($expiresAt->isPast()) {
                        DB::transaction(function () use ($post): void {
                            $post->update(['status' => FreightPostStatus::Expired->value]);
                            $post->moderationLogs()->create([
                                'from_status' => FreightPostStatus::Active->value,
                                'to_status' => FreightPostStatus::Expired->value,
                                'reason' => 'Expired past pickup window',
                                'moderator_id' => null,
                            ]);
                        });
                        $count++;
                    }
                }
            });

        $this->info("Expired {$count} freight posts.");

        return self::SUCCESS;
    }
}
