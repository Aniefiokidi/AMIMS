<?php
declare(strict_types=1);
$pageTitle = 'Inventory';
$rootDir   = dirname(dirname(dirname(__FILE__)));
require_once $rootDir . '/includes/header.php';

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    requireRole(['admin','manager']);
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid CSRF token.');
    } else {
        $pdo->prepare("DELETE FROM inventory_items WHERE item_id = :id")->execute([':id' => (int)$_POST['delete_id']]);
        flash('success', 'Inventory item deleted.');
    }
    header('Location: ' . BASE_URL . 'modules/inventory/index.php');
    exit;
}

$search  = trim($_GET['search']     ?? '');
$catId   = (int)($_GET['category_id'] ?? 0);
$deptId  = (int)($_GET['dept_id']    ?? 0);
$status  = trim($_GET['status']      ?? '');
$page    = getPageParam();
$perPage = 20;

$wheres = [];
$params = [];
if ($search) { $wheres[] = "i.item_name LIKE :s"; $params[':s'] = "%$search%"; }
if ($catId)  { $wheres[] = "i.category_id = :cat"; $params[':cat'] = $catId; }
if ($deptId) { $wheres[] = "i.dept_id = :dept";    $params[':dept'] = $deptId; }
$where = $wheres ? 'WHERE ' . implode(' AND ', $wheres) : '';

$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM inventory_items i $where");
$stmtCount->execute($params);
$total = (int)$stmtCount->fetchColumn();
$p     = paginate($total, $perPage, $page);

$params[':limit']  = $p['per_page'];
$params[':offset'] = $p['offset'];

$stmtItems = $pdo->prepare(
    "SELECT i.item_id, i.item_name, i.quantity, i.unit, i.reorder_level, i.last_updated,
            c.category_name, d.dept_name
     FROM inventory_items i
     LEFT JOIN categories c ON c.category_id = i.category_id
     LEFT JOIN departments d ON d.dept_id = i.dept_id
     $where
     ORDER BY i.item_name ASC
     LIMIT :limit OFFSET :offset"
);
foreach ($params as $k => $v) {
    $stmtItems->bindValue($k, $v, in_array($k,[':limit',':offset']) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmtItems->execute();
$items = $stmtItems->fetchAll();

// Apply status filter client-side (after fetch) for demo simplicity
if ($status) {
    $items = array_filter($items, fn($i) => inventoryStatus($i['quantity'], $i['reorder_level']) === $status);
    $items = array_values($items);
}

$categories  = $pdo->query("SELECT category_id, category_name FROM categories ORDER BY category_name")->fetchAll();
$departments = $pdo->query("SELECT dept_id, dept_name FROM departments ORDER BY dept_name")->fetchAll();
$baseUrl     = '?' . http_build_query(['search'=>$search,'category_id'=>$catId,'dept_id'=>$deptId,'status'=>$status]);
?>

<div class="page-header">
  <div>
    <h1>Inventory</h1>
    <p class="breadcrumb">Manage parts, consumables and supplies</p>
  </div>
  <div class="btn-group">
    <a href="<?= BASE_URL ?>modules/inventory/issue.php" class="btn btn-secondary">Issue Stock</a>
    <?php if (in_array(currentRole(), ['admin','manager'])): ?>
    <a href="<?= BASE_URL ?>modules/inventory/create.php" class="btn btn-primary">+ Add Item</a>
    <?php endif; ?>
  </div>
</div>

<div class="filter-bar">
  <form method="GET" action="" style="display:flex;gap:0.75rem;flex:1;flex-wrap:wrap;align-items:flex-end;">
    <div class="filter-group">
      <label class="filter-label">Search</label>
      <input type="text" name="search" class="form-control" placeholder="Item name…" value="<?= sanitize($search) ?>">
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
      <label class="filter-label">Status</label>
      <select name="status" class="form-control">
        <option value="">All Statuses</option>
        <option value="In Stock"     <?= $status==='In Stock'      ? 'selected' : '' ?>>In Stock</option>
        <option value="Low Stock"    <?= $status==='Low Stock'     ? 'selected' : '' ?>>Low Stock</option>
        <option value="Out of Stock" <?= $status==='Out of Stock'  ? 'selected' : '' ?>>Out of Stock</option>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">Filter</button>
    <?php if ($search || $catId || $deptId || $status): ?>
      <a href="?" class="btn btn-secondary">Clear</a>
    <?php endif; ?>
  </form>
</div>

<div class="card">
  <div class="card-header">
    <span class="card-title">Inventory Items (<?= number_format($total) ?>)</span>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th><th>Item Name</th><th>Category</th><th>Department</th>
          <th>Quantity</th><th>Unit</th><th>Reorder Level</th><th>Status</th>
          <th>Last Updated</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($items)): ?>
          <tr><td colspan="10" class="table-empty">No inventory items found.</td></tr>
        <?php else: ?>
          <?php foreach ($items as $i => $item): ?>
          <?php $invStatus = inventoryStatus($item['quantity'], $item['reorder_level']); ?>
          <tr>
            <td><?= $p['offset'] + $i + 1 ?></td>
            <td><strong><?= sanitize($item['item_name']) ?></strong></td>
            <td><?= sanitize($item['category_name'] ?? '—') ?></td>
            <td><?= sanitize($item['dept_name'] ?? '—') ?></td>
            <td>
              <strong><?= number_format($item['quantity']) ?></strong>
              <?php if ($item['quantity'] <= $item['reorder_level']): ?>
                <span style="color:var(--danger);font-size:0.75rem;">&#9660;</span>
              <?php endif; ?>
            </td>
            <td><?= sanitize($item['unit'] ?? '—') ?></td>
            <td><?= number_format($item['reorder_level']) ?></td>
            <td><?= statusBadge($invStatus) ?></td>
            <td><?= formatDateTime($item['last_updated']) ?></td>
            <td>
              <div class="btn-group">
                <a href="<?= BASE_URL ?>modules/inventory/create.php?edit_id=<?= $item['item_id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                <?php if (in_array(currentRole(), ['admin','manager'])): ?>
                <form method="POST" style="display:inline;">
                  <?= csrfField() ?>
                  <input type="hidden" name="delete_id" value="<?= $item['item_id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm" data-confirm="Delete '<?= sanitize($item['item_name']) ?>'?">Delete</button>
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
