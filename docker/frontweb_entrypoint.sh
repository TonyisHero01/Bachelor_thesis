#!/bin/bash
set -e

echo "🚀 Frontweb container startup initiated."

cd /var/www/html

if [ ! -f "vendor/autoload.php" ]; then
  echo "❌ vendor/autoload.php not found."
  echo "This image expects Composer dependencies to be installed during Docker build."
  exit 1
fi

mkdir -p var
chown -R www-data:www-data var || true

echo "✅ Database dependency should already be healthy via docker compose depends_on."
echo "✅ Frontweb is ready."

exec apache2-foreground