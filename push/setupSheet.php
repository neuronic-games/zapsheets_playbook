<?php
// setupSheet.php — full one-call setup for a new PitchBoard spreadsheet.
//
// Steps:
//   1. Initialise Google Sheet tabs (Pitches, Games, People, Settings) with headers
//   2. Deploy dashboard source files from source/ to sheets/{id}/
//   3. Refresh all four local JSON caches
//
// GET or POST param:  id — Google Spreadsheet ID (already shared with the service account)

error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/refreshJson.php';

$sheetId = trim($_POST['id'] ?? $_GET['id'] ?? '');

// Fallback: parse raw query string (handles PATH_INFO edge cases)
if (!$sheetId) {
    parse_str($_SERVER['QUERY_STRING'] ?? '', $qs);
    $sheetId = trim($qs['id'] ?? '');
}

if (!$sheetId) {
    echo json_encode(['error' => 'Missing sheet ID — pass id= as a GET or POST parameter']);
    exit;
}

$pythonPath = $_ENV['PYTHON'] ?? 'python3';
$result     = ['ok' => true];

// ── Step 1: Initialise Google Sheet tabs ──────────────────────────────────────
$cmd    = escapeshellarg($pythonPath) . ' '
        . escapeshellarg(__DIR__ . '/ginitsheet.py') . ' '
        . escapeshellarg($sheetId) . ' 2>&1';
$output = trim((string) shell_exec($cmd));

if ($output === '') {
    echo json_encode(['error' => 'No response from setup script']);
    exit;
}

$pyResult = json_decode($output, true);
if ($pyResult === null) {
    echo json_encode(['error' => $output]);
    exit;
}

// If ginitsheet.py returned a fatal error (e.g. sheet not shared with the
// service account), bail immediately — do NOT create the directory or caches.
if (isset($pyResult['error'])) {
    echo json_encode(['error' => $pyResult['error']]);
    exit;
}

$result['tabs']  = $pyResult['tabs']  ?? [];
$result['title'] = $pyResult['title'] ?? '';
if (empty($pyResult['ok'])) {
    $result['ok'] = false;
}

// ── Step 2: Create sheets/{id}/ directory and cache subfolder ─────────────────
// Always run this even if ginitsheet had tab-level errors — the directory and
// JSON cache files must exist before the dashboard can load.
$sheetDir = realpath(__DIR__ . '/..') . '/sheets/' . $sheetId;
if (!is_dir($sheetDir . '/cache')) {
    mkdir($sheetDir . '/cache', 0777, true);
}

// ── Step 3: Refresh JSON caches ───────────────────────────────────────────────
foreach (['pitches', 'games', 'people', 'settings'] as $tab) {
    refreshJson($pythonPath, $sheetId, $tab);
}
$result['json_refreshed'] = ['pitches', 'games', 'people', 'settings'];

echo json_encode($result);
?>
