<?php
// setDevNote.php — mark a DevBoard TODO item done / not done.
// Sets the note on the row's Solutions / Thoughts cell ("TODO" or "DONE"), then refreshes the JSON cache.
//
// POST params: id, game, row (sheet row from the cache, optional), obs, sol, note ("TODO" | "DONE" | "")

error_reporting(0);
header('Content-Type: application/json');
require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/refreshJson.php';

$sheetId = trim($_POST['id']   ?? '');
$game    = trim($_POST['game'] ?? '');
$note    = strtoupper(trim($_POST['note'] ?? ''));
if (!$sheetId || !$game) { echo json_encode(['error' => 'Missing id or game']); exit; }
if (!in_array($note, ['TODO', 'DONE', ''], true)) { echo json_encode(['error' => 'Invalid note']); exit; }

$tab     = '[' . $game . '] dev';
$payload = ['tab' => $tab, 'row' => (int) ($_POST['row'] ?? 0), 'obs' => $_POST['obs'] ?? '', 'sol' => $_POST['sol'] ?? '', 'note' => $note];
$python  = $_ENV['PYTHON'] ?? 'python3';
$cmd     = escapeshellarg($python) . ' ' . escapeshellarg(__DIR__ . '/gsetdevnote.py') . ' '
         . escapeshellarg($sheetId . '|' . base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE))) . ' 2>&1';
$result  = json_decode(trim((string) shell_exec($cmd)), true);
if (!is_array($result)) { echo json_encode(['error' => 'No response from the sheet script']); exit; }
if (!empty($result['ok'])) refreshJson($python, $sheetId, $tab);
echo json_encode($result);
