<?php

namespace App\Services\Bitrix;

use App\Models\BitrixSession;
use App\Services\Anima\NodeProcessor;
use App\Services\Anima\AnimaTreeService;
use App\Services\BitrixService;
use Illuminate\Support\Facades\Log;
use App\Models\BitrixMenuOption;
use App\Models\BitrixUserInput;
use App\Models\BitrixConversationThread;
use App\Services\Bitrix\BitrixOperatorService;

class BitrixFlowEngine
{
    protected BitrixSession $session;
    protected BitrixService $bitrix;
    protected BitrixOperatorService $bitrixOperatorService;
    protected AnimaTreeService $tree;
    protected NodeProcessor $processor;
    protected string $dialogId;
    protected string $hash;
    protected array $menuOptions = [];

    public function __construct(
        BitrixSession $session,
        BitrixService $bitrix,
        BitrixOperatorService $bitrixOperatorService,
        string $dialogId,
        string $hash
    ) {
        $this->session   = $session;
        $this->bitrix    = $bitrix;
        $this->bitrixOperatorService = $bitrixOperatorService;
        $this->dialogId  = $dialogId;
        $this->hash      = $hash;
        $this->tree      = new AnimaTreeService();
        $this->processor = new NodeProcessor($bitrixOperatorService, $session, $bitrix);
    }

    /**
     * Inicia el flujo conversacional desde el nodo raíz (nodo 0).
     */
    public function startFromRoot(): void
    {
        if ($this->session->transferred_to_human) {
            Log::info('[FlowEngine] Sesión transferida, no se inicia flujo');
            return;
        }

        $flow = $this->tree->fetchPartialFlow(0, $this->hash, $this->session->uid);
        $node = $flow['nodes'][0] ?? null;

        if (!$node) {
            Log::warning('[FlowEngine] Nodo raíz no encontrado (ID 0)');
            return;
        }

        Log::info('[FlowEngine] Iniciando flujo desde nodo raíz');
        $this->processChainFrom($node['id']);
    }

    /**
     * Procesa un nodo específico: envía mensajes, menús, imágenes y actualiza sesión.
     */
    public function processNode(array $node): ?array
    {
        $respuesta = $this->processor->handle($node, [
            'uid' => $this->session->uid,
            'hash' => $this->hash,
            'all_nodes' => $this->tree->fetchTree($this->hash, $this->session->uid)['nodes'] ?? [],
            'session' => $this->session,
        ]);

        // Enviar mensaje de texto si existe
        if (!empty($respuesta['reply']) && !str_starts_with($respuesta['reply'], 'http')) {
            $this->bitrix->sendBotMessage($this->dialogId, $respuesta['reply']);
            Log::debug('[FlowEngine] Enviado reply', ['reply' => $respuesta['reply']]);
        }

        // Enviar contenido enriquecido (menú o imagen)
        if (!empty($respuesta['rich_content'])) {
            $this->handleRichContent($respuesta['rich_content']);
        }

        // Actualizar la sesión con el nodo actual y el siguiente (si existe)
        $this->session->update([
            'current_node_id' => $node['id'],
            'next_node_id'    => $respuesta['next_node_id'] ?? null,
        ]);

        if (
            $this->session->status === 'awaiting_restart_option' &&
            $this->session->show_restart_menu_after
        ) {
            $options = [
                ['text' => '#. 🔄 Volver al menú principal', 'value' => 'main_menu_restart'],
                ['text' => '*. ❌ Finalizar chat', 'value' => 'end_chat'],
            ];

            $menuMessage = collect($options)->pluck('text')->implode("\n");

            $this->bitrix->sendBotMessage($this->dialogId, $menuMessage);

            $map = [
                '#' => 'main_menu_restart',
                '*' => 'end_chat',
            ];

            BitrixMenuOption::create([
                'uid' => $this->session->uid,
                'bitrix_session_id' => $this->session->id,
                'is_main_menu' => false,
                'options' => $map,
            ]);

            $this->session->update(['show_restart_menu_after' => false]);

            Log::info('[FlowEngine] Menú de reinicio enviado tras nodo IA', [
                'uid' => $this->session->uid,
            ]);
        }

        Log::debug('[FlowEngine - processNode] Estado de sesión al finalizar nodo IA', [
            'status' => $this->session->status,
            'show_restart_menu_after' => $this->session->show_restart_menu_after,
        ]);

        return $respuesta;
    }

    /**
     * Procesa y envía contenido enriquecido del nodo (menús o imágenes).
     */
    protected function handleRichContent(array $richContent): void
    {
        // Si es una imagen individual
        if (isset($richContent['type']) && $richContent['type'] === 'image') {
            $this->bitrix->sendBotImage(
                $this->dialogId,
                $richContent['src'],
                $richContent['alt'] ?? ''
            );
            Log::debug('[FlowEngine] Imagen enviada', ['src' => $richContent['src']]);
            return;
        }

        // Si es un arreglo de botones u opciones
        if (isset($richContent[0]['text'])) {

            $menuMessage = collect($richContent)
                            ->pluck('text')
                            ->implode("\n");

            $this->bitrix->sendBotMessage($this->dialogId, $menuMessage);
                
            Log::debug('[FlowEngine] Opción enviada', ['text' => $menuMessage]);

            // Guardar mapa de opciones para detección de respuestas posteriores
            $map = [];
            foreach ($richContent as $index => $item) {
                if (isset($item['text'], $item['value'])) {
                    $number = (string) ($index + 1);
                    $map[$item['text']] = $item['value']; // Opción completa con número
                    $map[$number] = $item['value'];       // Opción por número
                }
            }

            $this->storeMenuOptionsIfNeeded($map, $richContent['node'] ?? []);
        }
    }

    /**
     * Inicia el flujo desde el next_node_id almacenado en sesión.
     */
    public function startFromNextNode(): void
    {
        if ($this->session->transferred_to_human) {
            Log::info('[FlowEngine] Sesión transferida, no se inicia flujo');
            return;
        }

        if (!$this->session->next_node_id) {
            Log::info('[FlowEngine] No hay next_node_id definido en sesión');
            return;
        }

        Log::info('[FlowEngine] Iniciando flujo desde next_node_id', [
            'next_node_id' => $this->session->next_node_id,
        ]);

        $this->processChainFrom($this->session->next_node_id);
    }

    /**
     * Procesa un input si el nodo actual espera entrada del usuario (type_id = 14).
     * Devuelve true si se procesó como input, false si no aplica.
     */
    public function processFromCurrentNodeIfInput(string $userMessage): bool
    {
        if (!$this->session->current_node_id) {
            return false;
        }

        $flow = $this->tree->fetchPartialFlow(
            $this->session->current_node_id,
            $this->hash,
            $this->session->uid
        );

        $node = $flow['nodes'][0] ?? null;

        if (!$node || $node['type_id'] !== 14) {
            return false;
        }

        // Guardar input del usuario
        BitrixUserInput::create([
            'uid'     => $this->session->uid,
            'node_id' => $node['id'],
            'value'   => $userMessage,
        ]);
        Log::info('[FlowEngine] Input capturado', ['uid' => $this->session->uid, 'value' => $userMessage]);

        // Calcular siguiente nodo desde redirect o hijos
        $nextNodeId = $node['redirect_item'] ?? ($node['children'][0]['id'] ?? null);

        if ($nextNodeId) {
            $this->processChainFrom($nextNodeId);
        }

        return true;
    }

    /**
     * Procesa el mensaje del usuario como selección de menú, si corresponde.
     * Devuelve true si fue una selección válida y se procesó.
     */
    public function startFromMenuSelection(string $userMessage): bool|array
    {
        // Protección contra interacciones tras transferencia
        if ($this->session->transferred_to_human) {
            Log::info('[FlowEngine] Sesión transferida a humano. Ignorando selección de menú.', [
                'uid' => $this->session->uid,
            ]);

            return false;
        }

        $menuOptions = optional(
            BitrixMenuOption::where('uid', $this->session->uid)
                ->where('bitrix_session_id', $this->session->id)
                ->orderByDesc('id')
                ->first()
        )->options ?? [];

        if (empty($menuOptions) || !isset($menuOptions[$userMessage])) {
            return false;
        }

        $selectedNodeId = $menuOptions[$userMessage];

        // Manejo explícito de opción para finalizar chat
        if (is_string($selectedNodeId) && strtolower(trim($selectedNodeId)) === 'end_chat') {

            BitrixMenuOption::where('bitrix_session_id', $this->session->id)->delete();

            Log::info('[FlowEngine] Todos los menús eliminados (incluyendo principal)', [
                'uid' => $this->session->uid,
                'session_id' => $this->session->id,
            ]);

            try {
                \App\Services\Bitrix\BitrixSessionFinalizerService::finalizarSesionYNotificar($this->session);
            } catch (\Throwable $e) {
                Log::error('[FlowEngine] Error al notificar cierre a Ánima (end_chat)', [
                    'uid' => $this->session->uid,
                    'error' => $e->getMessage(),
                ]);
            }

            $this->bitrix->sendBotMessage($this->dialogId, '✅ El chat ha finalizado. ¡Gracias por tu consulta!');
            Log::info('[FlowEngine] Chat finalizado por elección del usuario', [
                'uid' => $this->session->uid,
            ]);

            return true;
        }

        Log::info('[FlowEngine] Usuario seleccionó opción de menú', [
            'text'    => $userMessage,
            'node_id' => $selectedNodeId,
        ]);

        // Verificar si el siguiente nodo es tipo input (type_id = 14)
        $nextFlow = (new AnimaTreeService())->fetchPartialFlow((int) $selectedNodeId, $this->hash, $this->session->uid);
        $nextNode = $nextFlow['nodes'][0] ?? null;

        $isInput = $nextNode && $nextNode['type_id'] === 14;

        if (!$isInput) {
            \App\Models\BitrixMenuOption::where('bitrix_session_id', $this->session->id)
                ->where('is_main_menu', false)
                ->delete();
        } else {
            Log::info('[FlowEngine] Nodo siguiente es input, se conserva menú temporalmente', [
                'node_id' => $selectedNodeId,
            ]);
        }

        // Si el valor no es numérico, probablemente sea una respuesta tipo input (Sí, No, etc.)
        if (!is_numeric($selectedNodeId)) {
            Log::info('[FlowEngine] Opción textual detectada. Esperando nodo actual para enviar input directamente.', [
                'uid' => $this->session->uid,
                'selected' => $selectedNodeId,
            ]);

            return $this->processUserMessage($selectedNodeId); 
        }

        $this->processChainFrom((int) $selectedNodeId);

        return true;
    }

    /**
     * Retorna la respuesta final para enviar al usuario (Bitrix).
     */
    public function sendFinalResponse(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'reply'              => $this->respuesta['reply'] ?? null,
            'rich_content'       => $this->respuesta['rich_content'] ?? null,
            'transfer_to_human'  => $this->respuesta['transfer_to_human'] ?? false,
        ]);
    }

    public function processUserMessage(string $message): array
    {
        $this->menuOptions = optional(
            BitrixMenuOption::where('uid', $this->session->uid)->first()
        )->options ?? [];

        $processor = $this->processor;
        $tree = $this->tree;

        // Si la sesión ya fue transferida a humano, no procesar más mensajes
        if ($this->session->transferred_to_human) {
            return $this->handleTransferToHuman();
        }

        // Comando para reiniciar desde menú principal
        if ($message === 'main_menu_restart') {
            return $this->handleMainMenuRestart();
        }

        if ($this->startFromMenuSelection($message)) {
            // Protección: si se ejecutó reinicio o fin de chat, no continuar con IA ni flujo
            return [
                'reply' => null,
                'transfer_to_human' => false,
            ];
        }

        // Validar si el mensaje corresponde a las opciones del menú de reinicio
        $restartResponse = $this->handleRestartOptions($message);
        if ($restartResponse) {
            return $restartResponse;
        }

        // Detectar cualquier mensaje como pregunta abierta durante menú de reinicio
        $openRestart = $this->handleOpenQuestionDuringRestart($message);
        if ($openRestart) {
            return $openRestart;
        }

        // Detectar cualquier mensaje como pregunta abierta cuando no hay menú activo
        $response = $this->handleOpenQuestionWithoutMenu($message);
        if ($response) {
            return $response;
        }

        // Detectar mensaje libre cuando hay menú activo y no coincide con ninguna opción
        $response = $this->handleOpenQuestionWithMenu($message);
        if ($response) {
            return $response;
        }

        // Manejo normal de input en nodo actual
        $response = $this->handleMenuMessage($message);
        if ($response) {
            return $response;
        }

        // Manejo especial para nodos IA con contexto (type_id = 91)
        $response = $this->handleIaNodeWithContext($message);
        if ($response) {
            return $response;
        }

        // Manejo especial para nodos que esperan input (type_id 5, 14)
        $response = $this->handleExpectedInputResponse($message);
        if ($response) {
            return $response;
        }

        // Manejo especial para mostrar menú de reinicio si aplica
        $response = $this->handleRestartMenuDisplay();
        if ($response) {
            return $response;
        }

        // fallback para nodos raíz o nulos
        $response = $this->handleFallbackNodeChain();
        if ($response) {
            return $response;
        }

        // Respuesta por defecto si nada aplica
        if ($this->session->status === 'awaiting_restart_option') {
            return $this->handleAwaitingRestartFallback();
        }

        // Si estamos en nodo virtual IA, redirigimos directamente al handler personalizado
        if (
            (int) $this->session->current_node_id === 999999 &&
            !($this->session->status === 'awaiting_restart_option' && in_array(trim($message), ['#', '*'], true))
        ) {
            return $this->reprocessVirtualIANode($message);
        }

        // Detección de pregunta abierta (IA con contexto)
        if ($this->session->current_node_id) {
            return $this->handleOpenQuestionWithNode($message);
        }

        return $this->handleFallback($message);
    }

    /**
     * Procesa un nodo y su cadena (next_node, hijos, menús).
     */
    private function processNodeChain(array $node, NodeProcessor $processor, AnimaTreeService $tree): array
    {
        $respuesta = $processor->handle($node, [
            'uid' => $this->session->uid,
            'hash' => $this->hash,
            'message' => null,
            'all_nodes' => $tree->fetchTree($this->hash, $this->session->uid)['nodes'] ?? [],
            'session' => $this->session,
        ]);

        if (!empty($respuesta['reply']) && !str_starts_with($respuesta['reply'], 'http')) {
            $this->bitrix->sendBotMessage($this->dialogId, $respuesta['reply']);
        } elseif ($this->session->status === 'awaiting_restart_option' && $this->session->show_restart_menu_after) {
            // Enviar mensaje genérico si no hay respuesta y toca mostrar opciones
            $this->bitrix->sendBotMessage($this->dialogId, '🟡 Hemos llegado al final del recorrido.');
        }

        if (!empty($respuesta['rich_content'])) {
            if (is_array($respuesta['rich_content']) && isset($respuesta['rich_content']['type'])) {
                if ($respuesta['rich_content']['type'] === 'image') {
                    $this->bitrix->sendBotImage($this->dialogId, $respuesta['rich_content']['src'], $respuesta['rich_content']['alt'] ?? '');
                } elseif ($respuesta['rich_content']['type'] === 'video') {
                    $texto = "▶️ " . ($respuesta['rich_content']['title'] ?? 'Video') . "\n" . ($respuesta['rich_content']['url'] ?? '');
                    $this->bitrix->sendBotMessage($this->dialogId, $texto);
                    Log::debug('[FlowEngine] Video enviado', ['url' => $respuesta['rich_content']['url'] ?? '']);
                } elseif ($respuesta['rich_content']['type'] === 'audio') {
                    Log::debug('[BitrixFlowEngine] Audio URL antes de enviar', [
                        'src' => $respuesta['rich_content']['src'] ?? 'no-src'
                    ]);
                    $this->bitrix->sendBotAudio($this->dialogId, $respuesta['rich_content']['src'], $respuesta['reply'] ?? 'Audio');
                    Log::debug('[FlowEngine] Audio enviado', ['src' => $respuesta['rich_content']['src']]);
                }
            } elseif (isset($respuesta['rich_content'][0]['text'])) {
                $menuMessage = collect($respuesta['rich_content'])->pluck('text')->implode("\n");
                $this->bitrix->sendBotMessage($this->dialogId, $menuMessage);

                $map = [];
                $index = 1;
                foreach ($respuesta['rich_content'] as $item) {
                    if (isset($item['text'], $item['value'])) {
                        $map[$item['text']] = $item['value'];
                        $map[(string) $index] = $item['value'];
                        $index++;
                    }
                }

                $this->storeMenuOptionsIfNeeded($map, $node);
            }
        }

        $this->session->update([
            'current_node_id' => $node['id'],
            'next_node_id' => $respuesta['next_node_id'] ?? null,
        ]);

        // Encadenar siguientes nodos
        while (array_key_exists('next_node_id', $respuesta) && !empty($respuesta['next_node_id']) && empty($respuesta['expects_input'])) {
            $nextFlow = $tree->fetchPartialFlow($respuesta['next_node_id'], $this->hash, $this->session->uid);
            $nextNode = $nextFlow['nodes'][0] ?? null;

            if (!$nextNode) break;

            $respuesta = $processor->handle($nextNode, [
                'uid' => $this->session->uid,
                'hash' => $this->hash,
                'message' => null,
                'all_nodes' => $tree->fetchTree($this->hash, $this->session->uid)['nodes'] ?? [],
                'session' => $this->session,
            ]);

            if (!empty($respuesta['reply']) && !str_starts_with($respuesta['reply'], 'http')) {
                $this->bitrix->sendBotMessage($this->dialogId, $respuesta['reply']);
            }

            if (array_key_exists('rich_content', $respuesta)) {
                if (!empty($respuesta['rich_content'])) {
                    if (is_array($respuesta['rich_content']) && isset($respuesta['rich_content']['type'])) {
                        if ($respuesta['rich_content']['type'] === 'image') {
                            $this->bitrix->sendBotImage($this->dialogId, $respuesta['rich_content']['src'], $respuesta['rich_content']['alt'] ?? '');
                        } elseif ($respuesta['rich_content']['type'] === 'video') {
                            $texto = "▶️ " . ($respuesta['rich_content']['title'] ?? 'Video') . "\n" . ($respuesta['rich_content']['url'] ?? '');
                            $this->bitrix->sendBotMessage($this->dialogId, $texto);
                            Log::debug('[FlowEngine] Video enviado', ['url' => $respuesta['rich_content']['url'] ?? '']);
                        } elseif ($respuesta['rich_content']['type'] === 'audio') {
                            Log::debug('[BitrixFlowEngine] Audio URL antes de enviar', [
                                'src' => $respuesta['rich_content']['src'] ?? 'no-src'
                            ]);
                            $this->bitrix->sendBotAudio($this->dialogId, $respuesta['rich_content']['src'], $respuesta['reply'] ?? 'Audio');
                            Log::debug('[FlowEngine] Audio enviado (encadenado)', ['src' => $respuesta['rich_content']['src']]);
                        }
                    } elseif (isset($respuesta['rich_content'][0]['text'])) {
                        $menuMessage = collect($respuesta['rich_content'])->pluck('text')->implode("\n");
                        $this->bitrix->sendBotMessage($this->dialogId, $menuMessage);

                        $map = [];
                        $index = 1;
                        foreach ($respuesta['rich_content'] as $item) {
                            if (isset($item['text'], $item['value'])) {
                                $map[$item['text']] = $item['value'];
                                $map[(string) $index] = $item['value'];
                                $index++;
                            }
                        }

                        $this->storeMenuOptionsIfNeeded($map, $node);
                    }
                }
            } else {
                Log::debug('[FlowEngine] Rich content ausente en respuesta del nodo encadenado');
            }

            $this->session->update([
                'current_node_id' => $nextNode['id'],
                'next_node_id' => $respuesta['next_node_id'] ?? null,
            ]);
        }

        // Mostrar menú de reinicio si la sesión lo requiere (al final del flujo)
        $this->session->refresh();

        $hasMenuOptions = isset($respuesta['rich_content'][0]['text'], $respuesta['rich_content'][0]['value']);

        // Si la sesión fue transferida a humano, nunca forzamos reinicio
        if ($this->session->transferred_to_human) {
            Log::info('[FlowEngine] La sesión fue transferida a humano. No se aplicará reinicio automático ni cambios de estado.', [
                'uid' => $this->session->uid,
            ]);
            return $respuesta;
        }

        if (
            !array_key_exists('next_node_id', $respuesta) ||
            (
                empty($respuesta['next_node_id']) &&
                !$this->session->transferred_to_human &&
                !($respuesta['expects_input'] ?? false) &&
                !$hasMenuOptions &&
                in_array($node['type_id'], [8, 9, 10, 11, 12, 15, 91])
            )
        ) {
            $this->session->update([
                'status' => 'awaiting_restart_option',
                'show_restart_menu_after' => true,
            ]);

            Log::info('[FlowEngine] Último nodo alcanzado sin hijos ni input. Forzando menú de reinicio.', [
                'uid' => $this->session->uid,
                'node_id' => $node['id'],
                'type_id' => $node['type_id'],
            ]);
        }

        if (
            $this->session->status === 'awaiting_restart_option' &&
            $this->session->show_restart_menu_after
        ) {
            $options = [
                ['text' => '#. 🔄 Volver al menú principal', 'value' => 'main_menu_restart'],
                ['text' => '*. ❌ Finalizar chat', 'value' => 'end_chat'],
            ];

            $menuMessage = collect($options)->pluck('text')->implode("\n");

            $this->bitrix->sendBotMessage($this->dialogId, $menuMessage);

            $map = [
                '#' => 'main_menu_restart',
                '*' => 'end_chat',
            ];

            BitrixMenuOption::create([
                'uid' => $this->session->uid,
                'bitrix_session_id' => $this->session->id,
                'is_main_menu' => false,
                'options' => $map,
            ]);

            $this->session->update(['show_restart_menu_after' => false]);

            Log::info('[FlowEngine] Menú de reinicio enviado tras último nodo', [
                'uid' => $this->session->uid,
            ]);
        }

        return $respuesta;
    }

    public function processChainFrom(int $nodeId): array
    {
        $tree = $this->tree;

        $processor = $this->processor;

        $flow = $tree->fetchPartialFlow($nodeId, $this->hash, $this->session->uid);
        $node = $flow['nodes'][0] ?? null;

        if (!$node) {
            Log::warning('[FlowEngine] Nodo no encontrado en processChainFrom', ['node_id' => $nodeId]);
            return [
                'reply' => 'No se encontró el siguiente paso.',
                'transfer_to_human' => true,
            ];
        }

        return $this->processNodeChain($node, $processor, $tree);
    }

    /**
     * Guarda las opciones de menú si aún no existen para el uid actual.
     */
    private function storeMenuOptionsIfNeeded(array $map, array $node = []): void
    {
        if (empty($map)) {
            return;
        }

        $nodeId = $node['id'] ?? null;

        // 1. Verificar si ya existe un menú principal para esta sesión
        $alreadyHasMain = BitrixMenuOption::where('bitrix_session_id', $this->session->id)
            ->where('is_main_menu', true)
            ->exists();

        $isMainMenu = false;

        // 2. Detectar si este nodo debe ser considerado menú principal
        if (!$alreadyHasMain && $nodeId) {
            $existingMain = BitrixMenuOption::where('bitrix_session_id', $this->session->id)
                ->where('node_id', $nodeId)
                ->where('is_main_menu', true)
                ->first();

            if (!$existingMain) {
                $isMainMenu = true;
            } else {
                Log::info('[FlowEngine] Nodo ya registrado como menú principal. Se omite duplicado.', [
                    'uid' => $this->session->uid,
                    'node_id' => $nodeId,
                ]);
                return;
            }
        }

        // 3. Si ya existe un menú principal y se está intentando guardar otro como principal, abortar
        if ($alreadyHasMain && !$isMainMenu && isset($nodeId)) {
            $isTryingToReplaceMain = BitrixMenuOption::where('bitrix_session_id', $this->session->id)
                ->where('node_id', $nodeId)
                ->where('is_main_menu', false)
                ->exists();

            if ($isTryingToReplaceMain) {
                Log::info('[FlowEngine] Ya existe un menú principal. Este menú no se guardará como principal ni reemplazará.', [
                    'uid' => $this->session->uid,
                    'node_id' => $nodeId,
                ]);
                return;
            }
        }

        // 4. Verificar si ya se guardó un menú secundario igual
        if (!$isMainMenu) {
            $alreadyStored = BitrixMenuOption::where('uid', $this->session->uid)
                ->where('bitrix_session_id', $this->session->id)
                ->where('options', json_encode($map))
                ->where('is_main_menu', false)
                ->exists();

            if ($alreadyStored) {
                Log::info('[FlowEngine] Este menú secundario ya fue guardado. Se omite.', [
                    'uid' => $this->session->uid,
                    'session_id' => $this->session->id,
                ]);
                return;
            }
        }

        // 5. Guardar el menú
        Log::debug('[storeMenuOptionsIfNeeded] Insertando menú', [
            'uid' => $this->session->uid,
            'bitrix_session_id' => $this->session->id,
            'node_id' => $nodeId,
            'is_main_menu' => $isMainMenu,
            'map' => $map,
        ]);

        BitrixMenuOption::create([
            'uid' => $this->session->uid,
            'options' => $map,
            'bitrix_session_id' => $this->session->id,
            'node_id' => $nodeId,
            'is_main_menu' => $isMainMenu,
        ]);

        Log::info($isMainMenu ? '[FlowEngine] ✅ Menú principal guardado' : '[FlowEngine] ✅ Menú secundario guardado', [
            'uid' => $this->session->uid,
            'session_id' => $this->session->id,
            'node_id' => $nodeId,
            'map' => $map,
        ]);
    }

    // Maneja la transferencia a humano
    private function handleTransferToHuman(): array
    {
        Log::info('[FlowEngine] Sesión transferida a humano, no se procesan más mensajes', [
            'uid' => $this->session->uid,
        ]);

        return [
            'reply' => null,
            'transfer_to_human' => true,
        ];
    }

    // Maneja el reinicio desde el menú principal
    private function handleMainMenuRestart(): array
    {
        $mainMenu = BitrixMenuOption::where('bitrix_session_id', $this->session->id)
            ->where('is_main_menu', true)
            ->first();

        if ($mainMenu && $mainMenu->node_id) {
            Log::info('[FlowEngine] Reiniciando desde menú principal', [
                'uid' => $this->session->uid,
                'node_id' => $mainMenu->node_id,
            ]);

            BitrixMenuOption::where('bitrix_session_id', $this->session->id)
                ->where('is_main_menu', false)
                ->delete();

            $this->session->update([
                'status' => 'active',
                'show_restart_menu_after' => true,
            ]);

            return $this->processChainFrom((int) $mainMenu->node_id);
        }

        return [
            'reply' => 'No se encontró el menú principal para reiniciar.',
            'transfer_to_human' => false,
        ];
    }

    // Maneja las opciones de reinicio (# para reiniciar, * para finalizar)
    private function handleRestartOptions(string $message): ?array
    {
        if (
            $this->session->status === 'awaiting_restart_option'
            && in_array(trim($message), ['#', '*'])
        ) {
            if ($message === '#') {
                return $this->processUserMessage('main_menu_restart');
            }

            if ($message === '*') {
                $this->session->update([
                    'status' => 'closed',
                ]);

                return [
                    'reply' => '✅ El chat ha sido finalizado. ¡Gracias por comunicarte con nosotros!',
                    'transfer_to_human' => false,
                ];
            }
        }

        return null;
    }

    // Maneja preguntas abiertas cuando se está en estado de reinicio
    private function handleOpenQuestionDuringRestart(string $message): ?array
    {
        if ($this->session->status !== 'awaiting_restart_option') {
            return null;
        }

        $msg = trim($message);
        if (in_array($msg, ['#', '*'], true)) {
            if ($msg === '#') {
                return $this->startFromMenuSelection('main_menu_restart');
            } elseif ($msg === '*') {
                return $this->startFromMenuSelection('end_chat');
            }
        }

        // Redirigir a IA como pregunta abierta
        $this->session->update([
            'current_node_id' => 999999,
            'next_node_id' => null,
        ]);

        return $this->processNode([
            'id' => 999999,
            'type_id' => 91,
            'title' => 'IA Virtual',
            'message' => $message,
        ]);
    }

    // Maneja preguntas abiertas cuando no hay menú activo
    private function handleOpenQuestionWithoutMenu(string $message): ?array
    {
        if (!empty($this->menuOptions)) {
            return null;
        }

        // 1. Evitar error al inicio del chat si no hay current_node_id
        if (is_null($this->session->current_node_id)) {
            return null;
        }

        // 2. Verificar si el nodo actual espera un input explícito
        $flow = $this->tree->fetchPartialFlow($this->session->current_node_id, $this->hash, $this->session->uid);
        $node = $flow['nodes'][0] ?? null;
        $typeId = $node['type_id'] ?? null;

        if (in_array($typeId, [5, 14])) {
            return null;
        }

        // 3. Verificar si la pregunta ya existe sin responder
        $existsUnanswered = BitrixConversationThread::where('uid', $this->session->uid)
            ->where('bitrix_session_id', $this->session->id)
            ->where(function ($query) use ($message) {
                $query->whereRaw('LOWER(user_message) = ?', [strtolower($message)])
                    ->orWhereRaw('? LIKE CONCAT("%", LOWER(user_message), "%")', [strtolower($message)]);
            })
            ->orderByDesc('id')
            ->first();

        if ($existsUnanswered) {
            Log::info('[FlowEngine - 444] Pregunta duplicada sin responder detectada. Se omite crear nuevo thread.', [
                'uid' => $this->session->uid,
                'message' => $message,
            ]);
            return [
                'reply' => 'Tu pregunta anterior sigue siendo procesada. Por favor, espera o reformúlala.',
                'transfer_to_human' => false,
            ];
        }

        // 4. Crear nuevo thread
        BitrixConversationThread::create([
            'uid' => $this->session->uid,
            'bitrix_session_id' => $this->session->id,
            'node_id' => 999999,
            'user_message' => $message,
            'thread_id' => null,
            'is_answered' => false,
        ]);

        $this->session->update([
            'current_node_id' => 999999,
            'next_node_id' => null,
        ]);

        Log::info('[FlowEngine - 470] Nodo virtual IA enviado sin menú activo', [
            'uid' => $this->session->uid,
            'message' => $message,
        ]);

        return $this->processNode([
            'id' => 999999,
            'type_id' => 91,
            'title' => 'IA Virtual',
            'message' => $message,
        ]);
    }

    // Maneja preguntas abiertas cuando hay menú activo
    private function handleOpenQuestionWithMenu(string $message): ?array
    {
        if (empty($this->menuOptions) || isset($this->menuOptions[$message])) {
            return null;
        }

        $flow = $this->tree->fetchPartialFlow($this->session->current_node_id, $this->hash, $this->session->uid);
        $node = $flow['nodes'][0] ?? null;
        $typeId = $node['type_id'] ?? null;

        if (in_array($typeId, [5, 14])) {
            Log::debug('[FlowEngine] Nodo actual con menú activo pero espera input (tipo 5 o 14). Se omite IA.', [
                'uid' => $this->session->uid,
                'current_node_id' => $this->session->current_node_id,
                'type_id' => $typeId,
            ]);
            return null;
        }

        $existsUnanswered = BitrixConversationThread::where('uid', $this->session->uid)
            ->where('bitrix_session_id', $this->session->id)
            ->where(function ($query) use ($message) {
                $query->whereRaw('LOWER(user_message) = ?', [strtolower($message)])
                    ->orWhereRaw('? LIKE CONCAT("%", LOWER(user_message), "%")', [strtolower($message)]);
            })
            ->orderByDesc('id')
            ->first();

        if ($existsUnanswered) {
            return [
                'reply' => 'Tu pregunta anterior sigue siendo procesada. Por favor, espera o reformúlala.',
                'transfer_to_human' => false,
            ];
        }

        BitrixConversationThread::create([
            'uid' => $this->session->uid,
            'bitrix_session_id' => $this->session->id,
            'node_id' => 999999,
            'user_message' => $message,
            'thread_id' => null,
            'is_answered' => false,
        ]);

        $this->session->update([
            'current_node_id' => 999999,
            'next_node_id' => null,
        ]);

        Log::info('[FlowEngine - 510] Nodo IA enviado con menú activo, mensaje libre', [
            'uid' => $this->session->uid,
            'message' => $message,
        ]);

        return $this->processNode([
            'id' => 999999,
            'type_id' => 91,
            'title' => 'IA Virtual',
            'message' => $message,
        ]);
    }

    // Maneja selección de opciones de menú
    private function handleMenuMessage(string &$message): ?array
    {
        if (empty($this->menuOptions) || !isset($this->menuOptions[$message])) {
            return null;
        }

        Log::debug('[FlowEngine] Opción de menú detectada', ['message' => $message]);

        $selectedNodeId = $this->menuOptions[$message];

        // Reemplazar número por texto si aplica
        foreach ($this->menuOptions as $key => $val) {
            if (!is_numeric($key) && $val == $selectedNodeId) {
                Log::info('[FlowEngine] Reemplazo input numérico por texto del menú', [
                    'input_original' => $message,
                    'valor_reemplazo' => $key,
                ]);
                $message = $key;
                break;
            }
        }

        // Eliminar menús secundarios
        BitrixMenuOption::where('bitrix_session_id', $this->session->id)
            ->where('is_main_menu', false)
            ->delete();

        $flow = $this->tree->fetchPartialFlow($selectedNodeId, $this->hash, $this->session->uid);
        $node = $flow['nodes'][0] ?? null;

        if ($node) {
            return $this->processNodeChain($node, $this->processor, $this->tree);
        }

        return null;
    }

    // Maneja preguntas abiertas cuando el nodo actual es IA contextual
    private function handleIaNodeWithContext(string $message): ?array
    {
        if (
            (int) $this->session->current_node_id !== 999999 ||
            ($this->session->status === 'awaiting_restart_option' && in_array(trim($message), ['#', '*'], true))
        ) {
            return null;
        }

        return $this->processNode([
            'id' => 999999,
            'type_id' => 91,
            'title' => 'IA Virtual',
            'message' => $message,
        ]);
    }

    // Maneja respuestas a nodos que esperan input (tipo 5 o 14)
    private function handleExpectedInputResponse(string $message): ?array
    {
        if (!$this->session->current_node_id) {
            return null;
        }

        $tree = $this->tree;
        $processor = $this->processor;

        $flow = $tree->fetchPartialFlow($this->session->current_node_id, $this->hash, $this->session->uid);
        $node = $flow['nodes'][0] ?? null;

        if (!$node || !in_array($node['type_id'], [5, 14])) {
            return null;
        }

        BitrixUserInput::create([
            'uid' => $this->session->uid,
            'node_id' => $node['id'],
            'value' => $message,
        ]);

        BitrixMenuOption::where('bitrix_session_id', $this->session->id)
            ->where('is_main_menu', false)
            ->delete();

        $apiResponse = $tree->postInputAnswer($this->hash, $this->session->uid, $node['id'], $message);
        $nextItem = $apiResponse['next_item'] ?? null;
        $firstChild = $nextItem['children'][0] ?? null;

        if ($nextItem) {
            if (
                $nextItem['id'] === $this->session->current_node_id &&
                $processor->expectsInput($nextItem)
            ) {
                if ($firstChild) {
                    $this->session->update([
                        'current_node_id' => $firstChild['id'],
                        'next_node_id' => null,
                    ]);

                    BitrixMenuOption::where('bitrix_session_id', $this->session->id)
                        ->where('is_main_menu', false)
                        ->delete();

                    return $this->processChainFrom($firstChild['id']);
                }

                $this->session->update([
                    'current_node_id' => $nextItem['id'],
                    'next_node_id' => null,
                ]);

                return [
                    'reply' => null,
                    'transfer_to_human' => false,
                ];
            }

            $this->session->update([
                'current_node_id' => $nextItem['id'],
                'next_node_id' => $firstChild['id'] ?? null,
            ]);

            return $processor->handle($nextItem, [
                'uid' => $this->session->uid,
                'hash' => $this->hash,
                'message' => $message,
                'all_nodes' => [$node, $nextItem],
                'session' => $this->session,
            ]);
        }

        Log::warning('[FlowEngine - 707] No se pudo obtener el siguiente nodo desde el API de Ánima', [
            'node_id' => $node['id'],
            'message' => $message,
        ]);

        return [
            'reply' => 'Gracias por tu respuesta.',
            'transfer_to_human' => false,
        ];
    }

    // Manejo especial para mostrar menú de reinicio si aplica
    private function handleRestartMenuDisplay(): ?array
    {
        if ($this->session->status !== 'awaiting_restart_option') {
            return null;
        }

        $map = [
            '#' => 'main_menu_restart',
            '*' => 'end_chat',
        ];

        BitrixMenuOption::create([
            'uid' => $this->session->uid,
            'bitrix_session_id' => $this->session->id,
            'is_main_menu' => false,
            'options' => $map,
        ]);

        return [
            'reply' => "#. 🔄 Volver al menú principal\n*. ❌ Finalizar chat",
            'rich_content' => null,
            'transfer_to_human' => false,
        ];
    }

    // Fallback para nodos raíz o nulos
    private function handleFallbackNodeChain(): ?array
    {
        $nextId = $this->session->next_node_id ?? $this->session->current_node_id;

        $flow = $this->tree->fetchPartialFlow($nextId ?? 0, $this->hash, $this->session->uid);
        $node = $flow['nodes'][0] ?? null;

        BitrixMenuOption::where('bitrix_session_id', $this->session->id)
            ->where('is_main_menu', false)
            ->delete();

        if ($node) {
            return $this->processNodeChain($node, $this->processor, $this->tree);
        }

        return null;
    }

    // Fallback específico para estado awaiting_restart_option sin menú guardado
    private function handleAwaitingRestartFallback(): array
    {
        $exists = BitrixMenuOption::where('bitrix_session_id', $this->session->id)
            ->where('is_main_menu', false)
            ->whereJsonContains('options->*', 'end_chat')
            ->exists();

        if (!$exists) {
            BitrixMenuOption::create([
                'uid' => $this->session->uid,
                'options' => [
                    '#' => 'main_menu_restart',
                    '*' => 'end_chat',
                ],
                'bitrix_session_id' => $this->session->id,
                'is_main_menu' => false,
            ]);
        }

        return [
            'reply' => "#. 🔄 Volver al menú principal\n*. ❌ Finalizar chat",
            'transfer_to_human' => false,
        ];
    }

    // Reprocesa un nodo virtual IA (999999) con un nuevo mensaje
    private function reprocessVirtualIANode(string $message): array
    {
        return $this->processor->handleCustomAiNode([
            'id' => 999999,
            'type_id' => 91,
            'title' => 'IA Virtual',
            'message' => $message,
        ], [
            'uid' => $this->session->uid,
            'hash' => $this->hash,
            'message' => $message,
        ]);
    }

    // Maneja preguntas abiertas cuando el nodo actual es distinto de IA contextual
    private function handleOpenQuestionWithNode(string $message): array
    {
        $flow = $this->tree->fetchPartialFlow($this->session->current_node_id, $this->hash, $this->session->uid);
        $node = $flow['nodes'][0] ?? null;

        if (!$node) {
            return [
                'reply' => 'No se pudo recuperar el nodo actual para procesar tu mensaje.',
                'transfer_to_human' => true,
            ];
        }

        // Guardar mensaje en tabla de conversación
        BitrixConversationThread::create([
            'uid' => $this->session->uid,
            'bitrix_session_id' => $this->session->id,
            'node_id' => $node['id'],
            'user_message' => $message,
            'thread_id' => null,
            'is_answered' => false,
        ]);

        $virtualNode = [
            'id' => 999999,
            'type_id' => 91,
            'title' => 'IA Virtual',
            'data' => null,
        ];

        return $this->processNode($virtualNode);
    }

    // Fallback genérico si nada aplica
    private function handleFallback(string $message): array
    {
        Log::warning('[FlowEngine] Fallback activado: mensaje no reconocido', [
            'uid' => $this->session->uid,
            'message' => $message,
        ]);

        return [
            'reply' => 'Perdón, no entendí tu mensaje. ¿Podés intentar otra vez o escribir "ayuda"?',
            'transfer_to_human' => true,
        ];
    }
}
