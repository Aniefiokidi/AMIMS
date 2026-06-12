<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

$rootDir = dirname(dirname(dirname(__FILE__)));
require_once $rootDir . '/config/db.php';
require_once $rootDir . '/includes/functions.php';

// Already logged in → redirect
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'modules/dashboard/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'Invalid request. Please refresh and try again.';
    } else {
        $email    = trim($_POST['email']    ?? '');
        $password = $_POST['password']      ?? '';
        $role     = trim($_POST['role']     ?? '');

        if (!$email || !$password || !$role) {
            $error = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } else {
            $stmt = $pdo->prepare(
                "SELECT user_id, full_name, email, password_hash, role, dept_id, is_active
                 FROM users WHERE email = :email LIMIT 1"
            );
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();

            if ($user && $user['is_active'] && password_verify($password, $user['password_hash'])) {
                if ($user['role'] !== $role) {
                    $error = 'The selected role does not match your account.';
                } else {
                    session_regenerate_id(true);
                    $_SESSION['user_id']   = $user['user_id'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['email']     = $user['email'];
                    $_SESSION['role']      = $user['role'];
                    $_SESSION['dept_id']   = $user['dept_id'];
                    unset($_SESSION['csrf_token']); // regenerate on next page
                    header('Location: ' . BASE_URL . 'modules/dashboard/index.php');
                    exit;
                }
            } else {
                // Generic error — no username enumeration
                $error = 'Invalid credentials or inactive account. Please try again.';
            }
        }
    }
}

// CSRF token for form
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$safeEmail = htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8');
$safeRole  = htmlspecialchars($_POST['role']  ?? '', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — AMIMS</title>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
<div class="login-layout">

  <!-- Left panel -->
  <div class="login-left">
    <div class="login-logo">&#9830;</div>
    <h1 class="login-system-name">AMIMS</h1>
    <div class="login-divider"></div>
    <p class="login-tagline">
      Asset, Maintenance &amp; Inventory Management System<br>
      <strong style="color:rgba(255,255,255,0.9)">Oil &amp; Gas Division</strong>
    </p>
    <p class="login-tagline" style="margin-top:1.5rem;font-size:0.82rem;">
      Streamlining asset tracking, preventive maintenance scheduling,
      and inventory control for the oil and gas industry.
    </p>
  </div>

  <!-- Right panel -->
  <div class="login-right">
    <h2 class="login-title">Welcome back</h2>
    <p class="login-subtitle">Sign in to your AMIMS account to continue.</p>

    <?php if ($error): ?>
      <div class="login-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="POST" action="" class="login-form" novalidate>
      <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

      <div class="form-group">
        <label class="form-label" for="email">Email Address</label>
        <input type="email" id="email" name="email" class="form-control"
               placeholder="you@company.ng" value="<?= $safeEmail ?>" required autocomplete="email">
      </div>

      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <input type="password" id="password" name="password" class="form-control"
               placeholder="••••••••" required autocomplete="current-password">
      </div>

      <div class="form-group">
        <label class="form-label" for="role">Role</label>
        <select id="role" name="role" class="form-control" required>
          <option value="">— Select role —</option>
          <option value="admin"               <?= ($safeRole === 'admin')               ? 'selected' : '' ?>>Administrator</option>
          <option value="manager"             <?= ($safeRole === 'manager')             ? 'selected' : '' ?>>Manager</option>
          <option value="maintenance_officer" <?= ($safeRole === 'maintenance_officer') ? 'selected' : '' ?>>Maintenance Officer</option>
        </select>
      </div>

      <button type="submit" class="login-btn">Sign In</button>
    </form>

    <p style="margin-top:2rem;font-size:0.75rem;color:var(--muted);text-align:center;">
      &copy; <?= date('Y') ?> AMIMS — Oil &amp; Gas Division. All rights reserved.
    </p>
  </div>

</div>
</body>
</html>
