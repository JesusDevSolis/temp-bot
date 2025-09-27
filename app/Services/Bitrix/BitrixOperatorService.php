<?php

namespace App\Services\Bitrix;

use App\Models\BitrixInstance;
use App\Services\BitrixService;
use App\Models\BitrixSession;
use Illuminate\Support\Facades\Log;


class BitrixOperatorService
{
    public function __construct(
        protected BitrixService $bitrix
    ) {}

    /**
     * Obtiene la lista de usuarios activos (simula operadores).
     */
    public function getActiveUsers(string $portal): ?array
    {
        $response = $this->bitrix->setPortal($portal)->call('user.get', [
            'FILTER' => ['ACTIVE' => true],
            'SELECT' => ['ID', 'NAME', 'LAST_NAME']
        ]);

        if (! isset($response['result']) || empty($response['result'])) {
            Log::warning('[BitrixOperatorService] No se encontraron usuarios activos.');
            return null;
        }

        return $response['result'];
    }

    public function transferNowIfNeeded(string $uid): void
    {
        $session = BitrixSession::where('uid', $uid)->first();

        if (! $session) {
            Log::warning("[BitrixOperatorService] No se encontró sesión con UID: {$uid}");
            return;
        }

        $chatId = $session->chat_id;

        // Intentar tomar portal desde sesión o desde instancia
        $portal = $session->portal;

        if (! $chatId) {
            Log::warning("[BitrixOperatorService] Sesión sin chat_id para UID {$uid}");
            return;
        }

        // Si no hay portal en sesión, buscarlo usando bitrix_instances por UID del user
        if (! $portal) {
            // Intentamos obtener el portal desde bitrix_instances usando el bot_id (opcional) o buscar el primero
            $instance = BitrixInstance::first(); // Aquí podrías usar un filtro si tienes múltiples portales
            $portal = $instance?->portal;

            if (! $portal) {
                Log::warning("[BitrixOperatorService] No se pudo determinar el portal desde sesión ni instancia");
                return;
            }

            Log::info("[BitrixOperatorService] Portal inferido desde BitrixInstance", ['portal' => $portal]);
        }

        // Obtener instancia actual
        $instance = BitrixInstance::where('portal', $portal)->first();

        if (! $instance || ! $instance->bot_id) {
            Log::warning("[BitrixOperatorService] No se encontró bot_id para portal {$portal}");
            return;
        }

        $botId = $instance->bot_id;

        $dialogId = 'chat' . $chatId;

        // Transferencia automática a cola
        if (! str_starts_with($dialogId, 'chat')) {
            $dialogId = 'chat' . $dialogId;
        }

        Log::debug('[BitrixOperatorService] Enviando transferencia con dialogId corregido', [
            'dialog_id' => $dialogId,
            'portal' => $portal,
        ]);
        
        $configId = $this->bitrix->setPortal($portal)->getConfigIdForBot($botId);

        if (! $configId) {
            Log::warning("[BitrixOperatorService] No se encontró CONFIG_ID para bot_id {$botId}");
            return;
        }

        $response = $this->transferToQueue($portal, $dialogId, $configId);

        Log::debug('[BitrixOperatorService] Transferencia ejecutada desde transferNowIfNeeded', [
            'uid' => $uid,
            'portal' => $portal,
            'chat_id' => $chatId,
            'bot_id' => $botId,
            'response' => $response,
        ]);
    }

    public function transferToQueue(string $portal, string $dialogId, int $queueId = 1): ?array
    {
        try {
            $chatId = (int) str_replace('chat', '', $dialogId);

            $response = $this->bitrix
                ->setPortal($portal)
                ->call('imopenlines.bot.session.transfer', [
                    'DIALOG_ID' => $dialogId,
                    'CHAT_ID'   => $chatId,
                    'QUEUE_ID'  => $queueId,
                ]);

            Log::info('[BitrixOperatorService] Transferencia enviada a la cola', [
                'portal' => $portal,
                'dialog_id' => $dialogId,
                'queue_id' => $queueId,
                'response' => $response,
            ]);

            if (!empty($response['result'])) {
                // Forzar inicio de sesión de agente (simula clic en "Start conversation")
                $this->bitrix->setPortal($portal)->call('imopenlines.operator.startSession', [
                    'CHAT_ID' => $chatId,
                ]);

                // Enviar mensaje inicial al cliente
                $this->bitrix->setPortal($portal)->call('imbot.message.add', [
                    'DIALOG_ID' => $dialogId,
                    'MESSAGE'   => "🔔 Has sido transferido a un agente. Un momento, por favor.",
                ]);
            }

            return $response;
        } catch (\Throwable $e) {
            Log::error('[BitrixOperatorService] Error al transferir a cola', [
                'portal' => $portal,
                'dialog_id' => $dialogId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

}
