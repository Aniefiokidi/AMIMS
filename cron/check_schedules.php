<?php
declare(strict_types=1);
/**
 * AMIMS Daily Schedule Checker
 * Run daily via Windows Task Scheduler:
 *   php C:/xampp/htdocs/amims/cron/check_schedules.php
 * Or trigger manually in browser:
 *   http://localhost/amims/cron/check_schedules.php
 */

$rootDir = dirname(__DIR__);
require_once $rootDir . '/config/db.php';
require_once $rootDir . '/includes/functions.php';

// Bootstrap session-independent auth context (cron has no session)
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/amims/');
}

$autoloadPath = $rootDir . '/vendor/autoload.php';
$mailerAvailable = file_exists($autoloadPath);
if ($mailerAvailable) {
    require_once $autoloadPath;
    require_once $rootDir . '/config/mail.php';
}

$today     = date('Y-m-d');
$todayPlus3 = date('Y-m-d', strtotime('+3 days'));
$log       = [];

function logLine(string $msg): void {
    global $log;
    $line = '[' . date('H:i:s') . '] ' . $msg;
    $log[] = $line;
    echo $line . PHP_EOL;
}

function notifAlreadyExists(PDO $pdo, int $scheduleId, string $type, string $date): bool {
    $stmt = $pdo->prepare(
        "SELECT notif_id FROM notifications
         WHERE schedule_id=:sid AND type=:t AND date(created_at)=:d
         LIMIT 1"
    );
    $stmt->execute([':sid'=>$scheduleId,':t'=>$type,':d'=>$date]);
    return (bool)$stmt->fetch();
}

logLine('Starting AMIMS schedule check for ' . $today);

// ── 1. Check overdue schedules ─────────────────────────────────────────────
$overdueStmt = $pdo->prepare(
    "SELECT ms.schedule_id, ms.notify_emails, ms.next_due_date,
            a.asset_name, a.asset_tag, a.asset_id
     FROM maintenance_schedule ms
     JOIN assets a ON a.asset_id = ms.asset_id
     WHERE ms.status='Scheduled' AND ms.next_due_date < :today"
);
$overdueStmt->execute([':today' => $today]);
$overdueRows = $overdueStmt->fetchAll();

logLine('Found ' . count($overdueRows) . ' overdue schedules.');

foreach ($overdueRows as $s) {
    // Mark overdue
    $pdo->prepare("UPDATE maintenance_schedule SET status='Overdue' WHERE schedule_id=:id")
        ->execute([':id' => $s['schedule_id']]);

    $title = 'Maintenance Overdue: ' . $s['asset_name'];
    $msg   = "Maintenance for asset '{$s['asset_name']}' [{$s['asset_tag']}] was due on {$s['next_due_date']} and is now overdue.";

    // Create notification (avoid duplicate for today)
    if (!notifAlreadyExists($pdo, $s['schedule_id'], 'maintenance', $today)) {
        createNotification($pdo, 'maintenance', $title, $msg, $s['asset_id'], $s['schedule_id']);
        logLine("Notification created for overdue schedule #{$s['schedule_id']}");
    }

    // Send email
    if ($mailerAvailable && $s['notify_emails']) {
        $emails = json_decode($s['notify_emails'], true);
        if (is_array($emails) && !empty($emails)) {
            $htmlBody = '<h2 style="color:#C53030">&#9888; Maintenance Overdue</h2>'
                      . '<p><strong>Asset:</strong> ' . htmlspecialchars($s['asset_name']) . ' [' . htmlspecialchars($s['asset_tag']) . ']</p>'
                      . '<p><strong>Due date:</strong> ' . htmlspecialchars($s['next_due_date']) . '</p>'
                      . '<p>Please attend to this maintenance task immediately.</p>'
                      . '<p style="color:#718096;font-size:12px;">AMIMS Automated Alert</p>';
            $sent = sendEmail($emails, '[AMIMS] OVERDUE: ' . $s['asset_name'], $htmlBody);
            logLine('Email sent for overdue schedule #' . $s['schedule_id'] . ': ' . ($sent ? 'OK' : 'FAILED'));
        }
    }
}

// ── 2. Due-in-3-days reminders ─────────────────────────────────────────────
$reminderStmt = $pdo->prepare(
    "SELECT ms.schedule_id, ms.notify_emails, ms.next_due_date,
            a.asset_name, a.asset_tag, a.asset_id
     FROM maintenance_schedule ms
     JOIN assets a ON a.asset_id = ms.asset_id
     WHERE ms.status='Scheduled' AND ms.next_due_date = :due"
);
$reminderStmt->execute([':due' => $todayPlus3]);
$reminderRows = $reminderStmt->fetchAll();

logLine('Found ' . count($reminderRows) . ' schedules due in 3 days.');

foreach ($reminderRows as $s) {
    $title = 'Maintenance Reminder: ' . $s['asset_name'];
    $msg   = "Maintenance for '{$s['asset_name']}' [{$s['asset_tag']}] is due on {$s['next_due_date']} (3 days from now).";

    if (!notifAlreadyExists($pdo, $s['schedule_id'], 'maintenance', $today)) {
        createNotification($pdo, 'maintenance', $title, $msg, $s['asset_id'], $s['schedule_id']);
        logLine("Reminder notification created for schedule #{$s['schedule_id']}");
    }

    if ($mailerAvailable && $s['notify_emails']) {
        $emails = json_decode($s['notify_emails'], true);
        if (is_array($emails) && !empty($emails)) {
            $htmlBody = '<h2 style="color:#C9A84C">&#9881; Maintenance Due in 3 Days</h2>'
                      . '<p><strong>Asset:</strong> ' . htmlspecialchars($s['asset_name']) . ' [' . htmlspecialchars($s['asset_tag']) . ']</p>'
                      . '<p><strong>Due date:</strong> ' . htmlspecialchars($s['next_due_date']) . '</p>'
                      . '<p>Please prepare for this scheduled maintenance task.</p>'
                      . '<p style="color:#718096;font-size:12px;">AMIMS Automated Reminder</p>';
            $sent = sendEmail($emails, '[AMIMS] REMINDER: ' . $s['asset_name'] . ' due ' . $s['next_due_date'], $htmlBody);
            logLine('Reminder email for schedule #' . $s['schedule_id'] . ': ' . ($sent ? 'OK' : 'FAILED'));
        }
    }
}

// ── 3. Low-stock inventory alerts ─────────────────────────────────────────
$lowStockStmt = $pdo->query(
    "SELECT item_id, item_name, quantity, reorder_level
     FROM inventory_items WHERE quantity <= reorder_level"
);
$lowStockItems = $lowStockStmt->fetchAll();

logLine('Found ' . count($lowStockItems) . ' low-stock inventory items.');

foreach ($lowStockItems as $item) {
    // Check if we already created a low-stock notification for this item today
    $existsStmt = $pdo->prepare(
        "SELECT notif_id FROM notifications
         WHERE item_id=:iid AND type='inventory' AND date(created_at)=:d LIMIT 1"
    );
    $existsStmt->execute([':iid'=>$item['item_id'],':d'=>$today]);
    if ($existsStmt->fetch()) continue;

    $status = $item['quantity'] <= 0 ? 'OUT OF STOCK' : 'LOW STOCK';
    createNotification(
        $pdo, 'inventory',
        $status . ': ' . $item['item_name'],
        "Inventory item '{$item['item_name']}' is {$status}. Current: {$item['quantity']}, Reorder level: {$item['reorder_level']}.",
        null, null, $item['item_id']
    );
    logLine("Low-stock notification created for item #{$item['item_id']} ({$item['item_name']})");
}

// ── Summary ────────────────────────────────────────────────────────────────
logLine('Schedule check complete.');

// Output HTML when accessed via browser
if (!empty($_SERVER['HTTP_HOST'])) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<html><head><title>AMIMS Cron</title>'
       . '<style>body{font-family:monospace;background:#0D2545;color:#fff;padding:2rem;}h1{color:#C9A84C;}'
       . 'pre{background:#1a3a6b;padding:1.5rem;border-radius:8px;color:#a3c4f3;}'
       . 'a{color:#C9A84C;}</style></head><body>'
       . '<h1>&#9881; AMIMS Schedule Checker</h1>'
       . '<pre>' . htmlspecialchars(implode(PHP_EOL, $log)) . '</pre>'
       . '<p><a href="' . BASE_URL . 'modules/dashboard/index.php">&larr; Back to Dashboard</a></p>'
       . '</body></html>';
    exit;
}
