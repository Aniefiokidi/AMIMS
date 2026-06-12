<?php
declare(strict_types=1);
$pageTitle = 'Edit User';
$rootDir   = dirname(dirname(dirname(__FILE__)));
require_once $rootDir . '/includes/header.php';
requireRole(['admin', 'manager']);

$userId = (int)($_GET['id'] ?? 0);
if (!$userId) {
    flash('error', 'No user specified.');
    header('Location: ' . BASE_URL . 'modules/users/index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = :id");
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();
if (!$user) {
    flash('error', 'User not found.');
    header('Location: ' . BASE_URL . 'modules/users/index.php');
    exit;
}

$departments = $pdo->query("SELECT dept_id, dept_name FROM departments ORDER BY dept_name")->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $fullName  = trim($_POST['full_name'] ?? '');
        $email     = trim($_POST['email']     ?? '');
        $role      = trim($_POST['role']      ?? '');
        $deptId    = $_POST['dept_id']        ?? '';
        $isActive  = isset($_POST['is_active']) ? 1 : 0;
        $password  = $_POST['password']        ?? '';
        $confirmPw = $_POST['confirm_password'] ?? '';

        if (!$fullName) $errors[] = 'Full name is required.';
        if (!$email)    $errors[] = 'Email is required.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email.';
        if (!$role)     $errors[] = 'Role is required.';
        if ($password && strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
        if ($password && $password !== $confirmPw) $errors[] = 'Passwords do not match.';

        if (empty($errors)) {
            $chk = $pdo->prepare("SELECT user_id FROM users WHERE email=:e AND user_id != :id");
            $chk->execute([':e' => $email, ':id' => $userId]);
            if ($chk->fetch()) $errors[] = 'That email belongs to another user.';
        }

        if (empty($errors)) {
            $deptVal = $deptId !== '' ? (int)$deptId : null;
            if ($password) {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $pdo->prepare(
                    "UPDATE users SET full_name=:fn, email=:em, role=:ro, dept_id=:di, is_active=:ia, password_hash=:ph WHERE user_id=:id"
                )->execute([':fn'=>$fullName,':em'=>$email,':ro'=>$role,':di'=>$deptVal,':ia'=>$isActive,':ph'=>$hash,':id'=>$userId]);
            } else {
                $pdo->prepare(
                    "UPDATE users SET full_name=:fn, email=:em, role=:ro, dept_id=:di, is_active=:ia WHERE user_id=:id"
                )->execute([':fn'=>$fullName,':em'=>$email,':ro'=>$role,':di'=>$deptVal,':ia'=>$isActive,':id'=>$userId]);
            }
            flash('success', 'User updated successfully.');
            header('Location: ' . BASE_URL . 'modules/users/index.php');
            exit;
        }

        // Repopulate vals on error
        $user['full_name'] = $fullName;
        $user['email']     = $email;
        $user['role']      = $role;
        $user['dept_id']   = $deptId;
        $user['is_active'] = $isActive;
    }
}
?>

<div class="page-header">
  <div>
    <h1>Edit User</h1>
    <p class="breadcrumb"><a href="<?= BASE_URL ?>modules/users/index.php">Users</a> &rsaquo; Edit</p>
  </div>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <span class="alert-icon">&#9888;</span>
    <div><?php foreach ($errors as $e): ?><div><?= sanitize($e) ?></div><?php endforeach; ?></div>
  </div>
<?php endif; ?>

<div class="form-card">
  <form method="POST" action="" novalidate>
    <?= csrfField() ?>
    <div class="form-grid">
      <div class="form-group">
        <label class="form-label" for="full_name">Full Name <span class="required">*</span></label>
        <input type="text" id="full_name" name="full_name" class="form-control"
               value="<?= sanitize($user['full_name']) ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label" for="email">Email Address <span class="required">*</span></label>
        <input type="email" id="email" name="email" class="form-control"
               value="<?= sanitize($user['email']) ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label" for="role">Role <span class="required">*</span></label>
        <select id="role" name="role" class="form-control" required>
          <option value="">— Select role —</option>
          <option value="admin"               <?= $user['role']==='admin'               ? 'selected' : '' ?>>Administrator</option>
          <option value="manager"             <?= $user['role']==='manager'             ? 'selected' : '' ?>>Manager</option>
          <option value="maintenance_officer" <?= $user['role']==='maintenance_officer' ? 'selected' : '' ?>>Maintenance Officer</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="dept_id">Department</label>
        <select id="dept_id" name="dept_id" class="form-control">
          <option value="">— None —</option>
          <?php foreach ($departments as $d): ?>
            <option value="<?= $d['dept_id'] ?>" <?= (string)($user['dept_id'] ?? '') === (string)$d['dept_id'] ? 'selected' : '' ?>>
              <?= sanitize($d['dept_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="password">New Password</label>
        <input type="password" id="password" name="password" class="form-control"
               placeholder="Leave blank to keep current" autocomplete="new-password">
        <span class="form-hint">Only fill in if you want to change the password.</span>
      </div>
      <div class="form-group">
        <label class="form-label" for="confirm_password">Confirm New Password</label>
        <input type="password" id="confirm_password" name="confirm_password" class="form-control"
               placeholder="Re-enter new password" autocomplete="new-password">
      </div>
      <div class="form-group">
        <label class="form-label">Status</label>
        <label class="checkbox-item" style="margin-top:0.4rem;">
          <input type="checkbox" name="is_active" value="1" <?= $user['is_active'] ? 'checked' : '' ?>>
          Active account
        </label>
      </div>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Save Changes</button>
      <a href="<?= BASE_URL ?>modules/users/index.php" class="btn btn-secondary">Cancel</a>
    </div>
  </form>
</div>

<script>window.BASE_URL='<?= BASE_URL ?>';window.CSRF_TOKEN='<?= generateCsrfToken() ?>';</script>
<script src="<?= BASE_URL ?>assets/js/app.js"></script>
</main><footer class="footer">&copy; <?= date('Y') ?> AMIMS</footer>
</div></div></body></html>
