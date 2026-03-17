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

# Create required directories and files with proper permissions
RUN mkdir -p /var/www/html/backups \
    && touch /var/www/html/movies.csv \
    && touch /var/www/html/users.json \
    && touch /var/www/html/bot_stats.json \
    && touch /var/www/html/movie_requests.json \
    && touch /var/www/html/bot_activity.log \
    && touch /var/www/html/error.log \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/backups \
    && chmod 666 /var/www/html/*.csv \
    && chmod 666 /var/www/html/*.json \
    && chmod 666 /var/www/html/*.log

# Create default content for files if they don't exist
RUN echo "movie_name,message_id,date,video_path,quality,size,language,channel_type,channel_id,channel_username" > /var/www/html/movies.csv \
    && echo '{"users":{},"total_requests":0,"message_logs":[],"daily_stats":[]}' > /var/www/html/users.json \
    && echo '{"total_movies":0,"total_users":0,"total_searches":0,"total_downloads":0,"successful_searches":0,"failed_searches":0,"daily_activity":{},"last_updated":"'$(date -Iseconds)'"}' > /var/www/html/bot_stats.json \
    && echo '{"requests":[],"pending_approval":[],"completed_requests":[],"user_request_count":{}}' > /var/www/html/movie_requests.json \
    && echo "["$(date -Iseconds)"] SYSTEM: Docker build completed" > /var/www/html/bot_activity.log \
    && echo "["$(date -Iseconds)"] PHP Notice: Docker container started" > /var/www/html/error.log

# Configure Apache
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Expose port
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
