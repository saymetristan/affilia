# 🚂 Deploy en Railway - Numok Affiliates

## 🚀 Deployment Rápido

```bash
# Ejecutar script automático
./docker/railway-deploy.sh
```

## 📋 Deployment Manual Paso a Paso

### 1. Preparación

```bash
# Instalar Railway CLI
npm install -g @railway/cli

# Login
railway login
```

### 2. Crear Proyecto

```bash
# Crear nuevo proyecto
railway project --new --name "numok-affiliates"

# Agregar MySQL
railway add --service mysql
```

### 3. Configurar Variables de Entorno

```bash
# Railway auto-configura estas variables:
# MYSQL_HOST, MYSQL_PORT, MYSQL_DATABASE, MYSQL_USER, MYSQL_PASSWORD

# Agregar variables adicionales
railway variables set APP_DEBUG=false
railway variables set STRIPE_SECRET_KEY=sk_live_your_key
railway variables set STRIPE_WEBHOOK_SECRET=whsec_your_secret
```

### 4. Deploy

```bash
# Importar schema de BD
cat database/deploy.sql | railway run mysql --database=$MYSQL_DATABASE

# Deploy aplicación
railway up
```

## 🎯 Arquitectura en Railway

### **Servicios:**
- **App Service:** PHP 8.1 + Apache (nuestro Dockerfile.railway)
- **MySQL Service:** Base de datos gestionada por Railway

### **Variables de Entorno Auto-configuradas:**
```
MYSQL_HOST=${{MYSQL_HOST}}
MYSQL_PORT=${{MYSQL_PORT}}  
MYSQL_DATABASE=${{MYSQL_DATABASE}}
MYSQL_USER=${{MYSQL_USER}}
MYSQL_PASSWORD=${{MYSQL_PASSWORD}}
APP_URL=${{RAILWAY_STATIC_URL}}
```

### **Health Checks:**
- **Endpoint:** `/api/health`
- **Timeout:** 300s
- **Restart Policy:** on_failure

## 🔧 Post-Deployment

### 1. Verificar Servicios

```bash
# Verificar status
railway status

# Ver logs
railway logs

# Verificar health check
curl https://your-app.railway.app/api/health
```

### 2. Configurar Stripe

1. **Dashboard Railway:** Agregar variables
   ```
   STRIPE_SECRET_KEY=sk_live_...
   STRIPE_WEBHOOK_SECRET=whsec_...
   ```

2. **Dashboard Stripe:** Configurar webhook
   - **URL:** `https://your-app.railway.app/webhook/stripe`
   - **Eventos:** `checkout.session.completed`, `payment_intent.succeeded`, `invoice.paid`

### 3. Admin Setup

1. **Login:** `https://your-app.railway.app/admin/login`
2. **Credenciales:** admin@numok.local / admin123
3. **¡Cambiar contraseña inmediatamente!**

## 🛠️ Comandos Útiles

```bash
# Ver logs en tiempo real
railway logs --follow

# Conectar a MySQL
railway connect mysql

# Ejecutar comandos en el container
railway shell

# Ver variables de entorno
railway variables

# Redeploy
railway up --detach

# Backup de BD
railway run mysqldump --databases $MYSQL_DATABASE > backup.sql
```

## 🔍 Monitoring

### **Health Checks Disponibles:**
- `/api/health` - Estado completo de servicios
- `/api/ready` - Simple ready check

### **Logs Importantes:**
- Apache access/error logs
- PHP application logs  
- MySQL query logs
- Stripe webhook logs

## 🚨 Troubleshooting

### **App no responde:**
```bash
# Ver logs
railway logs --service app

# Verificar variables
railway variables

# Redeploy
railway up
```

### **MySQL no conecta:**
```bash
# Verificar status de MySQL
railway status mysql

# Test conexión
railway connect mysql
```

### **Webhooks fallan:**
```bash
# Ver logs de webhooks
railway logs --filter "webhook"

# Verificar SSL
curl -I https://your-app.railway.app

# Test endpoint
curl https://your-app.railway.app/webhook/stripe
```

## 💰 Costos Estimados

- **Starter:** ~$5/mes (desarrollo)
- **Pro:** ~$20/mes (producción)
- **MySQL:** Incluido en plan
- **Bandwidth:** 100GB incluido

## 🔄 CI/CD Setup

```yaml
# .github/workflows/railway.yml
name: Deploy to Railway
on:
  push:
    branches: [main]
jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - uses: railway/up@v1
        with:
          railway_token: ${{ secrets.RAILWAY_TOKEN }}
```