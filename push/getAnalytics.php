<?php
// getAnalytics.php — return fresh page-view stats and storage size for a sheet.
//
// POST params:
//   id — Google Spreadsheet ID (sheet_Id)
//
// Response: { stats: {...}, storage: { bytes, files } }

error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/pageViewLogger.php';

$sheetId = trim($_POST['id'] ?? '');
if (!$sheetId) {
    echo json_encode(['error' => 'Missing id']);
    exit;
}

// ── Page-view stats ────────────────────────────────────────────────────────
$stats = pageViewStats($sheetId);

// ── Storage size ───────────────────────────────────────────────────────────
$bytes     = 0;
$fileCount = 0;
$sheetsDir = dirname(__DIR__) . '/sheets/' . $sheetId;
if (is_dir($sheetsDir)) {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sheetsDir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($it as $f) {
        if ($f->isFile()) { $bytes += $f->getSize(); $fileCount++; }
    }
}

echo json_encode([
    'ok'      => true,
    'stats'   => $stats,
    'storage' => ['bytes' => $bytes, 'files' => $fileCount],
]);
?>
