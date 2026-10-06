<?php
/**
 * syncGame.php — re-sync the [GameName]* tabs (and pitches) for a single game.
 *
 * POST: id={sheet_id}  game={game_name}
 */
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/refreshJson.php';

$sheetId    = trim($_POST['id']   ?? '');
$gameName   = trim($_POST['game'] ?? '');
$pythonPath = $_ENV['PYTHON'] ?? 'python3';

if (!$sheetId || !$gameName) {
    echo json_encode(['error' => 'Missing sheet ID or game name']);
    exit;
}

// Shared helper: refresh one tab and return a step log entry
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
        return ['status' => 'error', 'msg' => $tabName . ': ' . substr($out, 0, 120)];
    }
    if (isset($decoded['error'])) {
        return ['status' => 'error', 'msg' => $tabName . ': ' . $decoded['error']];
    }
    $dir = dirname(__DIR__) . '/sheets/' . $sheetId;
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    file_put_contents($dir . '/' . strtolower($tabName) . '.json', $out);
    $rows = is_array($decoded) ? count($decoded) : '?';
    return ['status' => 'ok', 'msg' => $tabName . ' — ' . $rows . ' rows'];
}

$steps = [];

// Always refresh pitches and people
foreach (['pitches', 'people'] as $tab) {
    $steps[] = refreshJsonStep($pythonPath, $sheetId, $tab);
}

// Discover the game's bracket tabs from the live sheet: [GameName], [GameName] dev, etc.
$prefix  = '[' . $gameName . ']';
$steps[] = ['status' => 'info', 'msg' => 'Fetching tabs for ' . $gameName . '…'];

$listCmd = escapeshellarg($pythonPath) . ' '
         . escapeshellarg(__DIR__ . '/glisttabs.py') . ' '
         . escapeshellarg($sheetId . '|' . $prefix) . ' 2>&1';
$listOut  = trim((string) shell_exec($listCmd));
$gameTabs = [];

if ($listOut) {
    $listResult = json_decode($listOut, true);
    if (!empty($listResult['tabs'])) {
        $gameTabs = $listResult['tabs'];
        $steps[]  = ['status' => 'info', 'msg' => count($gameTabs) . ' tab(s) found'];
        foreach ($gameTabs as $tabName) {
            $steps[] = refreshJsonStep($pythonPath, $sheetId, $tabName);
        }
    } elseif (isset($listResult['error'])) {
        $steps[] = ['status' => 'error', 'msg' => 'Tab list error: ' . $listResult['error']];
    } else {
        $steps[] = ['status' => 'skip', 'msg' => 'No tabs found for ' . $gameName];
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
