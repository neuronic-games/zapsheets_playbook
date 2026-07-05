<?php
/**
 * getOptionalSheets.php — returns a JSON array of optional tab names that
 * exist in the spreadsheet (e.g. ["News", "About"]).
 * Called by pushSteps.js before starting a push so it can add them to the queue.
 */

error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';

$sheetId    = trim($_POST['id'] ?? $_GET['id'] ?? '');
$pythonPath = $_ENV['PYTHON'] ?? 'python3';
$pushDir    = __DIR__;

if (!$sheetId) {
    echo json_encode([]);
    exit;
}

// Tabs to check — add any future optional tabs here
$OPTIONAL = ['News', 'About'];

$found = [];
foreach ($OPTIONAL as $tab) {
    $cmd = escapeshellarg($pythonPath) . ' '
         . escapeshellarg($pushDir . '/checkSheetStatus.py') . ' '
         . escapeshellarg($sheetId . 'sheetname' . $tab) . ' 2>/dev/null';
    $out = trim((string) shell_exec($cmd));
    $chk = json_decode($out, true);
    if (($chk['exists'] ?? '') === 'yes') {
        $found[] = $chk['sheet'];   // exact tab name as it appears in the sheet
    }
}

echo json_encode($found);
