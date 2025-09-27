<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BitrixSession extends Model
{
    protected $fillable = [
        'user_id',
        'chat_id',
        'uid',
        'dialog_id',
        'path_base', 
        'current_node_id',
        'next_node_id',
        'transferred_to_human',
        'status',
        'portal',
        'show_restart_menu_after',
        'op_group_id',
    ];
}
