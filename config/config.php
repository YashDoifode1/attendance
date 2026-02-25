<?php
// Start session if needed
// session_start();

/**
 * Load .env file
 * (shared loader — safe to include multiple times)
 */
$envPath = dirname(__DIR__) . '/.env';

if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");
        $_ENV[$key] = $value;
    }
} else {
    die('.env file not found.');
}

/**
 * Application Settings (from ENV)
 */
define('APP_NAME', $_ENV['APP_NAME'] ?? 'My Application');
define('APP_URL', rtrim($_ENV['APP_URL'] ?? '', '/'));
