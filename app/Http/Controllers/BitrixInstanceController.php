<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BitrixInstance;
use Illuminate\Support\Facades\Log;

class BitrixInstanceController extends Controller
{
    /**
     * Actualiza el hash conversacional de una instancia Bitrix.
     */
    public function updateHash(Request $request)
    {
        if (!$request->filled('portal') || !$request->filled('hash')) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Faltan parámetros requeridos: portal y hash.'
            ], 400);
        }

        $instance = BitrixInstance::where('portal', $request->portal)->first();
        if (!$instance) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'No se encontró la instancia Bitrix con el portal proporcionado.'
            ], 404);
        }

        $instance->hash = $request->hash;
        $instance->save();

        Log::info('[BitrixInstance] Hash actualizado', [
            'id'     => $instance->id,
            'portal' => $instance->portal,
            'nuevo_hash' => $instance->hash,
        ]);

        return response()->json([
            'status'  => 'OK',
            'message' => 'Hash actualizado correctamente.',
            'data'    => [
                'id'     => $instance->id,
                'portal' => $instance->portal,
                'hash'   => $instance->hash,
            ],
        ]);

    }
}
