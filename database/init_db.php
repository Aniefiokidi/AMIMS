<?php
$sql = file_get_contents(__DIR__ . '/amims_sqlite.sql');
$dbPath = getenv('DB_PATH') ?: __DIR__ . '/amims.db';

if (file_exists($dbPath)) unlink($dbPath);

$db = new PDO('sqlite:' . $dbPath);
$db->exec('PRAGMA foreign_keys = OFF;');

foreach (explode(';', $sql) as $stmt) {
    $stmt = trim($stmt);
    if ($stmt) {
        try {
            $db->exec($stmt . ';');
        } catch (PDOException $e) {
            echo "ERR: " . $e->getMessage() . "\n>> " . substr($stmt, 0, 100) . "\n";
        }
    }
}

$db->exec('PRAGMA foreign_keys = ON;');

$tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
echo "Tables:        " . implode(', ', $tables) . "\n";
echo "Users:         " . $db->query("SELECT COUNT(*) FROM users")->fetchColumn() . "\n";
echo "Assets:        " . $db->query("SELECT COUNT(*) FROM assets")->fetchColumn() . "\n";
echo "Schedules:     " . $db->query("SELECT COUNT(*) FROM maintenance_schedule")->fetchColumn() . "\n";
echo "Inventory:     " . $db->query("SELECT COUNT(*) FROM inventory_items")->fetchColumn() . "\n";
echo "Notifications: " . $db->query("SELECT COUNT(*) FROM notifications")->fetchColumn() . "\n";
echo "DB size:       " . round(filesize($dbPath) / 1024, 1) . " KB\n";
echo "DONE\n";
