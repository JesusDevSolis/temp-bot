<?php

namespace App\Services\Bitrix;

use App\Models\BitrixWebhookLog;

class BitrixLogService
{
    public function createInitial(string $portal, array $payload): BitrixWebhookLog
    {
        return BitrixWebhookLog::create([
            'portal'    => $portal,
            'payload'   => $payload,
            'dialog_id' => null,
            'response'  => null,
            'success'   => false,
        ]);
    }

    public function markSuccess(BitrixWebhookLog $log, string $dialogId, array $response): void
    {
        $log->update([
            'dialog_id' => $dialogId,
            'response'  => $response,
            'success'   => true,
        ]);
    }

    public function markFailure(BitrixWebhookLog $log, string $errorMessage): void
    {
        $log->update([
            'response' => ['error' => $errorMessage],
            'success'  => false,
        ]);
    }
}
