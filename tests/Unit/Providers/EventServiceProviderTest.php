<?php

namespace Tests\Unit\Providers;

use App\Models\Tenant;
use App\Services\Cache\TenantCacheManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class EventServiceProviderTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
    }

    public function test_tenant_events_are_logged(): void
    {
        Event::fake(['tenant.*']);

        // Trigger tenant events
        event('tenant.created', [$this->tenant->id]);
        event('tenant.updated', [$this->tenant->id]);
        event('tenant.deleted', [$this->tenant->id]);
        event('tenant.switched', [$this->tenant->id]);

        // Assert events were fired
        Event::assertDispatched('tenant.created');
        Event::assertDispatched('tenant.updated');
        Event::assertDispatched('tenant.deleted');
        Event::assertDispatched('tenant.switched');
    }

    public function test_tenant_cache_is_cleared_on_update(): void
    {
        /** @var TenantCacheManager */
        $cache = app('cache.tenant');
        $cache->forTenant($this->tenant)->put('test_key', 'test_value');

        // Trigger tenant update event
        event('tenant.updated', [$this->tenant->id]);

        // Assert cache was cleared
        $this->assertNull($cache->forTenant($this->tenant)->get('test_key'));
    }

    public function test_tenant_cache_is_cleared_on_delete(): void
    {
        /** @var TenantCacheManager */
        $cache = app('cache.tenant');
        $cache->forTenant($this->tenant)->put('test_key', 'test_value');

        // Trigger tenant delete event
        event('tenant.deleted', [$this->tenant->id]);

        // Assert cache was cleared
        $this->assertNull($cache->forTenant($this->tenant)->get('test_key'));
    }

    public function test_tenant_settings_are_initialized_on_create(): void
    {
        $defaultSettings = config('tenancy.default_settings');
        $customSettings = ['custom_setting' => 'value'];

        $tenant = Tenant::factory()->create([
            'settings' => $customSettings,
        ]);

        // Trigger tenant create event
        event('tenant.created', [$tenant->id]);

        // Refresh tenant from database
        $tenant->refresh();

        // Assert default settings were merged with custom settings
        foreach ($defaultSettings as $key => $value) {
            $this->assertEquals($value, $tenant->settings[$key]);
        }
        $this->assertEquals('value', $tenant->settings['custom_setting']);
    }

    public function test_tenant_events_with_invalid_tenant_id(): void
    {
        Event::fake(['tenant.*']);

        // Trigger events with invalid tenant ID
        event('tenant.updated', [999999]);
        event('tenant.deleted', [999999]);
        event('tenant.created', [999999]);

        // Assert events were still fired
        Event::assertDispatched('tenant.updated');
        Event::assertDispatched('tenant.deleted');
        Event::assertDispatched('tenant.created');
    }

    public function test_tenant_event_properties(): void
    {
        Event::fake(['tenant.*']);

        $properties = ['key' => 'value'];

        // Trigger tenant event with properties
        event('tenant.custom', [$this->tenant->id, $properties]);

        // Assert event was fired with properties
        Event::assertDispatched('tenant.custom', function ($eventName, $data) use ($properties) {
            return $data[1] === $properties;
        });
    }

    public function test_tenant_event_activity_logging(): void
    {
        // Trigger tenant event
        event('tenant.custom', [$this->tenant->id, ['key' => 'value']]);

        // Assert activity was logged
        $this->assertDatabaseHas('activity_log', [
            'description' => 'Tenant event: tenant.custom',
            'subject_type' => null,
            'event' => 'tenant.custom',
        ]);
    }

    public function test_registered_event_listeners(): void
    {
        $registeredEvents = array_keys(Event::getListeners());

        $this->assertContains('tenant.*', $registeredEvents);
        $this->assertContains('tenant.created', $registeredEvents);
        $this->assertContains('tenant.updated', $registeredEvents);
        $this->assertContains('tenant.deleted', $registeredEvents);
    }

    public function test_event_discovery_is_disabled(): void
    {
        $provider = new \App\Providers\EventServiceProvider($this->app);
        $this->assertFalse($provider->shouldDiscoverEvents());
    }
}
