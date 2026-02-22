#!/bin/bash
set -e

echo "🚀 BMS container startup initiated."

if [ ! -f "/var/www/html/vendor/autoload.php" ]; then
  echo "📦 vendor/autoload.php not found. Running composer install..."
  rm -rf /var/www/html/vendor
  composer install --no-interaction --prefer-dist --no-progress
else
  echo "✅ vendor/autoload.php exists. Skipping composer install."
fi

mkdir -p /var/www/html/var
chown -R www-data:www-data /var/www/html/var || true

echo "⏳ Waiting for database to be ready..."
until php bin/console doctrine:query:sql "SELECT 1" > /dev/null 2>&1; do
  sleep 2
done
echo "✅ Database is ready."

LOCK_FILE=/tmp/doctrine_migrate.lock
if [ -f "$LOCK_FILE" ]; then
  echo "⏭️  Migrations already attempted (lock exists), skipping."
else
  touch "$LOCK_FILE"
  echo "🗄️  Running migrations..."
  SYMFONY_DEPRECATIONS_HELPER=disabled php bin/console doctrine:migrations:migrate \
    --no-interaction --allow-no-migration --all-or-nothing -vvv
  echo "✅ Migrations are done."
fi

echo "✅ BMS is ready."

exec apache2-foreground