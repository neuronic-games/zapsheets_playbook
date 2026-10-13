<?php
/**
 * reloadTabPulseboard.php — re-read one PulseBoard tab from Google Sheets into
 * its JSON cache (the circular-arrow button on a tab).
 *
 * POST either:
 *   id + tab   — from the dashboard
 *   token      — from a client share page (only reloads that share's tab)
 * Returns the gfetchpulseboard.py result: { ok, count, logs } or { error }.
 */
error_reporting(0); ini_set('display_errors', '0');
header('Content-Type: application/json');
require_once __DIR__ . '/../dotEnv.php';

$sheet_id = trim($_POST['id']  ?? '');
$tab      = trim($_POST['tab'] ?? '');
$token    = trim($_POST['token'] ?? '');

if ($token !== '') {
    if (!preg_match('/^[A-Za-z0-9]+$/', $token)) { echo json_encode(['error' => 'Bad link']); exit; }
    $file = __DIR__ . '/../shares/pulse-group-readonly/' . $token . '.json';
    $share = file_exists($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];
    $sheet_id = $share['sheet_id'] ?? '';
    $tab      = $share['tab']      ?? '';
    if (!$sheet_id || $tab === '') { echo json_encode(['error' => 'Share link not found']); exit; }
}

if (!$sheet_id || !preg_match('/^[A-Za-z0-9_\-]+$/', $sheet_id)) { echo json_encode(['error' => 'Missing sheet ID']); exit; }
if ($tab === '') { echo json_encode(['error' => 'Missing tab']); exit; }

$py  = $_ENV['PYTHON'] ?? 'python3';
$cmd = escapeshellarg($py) . ' '
     . escapeshellarg(__DIR__ . '/gfetchpulseboard.py') . ' '
     . escapeshellarg($sheet_id . '|' . $tab) . ' 2>&1';

$out = trim((string) shell_exec($cmd));
if ($out === '') { echo json_encode(['error' => 'No response from script']); exit; }
$res = json_decode($out, true);
echo json_encode($res ?? ['error' => $out]);
