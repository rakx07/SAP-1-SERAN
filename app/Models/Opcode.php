<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Opcode extends Model
{
    protected $fillable = ['name', 'value']; // <-- This is important
}
