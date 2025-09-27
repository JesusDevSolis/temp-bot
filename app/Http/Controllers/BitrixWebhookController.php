<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use App\Models\BitrixInstance;

use App\Services\AnimaService;
use App\Services\BitrixService;
use App\Services\Bitrix\AnimaConnectorService;
use App\Services\Bitrix\BitrixFlowEngine;
use App\Services\Bitrix\BitrixLogService;
use App\Services\Bitrix\BitrixSessionHelper;
use App\Services\Bitrix\BitrixWebhookNormalizer;

class BitrixWebhookController extends Controller
{
    /**
     * Maneja eventos entrantes de Bitrix (mensajes).
     * Este método es el único webhook que Bitrix Open Lines invoca
     * cuando el usuario o el bot envían un mensaje.
     */
    public function handle(Request $request)
    {
        // 1. Captura el payload
        $payload = $request->all();
        $portal = data_get($payload, 'auth.domain')
            ?? data_get($payload, 'auth[domain]')
            ?? data_get($payload, 'data.BOT.13.client_endpoint');

        if ($portal) {
            $portal = str_replace(['https://', '/rest/'], '', $portal);
        }
        // 2. Registrar log Inicial
        $logService = new BitrixLogService();
        $log = $logService->createInitial($portal, $payload);
        
        // 3. Normalizar entrada (real o prueba)
        $normalizer = new BitrixWebhookNormalizer($payload);
        $normalizer->debug(); // para registrar logs de entrada y salida
        $normalizer->ensureBitrixInstanceCompleta();
        extract($normalizer->get()); // crea $userId, $chatId, $dialogId, $message, $channelId automáticamente
        // Log::debug('[Bitrix Webhook] Payload normalizado', compact('userId','chatId','dialogId','message','channelId'));

        // 4. Validar campos obligatorios
        if (! $userId || ! $chatId || ! $message) {
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        try {
            // Inicializar el servicio que invoca Ánima
            $anima = new AnimaService();
            $chatBotKey = '1001'. $chatId;
            $userId    = 'user-b'.(string) $userId;
            $attention_group = (string) $chatId;
            $tag = '#soporte';

            // flujo: connect → asegura/el crea el OpGroup ->flujo que se hace en animawebsocket
            $connector = new AnimaConnectorService();
            $opGroupId = $connector->connectUser([
                'chatbot_key'     => $chatBotKey,
                'attention_group' => $attention_group,
                'user_id'         => $userId,
                'portal'          => $portal,
                'tag'             => $tag,
                'origen'          => 'bitrix',
                'channel_id'      => $channelId,
            ]);

            $reply = $anima->sendMessage([
                'type'       => 'user',
                'userId'     => $userId,
                'message'    => $message,
                'origen'     => 'bitrix',
            ]);

            // Si Ánima devolvió un error, reenvíalo tal cual
            if (isset($reply['status']) && $reply['status'] === 'ERROR') {
                Log::error('[Bitrix Webhook] Error en respuesta de Ánima', [
                    'reply' => $reply,
                    'userId' => $userId,
                    'chatId' => $chatId,
                    'dialogId' => $dialogId,
                ]);
                return response()->json($reply, 200);
            }

            // 5. Obtener instancia por portal
            $bitrixInstance = BitrixInstance::firstOrCreate(
                ['portal' => $portal]
            );

            $hash = $bitrixInstance->hash;

            // Validar si está habilitado
            if (!$bitrixInstance->enabled) {
                Log::info('[Bitrix Webhook] Bot desactivado para este canal', ['portal' => $portal]);
                return response()->json([
                    'reply' => 'Este bot está temporalmente desactivado.',
                    'transfer_to_human' => true,
                ]);
            }
            
            // Si no está guardado el hash aún, lo actualizamos
            if (!$bitrixInstance->hash && $hash) {
                $bitrixInstance->update(['hash' => $hash]);
            }

            // Guardar o actualizar channel_id
            if ($channelId) {
                if ($bitrixInstance->channel_id !== $channelId) {
                    $bitrixInstance->update(['channel_id' => $channelId]);
                    Log::info('[Bitrix Webhook] channel_id guardado/actualizado en bitrix_instances', [
                        'channel_id' => $channelId,
                        'portal' => $portal,
                    ]);
                } 
            } else {
                Log::warning('[Bitrix Webhook] channel_id no recibido en payload de Bitrix', [
                    'payload' => $payload,
                ]);
            }

            $bitrixSession = BitrixSessionHelper::loadOrCreate($userId, $chatId, $bitrixInstance, $opGroupId);
            BitrixSessionHelper::ensureUid($bitrixSession, $bitrixInstance);

            // Guardamos el dialog_id si aún no está definido
            if (!$bitrixSession->dialog_id && !empty($dialogId)) {
                $bitrixSession->dialog_id = $dialogId;
                $bitrixSession->save();

                Log::info('[BitrixWebhookController] dialog_id guardado en bitrix_sessions', [
                    'uid' => $bitrixSession->uid,
                    'dialog_id' => $dialogId,
                ]);
            }

            $uid = $bitrixSession->uid;
            // Log::debug('[Bitrix Webhook] UID obtenido desde sesión', ['uid' => $uid]);

            //crear instancia de servicio y setear el portal
            $bitrix = (new BitrixService())->setPortal($portal);

            $bitrixOperatorService = app(\App\Services\Bitrix\BitrixOperatorService::class);

            $engine = new BitrixFlowEngine(
                session: $bitrixSession,
                bitrix: $bitrix,
                bitrixOperatorService: $bitrixOperatorService,
                dialogId: $dialogId,
                hash: $bitrixInstance->hash
            );

            // Procesar el flujo completo y obtener la respuesta
            $respuesta = $engine->processUserMessage($message);

            // 9. Enviar mensaje a Ánima (operador-bot)
            $anima->sendMessage([
                'type'    => 'op',
                'userId'  => $userId,
                'message' => $respuesta['reply'] ?? null,
                'origen'  => 'bitrix',
            ]);
            
            // 10. Actualizar log y retornar
            $logService->markSuccess($log, $dialogId, $respuesta);

            if (!empty($respuesta['transfer_to_human'])) {
                $bitrixSession->update(['transferred_to_human' => true]);
            }

            return response()->json([
                'reply' => $respuesta['reply'],
                'rich_content' => $respuesta['rich_content'] ?? null,
                'transfer_to_human' => $respuesta['transfer_to_human'] ?? false,
            ]);
        
        } catch (\Throwable $e) {
            // 12) En caso de excepción, log y devolver genérico
            Log::error('[Bitrix Webhook] Exception processing', [
                'msg'     => $e->getMessage(),
                'payload' => $payload,
            ]);
            // Actualizar log en caso de excepción
            $logService->markFailure($log, $e->getMessage());
            return response()->json(['error' => 'Processing failed'], 200);
        }
    }
}
