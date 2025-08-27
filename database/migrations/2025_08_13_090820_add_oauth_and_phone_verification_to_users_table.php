<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // OAuth fields
            $table->string('google_id')->nullable()->unique()->after('email');
            $table->string('provider')->nullable()->after('google_id'); // google, facebook, etc.
            $table->json('provider_data')->nullable()->after('provider'); // Store additional provider data

            // Phone verification fields
            $table->string('phone_country_code', 5)->nullable()->after('phone'); // +95 for Myanmar
            $table->timestamp('phone_verified_at')->nullable()->after('phone_country_code');
            $table->string('phone_verification_code', 10)->nullable()->after('phone_verified_at');
            $table->timestamp('phone_verification_code_expires_at')->nullable()->after('phone_verification_code');
            $table->integer('phone_verification_attempts')->default(0)->after('phone_verification_code_expires_at');
            $table->timestamp('phone_verification_locked_until')->nullable()->after('phone_verification_attempts');

            // Additional security fields
            $table->boolean('two_factor_enabled')->default(false)->after('phone_verification_locked_until');
            $table->string('two_factor_secret')->nullable()->after('two_factor_enabled');
            $table->json('two_factor_recovery_codes')->nullable()->after('two_factor_secret');

            // Indexes for performance
            $table->index(['provider', 'google_id']);
            $table->index(['phone', 'phone_country_code']);
            $table->index('phone_verified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['provider', 'google_id']);
            $table->dropIndex(['phone', 'phone_country_code']);
            $table->dropIndex(['phone_verified_at']);

            $table->dropColumn([
                'google_id',
                'provider',
                'provider_data',
                'phone_country_code',
                'phone_verified_at',
                'phone_verification_code',
                'phone_verification_code_expires_at',
                'phone_verification_attempts',
                'phone_verification_locked_until',
                'two_factor_enabled',
                'two_factor_secret',
                'two_factor_recovery_codes',
            ]);
        });
    }
};
