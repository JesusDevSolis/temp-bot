<?php

namespace App\Services\Anima;
use App\Services\Anima\AnimaTreeService;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\BitrixSession;
use App\Models\BitrixConversationThread;
use App\Models\BitrixMenuOption;

use App\Services\Bitrix\BitrixOperatorService;
use App\Services\BitrixService;
use App\Services\Bitrix\BitrixSessionFinalizerService;

class NodeProcessor
{
    protected BitrixOperatorService $bitrixOperatorService;
    protected BitrixSession $session;
    protected BitrixService $bitrixService;

    public function __construct(BitrixOperatorService $bitrixOperatorService, BitrixSession $session, BitrixService $bitrixService)
    {
        $this->bitrixOperatorService = $bitrixOperatorService;
        $this->session = $session;
        $this->bitrixService = $bitrixService;
    }

    /**
     * Procesa un nodo del árbol según su type_id
     *
     * @param array $node
     * @return array
     */
    public function handle(array $node, array $context = []): array
    {
        if (!array_key_exists('type_id', $node)) {
            return $this->nodeError("Nodo sin tipo definido.");
        }

        $typeId = $node['type_id'];

        if ($respuesta = $this->evaluarCierreDeSesion($node, $context)) {
            return $respuesta;
        }

        return match ($typeId) {
            1   => $this->handleTextNode($node, $context),
            2   => $this->handleMenuNode($node),
            3   => $this->handleMenuOptionNode($node),
            4   => $this->handleTextNode($node, $context),
            5   => $this->handleInputTextNode($node, $context),
            6   => $this->handleLinkNode($node),
            // 7   => $this->handleNaturalLanguageNode($node, $context), //redireje a otro bot ()
            8   => $this->handleImageNode($node, $context),
            9   => $this->handleVideoNode($node),
            10  => $this->handleFileNode($node, $context),
            11  => $this->handleAudioNode($node, $context),
            12  => $this->handleRedirectNode($node),
            13  => $this->handleTransferToHumanNode($node, $context),
            14  => $this->handleInputTextNode($node, $context),
            15  => $this->handleHttpNode($node),

            91 => $this->handleCustomAiNode($node, $context),

            default => $this->handleUnknownNode($node),
        };
    }

    public function evaluarCierreDeSesion(array $node, array $context = []): ?array
    {
        $typeId = $node['type_id'] ?? null;
        $uid = $context['uid'] ?? null;

        if (!in_array($typeId, [1, 4, 15]) || !$uid) {
            return null;
        }

        $hasChildren = isset($node['children']) && is_array($node['children']) && count($node['children']) > 0;
        $hasRedirect = !empty($node['redirect_item']['id'] ?? null);
        $nextNodeId = $context['next_node_id'] ?? null;

        $session = BitrixSession::where('uid', $uid)->first();

        if (!$session) {
            Log::warning('[NodeProcessor] Sesión no encontrada para evaluar cierre', ['uid' => $uid]);
            return null;
        }

        if ($session->transferred_to_human) {
            Log::info('[NodeProcessor] Sesión fue transferida a humano. No se mostrarán opciones de reinicio.', [
                'uid' => $uid,
                'status_actual' => $session->status,
            ]);
            return null;
        }

        if (!$hasChildren && !$hasRedirect && !$nextNodeId) {
            // No hay más flujo, marcar como esperando opciones
            BitrixSession::where('uid', $uid)->update([
                'status' => 'awaiting_restart_option',
                'show_restart_menu_after' => true,
            ]);

            Log::info('[NodeProcessor] Nodo final detectado. Mostrando opciones de reinicio en lugar de cerrar.', [
                'uid' => $uid,
                'node_id' => $node['id'] ?? null,
                'type_id' => $typeId,
            ]);

            $map = [
                '1' => 'main_menu_restart',
                '2' => 'end_chat',
            ];

            \App\Models\BitrixMenuOption::create([
                'uid' => $uid,
                'bitrix_session_id' => BitrixSession::where('uid', $uid)->value('id'),
                'is_main_menu' => false,
                'options' => $map,
            ]);

            Log::info('[NodeProcessor] Menú de reinicio será enviado tras el nodo actual.', [
                'uid' => $uid,
            ]);
        }

        return null;
    }

    private function handleAudioNode(array $node, array $context = []): array
    {
        if ($this->isEmptyNode($node)) {
            return $this->nodeError("Nodo de audio vacío (id={$node['id']})");
        }

        $title = $node['title'] ?? 'Audio';
        $path = $node['data'] ?? '';
        $url = $this->resolverUrl($path, $context['session']);

        $nextNodeId = $this->hasChildren($node) ? $node['children'][0]['id'] : null;

        log::debug('[handleAudioNode] Validando siguiente nodo', [
            'node_id' => $node['id'],
            'next_node_id' => $nextNodeId,
            'path' => $path,
            'url' => $url,
        ]);

        if (!$nextNodeId && !empty($context['all_nodes'])) {

            Log::debug('[handleAudioNode] Buscando siguiente nodo en contexto', [
                'node_id' => $node['id'],
                'all_nodes_count' => count($context['all_nodes']),
            ]);

            $nextNode = collect($context['all_nodes'])->firstWhere('parent', $node['id']);
            $nextNodeId = $nextNode['id'] ?? null;
        }

        if (!$nextNodeId) {
            log::debug('[handleAudioNode] No se encontró siguiente nodo, retornando sin continuación', [
                'node_id' => $node['id'],
                'path' => $path,
                'url' => $url,
                'reply' => $title,
            ]);
            return $this->nodeWithoutNextStep($title, false, $node['id'], null, $node, $context);
        }

        Log::debug('[handleAudioNode] Validación final antes de return', [
            'node_id' => $node['id'],
            'path' => $path,
            'url' => $url,
            'reply' => $title,
            'rich_content' => [
                'type' => 'audio',
                'src' => $url,
            ],
        ]);

        return [
            'reply' => $title,
            'rich_content' => $url ? [
                'type' => 'audio',
                'src' => $url,
            ] : null,
            'transfer_to_human' => $this->evaluarTransferenciaHumana($node),
            'next_node_id' => $nextNodeId,
        ];
    }

    private function handleFileNode(array $node, array $context = []): array
    {
        if ($this->isEmptyNode($node)) {
            return $this->nodeError("Nodo de archivo vacío (id={$node['id']})");
        }

        $path = $node['data'] ?? '';
        $title = $node['title'] ?? 'Archivo';

        // Construimos la URL absoluta
        $url = $this->resolverUrl($path, $context['session']);

        $nextNodeId = $this->hasChildren($node) ? $node['children'][0]['id'] : null;

        if (!$nextNodeId && !empty($context['all_nodes'])) {
            $nextNode = collect($context['all_nodes'])->firstWhere('parent', $node['id']);
            $nextNodeId = $nextNode['id'] ?? null;
        }

        if (!$nextNodeId) {
            return $this->nodeWithoutNextStep($title, false, $node['id'], null, $node, $context);
        }

        return [
            'reply' => "{$title}:\n{$url}", // Texto visible con el enlace
            'rich_content' => [
                'type' => 'file',
                'src'   => $url,
                'title' => $title,
            ],
            'transfer_to_human' => $this->evaluarTransferenciaHumana($node),
            'next_node_id' => $nextNodeId,
            'expects_input' => false,
        ];
    }

    private function handleHttpNode(array $node): array
    {
        try {
            $data = json_decode($node['data'] ?? '', true);

            if (!is_array($data) || !isset($data['url'], $data['method'])) {
                return $this->handleUnknownNode($node);
            }

            $method = strtolower($data['method']);
            $url = $data['url'];
            $headers = $data['headers'] ?? [];
            $body = $data['body'] ?? [];

            $request = Http::withHeaders($headers);

            $response = match ($method) {
                'get' => $request->get($url, $body),
                'post' => $request->post($url, $body),
                'put' => $request->put($url, $body),
                'delete' => $request->delete($url, $body),
                default => null,
            };

            if (!$response || !$response->ok()) {
                return [
                    'reply' => 'Ocurrió un error al procesar la solicitud.',
                    'rich_content' => null,
                    'transfer_to_human' => $this->evaluarTransferenciaHumana($node),
                ];
            }

            $json = $response->json();
            $reply = $json[$data['api_response_key']] ?? json_encode($json);

            $nextNodeId = $this->hasChildren($node) ? $node['children'][0]['id'] : null;

            if (!$nextNodeId) {
                return $this->nodeWithoutNextStep($reply, false, $node['id'], null, $node, $context);

            }

            return [
                'reply' => $reply,
                'rich_content' => null,
                'transfer_to_human' => $this->evaluarTransferenciaHumana($node),
                'next_node_id' => $nextNodeId,
            ];

        } catch (\Throwable $e) {
            return [
                'reply' => 'Error procesando nodo HTTP: ' . $e->getMessage(),
                'rich_content' => null,
                'transfer_to_human' => $this->evaluarTransferenciaHumana($node),
            ];
        }
    }

    private function handleImageNode(array $node, array $context = []): array
    {
        if ($this->isEmptyNode($node)) {
            return $this->nodeError("Nodo de imagen vacío (id={$node['id']})");
        }

        $path = $node['data'] ?? '';
        $title = $node['title'] ?? '';
        $url = $this->resolverUrl($path, $context['session']);
        $dialogId = $context['session']->dialog_id ?? null;

        $nextNodeId = $this->hasChildren($node) ? $node['children'][0]['id'] : null;

        if (!$nextNodeId && !empty($context['all_nodes'])) {
            $nextNode = collect($context['all_nodes'])->firstWhere('parent', $node['id']);
            $nextNodeId = $nextNode['id'] ?? null;
        }

        if (!$nextNodeId) {
            return $this->nodeWithoutNextStep($title ?: 'Imagen sin siguiente paso', false, $node['id'], null, $node, $context);
        }

        if (!$dialogId) {
            return [
                'reply' => null,
                'next_node_id' => $nextNodeId,
                'transfer_to_human' => $this->evaluarTransferenciaHumana($node),
            ];
        }

        // Enviar imagen directamente a Bitrix
        $this->bitrixService->sendBotImage($dialogId, $url, $title);

        return [
            'reply' => null,
            'transfer_to_human' => $this->evaluarTransferenciaHumana($node),
            'next_node_id' => $nextNodeId,
        ];
    }

    private function handleInputTextNode(array $node, array $context = []): array
    {
        $label = '';

        if (is_array($node['data']) && isset($node['data']['data_node']['label'])) {
            $label = $node['data']['data_node']['label'];
        } elseif (is_string($node['data'])) {
            $label = $this->extractLabelFromData($node['data']);
        } else {
            $label = '¿Podés escribir tu respuesta?';
        }

        $nextNodeId = $node['redirect_item']['id'] ?? ($node['children'][0]['id'] ?? null);

        Log::debug('', [
            'current_node' => $node['id'],
            'next_node_id' => $nextNodeId,
            'redirect_item' => $node['redirect_item'] ?? null,
            'children' => $node['children'] ?? null,
        ]);

        // Verificar si hay opciones definidas en el campo values
        $values = [];

        if (is_array($node['data']) && isset($node['data']['data_node']['values'])) {
            $values = is_string($node['data']['data_node']['values'])
                ? array_map('trim', explode(',', $node['data']['data_node']['values']))
                : (is_array($node['data']['data_node']['values']) ? $node['data']['data_node']['values'] : []);
        } elseif (is_string($node['data'])) {
            $values = $this->extractOptionsFromData($node['data']);
        }

        if (!empty($values)) {
            $options = collect($values)
                ->map(fn($value, $index) => [
                    'text'  => ($index + 1) . '. ' . $value,
                    'value' => $value,
                ])
                ->toArray();

            // Guardar mapa de opciones para menú posterior
            \App\Models\BitrixMenuOption::updateOrCreate(
                ['uid' => $context['uid'] ?? ''],
                ['options' => collect($values)
                    ->mapWithKeys(fn($value, $i) => [
                        (string) $i         => $value,
                        (string) ($i + 1)   => $value,
                        $value              => $value,
                    ])
                    ->toArray()
                ]
            );

            Log::info('[FlowEngine] Opciones de menú almacenadas desde nodo input', [
                'values' => $values
            ]);

            return [
                'reply' => $label,
                'rich_content' => $options,
                'transfer_to_human' => $this->evaluarTransferenciaHumana($node),
                'expects_input' => true,
                'current_node_id' => $node['id'],
                'next_node_id' => $nextNodeId,
            ];
        }

        if (!$nextNodeId) {
            return $this->nodeWithoutNextStep($label, true, $node['id']);
        }

        $showPrompt = empty($context['message']);

        if (!$showPrompt) {
            Log::debug('[handleInputTextNode] Mensaje ya fue enviado previamente, no se repite', [
                'node_id' => $node['id'],
                'label' => $label,
            ]);
        }

        return [
            'reply' => $showPrompt ? $label : null,
            'rich_content' => null,
            'transfer_to_human' => $this->evaluarTransferenciaHumana($node),
            'expects_input' => true,
            'next_node_id' => $nextNodeId,
            'current_node_id' => $node['id'],
        ];
    }

    private function handleLinkNode(array $node, array $context = []): array
    {
        if ($this->isEmptyNode($node)) {
            return $this->nodeError("Nodo de texto vacío (id={$node['id']})");
        }

        $text = $node['title'] ?? 'Consulta este enlace';
        $url = $node['data'] ?? '#';
        $nextNodeId = $this->hasChildren($node) ? $node['children'][0]['id'] : null;

        if (!$nextNodeId && !empty($context['all_nodes'])) {
            $nextNode = collect($context['all_nodes'])->firstWhere('parent', $node['id']);
            $nextNodeId = $nextNode['id'] ?? null;
        }

        if (!$nextNodeId) {
            return $this->nodeWithoutNextStep("$text\n$url", false, $node['id'], null, $node, $context);
        }

        return [
            'reply' => "$text\n$url",
            'rich_content' => null,
            'transfer_to_human' => $this->evaluarTransferenciaHumana($node),
            'next_node_id' => $nextNodeId,
        ];
    }

    private function handleMenuNode(array $node): array
    {
        $options = collect($node['children'] ?? [])
                    ->filter(fn($child) => isset($child['id'], $child['title']) && trim($child['title']) !== '')
                    ->values()
                    ->map(function ($child, $index) {
                        return [
                            'text' => ($index + 1) . '. ' . $child['title'],
                            'value' => $child['id'],
                            'raw_text' => $child['title'],
                        ];
                    })
                    ->all();

        if (empty($options)) {
            return $this->nodeError("El nodo menú no tiene opciones válidas (id={$node['id']})");
        }

        return [
            'reply' => $node['data'] ?? '',
            'rich_content' => $options,
            'transfer_to_human' => $this->evaluarTransferenciaHumana($node),
            'next_node_id' => null,
        ];
    }

    private function handleMenuOptionNode(array $node, array $context = []): array
    {
        if ($this->isEmptyNode($node)) {
            return $this->nodeError("Nodo de texto vacío (id={$node['id']})");
        }

        $reply = $node['data'] ?? '';

        // Intentar seguir con el primer hijo (si existe)
        $nextNodeId = $this->hasChildren($node) ? $node['children'][0]['id'] : null;

        // Si no hay hijo, buscar fallback al menú padre
        if (!$nextNodeId) {
            $allNodes = collect($context['all_nodes'] ?? []);
            $parentId = $node['parent'] ?? null;

            if ($parentId) {
                $parentNode = $allNodes->firstWhere('id', $parentId);

                if ($parentNode && ($parentNode['type_id'] ?? null) == 2) {
                    $options = $allNodes
                        ->where('parent', $parentId)
                        ->filter(fn($n) => isset($n['title']))
                        ->map(fn($n) => [
                            'text' => $n['title'],
                            'value' => $n['id'],
                        ])
                        ->values()
                        ->all();

                    return [
                        'reply' => $reply,
                        'rich_content' => $options,
                        'transfer_to_human' => $this->evaluarTransferenciaHumana($node),
                        'next_node_id' => null,
                    ];
                }
            }

            return $this->nodeWithoutNextStep($reply, true, $node['id'], null, $node, $context);
        }

        // Si hay hijo, continuar flujo normalmente
        return [
            'reply' => $reply,
            'rich_content' => null,
            'transfer_to_human' => $this->evaluarTransferenciaHumana($node),
            'next_node_id' => $nextNodeId,
        ];
    }

    private function handleRedirectNode(array $node, array $context = []): array
    {
        if ($this->isEmptyNode($node)) {
            return $this->nodeError("Nodo de redirección vacío (id={$node['id']})");
        }

        if (empty($node['redirect_item'])) {
            return $this->nodeError("Nodo de redirección sin destino definido (id={$node['id']})");
        }

        return [
            'reply' => $node['data'] ?? '', 
            'next_node_id' => $node['redirect_item'], 
            'transfer_to_human' => false,
            'expects_input' => false,
        ];
    }
    
    private function handleTextNode(array $node, array $context = []): array
    {
        if ($this->isEmptyNode($node)) {
            return $this->nodeError("Nodo de texto vacío (id={$node['id']})");
        }

        $text = $this->parseLabelValue($node['data'] ?? '');

        $nextNodeId = $this->hasChildren($node) ? $node['children'][0]['id'] : null;

        if (!$nextNodeId && !empty($context['all_nodes'])) {
            $nextNode = collect($context['all_nodes'])->firstWhere('parent', $node['id']);
            $nextNodeId = $nextNode['id'] ?? null;
        }

        if (!$nextNodeId) {
            return $this->nodeWithoutNextStep($text, false, $node['id'], null, $node, $context);
        }

        return [
            'reply' => $text,
            'rich_content' => null,
            'transfer_to_human' => $this->evaluarTransferenciaHumana($node),
            'next_node_id' => $nextNodeId,
        ];
    }

    private function handleTransferToHumanNode(array $node, array $context = []): array
    {
        if ($this->isEmptyNode($node)) {
            return $this->nodeError("Nodo de transferencia vacío (id={$node['id']})");
        }

        $uid = $context['uid'] ?? null;

        if (! $uid) {
            return $this->nodeError('UID no disponible en el contexto para transferencia a humano.');
        }

        // Marcar sesión como transferida
        BitrixSession::where('uid', $uid)->update([
            'transferred_to_human' => true,
        ]);

        // Limpiar todos los menús visibles para esta sesión
        \App\Models\BitrixMenuOption::where('bitrix_session_id', function ($query) use ($uid) {
            $query->select('id')
                ->from('bitrix_sessions')
                ->where('uid', $uid)
                ->limit(1);
        })
        ->where('is_main_menu', false)
        ->delete();

        Log::info('[NodeProcessor] Menús eliminados tras transferencia a humano', ['uid' => $uid]);

        try {
            $session = \App\Models\BitrixSession::where('uid', $uid)->first();

            Log::info('[NodeProcessor] Sesión marcada como transferida a humano', [
                'uid' => $uid,
                'session' => $session,
            ]);

            if ($session) {
                \App\Services\Bitrix\BitrixSessionFinalizerService::finalizarSesionYNotificar($session);
            } else {
                Log::warning('[NodeProcessor] No se encontró la sesión para notificar cierre a Ánima (transferencia a humano)', [
                    'uid' => $uid,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('[NodeProcessor] Error al notificar cierre a Ánima (transferencia a humano)', [
                'uid' => $uid,
                'error' => $e->getMessage(),
            ]);
        }

        // Ejecutar transferencia automática a operador
        try {
            $this->bitrixOperatorService->transferNowIfNeeded($uid);
        } catch (\Throwable $e) {
            Log::error("[NodeProcessor] Error al transferir a humano", [
                'uid' => $uid,
                'error' => $e->getMessage(),
            ]);
        }

        $text = $this->parseLabelValue($node['data'] ?? '');

        return [
            'reply' => $text ?? 'En un momento un agente humano te responderá.',
            'transfer_to_human' => true,
            'expects_input' => false,
        ];
    }

    private function handleUnknownNode(array $node): array
    {
        return [
            'reply' => 'Este tipo de nodo aún no está soportado.',
            'rich_content' => null,
            'transfer_to_human' => false,
        ];
    }

    public function handleCustomAiNode(array $node, array $context = []): array
    {
        $uid = $context['uid'] ?? null;
        $hash = $context['hash'] ?? null;
        $message = $node['message'] ?? null;

        if (!$uid || !$hash || !$message) {
            return [
                'reply' => 'No se pudo procesar tu pregunta. Faltan datos.',
                'transfer_to_human' => false,
            ];
        }

        $previousAnsweredThread = BitrixConversationThread::where('uid', $uid)
            ->where('bitrix_session_id', $this->session->id)
            ->whereNotNull('thread_id')
            ->where('is_answered', true)
            ->latest('updated_at')
            ->first();

        $headers = [];

        if ($previousAnsweredThread) {
            $headers['Thread-Id'] = $previousAnsweredThread->thread_id;
        }

        $treeService = app(\App\Services\Anima\AnimaTreeService::class);
        $response = $treeService->postNaturalLanguage($hash, $uid, $message, $headers);

        $threadIdResponse = $response['thread_id'] ?? null;

        if ($threadIdResponse && $previousAnsweredThread && $previousAnsweredThread->thread_id !== $threadIdResponse) {
            $previousAnsweredThread->update(['thread_id' => $threadIdResponse]);
        } elseif ($threadIdResponse && !$previousAnsweredThread) {
            BitrixConversationThread::create([
                'uid' => $uid,
                'bitrix_session_id' => $this->session->id,
                'node_id' => 999999,
                'user_message' => $message,
                'ai_response' => $response['message'] ?? '',
                'thread_id' => $threadIdResponse,
                'is_answered' => true,
            ]);

            Log::info('[NodeProcessor] Se creó nuevo hilo de conversación con thread-id', [
                'uid' => $uid,
                'thread_id' => $threadIdResponse,
            ]);
        }

        // Fallback si falla o no hay respuesta válida
        if (!is_array($response) || empty($response['message'])) {
            Log::warning('[NodeProcessor] Respuesta de IA vacía o inválida', [
                'uid' => $uid,
                'message' => $message,
                'response' => $response,
            ]);

            $fallbackMessage = "Lo siento, no pude entender tu pregunta.\n\n#. 🔄 Volver al menú principal\n*. ❌ Finalizar chat";

            $this->bitrixService->sendBotMessage($uid, $fallbackMessage);

            BitrixMenuOption::create([
                'uid' => $uid,
                'bitrix_session_id' => $this->session->id,
                'is_main_menu' => false,
                'options' => [
                    '#' => 'main_menu_restart',
                    '*' => 'end_chat',
                ],
            ]);

            BitrixConversationThread::where('uid', $uid)
                ->where('bitrix_session_id', $this->session->id)
                ->where('user_message', $message)
                ->where('is_answered', false)
                ->latest()
                ->first()?->update([
                    'ai_response' => "Lo siento, no pude entender tu pregunta.",
                    'thread_id' => null,
                    'is_answered' => true,
                ]);

            return [];
        }

        // Respuesta válida: actualizar o crear conversación
        $existing = BitrixConversationThread::where('uid', $uid)
            ->where('bitrix_session_id', $this->session->id)
            ->where(function ($query) use ($message) {
                $query->whereRaw('LOWER(user_message) = ?', [strtolower($message)])
                    ->orWhereRaw('? LIKE CONCAT("%", LOWER(user_message), "%")', [strtolower($message)]);
            })
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            $existing->update([
                'ai_response' => $response['message'],
                'thread_id' => $response['thread_id'] ?? null,
                'is_answered' => true,
            ]);
        } else {
            BitrixConversationThread::create([
                'uid' => $uid,
                'bitrix_session_id' => $this->session->id,
                'node_id' => 999999,
                'user_message' => $message,
                'ai_response' => $response['message'],
                'thread_id' => $response['thread_id'] ?? null,
                'is_answered' => true,
            ]);
        }

        // Marcar sesión para mostrar menú de reinicio en mensaje separado
        $this->session->update([
            'status' => 'awaiting_restart_option',
            'show_restart_menu_after' => true,
        ]);

        return [
            'reply' => $response['message'],
            'rich_content' => null,
            'transfer_to_human' => false,
            'next_node_id' => null,
        ];
    }

    private function handleVideoNode(array $node, array $context = []): array
    {
        if ($this->isEmptyNode($node)) {
            return $this->nodeError("Nodo de video vacío (id={$node['id']})");
        }

        $nextNodeId = $this->hasChildren($node) ? $node['children'][0]['id'] : null;

        if (!$nextNodeId && !empty($context['all_nodes'])) {
            $nextNode = collect($context['all_nodes'])->firstWhere('parent', $node['id']);
            $nextNodeId = $nextNode['id'] ?? null;
        }

        return [
            'reply' => null, // Ya no mostramos la URL como texto
            'rich_content' => [
                'type' => 'video',
                'url' => $node['data'] ?? '',
                'title' => $node['title'] ?? '',
            ],
            'transfer_to_human' => $this->evaluarTransferenciaHumana($node),
            'next_node_id' => $nextNodeId,
            'expects_input' => false,
        ];
    }

    // DEPRECATED: ya no se usa. El type_id 5 ahora se procesa como handleInputTextNode.
    // private function handleCallButtonNode(array $node, array $context = []): array
    // {
    //     if ($this->isEmptyNode($node)) {
    //         return $this->nodeError("Nodo de llamada vacío (id={$node['id']})");
    //     }

    //     $phone = $node['data'] ?? null;

    //     if (!$phone) {
    //         return $this->nodeError("Nodo de llamada sin número definido (id={$node['id']})");
    //     }

    //     $nextNodeId = $this->hasChildren($node) ? $node['children'][0]['id'] : null;

    //     return [
    //         'reply' => "Puedes llamarnos al número: {$phone}",
    //         'rich_content' => [
    //             'type' => 'call',
    //             'phone' => $phone,
    //             'title' => $node['title'] ?? 'Llamar',
    //         ],
    //         'transfer_to_human' => false,
    //         'next_node_id' => $nextNodeId,
    //         'expects_input' => false,
    //     ];
    // }

    private function extractLabelFromData(string|array $data): string
    {
        if (is_array($data)) {
            return $data['data_node']['label'] ?? '¿Podés escribir tu respuesta?';
        }

        preg_match('/label=([^|]+)/', $data, $matches);
        return $matches[1] ?? '¿Podés escribir tu respuesta?';
    }

    private function extractOptionsFromData(string|array $data): array
    {
        if (is_array($data)) {
            return $data['data_node']['values'] ?? [];
        }

        preg_match('/values=([^|]+)/', $data, $matches);
        $values = $matches[1] ?? '';
        return array_filter(array_map('trim', explode(',', $values)));
    }

    private function nodeWithoutNextStep(
        string $reply = 'Este paso no tiene continuación definida.',
        bool $expectsInput = false,
        $currentNodeId = null,
        ?bool $transfer = null,
        array $node = [],
        array $context = []
    ): array {
        if (is_null($transfer) && isset($node['transfer_to_human']) && $node['transfer_to_human'] === true) {
            $transfer = true;
        }

        $richContent = null;
        $typeId = $node['type_id'] ?? null;
        $path = $node['data'] ?? null;
        $title = $node['title'] ?? 'Contenido';

        if ($path && in_array($typeId, [8, 9, 10, 11])) {
            $url = $this->resolverUrl($path, $context['session']);

            switch ($typeId) {
                case 8: // Imagen
                    $richContent = [
                        'type' => 'image',
                        'src' => $url,
                        'alt' => $title,
                    ];
                    $reply = '🖼️ Mira esta imagen:';
                    break;

                case 9: // Video
                    $richContent = [
                        'type' => 'video',
                        'url' => $url,
                        'title' => $title,
                    ];
                    $reply = '▶️ Mira este video:';
                    break;

                case 10: // Archivo
                    $richContent = [
                        'type' => 'file',
                        'src' => $url,
                        'title' => $title,
                    ];
                    $reply = "📎 {$title}: {$url}";
                    break;

                case 11: // Audio
                    $richContent = [
                        'type' => 'audio',
                        'src' => $url,
                    ];
                    $reply = '🎧 Escucha este audio:';
                    break;
            }

            Log::debug('[nodeWithoutNextStep] Contenido enriquecido con reply adaptado', [
                'type_id' => $typeId,
                'src' => $url,
                'reply' => $reply,
            ]);
        }

        return array_filter([
            'reply' => $reply,
            'rich_content' => $richContent,
            'transfer_to_human' => $transfer ?? false,
            'expects_input' => $expectsInput ?: null,
            'current_node_id' => $currentNodeId,
            'next_node_id' => null,
            'warning' => 'Este nodo no tiene un siguiente paso definido.',
        ], fn($v) => $v !== null);
    }

    private function nodeError(string $message): array
    {
        return [
            'reply' => 'Error: ' . $message,
            'rich_content' => null,
            'transfer_to_human' => false,
            'next_node_id' => null,
        ];
    }

    private function isEmptyNode(array $node): bool
    {
        $data = $node['data'] ?? '';
        $title = $node['title'] ?? '';

        return (is_string($data) ? trim($data) : '') === '' &&
            (is_string($title) ? trim($title) : '') === '';
    }

    private function hasChildren(array $node): bool
    {
        return isset($node['children']) && is_array($node['children']) && count($node['children']) > 0;
    }

    private function resolverUrl(string $path, BitrixSession $session): string
    {
        // Si ya es una URL completa, no procesamos nada
        if (str_starts_with($path, 'http')) {
            return $path;
        }

        // Usamos el path_base dinámico de la sesión
        $base = $session->path_base;

        $finalUrl = rtrim($base, '/') . '/' . ltrim($path, '/');

        return $finalUrl;
    }

    /**
     * Evalúa si un nodo cualquiera debe forzar transferencia a humano
     * mediante la propiedad 'transfer_to_human': true
     *
     * @param array $node
     * @return bool
     */
    private function evaluarTransferenciaHumana(array $node): bool
    {
        return isset($node['transfer_to_human']) && $node['transfer_to_human'] === true;
    }

    /**
     * Extrae el texto 'label' si el contenido viene como 'label=...|value=...'.
     */
    private function parseLabelValue($text): ?string
    {
        if (!is_string($text)) {
            Log::warning('[parseLabelValue] Se esperaba string pero se recibió:', [
                'tipo' => gettype($text),
                'contenido' => $text,
            ]);
            return '';
        }

        if (!$text) return '';

        if (str_starts_with($text, 'label=')) {
            $partes = explode('|', $text);
            foreach ($partes as $parte) {
                if (str_starts_with($parte, 'label=')) {
                    return trim(str_replace('label=', '', $parte));
                }
            }
        }

        return $text;
    }

    /**
        * Determina si un nodo requiere una entrada del usuario.
    */
    public function expectsInput(array $node): bool
    {
        $inputTypes = [5, 14, 6, 7];
        return in_array($node['type_id'], $inputTypes);
    }

}
