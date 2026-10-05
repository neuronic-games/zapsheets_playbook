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
$myLogo    = '';

function _extractLogoUrl($raw) {
    $raw = ltrim(trim($raw), "'");
    if (preg_match('/^=IMAGE\("([^"]*)"\)$/i', $raw, $m)) return $m[1];
    return $raw;
}

foreach ($settings as $s) {
    // Format A: {Name: 'Company', Value: 'Acme'} — standard key/value rows
    $n = $s['Name'] ?? $s['name'] ?? '';
    $v = $s['Value'] ?? $s['value'] ?? '';
    if ($n === 'My Name')  { $myName    = $v; continue; }
    if ($n === 'My Email') { $myEmail   = $v; continue; }
    if ($n === 'My Phone') { $myPhone   = $v; continue; }
    if ($n === 'Company')  { $myCompany = ltrim($v, "'"); continue; }
    if ($n === 'Address')  { $myAddress = ltrim($v, "'"); continue; }
    if ($n === 'Logo')     { $myLogo    = _extractLogoUrl($v); continue; }

    // Format B: DevBoard quirky — {"My Name": "<label>", "<actual-name>": "<value>"}
    $label = $s['My Name'] ?? '';
    if ($label === '') continue;
    $keys = array_keys($s);
    $val2 = count($keys) > 1 ? ltrim(trim($s[$keys[1]] ?? ''), "'") : '';
    if ($label === 'My Name')  { $myName    = $val2; }
    if ($label === 'My Email') { $myEmail   = $val2; }
    if ($label === 'My Phone') { $myPhone   = $val2; }
    if ($label === 'Company')  { $myCompany = $val2; }
    if ($label === 'Address')  { $myAddress = $val2; }
    if ($label === 'Logo')     { $myLogo    = _extractLogoUrl($val2); }
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
    'my_logo'    => $myLogo,
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
