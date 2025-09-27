#!/bin/bash

echo "🔧 Cambiando permisos para desarrollo..."

# Permisos para archivos y carpetas del proyecto
sudo chown -R ubuntu:ubuntu /home/ubuntu/apibot-bitrix
sudo find /home/ubuntu/apibot-bitrix -type d -exec chmod 755 {} \;
sudo find /home/ubuntu/apibot-bitrix -type f -exec chmod 644 {} \;

# Permisos especiales para Laravel
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
sudo mkdir -p storage/logs
sudo touch storage/logs/laravel.log
sudo chmod 664 storage/logs/laravel.log
sudo chown www-data:www-data storage/logs/laravel.log

# Permiso de ejecución a scripts
chmod +x clean-and-build.sh
chmod +x set-permissions-prod.sh
chmod +x set-permissions-dev.sh

# 🔑 Permiso de ejecución para Vite
chmod +x node_modules/.bin/vite

sudo chown -R ubuntu:ubuntu .
chmod +x node_modules/@esbuild/linux-x64/bin/esbuild

echo "✅ Permisos listos para desarrollo (usuario ubuntu)"
