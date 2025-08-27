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
            // Drop the existing name column and add first_name, last_name
            $table->dropColumn('name');
            $table->string('first_name', 100)->after('id');
            $table->string('last_name', 100)->after('first_name');

            // Add additional profile fields
            $table->string('avatar')->nullable()->after('email_verified_at');
            $table->text('bio')->nullable()->after('avatar');
            $table->string('location')->nullable()->after('bio');
            $table->string('phone', 20)->nullable()->after('location');

            // Add status and admin flags
            $table->boolean('is_active')->default(true)->after('phone');
            $table->boolean('is_admin')->default(false)->after('is_active');

            // Add soft deletes
            $table->softDeletes()->after('is_admin');

            // Add indexes for performance
            $table->index('email');
            $table->index('location');
            $table->index(['is_active', 'is_admin']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Remove added columns
            $table->dropColumn([
                'first_name', 'last_name', 'avatar', 'bio',
                'location', 'phone', 'is_active', 'is_admin', 'deleted_at',
            ]);

            // Drop indexes
            $table->dropIndex(['users_email_index']);
            $table->dropIndex(['users_location_index']);
            $table->dropIndex(['users_is_active_is_admin_index']);

            // Add back the name column
            $table->string('name')->after('id');
        });
    }
};
