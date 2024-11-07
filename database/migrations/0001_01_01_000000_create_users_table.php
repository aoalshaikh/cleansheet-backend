<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                ->nullable()
                ->constrained()
                ->onDelete('cascade');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('password');
            $table->string('avatar_path')->nullable();
            $table->json('preferences')->nullable();
            $table->json('settings')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['tenant_id', 'email']);
            $table->index(['tenant_id', 'phone']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'deleted_at']);
            $table->index(['email_verified_at']);
            $table->index(['phone_verified_at']);
        });

        // Create a view for user statistics
        DB::statement('
            CREATE VIEW user_statistics AS
            SELECT 
                tenant_id,
                COUNT(*) as total_users,
                COUNT(CASE WHEN deleted_at IS NULL THEN 1 END) as active_users,
                COUNT(CASE WHEN deleted_at IS NOT NULL THEN 1 END) as deleted_users,
                COUNT(CASE WHEN email_verified_at IS NOT NULL THEN 1 END) as verified_email_users,
                COUNT(CASE WHEN phone_verified_at IS NOT NULL THEN 1 END) as verified_phone_users,
                COUNT(CASE WHEN avatar_path IS NOT NULL THEN 1 END) as users_with_avatar,
                MIN(created_at) as first_user_created_at,
                MAX(created_at) as last_user_created_at
            FROM users
            GROUP BY tenant_id
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS user_statistics');
        Schema::dropIfExists('users');
    }
};
