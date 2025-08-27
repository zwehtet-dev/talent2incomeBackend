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
        Schema::create('rating_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->decimal('simple_average', 3, 2)->default(0.00);
            $table->decimal('weighted_average', 3, 2)->default(0.00);
            $table->decimal('time_weighted_average', 3, 2)->default(0.00);
            $table->decimal('decayed_rating', 3, 2)->default(0.00);
            $table->decimal('quality_score', 5, 2)->default(0.00);
            $table->integer('total_reviews')->default(0);
            $table->json('rating_distribution')->nullable();
            $table->json('trend_data')->nullable();
            $table->string('calculation_trigger')->nullable(); // 'new_review', 'scheduled', 'manual'
            $table->timestamps();

            // Indexes for efficient querying
            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'weighted_average']);
            $table->index(['user_id', 'quality_score']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rating_history');
    }
};
