<?php
/**
 * setupGameboard.php — one-call setup for a GameBoard sheet.
 *
 * Works for:
 *   - A new blank sheet  → creates Games (and People) tabs with headers
 *   - An existing PitchBoard sheet → tabs already exist, just refreshes the cache
 *
 * POST / GET param:  id — Google Spreadsheet ID (already shared with service account)
 *
 * Returns JSON: { ok, title, tabs, json_refreshed } or { error }
 */
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/refreshJson.php';

$sheetId = trim($_POST['id'] ?? $_GET['id'] ?? '');
if (!$sheetId) {
    parse_str($_SERVER['QUERY_STRING'] ?? '', $qs);
    $sheetId = trim($qs['id'] ?? '');
}
if (!$sheetId) {
    echo json_encode(['error' => 'Missing sheet ID — pass id= as a GET or POST parameter']);
    exit;
}

$pythonPath = $_ENV['PYTHON'] ?? 'python3';
$mode       = trim($_POST['mode'] ?? $_GET['mode'] ?? 'setup');

// ── mode=game: publish a single game's tab cache ──────────────────────────────
if ($mode === 'game') {
    $gameName = trim($_POST['name'] ?? $_GET['name'] ?? '');
    if (!$gameName) {
        echo json_encode(['error' => 'Missing game name']);
        exit;
    }
    $pubCmd = escapeshellarg($pythonPath) . ' '
            . escapeshellarg(__DIR__ . '/gpublishgame.py') . ' '
            . escapeshellarg($sheetId . '|' . $gameName) . ' 2>&1';
    $pubOut = trim((string) shell_exec($pubCmd));
    $pubResult = $pubOut ? json_decode($pubOut, true) : null;
    if ($pubResult) {
        echo json_encode($pubResult);
    } elseif ($pubOut) {
        echo json_encode(['error' => 'script_error', 'detail' => substr($pubOut, 0, 500)]);
    } else {
        echo json_encode(['error' => 'No response']);
    }
    exit;
}

$result     = ['ok' => true];

// ── Step 1: Ensure required tabs exist (idempotent) ───────────────────────────
// ginitsheet.py creates Pitches, Games, People, Settings tabs if not present,
// and skips any that already exist. Safe to run on existing PitchBoard sheets.
$cmd    = escapeshellarg($pythonPath) . ' '
        . escapeshellarg(__DIR__ . '/ginitsheet.py') . ' '
        . escapeshellarg($sheetId) . ' 2>&1';
$output = trim((string) shell_exec($cmd));

if ($output === '') {
    echo json_encode(['error' => 'No response from setup script — check the service account has Editor access to the sheet.']);
    exit;
}

$pyResult = json_decode($output, true);
if ($pyResult === null) {
    echo json_encode(['error' => $output]);
    exit;
}

if (isset($pyResult['error'])) {
    echo json_encode(['error' => $pyResult['error']]);
    exit;
}

// Normalize tabs: "already exists" is not an error for GameBoard setup —
// it just means this is an existing sheet (e.g. a PitchBoard sheet).
$rawTabs     = $pyResult['tabs'] ?? [];
$normalTabs  = [];
$tabsHadRealError = false;
foreach ($rawTabs as $tabName => $status) {
    if ($status === 'ok' || $status === 'created') {
        $normalTabs[$tabName] = $status;
    } elseif (stripos((string) $status, 'already exists') !== false) {
        $normalTabs[$tabName] = 'ok';  // treat as pre-existing — that's fine
    } else {
        $normalTabs[$tabName] = $status;  // real error, pass through
        $tabsHadRealError = true;
    }
}
$result['tabs']  = $normalTabs;
$result['title'] = $pyResult['title'] ?? '';
// Only fail if there's an actual error beyond "already exists"
if ($tabsHadRealError) {
    $result['ok'] = false;
}

// ── Step 2: Ensure sheet directory exists ─────────────────────────────────────
$sheetDir = realpath(__DIR__ . '/..') . '/sheets/' . $sheetId;
if (!is_dir($sheetDir)) {
    mkdir($sheetDir, 0775, true);
}

// ── Step 3: Refresh JSON caches needed by GameBoard ───────────────────────────
$refreshed = [];
$refreshErrors = [];
foreach (['games', 'people'] as $tab) {
    $err = refreshJson($pythonPath, $sheetId, $tab);
    if ($err === null) {
        $refreshed[] = $tab;
    } else {
        $refreshErrors[$tab] = $err;
    }
}

// ── Step 4: Return game names list for client-side per-game publishing ────────
$gamesJson = $sheetDir . '/games.json';
$gameNames = [];
if (file_exists($gamesJson)) {
    $gamesData = json_decode(file_get_contents($gamesJson), true);
    if (is_array($gamesData)) {
        foreach ($gamesData as $g) {
            $n = trim($g['Name'] ?? '');
            if ($n) $gameNames[] = $n;
        }
    }
}
$result['game_names']    = $gameNames;
$result['json_refreshed'] = $refreshed;
if ($refreshErrors) {
    $result['refresh_errors'] = $refreshErrors;
    $result['ok'] = false;
}

echo json_encode($result);
?>
