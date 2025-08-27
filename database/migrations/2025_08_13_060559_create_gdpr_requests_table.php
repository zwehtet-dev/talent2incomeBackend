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
        Schema::create('gdpr_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('request_type', 50); // export, delete, rectify, restrict, object
            $table->enum('status', ['pending', 'processing', 'completed', 'rejected', 'cancelled'])
                ->default('pending')->index();
            $table->text('description')->nullable(); // User's description of request
            $table->json('requested_data')->nullable(); // Specific data types requested
            $table->string('verification_token', 100)->nullable(); // Email verification token
            $table->timestamp('verified_at')->nullable(); // When request was verified
            $table->timestamp('processed_at')->nullable(); // When processing started
            $table->timestamp('completed_at')->nullable(); // When request was fulfilled
            $table->string('export_file_path', 500)->nullable(); // Path to exported data file
            $table->string('export_file_hash', 64)->nullable(); // SHA-256 hash of export file
            $table->timestamp('export_expires_at')->nullable(); // When export file expires
            $table->text('admin_notes')->nullable(); // Internal notes for admins
            $table->unsignedBigInteger('processed_by')->nullable(); // Admin who processed
            $table->string('rejection_reason', 500)->nullable(); // Reason for rejection
            $table->json('metadata')->nullable(); // Additional request metadata
            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'status']);
            $table->index(['request_type', 'status']);
            $table->index(['status', 'created_at']);

            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('processed_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gdpr_requests');
    }
};
