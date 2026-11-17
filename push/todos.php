<?php
// todos.php — DevBoard TODO list (the "todos" sheet). See gtodos.py for the actions.
//
// POST params: id (sheet ID), action (add | update | order | status), data (JSON for that action)
// Response: the script's result plus "todos" — the refreshed list (sheets/{id}/todos.json).

error_reporting(0);
header('Content-Type: application/json');
require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/refreshJson.php';

$sheetId = trim($_POST['id'] ?? '');
$action  = trim($_POST['action'] ?? '');
$data    = json_decode($_POST['data'] ?? '{}', true) ?: [];
if (!preg_match('/^[A-Za-z0-9_-]{1,128}$/', $sheetId)) { echo json_encode(['error' => 'Invalid sheet id']); exit; }
if (!in_array($action, ['add', 'update', 'order', 'status'], true)) { echo json_encode(['error' => 'Invalid action']); exit; }

$data['action'] = $action;
$python = $_ENV['PYTHON'] ?? 'python3';
$cmd    = escapeshellarg($python) . ' ' . escapeshellarg(__DIR__ . '/gtodos.py') . ' '
        . escapeshellarg($sheetId . '|' . base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE))) . ' 2>&1';
$result = json_decode(trim((string) shell_exec($cmd)), true);
if (!is_array($result)) { echo json_encode(['error' => 'No response from the todos script']); exit; }

if (!empty($result['ok'])) {
    refreshJson($python, $sheetId, 'todos');
    $file = dirname(__DIR__) . '/sheets/' . $sheetId . '/todos.json';
    $result['todos'] = file_exists($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];
}
echo json_encode($result);
