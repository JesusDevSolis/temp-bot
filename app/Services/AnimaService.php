<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AnimaService
{
    // URL base de la API de Ánima (definida en config/services.php → anima.url)
    protected string $baseUrl;
    // Token de autenticación Bearer para llamar a la API de Ánima (config/services.php → anima.token)
    protected string $authToken;

    public function __construct()
    {
        // Leer configuración desde .env a través de config/services.php
        // Se recomienda no usar el helper env() directamente en el código
        // porque no se puede cachear la configuración
        $this->baseUrl = config('services.anima.url');
        $this->authToken = config('services.anima.token');
    }

    /**
     * Realiza la llamada HTTP a un endpoint de Ánima.
     *
     * @param string $uri Ruta después de /api/v1.0.0
     * @param array $payload, Datos a enviar en el cuerpo de la petición (body JSON)
     * @return array El JSON decodificado como array
     */
    protected function call(string $uri, array $payload = []): array
    {
        // Construir URL completa, asegurando que no haya doble slash
        // y que no falte el slash al final   
        $url = rtrim($this->baseUrl, '/') . '/api/v1.0.0' . $uri;
        // Enviar POST con Bearer token y cabecera adicional BearerToken
        // (la cabecera BearerToken es para el middleware de Ánima)
        // La cabecera Authorization es para el middleware de Laravel
        $response = Http::withToken($this->authToken)
            ->withHeaders(['BearerToken' => $this->authToken])
            ->asJson()
            ->post($url, $payload);

        // Intentar decodificar el body como JSON, cualquiera 
        // sea el status code (200, 400, 404, 500…)
        try {
            $result = $response->json();
            // Asegurar que siempre sea un array
            if (!is_array($result)) {
                throw new \Exception("Respuesta no es JSON válido");
            }
            return $result;
        } catch (\Exception $e) {
            // Si la respuesta no es JSON, devolver un error genérico con el status HTTP
            return [
                'status'  => 'ERROR',
                'message' => 'HTTP '.$response->status(),
            ];
        }

        return $result;
    }

    /**
     * Asegura o crea el OpGroup en Ánima.
     * Si el OpGroup no existe, lo crea y devuelve su ID.
     * Si existe, lo devuelve sin modificar.
     * Si el OpGroup no es válido, devuelve un error.
     */
    public function connect(array $data): array
    {
        return $this->call('/chat/connect', $data);
    }
    /**
     * Notifica a Ánima (broadcast) que hay un nuevo OpGroup.
     * Se usa para que Ánima sepa que hay un nuevo OpGroup
     * y lo muestre en la lista de chats.
     */
    public function insert(array $data): array
    {
        return $this->call('/chat/insert', $data);
    }
    /**
     * Registra al usuario en el OpGroup (primera vez que entra).
     * Se usa para que Ánima sepa que el usuario está en el OpGroup
     * y lo muestre en la lista de chats.
     * Si el usuario ya está en el OpGroup, no hace nada.
     */
    public function newUser(array $data): array
    {
        return $this->call('/chat/new-user', $data);
    }
    /**
     * Asigna un operador (o bot) al OpGroup, activando el chat.
     * El operador puede ser un bot o un humano.
     * Si el operador ya está asignado, no hace nada.
     * Si el operador no existe, lo crea y lo asigna.
     * Si el OpGroup no existe, devuelve un error.
     */
    public function assign(array $data): array
    {
        return $this->call('/chat/assign', $data);
    }

    /**
     * Envía un mensaje al chat en Ánima.
     * El mensaje puede ser de un usuario o de un operador.
     * Si el OpGroup no existe, devuelve un error.
     *
     * @param array $data Debe incluir:
     *   - type: 'user' o 'op'
     *   - userId
     *   - message
     */
    public function sendMessage(array $data): array
    {
        $type = $data['type'] ?? 'user';
        $payload = [
            'userId'  => $data['userId'],
            'message' => $data['message'],
            'origen'  => $data['origen'] ?? 'bitrix',
        ];
        return $this->call("/chat/send/{$type}", $payload);
    }
    /**
     * Desasigna un operador, dejando el chat pendiente.
     * El operador puede ser un bot o un humano.
     * Si el operador no está asignado, no hace nada.
     * Si el OpGroup no existe, devuelve un error.
     */
    public function unassign(array $data): array
    {
        return $this->call('/chat/unassign', $data);
    }
    /**
     * Cancela el chat.
     * El operador puede ser un bot o un humano.
     * Si el operador no está asignado, no hace nada.
     * Si el OpGroup no existe, devuelve un error.
     */
    public function cancel(array $data): array
    {
        return $this->call('/chat/cancel', $data);
    }
    /**
     * Marca el chat como finalizado.
     * El operador puede ser un bot o un humano.
     * Si el operador no está asignado, no hace nada.
     * Si el OpGroup no existe, devuelve un error.
     */
    public function finalized(array $data): array
    {
        return $this->call('/chat/finalized', $data);
    }
    /**
     * Obtiene estadísticas generales de chats.
     */
    public function statisticsGeneral(array $data): array
    {
        return $this->call('/chat/statistics/general', $data);
    }
    /**
     * Obtiene estadísticas por agente.
     */
    public function statisticsAgents(array $data): array
    {
        return $this->call('/chat/statistics/agents', $data);
    }

    /**
     * Consulta si la conversación con un usuario está activa o cerrada.
     */
    public function getChatData(array $data): array
    {
        return $this->call('/chat/data', $data);
    }
}
