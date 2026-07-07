# ============================================================
# Time Track API - CodeIgniter 4 (PHP 8.2)
# Forzar rebuild: fix cors preflight 204
# ============================================================

FROM php:8.2-apache AS base

# Instalar extensiones requeridas
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libicu-dev \
    libzip-dev \
    unzip \
    git \
    curl \
    && docker-php-ext-install \
    pgsql \
    pdo_pgsql \
    intl \
    zip \
    opcache \
    && a2enmod rewrite headers

# Configurar Apache para usar public/ como DocumentRoot
ENV APACHE_DOCUMENT_ROOT=/app/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Permitir .htaccess (AllowOverride All)
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# PHP config
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
RUN echo "memory_limit = 256M" >> "$PHP_INI_DIR/conf.d/overrides.ini"
RUN echo "upload_max_filesize = 20M" >> "$PHP_INI_DIR/conf.d/overrides.ini"
RUN echo "post_max_size = 20M" >> "$PHP_INI_DIR/conf.d/overrides.ini"

# ============================================================
# STAGE: Composer install
# ============================================================
FROM composer:latest AS composer-stage
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs

# ============================================================
# STAGE: Final
# ============================================================
FROM base AS runner
WORKDIR /app

# Copiar vendor de Composer
COPY --from=composer-stage /app/vendor ./vendor

# Copiar código fuente
COPY . .

# Enlazar spark CLI
RUN ln -s /app/vendor/codeigniter4/framework/spark /usr/local/bin/spark 2>/dev/null || true

# Crear writable y dar permisos (no existe en git por .gitignore)
RUN mkdir -p /app/writable/cache /app/writable/logs && chown -R www-data:www-data /app/writable

EXPOSE 80

CMD ["apache2-foreground"]
