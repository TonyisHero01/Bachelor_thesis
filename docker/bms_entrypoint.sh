#!/bin/bash

echo "🚀 BMS container startup initiated."

echo "✅ About to source /common_python_venv.sh"
source /common_python_venv.sh
echo "✅ Sourced /common_python_venv.sh"
create_venv_and_install "/var/www/html/python_scripts"
create_venv_and_install "/var/www/html/../eshop_frontweb/python_scripts"

echo "⏳ Waiting for database to be ready..."
until php bin/console doctrine:query:sql "SELECT 1" > /dev/null 2>&1; do
  sleep 2
done
echo "✅ ENTRYPOINT REACHED"
echo "✅ Database is ready."
php bin/console doctrine:migrations:diff --no-interaction || true
php bin/console doctrine:migrations:migrate --no-interaction

echo "🚀 Running training script..."
source /var/www/html/python_scripts/venv/bin/activate
python /var/www/html/python_scripts/tf-idf.py "__TRAIN__"
deactivate

exec apache2-foreground