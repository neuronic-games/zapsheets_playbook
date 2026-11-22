<?php
// setDevNote.php — mark a DevBoard TODO item done / not done.
// Sets the note on the row's Solutions / Thoughts cell ("TODO" or "DONE"), then refreshes the JSON cache.
//
// POST params: id, game, todo_id (finds the cell by its "TODO:<id>" note), row (optional), obs, sol, note ("TODO:<id>" | "TODO" | "")

require_once __DIR__ . '/gbGate.php'; gbGate(['devboard']);   // GameBoard account: signed in, the sheet's owner, the app unlocked
error_reporting(0);
header('Content-Type: application/json');
require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/refreshJson.php';

$sheetId = trim($_POST['id']   ?? '');
$game    = trim($_POST['game'] ?? '');
$note    = strtoupper(trim($_POST['note'] ?? ''));
if (!$sheetId || !$game) { echo json_encode(['error' => 'Missing id or game']); exit; }
if ($note !== '' && !preg_match('/^TODO(:[A-Z0-9]{1,20})?$/', $note)) { echo json_encode(['error' => 'Invalid note']); exit; }

$tab     = '[' . $game . '] dev';
$payload = ['tab' => $tab, 'todo_id' => strtoupper(trim($_POST['todo_id'] ?? '')), 'row' => (int) ($_POST['row'] ?? 0), 'obs' => $_POST['obs'] ?? '', 'sol' => $_POST['sol'] ?? '', 'note' => $note];
$python  = $_ENV['PYTHON'] ?? 'python3';
$cmd     = escapeshellarg($python) . ' ' . escapeshellarg(__DIR__ . '/gsetdevnote.py') . ' '
         . escapeshellarg($sheetId . '|' . base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE))) . ' 2>&1';
$result  = json_decode(trim((string) shell_exec($cmd)), true);
if (!is_array($result)) { echo json_encode(['error' => 'No response from the sheet script']); exit; }
if (!empty($result['ok'])) refreshJson($python, $sheetId, $tab);
echo json_encode($result);
