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
        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained()->onDelete('restrict');
            $table->string('title', 200)->index();
            $table->text('description');
            $table->decimal('price_per_hour', 10, 2)->nullable();
            $table->decimal('price_fixed', 10, 2)->nullable();
            $table->enum('pricing_type', ['hourly', 'fixed', 'negotiable'])->index();
            $table->boolean('is_available')->default(true)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            // Composite indexes for filtering and performance
            $table->index(['user_id', 'is_active']);
            $table->index(['category_id', 'is_available']);
            $table->index(['pricing_type', 'is_available']);
            $table->index(['is_available', 'is_active']);

            // Full-text search index for title and description (MySQL only)
            // SQLite will use regular indexes for search
            if (config('database.default') === 'mysql') {
                $table->fullText(['title', 'description'], 'skills_search_index');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('skills');
    }
};
