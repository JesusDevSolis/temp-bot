<?php

namespace App\Http\Controllers;

use App\Models\BitrixInstance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BitrixInstanceSetupController extends Controller
{
    /**
     * Registra o actualiza la configuración de una instancia Bitrix.
     * Espera recibir: portal, client_id, client_secret, auth_token
     */
    public function setup(Request $request)
    {
        $validated = $request->validate([
            'portal'        => 'required|string|max:255',
            'client_id'     => 'required|string|max:255',
            'client_secret' => 'required|string|max:255',
            'auth_token'    => 'required|string|max:255',
        ]);

        $instance = BitrixInstance::updateOrCreate(
            ['portal' => $validated['portal']],
            [
                'client_id'     => $validated['client_id'],
                'client_secret' => $validated['client_secret'],
                'auth_token'    => $validated['auth_token'],
            ]
        );

        Log::info('[BitrixInstanceSetup] Instancia configurada correctamente', [
            'portal' => $instance->portal,
        ]);

        return response()->json([
            'status' => 'OK',
            'message' => 'Instancia Bitrix registrada correctamente.',
            'data' => $instance,
        ]);
    }
}
