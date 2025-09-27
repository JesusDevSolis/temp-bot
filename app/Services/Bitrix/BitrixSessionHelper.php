<?php

namespace App\Services\Bitrix;

use App\Models\BitrixInstance;
use App\Models\BitrixSession;
use App\Services\Anima\AnimaTreeService;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;


class BitrixSessionHelper
{
    public static function loadOrCreate(string $userId, string $chatId, BitrixInstance $instance, ?string $opGroupId = null): BitrixSession
    {
        $uid = null;

        $existingSession = BitrixSession::where([
            'user_id' => $userId,
            'chat_id' => $chatId,
        ])->first();

        if ($existingSession) {
            $caducada = $existingSession->created_at->lt(Carbon::now()->subHours(24));
            $cerrada  = $existingSession->status === 'closed';

            if (! $caducada && ! $cerrada) {
                return $existingSession;
            }

            // Log::info('[BitrixSession] Sesión caducada o cerrada. Se genera una nueva.', [
            //     'user_id'      => $userId,
            //     'chat_id'      => $chatId,
            //     'uid_anterior' => $existingSession->uid,
            //     'cerrada_por'  => $caducada ? 'caducidad' : 'estado manual',
            // ]);

            $existingSession->delete();
        }

        $treeService = new AnimaTreeService();
        $uid = $treeService->getNewUserUid($instance->hash);

        if (! $uid) {
            throw new \Exception('Error al generar UID desde AnimaTreeService.');
        }

        $node0 = $treeService->fetchPartialFlow(0, $instance->hash, $uid);
        $pathBase = $node0['path'] ?? null;

        // if (!$pathBase) {
        //     Log::warning('[BitrixSessionHelper] No se encontró path en el nodo 0', [
        //         'uid' => $uid,
        //         'hash' => $instance->hash,
        //         'nodo0' => $node0,
        //     ]);
        // }

        // Log::debug('[BitrixSessionHelper] Sesión creada correctamente', [
        //     'uid' => $uid,
        //     'portal' => $instance->portal,
        //     'chat_id' => $chatId,
        // ]);

        return BitrixSession::create([
            'user_id'   => $userId,
            'chat_id'   => $chatId,
            'uid'       => $uid,
            'path_base' => $pathBase,
            'portal'    => $instance->portal,
            'op_group_id' => $opGroupId,
        ]);
    }

    public static function ensureUid(BitrixSession $session, BitrixInstance $instance): void
    {
        if (! $session->uid) {
            $uid = (new AnimaTreeService())->getNewUserUid($instance->hash);
            if (! $uid) {
                throw new \Exception('No se pudo generar UID para la sesión.');
            }

            $session->update(['uid' => $uid]);
        }
    }
}
