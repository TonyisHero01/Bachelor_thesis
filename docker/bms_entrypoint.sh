#!/bin/bash

echo "🚀 BMS container startup initiated."

echo "✅ About to source /common_python_venv.sh"
source /common_python_venv.sh
echo "✅ Sourced /common_python_venv.sh"
create_venv_and_install "/var/www/html/python_scripts"

echo "⏳ Waiting for database to be ready..."
until php bin/console doctrine:query:sql "SELECT 1" > /dev/null 2>&1; do
  sleep 2
done
echo "✅ ENTRYPOINT REACHED"
echo "✅ Database is ready."

LOCK_FILE=/tmp/doctrine_migrate.lock
if [ -f "$LOCK_FILE" ]; then
  echo "⏭️  Migrations already attempted (lock exists), skipping."
else
  touch "$LOCK_FILE"
  echo "🗄️  Running migrations..."
  SYMFONY_DEPRECATIONS_HELPER=disabled php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --all-or-nothing -vvv
  echo "✅ Mgrations are done."
fi

echo "✅ BMS is ready."

exec apache2-foreground