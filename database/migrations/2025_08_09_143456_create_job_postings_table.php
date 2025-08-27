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
        Schema::create('job_postings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained()->onDelete('restrict');
            $table->string('title', 200)->index();
            $table->text('description');
            $table->decimal('budget_min', 10, 2)->nullable();
            $table->decimal('budget_max', 10, 2)->nullable();
            $table->enum('budget_type', ['hourly', 'fixed', 'negotiable'])->index();
            $table->date('deadline')->nullable()->index();
            $table->enum('status', ['open', 'in_progress', 'completed', 'cancelled', 'expired'])->default('open')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->boolean('is_urgent')->default(false)->index();
            $table->timestamps();
            $table->softDeletes();

            // Composite indexes for filtering and performance
            $table->index(['user_id', 'status']);
            $table->index(['category_id', 'status']);
            $table->index(['status', 'deadline']);
            $table->index(['budget_type', 'status']);
            $table->index(['is_urgent', 'status']);
            $table->index(['assigned_to', 'status']);

            // Full-text search index for title and description (MySQL only)
            // SQLite will use regular indexes for search
            if (config('database.default') === 'mysql') {
                $table->fullText(['title', 'description'], 'job_postings_search_index');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_postings');
    }
};
