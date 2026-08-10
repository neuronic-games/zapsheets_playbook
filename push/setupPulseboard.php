<?php
/**
 * setupPulseboard.php — initialise a PulseBoard spreadsheet.
 * POST: id = Google Sheet ID
 * Returns JSON from ginitpulseboard.py
 */
error_reporting(0); ini_set('display_errors', '0');
header('Content-Type: application/json');
require_once __DIR__ . '/../dotEnv.php';

$id = trim($_POST['id'] ?? '');
if (!$id) { echo json_encode(['error' => 'Missing sheet ID']); exit; }

$py  = $_ENV['PYTHON'] ?? 'python3';
$cmd = escapeshellarg($py) . ' '
     . escapeshellarg(__DIR__ . '/ginitpulseboard.py') . ' '
     . escapeshellarg($id) . ' 2>&1';

$out = trim((string) shell_exec($cmd));
if ($out === '') { echo json_encode(['error' => 'No response from script']); exit; }

$res = json_decode($out, true);
echo json_encode($res ?? ['error' => $out]);
