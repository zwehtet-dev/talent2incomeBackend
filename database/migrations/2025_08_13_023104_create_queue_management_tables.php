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
        // Queue metrics table for historical data
        Schema::create('queue_metrics', function (Blueprint $table) {
            $table->id();
            $table->timestamp('timestamp');
            $table->string('overall_status', 20);
            $table->integer('total_pending')->default(0);
            $table->integer('total_processing')->default(0);
            $table->integer('total_failed')->default(0);
            $table->decimal('redis_memory_usage', 5, 2)->nullable();
            $table->integer('active_workers')->default(0);
            $table->json('queue_details')->nullable();
            $table->timestamps();

            $table->index('timestamp');
            $table->index('overall_status');
        });

        // Queue alerts table for alert history
        Schema::create('queue_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50);
            $table->string('queue', 50)->nullable();
            $table->string('status', 20);
            $table->json('data');
            $table->boolean('resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'created_at']);
            $table->index(['queue', 'created_at']);
            $table->index('resolved');
        });

        // Queue job history for detailed tracking
        Schema::create('queue_job_history', function (Blueprint $table) {
            $table->id();
            $table->string('job_id', 100);
            $table->string('job_class');
            $table->string('queue', 50);
            $table->string('status', 20); // pending, processing, completed, failed
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->integer('memory_usage_mb')->nullable();
            $table->integer('attempts')->default(1);
            $table->text('error_message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['job_class', 'created_at']);
            $table->index(['queue', 'status']);
            $table->index('started_at');
            $table->index('completed_at');
        });

        // Worker process tracking
        Schema::create('queue_workers', function (Blueprint $table) {
            $table->id();
            $table->string('worker_id', 100)->unique();
            $table->integer('pid');
            $table->string('hostname', 100);
            $table->string('queue', 50);
            $table->string('status', 20); // active, idle, stopped, failed
            $table->timestamp('started_at');
            $table->timestamp('last_heartbeat');
            $table->integer('jobs_processed')->default(0);
            $table->integer('memory_usage_mb')->nullable();
            $table->decimal('cpu_usage', 5, 2)->nullable();
            $table->timestamps();

            $table->index(['status', 'last_heartbeat']);
            $table->index('queue');
            $table->index('hostname');
        });

        // Queue configuration snapshots
        Schema::create('queue_config_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('config_hash', 64);
            $table->json('configuration');
            $table->timestamp('applied_at');
            $table->string('applied_by', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('config_hash');
            $table->index('applied_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('queue_config_snapshots');
        Schema::dropIfExists('queue_workers');
        Schema::dropIfExists('queue_job_history');
        Schema::dropIfExists('queue_alerts');
        Schema::dropIfExists('queue_metrics');
    }
};
