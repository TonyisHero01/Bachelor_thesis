#!/bin/bash
set -e

echo "🚀 Frontweb container startup initiated."

# Ensure the shared root .env is visible inside the Symfony project
mkdir -p /app_root_env
if [ ! -f /var/www/html/.env ] && [ -f /app_root_env/.env ]; then
  echo "🔗 Linking shared root .env into /var/www/html/.env ..."
  ln -s /app_root_env/.env /var/www/html/.env
fi

cd /var/www/html

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

until php -r '
$databaseUrl = getenv("DATABASE_URL");

if (!$databaseUrl) {
    fwrite(STDERR, "DATABASE_URL is not set\n");
    exit(1);
}

$parts = parse_url($databaseUrl);
if ($parts === false) {
    fwrite(STDERR, "Failed to parse DATABASE_URL\n");
    exit(1);
}

$host = $parts["host"] ?? "db";
$port = $parts["port"] ?? 5432;
$dbname = isset($parts["path"]) ? ltrim($parts["path"], "/") : "app";
$user = $parts["user"] ?? "user";
$pass = $parts["pass"] ?? "password";

try {
    $pdo = new PDO(
        "pgsql:host={$host};port={$port};dbname={$dbname}",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->query("SELECT 1");
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
' > /dev/null 2>&1; do
  echo "⌛ Database not ready yet, retrying in 2 seconds..."
  sleep 2
done

echo "✅ Database is ready."
echo "✅ Frontweb is ready."

exec apache2-foreground