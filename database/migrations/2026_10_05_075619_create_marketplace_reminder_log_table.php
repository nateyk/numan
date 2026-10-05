<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per reminder that has been decided, whether or not it was sent.
 *
 * This table is both the idempotency guard and the audit trail, which is why a
 * row exists for reminders that were deliberately NOT sent. Two kinds:
 *
 *   status = 'sent'     the reminder went out
 *   status = 'skipped'  a less urgent window was already open when a more
 *                       urgent one was reached, so only the most urgent was
 *                       sent. Recording it stops it firing later, hours after
 *                       the point where it would have been useful.
 *
 * Claiming happens before sending, so a row can exist with sent_at still null:
 * that is a reminder whose send has not completed yet, and the command reclaims
 * those after an hour so a queue outage cannot swallow a reminder permanently.
 * That is the whole reason sent_at is nullable rather than the insert simply
 * being the send.
 *
 * The unique key is on (subscription, type, channel) rather than including the
 * status, so a second attempt at the same reminder is a constraint violation and
 * not a duplicate row. attempts exists so a reminder that keeps failing is
 * eventually given up on rather than retried forever.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_reminder_log', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('marketplace_subscription_id')
                ->constrained('marketplace_subscriptions')
                ->cascadeOnDelete();

            $table->foreignId('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            // Not constrained: a notification may outlive the user who was
            // notified, and the row is the record that it was sent.
            $table->unsignedBigInteger('user_id')->nullable();

            $table->string('reminder_type', 32);
            $table->string('channel', 16);

            // 'sent' or 'skipped'. A row with null sent_at is claimed, not yet
            // delivered.
            $table->string('status', 16)->default('claimed');

            $table->timestamp('sent_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);

            $table->timestamps();

            // The idempotency guarantee. Nullable columns are exempt on every
            // database this runs on, so the admin-notification rows that may
            // leave user_id null do not collide.
            $table->unique(
                ['marketplace_subscription_id', 'reminder_type', 'channel'],
                'marketplace_reminder_log_unique_reminder'
            );

            // The reclaim query: rows claimed but not sent, oldest first.
            $table->index(['status', 'sent_at'], 'marketplace_reminder_log_pending');

            $table->index(['reminder_type', 'created_at'], 'marketplace_reminder_log_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_reminder_log');
    }
};
