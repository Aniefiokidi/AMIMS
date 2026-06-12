<?php
declare(strict_types=1);
$pageTitle = 'Record Maintenance Task';
$rootDir   = dirname(dirname(dirname(__FILE__)));
require_once $rootDir . '/includes/header.php';

$preAssetId    = (int)($_GET['asset_id']    ?? 0);
$preScheduleId = (int)($_GET['schedule_id'] ?? 0);

$assets    = $pdo->query("SELECT asset_id, asset_name, asset_tag FROM assets ORDER BY asset_name")->fetchAll();
$officers  = $pdo->query("SELECT user_id, full_name FROM users WHERE is_active=1 ORDER BY full_name")->fetchAll();

$errors = [];
$vals   = [
    'asset_id'       => $preAssetId,
    'schedule_id'    => $preScheduleId,
    'performed_by'   => currentUserId(),
    'performed_date' => date('Y-m-d'),
    'description'    => '',
    'cost'           => '',
    'outcome'        => '',
];

// Schedules for selected asset (populated via POST or GET)
$openSchedules = [];
$assetIdForSchedule = $vals['asset_id'];
if ($assetIdForSchedule) {
    $sched = $pdo->prepare(
        "SELECT schedule_id, schedule_type, frequency, next_due_date
         FROM maintenance_schedule WHERE asset_id=:aid AND status IN ('Scheduled','Overdue')"
    );
    $sched->execute([':aid' => $assetIdForSchedule]);
    $openSchedules = $sched->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $vals['asset_id']      = (int)($_POST['asset_id']      ?? 0);
        $vals['schedule_id']   = (int)($_POST['schedule_id']   ?? 0);
        $vals['performed_by']  = (int)($_POST['performed_by']  ?? 0);
        $vals['performed_date']= trim($_POST['performed_date']  ?? '');
        $vals['description']   = trim($_POST['description']     ?? '');
        $vals['cost']          = trim($_POST['cost']            ?? '');
        $vals['outcome']       = trim($_POST['outcome']         ?? '');

        if (!$vals['asset_id'])       $errors[] = 'Asset is required.';
        if (!$vals['performed_by'])   $errors[] = 'Performed by is required.';
        if (!$vals['performed_date']) $errors[] = 'Performed date is required.';
        if (!$vals['description'])    $errors[] = 'Description is required.';

        if (empty($errors)) {
            $pdo->prepare(
                "INSERT INTO maintenance_history
                 (asset_id, schedule_id, performed_by, performed_date, description, cost, outcome)
                 VALUES (:ai,:si,:pb,:pd,:de,:co,:ou)"
            )->execute([
                ':ai' => $vals['asset_id'],
                ':si' => $vals['schedule_id'] ?: null,
                ':pb' => $vals['performed_by'],
                ':pd' => $vals['performed_date'],
                ':de' => $vals['description'],
                ':co' => $vals['cost'] !== '' ? $vals['cost'] : null,
                ':ou' => $vals['outcome'] ?: null,
            ]);

            // Mark schedule as completed
            if ($vals['schedule_id']) {
                $pdo->prepare("UPDATE maintenance_schedule SET status='Completed' WHERE schedule_id=:id")
                    ->execute([':id' => $vals['schedule_id']]);
            }

            flash('success', 'Maintenance task recorded successfully.');
            header('Location: ' . BASE_URL . 'modules/maintenance/history.php');
            exit;
        }

        // Re-load schedules for posted asset
        if ($vals['asset_id']) {
            $sched = $pdo->prepare(
                "SELECT schedule_id, schedule_type, frequency, next_due_date
                 FROM maintenance_schedule WHERE asset_id=:aid AND status IN ('Scheduled','Overdue')"
            );
            $sched->execute([':aid' => $vals['asset_id']]);
            $openSchedules = $sched->fetchAll();
        }
    }
}
?>

<div class="page-header">
  <div>
    <h1>Record Maintenance Task</h1>
    <p class="breadcrumb"><a href="<?= BASE_URL ?>modules/maintenance/index.php">Maintenance</a> &rsaquo; Record</p>
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
        <label class="form-label" for="asset_id">Asset <span class="required">*</span></label>
        <select id="asset_id" name="asset_id" class="form-control" required
                onchange="this.form.submit()">
          <option value="">— Select asset —</option>
          <?php foreach ($assets as $a): ?>
            <option value="<?= $a['asset_id'] ?>" <?= $vals['asset_id']===$a['asset_id'] ? 'selected' : '' ?>>
              <?= sanitize($a['asset_name']) ?> [<?= sanitize($a['asset_tag']) ?>]
            </option>
          <?php endforeach; ?>
        </select>
        <span class="form-hint">Selecting asset reloads open schedules.</span>
      </div>
      <div class="form-group">
        <label class="form-label" for="schedule_id">Linked Schedule (optional)</label>
        <select id="schedule_id" name="schedule_id" class="form-control">
          <option value="">— Ad-hoc (no schedule) —</option>
          <?php foreach ($openSchedules as $sc): ?>
            <option value="<?= $sc['schedule_id'] ?>" <?= $vals['schedule_id']===$sc['schedule_id'] ? 'selected' : '' ?>>
              <?= sanitize($sc['schedule_type']) ?> — <?= sanitize($sc['frequency']) ?> — Due: <?= formatDate($sc['next_due_date']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="performed_by">Performed By <span class="required">*</span></label>
        <select id="performed_by" name="performed_by" class="form-control" required>
          <option value="">— Select officer —</option>
          <?php foreach ($officers as $o): ?>
            <option value="<?= $o['user_id'] ?>" <?= $vals['performed_by']===$o['user_id'] ? 'selected' : '' ?>>
              <?= sanitize($o['full_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="performed_date">Date Performed <span class="required">*</span></label>
        <input type="date" id="performed_date" name="performed_date" class="form-control"
               value="<?= sanitize($vals['performed_date']) ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label" for="cost">Maintenance Cost (₦)</label>
        <input type="number" id="cost" name="cost" class="form-control"
               value="<?= sanitize($vals['cost']) ?>" step="0.01" min="0" placeholder="0.00">
      </div>
      <div class="form-group">
        <label class="form-label" for="outcome">Outcome / Result</label>
        <input type="text" id="outcome" name="outcome" class="form-control"
               value="<?= sanitize($vals['outcome']) ?>" placeholder="e.g. Resolved, Parts replaced…">
      </div>
      <div class="form-group full">
        <label class="form-label" for="description">Description of Work Done <span class="required">*</span></label>
        <textarea id="description" name="description" class="form-control" rows="4"
                  required><?= sanitize($vals['description']) ?></textarea>
      </div>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Save Record</button>
      <a href="<?= BASE_URL ?>modules/maintenance/history.php" class="btn btn-secondary">Cancel</a>
    </div>
  </form>
</div>

<script>window.BASE_URL='<?= BASE_URL ?>';window.CSRF_TOKEN='<?= generateCsrfToken() ?>';</script>
<script src="<?= BASE_URL ?>assets/js/app.js"></script>
</main><footer class="footer">&copy; <?= date('Y') ?> AMIMS</footer>
</div></div></body></html>
