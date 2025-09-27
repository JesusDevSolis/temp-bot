<?php

namespace App\Services\Bitrix;

use App\Models\BitrixSession;
use App\Services\AnimaService;
use Illuminate\Support\Facades\Log;

class BitrixSessionFinalizerService
{
    public static function finalizarSesionYNotificar(BitrixSession $session): void
    {
        if ($session->status === 'closed') {
            Log::info('[Finalizer] La sesión ya estaba cerrada, se omite', [
                'uid' => $session->uid,
                'status' => $session->status,
            ]);
            return;
        }

        $session->update(['status' => 'closed']);

        $opGroupId = $session->op_group_id;
        $userId    = $session->user_id;

        if (!$opGroupId || !$userId) {
            Log::warning('[Finalizer] No se puede notificar cierre a Ánima. Faltan datos.', [
                'uid' => $session->uid,
                'op_group_id' => $opGroupId,
                'user_id' => $userId,
            ]);
            return;
        }

        // [1] Notificar a Ánima que se finalizó
        try {
            $anima = new AnimaService();
            $anima->finalized([
                'opGroupId' => $opGroupId,
                'userId'    => $userId,
            ]);

            Log::info('[Finalizer] Sesión cerrada y notificada a Ánima exitosamente', [
                'uid' => $session->uid,
                'op_group_id' => $opGroupId,
                'user_id' => $userId,
            ]);
        } catch (\Throwable $e) {
            Log::error('[Finalizer] Error al notificar cierre a Ánima', [
                'uid' => $session->uid,
                'error' => $e->getMessage(),
            ]);
        }

        // [1.5] Enviar mensaje final de cierre al usuario (antes de cerrar)
        try {
            $bitrix = (new \App\Services\BitrixService())->setPortal($session->portal);
            $bitrix->sendBotMessage($session->dialog_id, '✅ El chat ha sido finalizado. ¡Gracias por comunicarte con nosotros!');
            Log::info('[Finalizer] Mensaje de cierre enviado antes de finalizar sesión', [
                'dialog_id' => $session->dialog_id,
                'uid' => $session->uid,
            ]);
        } catch (\Throwable $e) {
            Log::error('[Finalizer] Error al enviar mensaje final de cierre', [
                'dialog_id' => $session->dialog_id,
                'uid' => $session->uid,
                'error' => $e->getMessage(),
            ]);
        }

        // [2] Cerrar conversación en el canal abierto de Bitrix
        try {
            if (!$session->chat_id) {
                Log::warning('[Finalizer] No hay chat_id para cerrar conversación en Bitrix', [
                    'uid' => $session->uid,
                ]);
                return;
            }

            $instance = \App\Models\BitrixInstance::where('portal', $session->portal)->first();

            if (!$instance || !$instance->access_token) {
                Log::error('[Finalizer] No se encontró instancia válida para cerrar chat en Bitrix', [
                    'portal' => $session->portal,
                    'uid' => $session->uid,
                ]);
                return;
            }

            $bitrix = (new \App\Services\BitrixService())->setPortal($session->portal);
            $bitrix->finishSessionWithChatId((int) $session->chat_id);

            $bitrix->closeOpenlineChat($session->dialog_id);

            Log::info('[Finalizer] Chat cerrado correctamente en Bitrix Contact Center', [
                'chat_id' => $session->chat_id,
                'dialog_id' => $session->dialog_id,
                'uid' => $session->uid,
            ]);
        } catch (\Throwable $e) {
            Log::error('[Finalizer] Error al cerrar chat en Bitrix Contact Center', [
                'chat_id' => $session->chat_id,
                'uid' => $session->uid,
                'error' => $e->getMessage(),
            ]);
        }
    }
}