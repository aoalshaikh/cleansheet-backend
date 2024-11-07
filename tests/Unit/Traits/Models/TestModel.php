<?php

namespace Tests\Unit\Traits\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class TestModel extends Model
{
    use LogsActivity;

    protected $table = 'test_models';
    protected $guarded = [];
}
