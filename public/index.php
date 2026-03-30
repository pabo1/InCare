<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Laragon / Apache may inject APP_* vars into the web process.
// If they differ from this project's .env, Laravel can ignore the local APP_KEY.
foreach (['APP_ENV', 'APP_KEY', 'APP_DEBUG', 'APP_URL'] as $variable) {
    if (array_key_exists($variable, $_SERVER)) {
        unset($_SERVER[$variable]);
    }

    if (array_key_exists($variable, $_ENV)) {
        unset($_ENV[$variable]);
    }

    putenv($variable);
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
