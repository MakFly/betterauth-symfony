<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

// Load vendor autoloader
if (!file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
    throw new LogicException('Dependencies are missing. Try running "composer install".');
}

require dirname(__DIR__) . '/vendor/autoload.php';

// Load environment variables if .env file exists
if (file_exists(dirname(__DIR__) . '/.env.test')) {
    (new Dotenv())->bootEnv(dirname(__DIR__) . '/.env.test');
}
