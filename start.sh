#!/bin/bash
# ================================================================
# start.sh — WedPlan Backend pour Render + Supabase
# ================================================================
set -e

echo "🚀 WedPlan Backend — Démarrage sur Render"
cd /var/www/html 2>/dev/null || cd /opt/render/project/src/backend 2>/dev/null || true

# ================================================================
# 1️⃣ Génération .env pour Supabase
# ================================================================
echo "📝 Génération .env..."

# Générer APP_KEY si vide
if [ -z "$APP_KEY" ]; then
    echo "🔑 APP_KEY vide, génération..."
    export APP_KEY=$(php artisan key:generate --show)
fi

cat > .env << ENVEOF
APP_NAME=WedPlan
APP_ENV=production
APP_KEY=${APP_KEY}
APP_DEBUG=false
APP_URL=${APP_URL:-http://localhost}
LOG_CHANNEL=stderr
LOG_LEVEL=error

# Database Supabase
DB_CONNECTION=${DB_CONNECTION:-pgsql}
DB_HOST=${DB_HOST:-db.xxxxx.supabase.co}
DB_PORT=${DB_PORT:-6543}
DB_DATABASE=${DB_DATABASE:-postgres}
DB_USERNAME=${DB_USERNAME:-postgres.xxxxx}
DB_PASSWORD=${DB_PASSWORD:-xxxxxx}

# Cache / Session / Queue
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync

FRONTEND_URL=${FRONTEND_URL:-*}
ENVEOF

echo "✅ .env généré — DB_HOST=${DB_HOST}, DB_DATABASE=${DB_DATABASE}"

# ================================================================
# 2️⃣ Laravel setup
# ================================================================
php artisan config:clear 2>&1 || true
php artisan config:cache 2>&1

echo "🔄 Migration de la base..."
php artisan migrate --force 2>&1

# ================================================================
# 3️⃣ Seeder si base vide
# ================================================================
COUNT=$(php artisan tinker --execute="echo \App\Models\User::count();" 2>/dev/null | grep -E '^[0-9]+$' | tail -1)
if [ -z "$COUNT" ] || [ "$COUNT" = "0" ]; then
    echo "🌱 Seeder..."
    php artisan db:seed --force 2>&1
fi

# ================================================================
# 4️⃣ Caches Laravel
# ================================================================
php artisan route:cache 2>&1
php artisan view:cache 2>&1 || true
chmod -R 775 storage bootstrap/cache

# ================================================================
# 5️⃣ Démarrage PHP built-in Render
# ================================================================
PORT=${PORT:-8000}
echo "✅ Démarrage serveur PHP sur le port $PORT..."
exec php -S "0.0.0.0:$PORT" -t public