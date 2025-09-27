<?php

namespace App\Services;

use App\Models\BitrixInstance;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BitrixService
{
    // Base URL para llamar a la API REST de Bitrix24 (por ejemplo: https://miportal.bitrix24.mx/rest)
    protected string $baseUrl;
    // Token de acceso OAuth guardado en BD para la instancia de Bitrix
    protected string $authToken;

    protected ?BitrixInstance $instance = null;
    
    public function __construct(string $accessToken = '', string $baseUrl = '')
    {
        if ($accessToken !== '' && $baseUrl !== '') {
            $this->authToken = $accessToken;
            $this->baseUrl   = $baseUrl;
        }
    }

    /**
     * Inicializa el servicio con el portal deseado.
     */
    public function setPortal(string $portal): static
    {
        $instance = BitrixInstance::where('portal', $portal)->firstOrFail();
        $this->instance = $instance;
        $this->authToken = $instance->access_token;
        $this->baseUrl = "https://{$portal}/rest";
        return $this;
    }

    /**
     * Llamada genérica a un método de la API REST de Bitrix.
     *
     * @param string $method  Nombre del método, ej. "imbot.register"
     * @param array  $params  Parámetros adicionales que exige el método
     * @return array          JSON decodificado de la respuesta
     */
    public function call(string $method, array $params = []): array
    {
        // Cada método se invoca en: {baseUrl}/{method}.json
        // Ejemplo: https://miportal.bitrix24.mx/rest/imbot.register.json
        // El primer parámetro es el token de acceso (auth) y el resto son los parámetros
        // que exige el método. Se envían como un array asociativo.
        // La respuesta es un JSON que se decodifica automáticamente
        $url = "{$this->baseUrl}/{$method}.json";

        try{
            // 1) Hacemos la petición POST con el token OAuth en el body y en la cabecera
            $response = Http::timeout(120)       // tiempo máximo total
                    ->withOptions([
                        'connect_timeout' => 30, // tiempo máximo para conectar
                    ])
                    ->asForm()
                    ->post($url, array_merge([
                        'auth' => $this->authToken,
                    ], $params));

            // 2) Si recibimos 401, reintentar una vez tras refrescar token
            if ($response->status() === 401) {
                // refrescar token
                $this->refreshAccessToken();
                // volver a intentar
                $response = Http::timeout(120)
                        ->withOptions([
                            'connect_timeout' => 30,
                        ])
                        ->asForm()
                        ->post($url, array_merge([
                            'auth' => $this->authToken,
                        ], $params));
            }

            // 3) Lanzar excepción si sigue siendo error HTTP
            $response->throw();

            // 4) Devolver siempre un array (decode JSON)
            $json = $response->json();
            return is_array($json) ? $json : ['status'=>'ERROR','error'=>'Invalid JSON'];
        }catch (\Illuminate\Http\Client\RequestException $e) {
            // Si la respuesta incluye JSON de error, devolverla
            if ($e->response && $e->response->header('Content-Type') === 'application/json') {
                return $e->response->json();
            }
            // Fallback genérico
            return [
                'status'  => 'ERROR',
                'error'   => 'HTTP '.$e->response?->status(),
                'message' => $e->getMessage(),
            ];
        }
        
    }

    /**
     * Registra un bot como Open Line Bot (imbot.register TYPE='O').
     * Este bot recibirá eventos de Open Lines (mensajes entrantes, bienvenida, etc.).
     *
     * @param string $code       Código único interno del bot (ej. "anima_bot")
     * @param string $name       Nombre visible en la UI de Bitrix (cuando exista)
     * @param string $webhookUrl URL a la que Bitrix enviará eventos de chat
     * @return array             Resultado de la llamada API
     */
    public function registerOpenLineBot(string $code, string $name, string $webhookUrl): array
    {
        return $this->call('imbot.register', [
            'CODE'                  => $code,
            'TYPE'                  => 'O',             // 'O' = Open Line Bot
            'EVENT_MESSAGE_ADD'     => $webhookUrl,     // webhook para nuevos mensajes
            'EVENT_WELCOME_MESSAGE' => $webhookUrl,     // webhook para mensaje de bienvenida
            'EVENT_BOT_DELETE'      => $webhookUrl,     // webhook cuando se elimina el bot
            'EVENT_MESSAGE_UPDATE'  => $webhookUrl,     // webhook al editar mensaje
            'EVENT_MESSAGE_DELETE'  => $webhookUrl,     // webhook al eliminar mensaje
            'OPENLINE'              => 'Y',             // marca este bot como Open Line
            'PROPERTIES'            => ['NAME' => $name],
        ]);
    }

    /**
     * (Opcional) Marca un bot existente como Open Line Bot.
     * Útil si ya tenías un bot registrado y sólo quieres activar Open Lines.
     * Este método no es necesario si ya lo registraste como Open Line Bot.
     * Si el bot ya es un Open Line Bot, no hace nada.
     * Si el bot no existe, lanza una excepción.
     * Si el bot no es un Open Line Bot, lo convierte en uno.
     *
     * @param int $botId  ID del bot en Bitrix (devuelto por imbot.register)
     * @return array      Resultado de la llamada API
     */
    public function updateBot(int $botId): array
    {
        return $this->call('imbot.update', [
            'BOT_ID'   => $botId,
            'OPENLINE' => 'Y',
        ]);
    }

    /**
     * Envía un mensaje desde el bot a un diálogo de Open Lines.
     *  @param string    $dialogId  ID de diálogo (DIALOG_ID)
     * @param string $message   Texto a enviar
     * @return array            Respuesta de Bitrix
     */
    public function sendBotMessage(string $dialogId, string $message): array
    {
        $text = $this->sanitizeHtmlBitrix($message);
        return $this->call('imbot.message.add', [
            'DIALOG_ID' => $dialogId,
            'MESSAGE' => $text,
        ]);
    }

    /**
     * Refresca el access_token usando el refresh_token y actualiza la BD.
     */
    public function refreshAccessToken(): bool
    {
        // 1) Cargar la instancia de este portal
        $host = parse_url($this->baseUrl, PHP_URL_HOST);
        $instance = \App\Models\BitrixInstance::where('portal', $host)->first();
        if (! $instance || ! $instance->refresh_token) {
            Log::error('[BitrixService] No hay refresh_token disponible para portal '.$host);
            return false;
        }

        // 2) Llamada al endpoint OAuth de Bitrix para renovar token
        $tokenUrl = "https://{$host}/oauth/token/";

        Log::debug('[BitrixService] Llamando a refresh en', ['url' => $tokenUrl]);

        $res = Http::asForm()->post($tokenUrl, [
            'grant_type'    => 'refresh_token',
            'client_id'     => $instance->client_id,
            'client_secret' => $instance->client_secret,
            'refresh_token' => $instance->refresh_token,
        ]);

        $data = $res->json();
        Log::debug('[BitrixService] Respuesta completa del endpoint OAuth', [
            'status' => $res->status(),
            'body' => $data,
        ]);

        // 3) Verificar que realmente tengamos un nuevo access_token
        if (! isset($data['access_token']) || ! isset($data['expires_in'])) {
            Log::error('[BitrixService] Falló refresh, payload recibido:', $data);
            return false;
        }

        // 4) Actualizar BD y propiedad local
        // $instance->access_token  = $data['access_token'];
        // $instance->refresh_token = $data['refresh_token'] ?? $instance->refresh_token;
        // $instance->expires       = now()->addSeconds($data['expires_in']);
        // $instance->save();
        $instance->update([
            'access_token'  => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $instance->refresh_token,
            'expires'       => now()->addSeconds($data['expires_in']),
        ]);

        $this->authToken = $instance->access_token;
        Log::debug('[BitrixService] Token refrescado exitosamente');
        return true;
    }

    public function sendBotImage(string $dialogId, string $imageUrl, string $altText = ''): array
    {
        return $this->call('imbot.message.add', [
            'DIALOG_ID' => $dialogId,
            'MESSAGE' => $altText
                ? "📷 Ver imagen:\n\n {$imageUrl}"
                : $imageUrl,
        ]);
    }

    /**
     * Envía un mensaje de audio al chat.
     *
     * @param string $dialogId
     * @param string $audioUrl
     * @return array|null
     */
    public function sendBotAudio(string $dialogId, string $audioUrl): ?array
    {
        return $this->sendBotMessage($dialogId, "\n{$audioUrl}");
    }

    /**
     * Retorna el client_id de la instancia actual.
     */
    public function getClientId(): ?string
    {
        return $this->instance?->client_id;
    }

    /**
     * Retorna el client_secret de la instancia actual.
     */
    public function getClientSecret(): ?string
    {
        return $this->instance?->client_secret;
    }

    /**
     * Retorna el auth_token estático para validar Webhooks.
     */
    public function getStaticAuthToken(): ?string
    {
        return $this->instance?->auth_token;
    }

    private function sanitizeHtmlBitrix(string $html): string
    {
        // Convertimos etiquetas HTML a texto plano con saltos de línea
        $html = str_ireplace(['<strong>', '</strong>'], ['*', '*'], $html);
        $html = str_ireplace(['<em>', '</em>'], ['_', '_'], $html);
        $html = str_ireplace(['<b>', '</b>'], ['*', '*'], $html);
        $html = str_ireplace(['<i>', '</i>'], ['_', '_'], $html);
        $html = str_ireplace(['<br>', '<br/>', '<br />', '</p>', '</div>'], "\n", $html);
        $html = str_ireplace(['<p>', '<div>'], '', $html);

        // Eliminamos cualquier etiqueta restante
        return trim(strip_tags(html_entity_decode($html)));
    }

    public function getConfigIdForBot(int $botId): ?int
    {
        $response = $this->call('imopenlines.config.get', ['CONFIG_ID' => 1]);

        $index = 1;
        while (true) {
            $response = $this->call('imopenlines.config.get', ['CONFIG_ID' => $index]);

            if (!isset($response['result'])) {
                break;
            }

            $config = $response['result'];

            if (isset($config['WELCOME_BOT_ID']) && (int)$config['WELCOME_BOT_ID'] === $botId) {
                return (int)$config['ID'];
            }

            $index++;
        }

        return null;
    }

    /**
    * Marca la conversación como finalizada en el canal abierto de Bitrix.
    */
    public function closeOpenlineChat(string $dialogId): array
    {
        return $this->call('imbot.chat.finish', [
            'DIALOG_ID' => $dialogId,
        ]);
    }

    /**
     * Finaliza una sesión de Open Line (tipo bot) usando el chat_id.
     */
    public function finishSessionWithChatId(int $chatId): array
    {
        return $this->call('imopenlines.bot.session.finish', [
            'CHAT_ID' => $chatId,
        ]);
    }
}
