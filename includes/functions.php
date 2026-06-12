<?php
declare(strict_types=1);

if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
        ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // Walk up 3 levels: script → module-subdir → modules → project-root
    $script   = dirname(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/')));
    $base     = ($script === '/' || $script === '\\') ? '/' : rtrim($script, '/\\') . '/';
    define('BASE_URL', $protocol . '://' . $host . $base);
}

function flash(string $key, string $msg): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash'][$key] = $msg;
}

function getFlash(string $key): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (isset($_SESSION['flash'][$key])) {
        $msg = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
    return '';
}

function sanitize(mixed $input): string {
    return htmlspecialchars(trim((string)$input), ENT_QUOTES, 'UTF-8');
}

function formatDate(string $date): string {
    if (empty($date) || $date === '0000-00-00') return '—';
    try {
        $d = new DateTime($date);
        return $d->format('d M Y');
    } catch (Exception $e) {
        return $date;
    }
}

function formatDateTime(string $dt): string {
    if (empty($dt)) return '—';
    try {
        $d = new DateTime($dt);
        return $d->format('d M Y, H:i');
    } catch (Exception $e) {
        return $dt;
    }
}

function formatCurrency(mixed $amount): string {
    return '₦' . number_format((float)$amount, 2);
}

function conditionBadge(string $cond): string {
    $map = [
        'Good'              => 'badge-good',
        'Fair'              => 'badge-fair',
        'Bad'               => 'badge-bad',
        'Needs Replacement' => 'badge-needs-replacement',
        'In Use'            => 'badge-in-use',
        'Inactive'          => 'badge-inactive',
    ];
    $class = $map[$cond] ?? 'badge-inactive';
    return '<span class="badge ' . $class . '">' . sanitize($cond) . '</span>';
}

function statusBadge(string $status): string {
    $map = [
        'Scheduled'  => 'badge-scheduled',
        'Overdue'    => 'badge-bad',
        'Completed'  => 'badge-good',
        'Cancelled'  => 'badge-inactive',
        'Low Stock'  => 'badge-needs-replacement',
        'In Stock'   => 'badge-good',
        'Out of Stock' => 'badge-bad',
    ];
    $class = $map[$status] ?? 'badge-inactive';
    return '<span class="badge ' . $class . '">' . sanitize($status) . '</span>';
}

function inventoryStatus(int $qty, int $reorder): string {
    if ($qty <= 0) return 'Out of Stock';
    if ($qty <= $reorder) return 'Low Stock';
    return 'In Stock';
}

function createNotification(
    PDO $pdo,
    string $type,
    string $title,
    string $message,
    ?int $assetId = null,
    ?int $scheduleId = null,
    ?int $itemId = null,
    array $sentTo = []
): int {
    $stmt = $pdo->prepare(
        "INSERT INTO notifications (type, title, message, asset_id, schedule_id, item_id, sent_to)
         VALUES (:type, :title, :message, :asset_id, :schedule_id, :item_id, :sent_to)"
    );
    $stmt->execute([
        ':type'        => $type,
        ':title'       => $title,
        ':message'     => $message,
        ':asset_id'    => $assetId,
        ':schedule_id' => $scheduleId,
        ':item_id'     => $itemId,
        ':sent_to'     => json_encode($sentTo),
    ]);
    return (int)$pdo->lastInsertId();
}

function getUnreadNotifCount(PDO $pdo, int $userId): int {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM notifications
         WHERE is_read = 0
         AND (sent_to IS NULL OR sent_to LIKE :uid)"
    );
    $stmt->execute([':uid' => '%' . $userId . '%']);
    return (int)$stmt->fetchColumn();
}

function paginate(int $total, int $perPage, int $page): array {
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page       = max(1, min($page, $totalPages));
    $offset     = ($page - 1) * $perPage;
    return ['total' => $total, 'per_page' => $perPage, 'page' => $page,
            'total_pages' => $totalPages, 'offset' => $offset];
}

function paginationLinks(array $p, string $baseUrl): string {
    if ($p['total_pages'] <= 1) return '';
    $html = '<div class="pagination">';
    if ($p['page'] > 1) {
        $html .= '<a href="' . $baseUrl . '&page=' . ($p['page'] - 1) . '" class="page-btn">&laquo; Prev</a>';
    }
    $start = max(1, $p['page'] - 2);
    $end   = min($p['total_pages'], $p['page'] + 2);
    for ($i = $start; $i <= $end; $i++) {
        $active = ($i === $p['page']) ? ' active' : '';
        $html  .= '<a href="' . $baseUrl . '&page=' . $i . '" class="page-btn' . $active . '">' . $i . '</a>';
    }
    if ($p['page'] < $p['total_pages']) {
        $html .= '<a href="' . $baseUrl . '&page=' . ($p['page'] + 1) . '" class="page-btn">Next &raquo;</a>';
    }
    $html .= '</div>';
    return $html;
}

function getPageParam(): int {
    return max(1, (int)($_GET['page'] ?? 1));
}
