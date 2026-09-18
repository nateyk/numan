<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_connection_outcomes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('marketplace_connection_id');
            $table->string('outcome');
            $table->text('notes')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('marketplace_connection_id', 'mpc_outcomes_conn_fk')->references('id')->on('marketplace_connections')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_connection_outcomes');
    }
};
