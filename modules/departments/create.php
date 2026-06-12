<?php
// Redirect to the combined list+form page
declare(strict_types=1);
$rootDir = dirname(dirname(dirname(__FILE__)));
require_once $rootDir . '/includes/functions.php';
header('Location: ' . BASE_URL . 'modules/departments/index.php');
exit;
