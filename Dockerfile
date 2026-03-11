# ==============================
# DOCKER CONFIGURATION
# ==============================
FROM php:8.1-apache

# System Labels
LABEL maintainer="Entertainment Tadka <admin@entertainmenttadka.com>"
LABEL version="3.0.0"
LABEL description="Entertainment Tadka Telegram Movie Bot"

# System Dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    wget \
    nano \
    htop \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    gzip \
    libzip-dev \
    libsqlite3-dev \
    sqlite3 \
    redis-server \
    supervisor \
    cron \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        sockets

# Install Redis Extension
RUN pecl install redis && docker-php-ext-enable redis

# Enable Apache Modules
RUN a2enmod rewrite headers expires deflate

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Working Directory
WORKDIR /var/www/html

# Copy Application
COPY . .

# Create Required Directories
RUN mkdir -p \
    backups \
    logs \
    cache \
    temp \
    database \
    && chmod 777 backups logs cache temp database

# Set Permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod 666 movies.csv users.json bot_stats.json movie_requests.json bot_activity.log 2>/dev/null || true

# Configure PHP
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Configure Apache
COPY docker/apache-config.conf /etc/apache2/sites-available/000-default.conf

# Configure Supervisor
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Configure Cron
COPY docker/crontab /etc/cron.d/bot-cron
RUN chmod 0644 /etc/cron.d/bot-cron && crontab /etc/cron.d/bot-cron

# Health Check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# Expose Port
EXPOSE 80

# Start Supervisor
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]