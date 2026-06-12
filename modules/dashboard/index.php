<?php
declare(strict_types=1);
$pageTitle = 'Dashboard';
$rootDir   = dirname(dirname(dirname(__FILE__)));
require_once $rootDir . '/includes/header.php';

$userId = currentUserId();
$role   = currentRole();

// ── Stat cards ──────────────────────────────────────────────────────────────
$totalAssets = (int)$pdo->query("SELECT COUNT(*) FROM assets")->fetchColumn();

$stmtMon = $pdo->prepare(
    "SELECT COUNT(*) FROM maintenance_schedule
     WHERE strftime('%Y-%m', next_due_date) = strftime('%Y-%m', 'now')"
);
$stmtMon->execute();
$schedThisMonth = (int)$stmtMon->fetchColumn();

$overdueCount = (int)$pdo->query(
    "SELECT COUNT(*) FROM maintenance_schedule WHERE status='Overdue'"
)->fetchColumn();

$lowStockCount = (int)$pdo->query(
    "SELECT COUNT(*) FROM inventory_items WHERE quantity <= reorder_level"
)->fetchColumn();

// ── Recent maintenance activities ───────────────────────────────────────────
if ($role === 'maintenance_officer') {
    $stmtRecent = $pdo->prepare(
        "SELECT ms.schedule_id, ms.schedule_type, ms.frequency, ms.next_due_date,
                ms.status, a.asset_name, a.asset_tag,
                u.full_name AS officer_name
         FROM maintenance_schedule ms
         JOIN assets a ON a.asset_id = ms.asset_id
         LEFT JOIN users u ON u.user_id = ms.assigned_to
         WHERE ms.assigned_to = :uid
         ORDER BY ms.next_due_date ASC
         LIMIT 10"
    );
    $stmtRecent->execute([':uid' => $userId]);
} else {
    $stmtRecent = $pdo->prepare(
        "SELECT ms.schedule_id, ms.schedule_type, ms.frequency, ms.next_due_date,
                ms.status, a.asset_name, a.asset_tag,
                u.full_name AS officer_name
         FROM maintenance_schedule ms
         JOIN assets a ON a.asset_id = ms.asset_id
         LEFT JOIN users u ON u.user_id = ms.assigned_to
         ORDER BY ms.next_due_date ASC
         LIMIT 10"
    );
    $stmtRecent->execute();
}
$recentSchedules = $stmtRecent->fetchAll();

// ── Condition breakdown ──────────────────────────────────────────────────────
$condRows = $pdo->query(
    "SELECT `condition`, COUNT(*) AS cnt FROM assets GROUP BY `condition`"
)->fetchAll();

$condMap = [];
foreach ($condRows as $r) {
    $condMap[$r['condition']] = (int)$r['cnt'];
}
$allConditions = ['Good','Fair','Bad','Needs Replacement','In Use','Inactive'];
$maxCond       = max(array_values($condMap) ?: [1]);

// ── Recent history ───────────────────────────────────────────────────────────
$recentHistory = $pdo->query(
    "SELECT mh.performed_date, mh.description, mh.outcome,
            a.asset_name, u.full_name AS officer_name
     FROM maintenance_history mh
     JOIN assets a ON a.asset_id = mh.asset_id
     LEFT JOIN users u ON u.user_id = mh.performed_by
     ORDER BY mh.performed_date DESC, mh.history_id DESC
     LIMIT 5"
)->fetchAll();
?>

<div class="page-header">
  <div>
    <h1>Dashboard</h1>
    <p class="breadcrumb">Welcome back, <?= currentUserName() ?></p>
  </div>
  <div class="btn-group">
    <a href="<?= BASE_URL ?>cron/check_schedules.php" class="btn btn-secondary btn-sm">&#9654; Run Schedule Check</a>
    <?php if (in_array($role, ['admin','manager'])): ?>
    <a href="<?= BASE_URL ?>modules/reports/index.php" class="btn btn-primary btn-sm">&#9878; Generate Report</a>
    <?php endif; ?>
  </div>
</div>

<!-- Stat cards -->
<div class="stat-grid">
  <div class="stat-card navy">
    <span class="stat-label">Total Assets</span>
    <span class="stat-value" data-target="<?= $totalAssets ?>"><?= number_format($totalAssets) ?></span>
    <span class="stat-sub">Registered in system</span>
  </div>
  <div class="stat-card gold">
    <span class="stat-label">Schedules This Month</span>
    <span class="stat-value" data-target="<?= $schedThisMonth ?>"><?= number_format($schedThisMonth) ?></span>
    <span class="stat-sub"><?= date('F Y') ?></span>
  </div>
  <div class="stat-card red">
    <span class="stat-label">Overdue</span>
    <span class="stat-value" data-target="<?= $overdueCount ?>"><?= number_format($overdueCount) ?></span>
    <span class="stat-sub">Maintenance overdue</span>
  </div>
  <div class="stat-card orange">
    <span class="stat-label">Low Stock Items</span>
    <span class="stat-value" data-target="<?= $lowStockCount ?>"><?= number_format($lowStockCount) ?></span>
    <span class="stat-sub">Below reorder level</span>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 340px;gap:1.5rem;margin-bottom:1.5rem;" class="dash-grid">

  <!-- Upcoming schedules table -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">Upcoming Maintenance Schedules</span>
      <a href="<?= BASE_URL ?>modules/maintenance/index.php" class="btn btn-secondary btn-sm">View All</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Asset</th>
            <th>Type</th>
            <th>Frequency</th>
            <th>Due Date</th>
            <th>Officer</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recentSchedules)): ?>
            <tr><td colspan="6" class="table-empty">No maintenance schedules found.</td></tr>
          <?php else: ?>
            <?php foreach ($recentSchedules as $s): ?>
            <tr>
              <td>
                <strong><?= sanitize($s['asset_name']) ?></strong><br>
                <small class="text-muted"><?= sanitize($s['asset_tag']) ?></small>
              </td>
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

  <!-- Asset condition chart -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">Asset Conditions</span>
    </div>
    <div class="condition-chart">
      <?php foreach ($allConditions as $cond):
        $count = $condMap[$cond] ?? 0;
        $pct   = $maxCond > 0 ? round($count / $maxCond * 100) : 0;
        $barClass = strtolower(str_replace(' ', '-', $cond));
        $barClass = str_replace(['needs-replacement'], ['needs-replacement'], $barClass);
      ?>
      <div class="chart-row">
        <span class="chart-label"><?= sanitize($cond) ?></span>
        <div class="chart-bar-wrap">
          <div class="chart-bar <?= $barClass ?>" style="width:<?= $pct ?>%"></div>
        </div>
        <span class="chart-count"><?= $count ?></span>
      </div>
      <?php endforeach; ?>
    </div>

    <div style="margin-top:1.5rem;border-top:1px solid var(--border);padding-top:1rem;">
      <p class="section-title" style="margin-bottom:0.75rem;">Recent Activity</p>
      <?php if (empty($recentHistory)): ?>
        <p class="text-muted" style="font-size:0.82rem;">No maintenance history yet.</p>
      <?php else: ?>
        <?php foreach ($recentHistory as $h): ?>
        <div style="margin-bottom:0.75rem;font-size:0.82rem;border-bottom:1px solid var(--border);padding-bottom:0.6rem;">
          <strong><?= sanitize($h['asset_name']) ?></strong><br>
          <span class="text-muted"><?= formatDate($h['performed_date']) ?></span>
          &mdash; <?= sanitize($h['officer_name'] ?? 'Unknown') ?><br>
          <span><?= sanitize(mb_strimwidth($h['description'], 0, 80, '…')) ?></span>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

</div>

<script>
window.BASE_URL   = '<?= BASE_URL ?>';
window.CSRF_TOKEN = '<?= generateCsrfToken() ?>';
</script>
<script src="<?= BASE_URL ?>assets/js/app.js"></script>
</main>
<footer class="footer">&copy; <?= date('Y') ?> AMIMS &mdash; Oil &amp; Gas Asset Management System</footer>
</div><!-- .main-wrapper -->
</div><!-- .layout -->
</body>
</html>
