<?php

namespace App\Services\Bitrix;
use App\Models\BitrixInstance;
use Illuminate\Support\Facades\Log;

class BitrixWebhookNormalizer
{
    protected array $rawPayload;
    protected array $normalized;

    public function __construct(array $payload)
    {
        $this->rawPayload = $payload;
        $this->normalized = $this->normalize($payload);
    }

    /**
     * Normaliza el payload recibido desde Bitrix para trabajar internamente.
     */
    protected function normalize(array $payload): array
    {
        // Aquí asumimos que Bitrix manda en formato form-urlencoded
        return [
            'userId'    => $payload['data']['USER']['ID'] ?? $payload['userId'] ?? null,
            'chatId'    => $payload['data']['PARAMS']['CHAT_ID'] ?? $payload['chatId'] ?? null,
            'dialogId'  => $payload['data']['PARAMS']['DIALOG_ID'] ?? $payload['dialogId'] ?? null,
            'message'   => $payload['data']['PARAMS']['MESSAGE'] ?? $payload['message'] ?? null,
            'channelId' => $this->extractBotCode($payload),
        ];
    }

    protected function extractBotCode(array $payload): ?string
    {
        if (!isset($payload['data']['BOT']) || !is_array($payload['data']['BOT'])) {
            return null;
        }

        foreach ($payload['data']['BOT'] as $bot) {
            if (isset($bot['BOT_CODE'])) {
                return $bot['BOT_CODE'];
            }
        }

        return null;
    }

    /**
     * Devuelve el payload ya normalizado.
     */
    public function get(): array
    {
        return $this->normalized;
    }

    /**
     * Valida si el payload contiene lo esencial para procesar.
     */
    public function isValid(): bool
    {
        return filled($this->normalized['userId']) &&
                filled($this->normalized['chatId']) &&
                filled($this->normalized['message']);
    }

    /**
     * Útil para logging completo del flujo.
     */
    public function debug(): void
    {
        // logger('[BitrixWebhookNormalizer] Payload original:', $this->rawPayload);
        // logger('[BitrixWebhookNormalizer] Payload normalizado:', $this->normalized);
    }

    public function ensureBitrixInstanceCompleta(): void
    {
        $botData = $this->rawPayload['data']['BOT'] ?? null;

        if (!$botData || !is_array($botData)) {
            Log::debug('[BitrixNormalizer] No hay datos BOT válidos');
            return;
        }

        foreach ($botData as $botId => $info) {
            $portal = $info['domain'] ?? null;

            if (!$portal) {
                Log::debug("[BitrixNormalizer] No se encontró el portal en BOT {$botId}");
                continue;
            }

            $instance = BitrixInstance::where('portal', $portal)->first();

            if (!$instance) {
                Log::debug("[BitrixNormalizer] No se encontró bitrix_instance para portal {$portal}");
                continue;
            }

            $actualizado = false;

            if (empty($instance->bot_id) && !empty($info['BOT_ID'])) {
                $instance->bot_id = $info['BOT_ID'];
                $actualizado = true;
            }

            if (empty($instance->channel_id) && !empty($info['BOT_CODE'])) {
                $instance->channel_id = $info['BOT_CODE'];
                $actualizado = true;
            }

            if ($actualizado) {
                $instance->save();
                Log::debug("[BitrixNormalizer] bitrix_instance actualizada automáticamente", [
                    'portal' => $portal,
                    'bot_id' => $instance->bot_id,
                    'channel_id' => $instance->channel_id,
                ]);
            }
        }
    }
}
