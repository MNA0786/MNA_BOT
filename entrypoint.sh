#!/bin/bash
set -e

# PORT environment variable ko read karo (default 10000)
PORT="${PORT:-10000}"

# Apache config files mein port update karo
sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf
sed -i "s/:80/:${PORT}/g" /etc/apache2/sites-available/000-default.conf

# Apache start karo
apache2-foreground
