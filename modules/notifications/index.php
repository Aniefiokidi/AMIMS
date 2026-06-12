<?php
declare(strict_types=1);
$pageTitle = 'Notifications';
$rootDir   = dirname(dirname(dirname(__FILE__)));
require_once $rootDir . '/includes/header.php';

$userId = currentUserId();

// Mark all as read (button)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $pdo->query("UPDATE notifications SET is_read=1");
        flash('success', 'All notifications marked as read.');
    }
    header('Location: ' . BASE_URL . 'modules/notifications/index.php');
    exit;
}

$activeTab = $_GET['tab'] ?? 'all';
$allowedTabs = ['all','unread','maintenance','inventory','system','report'];
if (!in_array($activeTab, $allowedTabs)) $activeTab = 'all';

// Build query
$where = '';
$params = [];
switch ($activeTab) {
    case 'unread':      $where = 'WHERE is_read=0'; break;
    case 'maintenance': $where = "WHERE type='maintenance'"; break;
    case 'inventory':   $where = "WHERE type='inventory'"; break;
    case 'system':      $where = "WHERE type='system'"; break;
    case 'report':      $where = "WHERE type='report'"; break;
}

$notifications = $pdo->prepare(
    "SELECT * FROM notifications $where ORDER BY created_at DESC LIMIT 100"
);
$notifications->execute($params);
$notifs = $notifications->fetchAll();

$unreadTotal = getUnreadNotifCount($pdo, $userId);
?>

<div class="page-header">
  <div>
    <h1>Notifications</h1>
    <p class="breadcrumb">System alerts, maintenance reminders &amp; inventory warnings</p>
  </div>
  <?php if ($unreadTotal > 0): ?>
  <form method="POST" action="">
    <?= csrfField() ?>
    <input type="hidden" name="mark_all_read" value="1">
    <button type="submit" class="btn btn-secondary">Mark All as Read</button>
  </form>
  <?php endif; ?>
</div>

<!-- Tabs -->
<div class="notif-tabs">
  <?php
  $tabs = [
    'all'         => 'All',
    'unread'      => 'Unread' . ($unreadTotal > 0 ? " ($unreadTotal)" : ''),
    'maintenance' => 'Maintenance',
    'inventory'   => 'Inventory',
    'system'      => 'System',
    'report'      => 'Reports',
  ];
  foreach ($tabs as $key => $label):
  ?>
    <a href="?tab=<?= $key ?>" class="notif-tab <?= $activeTab===$key ? 'active' : '' ?>">
      <?= sanitize($label) ?>
    </a>
  <?php endforeach; ?>
</div>

<div class="notif-list">
  <?php if (empty($notifs)): ?>
    <div style="text-align:center;padding:3rem;color:var(--muted);">
      <div style="font-size:2.5rem;margin-bottom:0.75rem;">&#9993;</div>
      <p>No notifications here.</p>
    </div>
  <?php else: ?>
    <?php foreach ($notifs as $n): ?>
    <?php
    $link = '';
    if ($n['asset_id'])    $link = BASE_URL . 'modules/assets/view.php?id=' . $n['asset_id'];
    if ($n['schedule_id']) $link = BASE_URL . 'modules/maintenance/index.php';
    if ($n['item_id'])     $link = BASE_URL . 'modules/inventory/index.php';
    ?>
    <div class="notif-card type-<?= sanitize($n['type']) ?> <?= !$n['is_read'] ? 'unread' : '' ?>"
         data-id="<?= $n['notif_id'] ?>"
         data-link="<?= sanitize($link) ?>">
      <?php if (!$n['is_read']): ?>
        <span class="notif-dot"></span>
      <?php endif; ?>
      <div class="notif-body">
        <div class="notif-title"><?= sanitize($n['title']) ?></div>
        <div class="notif-msg"><?= sanitize($n['message']) ?></div>
        <div class="notif-time">
          <span class="badge badge-<?= $n['type'] === 'maintenance' ? 'fair' : ($n['type'] === 'inventory' ? 'needs-replacement' : 'in-use') ?>"
                style="font-size:0.65rem;">
            <?= ucfirst(sanitize($n['type'])) ?>
          </span>
          &nbsp;<?= formatDateTime($n['created_at']) ?>
          <?php if ($link): ?>
            &nbsp;&mdash; <a href="<?= sanitize($link) ?>" style="color:var(--navy);font-size:0.75rem;" onclick="event.stopPropagation();">View Record</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<script>window.BASE_URL='<?= BASE_URL ?>';window.CSRF_TOKEN='<?= generateCsrfToken() ?>';</script>
<script src="<?= BASE_URL ?>assets/js/app.js"></script>
</main><footer class="footer">&copy; <?= date('Y') ?> AMIMS</footer>
</div></div></body></html>
