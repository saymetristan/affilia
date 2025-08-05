# Numok Affiliates - Docker Setup

## 🚀 Inicio Rápido

### Local Development

```bash
# 1. Clonar el proyecto
git clone <tu-repo>
cd afiliados

# 2. Ejecutar setup automático
chmod +x setup-local.sh
./setup-local.sh

# 3. Acceder a la aplicación
open http://localhost:8080
```

### Railway Deployment

```bash
# 1. Setup Railway CLI
npm install -g @railway/cli

# 2. Deploy automático
chmod +x docker/railway-deploy.sh
./docker/railway-deploy.sh
```

## 📋 Servicios

| Servicio | Puerto | Descripción |
|----------|---------|-------------|
| `web` | 8080 | Aplicación PHP principal |
| `db` | 3306 | MySQL 8.0 |
| `nginx` | 80, 443 | Proxy reverso (opcional) |

## 🔧 Configuración

### Variables de Entorno

Copia `.env.example` a `.env` y configura:

```bash
# Base de datos (ya configuradas para Docker)
DB_HOST=db
DB_NAME=numok
DB_USER=numok_user
DB_PASS=numok_password

# App
APP_URL=http://localhost:8080
APP_DEBUG=true

# Stripe (agregar tus keys reales)
STRIPE_SECRET_KEY=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

### Credenciales de Admin

- **Email:** admin@numok.local
- **Password:** admin123

⚠️ **Cambiar después del primer login**

## 🛠️ Comandos Docker

```bash
# Construir y ejecutar
docker-compose up -d --build

# Ver logs
docker-compose logs -f

# Acceder al container de la app
docker-compose exec web bash

# Acceder a MySQL
docker-compose exec db mysql -u root -p

# Reiniciar servicios
docker-compose restart

# Parar todo
docker-compose down

# Limpiar todo (¡cuidado!)
docker-compose down -v --rmi all
```

## 🗄️ Base de Datos

### Backup

```bash
docker-compose exec db mysqldump -u root -p numok > backup.sql
```

### Restore

```bash
docker-compose exec -T db mysql -u root -p numok < backup.sql
```

### Acceso directo

```bash
docker-compose exec db mysql -u numok_user -p numok
```

## 🌐 SSL/HTTPS (Producción)

Para HTTPS en Railway:
1. Railway proporciona SSL automáticamente
2. No necesitas configurar certificados

Para local con SSL:
1. Coloca certificados en `docker/ssl/`
2. Descomenta las líneas SSL en `docker/nginx.conf`

## 🐛 Troubleshooting

### Container no inicia

```bash
# Ver logs detallados
docker-compose logs web
docker-compose logs db

# Verificar estado
docker-compose ps
```

### Problemas de permisos

```bash
# Fijar permisos
docker-compose exec web chown -R www-data:www-data /var/www/html
docker-compose exec web chmod -R 777 /var/www/html/public/tracking
```

### MySQL no conecta

```bash
# Verificar que MySQL esté ready
docker-compose exec db mysqladmin ping -h localhost -u root -p

# Verificar variables de entorno
docker-compose exec web env | grep DB_
```

### Reset completo

```bash
# Eliminar todo y empezar de nuevo
docker-compose down -v
docker system prune -a
./setup-local.sh
```

## 📦 Railway Deployment

### Preparación

1. Instalar Railway CLI: `npm install -g @railway/cli`
2. Login: `railway login`
3. Ejecutar: `./docker/railway-deploy.sh`

### Variables en Railway

Agregar en el dashboard de Railway:

```
APP_DEBUG=false
STRIPE_SECRET_KEY=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

### Webhook de Stripe

Configurar en Stripe Dashboard:
- **URL:** `https://tu-app.railway.app/webhook/stripe`
- **Eventos:** `checkout.session.completed`, `payment_intent.succeeded`

## 🔍 Monitoreo

### Logs en tiempo real

```bash
# Todos los servicios
docker-compose logs -f

# Solo la aplicación
docker-compose logs -f web

# Solo base de datos
docker-compose logs -f db
```

### Métricas

```bash
# Uso de recursos
docker stats

# Estado de containers
docker-compose ps
```