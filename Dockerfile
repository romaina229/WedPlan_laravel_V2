# ================================================================
# Dockerfile — WedPlan Backend Laravel 11 pour Render + Supabase
# ================================================================
FROM php:8.3-cli

# ---------------------------------------------------------------
# 1️⃣ Installer dépendances système
# ---------------------------------------------------------------
RUN apt-get update && apt-get install -y --no-install-recommends \
    git curl unzip zip netcat-openbsd \
    libpng-dev libonig-dev libxml2-dev libzip-dev \
    libsqlite3-dev default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

# ---------------------------------------------------------------
# 2️⃣ Extensions PHP nécessaires
# ---------------------------------------------------------------
RUN docker-php-ext-install pdo pdo_pgsql mbstring exif pcntl bcmath gd zip opcache

# ---------------------------------------------------------------
# 3️⃣ Composer
# ---------------------------------------------------------------
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# ---------------------------------------------------------------
# 4️⃣ Opcache pour production
# ---------------------------------------------------------------
RUN printf "opcache.enable=1\nopcache.memory_consumption=128\nopcache.max_accelerated_files=10000\n" \
    > /usr/local/etc/php/conf.d/opcache.ini

# ---------------------------------------------------------------
# 5️⃣ Préparer le workspace
# ---------------------------------------------------------------
WORKDIR /var/www/html
COPY . .

# Installer les dépendances Laravel sans dev
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# Permissions correctes
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

# ---------------------------------------------------------------
# 6️⃣ Exposer le port attendu par Render
# ---------------------------------------------------------------
EXPOSE 8000

# ---------------------------------------------------------------
# 7️⃣ Entrypoint
# ---------------------------------------------------------------
COPY start.sh /start.sh
RUN chmod +x /start.sh
ENTRYPOINT ["/start.sh"]