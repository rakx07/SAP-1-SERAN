<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Memory extends Model
{
    protected $table = 'memory'; // 👈 this line is required
    protected $fillable = ['address', 'instruction', 'value'];
}
