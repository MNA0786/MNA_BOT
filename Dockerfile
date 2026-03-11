# Dockerfile
FROM php:8.2-apache

# Set working directory
WORKDIR /var/www/html

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
    cron \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd \
    && docker-php-ext-install pdo_sqlite sqlite3

# Enable Apache modules
RUN a2enmod rewrite headers deflate expires

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application files
COPY . /var/www/html

# Create necessary directories
RUN mkdir -p /var/www/html/database \
    && mkdir -p /var/www/html/backups \
    && mkdir -p /var/www/html/cache \
    && mkdir -p /var/www/html/logs \
    && mkdir -p /var/www/html/storage

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/database \
    && chmod -R 777 /var/www/html/backups \
    && chmod -R 777 /var/www/html/cache \
    && chmod -R 777 /var/www/html/logs \
    && chmod -R 777 /var/www/html/storage \
    && touch /var/www/html/movies.csv \
    && touch /var/www/html/users.json \
    && touch /var/www/html/bot_stats.json \
    && touch /var/www/html/movie_requests.json \
    && touch /var/www/html/learning.json \
    && chmod 666 /var/www/html/*.csv \
    && chmod 666 /var/www/html/*.json

# Configure Apache
COPY docker/apache-config.conf /etc/apache2/sites-available/000-default.conf

# Set environment variables
ENV ENVIRONMENT=production
ENV PHP_MEMORY_LIMIT=256M
ENV PHP_MAX_EXECUTION_TIME=300

# Setup cron jobs
COPY docker/cronjob /etc/cron.d/bot-cron
RUN chmod 0644 /etc/cron.d/bot-cron \
    && crontab /etc/cron.d/bot-cron \
    && touch /var/log/cron.log

# Expose port
EXPOSE 80

# Start services
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]