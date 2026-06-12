<?php
declare(strict_types=1);
$pageTitle = 'Maintenance History';
$rootDir   = dirname(dirname(dirname(__FILE__)));
require_once $rootDir . '/includes/header.php';

$assetId   = (int)($_GET['asset_id'] ?? 0);
$search    = trim($_GET['search']    ?? '');
$page      = getPageParam();
$perPage   = 20;

$wheres = [];
$params = [];
if ($assetId) { $wheres[] = "mh.asset_id = :aid"; $params[':aid'] = $assetId; }
if ($search)  { $wheres[] = "(a.asset_name LIKE :s OR mh.description LIKE :s2)"; $params[':s'] = "%$search%"; $params[':s2'] = "%$search%"; }
$where = $wheres ? 'WHERE ' . implode(' AND ', $wheres) : '';

$stmtCount = $pdo->prepare(
    "SELECT COUNT(*) FROM maintenance_history mh
     JOIN assets a ON a.asset_id = mh.asset_id
     $where"
);
$stmtCount->execute($params);
$total = (int)$stmtCount->fetchColumn();
$p     = paginate($total, $perPage, $page);

$params[':limit']  = $p['per_page'];
$params[':offset'] = $p['offset'];

$stmtHist = $pdo->prepare(
    "SELECT mh.history_id, mh.performed_date, mh.description, mh.cost, mh.outcome,
            a.asset_id, a.asset_name, a.asset_tag,
            u.full_name AS officer_name,
            ms.schedule_type
     FROM maintenance_history mh
     JOIN assets a ON a.asset_id = mh.asset_id
     LEFT JOIN users u ON u.user_id = mh.performed_by
     LEFT JOIN maintenance_schedule ms ON ms.schedule_id = mh.schedule_id
     $where
     ORDER BY mh.performed_date DESC, mh.history_id DESC
     LIMIT :limit OFFSET :offset"
);
foreach ($params as $k => $v) {
    $stmtHist->bindValue($k, $v, in_array($k,[':limit',':offset']) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmtHist->execute();
$rows = $stmtHist->fetchAll();

$baseUrl = '?' . http_build_query(['asset_id'=>$assetId,'search'=>$search]);
?>

<div class="page-header">
  <div>
    <h1>Maintenance History</h1>
    <p class="breadcrumb">
      <a href="<?= BASE_URL ?>modules/maintenance/index.php">Maintenance</a> &rsaquo; History
      <?php if ($assetId): ?>
        &rsaquo; Asset #<?= $assetId ?>
      <?php endif; ?>
    </p>
  </div>
  <a href="<?= BASE_URL ?>modules/maintenance/record.php" class="btn btn-primary">+ Record Task</a>
</div>

<div class="filter-bar">
  <form method="GET" action="" style="display:flex;gap:0.75rem;flex:1;flex-wrap:wrap;align-items:flex-end;">
    <?php if ($assetId): ?>
      <input type="hidden" name="asset_id" value="<?= $assetId ?>">
    <?php endif; ?>
    <div class="filter-group">
      <label class="filter-label">Search</label>
      <input type="text" name="search" class="form-control" placeholder="Asset or description…" value="<?= sanitize($search) ?>">
    </div>
    <button type="submit" class="btn btn-primary">Search</button>
    <?php if ($search || $assetId): ?>
      <a href="?" class="btn btn-secondary">Clear</a>
    <?php endif; ?>
  </form>
</div>

<div class="card">
  <div class="card-header">
    <span class="card-title">History Records (<?= number_format($total) ?>)</span>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th><th>Date</th><th>Asset</th><th>Type</th>
          <th>Performed By</th><th>Description</th><th>Cost</th><th>Outcome</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="8" class="table-empty">No history records found.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $i => $r): ?>
          <tr>
            <td><?= $p['offset'] + $i + 1 ?></td>
            <td><?= formatDate($r['performed_date']) ?></td>
            <td>
              <a href="<?= BASE_URL ?>modules/assets/view.php?id=<?= $r['asset_id'] ?>" style="color:var(--navy);font-weight:600;">
                <?= sanitize($r['asset_name']) ?>
              </a><br>
              <small class="text-muted"><?= sanitize($r['asset_tag']) ?></small>
            </td>
            <td><?= sanitize($r['schedule_type'] ?? 'Ad-hoc') ?></td>
            <td><?= sanitize($r['officer_name'] ?? '—') ?></td>
            <td><?= sanitize(mb_strimwidth($r['description'], 0, 80, '…')) ?></td>
            <td><?= $r['cost'] ? formatCurrency($r['cost']) : '—' ?></td>
            <td><?= sanitize($r['outcome'] ?? '—') ?></td>
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
