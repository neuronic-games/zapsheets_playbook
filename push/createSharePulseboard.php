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
require_once __DIR__ . '/pulseShareLib.php';

$sheet_id = trim($_POST['sheet_id']  ?? '');
$tab      = trim($_POST['tab']       ?? '');
$tab_safe = trim($_POST['tab_safe']  ?? '');

if (!$sheet_id) { echo json_encode(['ok' => false, 'error' => 'Missing sheet_id']); exit; }
if (!$tab)      { echo json_encode(['ok' => false, 'error' => 'Missing tab']);      exit; }

$share = pulseboard_share_token($sheet_id, $tab, $tab_safe);
if (!$share) { echo json_encode(['ok' => false, 'error' => 'Could not create share']); exit; }

$base = rtrim($_ENV['BASE_PATH'] ?? '', '/');
echo json_encode(['ok' => true, 'token' => $share['token'], 'url' => $base . '/share/' . $share['token']]);
