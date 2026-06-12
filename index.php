<?php
declare(strict_types=1);
// Root entry point — redirect to login
$rootDir = __DIR__;
require_once $rootDir . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'modules/dashboard/index.php');
} else {
    header('Location: ' . BASE_URL . 'modules/auth/login.php');
}
exit;
