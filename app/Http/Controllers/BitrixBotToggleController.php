<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BitrixInstance;
use Illuminate\Support\Facades\Log;

class BitrixBotToggleController extends Controller
{
    public function toggle(Request $request)
    {
        $validated = $request->validate([
            'portal' => 'required|string',
            'enabled' => 'required|boolean',
        ]);

        $instance = BitrixInstance::where('portal', $validated['portal'])->first();

        if (! $instance) {
            return response()->json([
                'status' => 'error',
                'message' => 'BitrixInstance no encontrada para el portal especificado.'
            ], 404);
        }

        $instance->update([
            'enabled' => $validated['enabled']
        ]);

        Log::info('[Bitrix API] Bot actualizado', [
            'portal' => $validated['portal'],
            'enabled' => $validated['enabled']
        ]);

        return response()->json([
            'status' => 'ok',
            'enabled' => $validated['enabled']
        ]);
    }

    public function status(Request $request)
    {
        $request->validate([
            'portal' => 'required|string',
        ]);

        $instance = \App\Models\BitrixInstance::where('portal', $request->portal)->first();

        if (!$instance) {
            return response()->json([
                'status' => 'error',
                'message' => 'BitrixInstance no encontrada para el portal especificado.'
            ], 404);
        }

        return response()->json([
            'portal' => $instance->portal,
            'enabled' => $instance->enabled,
        ]);
    }

}
