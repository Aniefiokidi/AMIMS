<?php
declare(strict_types=1);
$pageTitle = 'Generating Report…';
$rootDir   = dirname(dirname(dirname(__FILE__)));
require_once $rootDir . '/config/db.php';
require_once $rootDir . '/includes/functions.php';
require_once $rootDir . '/includes/auth.php';
requireLogin();
requireRole(['admin','manager']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash('error', 'Invalid request method.');
    header('Location: ' . BASE_URL . 'modules/reports/index.php');
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    flash('error', 'Invalid CSRF token.');
    header('Location: ' . BASE_URL . 'modules/reports/index.php');
    exit;
}

$reportType     = trim($_POST['report_type']      ?? '');
$recipientStr   = trim($_POST['recipient_emails'] ?? '');
$dateFrom       = trim($_POST['date_from']        ?? '');
$dateTo         = trim($_POST['date_to']          ?? '');

$allowedTypes = ['inventory','maintenance_history','asset_condition','scheduled_maintenance'];
if (!in_array($reportType, $allowedTypes)) {
    flash('error', 'Invalid report type selected.');
    header('Location: ' . BASE_URL . 'modules/reports/index.php');
    exit;
}

$recipients = array_filter(array_map('trim', explode(',', $recipientStr)), fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL));

// ── Fetch report data ──────────────────────────────────────────────────────
$reportData   = [];
$reportTitle  = '';
$reportHeaders = [];

switch ($reportType) {
    case 'inventory':
        $reportTitle   = 'Inventory Report';
        $reportHeaders = ['Item Name','Category','Department','Quantity','Unit','Reorder Level','Status'];
        $rows = $pdo->query(
            "SELECT i.item_name, c.category_name, d.dept_name, i.quantity, i.unit, i.reorder_level
             FROM inventory_items i
             LEFT JOIN categories c ON c.category_id = i.category_id
             LEFT JOIN departments d ON d.dept_id = i.dept_id
             ORDER BY i.item_name"
        )->fetchAll();
        foreach ($rows as $r) {
            $status = $r['quantity'] <= 0 ? 'Out of Stock' : ($r['quantity'] <= $r['reorder_level'] ? 'Low Stock' : 'In Stock');
            $reportData[] = [
                $r['item_name'], $r['category_name']??'—', $r['dept_name']??'—',
                $r['quantity'], $r['unit']??'—', $r['reorder_level'], $status,
            ];
        }
        break;

    case 'maintenance_history':
        $reportTitle   = 'Maintenance History Report';
        $reportHeaders = ['Date','Asset','Tag','Type','Performed By','Description','Cost (₦)','Outcome'];
        $whereDate = '';
        $params = [];
        if ($dateFrom) { $whereDate .= " AND mh.performed_date >= :df"; $params[':df'] = $dateFrom; }
        if ($dateTo)   { $whereDate .= " AND mh.performed_date <= :dt"; $params[':dt'] = $dateTo; }
        $stmt = $pdo->prepare(
            "SELECT mh.performed_date, a.asset_name, a.asset_tag, ms.schedule_type,
                    u.full_name, mh.description, mh.cost, mh.outcome
             FROM maintenance_history mh
             JOIN assets a ON a.asset_id = mh.asset_id
             LEFT JOIN users u ON u.user_id = mh.performed_by
             LEFT JOIN maintenance_schedule ms ON ms.schedule_id = mh.schedule_id
             WHERE 1=1 $whereDate
             ORDER BY mh.performed_date DESC"
        );
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $r) {
            $reportData[] = [
                $r['performed_date'], $r['asset_name'], $r['asset_tag'],
                $r['schedule_type']??'Ad-hoc', $r['full_name']??'—',
                mb_strimwidth($r['description'],0,80,'…'), number_format((float)($r['cost']??0),2),
                $r['outcome']??'—',
            ];
        }
        break;

    case 'asset_condition':
        $reportTitle   = 'Asset Condition Report';
        $reportHeaders = ['Asset Name','Tag','Category','Department','Condition','Assigned To','Purchase Date','Cost (₦)'];
        $rows = $pdo->query(
            "SELECT a.asset_name, a.asset_tag, c.category_name, d.dept_name,
                    a.`condition`, u.full_name AS assigned, a.purchase_date, a.purchase_cost
             FROM assets a
             LEFT JOIN categories c ON c.category_id = a.category_id
             LEFT JOIN departments d ON d.dept_id = a.dept_id
             LEFT JOIN users u ON u.user_id = a.assigned_to
             ORDER BY a.`condition`, a.asset_name"
        )->fetchAll();
        foreach ($rows as $r) {
            $reportData[] = [
                $r['asset_name'], $r['asset_tag'], $r['category_name']??'—', $r['dept_name']??'—',
                $r['condition'], $r['assigned']??'—',
                $r['purchase_date'] ? date('d M Y', strtotime($r['purchase_date'])) : '—',
                $r['purchase_cost'] ? number_format((float)$r['purchase_cost'],2) : '—',
            ];
        }
        break;

    case 'scheduled_maintenance':
        $reportTitle   = 'Scheduled Maintenance Report';
        $reportHeaders = ['Asset','Tag','Type','Frequency','Next Due','Officer','Status'];
        $rows = $pdo->query(
            "SELECT a.asset_name, a.asset_tag, ms.schedule_type, ms.frequency,
                    ms.next_due_date, u.full_name AS officer, ms.status
             FROM maintenance_schedule ms
             JOIN assets a ON a.asset_id = ms.asset_id
             LEFT JOIN users u ON u.user_id = ms.assigned_to
             ORDER BY FIELD(ms.status,'Overdue','Scheduled','Completed','Cancelled'), ms.next_due_date ASC"
        )->fetchAll();
        foreach ($rows as $r) {
            $reportData[] = [
                $r['asset_name'], $r['asset_tag'], $r['schedule_type'], $r['frequency'],
                date('d M Y', strtotime($r['next_due_date'])), $r['officer']??'—', $r['status'],
            ];
        }
        break;
}

// ── Generate PDF with TCPDF ─────────────────────────────────────────────────
$autoloadPath = $rootDir . '/vendor/autoload.php';
$pdfPath      = null;

if (file_exists($autoloadPath)) {
    require_once $autoloadPath;

    $timestamp = date('Ymd_His');
    $filename  = $reportType . '_' . $timestamp . '.pdf';
    $savePath  = $rootDir . '/reports/pdf/';
    if (!is_dir($savePath)) { mkdir($savePath, 0755, true); }
    $fullPath  = $savePath . $filename;
    $relPath   = 'reports/pdf/' . $filename;

    try {
        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('AMIMS');
        $pdf->SetAuthor('AMIMS System');
        $pdf->SetTitle($reportTitle);
        $pdf->SetHeaderData('', 0, 'AMIMS — Oil & Gas Division', $reportTitle . ' | Generated: ' . date('d M Y H:i'));
        $pdf->setHeaderFont(['helvetica', 'B', 10]);
        $pdf->setFooterFont(['helvetica', '', 8]);
        $pdf->SetDefaultMonospacedFont('courier');
        $pdf->SetMargins(10, 22, 10);
        $pdf->SetHeaderMargin(5);
        $pdf->SetFooterMargin(10);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->setImageScale(1.25);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->AddPage();

        // Title
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 8, $reportTitle, 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 9);
        if ($dateFrom || $dateTo) {
            $range = '';
            if ($dateFrom) $range .= 'From: ' . date('d M Y', strtotime($dateFrom)) . '  ';
            if ($dateTo)   $range .= 'To: ' . date('d M Y', strtotime($dateTo));
            $pdf->Cell(0, 5, $range, 0, 1, 'C');
        }
        $pdf->Ln(4);

        // Table header
        $colCount = count($reportHeaders);
        $colWidth = max(20, (270 / $colCount));
        $pdf->SetFillColor(13, 37, 69);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 8);
        foreach ($reportHeaders as $h) {
            $pdf->Cell($colWidth, 7, $h, 1, 0, 'C', true);
        }
        $pdf->Ln();

        // Table rows
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 7.5);
        $fill = false;
        if (empty($reportData)) {
            $pdf->SetFillColor(247, 249, 252);
            $pdf->Cell($colWidth * $colCount, 7, 'No records found for this report.', 1, 1, 'C', true);
        } else {
            foreach ($reportData as $row) {
                $pdf->SetFillColor($fill ? 240 : 255, $fill ? 244 : 255, $fill ? 248 : 255);
                foreach ($row as $cell) {
                    $pdf->Cell($colWidth, 6, mb_strimwidth((string)$cell, 0, 40, '…'), 1, 0, 'L', true);
                }
                $pdf->Ln();
                $fill = !$fill;
            }
        }

        // Summary row
        $pdf->Ln(3);
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->Cell(0, 5, 'Total records: ' . count($reportData) . '  |  Report generated by: ' . currentUserName() . '  |  ' . date('d M Y H:i:s'), 0, 1, 'R');

        $pdf->Output($fullPath, 'F');
        $pdfPath = $relPath;

    } catch (Throwable $e) {
        error_log('AMIMS TCPDF error: ' . $e->getMessage());
        $pdfPath = null;
    }
}

// ── Send email ─────────────────────────────────────────────────────────────
$mailSent = false;
if (!empty($recipients) && $pdfPath) {
    require_once $rootDir . '/config/mail.php';
    $subject = '[AMIMS] ' . $reportTitle . ' — ' . date('d M Y');
    $body    = '<h2 style="color:#0D2545">' . htmlspecialchars($reportTitle) . '</h2>'
             . '<p>Please find the attached AMIMS report generated on ' . date('d M Y H:i') . '.</p>'
             . '<p><strong>Total records:</strong> ' . count($reportData) . '</p>'
             . '<p style="color:#718096;font-size:12px;">This is an automated message from the AMIMS system. Do not reply to this email.</p>';
    $mailSent = sendEmail($recipients, $subject, $body, $rootDir . '/' . $pdfPath);
}

// ── Save report record ─────────────────────────────────────────────────────
$pdo->prepare(
    "INSERT INTO reports (report_type, generated_by, file_path, sent_to_emails)
     VALUES (:rt, :gb, :fp, :ste)"
)->execute([
    ':rt'  => $reportType,
    ':gb'  => currentUserId(),
    ':fp'  => $pdfPath,
    ':ste' => $recipientStr ?: null,
]);

// ── Redirect with result ───────────────────────────────────────────────────
if ($pdfPath) {
    $msg = 'Report generated successfully.';
    if ($mailSent) $msg .= ' Email sent to: ' . implode(', ', $recipients) . '.';
    elseif (!empty($recipients)) $msg .= ' (Email sending failed — check mail config.)';
    flash('success', $msg);
    $_SESSION['last_report_path'] = BASE_URL . $pdfPath;
} else {
    flash('info', 'Report saved (PDF generation requires Composer — run: composer install).');
}

header('Location: ' . BASE_URL . 'modules/reports/index.php');
exit;
