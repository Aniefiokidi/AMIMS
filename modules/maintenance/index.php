<?php
declare(strict_types=1);
$pageTitle = 'Maintenance Schedules';
$rootDir   = dirname(dirname(dirname(__FILE__)));
require_once $rootDir . '/includes/header.php';

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    requireRole(['admin','manager']);
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid CSRF token.');
    } else {
        $pdo->prepare("DELETE FROM maintenance_schedule WHERE schedule_id = :id")->execute([':id' => (int)$_POST['delete_id']]);
        flash('success', 'Schedule deleted.');
    }
    header('Location: ' . BASE_URL . 'modules/maintenance/index.php');
    exit;
}

$statusFilter = trim($_GET['status']    ?? '');
$assetSearch  = trim($_GET['asset']     ?? '');
$page         = getPageParam();
$perPage      = 20;

$wheres = [];
$params = [];
if ($statusFilter) { $wheres[] = "ms.status = :st"; $params[':st'] = $statusFilter; }
if ($assetSearch)  { $wheres[] = "a.asset_name LIKE :as"; $params[':as'] = "%$assetSearch%"; }

if (currentRole() === 'maintenance_officer') {
    $wheres[] = "ms.assigned_to = :uid";
    $params[':uid'] = currentUserId();
}

$where = $wheres ? 'WHERE ' . implode(' AND ', $wheres) : '';

$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM maintenance_schedule ms JOIN assets a ON a.asset_id = ms.asset_id $where");
$stmtCount->execute($params);
$total = (int)$stmtCount->fetchColumn();
$p = paginate($total, $perPage, $page);

$params[':limit']  = $p['per_page'];
$params[':offset'] = $p['offset'];

$stmtList = $pdo->prepare(
    "SELECT ms.schedule_id, ms.schedule_type, ms.frequency, ms.next_due_date, ms.status,
            ms.description, a.asset_id, a.asset_name, a.asset_tag,
            u.full_name AS officer_name
     FROM maintenance_schedule ms
     JOIN assets a ON a.asset_id = ms.asset_id
     LEFT JOIN users u ON u.user_id = ms.assigned_to
     $where
     ORDER BY FIELD(ms.status,'Overdue','Scheduled','Completed','Cancelled'), ms.next_due_date ASC
     LIMIT :limit OFFSET :offset"
);
foreach ($params as $k => $v) {
    $stmtList->bindValue($k, $v, in_array($k,[':limit',':offset']) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmtList->execute();
$schedules = $stmtList->fetchAll();

$statuses  = ['Scheduled','Overdue','Completed','Cancelled'];
$baseUrl   = '?' . http_build_query(['status'=>$statusFilter,'asset'=>$assetSearch]);
?>

<div class="page-header">
  <div>
    <h1>Maintenance Schedules</h1>
    <p class="breadcrumb">Manage preventive and corrective maintenance schedules</p>
  </div>
  <div class="btn-group">
    <a href="<?= BASE_URL ?>modules/maintenance/history.php" class="btn btn-secondary">History</a>
    <?php if (in_array(currentRole(), ['admin','manager'])): ?>
    <a href="<?= BASE_URL ?>modules/maintenance/create.php" class="btn btn-primary">+ New Schedule</a>
    <?php endif; ?>
  </div>
</div>

<div class="filter-bar">
  <form method="GET" action="" style="display:flex;gap:0.75rem;flex:1;flex-wrap:wrap;align-items:flex-end;">
    <div class="filter-group">
      <label class="filter-label">Asset</label>
      <input type="text" name="asset" class="form-control" placeholder="Asset name…" value="<?= sanitize($assetSearch) ?>">
    </div>
    <div class="filter-group">
      <label class="filter-label">Status</label>
      <select name="status" class="form-control">
        <option value="">All Statuses</option>
        <?php foreach ($statuses as $s): ?>
          <option value="<?= $s ?>" <?= $statusFilter===$s ? 'selected' : '' ?>><?= $s ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">Filter</button>
    <?php if ($statusFilter || $assetSearch): ?>
      <a href="?" class="btn btn-secondary">Clear</a>
    <?php endif; ?>
  </form>
</div>

<div class="card">
  <div class="card-header">
    <span class="card-title">Schedules (<?= number_format($total) ?>)</span>
    <a href="<?= BASE_URL ?>modules/maintenance/record.php" class="btn btn-gold btn-sm">Record Completed Task</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th><th>Asset</th><th>Type</th><th>Frequency</th>
          <th>Next Due</th><th>Officer</th><th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($schedules)): ?>
          <tr><td colspan="8" class="table-empty">No schedules found.</td></tr>
        <?php else: ?>
          <?php foreach ($schedules as $i => $s): ?>
          <tr>
            <td><?= $p['offset'] + $i + 1 ?></td>
            <td>
              <a href="<?= BASE_URL ?>modules/assets/view.php?id=<?= $s['asset_id'] ?>" style="color:var(--navy);font-weight:600;">
                <?= sanitize($s['asset_name']) ?>
              </a><br>
              <small class="text-muted"><?= sanitize($s['asset_tag']) ?></small>
            </td>
            <td><?= sanitize($s['schedule_type']) ?></td>
            <td><?= sanitize($s['frequency']) ?></td>
            <td><?= formatDate($s['next_due_date']) ?></td>
            <td><?= sanitize($s['officer_name'] ?? '—') ?></td>
            <td><?= statusBadge($s['status']) ?></td>
            <td>
              <div class="btn-group">
                <a href="<?= BASE_URL ?>modules/maintenance/record.php?schedule_id=<?= $s['schedule_id'] ?>&asset_id=<?= $s['asset_id'] ?>"
                   class="btn btn-success btn-sm">Record</a>
                <?php if (in_array(currentRole(), ['admin','manager'])): ?>
                <form method="POST" style="display:inline;">
                  <?= csrfField() ?>
                  <input type="hidden" name="delete_id" value="<?= $s['schedule_id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm"
                          data-confirm="Delete this schedule?">Delete</button>
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
