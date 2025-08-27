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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 100)->index(); // CREATE, UPDATE, DELETE, LOGIN, etc.
            $table->string('auditable_type', 100)->index(); // Model class name
            $table->unsignedBigInteger('auditable_id')->nullable()->index(); // Model ID
            $table->unsignedBigInteger('user_id')->nullable()->index(); // User who performed action
            $table->string('user_type', 100)->nullable(); // User model type
            $table->json('old_values')->nullable(); // Previous values
            $table->json('new_values')->nullable(); // New values
            $table->string('ip_address', 45)->nullable(); // IPv4 or IPv6
            $table->string('user_agent', 500)->nullable(); // Browser/client info
            $table->string('url', 500)->nullable(); // Request URL
            $table->string('http_method', 10)->nullable(); // GET, POST, etc.
            $table->json('request_data')->nullable(); // Request payload
            $table->string('session_id', 100)->nullable(); // Session identifier
            $table->string('transaction_id', 100)->nullable(); // For grouping related actions
            $table->text('description')->nullable(); // Human readable description
            $table->json('metadata')->nullable(); // Additional context data
            $table->string('severity', 20)->default('info'); // info, warning, error, critical
            $table->boolean('is_sensitive')->default(false); // Contains sensitive data
            $table->string('hash', 64)->nullable(); // SHA-256 hash for integrity
            $table->timestamp('created_at')->useCurrent();

            // Indexes for performance
            $table->index(['user_id', 'created_at']);
            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['event_type', 'created_at']);
            $table->index(['severity', 'created_at']);
            $table->index('transaction_id');

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
