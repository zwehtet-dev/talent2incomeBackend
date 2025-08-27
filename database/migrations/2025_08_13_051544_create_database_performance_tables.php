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
        Schema::create('database_performance_metrics', function (Blueprint $table) {
            $table->id();
            $table->timestamp('timestamp');
            $table->decimal('connection_usage_percent', 5, 2)->nullable();
            $table->decimal('avg_query_time', 10, 2)->nullable();
            $table->decimal('slow_query_percent', 5, 2)->nullable();
            $table->integer('total_queries')->nullable();
            $table->json('pool_stats')->nullable();
            $table->timestamps();

            $table->index('timestamp'); // single column
            $table->index(['timestamp', 'connection_usage_percent'], 'db_perf_metrics_ts_conn_idx'); // short name
        });

        // Backup logs table
        Schema::create('backup_logs', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->enum('type', ['full', 'incremental', 'point_in_time']);
            $table->string('path');
            $table->bigInteger('size')->default(0);
            $table->text('metadata')->nullable();
            $table->enum('status', ['completed', 'failed', 'in_progress'])->default('completed');
            $table->timestamps();

            $table->index('type');
            $table->index('created_at');
            $table->index(['type', 'created_at']);
        });

        // Slow query log table
        Schema::create('slow_query_logs', function (Blueprint $table) {
            $table->id();
            $table->text('sql');
            $table->json('bindings')->nullable();
            $table->decimal('execution_time', 10, 2);
            $table->string('query_type', 20);
            $table->text('suggestions')->nullable();
            $table->json('explain_data')->nullable();
            $table->timestamp('executed_at');
            $table->timestamps();

            $table->index('execution_time');
            $table->index('query_type');
            $table->index('executed_at');
        });

        // Database connection pool stats table
        Schema::create('connection_pool_stats', function (Blueprint $table) {
            $table->id();
            $table->string('pool_type', 20);
            $table->integer('active_connections');
            $table->integer('idle_connections');
            $table->integer('total_connections');
            $table->decimal('utilization_percent', 5, 2);
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['pool_type', 'recorded_at']);
            $table->index('utilization_percent');
        });

        // Database table statistics
        Schema::create('table_statistics', function (Blueprint $table) {
            $table->id();
            $table->string('table_name');
            $table->bigInteger('row_count');
            $table->bigInteger('data_size');
            $table->bigInteger('index_size');
            $table->bigInteger('total_size');
            $table->decimal('fragmentation_percent', 5, 2)->nullable();
            $table->timestamp('analyzed_at');
            $table->timestamps();

            $table->index(['table_name', 'analyzed_at']);
            $table->index('total_size');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('table_statistics');
        Schema::dropIfExists('connection_pool_stats');
        Schema::dropIfExists('slow_query_logs');
        Schema::dropIfExists('backup_logs');
        Schema::dropIfExists('database_performance_metrics');
    }
};
