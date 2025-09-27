# 🧠 Documentación Interna – Ánima ↔ Bitrix24

Este documento está destinado a desarrolladores y operadores que mantendrán el sistema en producción, ayudando a entender cómo funciona la integración, cómo verificar su estado y cómo responder ante errores comunes.

---

## 🧩 1. Resumen de funcionamiento

El sistema permite integrar un árbol conversacional alojado en Ánima con canales conectados a Bitrix24 (ej. Telegram, WhatsApp), procesando mensajes automáticamente vía Laravel.

### Flujo general

Usuario → Bitrix24 → Webhook Laravel → Ánima → Respuesta → Bitrix24

El backend determina el tipo de nodo, registra sesiones, guarda entradas del usuario, y decide si debe transferir la conversación a un humano.

---

## 🧪 2. Cómo verificar que el sistema funciona

| Acción                               | Resultado esperado                                      |
| ------------------------------------ | ------------------------------------------------------- |
| Usuario escribe "Hola" en Telegram   | Laravel recibe el webhook y responde con el primer nodo |
| Usuario elige opción del menú        | Se guarda en `bitrix_sessions` el `next_node_id`        |
| Nodo requiere transferencia a humano | Campo `transferred_to_human = 1` en `bitrix_sessions`   |
| Error en flujo                       | Se registra en `bitrix_webhook_logs` con `success = 0`  |

---

## 🔧 3. Errores comunes y solución

| Error                     | Causa probable                                  | Solución                                             |
| ------------------------- | ----------------------------------------------- | ---------------------------------------------------- |
| No se responde al mensaje | `enabled = 0` o error en payload                | Verificar tabla `bitrix_instances`, activar canal    |
| Token expirado            | `access_token` vencido                          | Usar `refresh_token` para renovar y actualizar en BD |
| No se guarda sesión       | Falla en generación de `uid` o error de BD      | Validar payload, revisar `BitrixSessionHelper`       |
| Webhook no llega          | Error de red, firewall o Bitrix mal configurado | Revisar logs, headers y conexión del canal con HTTPS |

---

## 📄 4. Endpoints clave

- **Webhook de mensajes:**  
  `POST /api/v1.0.0/webhook/bitrix/message`

- **Activar/desactivar bot:**  
  `POST /api/v1.0.0/bitrix/bot-toggle`

- **Transferencia manual:**  
  `POST /api/v1.0.0/bitrix/manual-transfer`

- **Actualizar hash del árbol:**  
  `POST /api/v1.0.0/bitrix/update-hash`

- **Comando Artisan para cerrar sesiones inactivas:**

  ```bash
  php artisan bitrix:close-inactive-sessions
  ```

---

## 🗃️ 5. Tablas importantes

| Tabla                 | Descripción                                                           |
| --------------------- | --------------------------------------------------------------------- |
| `bitrix_instances`    | Configuración por portal (tokens, canal, hash, estado)                |
| `bitrix_sessions`     | Estado actual de cada conversación                                    |
| `bitrix_user_inputs`  | Respuestas del usuario a nodos tipo input (`type_id = 14`)            |
| `bitrix_webhook_logs` | Registro de cada webhook recibido con su respuesta asociada           |
| `bitrix_menu_options` | Opciones válidas de menú mostradas al usuario para validar respuestas |

---

## ✅ 6. Checklist operativo

| Verificación                        | Frecuencia | Acción sugerida                                              |
| ----------------------------------- | ---------- | ------------------------------------------------------------ |
| Webhook responde correctamente      | Diario     | Revisar `bitrix_webhook_logs` con `success = 1`              |
| Tokens no están por expirar         | Semanal    | Verificar campo `expires` en `bitrix_instances`              |
| Sesiones inactivas se cierran solas | Diario     | Confirmar que comando `bitrix:close-inactive-sessions` corre |
| No hay errores de conexión a Ánima  | Diario     | Revisar logs o respuesta HTTP en `BitrixLogService`          |

---

## 📂 Archivos clave involucrados

- `BitrixWebhookController.php` → Entrada principal
- `BitrixService.php` → Comunicación con Bitrix
- `AnimaTreeService.php` → Comunicación con Ánima
- `NodeProcessor.php` → Lógica según `type_id`
- `BitrixSessionHelper.php` → Manejo de sesiones
- `BitrixWebhookNormalizer.php` → Limpieza de datos entrantes

---
