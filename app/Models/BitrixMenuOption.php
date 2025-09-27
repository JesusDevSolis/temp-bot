<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BitrixMenuOption extends Model
{
    protected $fillable = [
        'uid',
        'options',
        'bitrix_session_id',
        'is_main_menu',
        'node_id',
    ];

    protected $casts = [
        'options' => 'array',
        'is_main_menu' => 'boolean',
    ];
}

