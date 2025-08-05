#!/bin/bash

# Script para deployment completo en Railway

echo "🚀 Preparando deployment en Railway..."

# Verificar que railway CLI esté instalado
if ! command -v railway &> /dev/null; then
    echo "❌ Railway CLI no está instalado. Instala con:"
    echo "npm install -g @railway/cli"
    exit 1
fi

# Login a Railway (si no está autenticado)
echo "🔐 Verificando autenticación..."
if ! railway whoami > /dev/null 2>&1; then
    echo "Necesitas hacer login a Railway:"
    railway login
fi

# Crear nuevo proyecto
echo "📦 Creando nuevo proyecto en Railway..."
railway project --new --name "numok-affiliates"

# Cambiar al directorio del proyecto (railway crea contexto automáticamente)
echo "🗄️ Agregando servicio de MySQL..."
railway add --service mysql

echo "⏳ Esperando que MySQL esté listo..."
sleep 10

# Verificar variables de entorno de MySQL
echo "🔍 Verificando variables de MySQL..."
railway variables

echo "📊 Importando schema de base de datos..."
# Usar railway para ejecutar el SQL
cat database/deploy.sql | railway run mysql --database=\$MYSQL_DATABASE

echo "🚢 Desplegando aplicación principal..."
railway up --detach

echo "⏳ Esperando deployment..."
sleep 30

# Obtener URL del deployment
APP_URL=$(railway domain)
echo "🌐 Aplicación desplegada en: $APP_URL"

echo ""
echo "✅ Deployment completado!"
echo ""
echo "📱 URLs importantes:"
echo "   - App: $APP_URL"
echo "   - Admin: $APP_URL/admin/login"
echo "   - Health: $APP_URL/api/health"
echo ""
echo "🔑 Credenciales de admin:"
echo "   Email: admin@numok.local"
echo "   Password: admin123"
echo ""
echo "🔧 Configuración post-deployment:"
echo "1. Ve al dashboard de Railway"
echo "2. Configura las variables de entorno de Stripe:"
echo "   - STRIPE_SECRET_KEY=sk_live_..."
echo "   - STRIPE_WEBHOOK_SECRET=whsec_..."
echo "3. Configura el webhook de Stripe con: $APP_URL/webhook/stripe"
echo "4. ¡Cambia la contraseña del admin!"