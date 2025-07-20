<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExecutionLog extends Model
{
    protected $fillable = [
        'pc_address',
        'active_controller',
        'step',
        'description',
    ];
}
