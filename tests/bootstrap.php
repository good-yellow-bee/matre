<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

// Force test environment before loading dotenv
$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';

// Clear Docker-injected DB_NAME so .env.test can override it
unset($_SERVER['DB_NAME'], $_ENV['DB_NAME']);
putenv('DB_NAME');

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__) . '/.env');
}

// Safety check: fail fast if test database not configured
if (($_ENV['DB_NAME'] ?? '') === 'matre') {
    throw new RuntimeException('Tests cannot run against production database! Check .env.test has DB_NAME=matre_test');
}

if ($_SERVER['APP_DEBUG'] ?? false) {
    umask(0o000);
}
