<?php
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require dirname(__DIR__) . '/dotEnv.php';
require_once __DIR__ . '/refreshJson.php';

$sheetId = trim($_POST['id']       ?? '');
$oldName = trim($_POST['old_name'] ?? '');
$newName = trim($_POST['new_name'] ?? '');

if (!$sheetId || !$oldName || !$newName) {
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

$pythonPath = $_ENV['PYTHON'] ?? 'python3';
$encoded    = base64_encode(json_encode(['old_name' => $oldName, 'new_name' => $newName], JSON_UNESCAPED_UNICODE));
$arg        = $sheetId . '|' . $encoded;

$cmd = escapeshellarg($pythonPath) . ' '
     . escapeshellarg(__DIR__ . '/grename_designer.py') . ' '
     . escapeshellarg($arg) . ' 2>&1';

$output = trim((string) shell_exec($cmd));

if ($output === '') {
    echo json_encode(['error' => 'No response from Python script']);
    exit;
}

$result = json_decode($output, true);
if ($result !== null && !empty($result['ok'])) {
    refreshJson($pythonPath, $sheetId, 'games');

    // Update designer name in all cached game-*-en.json files for this sheet
    $sheetsDir    = dirname(__DIR__) . '/sheets/' . $sheetId;
    $gameJsonFiles = glob($sheetsDir . '/game-*-en.json') ?: [];
    $filesUpdated  = 0;
    foreach ($gameJsonFiles as $gameFile) {
        $raw  = file_get_contents($gameFile);
        $rows = json_decode($raw, true);
        if (!is_array($rows)) continue;
        $changed = false;
        foreach ($rows as &$row) {
            if (isset($row['Name'], $row['Value']) &&
                $row['Name'] === 'Designer' &&
                $row['Value'] === $oldName) {
                $row['Value'] = $newName;
                $changed = true;
            }
        }
        unset($row);
        if ($changed) {
            file_put_contents($gameFile, json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $filesUpdated++;
        }
    }
    if ($filesUpdated > 0) $result['game_jsons_updated'] = $filesUpdated;
}
echo $result !== null ? json_encode($result) : json_encode(['error' => $output]);
?>
