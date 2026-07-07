<?php
// Dashboard is now served directly from source/dashboard/index.php via .htaccess routing —
// no per-sheet copies are needed.
//
// This script still increments Version in version.json and writes it back to
// each Google Sheet's Settings tab so the in-app version badge stays current.

require dirname(__DIR__) . '/dotEnv.php';

$root       = dirname(__DIR__);
$pythonPath = $_ENV['PYTHON'] ?? 'python3';

// Collect all known sheet IDs (any directory under sheets/)
$singleId = trim($_POST['sheet_id'] ?? '');
if ($singleId !== '') {
    $sheetIds = [$singleId];
} else {
    $sheetDirs = glob($root . '/sheets/*', GLOB_ONLYDIR);
    $sheetIds  = array_map('basename', $sheetDirs ?: []);
}

if (empty($sheetIds)) {
    echo 'SKIP: no sheet directories found';
    exit;
}

// ── Update version.json ───────────────────────────────────────────────────────
$versionFile = $root . '/version.json';
$versionData = ['Version' => 0, 'PublishedOn' => ''];
if (file_exists($versionFile)) {
    $existing = json_decode(file_get_contents($versionFile), true);
    if (is_array($existing)) $versionData = $existing;
}
$versionData['Version']     = (int)($versionData['Version'] ?? 0) + 1;
$versionData['PublishedOn'] = date('M j, Y g:i A');
file_put_contents($versionFile, json_encode($versionData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

$versionLabel = 'v' . $versionData['Version'] . ' · ' . $versionData['PublishedOn'];

// ── Write Version + PublishedOn back to each Google Sheet's Settings tab ──────
$gwriteScript  = __DIR__ . '/gwrite.py';
$gwriteResults = [];
foreach ($sheetIds as $sheetId) {
    $arg = $sheetId . 'version' . $versionData['Version'];
    $cmd = escapeshellarg($pythonPath) . ' '
         . escapeshellarg($gwriteScript) . ' '
         . escapeshellarg($arg) . ' 2>/dev/null';
    $out = trim((string) shell_exec($cmd));
    $gwriteResults[] = $sheetId . ': ' . ($out ?: 'no response');
}

// Return each result on its own line so the sync log can show them individually
echo 'deployed — ' . $versionLabel;
foreach ($gwriteResults as $line) {
    echo "\n" . $line;
}
