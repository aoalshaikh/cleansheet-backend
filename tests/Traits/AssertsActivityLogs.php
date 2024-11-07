<?php

namespace Tests\Traits;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

trait AssertsActivityLogs
{
    /**
     * Assert that an activity was logged.
     */
    protected function assertActivityLogged(
        string $description,
        ?Model $subject = null,
        ?Model $causer = null,
        ?array $properties = null,
        ?Tenant $tenant = null
    ): void {
        $query = Activity::query()
            ->where('description', $description);

        if ($subject) {
            $query->where('subject_type', get_class($subject))
                ->where('subject_id', $subject->getKey());
        }

        if ($causer) {
            $query->where('causer_type', get_class($causer))
                ->where('causer_id', $causer->getKey());
        }

        if ($properties) {
            foreach ($properties as $key => $value) {
                $query->where("properties->{$key}", $value);
            }
        }

        if ($tenant) {
            $query->where('tenant_id', $tenant->id);
        }

        $this->assertTrue(
            $query->exists(),
            "Failed asserting that activity '{$description}' was logged."
        );
    }

    /**
     * Assert that an activity was not logged.
     */
    protected function assertActivityNotLogged(
        string $description,
        ?Model $subject = null,
        ?Model $causer = null,
        ?array $properties = null,
        ?Tenant $tenant = null
    ): void {
        $query = Activity::query()
            ->where('description', $description);

        if ($subject) {
            $query->where('subject_type', get_class($subject))
                ->where('subject_id', $subject->getKey());
        }

        if ($causer) {
            $query->where('causer_type', get_class($causer))
                ->where('causer_id', $causer->getKey());
        }

        if ($properties) {
            foreach ($properties as $key => $value) {
                $query->where("properties->{$key}", $value);
            }
        }

        if ($tenant) {
            $query->where('tenant_id', $tenant->id);
        }

        $this->assertFalse(
            $query->exists(),
            "Failed asserting that activity '{$description}' was not logged."
        );
    }

    /**
     * Assert the number of activities logged.
     */
    protected function assertActivityCount(int $count, ?Tenant $tenant = null): void
    {
        $query = Activity::query();

        if ($tenant) {
            $query->where('tenant_id', $tenant->id);
        }

        $this->assertEquals(
            $count,
            $query->count(),
            "Failed asserting that {$count} activities were logged."
        );
    }

    /**
     * Assert that activities were logged in order.
     *
     * @param array<string> $descriptions
     */
    protected function assertActivitiesInOrder(array $descriptions, ?Tenant $tenant = null): void
    {
        $query = Activity::query()->orderBy('id');

        if ($tenant) {
            $query->where('tenant_id', $tenant->id);
        }

        $activities = $query->pluck('description');

        $this->assertEquals(
            $descriptions,
            $activities->toArray(),
            'Failed asserting that activities were logged in the expected order.'
        );
    }

    /**
     * Assert that a model has activities.
     */
    protected function assertModelHasActivities(Model $model, int $count = null): void
    {
        $query = Activity::query()
            ->where('subject_type', get_class($model))
            ->where('subject_id', $model->getKey());

        if ($count !== null) {
            $this->assertEquals(
                $count,
                $query->count(),
                "Failed asserting that model has {$count} activities."
            );
        } else {
            $this->assertTrue(
                $query->exists(),
                'Failed asserting that model has activities.'
            );
        }
    }

    /**
     * Assert that a model has no activities.
     */
    protected function assertModelHasNoActivities(Model $model): void
    {
        $this->assertFalse(
            Activity::query()
                ->where('subject_type', get_class($model))
                ->where('subject_id', $model->getKey())
                ->exists(),
            'Failed asserting that model has no activities.'
        );
    }

    /**
     * Assert that a user has caused activities.
     */
    protected function assertUserHasActivities(User $user, int $count = null): void
    {
        $query = Activity::query()
            ->where('causer_type', User::class)
            ->where('causer_id', $user->id);

        if ($count !== null) {
            $this->assertEquals(
                $count,
                $query->count(),
                "Failed asserting that user has {$count} activities."
            );
        } else {
            $this->assertTrue(
                $query->exists(),
                'Failed asserting that user has activities.'
            );
        }
    }

    /**
     * Assert that a user has no activities.
     */
    protected function assertUserHasNoActivities(User $user): void
    {
        $this->assertFalse(
            Activity::query()
                ->where('causer_type', User::class)
                ->where('causer_id', $user->id)
                ->exists(),
            'Failed asserting that user has no activities.'
        );
    }

    /**
     * Assert that a tenant has activities.
     */
    protected function assertTenantHasActivities(Tenant $tenant, int $count = null): void
    {
        $query = Activity::query()->where('tenant_id', $tenant->id);

        if ($count !== null) {
            $this->assertEquals(
                $count,
                $query->count(),
                "Failed asserting that tenant has {$count} activities."
            );
        } else {
            $this->assertTrue(
                $query->exists(),
                'Failed asserting that tenant has activities.'
            );
        }
    }

    /**
     * Assert that a tenant has no activities.
     */
    protected function assertTenantHasNoActivities(Tenant $tenant): void
    {
        $this->assertFalse(
            Activity::query()
                ->where('tenant_id', $tenant->id)
                ->exists(),
            'Failed asserting that tenant has no activities.'
        );
    }

    /**
     * Get the latest activity.
     */
    protected function getLatestActivity(?Tenant $tenant = null): ?Activity
    {
        $query = Activity::query()->latest('id');

        if ($tenant) {
            $query->where('tenant_id', $tenant->id);
        }

        return $query->first();
    }

    /**
     * Get activities for a model.
     *
     * @return Collection<int, Activity>
     */
    protected function getActivitiesForModel(Model $model): Collection
    {
        return Activity::query()
            ->where('subject_type', get_class($model))
            ->where('subject_id', $model->getKey())
            ->get();
    }

    /**
     * Get activities for a user.
     *
     * @return Collection<int, Activity>
     */
    protected function getActivitiesForUser(User $user): Collection
    {
        return Activity::query()
            ->where('causer_type', User::class)
            ->where('causer_id', $user->id)
            ->get();
    }

    /**
     * Get activities for a tenant.
     *
     * @return Collection<int, Activity>
     */
    protected function getActivitiesForTenant(Tenant $tenant): Collection
    {
        return Activity::query()
            ->where('tenant_id', $tenant->id)
            ->get();
    }
}
