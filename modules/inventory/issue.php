<?php
declare(strict_types=1);
$pageTitle = 'Issue Stock';
$rootDir   = dirname(dirname(dirname(__FILE__)));
require_once $rootDir . '/includes/header.php';

$items  = $pdo->query(
    "SELECT i.item_id, i.item_name, i.quantity, i.unit, i.reorder_level, d.dept_name
     FROM inventory_items i
     LEFT JOIN departments d ON d.dept_id = i.dept_id
     ORDER BY i.item_name"
)->fetchAll();

$errors = [];
$vals   = ['item_id'=>'','quantity_issued'=>1,'work_order'=>'','issued_to'=>'','notes'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $vals['item_id']         = (int)($_POST['item_id']         ?? 0);
        $vals['quantity_issued'] = max(1, (int)($_POST['quantity_issued'] ?? 1));
        $vals['work_order']      = trim($_POST['work_order']        ?? '');
        $vals['issued_to']       = trim($_POST['issued_to']         ?? '');
        $vals['notes']           = trim($_POST['notes']             ?? '');

        if (!$vals['item_id'])  $errors[] = 'Please select an item.';
        if ($vals['quantity_issued'] < 1) $errors[] = 'Quantity must be at least 1.';

        if (empty($errors)) {
            // Fetch current stock
            $stockStmt = $pdo->prepare("SELECT quantity, reorder_level, item_name FROM inventory_items WHERE item_id=:id FOR UPDATE");
            $pdo->beginTransaction();
            $stockStmt->execute([':id' => $vals['item_id']]);
            $stockRow = $stockStmt->fetch();

            if (!$stockRow) {
                $errors[] = 'Item not found.';
                $pdo->rollBack();
            } elseif ($stockRow['quantity'] < $vals['quantity_issued']) {
                $errors[] = 'Insufficient stock. Available: ' . $stockRow['quantity'] . ' ' . ($stockRow['unit'] ?? 'units');
                $pdo->rollBack();
            } else {
                $newQty = $stockRow['quantity'] - $vals['quantity_issued'];
                $pdo->prepare("UPDATE inventory_items SET quantity=:q WHERE item_id=:id")
                    ->execute([':q' => $newQty, ':id' => $vals['item_id']]);
                $pdo->commit();

                // Low-stock notification
                if ($newQty <= $stockRow['reorder_level']) {
                    $status = $newQty <= 0 ? 'out of stock' : 'low on stock';
                    createNotification(
                        $pdo, 'inventory',
                        'Low Stock Alert: ' . $stockRow['item_name'],
                        "After issuing {$vals['quantity_issued']} units, '{$stockRow['item_name']}' is now {$status}. Remaining: {$newQty}.",
                        null, null, $vals['item_id']
                    );
                }

                flash('success', "Issued {$vals['quantity_issued']} unit(s) of '{$stockRow['item_name']}'. Remaining stock: {$newQty}.");
                header('Location: ' . BASE_URL . 'modules/inventory/index.php');
                exit;
            }
        }
    }
}
?>

<div class="page-header">
  <div>
    <h1>Issue Stock</h1>
    <p class="breadcrumb"><a href="<?= BASE_URL ?>modules/inventory/index.php">Inventory</a> &rsaquo; Issue Stock</p>
  </div>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <span class="alert-icon">&#9888;</span>
    <div><?php foreach ($errors as $e): ?><div><?= sanitize($e) ?></div><?php endforeach; ?></div>
  </div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">
  <div class="form-card" style="max-width:100%;">
    <h2 class="section-title">Issue Stock to Work Order</h2>
    <form method="POST" action="" novalidate>
      <?= csrfField() ?>
      <div class="form-group mb-4">
        <label class="form-label" for="item_id">Inventory Item <span class="required">*</span></label>
        <select id="item_id" name="item_id" class="form-control" required>
          <option value="">— Select item —</option>
          <?php foreach ($items as $it): ?>
            <?php $invSt = inventoryStatus($it['quantity'], $it['reorder_level']); ?>
            <option value="<?= $it['item_id'] ?>"
                    <?= $vals['item_id']===$it['item_id'] ? 'selected' : '' ?>
                    style="<?= $invSt==='Out of Stock' ? 'color:#C53030' : ($invSt==='Low Stock' ? 'color:#C05621' : '') ?>">
              <?= sanitize($it['item_name']) ?>
              (<?= $it['quantity'] ?> <?= sanitize($it['unit'] ?? 'units') ?>)
              <?= $invSt !== 'In Stock' ? "[$invSt]" : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group mb-4">
        <label class="form-label" for="quantity_issued">Quantity to Issue <span class="required">*</span></label>
        <input type="number" id="quantity_issued" name="quantity_issued" class="form-control"
               value="<?= (int)$vals['quantity_issued'] ?>" min="1" required>
      </div>
      <div class="form-group mb-4">
        <label class="form-label" for="work_order">Work Order / Reference</label>
        <input type="text" id="work_order" name="work_order" class="form-control"
               value="<?= sanitize($vals['work_order']) ?>" placeholder="WO-2024-001">
      </div>
      <div class="form-group mb-4">
        <label class="form-label" for="issued_to">Issued To</label>
        <input type="text" id="issued_to" name="issued_to" class="form-control"
               value="<?= sanitize($vals['issued_to']) ?>" placeholder="Person or team receiving the stock">
      </div>
      <div class="form-group mb-4">
        <label class="form-label" for="notes">Notes</label>
        <textarea id="notes" name="notes" class="form-control" rows="3"><?= sanitize($vals['notes']) ?></textarea>
      </div>
      <div class="form-actions" style="border:0;padding:0;margin:0;">
        <button type="submit" class="btn btn-primary">Issue Stock</button>
        <a href="<?= BASE_URL ?>modules/inventory/index.php" class="btn btn-secondary">Cancel</a>
      </div>
    </form>
  </div>

  <!-- Current stock table -->
  <div class="card" style="max-height:600px;overflow-y:auto;">
    <div class="card-header">
      <span class="card-title">Current Stock Levels</span>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Item</th><th>Qty</th><th>Reorder</th><th>Status</th></tr>
        </thead>
        <tbody>
          <?php foreach ($items as $it): ?>
          <?php $st = inventoryStatus($it['quantity'], $it['reorder_level']); ?>
          <tr>
            <td><?= sanitize($it['item_name']) ?></td>
            <td><strong><?= $it['quantity'] ?></strong> <?= sanitize($it['unit'] ?? '') ?></td>
            <td><?= $it['reorder_level'] ?></td>
            <td><?= statusBadge($st) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>window.BASE_URL='<?= BASE_URL ?>';window.CSRF_TOKEN='<?= generateCsrfToken() ?>';</script>
<script src="<?= BASE_URL ?>assets/js/app.js"></script>
</main><footer class="footer">&copy; <?= date('Y') ?> AMIMS</footer>
</div></div></body></html>
