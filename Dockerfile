# =========================
# Base Image
# =========================
FROM php:8.2-apache

# =========================
# Enable Apache Rewrite
# =========================
RUN a2enmod rewrite

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
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        mbstring \
        zip \
        exif \
        pcntl \
        bcmath \
        gd \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# =========================
# Set Working Directory
# =========================
WORKDIR /var/www/html

# =========================
# Copy Project Files
# =========================
COPY . .

# =========================
# Install Composer
# =========================
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# =========================
# Install PHP Dependencies
# =========================
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction

# =========================
# Laravel Permissions
# =========================
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

# =========================
# Apache Document Root → /public
# =========================
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri 's!/var/www/html!/var/www/html/public!g' \
    /etc/apache2/sites-available/*.conf \
 && sed -ri 's!/var/www/!/var/www/html/public!g' \
    /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/*.conf

# =========================
# Railway Port Support
# =========================
RUN sed -i 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf

# =========================
# Start Apache
# =========================
CMD apache2-foreground
