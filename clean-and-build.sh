#!/bin/bash

echo "🚧 Ejecutando limpieza de cachés Laravel..."

php artisan config:clear
php artisan config:cache
php artisan route:clear
php artisan view:clear
php artisan view:cache

echo "🔄 Ejecutando dump-autoload con Composer..."
composer dump-autoload

echo "🚀 Compilando assets con Vite..."
npm run build

echo "✅ Todo limpio y compilado."
