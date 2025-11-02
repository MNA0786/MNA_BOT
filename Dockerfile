FROM php:8.1-apache

# System dependencies install karo
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Composer install karo
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Working directory set karo
WORKDIR /var/www/html

# Apache configuration - headers module enable karo
RUN a2enmod rewrite headers

# Port expose karo
EXPOSE 80

# Application copy karo
COPY . .

# File permissions set karo
RUN mkdir -p backups \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod 666 movies.csv users.json bot_stats.json movie_requests.json bot_activity.log 2>/dev/null || true \
    && chmod 777 backups

CMD ["apache2-foreground"]
