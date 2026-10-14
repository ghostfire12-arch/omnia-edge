<?php
/**
 * OMNIA EDGE STRATEGIC SERVICES
 * Database connection (PDO / MySQL).
 *
 * Include this file to get a ready-to-use $pdo connection.
 * All queries throughout the app use PDO prepared statements.
 */

require_once __DIR__ . '/config.php';

try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, $options);
} catch (PDOException $e) {
    if (APP_DEBUG) {
        die('Database connection failed: ' . $e->getMessage());
    }
    error_log('DB connection error: ' . $e->getMessage());
    die('We are unable to connect right now. Please try again shortly.');
}
