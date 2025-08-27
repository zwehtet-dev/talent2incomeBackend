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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('recipient_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('job_id')->nullable()->constrained('job_postings')->onDelete('set null');
            $table->text('content');
            $table->boolean('is_read')->default(false)->index();
            $table->timestamps();

            // Indexes for conversation queries and performance
            $table->index(['sender_id', 'recipient_id']);
            $table->index(['recipient_id', 'is_read']);
            $table->index(['job_id']);
            $table->index(['created_at']);

            // Composite index for conversation threading
            $table->index(['sender_id', 'recipient_id', 'created_at']);
            $table->index(['recipient_id', 'sender_id', 'created_at']);

            // Partitioning strategy preparation - index on created_at for time-based partitioning
            $table->index(['created_at', 'sender_id']);
            $table->index(['created_at', 'recipient_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
