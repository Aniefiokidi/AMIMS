<?php
declare(strict_types=1);
$pageTitle = 'New Maintenance Schedule';
$rootDir   = dirname(dirname(dirname(__FILE__)));
require_once $rootDir . '/includes/header.php';
requireRole(['admin','manager']);

$assets    = $pdo->query("SELECT asset_id, asset_name, asset_tag FROM assets ORDER BY asset_name")->fetchAll();
$officers  = $pdo->query("SELECT user_id, full_name, email FROM users WHERE is_active=1 ORDER BY full_name")->fetchAll();
$types     = ['Preventive','Corrective'];
$freqs     = ['Daily','Weekly','Monthly','Quarterly','Annually'];

$errors = [];
$vals   = [
    'asset_id'=>$_GET['asset_id'] ?? '','schedule_type'=>'Preventive',
    'frequency'=>'Monthly','next_due_date'=>'','description'=>'','assigned_to'=>'',
];
$selectedEmails = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $vals['asset_id']      = (int)($_POST['asset_id']      ?? 0);
        $vals['schedule_type'] = trim($_POST['schedule_type']  ?? '');
        $vals['frequency']     = trim($_POST['frequency']       ?? '');
        $vals['next_due_date'] = trim($_POST['next_due_date']   ?? '');
        $vals['description']   = trim($_POST['description']     ?? '');
        $vals['assigned_to']   = (int)($_POST['assigned_to']   ?? 0);
        $selectedEmails        = $_POST['notify_emails']        ?? [];

        if (!$vals['asset_id'])      $errors[] = 'Asset is required.';
        if (!$vals['schedule_type']) $errors[] = 'Schedule type is required.';
        if (!$vals['frequency'])     $errors[] = 'Frequency is required.';
        if (!$vals['next_due_date']) $errors[] = 'Next due date is required.';

        if (empty($errors)) {
            $validEmails = array_filter(array_map('trim', $selectedEmails), fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL));
            $notifyJson  = json_encode(array_values($validEmails));

            $pdo->prepare(
                "INSERT INTO maintenance_schedule
                 (asset_id, schedule_type, frequency, description, next_due_date, assigned_to, notify_emails, status)
                 VALUES (:ai,:st,:fr,:desc,:nd,:ao,:ne,'Scheduled')"
            )->execute([
                ':ai'   => $vals['asset_id'],
                ':st'   => $vals['schedule_type'],
                ':fr'   => $vals['frequency'],
                ':desc' => $vals['description'] ?: null,
                ':nd'   => $vals['next_due_date'],
                ':ao'   => $vals['assigned_to'] ?: null,
                ':ne'   => $notifyJson,
            ]);

            flash('success', 'Maintenance schedule created successfully.');
            header('Location: ' . BASE_URL . 'modules/maintenance/index.php');
            exit;
        }
    }
}
?>

<div class="page-header">
  <div>
    <h1>New Maintenance Schedule</h1>
    <p class="breadcrumb"><a href="<?= BASE_URL ?>modules/maintenance/index.php">Maintenance</a> &rsaquo; New Schedule</p>
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
        <label class="form-label" for="asset_search">Search Asset</label>
        <input type="text" id="asset_search" class="form-control" placeholder="Type to filter assets…">
      </div>
      <div class="form-group">
        <label class="form-label" for="asset_id">Asset <span class="required">*</span></label>
        <select id="asset_id" name="asset_id" class="form-control" required>
          <option value="">— Select asset —</option>
          <?php foreach ($assets as $a): ?>
            <option value="<?= $a['asset_id'] ?>"
              <?= (string)$vals['asset_id'] === (string)$a['asset_id'] ? 'selected' : '' ?>>
              <?= sanitize($a['asset_name']) ?> [<?= sanitize($a['asset_tag']) ?>]
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="schedule_type">Schedule Type <span class="required">*</span></label>
        <select id="schedule_type" name="schedule_type" class="form-control" required>
          <?php foreach ($types as $t): ?>
            <option value="<?= $t ?>" <?= $vals['schedule_type']===$t ? 'selected' : '' ?>><?= $t ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="frequency">Frequency <span class="required">*</span></label>
        <select id="frequency" name="frequency" class="form-control" required>
          <?php foreach ($freqs as $f): ?>
            <option value="<?= $f ?>" <?= $vals['frequency']===$f ? 'selected' : '' ?>><?= $f ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="next_due_date">Next Due Date <span class="required">*</span></label>
        <input type="date" id="next_due_date" name="next_due_date" class="form-control"
               value="<?= sanitize($vals['next_due_date']) ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label" for="assigned_to">Assign Officer</label>
        <select id="assigned_to" name="assigned_to" class="form-control">
          <option value="">— Unassigned —</option>
          <?php foreach ($officers as $o): ?>
            <option value="<?= $o['user_id'] ?>" <?= (string)$vals['assigned_to']===(string)$o['user_id'] ? 'selected' : '' ?>>
              <?= sanitize($o['full_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group full">
        <label class="form-label" for="description">Description / Instructions</label>
        <textarea id="description" name="description" class="form-control"><?= sanitize($vals['description']) ?></textarea>
      </div>
      <div class="form-group full">
        <label class="form-label">Email Notification Recipients</label>
        <p class="form-hint" style="margin-bottom:0.5rem;">Select users who will receive email alerts for this schedule.</p>
        <div class="checkbox-list">
          <?php foreach ($officers as $o): ?>
            <?php $checked = in_array($o['email'], $selectedEmails, true) ? 'checked' : '' ?>
            <label class="checkbox-item">
              <input type="checkbox" name="notify_emails[]" value="<?= sanitize($o['email']) ?>" <?= $checked ?>>
              <?= sanitize($o['full_name']) ?> <span class="text-muted">&lt;<?= sanitize($o['email']) ?>&gt;</span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Create Schedule</button>
      <a href="<?= BASE_URL ?>modules/maintenance/index.php" class="btn btn-secondary">Cancel</a>
    </div>
  </form>
</div>

<script>window.BASE_URL='<?= BASE_URL ?>';window.CSRF_TOKEN='<?= generateCsrfToken() ?>';</script>
<script src="<?= BASE_URL ?>assets/js/app.js"></script>
</main><footer class="footer">&copy; <?= date('Y') ?> AMIMS</footer>
</div></div></body></html>
