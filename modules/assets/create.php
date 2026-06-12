<?php
declare(strict_types=1);
$pageTitle = 'Register Asset';
$rootDir   = dirname(dirname(dirname(__FILE__)));
require_once $rootDir . '/includes/header.php';
requireRole(['admin', 'manager']);

$categories  = $pdo->query("SELECT category_id, category_name FROM categories ORDER BY category_name")->fetchAll();
$departments = $pdo->query("SELECT dept_id, dept_name FROM departments ORDER BY dept_name")->fetchAll();
$users       = $pdo->query("SELECT user_id, full_name FROM users WHERE is_active=1 ORDER BY full_name")->fetchAll();
$conditions  = ['Good','Fair','Bad','Needs Replacement','In Use','Inactive'];

$errors = [];
$vals   = [
    'asset_name'=>'','asset_tag'=>'','category_id'=>'','dept_id'=>'',
    'condition'=>'Good','purchase_date'=>'','purchase_cost'=>'','assigned_to'=>'','notes'=>''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        foreach ($vals as $k => $_) {
            $vals[$k] = trim($_POST[$k] ?? '');
        }
        if (!$vals['asset_name']) $errors[] = 'Asset name is required.';
        if (!$vals['asset_tag'])  $errors[] = 'Asset tag is required.';
        if (!$vals['condition'])  $errors[] = 'Condition is required.';

        if ($vals['asset_tag']) {
            $chk = $pdo->prepare("SELECT asset_id FROM assets WHERE asset_tag = :tag");
            $chk->execute([':tag' => $vals['asset_tag']]);
            if ($chk->fetch()) $errors[] = 'An asset with tag "' . sanitize($vals['asset_tag']) . '" already exists.';
        }

        if (empty($errors)) {
            $pdo->prepare(
                "INSERT INTO assets (asset_name, asset_tag, category_id, dept_id, `condition`,
                 purchase_date, purchase_cost, assigned_to, notes)
                 VALUES (:an,:at,:ci,:di,:co,:pd,:pc,:asgn,:no)"
            )->execute([
                ':an'   => $vals['asset_name'],
                ':at'   => $vals['asset_tag'],
                ':ci'   => $vals['category_id'] ?: null,
                ':di'   => $vals['dept_id']      ?: null,
                ':co'   => $vals['condition'],
                ':pd'   => $vals['purchase_date'] ?: null,
                ':pc'   => $vals['purchase_cost'] !== '' ? $vals['purchase_cost'] : null,
                ':asgn' => $vals['assigned_to']  ?: null,
                ':no'   => $vals['notes']         ?: null,
            ]);
            flash('success', 'Asset "' . $vals['asset_name'] . '" registered successfully.');
            header('Location: ' . BASE_URL . 'modules/assets/index.php');
            exit;
        }
    }
}
?>

<div class="page-header">
  <div>
    <h1>Register Asset</h1>
    <p class="breadcrumb"><a href="<?= BASE_URL ?>modules/assets/index.php">Assets</a> &rsaquo; Register</p>
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
        <label class="form-label" for="asset_name">Asset Name <span class="required">*</span></label>
        <input type="text" id="asset_name" name="asset_name" class="form-control"
               value="<?= sanitize($vals['asset_name']) ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label" for="asset_tag">Asset Tag / ID <span class="required">*</span></label>
        <input type="text" id="asset_tag" name="asset_tag" class="form-control"
               value="<?= sanitize($vals['asset_tag']) ?>" placeholder="e.g. ASSET-001" required>
      </div>
      <div class="form-group">
        <label class="form-label" for="category_id">Category</label>
        <select id="category_id" name="category_id" class="form-control">
          <option value="">— Select category —</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= $c['category_id'] ?>" <?= $vals['category_id']==(string)$c['category_id'] ? 'selected' : '' ?>>
              <?= sanitize($c['category_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="dept_id">Department</label>
        <select id="dept_id" name="dept_id" class="form-control">
          <option value="">— Select department —</option>
          <?php foreach ($departments as $d): ?>
            <option value="<?= $d['dept_id'] ?>" <?= $vals['dept_id']==(string)$d['dept_id'] ? 'selected' : '' ?>>
              <?= sanitize($d['dept_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="condition">Condition <span class="required">*</span></label>
        <select id="condition" name="condition" class="form-control" required>
          <?php foreach ($conditions as $cn): ?>
            <option value="<?= $cn ?>" <?= $vals['condition']===$cn ? 'selected' : '' ?>><?= $cn ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="assigned_to">Assigned To</label>
        <select id="assigned_to" name="assigned_to" class="form-control">
          <option value="">— Unassigned —</option>
          <?php foreach ($users as $u): ?>
            <option value="<?= $u['user_id'] ?>" <?= $vals['assigned_to']==(string)$u['user_id'] ? 'selected' : '' ?>>
              <?= sanitize($u['full_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="purchase_date">Purchase Date</label>
        <input type="date" id="purchase_date" name="purchase_date" class="form-control"
               value="<?= sanitize($vals['purchase_date']) ?>">
      </div>
      <div class="form-group">
        <label class="form-label" for="purchase_cost">Purchase Cost (₦)</label>
        <input type="number" id="purchase_cost" name="purchase_cost" class="form-control"
               value="<?= sanitize($vals['purchase_cost']) ?>" step="0.01" min="0" placeholder="0.00">
      </div>
      <div class="form-group full">
        <label class="form-label" for="notes">Notes</label>
        <textarea id="notes" name="notes" class="form-control"><?= sanitize($vals['notes']) ?></textarea>
      </div>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Register Asset</button>
      <a href="<?= BASE_URL ?>modules/assets/index.php" class="btn btn-secondary">Cancel</a>
    </div>
  </form>
</div>

<script>window.BASE_URL='<?= BASE_URL ?>';window.CSRF_TOKEN='<?= generateCsrfToken() ?>';</script>
<script src="<?= BASE_URL ?>assets/js/app.js"></script>
</main><footer class="footer">&copy; <?= date('Y') ?> AMIMS</footer>
</div></div></body></html>
