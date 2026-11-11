<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_reveal_blocks', function (Blueprint $table): void {
            // Existing refusals were contact-quota blocks. Preserve that meaning
            // while distinguishing new post-cap blocks in the audit trail.
            $table->string('reason', 24)->default('contact_limit')->after('limit_at_time');
        });
    }

    public function down(): void
    {
        Schema::table('contact_reveal_blocks', function (Blueprint $table): void {
            $table->dropColumn('reason');
        });
    }
};
