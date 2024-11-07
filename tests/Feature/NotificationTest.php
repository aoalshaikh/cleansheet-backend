<?php

namespace Tests\Feature;

use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $admin;
    private User $user;
    private NotificationTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();
        
        $tenant = Tenant::factory()->create();
        
        /** @var User $admin */
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $admin->assignRole('admin');
        $this->admin = $admin;

        /** @var User $user */
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->user = $user;

        // Create a notification template
        $this->template = NotificationTemplate::create([
            'name' => 'Match Reminder',
            'title' => 'Upcoming Match: {team_name} vs {opponent_name}',
            'content' => 'Don\'t forget your match against {opponent_name} at {venue} on {match_date}.',
            'channels' => ['email', 'sms'],
            'variables' => [
                'team_name',
                'opponent_name',
                'venue',
                'match_date'
            ],
            'is_active' => true
        ]);
    }

    public function test_can_create_notification_template(): void
    {
        $templateData = [
            'name' => 'Training Schedule Change',
            'title' => 'Training Schedule Update for {team_name}',
            'content' => 'The training schedule for {team_name} has changed. New time: {new_time}',
            'channels' => ['email', 'push'],
            'variables' => [
                'team_name',
                'new_time'
            ]
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/notification-templates', $templateData);

        $response->assertCreated();
        $this->assertDatabaseHas('notification_templates', [
            'name' => 'Training Schedule Change',
            'slug' => 'training-schedule-change'
        ]);
    }

    public function test_can_update_notification_template(): void
    {
        $updateData = [
            'title' => 'Match Day: {team_name} vs {opponent_name}',
            'content' => 'Your match against {opponent_name} is today at {venue}. Arrival time: {arrival_time}',
            'channels' => ['email', 'sms', 'push'],
            'variables' => [
                'team_name',
                'opponent_name',
                'venue',
                'arrival_time'
            ]
        ];

        $response = $this->actingAs($this->admin)
            ->putJson("/api/notification-templates/{$this->template->id}", $updateData);

        $response->assertOk();
        $this->template->refresh();
        
        $this->assertContains('push', $this->template->channels);
        $this->assertContains('arrival_time', $this->template->variables);
    }

    public function test_can_compile_template(): void
    {
        $data = [
            'team_name' => 'Blue Dragons',
            'opponent_name' => 'Red Lions',
            'venue' => 'Central Stadium',
            'match_date' => '2024-03-15 15:00'
        ];

        $compiled = $this->template->compile($data);

        $this->assertEquals(
            'Upcoming Match: Blue Dragons vs Red Lions',
            $compiled['title']
        );
        $this->assertEquals(
            'Don\'t forget your match against Red Lions at Central Stadium on 2024-03-15 15:00.',
            $compiled['content']
        );
    }

    public function test_can_manage_template_channels(): void
    {
        // Add new channel
        $this->template->addChannel('push');
        $this->assertContains('push', $this->template->channels);

        // Remove channel
        $this->template->removeChannel('sms');
        $this->assertNotContains('sms', $this->template->channels);
    }

    public function test_can_send_notification(): void
    {
        $notificationData = [
            'user_id' => $this->user->id,
            'template_id' => $this->template->id,
            'channel' => 'email',
            'variables' => [
                'team_name' => 'Blue Dragons',
                'opponent_name' => 'Red Lions',
                'venue' => 'Central Stadium',
                'match_date' => '2024-03-15 15:00'
            ]
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/notifications/send', $notificationData);

        $response->assertOk();
        $this->assertDatabaseHas('notification_logs', [
            'user_id' => $this->user->id,
            'template_id' => $this->template->id,
            'channel' => 'email'
        ]);
    }

    public function test_can_track_notification_status(): void
    {
        $log = NotificationLog::create([
            'template_id' => $this->template->id,
            'user_id' => $this->user->id,
            'channel' => 'email',
            'title' => 'Test Notification',
            'content' => 'Test content',
            'sent_at' => now()
        ]);

        // Mark as read
        $response = $this->actingAs($this->user)
            ->putJson("/api/notifications/{$log->id}/read");

        $response->assertOk();
        $this->assertNotNull($log->fresh()->read_at);

        // Test failed notification
        $failedLog = NotificationLog::create([
            'template_id' => $this->template->id,
            'user_id' => $this->user->id,
            'channel' => 'sms',
            'title' => 'Failed Notification',
            'content' => 'Failed content',
            'failed_at' => now(),
            'failure_reason' => 'Invalid phone number'
        ]);

        $this->assertNotNull($failedLog->failed_at);
        $this->assertEquals('Invalid phone number', $failedLog->failure_reason);
    }

    public function test_notification_template_scopes(): void
    {
        // Create additional templates
        NotificationTemplate::create([
            'name' => 'Inactive Template',
            'title' => 'Inactive',
            'content' => 'Content',
            'channels' => ['email'],
            'is_active' => false
        ]);

        NotificationTemplate::create([
            'name' => 'Push Only',
            'title' => 'Push',
            'content' => 'Content',
            'channels' => ['push'],
            'is_active' => true
        ]);

        $this->assertEquals(2, NotificationTemplate::active()->count());
        $this->assertEquals(2, NotificationTemplate::byChannel('email')->count());
        $this->assertEquals(1, NotificationTemplate::byChannel('push')->count());
    }

    public function test_notification_log_queries(): void
    {
        // Create multiple notification logs
        NotificationLog::create([
            'template_id' => $this->template->id,
            'user_id' => $this->user->id,
            'channel' => 'email',
            'title' => 'First Notification',
            'content' => 'Content',
            'sent_at' => now()->subDays(2)
        ]);

        NotificationLog::create([
            'template_id' => $this->template->id,
            'user_id' => $this->user->id,
            'channel' => 'sms',
            'title' => 'Second Notification',
            'content' => 'Content',
            'sent_at' => now()->subDay(),
            'read_at' => now()
        ]);

        NotificationLog::create([
            'template_id' => $this->template->id,
            'user_id' => $this->user->id,
            'channel' => 'push',
            'title' => 'Failed Notification',
            'content' => 'Content',
            'failed_at' => now(),
            'failure_reason' => 'Device token expired'
        ]);

        // Test various queries
        $response = $this->actingAs($this->user)
            ->getJson('/api/notifications');

        $response->assertOk();
        $this->assertEquals(3, $response->json('total'));

        // Test unread notifications
        $response = $this->actingAs($this->user)
            ->getJson('/api/notifications/unread');

        $response->assertOk();
        $this->assertEquals(1, $response->json('total'));

        // Test failed notifications
        $response = $this->actingAs($this->admin)
            ->getJson('/api/notifications/failed');

        $response->assertOk();
        $this->assertEquals(1, $response->json('total'));
    }
}
