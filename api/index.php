<?php

define('LARAVEL_START', microtime(true));

// Vercel-only front controller. The normal public/index.php remains unchanged.
$temporary = sys_get_temp_dir().'/assetkeeper';
foreach (['framework/views', 'framework/cache/data', 'framework/sessions', 'logs', 'bootstrap/cache', 'app/private', 'app/public'] as $directory) {
    $path = $temporary.'/'.$directory;
    if (! is_dir($path) && ! mkdir($path, 0700, true) && ! is_dir($path)) {
        http_response_code(500);
        exit('Application initialization failed.');
    }
}

// Only disposable framework files belong on the function filesystem.
foreach ([
    'LARAVEL_STORAGE_PATH' => $temporary,
    'VIEW_COMPILED_PATH' => $temporary.'/framework/views',
    'APP_CONFIG_CACHE' => $temporary.'/bootstrap/cache/config.php',
    'APP_ROUTES_CACHE' => $temporary.'/bootstrap/cache/routes.php',
    'APP_EVENTS_CACHE' => $temporary.'/bootstrap/cache/events.php',
    'APP_SERVICES_CACHE' => $temporary.'/bootstrap/cache/services.php',
    'APP_PACKAGES_CACHE' => $temporary.'/bootstrap/cache/packages.php',
] as $key => $value) {
    $_ENV[$key] = $_SERVER[$key] = $value;
    putenv($key.'='.$value);
}

require_once __DIR__.'/../vendor/autoload.php';

// Vercel terminates HTTPS at its proxy. Do not trust forwarded host headers.
Illuminate\Http\Middleware\TrustProxies::at('*');
Illuminate\Http\Middleware\TrustProxies::withHeaders(
    Illuminate\Http\Request::HEADER_X_FORWARDED_FOR
    | Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO
);

$app = require __DIR__.'/../bootstrap/app.php';
// Recognize the temporary absolute path on Windows during local smoke tests too.
$app->addAbsoluteCachePathPrefix($temporary);
$app->handleRequest(Illuminate\Http\Request::capture());
