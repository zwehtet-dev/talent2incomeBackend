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
        Schema::create('security_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('incident_type', 100)->index(); // brute_force, sql_injection, xss, etc.
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium')->index();
            $table->enum('status', ['open', 'investigating', 'resolved', 'false_positive'])
                ->default('open')->index();
            $table->string('title', 200); // Brief incident description
            $table->text('description'); // Detailed incident description
            $table->string('source_ip', 45)->nullable()->index(); // IP address of attacker
            $table->string('target_endpoint', 500)->nullable(); // Attacked endpoint
            $table->string('http_method', 10)->nullable(); // Request method
            $table->json('request_headers')->nullable(); // Request headers
            $table->json('request_payload')->nullable(); // Request body/parameters
            $table->string('user_agent', 500)->nullable(); // Attacker's user agent
            $table->unsignedBigInteger('affected_user_id')->nullable()->index(); // Affected user
            $table->json('affected_resources')->nullable(); // List of affected resources
            $table->integer('attack_count')->default(1); // Number of attack attempts
            $table->timestamp('first_detected_at')->useCurrent(); // When first detected
            $table->timestamp('last_detected_at')->useCurrent(); // Most recent occurrence
            $table->boolean('is_automated')->default(false); // Automated vs manual attack
            $table->string('detection_method', 100)->nullable(); // How it was detected
            $table->json('mitigation_actions')->nullable(); // Actions taken to mitigate
            $table->unsignedBigInteger('assigned_to')->nullable(); // Admin assigned to investigate
            $table->text('investigation_notes')->nullable(); // Investigation findings
            $table->timestamp('resolved_at')->nullable(); // When incident was resolved
            $table->string('resolution_summary', 500)->nullable(); // How it was resolved
            $table->json('metadata')->nullable(); // Additional incident data
            $table->boolean('notification_sent')->default(false); // Whether alerts were sent
            $table->string('reference_id', 100)->nullable(); // External reference ID
            $table->timestamps();

            // Indexes for performance
            $table->index(['incident_type', 'severity']);
            $table->index(['status', 'created_at']);
            $table->index(['source_ip', 'created_at']);
            $table->index(['first_detected_at', 'last_detected_at']);

            // Foreign keys
            $table->foreign('affected_user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_incidents');
    }
};
