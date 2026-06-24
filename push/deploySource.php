<?php
// Copies source/dashboard/index.php → sheets/*/dashboard/index.php
// Also increments Version and updates PublishedOn in version.json
// and writes Version + PublishedOn back to each Google Sheet's Settings tab.

require dirname(__DIR__) . '/dotEnv.php';

$root       = dirname(__DIR__);
$srcFile    = $root . '/source/dashboard/index.php';
$pythonPath = $_ENV['PYTHON'] ?? 'python3';

if (!file_exists($srcFile)) {
    echo 'ERROR: source/dashboard/index.php not found';
    exit;
}

$dirs = glob($root . '/sheets/*/dashboard', GLOB_ONLYDIR);
if (empty($dirs)) {
    echo 'SKIP: no sheet dashboard directories found';
    exit;
}

$count = 0;
foreach ($dirs as $dir) {
    $dest = $dir . '/index.php';
    if (!copy($srcFile, $dest)) {
        echo 'ERROR: could not write to ' . basename(dirname($dir)) . '/dashboard/index.php';
        exit;
    }
    $count++;
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
foreach ($dirs as $dir) {
    $sheetId = basename(dirname($dir));
    $arg     = $sheetId . 'version' . $versionData['Version'];
    $cmd     = escapeshellarg($pythonPath) . ' '
             . escapeshellarg($gwriteScript) . ' '
             . escapeshellarg($arg) . ' 2>/dev/null';
    $out = trim((string) shell_exec($cmd));
    $gwriteResults[] = $sheetId . ': ' . ($out ?: 'no response');
}

// Return each result on its own line so the sync log can show them individually
echo 'source deployed to ' . $count . ' sheet' . ($count === 1 ? '' : 's')
   . ' — ' . $versionLabel;
foreach ($gwriteResults as $line) {
    echo "\n" . $line;
}
