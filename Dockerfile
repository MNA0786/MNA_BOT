# ==============================
# SIMPLE DOCKERFILE - NO ERRORS
# ==============================
FROM php:8.1-apache

# System Dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    wget \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    libsqlite3-dev \
    sqlite3 \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        exif \
        bcmath \
        gd \
        zip

# Enable Apache Modules
RUN a2enmod rewrite headers

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Working Directory
WORKDIR /var/www/html

# Copy Application
COPY . .

# Create Required Directories
RUN mkdir -p backups logs cache temp database \
    && chmod 777 backups logs cache temp database

# Set Permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod 666 movies.csv users.json bot_stats.json movie_requests.json bot_activity.log 2>/dev/null || true

# Health Check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# Expose Port
EXPOSE 80

CMD ["apache2-foreground"]
