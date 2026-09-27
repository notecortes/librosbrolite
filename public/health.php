<?php
declare(strict_types=1);
require __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');
 $db = 'ok';
try {
    new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]);
} catch (Throwable $e) {
    $db = 'error';
    http_response_code(503);
}
echo json_encode(['ok' => $db === 'ok', 'db' => $db, 'app' => 'BookSwap']);