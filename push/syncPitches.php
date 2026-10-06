<?php
/**
 * syncPitches.php — re-sync pitches (+ games & people) from Google Sheets
 * to the local JSON cache, then return ok with a detailed steps log.
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

// refreshJsonStep: like refreshJson() but returns a status/message pair for logging
function refreshJsonStep($pythonPath, $sheetId, $tabName) {
    $script = __DIR__ . '/gread.py';
    $arg    = $sheetId . 'sheetname' . $tabName;
    $cmd    = escapeshellarg($pythonPath) . ' '
            . escapeshellarg($script) . ' '
            . escapeshellarg($arg) . ' 2>&1';
    $out    = trim((string) shell_exec($cmd));
    if ($out === '') {
        return ['status' => 'error', 'msg' => $tabName . ': no response from script'];
    }
    $decoded = json_decode($out, true);
    if ($decoded === null) {
        // Not valid JSON — treat first 120 chars as error text
        return ['status' => 'error', 'msg' => $tabName . ': ' . substr($out, 0, 120)];
    }
    if (isset($decoded['error'])) {
        return ['status' => 'error', 'msg' => $tabName . ': ' . $decoded['error']];
    }
    // Write the cache file
    $dir = dirname(__DIR__) . '/sheets/' . $sheetId;
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    file_put_contents($dir . '/' . strtolower($tabName) . '.json', $out);
    $rows = is_array($decoded) ? count($decoded) : '?';
    return ['status' => 'ok', 'msg' => $tabName . ' — ' . $rows . ' rows'];
}

$steps = [];

// Core tabs
foreach (['pitches', 'games', 'people'] as $tab) {
    $steps[] = refreshJsonStep($pythonPath, $sheetId, $tab);
}

// Discover all game-specific tabs from the live sheet (tabs starting with '[')
$steps[] = ['status' => 'info', 'msg' => 'Fetching live tab list from Google Sheets…'];
$listCmd = escapeshellarg($pythonPath) . ' '
         . escapeshellarg(__DIR__ . '/glisttabs.py') . ' '
         . escapeshellarg($sheetId . '|[') . ' 2>&1';
$listOut  = trim((string) shell_exec($listCmd));
$gameTabs = [];

if ($listOut) {
    $listResult = json_decode($listOut, true);
    if (!empty($listResult['tabs'])) {
        $gameTabs = $listResult['tabs'];
        $steps[]  = ['status' => 'info', 'msg' => count($gameTabs) . ' game tab(s) found'];
        foreach ($gameTabs as $tabName) {
            $steps[] = refreshJsonStep($pythonPath, $sheetId, $tabName);
        }
    } elseif (isset($listResult['error'])) {
        $steps[] = ['status' => 'error', 'msg' => 'Tab list error: ' . $listResult['error']];
    } else {
        $steps[] = ['status' => 'skip', 'msg' => 'No game tabs found'];
    }
} else {
    $steps[] = ['status' => 'error', 'msg' => 'glisttabs.py returned no output'];
}

$hasError = !empty(array_filter($steps, fn($s) => $s['status'] === 'error'));

echo json_encode([
    'ok'        => !$hasError,
    'game_tabs' => $gameTabs,
    'steps'     => $steps,
]);
?>
