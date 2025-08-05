#!/bin/bash

# Script de inicio simple para Railway

echo "🚀 Iniciando aplicación en Railway..."

# Asegurar permisos correctos
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html
chmod -R 777 /var/www/html/public/tracking
chmod -R 777 /var/www/html/public/assets/uploads

# Verificar y crear directorios necesarios
mkdir -p /var/www/html/public/tracking
mkdir -p /var/www/html/public/assets/uploads

echo "🌐 Iniciando Apache en puerto $PORT..."

# Configurar Apache para usar el puerto de Railway
sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf
sed -i "s/:80/:$PORT/g" /etc/apache2/sites-available/*.conf

# Iniciar Apache en foreground
apache2-foreground