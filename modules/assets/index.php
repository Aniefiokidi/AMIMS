<?php
declare(strict_types=1);
$pageTitle = 'Assets';
$rootDir   = dirname(dirname(dirname(__FILE__)));
require_once $rootDir . '/includes/header.php';

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    requireRole(['admin', 'manager']);
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid CSRF token.');
    } else {
        $pdo->prepare("DELETE FROM assets WHERE asset_id = :id")->execute([':id' => (int)$_POST['delete_id']]);
        flash('success', 'Asset deleted.');
    }
    header('Location: ' . BASE_URL . 'modules/assets/index.php');
    exit;
}

$search   = trim($_GET['search']      ?? '');
$catId    = (int)($_GET['category_id'] ?? 0);
$deptId   = (int)($_GET['dept_id']    ?? 0);
$cond     = trim($_GET['condition']   ?? '');
$page     = getPageParam();
$perPage  = 20;

$wheres = [];
$params = [];
if ($search) { $wheres[] = "(a.asset_name LIKE :s OR a.asset_tag LIKE :s2)"; $params[':s'] = "%$search%"; $params[':s2'] = "%$search%"; }
if ($catId)  { $wheres[] = "a.category_id = :cat";  $params[':cat']  = $catId; }
if ($deptId) { $wheres[] = "a.dept_id = :dept";     $params[':dept'] = $deptId; }
if ($cond)   { $wheres[] = "a.`condition` = :cond"; $params[':cond'] = $cond; }
$where = $wheres ? 'WHERE ' . implode(' AND ', $wheres) : '';

$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM assets a $where");
$stmtCount->execute($params);
$total = (int)$stmtCount->fetchColumn();
$p     = paginate($total, $perPage, $page);

$params[':limit']  = $p['per_page'];
$params[':offset'] = $p['offset'];

$stmtAssets = $pdo->prepare(
    "SELECT a.asset_id, a.asset_name, a.asset_tag, a.`condition`, a.purchase_date, a.purchase_cost,
            c.category_name, d.dept_name, u.full_name AS assigned_to_name
     FROM assets a
     LEFT JOIN categories c ON c.category_id = a.category_id
     LEFT JOIN departments d ON d.dept_id = a.dept_id
     LEFT JOIN users u ON u.user_id = a.assigned_to
     $where
     ORDER BY a.created_at DESC
     LIMIT :limit OFFSET :offset"
);
foreach ($params as $k => $v) {
    $stmtAssets->bindValue($k, $v, in_array($k, [':limit',':offset']) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmtAssets->execute();
$assets = $stmtAssets->fetchAll();

$categories  = $pdo->query("SELECT category_id, category_name FROM categories ORDER BY category_name")->fetchAll();
$departments = $pdo->query("SELECT dept_id, dept_name FROM departments ORDER BY dept_name")->fetchAll();
$conditions  = ['Good','Fair','Bad','Needs Replacement','In Use','Inactive'];

$baseUrl = '?' . http_build_query(['search'=>$search,'category_id'=>$catId,'dept_id'=>$deptId,'condition'=>$cond]);
?>

<div class="page-header">
  <div>
    <h1>Assets</h1>
    <p class="breadcrumb">All registered company assets</p>
  </div>
  <?php if (in_array(currentRole(), ['admin','manager'])): ?>
  <a href="<?= BASE_URL ?>modules/assets/create.php" class="btn btn-primary">+ Register Asset</a>
  <?php endif; ?>
</div>

<div class="filter-bar">
  <form method="GET" action="" style="display:flex;gap:0.75rem;flex:1;flex-wrap:wrap;align-items:flex-end;">
    <div class="filter-group">
      <label class="filter-label">Search</label>
      <input type="text" name="search" class="form-control" placeholder="Name or tag…" value="<?= sanitize($search) ?>">
    </div>
    <div class="filter-group">
      <label class="filter-label">Category</label>
      <select name="category_id" class="form-control">
        <option value="0">All Categories</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= $c['category_id'] ?>" <?= $catId===$c['category_id'] ? 'selected' : '' ?>><?= sanitize($c['category_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="filter-group">
      <label class="filter-label">Department</label>
      <select name="dept_id" class="form-control">
        <option value="0">All Depts</option>
        <?php foreach ($departments as $d): ?>
          <option value="<?= $d['dept_id'] ?>" <?= $deptId===$d['dept_id'] ? 'selected' : '' ?>><?= sanitize($d['dept_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="filter-group">
      <label class="filter-label">Condition</label>
      <select name="condition" class="form-control">
        <option value="">All Conditions</option>
        <?php foreach ($conditions as $cn): ?>
          <option value="<?= $cn ?>" <?= $cond===$cn ? 'selected' : '' ?>><?= $cn ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">Filter</button>
    <?php if ($search || $catId || $deptId || $cond): ?>
      <a href="?" class="btn btn-secondary">Clear</a>
    <?php endif; ?>
  </form>
</div>

<div class="card">
  <div class="card-header">
    <span class="card-title">Assets (<?= number_format($total) ?>)</span>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Asset Name</th>
          <th>Tag</th>
          <th>Category</th>
          <th>Department</th>
          <th>Condition</th>
          <th>Cost</th>
          <th>Assigned To</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($assets)): ?>
          <tr><td colspan="9" class="table-empty">No assets found.</td></tr>
        <?php else: ?>
          <?php foreach ($assets as $i => $a): ?>
          <tr>
            <td><?= $p['offset'] + $i + 1 ?></td>
            <td><strong><?= sanitize($a['asset_name']) ?></strong></td>
            <td><code><?= sanitize($a['asset_tag']) ?></code></td>
            <td><?= sanitize($a['category_name'] ?? '—') ?></td>
            <td><?= sanitize($a['dept_name'] ?? '—') ?></td>
            <td><?= conditionBadge($a['condition']) ?></td>
            <td><?= $a['purchase_cost'] ? formatCurrency($a['purchase_cost']) : '—' ?></td>
            <td><?= sanitize($a['assigned_to_name'] ?? '—') ?></td>
            <td>
              <div class="btn-group">
                <a href="<?= BASE_URL ?>modules/assets/view.php?id=<?= $a['asset_id'] ?>" class="btn btn-secondary btn-sm">View</a>
                <?php if (in_array(currentRole(), ['admin','manager'])): ?>
                <a href="<?= BASE_URL ?>modules/assets/edit.php?id=<?= $a['asset_id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                <form method="POST" style="display:inline;">
                  <?= csrfField() ?>
                  <input type="hidden" name="delete_id" value="<?= $a['asset_id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm"
                          data-confirm="Delete asset '<?= sanitize($a['asset_name']) ?>'? All related maintenance records will also be deleted.">Delete</button>
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

<script>window.BASE_URL='<?= BASE_URL ?>';window.CSRF_TOKEN='<?= generateCsrfToken() ?>';</script>
<script src="<?= BASE_URL ?>assets/js/app.js"></script>
</main><footer class="footer">&copy; <?= date('Y') ?> AMIMS</footer>
</div></div></body></html>
