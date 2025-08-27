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
            // Account lockout tracking fields
            $table->integer('failed_login_attempts')->default(0)->after('is_admin');
            $table->timestamp('locked_until')->nullable()->after('failed_login_attempts');
            $table->string('last_login_ip', 45)->nullable()->after('locked_until');
            $table->timestamp('last_login_at')->nullable()->after('last_login_ip');
            $table->json('login_history')->nullable()->after('last_login_at');

            // Add indexes for performance
            $table->index('failed_login_attempts');
            $table->index('locked_until');
            $table->index('last_login_ip');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['users_failed_login_attempts_index']);
            $table->dropIndex(['users_locked_until_index']);
            $table->dropIndex(['users_last_login_ip_index']);

            $table->dropColumn([
                'failed_login_attempts',
                'locked_until',
                'last_login_ip',
                'last_login_at',
                'login_history',
            ]);
        });
    }
};
