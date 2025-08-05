#!/bin/bash

# Script de inicio para la aplicación PHP

# Esperar a que MySQL esté disponible
echo "Esperando conexión a MySQL..."
until nc -z db 3306; do
  echo "MySQL no está listo - esperando..."
  sleep 2
done

echo "MySQL está listo!"

# Crear archivo de configuración si no existe
if [ ! -f /var/www/html/config/config.php ]; then
    echo "Creando archivo de configuración..."
    cp /var/www/html/config/config.example.php /var/www/html/config/config.php
fi

# Asegurar permisos correctos
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html
chmod -R 777 /var/www/html/public/tracking
chmod -R 777 /var/www/html/public/assets/uploads

echo "Iniciando Apache..."

# Iniciar Apache en foreground
apache2-foreground