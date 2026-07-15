#!/usr/bin/env bash

set -euo pipefail

export APP_ENV=test
export APP_DEBUG=1

echo "Checking test database..."

php bin/console doctrine:database:create \
    --env=test \
    --if-not-exists \
    --no-interaction

echo "Running database migrations..."

php bin/console doctrine:migrations:migrate \
    --env=test \
    --no-interaction \
    --allow-no-migration

echo "Running PHPUnit..."

php bin/phpunit "$@"