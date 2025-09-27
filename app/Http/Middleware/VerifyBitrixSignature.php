<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Models\BitrixInstance;

class VerifyBitrixSignature
{
    /**
     * Verifica que la petición sea válida comparando:
     * - Bearer Token (auth_token) del header con el registrado en la base de datos.
     * - application_token en el payload, si existe.
     */
    public function handle(Request $request, Closure $next)
    {
        $headerToken = $request->bearerToken(); // Token enviado como Bearer
        $payload     = $request->all();
        $payloadToken = $payload['application_token'] 
                        ?? data_get($payload, 'auth.application_token') 
                        ?? null;

        // Buscar instancia con el auth_token
        $instance = BitrixInstance::where('auth_token', $headerToken)->first();

        $staticValid  = $instance !== null;
        $dynamicValid = $instance && $payloadToken && $payloadToken === $instance->application_token;

        Log::debug('[VerifyBitrixSignature] header='.($headerToken ?: 'nulo')
            .' payloadToken='.($payloadToken ?: 'nulo')
            .' staticValid='.($staticValid ? '1' : '0')
            .' dynamicValid='.($dynamicValid ? '1' : '0'));

        if ($staticValid || $dynamicValid) {
            // Si es válido, añadir instancia al request por si se quiere reutilizar
            $request->merge(['bitrix_instance' => $instance]);
            return $next($request);
        }

        return new JsonResponse(
            ['error' => 'Unauthorized. Token inválido o ausente.'],
            401
        );
    }
}
