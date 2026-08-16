<?php
/**
 * createSharePulseboard.php — generate a unique share token for one PulseBoard tab.
 *
 * POST: sheet_id, tab (display name), tab_safe (filename-safe name)
 * Returns: { ok: true, token: "...", url: "..." }
 */
error_reporting(0); ini_set('display_errors', '0');
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../dotEnv.php';

$sheet_id = trim($_POST['sheet_id']  ?? '');
$tab      = trim($_POST['tab']       ?? '');
$tab_safe = trim($_POST['tab_safe']  ?? '');

if (!$sheet_id) { echo json_encode(['ok' => false, 'error' => 'Missing sheet_id']); exit; }
if (!$tab)      { echo json_encode(['ok' => false, 'error' => 'Missing tab']);      exit; }

$share_dir = __DIR__ . '/../shares/pulse-group-readonly';
if (!is_dir($share_dir)) { mkdir($share_dir, 0755, true); }

// Return existing token if this sheet+tab already has one
foreach (glob($share_dir . '/*.json') as $file) {
    $data = json_decode(file_get_contents($file), true);
    if (($data['sheet_id'] ?? '') === $sheet_id && ($data['tab'] ?? '') === $tab) {
        $token = basename($file, '.json');
        $base  = rtrim($_ENV['BASE_PATH'] ?? '', '/');
        echo json_encode(['ok' => true, 'token' => $token, 'url' => $base . '/share/' . $token]);
        exit;
    }
}

// Generate new token
$token   = bin2hex(random_bytes(16));
$payload = json_encode([
    'sheet_id' => $sheet_id,
    'tab'      => $tab,
    'tab_safe' => $tab_safe,
    'created'  => date('c'),
], JSON_PRETTY_PRINT);

file_put_contents($share_dir . '/' . $token . '.json', $payload);

$base = rtrim($_ENV['BASE_PATH'] ?? '', '/');
echo json_encode(['ok' => true, 'token' => $token, 'url' => $base . '/share/' . $token]);
