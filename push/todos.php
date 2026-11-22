<?php
// todos.php — DevBoard TODO list (the "todos" sheet). See gtodos.py for the actions.
//
// POST params: id (sheet ID), action (add | update | order | status), data (JSON for that action)
// Response: the script's result plus "todos" — the refreshed list (sheets/{id}/todos.json).

require_once __DIR__ . '/gbGate.php'; gbGate(['devboard']);   // GameBoard account: signed in, the sheet's owner, the app unlocked
error_reporting(0);
header('Content-Type: application/json');
require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/refreshJson.php';

$sheetId = trim($_POST['id'] ?? '');
$action  = trim($_POST['action'] ?? '');
$data    = json_decode($_POST['data'] ?? '{}', true) ?: [];
if (!preg_match('/^[A-Za-z0-9_-]{1,128}$/', $sheetId)) { echo json_encode(['error' => 'Invalid sheet id']); exit; }
if (!in_array($action, ['add', 'sync', 'update', 'order', 'status'], true)) { echo json_encode(['error' => 'Invalid action']); exit; }

$data['action'] = $action;
$python = $_ENV['PYTHON'] ?? 'python3';
$cmd    = escapeshellarg($python) . ' ' . escapeshellarg(__DIR__ . '/gtodos.py') . ' '
        . escapeshellarg($sheetId . '|' . base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE))) . ' 2>&1';
$raw    = trim((string) shell_exec($cmd));
$result = null;
foreach (array_reverse(preg_split('/\R/', $raw)) as $line) {   // the answer is the last JSON line
    $line = trim($line);
    if ($line !== '' && $line[0] === '{' && is_array($try = json_decode($line, true))) { $result = $try; break; }
}
if (!is_array($result)) {
    echo json_encode(['error' => 'The todos script failed: ' . ($raw !== '' ? mb_substr($raw, -300) : 'no output')]);
    exit;
}

if (!empty($result['ok'])) {
    refreshJson($python, $sheetId, 'todos');
    $file = dirname(__DIR__) . '/sheets/' . $sheetId . '/todos.json';
    $result['todos'] = file_exists($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];
}
echo json_encode($result);
