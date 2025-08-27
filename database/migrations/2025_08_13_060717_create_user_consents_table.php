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
        Schema::create('user_consents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('consent_type', 100); // privacy_policy, terms_of_service, marketing, cookies, etc.
            $table->string('consent_version', 20); // Version of the consent document
            $table->boolean('is_granted')->default(false); // Whether consent is granted
            $table->timestamp('granted_at')->nullable(); // When consent was granted
            $table->timestamp('withdrawn_at')->nullable(); // When consent was withdrawn
            $table->string('ip_address', 45)->nullable(); // IP when consent was given
            $table->string('user_agent', 500)->nullable(); // Browser info
            $table->text('consent_text')->nullable(); // Full text of what was consented to
            $table->string('consent_method', 50)->nullable(); // checkbox, signature, verbal, etc.
            $table->json('metadata')->nullable(); // Additional consent context
            $table->boolean('is_required')->default(false); // Whether this consent is mandatory
            $table->timestamp('expires_at')->nullable(); // When consent expires (if applicable)
            $table->text('withdrawal_reason')->nullable(); // Reason for withdrawal
            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'consent_type']);
            $table->index(['consent_type', 'is_granted']);
            $table->index(['user_id', 'is_granted']);
            $table->index('expires_at');

            // Unique constraint to prevent duplicate active consents
            $table->unique(['user_id', 'consent_type', 'consent_version'], 'unique_user_consent');

            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_consents');
    }
};
