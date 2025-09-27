<?php

namespace App\Services\Bitrix;

use App\Services\AnimaService;
use App\Models\BitrixInstance;
use Illuminate\Support\Facades\Log;

class AnimaConnectorService
{
    protected $anima;

    public function __construct()
    {
        $this->anima = new AnimaService();
    }

    /**
     * Ejecuta el flujo de conexión con Ánima: connect, insert, newUser, assign
     */
    public function connectUser(array $data): ?string
    {
        $chatBotKey = $data['chatbot_key'];
        $attentionGroup = $data['attention_group'];
        $userId = $data['user_id'];
        $portal = $data['portal'];
        $tag = $data['tag'] ?? '#soporte';
        $origen = $data['origen'] ?? 'bitrix';
        $channelId = $data['channel_id'] ?? null;

        // Paso 1: Conectar (si ya existe, simplemente lo devuelve)
        $group = $this->anima->connect([
            'attention_group' => $attentionGroup,
            'chatbot_key'     => $chatBotKey,
            'tag'             => $tag,
            'origen'          => $origen,
        ]);

        $opGroupId = $group['data']['op_group_id'] ?? null;
        $exist = $group['data']['exist'] ?? false;

        // Consultar si ya existe un chat activo para este userId
        $chatData = $this->anima->getChatData(['userId' => $userId]);
        $status = $chatData['chatData']['data']['status'] ?? null;

        if ((!$exist || $status !== 1) && $opGroupId) {
            // Paso 2: Insertar broadcast interno
            $this->anima->insert(['opGroupId' => $opGroupId]);

            // Paso 3: Registrar usuario
            $this->anima->newUser([
                'attention_group' => $attentionGroup,
                'userId'          => $userId,
                'chatbot_id'      => $chatBotKey,
                'chatbot_key'     => $chatBotKey,
                'payloadUser'     => ['name' => 'bitrix-' . $userId],
                'tag'             => $tag,
                'opQueueType'     => 'auto',
                'origen'          => $origen,
            ]);

            // Paso 4: Asignar bot como operador
            $instance = BitrixInstance::where('portal', $portal)->first();
            if ($instance && $instance->bot_id) {
                $this->anima->assign([
                    'opGroupId'       => $opGroupId,
                    'userId'          => $userId,
                    'opId'            => $attentionGroup,
                    'payloadOperador' => ['nombre' => 'BotBitrix'],
                    'origen'          => $origen,
                ]);

                if ($channelId && $instance->channel_id !== $channelId) {
                    $instance->update(['channel_id' => $channelId]);
                }
            }
        }

        return $opGroupId;
    }
}
