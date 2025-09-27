<?php

namespace App\Services\Anima;

use App\Models\BitrixUserInput;
use App\Models\BitrixConversationThread;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnimaTreeService
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.anima.base_url', 'https://animalogic.anima.bot/api');
    }

    /**
     * Solicita un nuevo UID para iniciar una conversación
     */
    public function getNewUserUid(string $hash): ?string
    {
        try {
            $response = Http::withHeaders([
                'channel' => 'Web',
            ])->get("{$this->baseUrl}/connect/stats/new-user/{$hash}");

            if ($response->successful() && isset($response['uid'])) {
                return $response['uid'];
            }

            Log::warning('[AnimaTreeService] No se recibió UID válido', [
                'response' => $response->json(),
            ]);
        } catch (\Throwable $e) {
            Log::error('[AnimaTreeService] Error al obtener UID: ' . $e->getMessage());
        }

        return null;
    }

    public function fetchPartialFlow(int|string $nodeId, string $hash, string $uid): ?array
    {
        try {
            if ((int) $nodeId === 999999) {
                Log::warning('[AnimaTreeService] Se intentó acceder a nodo virtual 999999, abortando.');
                return null;
            }

            $response = Http::withHeaders([
                'uid' => $uid,
            ])->get("{$this->baseUrl}/connect/{$nodeId}/{$hash}");

            if ($response->successful() && is_array($response->json())) {
                return $response->json();
            }
        } catch (\Throwable $e) {
            Log::error('[AnimaTreeService] Error al obtener flujo parcial: ' . $e->getMessage(), [
                'node_id' => $nodeId,
                'hash' => $hash,
            ]);
        }

        return null;
    }

    public function postNaturalLanguage(string $hash, string $uid, string $message, array $headers = []): ?array
    {
        try {
            // Corrige nombre del header a 'Thread-Id'
            if (!isset($headers['Thread-Id'])) {
                $pendingThreadId = BitrixConversationThread::where('uid', $uid)
                    ->where('is_answered', false)
                    ->latest('id')
                    ->value('thread_id');

                if ($pendingThreadId) {
                    $headers['Thread-Id'] = $pendingThreadId;
                }
            }

            $headers['uid'] = $uid;
            $headers['origen'] = 'bitrix';

            $response = Http::withHeaders($headers)
                ->post("{$this->baseUrl}/ia/natural-language/{$hash}", [
                    'question' => $message,
                ]);

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Throwable $e) {
            Log::error('[AnimaTreeService] Excepción en postNaturalLanguage', [
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    public function fetchTree(string $hash, string $uid): array
    {
        try {
            $response = Http::withHeaders([
                'uid' => $uid,
            ])->get("{$this->baseUrl}/connect/{$hash}");

            if ($response->successful() && is_array($response->json())) {
                return $response->json();
            }
        } catch (\Throwable $e) {
            Log::error('[AnimaTreeService] Error al obtener árbol completo: ' . $e->getMessage(), [
                'hash' => $hash,
            ]);
        }

        return ['nodes' => []];
    }

    /**
     * Envía el valor del input de usuario a Anima para que determine el siguiente nodo del flujo.
     *
     * @param string $hash
     * @param string $uid
     * @param int $nodeId
     * @param string $value
     * @return array|null
     */
    public function postInputAnswer(string $hash, string $uid, int $nodeId, string $value): ?array
    {
        try {
            $url = "{$this->baseUrl}/form/{$nodeId}/{$hash}";

            $response = Http::withHeaders([
                'uid' => $uid,
                'Content-Type' => 'application/json',
            ])->post($url, [
                'value' => $value,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('[AnimaTreeService] Falló el envío de input al árbol', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

        } catch (\Throwable $e) {
            Log::error('[AnimaTreeService] Excepción en postInputAnswer()', [
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

}
