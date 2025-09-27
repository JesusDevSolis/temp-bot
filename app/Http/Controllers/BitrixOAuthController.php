<?php

namespace App\Http\Controllers;

use App\Models\BitrixInstance;
use App\Services\BitrixService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class BitrixOAuthController extends Controller
{
    /**
     * Install: recibe el payload de instalación de Bitrix24
     * Se ejecuta cuando el administrador instala o reintala la aplicación en su portal (Bitrix24).
     */
    public function install(Request $request)
    {
        // Log: El contenido del $request que se recibe
        Log::debug('[Bitrix Install] Entrada al método install()', $request->all());

        // PASO 1: Si viene un código de autorización, obtener los tokens desde Bitrix
        if ($request->has('code') && $request->has('domain')) {
            $code = $request->input('code');
            $domain = $request->input('domain');
            Log::debug('[Bitrix Install] Código de autorización recibido', compact('code', 'domain'));
            // Obtener client_id y client_secret desde BD por dominio
            $instance = BitrixInstance::where('portal', $domain)->first();
            if (! $instance || ! $instance->client_id || ! $instance->client_secret) {
                Log::error('[Bitrix Install] No se encontró client_id o client_secret para el dominio', compact('domain'));
                return response()->json(['error' => 'Credenciales no configuradas para este portal'], 400);
            }

            // Construir URL para solicitar los tokens a Bitrix
            $tokenResponse = Http::asForm()->post("https://oauth.bitrix.info/oauth/token/", [
                'grant_type' => 'authorization_code',
                'client_id' => $instance->client_id,
                'client_secret' => $instance->client_secret,
                'code' => $code,
                'redirect_uri' => config('services.bitrix.redirect_uri'),
            ]);

            if (! $tokenResponse->ok()) {
                Log::error('[Bitrix Install] Error al obtener tokens desde Bitrix', [
                    'response' => $tokenResponse->body()
                ]);
                return response()->json(['error' => 'No se pudieron obtener tokens de Bitrix'], 400);
            }

            // Sustituir $auth con la respuesta obtenida
            $auth = $tokenResponse->json();
            Log::debug('[Bitrix Install] Tokens recibidos desde Bitrix', $auth);

            // Continuamos como si hubieran llegado directamente en el payload
            $request->merge([
                'access_token' => $auth['access_token'] ?? null,
                'refresh_token' => $auth['refresh_token'] ?? null,
                'expires_in' => $auth['expires_in'] ?? null,
                'application_token' => $auth['application_token'] ?? null,
                'domain' => $domain,
            ]);
        }


        // Extraer credenciales de OAuth del request.
        //    Bitrix puede enviar { auth: { access_token… } }.
        $auth = $request->input('auth', []);

        if (empty($auth['access_token'])) {
            // Payload plano: tomar campos directamente del request que se recibe de Bitrix
            $auth = [
                'access_token'  => $request->input('access_token'),
                'refresh_token' => $request->input('refresh_token'),
                'expires_in'    => $request->input('expires_in'),
                'domain'        => $request->input('domain'),
                'application_token'  => $request->input('application_token'),
            ];
            Log::debug('[Bitrix Install] Payload plano detectado', $auth);
        } else {
            // Payload anidado bajo "auth", cuando ya existe un token
            // de acceso en el portal de Bitrix24.
            // Payload anidado (auth: {...})
            $auth = [
                'access_token'       => $auth['access_token']       ?? null,
                'refresh_token'      => $auth['refresh_token']      ?? null,
                'expires_in'         => $auth['expires_in']         ?? null,
                'domain'             => $auth['domain']             ?? null,
                'application_token'  => $auth['application_token']  ?? null,
            ];
            Log::debug('[Bitrix Install] Payload anidado detectado', $auth);
        }

        // Validar que tengamos token y dominio para continuar
        //    Si no hay token o dominio, devuelve msj de error.
        if (empty($auth['access_token']) || empty($auth['domain'])) {
            Log::warning('[Bitrix Install] Faltan datos', [
                'auth' => $auth,
                'domain' => $auth['domain'] ?? null,
            ]);
            return response()->json(['error' => 'Faltan access_token o domain'], 400);
        }

        //    Calcular TTL y guardar (o actualizar) la instancia en BD en la tabla bitrix_instances
        //    Se guarda el token de acceso, el token de refresco y la fecha de expiración.
        //    Se usa Carbon para calcular la fecha de expiración.
        $ttl = (int)($auth['expires_in'] ?? 0);
        $instance = BitrixInstance::updateOrCreate(
            ['portal' => $auth['domain']],
            [
                'access_token'  => $auth['access_token'],
                'refresh_token' => $auth['refresh_token'] ?? null,
                'expires'       => Carbon::now()->addSeconds($ttl),
                'application_token'  => $auth['application_token'] ?? null,
            ]
        );
        Log::debug('[Bitrix Install] Registro creado/actualizado en BD', [
            'id'        => $instance->id,
            'portal'    => $instance->portal,
            'expiresAt' => $instance->expires->toDateTimeString(),
        ]);

        // Registrar el bot en Open Lines de Bitrix
        try {
            // Servicio que envuelve llamadas a la REST API de Bitrix24
            // Se usa para registrar el bot en Open Lines.
            $bitrix = app(BitrixService::class)->setPortal($auth['domain']);
            // URL pública de webhook para recibir mensajes luego de la instalación.
            // Se usa el webhook_url de la configuración de servicios.
            // Se debe crear un webhook en el portal de Bitrix24 para recibir mensajes.
            $webhookUrl  = config('services.bitrix.webhook_url') . '/api/v1.0.0/webhook/bitrix/message';
            // Registrar bot tipo "Open Line" en Bitrix24
            // Se usa el nombre del bot y el nombre de la línea de Open Line.
            $registerRes = $bitrix->registerOpenLineBot(
                'chatbot_Anima',  // Código único del bot
                'Chatbot Anima', // Nombre del bot
                $webhookUrl
            );
            Log::debug('[Bitrix Install] imbot.register response', $registerRes);

            // 6) Guardar el bot_id que devolvió Bitrix en la tabla bitrix_instances
            $instance->bot_id = $registerRes['result'] ?? null;
            $instance->bot_code = 'chatbot_Anima';
            $instance->save();
        } catch (\Throwable $e) {
            // Capturar fallos en el registro del bot en Open Lines
            // y log del error.
            Log::error('[Bitrix Install] Error registrando OpenLine bot', [
                'message' => $e->getMessage()
            ]);
        }
        // 7) Responder a Bitrix que la instalación fue exitosa
        return response()->json(['status' => 'OK'], 200);
    }

    /**
     * Callback de OAuth (authorization_code → tokens)
     * Se invoca cuando el usuario autoriza la app y Bitrix redirige con ?code=
     * a la URL de callback configurada en el portal de Bitrix.
     * Se obtiene el access_token y refresh_token para la instancia de Bitrix.
     */
    public function callback(Request $request)
    {
        //  Validación: Se asegura de recibir el "code" de autorización
        //    que envía Bitrix al redirigir a la URL de callback.
        //    Si no hay "code", devuelve un error 400.
        //    El "code" es un código de autorización que se usa para obtener
        //    el access_token y refresh_token.
        $code = $request->query('code');
        if (! $code) {
            return response('Missing code', 400);
        }
        
        // Buscar instancia temporal usando portal almacenado en session o parámetro adicional si fuera necesario
        // Suponemos que Bitrix redirige con domain en el query (o usar un valor fijo si solo hay un portal)
        $domain = $request->query('domain');

        if (!$domain) {
            return response('Missing domain', 400);
        }

        $instance = BitrixInstance::where('portal', $domain)->first();

        if (!$instance) {
            return response("Portal '{$domain}' no registrado", 404);
        }

        $res = Http::asForm()->post('https://oauth.bitrix.info/oauth/token', [
            'grant_type'    => 'authorization_code',
            'client_id'     => $instance->client_id,
            'client_secret' => $instance->client_secret,
            'code'          => $code,
            'redirect_uri'  => config('app.url') . '/api/v1.0.0/bitrix/oauth/callback',
        ]);

        //  Lanzar excepción si hay error HTTP, luego obtener JSON
        $data = $res->throw()->json();

        Log::debug('[Bitrix OAuth Callback] Token response', $data);

        $ttl  = (int)($data['expires_in'] ?? 0);

        // Actualizar o crear la instancia con los nuevos tokens y expiración
        //    Se usa el dominio del portal como clave única.
        //    Se guarda el access_token, refresh_token y la fecha de expiración.
        BitrixInstance::updateOrCreate(
            ['portal' => $data['domain']],
            [
                'access_token'  => $data['access_token'],
                'refresh_token' => $data['refresh_token'],
                'expires'       => Carbon::now()->addSeconds($ttl),
                'application_token'  => $data['application_token'] ?? null,
            ]
        );
        // Redirigir al dashboard de la app con el token de acceso
        // dashboard cuando la instalación fue exitosa y cuando sea desarrollada.
        return redirect()->away(config('app.url').'/dashboard');
    }

    /**
     * Uninstall: Bitrix envía una llamada aquí cuando el administrador
     * desinstala la aplicación desde su portal. Se marca la instancia como inactiva.
     */
    public function uninstall(Request $request)
    {
        Log::debug('[Bitrix Uninstall] Payload recibido', $request->all());

        $domain = $request->input('domain');
        $applicationToken = $request->input('application_token');

        if (!$domain || !$applicationToken) {
            Log::warning('[Bitrix Uninstall] Faltan parámetros', compact('domain', 'applicationToken'));
            return response()->json(['error' => 'Faltan domain o application_token'], 400);
        }

        // Buscar la instancia con el dominio y application_token
        $instance = BitrixInstance::where('portal', $domain)
            ->where('application_token', $applicationToken)
            ->first();

        if (!$instance) {
            Log::warning('[Bitrix Uninstall] No se encontró la instancia', compact('domain'));
            return response()->json(['error' => 'Instancia no encontrada'], 404);
        }

        // Marcar como desactivada
        $instance->enabled = 0;
        $instance->save();

        Log::info('[Bitrix Uninstall] Instancia desactivada correctamente', [
            'portal' => $instance->portal,
            'enabled' => $instance->enabled,
        ]);

        return response()->json(['status' => 'OK'], 200);
    }
}
