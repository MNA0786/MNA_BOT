# Dockerfile
# Stage: Build and Run
FROM php:8.2-apache

# ============================================
# 1. INSTALL SYSTEM DEPENDENCIES
# ============================================
RUN apt-get update && apt-get install -y \
    git \
    curl \
    wget \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    sqlite3 \
    libsqlite3-dev \
    libssl-dev \
    libzip-dev \
    && rm -rf /var/lib/apt/lists/*

# ============================================
# 2. INSTALL PHP EXTENSIONS
# ============================================
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    pdo_sqlite \
    zip \
    && docker-php-ext-enable pdo_sqlite

# ============================================
# 3. ENABLE APACHE MODULES
# ============================================
RUN a2enmod rewrite headers

# ============================================
# 4. INSTALL COMPOSER
# ============================================
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# ============================================
# 5. SET WORKING DIRECTORY
# ============================================
WORKDIR /var/www/html

# ============================================
# 6. COPY APPLICATION FILES
# ============================================
# Copy all files from current directory to container
COPY . .

# ============================================
# 7. CREATE NECESSARY DIRECTORIES AND SET PERMISSIONS
# ============================================
RUN mkdir -p /var/www/html/session \
    && mkdir -p /var/www/html/logs \
    && touch /var/www/html/error.log \
    && touch /var/www/html/bot.sqlite \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod 664 /var/www/html/bot.sqlite \
    && chmod 664 /var/www/html/error.log \
    && chmod -R 775 /var/www/html/session \
    && chmod -R 775 /var/www/html/logs

# ============================================
# 8. INSTALL PHP DEPENDENCIES
# ============================================
RUN composer install --no-dev --optimize-autoloader

# ============================================
# 9. SET ENVIRONMENT VARIABLES (Placeholders)
# ============================================
ENV BOT_TOKEN=""
ENV ADMIN_ID=""
ENV REQUEST_GROUP_ID=""
ENV WEBHOOK_PASS=""
ENV CHANNEL_IDS=""
ENV CHANNEL_USERNAMES=""
ENV API_ID=""
ENV API_HASH=""
ENV PORT="10000"

# ============================================
# 10. CREATE ENTRYPOINT SCRIPT
# ============================================
RUN echo '#!/bin/bash\n\
set -e\n\
\n\
# Get PORT from environment variable (Render sets this automatically)\n\
PORT="${PORT:-10000}"\n\
\n\
echo "Starting Apache on port: $PORT"\n\
\n\
# Update Apache configuration to use the correct port\n\
sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf\n\
sed -i "s/:80/:${PORT}/g" /etc/apache2/sites-available/000-default.conf\n\
\n\
# Set ServerName to suppress warning\n\
echo "ServerName localhost" >> /etc/apache2/apache2.conf\n\
\n\
# Ensure log files are writable\n\
chown www-data:www-data /var/www/html/logs /var/www/html/error.log /var/www/html/bot.sqlite\n\
\n\
# Start Apache in foreground\n\
exec apache2-foreground\n\
' > /entrypoint.sh && chmod +x /entrypoint.sh

# ============================================
# 11. SET ENTRYPOINT
# ============================================
ENTRYPOINT ["/entrypoint.sh"]

# ============================================
# 12. EXPOSE PORT (Dynamic, but document 10000)
# ============================================
EXPOSE 10000
