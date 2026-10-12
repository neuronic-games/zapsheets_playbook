<?php
// tmRefresh.php — re-fetch all T&MBoard tabs from Google Sheets into local JSON cache
// POST: id (sheet ID)
error_reporting(0); ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/refreshJson.php';

$sheetId = trim($_POST['id'] ?? '');
if (!$sheetId) { echo json_encode(['error' => 'Missing id']); exit; }

$py   = $_ENV['PYTHON'] ?? 'python3';
$tabs = ['Clients', 'Projects', 'Time', 'Hardware'];
foreach ($tabs as $tab) {
    refreshJson($py, $sheetId, $tab);
}

echo json_encode(['ok' => true]);
?>
