<?php

include "config.php";

/**
 * Load .env file
 */
$envPath = dirname(__DIR__) . '/.env';

if (!file_exists($envPath)) {
    die('.env file not found.');
}

$lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

foreach ($lines as $line) {
    if (str_starts_with(trim($line), '#')) {
        continue;
    }

    [$key, $value] = array_map('trim', explode('=', $line, 2));
    $value = trim($value, "\"'");
    $_ENV[$key] = $value;
}

/**
 * Database Configuration (from ENV)
 */
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? '');
define('DB_USER', $_ENV['DB_USER'] ?? '');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');

define('SECRET_KEY', $_ENV['SECRET_KEY'] ?? '');
define('INSTITUTION_ID', $_ENV['INSTITUTION_ID'] ?? '');
define('QR_EXPIRY_MINUTES', (int)($_ENV['QR_EXPIRY_MINUTES'] ?? 10));

/**
 * Create PDO Connection
 */
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}
