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

// Also refresh all game-specific tabs from the live sheet (tabs starting with '[')
$listCmd = escapeshellarg($pythonPath) . ' '
         . escapeshellarg(__DIR__ . '/glisttabs.py') . ' '
         . escapeshellarg($sheetId . '|[') . ' 2>/dev/null';
$listOut  = trim((string) shell_exec($listCmd));
$gameTabs = [];
if ($listOut) {
    $listResult = json_decode($listOut, true);
    if (!empty($listResult['tabs'])) {
        foreach ($listResult['tabs'] as $tabName) {
            $gameTabs[] = $tabName;
            refreshJson($pythonPath, $sheetId, $tabName);
        }
    }
}

echo json_encode(['ok' => true, 'game_tabs' => $gameTabs]);
?>
