<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BitrixConversationThread extends Model
{
    protected $fillable = [
        'uid',
        'bitrix_session_id',
        'node_id',
        'user_message',
        'ai_response',
        'is_answered',
        'thread_id',
    ];
}