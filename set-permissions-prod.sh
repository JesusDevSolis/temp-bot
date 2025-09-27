#!/bin/bash

echo "🔐 Restaurando permisos para producción (www-data)..."

# Asignar al usuario de servidor web
sudo chown -R www-data:www-data /home/ubuntu/apibot-bitrix

# Asignar permisos seguros de carpetas y archivos
sudo find /home/ubuntu/apibot-bitrix -type d -exec chmod 755 {} \;
sudo find /home/ubuntu/apibot-bitrix -type f -exec chmod 644 {} \;

# Asegurar permisos especiales para rutas necesarias de Laravel
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# Verificar existencia de archivo de log
sudo touch storage/logs/laravel.log
sudo chown www-data:www-data storage/logs/laravel.log
sudo chmod 664 storage/logs/laravel.log

echo "✅ Permisos restaurados para producción (www-data)"
