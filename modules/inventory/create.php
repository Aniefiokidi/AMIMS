<?php
declare(strict_types=1);
$pageTitle = 'Add Inventory Item';
$rootDir   = dirname(dirname(dirname(__FILE__)));
require_once $rootDir . '/includes/header.php';
requireRole(['admin','manager']);

$editId = (int)($_GET['edit_id'] ?? 0);
$item   = null;
if ($editId) {
    $s = $pdo->prepare("SELECT * FROM inventory_items WHERE item_id = :id");
    $s->execute([':id' => $editId]);
    $item = $s->fetch();
    if (!$item) { flash('error','Item not found.'); header('Location: ' . BASE_URL . 'modules/inventory/index.php'); exit; }
    $pageTitle = 'Edit Inventory Item';
}

$categories  = $pdo->query("SELECT category_id, category_name FROM categories ORDER BY category_name")->fetchAll();
$departments = $pdo->query("SELECT dept_id, dept_name FROM departments ORDER BY dept_name")->fetchAll();

$errors = [];
$vals   = [
    'item_name'    => $item['item_name']    ?? '',
    'category_id'  => $item['category_id']  ?? '',
    'dept_id'      => $item['dept_id']      ?? '',
    'quantity'     => $item['quantity']     ?? 0,
    'unit'         => $item['unit']         ?? '',
    'reorder_level'=> $item['reorder_level'] ?? 10,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $vals['item_name']     = trim($_POST['item_name']     ?? '');
        $vals['category_id']   = $_POST['category_id']        ?? '';
        $vals['dept_id']       = $_POST['dept_id']            ?? '';
        $vals['quantity']      = max(0, (int)($_POST['quantity']      ?? 0));
        $vals['unit']          = trim($_POST['unit']          ?? '');
        $vals['reorder_level'] = max(0, (int)($_POST['reorder_level'] ?? 10));

        if (!$vals['item_name']) $errors[] = 'Item name is required.';

        if (empty($errors)) {
            if ($editId) {
                $pdo->prepare(
                    "UPDATE inventory_items SET item_name=:n,category_id=:ci,dept_id=:di,
                     quantity=:q,unit=:u,reorder_level=:rl WHERE item_id=:id"
                )->execute([
                    ':n'=>$vals['item_name'],':ci'=>$vals['category_id']?:null,':di'=>$vals['dept_id']?:null,
                    ':q'=>$vals['quantity'],':u'=>$vals['unit']?:null,':rl'=>$vals['reorder_level'],':id'=>$editId,
                ]);
            } else {
                $pdo->prepare(
                    "INSERT INTO inventory_items (item_name,category_id,dept_id,quantity,unit,reorder_level)
                     VALUES (:n,:ci,:di,:q,:u,:rl)"
                )->execute([
                    ':n'=>$vals['item_name'],':ci'=>$vals['category_id']?:null,':di'=>$vals['dept_id']?:null,
                    ':q'=>$vals['quantity'],':u'=>$vals['unit']?:null,':rl'=>$vals['reorder_level'],
                ]);
                $newItemId = (int)$pdo->lastInsertId();
            }

            // Low-stock notification
            $checkQty = $vals['quantity'];
            $checkRl  = $vals['reorder_level'];
            $checkId  = $editId ?: ($newItemId ?? 0);

            if ($checkId && $checkQty <= $checkRl) {
                $status = $checkQty <= 0 ? 'out of stock' : 'low stock';
                createNotification(
                    $pdo, 'inventory',
                    'Low Stock Alert: ' . $vals['item_name'],
                    "Inventory item '{$vals['item_name']}' is {$status}. Current quantity: {$checkQty}, reorder level: {$checkRl}.",
                    null, null, $checkId
                );
            }

            flash('success', ($editId ? 'Item updated.' : 'Item added.'));
            header('Location: ' . BASE_URL . 'modules/inventory/index.php');
            exit;
        }
    }
}
?>

<div class="page-header">
  <div>
    <h1><?= $editId ? 'Edit Inventory Item' : 'Add Inventory Item' ?></h1>
    <p class="breadcrumb"><a href="<?= BASE_URL ?>modules/inventory/index.php">Inventory</a> &rsaquo; <?= $editId ? 'Edit' : 'Add' ?></p>
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
      <div class="form-group full">
        <label class="form-label" for="item_name">Item Name <span class="required">*</span></label>
        <input type="text" id="item_name" name="item_name" class="form-control"
               value="<?= sanitize($vals['item_name']) ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label" for="category_id">Category</label>
        <select id="category_id" name="category_id" class="form-control">
          <option value="">— None —</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= $c['category_id'] ?>" <?= (string)$vals['category_id']===(string)$c['category_id'] ? 'selected' : '' ?>>
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
            <option value="<?= $d['dept_id'] ?>" <?= (string)$vals['dept_id']===(string)$d['dept_id'] ? 'selected' : '' ?>>
              <?= sanitize($d['dept_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="quantity">Quantity <span class="required">*</span></label>
        <input type="number" id="quantity" name="quantity" class="form-control"
               value="<?= (int)$vals['quantity'] ?>" min="0" required>
      </div>
      <div class="form-group">
        <label class="form-label" for="unit">Unit of Measure</label>
        <input type="text" id="unit" name="unit" class="form-control"
               value="<?= sanitize($vals['unit']) ?>" placeholder="e.g. litres, pcs, kg">
      </div>
      <div class="form-group">
        <label class="form-label" for="reorder_level">Reorder Level</label>
        <input type="number" id="reorder_level" name="reorder_level" class="form-control"
               value="<?= (int)$vals['reorder_level'] ?>" min="0">
        <span class="form-hint">Alert is triggered when quantity falls to or below this value.</span>
      </div>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary"><?= $editId ? 'Save Changes' : 'Add Item' ?></button>
      <a href="<?= BASE_URL ?>modules/inventory/index.php" class="btn btn-secondary">Cancel</a>
    </div>
  </form>
</div>

<script>window.BASE_URL='<?= BASE_URL ?>';window.CSRF_TOKEN='<?= generateCsrfToken() ?>';</script>
<script src="<?= BASE_URL ?>assets/js/app.js"></script>
</main><footer class="footer">&copy; <?= date('Y') ?> AMIMS</footer>
</div></div></body></html>
