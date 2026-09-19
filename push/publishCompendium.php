<?php
// publishCompendium.php — Save Compendium sheet config and stream publish progress.
//
// POST params:
//   sheet_url — Google Sheets URL or bare sheet ID
//
// Response: newline-delimited JSON (chunked), each line:
//   {"status":"info"|"ok"|"error"|"done"|"close", "msg":"..."}

error_reporting(0);
ini_set('display_errors', '0');

// Disable output buffering so lines stream immediately
if (function_exists('apache_setenv')) { @apache_setenv('no-gzip', 1); }
@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);
while (ob_get_level() > 0) { ob_end_flush(); }

header('Content-Type: application/x-ndjson');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
header('Transfer-Encoding: chunked');

require __DIR__ . '/../dotEnv.php';

function send(string $status, string $msg, array $extra = []): void {
    $data = array_merge(['status' => $status, 'msg' => $msg], $extra);
    echo json_encode($data) . "\n";
    flush();
}

// ── Parse + validate sheet URL ────────────────────────────────────────────────
$raw = trim($_POST['sheet_url'] ?? '');
$sheetId = '';
if (preg_match('#/spreadsheets/d/([A-Za-z0-9_\-]+)#', $raw, $m)) {
    $sheetId = $m[1];
} elseif (preg_match('/^[A-Za-z0-9_\-]{10,}$/', $raw)) {
    $sheetId = $raw;
}

if (!$sheetId) {
    send('error', 'Invalid sheet URL or ID.');
    send('close', '');
    exit;
}

// ── Save / update config ──────────────────────────────────────────────────────
$dataDir    = realpath(__DIR__ . '/..') . '/data';
$configFile = $dataDir . '/compendium_config.json';

if (!is_dir($dataDir)) { mkdir($dataDir, 0777, true); }

$config = [];
if (file_exists($configFile)) {
    $config = json_decode(file_get_contents($configFile), true) ?: [];
}
$config['sheet_url'] = $raw;
$config['sheet_id']  = $sheetId;
// last_published updated after successful run

file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// ── Run gcompendium.py and stream its output ──────────────────────────────────
$pythonPath = $_ENV['PYTHON'] ?? 'python3';
$cmd = escapeshellarg($pythonPath)
     . ' ' . escapeshellarg(__DIR__ . '/gcompendium.py')
     . ' ' . escapeshellarg($sheetId)
     . ' 2>&1';

$proc = popen($cmd, 'r');
if (!$proc) {
    send('error', 'Could not start publish script.');
    send('close', '');
    exit;
}

$success = false;
while (!feof($proc)) {
    $line = fgets($proc);
    if ($line === false) continue;
    $line = trim($line);
    if ($line === '') continue;

    $parsed = json_decode($line, true);
    if (is_array($parsed) && isset($parsed['status'])) {
        echo $line . "\n";
        flush();
        if ($parsed['status'] === 'ok') { $success = true; }
        if ($parsed['status'] === 'error') { $success = false; }
    } else {
        // Raw text line — wrap it
        send('info', $line);
    }
}
pclose($proc);

// ── Update last_published on success ─────────────────────────────────────────
if ($success) {
    $config['last_published'] = date('c');
    file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    send('done', 'Published successfully.');
} else {
    send('done', 'Publish finished with errors.');
}

send('close', '');
?>
