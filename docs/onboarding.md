# 👋 Onboarding para Desarrolladores – Ánima ↔ Bitrix24

Este documento te guiará en los primeros pasos para trabajar localmente con la integración del motor Ánima como chatbot en Bitrix24.

---

## 📘 ¿Qué es este proyecto?

Este proyecto integra Laravel como backend puro para recibir mensajes desde Bitrix24 (Telegram, WhatsApp, etc.) y procesarlos con lógica conversacional basada en árboles alojados en Ánima.

Permite:

- Automatizar respuestas desde un flujo de nodos
- Transferir al humano cuando sea necesario
- Manejar múltiples portales y canales

---

## 💻 Requisitos mínimos

- PHP 8.2+
- Composer
- MySQL o MariaDB
- Redis (para colas)
- Node.js (solo si compilas assets, opcional)
- Laravel CLI (`artisan`)
- Postman (opcional para pruebas)

---

## 🚀 Instalación local

```bash
git clone https://github.com/previsrl/apibot-bitrix-integration.git
cd apibot-bitrix-integration
cp .env.example .env

# Instalar dependencias
composer install

# Generar clave de aplicación
php artisan key:generate

# Migrar base de datos
php artisan migrate

# Correr servidor local
php artisan serve
```

Para exponer públicamente el webhook (requerido por Bitrix):

```bash
ngrok http 8000
```

---

## 📡 ¿Cómo probar que funciona?

1. Apunta el webhook de Bitrix a tu URL ngrok:

   ```
   https://xxxx.ngrok.io/api/v1.0.0/webhook/bitrix/message
   ```

2. Usa Postman para enviar un mensaje simulado:

```json
POST /api/v1.0.0/webhook/bitrix/message
Headers:
  Authorization: Bearer {BITRIX_AUTH_TOKEN}
Body:
{
  "user_id": "user-b11",
  "channel_id": "telegrambot|1|...",
  "message": "Hola"
}
```

3. Verifica logs en `storage/logs/laravel.log` y en la base de datos (`bitrix_webhook_logs`)

---

## 🧠 Entendiendo el flujo general

1. Bitrix envía mensaje → llega al `BitrixWebhookController`
2. Se normaliza y se busca la sesión (`BitrixSession`)
3. Se detecta el tipo de nodo (`NodeProcessor`)
4. Se consulta el árbol de Ánima
5. Se envía una respuesta al usuario vía `BitrixService`

---

## 📂 Archivos clave

- `app/Services/Anima/AnimaTreeService.php` → Comunicación con API Ánima
- `app/Services/Bitrix/BitrixService.php` → Comunicación con API Bitrix
- `app/Services/Anima/NodeProcessor.php` → Lógica de cada tipo de nodo
- `app/Http/Controllers/BitrixWebhookController.php` → Webhook de entrada
- `database/migrations/` → Estructura de tablas Bitrix

---

## 🐞 Logs y debugging

- Logs de Laravel: `storage/logs/laravel.log`
- Logs de webhooks recibidos y respuestas: tabla `bitrix_webhook_logs`
- Respuestas del árbol de nodos: revisa `NodeProcessor::handle*Node()`
- Si no hay respuesta: revisa el `enabled` en `bitrix_instances`

---

## 🆘 ¿Dudas o soporte?

- Documentación completa:

  - `README.md`
  - `docs/deploy.md`
  - `docs/internal.md`

- Contacta al responsable del proyecto o revisa GitHub para detalles adicionales.

---
