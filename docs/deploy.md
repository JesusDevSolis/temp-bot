# 📦 Despliegue en Producción – Ánima ↔ Bitrix24

Guía paso a paso para implementar el backend Laravel en un servidor real, con HTTPS, Redis, colas y configuración segura para recibir mensajes desde Bitrix24.

---

## ✅ 1. Requisitos del sistema

- Ubuntu 20.04+ (o equivalente)
- PHP 8.2+
- Composer
- MySQL o MariaDB
- Redis
- Supervisor
- Nginx o Apache
- Certbot (HTTPS) o ngrok (pruebas)

---

## ⚙️ 2. Preparación del entorno

```bash
sudo apt update && sudo apt upgrade
sudo apt install php php-cli php-mbstring php-xml php-curl php-mysql php-bcmath unzip git curl redis nginx supervisor
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

---

## 🧪 3. Base de datos

```sql
CREATE DATABASE apibot_bitrix CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'anima_user'@'localhost' IDENTIFIED BY 'tu_password_segura';
GRANT ALL PRIVILEGES ON apibot_bitrix.* TO 'anima_user'@'localhost';
FLUSH PRIVILEGES;
```

---

## 🔑 4. Configuración `.env`

```env
APP_ENV=production
APP_KEY= (usa `php artisan key:generate`)
APP_DEBUG=false
APP_URL=https://tudominio.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=apibot_bitrix
DB_USERNAME=anima_user
DB_PASSWORD=tu_password_segura

QUEUE_CONNECTION=database
ANIMA_API_URL=https://animalogic.anima.bot/api
ANIMA_API_KEY=token_proporcionado

BITRIX_AUTH_TOKEN=token_para_validar_webhooks
```

---

## 🧱 5. Migraciones y colas

```bash
php artisan migrate --force
php artisan queue:table
php artisan migrate
php artisan queue:restart
```

---

## 🔁 6. Supervisor (para mantener `queue:work` activo)

```bash
sudo nano /etc/supervisor/conf.d/anima-bot-worker.conf
```

Contenido:

```ini
[program:anima-bot-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/anima-bot/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/anima-bot/storage/logs/worker.log
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start anima-bot-worker:*
```

---

## 🌐 7. Nginx + HTTPS

```bash
sudo nano /etc/nginx/sites-available/anima-bot
```

Ejemplo:

```nginx
server {
    listen 80;
    server_name tudominio.com;
    root /var/www/anima-bot/public;

    index index.php index.html;
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/anima-bot /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

Instalar SSL:

```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d tudominio.com
```

---

## 🌐 8. Alternativa con ngrok

```bash
ngrok http 9010
```

Apunta el webhook de Bitrix a la URL HTTPS generada:

```
https://xxxx.ngrok-free.app/api/v1.0.0/webhook/bitrix/message
```

---

## 🔐 9. Webhook Bitrix: formato esperado

**POST /api/v1.0.0/webhook/bitrix/message**

Headers:

```http
Authorization: Bearer {BITRIX_AUTH_TOKEN}
Content-Type: application/json
```

Body:

```json
{
  "user_id": "user-b11",
  "channel_id": "telegrambot|1|...",
  "message": "Hola"
}
```

---

## ✅ 10. Checklist final

| Verificación                               | Estado |
| ------------------------------------------ | ------ |
| `.env` completo y seguro                   | ✅     |
| Webhook HTTPS funcionando                  | ✅     |
| Base de datos migrada                      | ✅     |
| Workers corriendo con supervisor           | ✅     |
| Bitrix configurado con webhook             | ✅     |
| Certificado SSL activo o ngrok funcionando | ✅     |
| Tokens Bitrix/API presentes                | ✅     |

---
