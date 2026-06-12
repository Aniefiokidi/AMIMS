<?php
declare(strict_types=1);
$pageTitle = 'Departments';
$rootDir   = dirname(dirname(dirname(__FILE__)));
require_once $rootDir . '/includes/header.php';
requireRole(['admin', 'manager']);

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid CSRF token.');
    } else {
        $pdo->prepare("DELETE FROM departments WHERE dept_id = :id")->execute([':id' => (int)$_POST['delete_id']]);
        flash('success', 'Department deleted.');
    }
    header('Location: ' . BASE_URL . 'modules/departments/index.php');
    exit;
}

// Handle inline edit
$editDept = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $s = $pdo->prepare("SELECT * FROM departments WHERE dept_id = :id");
    $s->execute([':id' => (int)$_GET['id']]);
    $editDept = $s->fetch();
}

// Handle create / update
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_id'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $name = trim($_POST['dept_name']    ?? '');
        $desc = trim($_POST['description']  ?? '');
        $editId = (int)($_POST['edit_id'] ?? 0);

        if (!$name) {
            $errors[] = 'Department name is required.';
        } else {
            $chk = $pdo->prepare("SELECT dept_id FROM departments WHERE dept_name=:n AND dept_id != :id");
            $chk->execute([':n' => $name, ':id' => $editId]);
            if ($chk->fetch()) $errors[] = 'A department with that name already exists.';
        }

        if (empty($errors)) {
            if ($editId) {
                $pdo->prepare("UPDATE departments SET dept_name=:n, description=:d WHERE dept_id=:id")
                    ->execute([':n'=>$name,':d'=>$desc,':id'=>$editId]);
                flash('success', 'Department updated.');
            } else {
                $pdo->prepare("INSERT INTO departments (dept_name, description) VALUES (:n,:d)")
                    ->execute([':n'=>$name,':d'=>$desc]);
                flash('success', 'Department created.');
            }
            header('Location: ' . BASE_URL . 'modules/departments/index.php');
            exit;
        }
    }
}

$departments = $pdo->query(
    "SELECT d.*, COUNT(u.user_id) AS user_count
     FROM departments d
     LEFT JOIN users u ON u.dept_id = d.dept_id
     GROUP BY d.dept_id
     ORDER BY d.dept_name"
)->fetchAll();
?>

<div class="page-header">
  <div>
    <h1>Departments</h1>
    <p class="breadcrumb">Manage company departments</p>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 360px;gap:1.5rem;">

  <!-- List -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">All Departments (<?= count($departments) ?>)</span>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>#</th><th>Name</th><th>Description</th><th>Users</th><th>Created</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php if (empty($departments)): ?>
            <tr><td colspan="6" class="table-empty">No departments yet.</td></tr>
          <?php else: ?>
            <?php foreach ($departments as $i => $d): ?>
            <tr>
              <td><?= $i+1 ?></td>
              <td><?= sanitize($d['dept_name']) ?></td>
              <td><?= sanitize($d['description'] ?? '—') ?></td>
              <td><?= $d['user_count'] ?></td>
              <td><?= formatDate($d['created_at']) ?></td>
              <td>
                <div class="btn-group">
                  <a href="?action=edit&id=<?= $d['dept_id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                  <form method="POST" style="display:inline;">
                    <?= csrfField() ?>
                    <input type="hidden" name="delete_id" value="<?= $d['dept_id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm"
                            data-confirm="Delete department '<?= sanitize($d['dept_name']) ?>'?">Delete</button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Form -->
  <div class="card">
    <div class="card-header">
      <span class="card-title"><?= $editDept ? 'Edit Department' : 'Add Department' ?></span>
      <?php if ($editDept): ?>
        <a href="?" class="btn btn-secondary btn-sm">Cancel Edit</a>
      <?php endif; ?>
    </div>

    <?php if ($errors): ?>
      <div class="alert alert-danger mb-4">
        <?php foreach ($errors as $e): ?><div><?= sanitize($e) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="" novalidate>
      <?= csrfField() ?>
      <?php if ($editDept): ?>
        <input type="hidden" name="edit_id" value="<?= $editDept['dept_id'] ?>">
      <?php endif; ?>
      <div class="form-group mb-4">
        <label class="form-label" for="dept_name">Name <span class="required">*</span></label>
        <input type="text" id="dept_name" name="dept_name" class="form-control"
               value="<?= sanitize($editDept['dept_name'] ?? '') ?>" required>
      </div>
      <div class="form-group mb-4">
        <label class="form-label" for="description">Description</label>
        <textarea id="description" name="description" class="form-control"
                  rows="3"><?= sanitize($editDept['description'] ?? '') ?></textarea>
      </div>
      <button type="submit" class="btn btn-primary w-100">
        <?= $editDept ? 'Update Department' : 'Create Department' ?>
      </button>
    </form>
  </div>
</div>

<script>window.BASE_URL='<?= BASE_URL ?>';window.CSRF_TOKEN='<?= generateCsrfToken() ?>';</script>
<script src="<?= BASE_URL ?>assets/js/app.js"></script>
</main><footer class="footer">&copy; <?= date('Y') ?> AMIMS</footer>
</div></div></body></html>
