<?php
/**
 * tmSetName.php — set a collaborator's display name, keeping names unique per sheet
 *
 * POST params:
 *   id    — sheet ID (accounts.json lives inside sheets/{id}/)
 *   email — account email (must already exist in accounts.json)
 *   name  — requested display name
 *
 * If another person already uses the name (case-insensitive), a number suffix
 * is added: "John Doe" → "John Doe 2" → "John Doe 3" …
 * "Another person" = a Name on the Staff tab with a different Email, or another
 * account's saved name.
 *
 * The final name is written to the Staff tab (Name/Email row, matched by email)
 * and saved on the account in accounts.json.
 *
 * Returns JSON:
 *   { ok: true, name: string, adjusted: bool }
 *   { error: string }
 */
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/refreshJson.php';

$sheetId = trim($_POST['id']    ?? '');
$email   = strtolower(trim($_POST['email'] ?? ''));
$name    = preg_replace('/\s+/', ' ', trim($_POST['name'] ?? ''));

if (!$sheetId || !$email) { echo json_encode(['error' => 'Missing sheet ID or email.']); exit; }
if ($name === '')         { echo json_encode(['error' => 'Please enter your name.']); exit; }
if (mb_strlen($name) > 80) { echo json_encode(['error' => 'Name is too long.']); exit; }
if (!preg_match('/^[A-Za-z0-9_\-]+$/', $sheetId)) { echo json_encode(['error' => 'Bad sheet ID.']); exit; }

$sheetDir     = dirname(__DIR__) . '/sheets/' . $sheetId;
$accountsFile = $sheetDir . '/accounts.json';
if (!file_exists($accountsFile)) { echo json_encode(['error' => 'Account not found.']); exit; }

// Lock so two people saving the same name at once can't both get it
$fh = fopen($accountsFile, 'c+');
if (!$fh || !flock($fh, LOCK_EX)) { echo json_encode(['error' => 'Could not lock accounts file.']); exit; }
$accounts = json_decode(stream_get_contents($fh), true) ?: [];

if (!isset($accounts[$email])) {
    flock($fh, LOCK_UN); fclose($fh);
    echo json_encode(['error' => 'Account not found.']); exit;
}

// Collect names already used by other people
$norm  = function($s) { return mb_strtolower(preg_replace('/\s+/', ' ', trim(ltrim((string)$s, "'")))); };
$taken = [];
foreach ($accounts as $acctEmail => $acct) {
    if ($acctEmail !== $email && !empty($acct['name'])) $taken[$norm($acct['name'])] = true;
}
$staffFile = $sheetDir . '/staff.json';
if (file_exists($staffFile)) {
    foreach ((json_decode(file_get_contents($staffFile), true) ?: []) as $r) {
        $rEmail = strtolower(trim(ltrim($r['Email'] ?? '', "'")));
        $rName  = trim(ltrim($r['Name'] ?? '', "'"));
        if ($rName !== '' && $rEmail !== $email) $taken[$norm($rName)] = true;
    }
}

// Pick the first free name: "Name", "Name 2", "Name 3", …
$final = $name;
for ($n = 2; isset($taken[$norm($final)]); $n++) { $final = $name . ' ' . $n; }

// Write Name/Email to the Staff tab (source of truth for who's who)
$py  = $_ENV['PYTHON'] ?? 'python3';
$enc = base64_encode(json_encode(['name' => $final, 'email' => $email], JSON_UNESCAPED_UNICODE));
$out = trim((string) shell_exec(escapeshellarg($py) . ' ' . escapeshellarg(__DIR__ . '/gtmStaffUpsert.py') . ' '
        . escapeshellarg($sheetId . '|' . $enc) . ' 2>&1'));
$res = $out ? json_decode($out, true) : null;
if (empty($res['ok'])) {
    flock($fh, LOCK_UN); fclose($fh);
    echo json_encode(['error' => 'Could not save to the Staff tab: ' . (($res['error'] ?? '') ?: ($out ?: 'no response'))]);
    exit;
}

$accounts[$email]['name'] = $final;
ftruncate($fh, 0); rewind($fh);
fwrite($fh, json_encode($accounts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
fflush($fh); flock($fh, LOCK_UN); fclose($fh);

refreshJson($py, $sheetId, 'Staff');
echo json_encode(['ok' => true, 'name' => $final, 'adjusted' => $final !== $name]);
?>
