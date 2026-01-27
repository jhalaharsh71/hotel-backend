# =========================
# Base Image (STABLE)
# =========================
FROM php:8.2-cli

# =========================
# System Dependencies
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
    nginx \
    supervisor \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        mbstring \
        zip \
        bcmath \
        gd \
    && rm -rf /var/lib/apt/lists/*

# =========================
# Install Composer
# =========================
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# =========================
# App Directory
# =========================
WORKDIR /var/www/html
COPY . .

# =========================
# Install PHP Dependencies
# =========================
RUN composer install --no-dev --optimize-autoloader --no-interaction

# =========================
# Permissions
# =========================
RUN chmod -R 775 storage bootstrap/cache

# =========================
# Expose Railway Port
# =========================
EXPOSE 8080

# =========================
# Start Laravel (Built-in server)
# =========================
CMD php artisan serve --host=0.0.0.0 --port=$PORT
