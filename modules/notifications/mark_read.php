<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

$rootDir = dirname(dirname(dirname(__FILE__)));
require_once $rootDir . '/config/db.php';
require_once $rootDir . '/includes/functions.php';
require_once $rootDir . '/includes/auth.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$token   = $_POST['csrf_token'] ?? '';
$notifId = (int)($_POST['notif_id'] ?? 0);

if (!verifyCsrfToken($token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

if (!$notifId) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing notif_id']);
    exit;
}

$pdo->prepare("UPDATE notifications SET is_read=1 WHERE notif_id=:id")->execute([':id' => $notifId]);
echo json_encode(['ok' => true]);
