<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BitrixInstance;
use App\Services\BitrixService;
use Illuminate\Support\Facades\Log;

class BitrixBotUpdateController extends Controller
{
    public function updateName(Request $request)
    {
        $request->validate([
            'portal'   => 'required|string',
            'new_name' => 'required|string|max:100',
        ]);

        $portal   = $request->input('portal');
        $newName  = $request->input('new_name');

        $instance = BitrixInstance::where('portal', $portal)->first();

        if (!$instance || !$instance->bot_id) {
            return response()->json([
                'status'  => 'ERROR',
                'message' => "No se encontró instancia o bot_id válido para el portal: $portal",
            ], 404);
        }

        try {
            $bitrix = new BitrixService($instance->access_token, "https://{$portal}/rest");

            $webhookUrl = config('services.bitrix.webhook_url') . '/api/v1.0.0/webhook/bitrix/message';

            Log::info('[BotUpdate] Intentando actualizar nombre del bot', [
                'bot_id'   => $instance->bot_id,
                'new_name' => $newName,
                'portal'   => $portal,
            ]);

            $result = $bitrix->call('imbot.update', [
                'BOT_ID' => $instance->bot_id,
                'FIELDS' => [
                    'CODE'           => $instance->bot_code,
                    'EVENT_HANDLER'  => $webhookUrl,
                    'PROPERTIES'     => [
                        'NAME' => $newName,
                    ],
                ],
            ]);

            if (isset($result['result']) && $result['result'] === true) {
                Log::info('[BotUpdate] Nombre del bot actualizado correctamente', [
                    'bot_id'   => $instance->bot_id,
                    'new_name' => $newName,
                    'portal'   => $portal,
                ]);

                return response()->json([
                    'status'  => 'OK',
                    'message' => "Nombre del bot actualizado a: $newName",
                ]);
            } else {
                throw new \Exception('Respuesta inválida de Bitrix al actualizar el bot.');
            }
        } catch (\Throwable $e) {
            Log::error('[BotUpdate] Error al actualizar nombre del bot', [
                'portal' => $portal,
                'error'  => $e->getMessage(),
            ]);

            return response()->json([
                'status'  => 'ERROR',
                'message' => 'No se pudo actualizar el nombre del bot.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}