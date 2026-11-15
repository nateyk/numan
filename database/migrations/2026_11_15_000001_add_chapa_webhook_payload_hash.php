<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chapa_webhook_events', function (Blueprint $table): void {
            // Fingerprints new signed deliveries before the personal fields
            // are discarded; historical hashes cannot be reconstructed.
            $table->char('payload_hash', 64)->nullable()->after('signature');
        });
    }

    public function down(): void
    {
        Schema::table('chapa_webhook_events', function (Blueprint $table): void {
            $table->dropColumn('payload_hash');
        });
    }
};
