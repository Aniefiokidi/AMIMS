<?php
declare(strict_types=1);
$pageTitle = 'Asset Detail';
$rootDir   = dirname(dirname(dirname(__FILE__)));
require_once $rootDir . '/includes/header.php';

$assetId = (int)($_GET['id'] ?? 0);
if (!$assetId) {
    flash('error', 'No asset specified.');
    header('Location: ' . BASE_URL . 'modules/assets/index.php');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT a.*, c.category_name, d.dept_name, u.full_name AS assigned_to_name
     FROM assets a
     LEFT JOIN categories c ON c.category_id = a.category_id
     LEFT JOIN departments d ON d.dept_id = a.dept_id
     LEFT JOIN users u ON u.user_id = a.assigned_to
     WHERE a.asset_id = :id"
);
$stmt->execute([':id' => $assetId]);
$asset = $stmt->fetch();

if (!$asset) {
    flash('error', 'Asset not found.');
    header('Location: ' . BASE_URL . 'modules/assets/index.php');
    exit;
}

$pageTitle = sanitize($asset['asset_name']);

// Maintenance history for this asset
$history = $pdo->prepare(
    "SELECT mh.*, u.full_name AS officer_name
     FROM maintenance_history mh
     LEFT JOIN users u ON u.user_id = mh.performed_by
     WHERE mh.asset_id = :id
     ORDER BY mh.performed_date DESC
     LIMIT 10"
);
$history->execute([':id' => $assetId]);
$historyRows = $history->fetchAll();

// Upcoming schedules
$schedules = $pdo->prepare(
    "SELECT ms.*, u.full_name AS officer_name
     FROM maintenance_schedule ms
     LEFT JOIN users u ON u.user_id = ms.assigned_to
     WHERE ms.asset_id = :id AND ms.status IN ('Scheduled','Overdue')
     ORDER BY ms.next_due_date ASC"
);
$schedules->execute([':id' => $assetId]);
$scheduleRows = $schedules->fetchAll();
?>

<div class="page-header">
  <div>
    <h1><?= sanitize($asset['asset_name']) ?></h1>
    <p class="breadcrumb">
      <a href="<?= BASE_URL ?>modules/assets/index.php">Assets</a> &rsaquo; Detail
    </p>
  </div>
  <?php if (in_array(currentRole(), ['admin','manager'])): ?>
  <div class="btn-group">
    <a href="<?= BASE_URL ?>modules/assets/edit.php?id=<?= $assetId ?>" class="btn btn-primary">Edit Asset</a>
    <a href="<?= BASE_URL ?>modules/maintenance/create.php?asset_id=<?= $assetId ?>" class="btn btn-secondary">+ Schedule Maintenance</a>
    <a href="<?= BASE_URL ?>modules/maintenance/record.php?asset_id=<?= $assetId ?>" class="btn btn-gold">Record Maintenance</a>
  </div>
  <?php endif; ?>
</div>

<!-- Asset Details Card -->
<div class="card" style="margin-bottom:1.5rem;">
  <div class="card-header">
    <span class="card-title">Asset Information</span>
    <span><?= conditionBadge($asset['condition']) ?></span>
  </div>
  <div class="detail-grid">
    <div class="detail-row">
      <span class="detail-key">Asset Tag</span>
      <span class="detail-val"><code><?= sanitize($asset['asset_tag']) ?></code></span>
    </div>
    <div class="detail-row">
      <span class="detail-key">Category</span>
      <span class="detail-val"><?= sanitize($asset['category_name'] ?? '—') ?></span>
    </div>
    <div class="detail-row">
      <span class="detail-key">Department</span>
      <span class="detail-val"><?= sanitize($asset['dept_name'] ?? '—') ?></span>
    </div>
    <div class="detail-row">
      <span class="detail-key">Assigned To</span>
      <span class="detail-val"><?= sanitize($asset['assigned_to_name'] ?? '—') ?></span>
    </div>
    <div class="detail-row">
      <span class="detail-key">Purchase Date</span>
      <span class="detail-val"><?= formatDate($asset['purchase_date'] ?? '') ?></span>
    </div>
    <div class="detail-row">
      <span class="detail-key">Purchase Cost</span>
      <span class="detail-val"><?= $asset['purchase_cost'] ? formatCurrency($asset['purchase_cost']) : '—' ?></span>
    </div>
    <div class="detail-row">
      <span class="detail-key">Registered On</span>
      <span class="detail-val"><?= formatDateTime($asset['created_at']) ?></span>
    </div>
    <div class="detail-row full">
      <span class="detail-key">Notes</span>
      <span class="detail-val"><?= sanitize($asset['notes'] ?? '—') ?></span>
    </div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">

  <!-- Upcoming Schedules -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">Active Maintenance Schedules</span>
      <a href="<?= BASE_URL ?>modules/maintenance/create.php?asset_id=<?= $assetId ?>" class="btn btn-secondary btn-sm">+ Add</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Type</th><th>Frequency</th><th>Due Date</th><th>Officer</th><th>Status</th></tr>
        </thead>
        <tbody>
          <?php if (empty($scheduleRows)): ?>
            <tr><td colspan="5" class="table-empty">No active schedules.</td></tr>
          <?php else: ?>
            <?php foreach ($scheduleRows as $s): ?>
            <tr>
              <td><?= sanitize($s['schedule_type']) ?></td>
              <td><?= sanitize($s['frequency']) ?></td>
              <td><?= formatDate($s['next_due_date']) ?></td>
              <td><?= sanitize($s['officer_name'] ?? '—') ?></td>
              <td><?= statusBadge($s['status']) ?></td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Maintenance History -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">Maintenance History</span>
      <a href="<?= BASE_URL ?>modules/maintenance/history.php?asset_id=<?= $assetId ?>" class="btn btn-secondary btn-sm">View All</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Date</th><th>Performed By</th><th>Cost</th><th>Outcome</th></tr>
        </thead>
        <tbody>
          <?php if (empty($historyRows)): ?>
            <tr><td colspan="4" class="table-empty">No history yet.</td></tr>
          <?php else: ?>
            <?php foreach ($historyRows as $h): ?>
            <tr>
              <td><?= formatDate($h['performed_date']) ?></td>
              <td><?= sanitize($h['officer_name'] ?? '—') ?></td>
              <td><?= $h['cost'] ? formatCurrency($h['cost']) : '—' ?></td>
              <td><?= sanitize($h['outcome'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<script>window.BASE_URL='<?= BASE_URL ?>';window.CSRF_TOKEN='<?= generateCsrfToken() ?>';</script>
<script src="<?= BASE_URL ?>assets/js/app.js"></script>
</main><footer class="footer">&copy; <?= date('Y') ?> AMIMS</footer>
</div></div></body></html>
