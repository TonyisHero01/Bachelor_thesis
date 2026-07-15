<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

$envFile = dirname(__DIR__).'/.env';

if (method_exists(Dotenv::class, 'bootEnv') && is_file($envFile)) {
    (new Dotenv())->bootEnv($envFile);
}

$appDebug = $_SERVER['APP_DEBUG']
    ?? $_ENV['APP_DEBUG']
    ?? getenv('APP_DEBUG')
    ?? false;

if (filter_var($appDebug, FILTER_VALIDATE_BOOL)) {
    umask(0000);
}