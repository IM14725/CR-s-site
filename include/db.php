<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $config = require __DIR__ . '/config.php';
    $dns = "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4";

    try {
        $pdo = new PDO($dns, $config['db_user'], $config['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        $pdo->exec("SET time_zone = '+03:00'");
    } catch (PDOException $e) {
        error_log('Database connection failed: ' . $e->getMessage());
        http_response_code(500);
        exit($config['debug']? $e->getMessage() : 'Sevices are currently unavailable. Please try again later.');
    }
    return $pdo;
}