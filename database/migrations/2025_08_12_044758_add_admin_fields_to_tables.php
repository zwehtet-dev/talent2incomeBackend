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
        // Add dispute fields to payments table
        Schema::table('payments', function (Blueprint $table) {
            $table->string('dispute_reason')->nullable()->after('status');
            $table->text('dispute_description')->nullable()->after('dispute_reason');
            $table->json('dispute_evidence')->nullable()->after('dispute_description');
            $table->enum('dispute_priority', ['low', 'medium', 'high'])->default('medium')->after('dispute_evidence');
            $table->timestamp('dispute_created_at')->nullable()->after('dispute_priority');
            $table->timestamp('dispute_resolved_at')->nullable()->after('dispute_created_at');
            $table->string('dispute_resolution')->nullable()->after('dispute_resolved_at');
            $table->text('dispute_resolution_notes')->nullable()->after('dispute_resolution');
            $table->unsignedBigInteger('dispute_resolved_by')->nullable()->after('dispute_resolution_notes');
            $table->decimal('refund_amount', 10, 2)->nullable()->after('dispute_resolved_by');
            $table->decimal('released_amount', 10, 2)->nullable()->after('refund_amount');
            $table->timestamp('refunded_at')->nullable()->after('released_amount');
            $table->timestamp('released_at')->nullable()->after('refunded_at');

            $table->foreign('dispute_resolved_by')->references('id')->on('users')->onDelete('set null');
        });

        // Add missing moderation fields to reviews table
        Schema::table('reviews', function (Blueprint $table) {
            // is_flagged already exists, add missing fields
            if (! Schema::hasColumn('reviews', 'is_approved')) {
                $table->boolean('is_approved')->nullable()->after('is_flagged');
            }
            if (! Schema::hasColumn('reviews', 'moderation_reason')) {
                $table->text('moderation_reason')->nullable()->after('moderated_by');
            }
            if (! Schema::hasColumn('reviews', 'flag_reason')) {
                $table->text('flag_reason')->nullable()->after('moderation_reason');
            }
            if (! Schema::hasColumn('reviews', 'flagged_by')) {
                $table->unsignedBigInteger('flagged_by')->nullable()->after('flag_reason');
            }
            if (! Schema::hasColumn('reviews', 'flagged_at')) {
                $table->timestamp('flagged_at')->nullable()->after('flagged_by');
            }

            // Add foreign keys if they don't exist
            if (! Schema::hasColumn('reviews', 'flagged_by')) {
                $table->foreign('flagged_by')->references('id')->on('users')->onDelete('set null');
            }
        });

        // Add moderation fields to job_postings table (not jobs which is for queue)
        Schema::table('job_postings', function (Blueprint $table) {
            $table->boolean('is_flagged')->default(false)->after('is_urgent');
            $table->boolean('is_approved')->nullable()->after('is_flagged');
            $table->timestamp('moderated_at')->nullable()->after('is_approved');
            $table->unsignedBigInteger('moderated_by')->nullable()->after('moderated_at');
            $table->text('moderation_reason')->nullable()->after('moderated_by');
            $table->text('flag_reason')->nullable()->after('moderation_reason');
            $table->unsignedBigInteger('flagged_by')->nullable()->after('flag_reason');
            $table->timestamp('flagged_at')->nullable()->after('flagged_by');

            $table->foreign('moderated_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('flagged_by')->references('id')->on('users')->onDelete('set null');
        });

        // Add moderation fields to skills table
        Schema::table('skills', function (Blueprint $table) {
            $table->boolean('is_flagged')->default(false)->after('is_active');
            $table->boolean('is_approved')->nullable()->after('is_flagged');
            $table->timestamp('moderated_at')->nullable()->after('is_approved');
            $table->unsignedBigInteger('moderated_by')->nullable()->after('moderated_at');
            $table->text('moderation_reason')->nullable()->after('moderated_by');
            $table->text('flag_reason')->nullable()->after('moderation_reason');
            $table->unsignedBigInteger('flagged_by')->nullable()->after('flag_reason');
            $table->timestamp('flagged_at')->nullable()->after('flagged_by');

            $table->foreign('moderated_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('flagged_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['dispute_resolved_by']);
            $table->dropColumn([
                'dispute_reason',
                'dispute_description',
                'dispute_evidence',
                'dispute_priority',
                'dispute_created_at',
                'dispute_resolved_at',
                'dispute_resolution',
                'dispute_resolution_notes',
                'dispute_resolved_by',
                'refund_amount',
                'released_amount',
                'refunded_at',
                'released_at',
            ]);
        });

        Schema::table('reviews', function (Blueprint $table) {
            if (Schema::hasColumn('reviews', 'flagged_by')) {
                $table->dropForeign(['flagged_by']);
            }
            $table->dropColumn([
                'is_approved',
                'moderation_reason',
                'flag_reason',
                'flagged_by',
                'flagged_at',
            ]);
        });

        Schema::table('job_postings', function (Blueprint $table) {
            $table->dropForeign(['moderated_by']);
            $table->dropForeign(['flagged_by']);
            $table->dropColumn([
                'is_flagged',
                'is_approved',
                'moderated_at',
                'moderated_by',
                'moderation_reason',
                'flag_reason',
                'flagged_by',
                'flagged_at',
            ]);
        });

        Schema::table('skills', function (Blueprint $table) {
            $table->dropForeign(['moderated_by']);
            $table->dropForeign(['flagged_by']);
            $table->dropColumn([
                'is_flagged',
                'is_approved',
                'moderated_at',
                'moderated_by',
                'moderation_reason',
                'flag_reason',
                'flagged_by',
                'flagged_at',
            ]);
        });
    }
};
