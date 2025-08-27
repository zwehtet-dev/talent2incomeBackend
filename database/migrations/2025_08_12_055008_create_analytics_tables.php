<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // Revenue analytics table
        Schema::create('revenue_analytics', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->decimal('total_revenue', 12, 2)->default(0);
            $table->decimal('platform_fees', 12, 2)->default(0);
            $table->decimal('net_revenue', 12, 2)->default(0);
            $table->integer('transaction_count')->default(0);
            $table->decimal('average_transaction_value', 10, 2)->default(0);
            $table->timestamps();

            $table->unique('date');
            $table->index('date');
        });

        // User engagement analytics table
        Schema::create('user_engagement_analytics', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->integer('daily_active_users')->default(0);
            $table->integer('weekly_active_users')->default(0);
            $table->integer('monthly_active_users')->default(0);
            $table->integer('new_registrations')->default(0);
            $table->integer('jobs_posted')->default(0);
            $table->integer('skills_posted')->default(0);
            $table->integer('messages_sent')->default(0);
            $table->integer('reviews_created')->default(0);
            $table->decimal('average_session_duration', 8, 2)->default(0);
            $table->timestamps();

            $table->unique('date');
            $table->index('date');
        });

        // Cohort analytics table
        Schema::create('cohort_analytics', function (Blueprint $table) {
            $table->id();
            $table->date('cohort_month');
            $table->integer('period_number');
            $table->integer('users_count')->default(0);
            $table->decimal('retention_rate', 5, 2)->default(0);
            $table->decimal('revenue_per_user', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['cohort_month', 'period_number']);
            $table->index(['cohort_month', 'period_number']);
        });

        // System performance metrics table
        Schema::create('system_performance_metrics', function (Blueprint $table) {
            $table->id();
            $table->timestamp('recorded_at');
            $table->decimal('average_response_time', 8, 2)->default(0);
            $table->integer('total_requests')->default(0);
            $table->integer('error_count')->default(0);
            $table->decimal('error_rate', 5, 2)->default(0);
            $table->decimal('cpu_usage', 5, 2)->default(0);
            $table->decimal('memory_usage', 5, 2)->default(0);
            $table->decimal('disk_usage', 5, 2)->default(0);
            $table->integer('active_connections')->default(0);
            $table->timestamps();

            $table->index('recorded_at');
        });

        // Scheduled reports table
        Schema::create('scheduled_reports', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // daily, weekly, monthly
            $table->json('recipients'); // email addresses
            $table->json('metrics'); // which metrics to include
            $table->string('frequency'); // daily, weekly, monthly
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('next_send_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'next_send_at']);
        });

        // Generated reports table
        Schema::create('generated_reports', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->date('report_date');
            $table->json('data');
            $table->string('file_path')->nullable();
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->index(['type', 'report_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_reports');
        Schema::dropIfExists('scheduled_reports');
        Schema::dropIfExists('system_performance_metrics');
        Schema::dropIfExists('cohort_analytics');
        Schema::dropIfExists('user_engagement_analytics');
        Schema::dropIfExists('revenue_analytics');
    }
};
