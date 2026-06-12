<?php
declare(strict_types=1);
$pageTitle = 'Edit Asset';
$rootDir   = dirname(dirname(dirname(__FILE__)));
require_once $rootDir . '/includes/header.php';
requireRole(['admin', 'manager']);

$assetId = (int)($_GET['id'] ?? 0);
if (!$assetId) {
    flash('error', 'No asset specified.');
    header('Location: ' . BASE_URL . 'modules/assets/index.php');
    exit;
}
$stmt = $pdo->prepare("SELECT * FROM assets WHERE asset_id = :id");
$stmt->execute([':id' => $assetId]);
$asset = $stmt->fetch();
if (!$asset) {
    flash('error', 'Asset not found.');
    header('Location: ' . BASE_URL . 'modules/assets/index.php');
    exit;
}

$categories  = $pdo->query("SELECT category_id, category_name FROM categories ORDER BY category_name")->fetchAll();
$departments = $pdo->query("SELECT dept_id, dept_name FROM departments ORDER BY dept_name")->fetchAll();
$users       = $pdo->query("SELECT user_id, full_name FROM users WHERE is_active=1 ORDER BY full_name")->fetchAll();
$conditions  = ['Good','Fair','Bad','Needs Replacement','In Use','Inactive'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $fields = ['asset_name','asset_tag','category_id','dept_id','condition','purchase_date','purchase_cost','assigned_to','notes'];
        foreach ($fields as $f) {
            $asset[$f] = trim($_POST[$f] ?? '');
        }
        if (!$asset['asset_name']) $errors[] = 'Asset name is required.';
        if (!$asset['asset_tag'])  $errors[] = 'Asset tag is required.';
        if (!$asset['condition'])  $errors[] = 'Condition is required.';

        $chk = $pdo->prepare("SELECT asset_id FROM assets WHERE asset_tag=:tag AND asset_id != :id");
        $chk->execute([':tag' => $asset['asset_tag'], ':id' => $assetId]);
        if ($chk->fetch()) $errors[] = 'Another asset already uses this tag.';

        if (empty($errors)) {
            $pdo->prepare(
                "UPDATE assets SET asset_name=:an, asset_tag=:at, category_id=:ci, dept_id=:di,
                 `condition`=:co, purchase_date=:pd, purchase_cost=:pc, assigned_to=:asgn, notes=:no
                 WHERE asset_id=:id"
            )->execute([
                ':an'  => $asset['asset_name'],
                ':at'  => $asset['asset_tag'],
                ':ci'  => $asset['category_id']   ?: null,
                ':di'  => $asset['dept_id']        ?: null,
                ':co'  => $asset['condition'],
                ':pd'  => $asset['purchase_date']  ?: null,
                ':pc'  => $asset['purchase_cost'] !== '' ? $asset['purchase_cost'] : null,
                ':asgn'=> $asset['assigned_to']   ?: null,
                ':no'  => $asset['notes']          ?: null,
                ':id'  => $assetId,
            ]);
            flash('success', 'Asset updated successfully.');
            header('Location: ' . BASE_URL . 'modules/assets/view.php?id=' . $assetId);
            exit;
        }
    }
}
?>

<div class="page-header">
  <div>
    <h1>Edit Asset</h1>
    <p class="breadcrumb">
      <a href="<?= BASE_URL ?>modules/assets/index.php">Assets</a> &rsaquo;
      <a href="<?= BASE_URL ?>modules/assets/view.php?id=<?= $assetId ?>"><?= sanitize($asset['asset_name']) ?></a> &rsaquo; Edit
    </p>
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
               value="<?= sanitize($asset['asset_name']) ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label" for="asset_tag">Asset Tag <span class="required">*</span></label>
        <input type="text" id="asset_tag" name="asset_tag" class="form-control"
               value="<?= sanitize($asset['asset_tag']) ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label" for="category_id">Category</label>
        <select id="category_id" name="category_id" class="form-control">
          <option value="">— None —</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= $c['category_id'] ?>" <?= (string)$asset['category_id']===(string)$c['category_id'] ? 'selected' : '' ?>>
              <?= sanitize($c['category_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="dept_id">Department</label>
        <select id="dept_id" name="dept_id" class="form-control">
          <option value="">— None —</option>
          <?php foreach ($departments as $d): ?>
            <option value="<?= $d['dept_id'] ?>" <?= (string)$asset['dept_id']===(string)$d['dept_id'] ? 'selected' : '' ?>>
              <?= sanitize($d['dept_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="condition">Condition <span class="required">*</span></label>
        <select id="condition" name="condition" class="form-control" required>
          <?php foreach ($conditions as $cn): ?>
            <option value="<?= $cn ?>" <?= $asset['condition']===$cn ? 'selected' : '' ?>><?= $cn ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="assigned_to">Assigned To</label>
        <select id="assigned_to" name="assigned_to" class="form-control">
          <option value="">— Unassigned —</option>
          <?php foreach ($users as $u): ?>
            <option value="<?= $u['user_id'] ?>" <?= (string)$asset['assigned_to']===(string)$u['user_id'] ? 'selected' : '' ?>>
              <?= sanitize($u['full_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="purchase_date">Purchase Date</label>
        <input type="date" id="purchase_date" name="purchase_date" class="form-control"
               value="<?= sanitize($asset['purchase_date'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label" for="purchase_cost">Purchase Cost (₦)</label>
        <input type="number" id="purchase_cost" name="purchase_cost" class="form-control"
               value="<?= sanitize($asset['purchase_cost'] ?? '') ?>" step="0.01" min="0">
      </div>
      <div class="form-group full">
        <label class="form-label" for="notes">Notes</label>
        <textarea id="notes" name="notes" class="form-control"><?= sanitize($asset['notes'] ?? '') ?></textarea>
      </div>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Save Changes</button>
      <a href="<?= BASE_URL ?>modules/assets/view.php?id=<?= $assetId ?>" class="btn btn-secondary">Cancel</a>
    </div>
  </form>
</div>

<script>window.BASE_URL='<?= BASE_URL ?>';window.CSRF_TOKEN='<?= generateCsrfToken() ?>';</script>
<script src="<?= BASE_URL ?>assets/js/app.js"></script>
</main><footer class="footer">&copy; <?= date('Y') ?> AMIMS</footer>
</div></div></body></html>
