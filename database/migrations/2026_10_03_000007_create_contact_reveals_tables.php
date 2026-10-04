<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The contact-reveal ledger and the record of refused reveals.
 *
 * contact_reveals is the permanent record that one user has seen another's
 * contact details. Its unique (revealer_id, revealed_id) key is what makes a
 * re-reveal free: the row exists, so no quota is consumed and no second row can
 * be written. The key is also what stops two concurrent requests from both
 * passing the quota check.
 *
 * contact_reveal_blocks records attempts refused for quota. It is analytics
 * only — it never grants access — so it carries no quota columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_reveals', function (Blueprint $table): void {
            $table->id();

            // Who looked, and at whom.
            $table->foreignId('revealer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('revealed_id')->constrained('users')->cascadeOnDelete();

            // Context, so an analytics query can attribute a reveal to a post.
            // Nullable because the two are independent: an interest reveal has
            // no connection, a connection reveal may predate a stored interest.
            $table->unsignedBigInteger('freight_post_id')->nullable();
            $table->unsignedBigInteger('interest_id')->nullable();
            $table->unsignedBigInteger('connection_id')->nullable();

            $table->timestamps();

            // The pair is the unit of metering. Once revealed, always free.
            $table->unique(['revealer_id', 'revealed_id'], 'contact_reveals_pair_unique');

            // The quota query filters on this pair plus the window.
            $table->index(['revealer_id', 'created_at'], 'contact_reveals_revealer_window_index');

            $table->foreign('freight_post_id')->references('id')->on('freight_posts')->nullOnDelete();
            $table->foreign('interest_id')->references('id')->on('freight_interests')->nullOnDelete();
            $table->foreign('connection_id')->references('id')->on('marketplace_connections')->nullOnDelete();
        });

        Schema::create('contact_reveal_blocks', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('revealed_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('interest_id')->nullable();
            $table->unsignedBigInteger('connection_id')->nullable();
            $table->unsignedBigInteger('freight_post_id')->nullable();

            // The plan limit at the moment of refusal, so a quota change later
            // does not rewrite history.
            $table->integer('limit_at_time')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'created_at'], 'contact_reveal_blocks_user_window_index');
            $table->index(['created_at'], 'contact_reveal_blocks_created_index');

            $table->foreign('interest_id')->references('id')->on('freight_interests')->nullOnDelete();
            $table->foreign('connection_id')->references('id')->on('marketplace_connections')->nullOnDelete();
            $table->foreign('freight_post_id')->references('id')->on('freight_posts')->nullOnDelete();
        });

        $this->seedRevealWindowSetting();
    }

    /**
     * The rolling window is its own setting, deliberately not interest_window_days.
     *
     * Reusing the interest window would couple two unrelated numbers: changing
     * how long an interest stays open would silently change how often a user
     * can look at a contact.
     */
    private function seedRevealWindowSetting(): void
    {
        $now = now();

        DB::table('marketplace_settings')->insertOrIgnore([
            'key' => 'free_contact_reveal_window_days',
            'value' => (int) config('marketplace.free_contact_reveal_window_days', 7),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('marketplace_settings')->insertOrIgnore([
            'key' => 'free_contact_reveals_per_week',
            'value' => (int) config('marketplace.free_limits.free_contact_reveals_per_week', 3),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_reveal_blocks');
        Schema::dropIfExists('contact_reveals');
    }
};
