#!/bin/bash
set -e

echo "🚀 BMS container startup initiated."

cd /var/www/html

if [ ! -f "vendor/autoload.php" ]; then
  echo "❌ vendor/autoload.php not found."
  echo "This image expects Composer dependencies to be installed during Docker build."
  exit 1
fi

mkdir -p var
chown -R www-data:www-data var || true

LOCK_FILE=/tmp/doctrine_migrate.lock
MIGRATIONS_DIR=/var/www/html/migrations

if [ -f "$LOCK_FILE" ]; then
  echo "⏭️ Migrations already attempted (lock exists), skipping."
else
  touch "$LOCK_FILE"

  if [ -d "$MIGRATIONS_DIR" ] && find "$MIGRATIONS_DIR" -maxdepth 1 -type f -name 'Version*.php' | grep -q .; then
    echo "🗄️ Migration files found in $MIGRATIONS_DIR. Running existing migrations..."
    SYMFONY_DEPRECATIONS_HELPER=disabled php bin/console doctrine:migrations:migrate \
      --no-interaction \
      --allow-no-migration \
      --all-or-nothing \
      -vvv
    echo "✅ Migrations are done."
  else
    echo "ℹ️ No migration files found in $MIGRATIONS_DIR. Skipping doctrine migrations."
  fi
fi

echo "✅ BMS is ready."

exec apache2-foreground