FROM php:8.1-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Enable Apache modules
RUN a2enmod rewrite headers

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/backups \
    && chmod 666 /var/www/html/*.csv \
    && chmod 666 /var/www/html/*.json \
    && chmod 666 /var/www/html/*.log

# Configure Apache
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Expose port
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
