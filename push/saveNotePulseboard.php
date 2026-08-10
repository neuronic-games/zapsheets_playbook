<?php
/**
 * saveNotePulseboard.php — save a note for one machine.
 *
 * URL:  /{sheet_id}/pulseboard/note  (routed here by .htaccess)
 * POST: tab, exhibit, notes
 */
error_reporting(0); ini_set('display_errors', '0');
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../dotEnv.php';

$_rp = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$_bp = rtrim($_ENV['BASE_PATH'] ?? '', '/');
if ($_bp !== '' && str_starts_with($_rp, $_bp)) {
    $_rp = substr($_rp, strlen($_bp)) ?: '/';
}
preg_match('#^/([A-Za-z0-9_\-]+)/pulseboard/note#', $_rp, $_m);
$sheet_id = $_m[1] ?? '';

if (!$sheet_id) { echo json_encode(['error' => 'Missing sheet ID']); exit; }

$exhibit = trim($_POST['exhibit'] ?? '');
$tab     = trim($_POST['tab']     ?? '');
$notes   = $_POST['notes'] ?? '';

if (!$exhibit) { echo json_encode(['error' => 'Missing exhibit']); exit; }
if (!$tab)     { echo json_encode(['error' => 'Missing tab']);     exit; }

$payload = json_encode([
    'tab'     => $tab,
    'exhibit' => $exhibit,
    'notes'   => $notes,
], JSON_UNESCAPED_UNICODE);

$py  = $_ENV['PYTHON'] ?? 'python3';
$arg = $sheet_id . '|' . base64_encode($payload);
$cmd = escapeshellarg($py) . ' '
     . escapeshellarg(__DIR__ . '/gsavenotepulseboard.py') . ' '
     . escapeshellarg($arg) . ' 2>&1';

$out = trim((string) shell_exec($cmd));
if ($out === '') { echo json_encode(['error' => 'No response from script']); exit; }

$res = json_decode($out, true);
echo json_encode($res ?? ['error' => $out]);
