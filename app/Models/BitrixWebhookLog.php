<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BitrixWebhookLog extends Model
{
    use HasFactory;

    protected $table = 'bitrix_webhook_logs';

    protected $fillable = [
        'portal',
        'payload',
        'dialog_id',
        'response',
        'success',
    ];

    protected $casts = [
        'payload'  => 'array',
        'response' => 'array',
        'success'  => 'boolean',
    ];
}
