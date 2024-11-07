<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('domain')->unique()->nullable();
            $table->json('domains')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('is_active');
            $table->index('created_at');
            $table->index('deleted_at');
        });

        // Add tenant_id to users table
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->onDelete('cascade');

            $table->index('tenant_id');
        });

        // Add tenant_id to activity_log table
        Schema::table('activity_log', function (Blueprint $table) {
            $table->foreignId('tenant_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->onDelete('cascade');

            $table->index(['tenant_id', 'created_at']);
        });

        // Add tenant_id to personal_access_tokens table
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->foreignId('tenant_id')
                ->nullable()
                ->after('tokenable_id')
                ->constrained()
                ->onDelete('cascade');

            $table->index('tenant_id');
        });

        // Add tenant_id to model_has_roles table
        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->foreignId('tenant_id')
                ->nullable()
                ->after('role_id')
                ->constrained()
                ->onDelete('cascade');

            $table->index('tenant_id');
        });

        // Add tenant_id to model_has_permissions table
        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->foreignId('tenant_id')
                ->nullable()
                ->after('permission_id')
                ->constrained()
                ->onDelete('cascade');

            $table->index('tenant_id');
        });

        // Add tenant_id to jobs table
        Schema::table('jobs', function (Blueprint $table) {
            $table->foreignId('tenant_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->onDelete('cascade');

            $table->index(['tenant_id', 'queue']);
        });

        // Add tenant_id to failed_jobs table
        Schema::table('failed_jobs', function (Blueprint $table) {
            $table->foreignId('tenant_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->onDelete('cascade');

            $table->index('tenant_id');
        });

        // Add tenant_id to notifications table
        Schema::table('notifications', function (Blueprint $table) {
            $table->foreignId('tenant_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->onDelete('cascade');

            $table->index(['tenant_id', 'created_at']);
        });

        // Add tenant_id to cache table
        Schema::table('cache', function (Blueprint $table) {
            $table->string('tenant_id')
                ->nullable()
                ->after('key');

            $table->index(['tenant_id', 'key']);
        });

        // Add tenant_id to sessions table
        Schema::table('sessions', function (Blueprint $table) {
            $table->string('tenant_id')
                ->nullable()
                ->after('id');

            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove tenant_id from all tables in reverse order
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('cache', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'key']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'created_at']);
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('failed_jobs', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('jobs', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'queue']);
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'created_at']);
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });

        Schema::dropIfExists('tenants');
    }
};
