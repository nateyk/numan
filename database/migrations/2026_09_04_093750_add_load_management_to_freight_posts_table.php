<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('freight_posts', function (Blueprint $table) {
            $table->string('item_name')->nullable()->after('cargo_description');
            $table->decimal('actual_weight', 10, 2)->nullable()->after('item_name');
            $table->string('weight_unit')->default('ton')->after('actual_weight');
            $table->decimal('tariff_amount', 10, 2)->nullable()->after('weight_unit');
            $table->string('tariff_currency')->default('ETB')->after('tariff_amount');
            $table->boolean('insurance_required')->default(false)->after('tariff_currency');
            $table->decimal('insurance_value', 10, 2)->nullable()->after('insurance_required');
            $table->decimal('depreciation_rate', 5, 2)->nullable()->after('insurance_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('freight_posts', function (Blueprint $table) {
            $table->dropColumn(['item_name', 'actual_weight', 'weight_unit', 'tariff_amount', 'tariff_currency', 'insurance_required', 'insurance_value', 'depreciation_rate']);
        });
    }
};
