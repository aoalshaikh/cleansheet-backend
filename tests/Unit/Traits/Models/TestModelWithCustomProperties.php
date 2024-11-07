<?php

namespace Tests\Unit\Traits\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class TestModelWithCustomProperties extends Model
{
    use LogsActivity;

    protected $table = 'test_models';
    protected $guarded = [];

    protected $activityLogAttributes = [
        'name',
        'description',
        'custom_field',
    ];

    public function getActivityLogProperties(): array
    {
        return [
            'custom_property' => 'custom value',
            'timestamp' => now()->toDateTimeString(),
        ];
    }
}
