#!/bin/bash
set -e

echo "🚀 Starting Entertainment Tadka Bot..."

# Set proper permissions
echo "📁 Setting permissions..."
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html
chmod -R 777 /var/www/html/database
chmod -R 777 /var/www/html/backups
chmod -R 777 /var/www/html/cache
chmod -R 777 /var/www/html/logs
chmod -R 777 /var/www/html/storage
chmod 666 /var/www/html/*.csv 2>/dev/null || true
chmod 666 /var/www/html/*.json 2>/dev/null || true

# Create SQLite database if not exists
if [ ! -f /var/www/html/database/movies.db ]; then
    echo "🗄️ Creating SQLite database..."
    touch /var/www/html/database/movies.db
    chmod 666 /var/www/html/database/movies.db
fi

# Initialize files
echo "📄 Initializing data files..."
php -r "
    require 'index.php';
    initialize_files();
    echo '✅ Files initialized\n';
"

# Start cron service
echo "⏰ Starting cron service..."
service cron start

# Set webhook
echo "🔗 Setting Telegram webhook..."
WEBHOOK_URL="${WEBHOOK_URL:-https://${RENDER_EXTERNAL_HOSTNAME}}"
php -r "
    \$result = apiRequest('setWebhook', ['url' => '$WEBHOOK_URL']);
    echo \$result ? '✅ Webhook set successfully\n' : '❌ Failed to set webhook\n';
"

echo "✅ Bot is ready! Starting Apache..."

# Start Apache in foreground
exec apache2-foreground
