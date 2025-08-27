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
            // Cached rating statistics for performance
            $table->decimal('cached_average_rating', 3, 2)->default(0.00)->after('is_admin');
            $table->decimal('cached_weighted_rating', 3, 2)->default(0.00)->after('cached_average_rating');
            $table->decimal('cached_quality_score', 5, 2)->default(0.00)->after('cached_weighted_rating');
            $table->integer('cached_total_reviews')->default(0)->after('cached_quality_score');
            $table->timestamp('rating_cache_updated_at')->nullable()->after('cached_total_reviews');

            // Activity tracking for rating decay
            $table->timestamp('last_activity_at')->nullable()->after('rating_cache_updated_at');
            $table->boolean('is_rating_eligible')->default(false)->after('last_activity_at');

            // Indexes for rating-based queries
            $table->index(['cached_weighted_rating', 'is_active']);
            $table->index(['cached_quality_score', 'is_rating_eligible']);
            $table->index(['cached_total_reviews', 'is_active']);
            $table->index('last_activity_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['cached_weighted_rating', 'is_active']);
            $table->dropIndex(['cached_quality_score', 'is_rating_eligible']);
            $table->dropIndex(['cached_total_reviews', 'is_active']);
            $table->dropIndex(['last_activity_at']);

            $table->dropColumn([
                'cached_average_rating',
                'cached_weighted_rating',
                'cached_quality_score',
                'cached_total_reviews',
                'rating_cache_updated_at',
                'last_activity_at',
                'is_rating_eligible',
            ]);
        });
    }
};
