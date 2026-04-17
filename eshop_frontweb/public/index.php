<?php

use App\Kernel;

$_SERVER['APP_RUNTIME_OPTIONS'] = [
    'disable_dotenv' => true,
];

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    return new Kernel(
        $context['APP_ENV'] ?? 'dev',
        (bool) ($context['APP_DEBUG'] ?? true)
    );
};