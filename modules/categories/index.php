<?php
declare(strict_types=1);
$pageTitle = 'Categories';
$rootDir   = dirname(dirname(dirname(__FILE__)));
require_once $rootDir . '/includes/header.php';
requireRole(['admin', 'manager']);

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid CSRF token.');
    } else {
        $pdo->prepare("DELETE FROM categories WHERE category_id = :id")->execute([':id' => (int)$_POST['delete_id']]);
        flash('success', 'Category deleted.');
    }
    header('Location: ' . BASE_URL . 'modules/categories/index.php');
    exit;
}

$editCat = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $s = $pdo->prepare("SELECT * FROM categories WHERE category_id = :id");
    $s->execute([':id' => (int)$_GET['id']]);
    $editCat = $s->fetch();
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_id'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $name   = trim($_POST['category_name'] ?? '');
        $desc   = trim($_POST['description']   ?? '');
        $editId = (int)($_POST['edit_id']      ?? 0);

        if (!$name) {
            $errors[] = 'Category name is required.';
        } else {
            $chk = $pdo->prepare("SELECT category_id FROM categories WHERE category_name=:n AND category_id != :id");
            $chk->execute([':n' => $name, ':id' => $editId]);
            if ($chk->fetch()) $errors[] = 'A category with that name already exists.';
        }

        if (empty($errors)) {
            if ($editId) {
                $pdo->prepare("UPDATE categories SET category_name=:n, description=:d WHERE category_id=:id")
                    ->execute([':n'=>$name,':d'=>$desc,':id'=>$editId]);
                flash('success', 'Category updated.');
            } else {
                $pdo->prepare("INSERT INTO categories (category_name, description) VALUES (:n,:d)")
                    ->execute([':n'=>$name,':d'=>$desc]);
                flash('success', 'Category created.');
            }
            header('Location: ' . BASE_URL . 'modules/categories/index.php');
            exit;
        }
    }
}

$categories = $pdo->query(
    "SELECT c.*, COUNT(a.asset_id) AS asset_count
     FROM categories c
     LEFT JOIN assets a ON a.category_id = c.category_id
     GROUP BY c.category_id
     ORDER BY c.category_name"
)->fetchAll();
?>

<div class="page-header">
  <div>
    <h1>Categories</h1>
    <p class="breadcrumb">Manage asset and inventory categories</p>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 360px;gap:1.5rem;">

  <div class="card">
    <div class="card-header">
      <span class="card-title">All Categories (<?= count($categories) ?>)</span>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>#</th><th>Name</th><th>Description</th><th>Assets</th><th>Created</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php if (empty($categories)): ?>
            <tr><td colspan="6" class="table-empty">No categories yet.</td></tr>
          <?php else: ?>
            <?php foreach ($categories as $i => $c): ?>
            <tr>
              <td><?= $i+1 ?></td>
              <td><?= sanitize($c['category_name']) ?></td>
              <td><?= sanitize($c['description'] ?? '—') ?></td>
              <td><?= $c['asset_count'] ?></td>
              <td><?= formatDate($c['created_at']) ?></td>
              <td>
                <div class="btn-group">
                  <a href="?action=edit&id=<?= $c['category_id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                  <form method="POST" style="display:inline;">
                    <?= csrfField() ?>
                    <input type="hidden" name="delete_id" value="<?= $c['category_id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm"
                            data-confirm="Delete category '<?= sanitize($c['category_name']) ?>'?">Delete</button>
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

  <div class="card">
    <div class="card-header">
      <span class="card-title"><?= $editCat ? 'Edit Category' : 'Add Category' ?></span>
      <?php if ($editCat): ?>
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
      <?php if ($editCat): ?>
        <input type="hidden" name="edit_id" value="<?= $editCat['category_id'] ?>">
      <?php endif; ?>
      <div class="form-group mb-4">
        <label class="form-label" for="category_name">Name <span class="required">*</span></label>
        <input type="text" id="category_name" name="category_name" class="form-control"
               value="<?= sanitize($editCat['category_name'] ?? '') ?>" required>
      </div>
      <div class="form-group mb-4">
        <label class="form-label" for="description">Description</label>
        <textarea id="description" name="description" class="form-control"
                  rows="3"><?= sanitize($editCat['description'] ?? '') ?></textarea>
      </div>
      <button type="submit" class="btn btn-primary w-100">
        <?= $editCat ? 'Update Category' : 'Create Category' ?>
      </button>
    </form>
  </div>
</div>

<script>window.BASE_URL='<?= BASE_URL ?>';window.CSRF_TOKEN='<?= generateCsrfToken() ?>';</script>
<script src="<?= BASE_URL ?>assets/js/app.js"></script>
</main><footer class="footer">&copy; <?= date('Y') ?> AMIMS</footer>
</div></div></body></html>
