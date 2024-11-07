<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Tests\Unit\Traits\TestModel;
use Tests\Unit\Traits\TestModelWithCustomDescriptions;
use Tests\Unit\Traits\TestModelWithCustomLogName;
use Tests\Unit\Traits\TestModelWithCustomProperties;
use Tests\Unit\Traits\TestModelWithDisabledLogging;
use Tests\Unit\Traits\TestModelWithSoftDeletes;

class TestModelFactory extends Factory
{
    protected $model = TestModel::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->words(3, true),
            'secret' => $this->faker->uuid(),
        ];
    }

    public function withoutTenant(): self
    {
        return $this->state(function () {
            return [
                'tenant_id' => null,
            ];
        });
    }

    public function withSecret(string $secret): self
    {
        return $this->state(function () use ($secret) {
            return [
                'secret' => $secret,
            ];
        });
    }

    public function withName(string $name): self
    {
        return $this->state(function () use ($name) {
            return [
                'name' => $name,
            ];
        });
    }

    public function forTenant(Tenant $tenant): self
    {
        return $this->state(function () use ($tenant) {
            return [
                'tenant_id' => $tenant->id,
            ];
        });
    }

    public function deleted(): self
    {
        return $this->state(function () {
            return [
                'deleted_at' => now(),
            ];
        });
    }

    public function asCustomDescriptions(): Factory
    {
        return $this->newFactory(TestModelWithCustomDescriptions::class);
    }

    public function asCustomLogName(): Factory
    {
        return $this->newFactory(TestModelWithCustomLogName::class);
    }

    public function asDisabledLogging(): Factory
    {
        return $this->newFactory(TestModelWithDisabledLogging::class);
    }

    public function asCustomProperties(): Factory
    {
        return $this->newFactory(TestModelWithCustomProperties::class);
    }

    public function asSoftDeletes(): Factory
    {
        return $this->newFactory(TestModelWithSoftDeletes::class);
    }

    protected function newFactory(string $modelClass): Factory
    {
        $factory = new static();
        $factory->model = $modelClass;
        return $factory;
    }
}
