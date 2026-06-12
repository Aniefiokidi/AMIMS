<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';
requireLogin();

$unreadCount = getUnreadNotifCount($pdo, currentUserId());
$role        = currentRole();
$pageTitle   = $pageTitle ?? 'AMIMS';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= sanitize($pageTitle) ?> — AMIMS</title>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

<div class="layout">
  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <span class="brand-icon">&#9830;</span>
      <span class="brand-text">AMIMS</span>
    </div>

    <nav class="sidebar-nav">
      <a href="<?= BASE_URL ?>modules/dashboard/index.php"
         class="nav-item <?= (strpos($_SERVER['PHP_SELF'], '/dashboard/') !== false) ? 'active' : '' ?>">
        <span class="nav-icon">&#9783;</span> Dashboard
      </a>

      <?php if (in_array($role, ['admin', 'manager'])): ?>
      <a href="<?= BASE_URL ?>modules/users/index.php"
         class="nav-item <?= (strpos($_SERVER['PHP_SELF'], '/users/') !== false) ? 'active' : '' ?>">
        <span class="nav-icon">&#9786;</span> Users
      </a>
      <a href="<?= BASE_URL ?>modules/departments/index.php"
         class="nav-item <?= (strpos($_SERVER['PHP_SELF'], '/departments/') !== false) ? 'active' : '' ?>">
        <span class="nav-icon">&#9962;</span> Departments
      </a>
      <a href="<?= BASE_URL ?>modules/categories/index.php"
         class="nav-item <?= (strpos($_SERVER['PHP_SELF'], '/categories/') !== false) ? 'active' : '' ?>">
        <span class="nav-icon">&#9741;</span> Categories
      </a>
      <?php endif; ?>

      <a href="<?= BASE_URL ?>modules/assets/index.php"
         class="nav-item <?= (strpos($_SERVER['PHP_SELF'], '/assets/') !== false) ? 'active' : '' ?>">
        <span class="nav-icon">&#9874;</span> Assets
      </a>

      <a href="<?= BASE_URL ?>modules/maintenance/index.php"
         class="nav-item <?= (strpos($_SERVER['PHP_SELF'], '/maintenance/') !== false) ? 'active' : '' ?>">
        <span class="nav-icon">&#9881;</span> Maintenance
      </a>

      <a href="<?= BASE_URL ?>modules/inventory/index.php"
         class="nav-item <?= (strpos($_SERVER['PHP_SELF'], '/inventory/') !== false) ? 'active' : '' ?>">
        <span class="nav-icon">&#9636;</span> Inventory
      </a>

      <a href="<?= BASE_URL ?>modules/notifications/index.php"
         class="nav-item <?= (strpos($_SERVER['PHP_SELF'], '/notifications/') !== false) ? 'active' : '' ?>">
        <span class="nav-icon">&#9993;</span> Notifications
        <?php if ($unreadCount > 0): ?>
          <span class="notif-badge"><?= $unreadCount ?></span>
        <?php endif; ?>
      </a>

      <?php if (in_array($role, ['admin', 'manager'])): ?>
      <a href="<?= BASE_URL ?>modules/reports/index.php"
         class="nav-item <?= (strpos($_SERVER['PHP_SELF'], '/reports/') !== false) ? 'active' : '' ?>">
        <span class="nav-icon">&#9878;</span> Reports
      </a>
      <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
      <a href="<?= BASE_URL ?>modules/auth/logout.php" class="nav-item logout-link">
        <span class="nav-icon">&#9099;</span> Logout
      </a>
    </div>
  </aside>

  <!-- Main content area -->
  <div class="main-wrapper">
    <!-- Topbar -->
    <header class="topbar">
      <button class="hamburger" id="hamburger" aria-label="Toggle menu">&#9776;</button>
      <div class="topbar-title"><?= sanitize($pageTitle) ?></div>
      <div class="topbar-user">
        <span class="role-pill"><?= sanitize(str_replace('_', ' ', ucwords($role))) ?></span>
        <span class="user-name"><?= currentUserName() ?></span>
      </div>
    </header>

    <!-- Flash messages -->
    <?php
    $flashSuccess = getFlash('success');
    $flashError   = getFlash('error');
    $flashInfo    = getFlash('info');
    ?>
    <?php if ($flashSuccess): ?>
      <div class="alert alert-success" role="alert">
        <span class="alert-icon">&#10004;</span> <?= sanitize($flashSuccess) ?>
        <button class="alert-close" onclick="this.parentElement.remove()">&#10005;</button>
      </div>
    <?php endif; ?>
    <?php if ($flashError): ?>
      <div class="alert alert-danger" role="alert">
        <span class="alert-icon">&#9888;</span> <?= sanitize($flashError) ?>
        <button class="alert-close" onclick="this.parentElement.remove()">&#10005;</button>
      </div>
    <?php endif; ?>
    <?php if ($flashInfo): ?>
      <div class="alert alert-info" role="alert">
        <span class="alert-icon">&#9432;</span> <?= sanitize($flashInfo) ?>
        <button class="alert-close" onclick="this.parentElement.remove()">&#10005;</button>
      </div>
    <?php endif; ?>

    <main class="content">
