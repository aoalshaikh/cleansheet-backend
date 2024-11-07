<?php

namespace Tests\Unit\Traits\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class TestModelWithCustomDescriptions extends Model
{
    use LogsActivity;

    protected $table = 'test_models';
    protected $guarded = [];

    protected $activityLogDescriptions = [
        'created' => 'Custom created description',
        'updated' => 'Custom updated description',
        'deleted' => 'Custom deleted description',
    ];
}
