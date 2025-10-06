<?php

namespace App\Http\Controllers;

use App\Models\BitrixInstance;
use App\Services\BitrixService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BitrixMarketController extends Controller
{
    /**
     * Instalación desde Bitrix24 Market
     * Endpoint: POST /api/v1.0.0/bitrix/install
     * Este método se ejecuta cuando se instala la app desde el Market
     */
    public function install(Request $request)
    {
        Log::debug('[Bitrix Market Install] Payload recibido del Market', $request->all());

        try {
            // El Market puede enviar datos en diferentes formatos
            $domain = $this->extractDomain($request);
            $auth = $this->extractAuthData($request);

            if (!$domain) {
                Log::error('[Bitrix Market Install] Falta dominio en el payload');
                return response()->json(['error' => 'Domain required'], 400);
            }

            // Generar credenciales automáticamente (tu aplicación las proporciona)
            $credentials = $this->generateCredentials();

            // Crear o actualizar la instancia
            $instance = BitrixInstance::updateOrCreate(
                ['portal' => $domain],
                [
                    'access_token' => $auth['access_token'] ?? null,
                    'refresh_token' => $auth['refresh_token'] ?? null,
                    'application_token' => $auth['application_token'] ?? null,
                    'expires' => isset($auth['expires_in']) 
                        ? Carbon::now()->addSeconds((int)$auth['expires_in'])
                        : null,
                    // Credenciales opcionales (se proporcionarán en la configuración)
                    'client_id' => $credentials['client_id'] ?? null,
                    'client_secret' => $credentials['client_secret'] ?? null,
                    'auth_token' => null, // Se configurará por el usuario en /setup
                    'hash' => 'default', // Hash por defecto del árbol conversacional
                    'enabled' => true,
                    'bot_code' => 'anima_chatbot',
                    'installed_from_market' => true,
                ]
            );

            Log::info('[Bitrix Market Install] Instancia creada/actualizada', [
                'id' => $instance->id,
                'portal' => $instance->portal,
                'from_market' => true
            ]);

            // Registrar bot en Bitrix si tenemos token de acceso
            if ($instance->access_token) {
                $this->registerBotInBitrix($instance);
            }

            // URL de configuración post-instalación
            $setupUrl = $this->generateSetupUrl($instance);

            Log::info('[Bitrix Market Install] Instalación completada exitosamente', [
                'portal' => $domain,
                'setup_url' => $setupUrl
            ]);

            // Respuesta JSON con instrucciones para el usuario
            return response()->json([
                'result' => true,
                'message' => '¡Instalación completada! Para finalizar la configuración, acceda a: ' . $setupUrl,
                'setup_url' => $setupUrl,
                'instructions' => [
                    'es' => 'Bot instalado correctamente. Complete la configuración en: ' . $setupUrl,
                    'en' => 'Bot installed successfully. Complete setup at: ' . $setupUrl,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('[Bitrix Market Install] Error durante la instalación', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all()
            ]);
            
            return response()->json([
                'status' => 'ERROR',
                'error' => 'Installation failed',
                'message' => 'Error durante la instalación. Contacte soporte.'
            ], 500);
        }
    }

    /**
     * Desinstalación desde Market
     * Endpoint: POST /api/v1.0.0/bitrix/uninstall
     */
    public function uninstall(Request $request)
    {
        Log::debug('[Bitrix Market Uninstall] Payload recibido', $request->all());

        try {
            $domain = $this->extractDomain($request);

            if (!$domain) {
                Log::warning('[Bitrix Market Uninstall] Falta dominio');
                return response()->json(['error' => 'Domain required'], 400);
            }

            $instance = BitrixInstance::where('portal', $domain)->first();
            
            if ($instance) {
                // Desactivar pero no eliminar (conservar datos para posible reinstalación)
                $instance->update([
                    'enabled' => false,
                    'uninstalled_at' => now()
                ]);
                
                Log::info('[Bitrix Market Uninstall] Instancia desactivada', [
                    'portal' => $domain,
                    'instance_id' => $instance->id
                ]);
            } else {
                Log::warning('[Bitrix Market Uninstall] Instancia no encontrada', [
                    'portal' => $domain
                ]);
            }

            return response()->json(['status' => 'OK']);

        } catch (\Exception $e) {
            Log::error('[Bitrix Market Uninstall] Error durante desinstalación', [
                'message' => $e->getMessage(),
                'payload' => $request->all()
            ]);
            
            return response()->json(['status' => 'ERROR', 'message' => 'Uninstall failed'], 500);
        }
    }

    /**
     * Página principal de la aplicación (Address en Market)
     * Bitrix envía automáticamente datos del usuario cuando accede
     * Endpoint: GET /api/v1.0.0/bitrix/app
     */
    public function app(Request $request)
    {
        Log::debug('[Bitrix Market App] Request completo', $request->all());

        $domain = $this->extractDomain($request);
        
        if (!$domain) {
            return response('<h1>Error: No se detectó el portal</h1><pre>' . json_encode($request->all(), JSON_PRETTY_PRINT) . '</pre>');
        }

        $instance = BitrixInstance::where('portal', $domain)->first();
        
        if (!$instance) {
            return response('<h1>Instancia no encontrada</h1><p>Portal: ' . $domain . '</p>');
        }

        // En lugar de redirect, renderizar directamente la vista
        return view('bitrix.setup', ['portal' => $domain]);
    }

    /**
     * Extrae el dominio del payload del Market
     */
    private function extractDomain(Request $request): ?string
    {
        // El Market puede enviar el dominio en diferentes lugares
        $domain = $request->input('DOMAIN') 
                ?? $request->input('domain')
                ?? data_get($request->input('auth', []), 'domain');

        if ($domain) {
            // Limpiar el dominio de protocolos y rutas
            $domain = str_replace(['https://', 'http://', '/rest/'], '', $domain);
            $domain = trim($domain, '/');
        }

        return $domain;
    }

    /**
     * Extrae los datos de autenticación OAuth del payload
     */
    private function extractAuthData(Request $request): array
    {
        // Datos OAuth pueden venir en 'auth' o directamente en el payload
        $auth = $request->input('auth', []);
        
        if (empty($auth['access_token'])) {
            $auth = $request->only([
                'access_token', 
                'refresh_token', 
                'expires_in', 
                'application_token'
            ]);
        }

        return $auth;
    }

    /**
     * Genera credenciales automáticamente desde tu aplicación
     * Aquí tú proporcionas las credenciales, no el usuario
     */
    private function generateCredentials(): array
    {
        return [
            'client_id' => config('services.bitrix.default_client_id') ?? Str::uuid()->toString(),
            'client_secret' => config('services.bitrix.default_client_secret') ?? Str::random(32),
            'auth_token' => Str::random(40), // Para validar webhooks de salida
        ];
    }

    /**
     * Registra el bot en Bitrix Open Lines
     */
    private function registerBotInBitrix(BitrixInstance $instance): void
    {
        try {
            $bitrix = app(BitrixService::class)->setPortal($instance->portal);
            $webhookUrl = config('app.url') . '/api/v1.0.0/webhook/bitrix/message';
            
            $registerRes = $bitrix->registerOpenLineBot(
                $instance->bot_code,
                'Chatbot Ánima',
                $webhookUrl
            );

            if (isset($registerRes['result'])) {
                $instance->update(['bot_id' => $registerRes['result']]);
                
                Log::info('[Bitrix Market Install] Bot registrado correctamente', [
                    'bot_id' => $registerRes['result'],
                    'portal' => $instance->portal,
                    'webhook_url' => $webhookUrl
                ]);
            } else {
                Log::warning('[Bitrix Market Install] No se pudo obtener bot_id', [
                    'response' => $registerRes,
                    'portal' => $instance->portal
                ]);
            }
        } catch (\Exception $e) {
            Log::error('[Bitrix Market Install] Error registrando bot', [
                'error' => $e->getMessage(),
                'portal' => $instance->portal
            ]);
            // No lanzamos la excepción para que la instalación continúe
        }
    }

    /**
     * Muestra la página de configuración post-instalación
     * Endpoint: GET /setup?portal=xxx
     */
    public function showSetupPage(Request $request)
    {
        $portal = $request->query('portal');
        
        if (!$portal) {
            return response('Portal requerido', 400);
        }

        $instance = BitrixInstance::where('portal', $portal)->first();
        
        if (!$instance) {
            return response('Instancia no encontrada', 404);
        }

        return view('bitrix.setup', ['portal' => $portal]);
    }

    /**
     * Guarda la configuración adicional
     * Endpoint: POST /setup
     */
    public function saveSetupConfig(Request $request)
    {
        $validated = $request->validate([
            'portal' => 'required|string',
            'webhook_token' => 'nullable|string|max:255',
            'hash' => 'nullable|string|max:255',
        ]);

        $instance = BitrixInstance::where('portal', $validated['portal'])->firstOrFail();

        // Actualizar solo los campos nuevos, sin tocar la funcionalidad existente
        $updateData = [];
        
        if (!empty($validated['webhook_token'])) {
            // Actualizar el auth_token con el webhook_token proporcionado por el usuario
            $updateData['auth_token'] = $validated['webhook_token'];
        }
        
        if (!empty($validated['hash']) && $validated['hash'] !== $instance->hash) {
            $updateData['hash'] = $validated['hash'];
        }

        if (!empty($updateData)) {
            $instance->update($updateData);
            
            Log::info('[Bitrix Market Setup] Configuración actualizada', [
                'portal' => $instance->portal,
                'fields_updated' => array_keys($updateData)
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Configuración guardada correctamente'
        ]);
    }

    /**
     * Genera URL de configuración post-instalación
     */
    private function generateSetupUrl(BitrixInstance $instance): string
    {
        return config('app.url') . '/setup?portal=' . urlencode($instance->portal);
    }
}
