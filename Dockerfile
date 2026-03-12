# Dockerfile
FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    sqlite3 \
    libsqlite3-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd pdo_sqlite

# Enable Apache modules
RUN a2enmod rewrite headers

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Create necessary directories and set permissions
RUN mkdir -p /var/www/html/session /var/www/html/logs \
    && touch /var/www/html/error.log /var/www/html/bot.sqlite \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod 664 /var/www/html/bot.sqlite /var/www/html/error.log

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Set environment variables (will be overridden by docker-compose or Render)
ENV BOT_TOKEN=""
ENV ADMIN_ID=""
ENV REQUEST_GROUP_ID=""
ENV WEBHOOK_PASS=""
ENV CHANNEL_IDS=""
ENV CHANNEL_USERNAMES=""
ENV API_ID=""
ENV API_HASH=""

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
