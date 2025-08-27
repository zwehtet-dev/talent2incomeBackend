<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('reviews')) {
            Schema::create('reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('job_id')->constrained('job_postings')->onDelete('cascade');
                $table->foreignId('reviewer_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('reviewee_id')->constrained('users')->onDelete('cascade');
                $table->tinyInteger('rating')->unsigned()->index(); // 1-5 rating
                $table->text('comment')->nullable();
                $table->boolean('is_public')->default(true)->index();
                $table->boolean('is_flagged')->default(false)->index();
                $table->string('flagged_reason')->nullable();
                $table->timestamp('moderated_at')->nullable();
                $table->foreignId('moderated_by')->nullable()->constrained('users')->onDelete('set null');

                // Soft deletes support
                $table->softDeletes();

                $table->timestamps();

                // Unique constraint to prevent duplicate reviews
                $table->unique(['job_id', 'reviewer_id', 'reviewee_id'], 'unique_review_per_job');

                // Indexes for review queries and statistics
                $table->index(['reviewee_id', 'is_public']);
                $table->index(['reviewer_id']);
                $table->index(['rating', 'is_public']);
                $table->index(['created_at', 'is_public']);

                // Composite indexes for rating calculations
                $table->index(['reviewee_id', 'rating', 'is_public']);
                $table->index(['reviewee_id', 'created_at', 'is_public']);

                // Add check constraint for rating range (MySQL syntax)
                // SQLite will handle this at the application level
                if (config('database.default') === 'mysql') {
                    DB::statement('ALTER TABLE reviews ADD CONSTRAINT rating_check CHECK (rating >= 1 AND rating <= 5)');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
