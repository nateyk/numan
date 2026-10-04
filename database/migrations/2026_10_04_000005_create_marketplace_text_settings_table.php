<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-editable text settings, in their own table.
 *
 * marketplace_settings.value is an integer column and its service casts with
 * (int), so an account number or a paragraph of payment instructions cannot go
 * there: "1000234567894" survives the round trip but "Telebirr, account name
 * XCO Agency" becomes 0.
 *
 * A separate table rather than a nullable value_text column beside value,
 * because two nullable value columns and no constraint forbidding both being
 * set is a type error waiting to happen. Two tables make the mistake impossible
 * rather than merely discouraged.
 *
 * Seeded empty on purpose. Nothing here is secret -- these are the account
 * numbers a payer is meant to read off the screen -- and an empty table means
 * manual payment stays hidden until an admin has actually configured it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_text_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('label');
            $table->text('description')->nullable();

            // A method is only offered to payers when it is both enabled and
            // completely filled in, so a half-configured account is never shown.
            $table->boolean('is_enabled')->default(false);

            $table->foreignId('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });

        // Seed the keys, with no values. Not a secret: these are the account
        // details a payer is meant to read before transferring.
        $definitions = [
            'manual_payment_telebirr_number' => 'Telebirr account number',
            'manual_payment_telebirr_name' => 'Telebirr account name',
            'manual_payment_cbe_birr_number' => 'CBE Birr account number',
            'manual_payment_cbe_birr_name' => 'CBE Birr account name',
            'manual_payment_bank_transfer_number' => 'Bank transfer account number',
            'manual_payment_bank_transfer_name' => 'Bank transfer account name',
            'manual_payment_bank_transfer_bank' => 'Bank name',
            'manual_payment_instructions' => 'General payment instructions',
        ];

        $now = now();

        foreach ($definitions as $key => $label) {
            Schema::getConnection()->table('marketplace_text_settings')->insertOrIgnore([
                'key' => $key,
                'value' => null,
                'label' => $label,
                'description' => null,
                'is_enabled' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_text_settings');
    }
};
