<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BitrixSession;
use Illuminate\Support\Facades\Log;

class BitrixManualTransferController extends Controller
{
    public function transfer(Request $request)
    {
        $validated = $request->validate([
            'chat_id' => 'required|string',
            'user_id' => 'required|string',
        ]);

        $session = BitrixSession::where([
            'chat_id' => $validated['chat_id'],
            'user_id' => $validated['user_id'],
        ])->first();

        if (! $session) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sesión no encontrada.'
            ], 404);
        }

        $session->update([
            'transferred_to_human' => true
        ]);

        Log::info('[Manual Transfer] Bot transferido manualmente a humano', [
            'chat_id' => $validated['chat_id'],
            'user_id' => $validated['user_id']
        ]);

        return response()->json([
            'status' => 'ok',
            'transferred_to_human' => true
        ]);
    }
}
