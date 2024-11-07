<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->string('log_name')->nullable()->index();
            $table->text('description');
            
            // Subject - the model being logged
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->index(['subject_type', 'subject_id']);

            // Causer - the model causing the activity (usually a user)
            $table->string('causer_type')->nullable();
            $table->unsignedBigInteger('causer_id')->nullable();
            $table->index(['causer_type', 'causer_id']);

            // Properties - JSON column for additional data
            $table->json('properties')->nullable();

            // Add index for tenant_id in properties for efficient tenant scoping
            $table->rawIndex(
                '((properties->>"$.tenant_id")::bigint)',
                'activity_log_tenant_id_index'
            );

            // Add index for event type in properties
            $table->rawIndex(
                'properties->>"$.event"',
                'activity_log_event_index'
            );

            // Add index for success flag in properties
            $table->rawIndex(
                'properties->>"$.success"',
                'activity_log_success_index'
            );

            // Add index for IP address in properties
            $table->rawIndex(
                'properties->>"$.ip_address"',
                'activity_log_ip_address_index'
            );

            // Add composite index for tenant_id and created_at for efficient cleanup
            $table->rawIndex(
                '((properties->>"$.tenant_id")::bigint), created_at',
                'activity_log_tenant_date_index'
            );

            $table->timestamps();
            $table->index('created_at');
            $table->index('updated_at');

            // Add batch UUID for grouping related activities
            $table->uuid('batch_uuid')->nullable()->index();
        });

        // Create a view for common activity queries
        DB::statement('
            CREATE VIEW activity_log_summary AS
            SELECT 
                DATE(created_at) as date,
                log_name,
                (properties->>"$.tenant_id")::bigint as tenant_id,
                COUNT(*) as total_activities,
                COUNT(CASE WHEN properties->>"$.success" = \'true\' THEN 1 END) as successful_activities,
                COUNT(CASE WHEN properties->>"$.success" = \'false\' THEN 1 END) as failed_activities,
                COUNT(DISTINCT causer_id) as unique_users,
                COUNT(DISTINCT properties->>"$.ip_address") as unique_ips
            FROM activity_log
            GROUP BY DATE(created_at), log_name, (properties->>"$.tenant_id")::bigint
        ');
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS activity_log_summary');
        Schema::dropIfExists('activity_log');
    }
};
