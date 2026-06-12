<?php
declare(strict_types=1);
$pageTitle = 'Users';
$rootDir   = dirname(dirname(dirname(__FILE__)));
require_once $rootDir . '/includes/header.php';
requireRole(['admin', 'manager']);

$search = trim($_GET['search'] ?? '');
$page   = getPageParam();
$perPage = 20;

$where  = '';
$params = [];
if ($search !== '') {
    $where  = "WHERE full_name LIKE :s OR email LIKE :s2 OR role LIKE :s3";
    $params = [':s' => "%$search%", ':s2' => "%$search%", ':s3' => "%$search%"];
}

$total = (int)$pdo->prepare("SELECT COUNT(*) FROM users $where")->execute($params) && 0 ?: 0;
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM users $where");
$stmtCount->execute($params);
$total = (int)$stmtCount->fetchColumn();

$p      = paginate($total, $perPage, $page);
$params[':limit']  = $p['per_page'];
$params[':offset'] = $p['offset'];

$stmtUsers = $pdo->prepare(
    "SELECT u.user_id, u.full_name, u.email, u.role, u.is_active, u.created_at,
            d.dept_name
     FROM users u
     LEFT JOIN departments d ON d.dept_id = u.dept_id
     $where
     ORDER BY u.created_at DESC
     LIMIT :limit OFFSET :offset"
);
foreach ($params as $k => $v) {
    if (in_array($k, [':limit', ':offset'])) {
        $stmtUsers->bindValue($k, $v, PDO::PARAM_INT);
    } else {
        $stmtUsers->bindValue($k, $v, PDO::PARAM_STR);
    }
}
$stmtUsers->execute();
$users = $stmtUsers->fetchAll();

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid CSRF token.');
    } else {
        $delId = (int)$_POST['delete_id'];
        if ($delId === currentUserId()) {
            flash('error', 'You cannot delete your own account.');
        } else {
            $pdo->prepare("DELETE FROM users WHERE user_id = :id")->execute([':id' => $delId]);
            flash('success', 'User deleted successfully.');
        }
    }
    header('Location: ' . BASE_URL . 'modules/users/index.php');
    exit;
}

$baseUrl = '?' . http_build_query(['search' => $search]);
?>

<div class="page-header">
  <div>
    <h1>Users</h1>
    <p class="breadcrumb">Manage system users and their roles</p>
  </div>
  <a href="<?= BASE_URL ?>modules/users/create.php" class="btn btn-primary">+ Add User</a>
</div>

<div class="filter-bar">
  <form method="GET" action="" style="display:flex;gap:0.75rem;flex:1;flex-wrap:wrap;align-items:flex-end;">
    <div class="filter-group">
      <label class="filter-label">Search</label>
      <input type="text" name="search" class="form-control" placeholder="Name, email or role…"
             value="<?= sanitize($search) ?>">
    </div>
    <button type="submit" class="btn btn-primary">Search</button>
    <?php if ($search): ?>
      <a href="?" class="btn btn-secondary">Clear</a>
    <?php endif; ?>
  </form>
</div>

<div class="card">
  <div class="card-header">
    <span class="card-title">All Users (<?= number_format($total) ?>)</span>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Full Name</th>
          <th>Email</th>
          <th>Role</th>
          <th>Department</th>
          <th>Status</th>
          <th>Created</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($users)): ?>
          <tr><td colspan="8" class="table-empty">No users found.</td></tr>
        <?php else: ?>
          <?php foreach ($users as $i => $u): ?>
          <tr>
            <td><?= $p['offset'] + $i + 1 ?></td>
            <td><?= sanitize($u['full_name']) ?></td>
            <td><?= sanitize($u['email']) ?></td>
            <td><?= sanitize(str_replace('_', ' ', ucwords($u['role']))) ?></td>
            <td><?= sanitize($u['dept_name'] ?? '—') ?></td>
            <td>
              <?php if ($u['is_active']): ?>
                <span class="badge badge-good">Active</span>
              <?php else: ?>
                <span class="badge badge-inactive">Inactive</span>
              <?php endif; ?>
            </td>
            <td><?= formatDate($u['created_at']) ?></td>
            <td>
              <div class="btn-group">
                <a href="<?= BASE_URL ?>modules/users/edit.php?id=<?= $u['user_id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                <?php if ($u['user_id'] !== currentUserId()): ?>
                <form method="POST" action="" style="display:inline;">
                  <?= csrfField() ?>
                  <input type="hidden" name="delete_id" value="<?= $u['user_id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm"
                          data-confirm="Delete user '<?= sanitize($u['full_name']) ?>'? This cannot be undone.">Delete</button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?= paginationLinks($p, $baseUrl) ?>
</div>

<script>
window.BASE_URL   = '<?= BASE_URL ?>';
window.CSRF_TOKEN = '<?= generateCsrfToken() ?>';
</script>
<script src="<?= BASE_URL ?>assets/js/app.js"></script>
</main>
<footer class="footer">&copy; <?= date('Y') ?> AMIMS</footer>
</div></div></body></html>
