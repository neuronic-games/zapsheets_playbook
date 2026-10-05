<?php
/**
 * createReport.php — generate a playtest report Google Doc from DevBoard session data.
 *
 * POST params:
 *   id         — Google Spreadsheet ID
 *   game       — Game name
 *   client     — Client / publisher name
 *   ref_code   — Optional reference code (e.g. "TR05")
 */

error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';

$sheetId  = trim($_POST['id']       ?? '');
$game     = trim($_POST['game']     ?? '');
$client   = trim($_POST['client']   ?? '');
$refCode  = trim($_POST['ref_code'] ?? '');

if (!$sheetId || !$game) {
    echo json_encode(['error' => 'Missing id or game']);
    exit;
}

// Load settings for sender identity
$settingsFile = dirname(__DIR__) . '/sheets/' . $sheetId . '/settings.json';
$settings     = file_exists($settingsFile)
    ? (json_decode(file_get_contents($settingsFile), true) ?: [])
    : [];

$myName    = '';
$myCompany = '';
$myAddress = '';
$myEmail   = '';
$myPhone   = '';

foreach ($settings as $s) {
    $n = $s['Name'] ?? $s['name'] ?? $s['My Name'] ?? '';
    $v = $s['Value'] ?? $s['value'] ?? '';
    // Support both {Name, Value} and {My Name, value} formats
    if (!$n && !$v) {
        foreach ($s as $k => $val) {
            if (stripos($k, 'name') !== false) $n = $k;
            $v = $val;
        }
    }
    if ($n === 'My Name')     $myName    = $v;
    if ($n === 'My Email')    $myEmail   = $v;
    if ($n === 'My Phone')    $myPhone   = $v;
    if ($n === 'My Company')  $myCompany = $v;
    if ($n === 'My Address')  $myAddress = $v;
}

$pythonPath = $_ENV['PYTHON'] ?? 'python3';

$payload = base64_encode(json_encode([
    'game'       => $game,
    'client'     => $client,
    'my_name'    => $myName,
    'my_company' => $myCompany,
    'my_address' => $myAddress,
    'my_email'   => $myEmail,
    'my_phone'   => $myPhone,
    'ref_code'   => $refCode,
], JSON_UNESCAPED_UNICODE));

$arg    = $sheetId . '|' . $payload;
$cmd    = escapeshellarg($pythonPath) . ' '
        . escapeshellarg(__DIR__ . '/gcreateReport.py') . ' '
        . escapeshellarg($arg) . ' 2>&1';
$output = trim((string) shell_exec($cmd));

if ($output === '') {
    echo json_encode(['error' => 'No response from Python script']);
    exit;
}

$result = json_decode($output, true);
if ($result === null) {
    echo json_encode(['error' => $output]);
    exit;
}

echo json_encode($result);
?>
