<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']);
}
session_destroy();

$rootDir = dirname(dirname(dirname(__FILE__)));
require_once $rootDir . '/includes/functions.php';
header('Location: ' . BASE_URL . 'modules/auth/login.php');
exit;
