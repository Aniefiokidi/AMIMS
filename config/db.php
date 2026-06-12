<?php
declare(strict_types=1);

define('DB_PATH', dirname(__DIR__) . '/database/amims.db');

function getPDO(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        try {
            $pdo = new PDO('sqlite:' . DB_PATH, null, null, $options);
            $pdo->exec('PRAGMA foreign_keys = ON;');
            $pdo->exec('PRAGMA journal_mode = WAL;');
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            die('<h2 style="font-family:sans-serif;color:#C53030;padding:2rem;">Database connection failed. Please check that database/amims.db exists and is writable.</h2>');
        }
    }
    return $pdo;
}

$pdo = getPDO();
