<?php
declare(strict_types=1);
$pageTitle = 'Add User';
$rootDir   = dirname(dirname(dirname(__FILE__)));
require_once $rootDir . '/includes/header.php';
requireRole(['admin', 'manager']);

$errors = [];
$vals   = ['full_name' => '', 'email' => '', 'role' => '', 'dept_id' => '', 'is_active' => 1];

$departments = $pdo->query("SELECT dept_id, dept_name FROM departments ORDER BY dept_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token. Please try again.';
    } else {
        $vals['full_name'] = trim($_POST['full_name'] ?? '');
        $vals['email']     = trim($_POST['email']     ?? '');
        $vals['role']      = trim($_POST['role']      ?? '');
        $vals['dept_id']   = $_POST['dept_id']        ?? '';
        $vals['is_active'] = isset($_POST['is_active']) ? 1 : 0;
        $password          = $_POST['password']        ?? '';
        $confirmPw         = $_POST['confirm_password'] ?? '';

        if (!$vals['full_name']) $errors[] = 'Full name is required.';
        if (!$vals['email'])     $errors[] = 'Email is required.';
        elseif (!filter_var($vals['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
        if (!$vals['role'])      $errors[] = 'Role is required.';
        if (!$password)          $errors[] = 'Password is required.';
        elseif (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
        elseif ($password !== $confirmPw) $errors[] = 'Passwords do not match.';

        if (empty($errors)) {
            // Check email uniqueness
            $chk = $pdo->prepare("SELECT user_id FROM users WHERE email = :email");
            $chk->execute([':email' => $vals['email']]);
            if ($chk->fetch()) {
                $errors[] = 'A user with this email already exists.';
            }
        }

        if (empty($errors)) {
            $hash   = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $deptId = $vals['dept_id'] !== '' ? (int)$vals['dept_id'] : null;
            $stmt   = $pdo->prepare(
                "INSERT INTO users (full_name, email, password_hash, role, dept_id, is_active)
                 VALUES (:full_name, :email, :hash, :role, :dept_id, :is_active)"
            );
            $stmt->execute([
                ':full_name' => $vals['full_name'],
                ':email'     => $vals['email'],
                ':hash'      => $hash,
                ':role'      => $vals['role'],
                ':dept_id'   => $deptId,
                ':is_active' => $vals['is_active'],
            ]);
            flash('success', 'User "' . $vals['full_name'] . '" created successfully.');
            header('Location: ' . BASE_URL . 'modules/users/index.php');
            exit;
        }
    }
}
?>

<div class="page-header">
  <div>
    <h1>Add User</h1>
    <p class="breadcrumb"><a href="<?= BASE_URL ?>modules/users/index.php">Users</a> &rsaquo; Add</p>
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
               value="<?= sanitize($vals['full_name']) ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label" for="email">Email Address <span class="required">*</span></label>
        <input type="email" id="email" name="email" class="form-control"
               value="<?= sanitize($vals['email']) ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label" for="role">Role <span class="required">*</span></label>
        <select id="role" name="role" class="form-control" required>
          <option value="">— Select role —</option>
          <option value="admin"               <?= $vals['role']==='admin'               ? 'selected' : '' ?>>Administrator</option>
          <option value="manager"             <?= $vals['role']==='manager'             ? 'selected' : '' ?>>Manager</option>
          <option value="maintenance_officer" <?= $vals['role']==='maintenance_officer' ? 'selected' : '' ?>>Maintenance Officer</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="dept_id">Department</label>
        <select id="dept_id" name="dept_id" class="form-control">
          <option value="">— None —</option>
          <?php foreach ($departments as $d): ?>
            <option value="<?= $d['dept_id'] ?>" <?= (string)$vals['dept_id'] === (string)$d['dept_id'] ? 'selected' : '' ?>>
              <?= sanitize($d['dept_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="password">Password <span class="required">*</span></label>
        <input type="password" id="password" name="password" class="form-control"
               placeholder="Min. 8 characters" required autocomplete="new-password">
      </div>
      <div class="form-group">
        <label class="form-label" for="confirm_password">Confirm Password <span class="required">*</span></label>
        <input type="password" id="confirm_password" name="confirm_password" class="form-control"
               placeholder="Re-enter password" required autocomplete="new-password">
      </div>
      <div class="form-group">
        <label class="form-label">Status</label>
        <label class="checkbox-item" style="margin-top:0.4rem;">
          <input type="checkbox" name="is_active" value="1" <?= $vals['is_active'] ? 'checked' : '' ?>>
          Active account
        </label>
      </div>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Create User</button>
      <a href="<?= BASE_URL ?>modules/users/index.php" class="btn btn-secondary">Cancel</a>
    </div>
  </form>
</div>

<script>window.BASE_URL='<?= BASE_URL ?>';window.CSRF_TOKEN='<?= generateCsrfToken() ?>';</script>
<script src="<?= BASE_URL ?>assets/js/app.js"></script>
</main><footer class="footer">&copy; <?= date('Y') ?> AMIMS</footer>
</div></div></body></html>
