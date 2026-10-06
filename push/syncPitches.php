<?php
/**
 * syncPitches.php — re-sync pitches (+ games & people) from Google Sheets
 * to the local JSON cache, then return ok.
 *
 * POST: id={sheet_id}
 */
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/refreshJson.php';

$sheetId    = trim($_POST['id'] ?? '');
$pythonPath = $_ENV['PYTHON'] ?? 'python3';

if (!$sheetId) {
    echo json_encode(['error' => 'Missing sheet ID']);
    exit;
}

foreach (['pitches', 'games', 'people'] as $tab) {
    refreshJson($pythonPath, $sheetId, $tab);
}

// Also refresh any game-specific tabs: files named like [gamename].json or [gamename] dev.json
$sheetsDir = dirname(__DIR__) . '/sheets/' . $sheetId;
$gameTabs  = [];
if (is_dir($sheetsDir)) {
    foreach (scandir($sheetsDir) as $f) {
        if ($f[0] === '[' && substr($f, -5) === '.json') {
            $tabName = substr($f, 0, -5); // strip .json → lowercase tab name
            $gameTabs[] = $tabName;
            refreshJson($pythonPath, $sheetId, $tabName);
        }
    }
}

echo json_encode(['ok' => true, 'game_tabs' => $gameTabs]);
?>
