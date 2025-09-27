# 📓 CHANGELOG – Ánima ↔ Bitrix24

Todas las modificaciones relevantes del proyecto se documentan aquí siguiendo el formato de versionado semántico.

---

## [1.0.0] - 2025-06-14

### 🆕 Agregado

- `README.md` con documentación técnica, endpoints, seguridad y ejemplos
- `docs/deploy.md` con guía detallada para producción (nginx, Redis, Supervisor, SSL)
- `docs/internal.md` con manual operativo para detectar errores y mantener el sistema
- `docs/onboarding.md` para nuevos desarrolladores

### 🛠️ Implementado

- Webhook funcional para recibir mensajes desde Bitrix24
- Integración con árbol conversacional Ánima (`type_id` 1 al 15)
- Transferencia automática a humano desde nodos `transfer_to_human`
- Registro y control de sesiones por chat y usuario (`bitrix_sessions`)
- Captura de inputs tipo texto (`bitrix_user_inputs`)
- Logs de entrada y respuesta por webhook (`bitrix_webhook_logs`)
- Activación/desactivación de bots por canal (`enabled` en `bitrix_instances`)
- Middleware de autenticación para Bitrix (`verify.bitrix`)

### ✅ Validado

- Flujo conversacional real probado desde Telegram conectado a Bitrix24
- Ejecución completa de pruebas en entorno local con ngrok

---

## [0.9.0] - 2025-06-10

### 🏗️ Estructura inicial

- Instalación de Laravel y configuración base
- Modelos creados: `BitrixInstance`, `BitrixSession`, `BitrixUserInput`, `BitrixWebhookLog`
- Migraciones completas para todas las tablas
- Primeros controladores (`Webhook`, `OAuth`, `Toggle`, `Transfer`)
- Configuración de `.env` para conexión a base de datos y tokens externos

---
