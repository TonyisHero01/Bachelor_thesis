#!/usr/bin/env bash

set -euo pipefail

export APP_ENV=test
export APP_DEBUG=1

SOURCE_DATABASE="${TEST_SOURCE_DATABASE:-app}"
TARGET_DATABASE="${TEST_TARGET_DATABASE:-app_test}"

echo "Checking source database '${SOURCE_DATABASE}' and rebuilding '${TARGET_DATABASE}'..."

DATABASE_URL="${DATABASE_URL}" \
SOURCE_DATABASE="${SOURCE_DATABASE}" \
TARGET_DATABASE="${TARGET_DATABASE}" \
php <<'PHP'
<?php

declare(strict_types=1);

$databaseUrl = getenv('DATABASE_URL');
$sourceDatabase = getenv('SOURCE_DATABASE') ?: 'app';
$targetDatabase = getenv('TARGET_DATABASE') ?: 'app_test';

if (!$databaseUrl) {
    fwrite(STDERR, "DATABASE_URL is not defined.\n");
    exit(1);
}

if (!preg_match('/^[a-zA-Z0-9_]+$/', $sourceDatabase)) {
    fwrite(STDERR, "Invalid source database name.\n");
    exit(1);
}

if (!preg_match('/^[a-zA-Z0-9_]+$/', $targetDatabase)) {
    fwrite(STDERR, "Invalid target database name.\n");
    exit(1);
}

if ($sourceDatabase === $targetDatabase) {
    fwrite(STDERR, "Source and target database names must be different.\n");
    exit(1);
}

$parts = parse_url($databaseUrl);

if ($parts === false) {
    fwrite(STDERR, "Unable to parse DATABASE_URL.\n");
    exit(1);
}

$host = $parts['host'] ?? 'db';
$port = (int) ($parts['port'] ?? 5432);
$user = isset($parts['user']) ? urldecode($parts['user']) : '';
$password = isset($parts['pass']) ? urldecode($parts['pass']) : '';

if ($user === '') {
    fwrite(STDERR, "Database user is missing from DATABASE_URL.\n");
    exit(1);
}

/**
 * Create a PostgreSQL PDO connection.
 */
function connectDatabase(
    string $host,
    int $port,
    string $database,
    string $user,
    string $password
): PDO {
    return new PDO(
        sprintf(
            'pgsql:host=%s;port=%d;dbname=%s',
            $host,
            $port,
            $database
        ),
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
}

/**
 * Quote a PostgreSQL identifier.
 */
function quoteIdentifier(string $identifier): string
{
    return '"' . str_replace('"', '""', $identifier) . '"';
}

try {
    /*
     * Check whether the source database contains data in at least one
     * application table. The Doctrine migrations metadata table is excluded.
     */
    $sourceConnection = connectDatabase(
        $host,
        $port,
        $sourceDatabase,
        $user,
        $password
    );

    $tables = $sourceConnection
        ->query(
            <<<'SQL'
SELECT table_name
FROM information_schema.tables
WHERE table_schema = 'public'
  AND table_type = 'BASE TABLE'
  AND table_name <> 'doctrine_migration_versions'
ORDER BY table_name
SQL
        )
        ->fetchAll(PDO::FETCH_COLUMN);

    if ($tables === []) {
        fwrite(
            STDERR,
            "The source database '{$sourceDatabase}' contains no application tables.\n"
        );
        fwrite(
            STDERR,
            "Run the migrations and create some application data before running the tests.\n"
        );
        exit(2);
    }

    $hasApplicationData = false;
    $firstNonEmptyTable = null;

    foreach ($tables as $table) {
        $qualifiedTable = quoteIdentifier('public')
            . '.'
            . quoteIdentifier((string) $table);

        $statement = $sourceConnection->query(
            sprintf(
                'SELECT EXISTS (SELECT 1 FROM %s LIMIT 1)',
                $qualifiedTable
            )
        );

        if ((bool) $statement->fetchColumn()) {
            $hasApplicationData = true;
            $firstNonEmptyTable = (string) $table;
            break;
        }
    }

    if (!$hasApplicationData) {
        fwrite(
            STDERR,
            "The source database '{$sourceDatabase}' contains tables but no application data.\n"
        );
        fwrite(
            STDERR,
            "Please create some products, customers, orders, or other required test data first.\n"
        );
        exit(3);
    }

    echo "Source data found in table '{$firstNonEmptyTable}'.\n";

    /*
     * Close the source connection before using the source database as
     * a PostgreSQL template.
     */
    $sourceConnection = null;

    $adminConnection = connectDatabase(
        $host,
        $port,
        'postgres',
        $user,
        $password
    );

    $quotedSource = quoteIdentifier($sourceDatabase);
    $quotedTarget = quoteIdentifier($targetDatabase);
    $quotedOwner = quoteIdentifier($user);

    /*
     * Temporarily prevent services from reconnecting to the source database.
     * PostgreSQL requires the template database to have no active sessions.
     */
    $sourceConnectionsDisabled = false;

    try {
        echo "Temporarily disabling new connections to '{$sourceDatabase}'...\n";

        $adminConnection->exec(
            sprintf(
                'ALTER DATABASE %s WITH ALLOW_CONNECTIONS false',
                $quotedSource
            )
        );

        $sourceConnectionsDisabled = true;

        $terminateSource = $adminConnection->prepare(
            <<<'SQL'
SELECT pg_terminate_backend(pid)
FROM pg_stat_activity
WHERE datname = :database
  AND pid <> pg_backend_pid()
SQL
        );

        $terminateSource->execute([
            'database' => $sourceDatabase,
        ]);

        $terminateTarget = $adminConnection->prepare(
            <<<'SQL'
SELECT pg_terminate_backend(pid)
FROM pg_stat_activity
WHERE datname = :database
  AND pid <> pg_backend_pid()
SQL
        );

        $terminateTarget->execute([
            'database' => $targetDatabase,
        ]);

        echo "Dropping old '{$targetDatabase}' database if it exists...\n";

        $adminConnection->exec(
            sprintf(
                'DROP DATABASE IF EXISTS %s',
                $quotedTarget
            )
        );

        echo "Copying '{$sourceDatabase}' to '{$targetDatabase}'...\n";

        $adminConnection->exec(
            sprintf(
                'CREATE DATABASE %s WITH TEMPLATE %s OWNER %s',
                $quotedTarget,
                $quotedSource,
                $quotedOwner
            )
        );

        echo "Database '{$targetDatabase}' was created successfully.\n";
    } finally {
        if ($sourceConnectionsDisabled) {
            $adminConnection->exec(
                sprintf(
                    'ALTER DATABASE %s WITH ALLOW_CONNECTIONS true',
                    $quotedSource
                )
            );

            echo "Connections to '{$sourceDatabase}' were re-enabled.\n";
        }
    }
} catch (Throwable $exception) {
    fwrite(
        STDERR,
        sprintf(
            "Unable to prepare the test database:\n%s\n",
            $exception->getMessage()
        )
    );

    exit(1);
}
PHP

echo "Test database was copied from '${SOURCE_DATABASE}'."
echo "Skipping migrations because schema and migration history were copied together."

echo "Running PHPUnit..."

php bin/phpunit "$@"