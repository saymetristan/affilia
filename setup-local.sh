#!/bin/bash

# Script de setup local para Numok Affiliates

echo "🐳 Configurando entorno local..."

# Verificar que Docker esté instalado
if ! command -v docker &> /dev/null; then
    echo "❌ Docker no está instalado. Instálalo desde https://docker.com"
    exit 1
fi

if ! docker compose version &> /dev/null; then
    echo "❌ Docker Compose no está instalado"
    exit 1
fi

# Crear archivo .env si no existe
if [ ! -f .env ]; then
    echo "📄 Creando archivo .env..."
    cp .env.example .env
    echo "✏️  Edita el archivo .env con tus configuraciones específicas"
fi

# Crear directorios necesarios
echo "📁 Creando directorios..."
mkdir -p public/tracking
mkdir -p public/assets/uploads
mkdir -p docker/ssl

# Crear archivo .htaccess para uploads si no existe
if [ ! -f public/assets/uploads/.htaccess ]; then
    echo "Deny from all" > public/assets/uploads/.htaccess
fi

# Construir y ejecutar containers
echo "🏗️  Construyendo containers..."
docker compose build

echo "🚀 Iniciando servicios..."
docker compose up -d

# Esperar a que los servicios estén listos
echo "⏳ Esperando servicios..."
sleep 10

# Verificar estado de los containers
echo "📊 Estado de los containers:"
docker compose ps

echo ""
echo "✅ Setup completado!"
echo ""
echo "🌐 Acceso a la aplicación:"
echo "   - Aplicación: http://localhost:8080"
echo "   - Admin: http://localhost:8080/admin/login"
echo "   - Nginx (producción): http://localhost"
echo ""
echo "🔑 Credenciales de admin por defecto:"
echo "   Email: admin@numok.local"
echo "   Password: admin123"
echo ""
echo "📝 Próximos pasos:"
echo "1. Accede al panel de admin y cambia la contraseña"
echo "2. Configura tus credenciales de Stripe en Settings"
echo "3. Crea tus primeros programas de afiliados"
echo ""
echo "🛠️  Comandos útiles:"
echo "   - Ver logs: docker compose logs -f"
echo "   - Parar: docker compose down"
echo "   - Reiniciar: docker compose restart"