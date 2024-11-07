<?php

namespace Tests\Unit\Traits\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TestModelWithSoftDeletes extends Model
{
    use LogsActivity, SoftDeletes;

    protected $table = 'test_models';
    protected $guarded = [];

    protected $activityLogAttributes = [
        'name',
        'description',
        'deleted_at',
    ];
}
