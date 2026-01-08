#!/bin/bash
set -e

echo "🚀 Frontweb container startup initiated."

if [ ! -f "/var/www/html/vendor/autoload.php" ]; then
  echo "📦 vendor/autoload.php not found. Running composer install..."
  rm -rf /var/www/html/vendor
  composer install --no-interaction --prefer-dist --no-progress
else
  echo "✅ vendor/autoload.php exists. Skipping composer install."
fi

mkdir -p /var/www/html/var
chown -R www-data:www-data /var/www/html/var || true

source /common_python_venv.sh
create_venv_and_install "/var/www/html/python_scripts"

echo "✅ Frontweb is ready."

exec apache2-foreground