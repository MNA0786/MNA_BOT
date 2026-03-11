FROM php:8.1-apache

# System Dependencies
RUN apt-get update && apt-get install -y \
    git curl wget nano \
    libpng-dev libonig-dev libxml2-dev \
    zip unzip libzip-dev libsqlite3-dev sqlite3 \
    && docker-php-ext-install pdo_mysql mbstring exif bcmath gd zip

# Enable Apache Modules
RUN a2enmod rewrite headers

# Fix Apache ServerName
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy Application
COPY . .

# Create Directories & Set Permissions
RUN mkdir -p backups logs cache temp database \
    && chmod 777 backups logs cache temp database \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod 666 *.json *.csv *.log 2>/dev/null || true \
    && chmod 755 index.php

# Health Check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

EXPOSE 80

CMD ["apache2-foreground"]
