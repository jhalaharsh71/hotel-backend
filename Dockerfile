FROM php:8.2-cli

# =========================
# System packages
# =========================
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    curl \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libxml2-dev \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        zip \
        bcmath \
        gd \
    && rm -rf /var/lib/apt/lists/*

# =========================
# Install Composer
# =========================
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# =========================
# App directory
# =========================
WORKDIR /var/www/html
COPY . .

# =========================
# Install Laravel dependencies
# =========================
RUN composer install --no-dev --optimize-autoloader --no-interaction

# =========================
# Permissions
# =========================
RUN chmod -R 775 storage bootstrap/cache

# =========================
# START APPLICATION (IMPORTANT FIXES)
# =========================
CMD php artisan config:clear \
 && php artisan cache:clear \
 && php artisan migrate --force \
 && php artisan serve --host=0.0.0.0 --port=$PORT
