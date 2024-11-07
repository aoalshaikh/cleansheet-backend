<?php

namespace Tests\Unit\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ActivityLogMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_log_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('activity_log'));
    }

    public function test_activity_log_table_has_required_columns(): void
    {
        $columns = Schema::getColumnListing('activity_log');

        $this->assertContains('id', $columns);
        $this->assertContains('log_name', $columns);
        $this->assertContains('description', $columns);
        $this->assertContains('subject_type', $columns);
        $this->assertContains('subject_id', $columns);
        $this->assertContains('causer_type', $columns);
        $this->assertContains('causer_id', $columns);
        $this->assertContains('properties', $columns);
        $this->assertContains('batch_uuid', $columns);
        $this->assertContains('created_at', $columns);
        $this->assertContains('updated_at', $columns);
    }

    public function test_activity_log_table_has_required_indexes(): void
    {
        $indexes = collect(DB::select('SHOW INDEXES FROM activity_log'))
            ->pluck('Key_name')
            ->unique()
            ->values()
            ->all();

        $this->assertContains('activity_log_log_name_index', $indexes);
        $this->assertContains('activity_log_subject_type_subject_id_index', $indexes);
        $this->assertContains('activity_log_causer_type_causer_id_index', $indexes);
        $this->assertContains('activity_log_tenant_id_index', $indexes);
        $this->assertContains('activity_log_event_index', $indexes);
        $this->assertContains('activity_log_success_index', $indexes);
        $this->assertContains('activity_log_ip_address_index', $indexes);
        $this->assertContains('activity_log_tenant_date_index', $indexes);
        $this->assertContains('activity_log_created_at_index', $indexes);
        $this->assertContains('activity_log_updated_at_index', $indexes);
        $this->assertContains('activity_log_batch_uuid_index', $indexes);
    }

    public function test_activity_log_summary_view_exists(): void
    {
        $views = collect(DB::select("SHOW FULL TABLES WHERE Table_Type = 'VIEW'"))
            ->pluck('Tables_in_' . env('DB_DATABASE'))
            ->all();

        $this->assertContains('activity_log_summary', $views);
    }

    public function test_activity_log_summary_view_returns_correct_data(): void
    {
        // Insert test data
        DB::table('activity_log')->insert([
            'log_name' => 'auth',
            'description' => 'User login',
            'subject_type' => 'App\\Models\\User',
            'subject_id' => 1,
            'causer_type' => 'App\\Models\\User',
            'causer_id' => 1,
            'properties' => json_encode([
                'tenant_id' => 1,
                'success' => true,
                'ip_address' => '127.0.0.1',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('activity_log')->insert([
            'log_name' => 'auth',
            'description' => 'Failed login',
            'subject_type' => 'App\\Models\\User',
            'subject_id' => 2,
            'causer_type' => 'App\\Models\\User',
            'causer_id' => 2,
            'properties' => json_encode([
                'tenant_id' => 1,
                'success' => false,
                'ip_address' => '127.0.0.2',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $summary = DB::table('activity_log_summary')
            ->where('tenant_id', 1)
            ->where('log_name', 'auth')
            ->where('date', now()->toDateString())
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(2, $summary->total_activities);
        $this->assertEquals(1, $summary->successful_activities);
        $this->assertEquals(1, $summary->failed_activities);
        $this->assertEquals(2, $summary->unique_users);
        $this->assertEquals(2, $summary->unique_ips);
    }

    public function test_activity_log_table_json_queries(): void
    {
        // Insert test data
        DB::table('activity_log')->insert([
            'log_name' => 'profile',
            'description' => 'Profile updated',
            'properties' => json_encode([
                'tenant_id' => 1,
                'changes' => [
                    'name' => ['old' => 'John', 'new' => 'Johnny'],
                    'email' => ['old' => 'john@example.com', 'new' => 'johnny@example.com'],
                ],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Test JSON path queries
        $result = DB::table('activity_log')
            ->whereJsonContains('properties->changes->name->new', 'Johnny')
            ->first();

        $this->assertNotNull($result);
        $this->assertEquals('Profile updated', $result->description);
    }

    public function test_activity_log_table_supports_batch_operations(): void
    {
        $batchUuid = '123e4567-e89b-12d3-a456-426614174000';

        // Insert batch of related activities
        DB::table('activity_log')->insert([
            [
                'log_name' => 'user',
                'description' => 'User created',
                'batch_uuid' => $batchUuid,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'log_name' => 'profile',
                'description' => 'Profile created',
                'batch_uuid' => $batchUuid,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $batchActivities = DB::table('activity_log')
            ->where('batch_uuid', $batchUuid)
            ->get();

        $this->assertCount(2, $batchActivities);
    }
}
