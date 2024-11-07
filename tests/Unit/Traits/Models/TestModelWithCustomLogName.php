<?php

namespace Tests\Unit\Traits\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class TestModelWithCustomLogName extends Model
{
    use LogsActivity;

    protected $table = 'test_models';
    protected $guarded = [];

    protected $activityLogName = 'custom_log';
}
