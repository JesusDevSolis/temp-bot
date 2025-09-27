<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BitrixUserInput extends Model
{
    protected $fillable = [
        'uid',
        'node_id',
        'value',
    ];
}
