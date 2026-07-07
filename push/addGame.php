<?php
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/refreshJson.php';

$sheetId   = trim($_POST['id']        ?? '');
$name      = trim($_POST['name']      ?? '');
$designer1 = trim($_POST['designer1'] ?? '');
$designer2 = trim($_POST['designer2'] ?? '');
$designer3 = trim($_POST['designer3'] ?? '');
$designer4 = trim($_POST['designer4'] ?? '');
$rules     = trim($_POST['rules']     ?? '');
$play      = trim($_POST['play']      ?? '');
$print     = trim($_POST['print']     ?? '');
$sellsheet = trim($_POST['sellsheet'] ?? '');
$view      = trim($_POST['view']      ?? '');
$video     = trim($_POST['video']     ?? '');

if (!$sheetId) {
    echo json_encode(['error' => 'Missing sheet ID']);
    exit;
}

if (!$name) {
    echo json_encode(['error' => 'Game name is required']);
    exit;
}

// Include all column-name variants so gadd.py's header-matching picks the right one
// regardless of whether the sheet uses 'Designer1' vs 'Designer 1', etc.
$data = [
    'Name'              => $name,
    'Designer1'         => $designer1,  'Designer 1'         => $designer1,
    'Designer2'         => $designer2,  'Designer 2'         => $designer2,
    'Designer3'         => $designer3,  'Designer 3'         => $designer3,
    'Designer4'         => $designer4,  'Designer 4'         => $designer4,
    'Rules'             => $rules,      'Rules URL'          => $rules,      'RulesURL'     => $rules,
    'Play'              => $play,       'Play URL'           => $play,       'PlayURL'      => $play,
    'Print'             => $print,      'Print URL'          => $print,      'PrintURL'     => $print,
    'Sellsheet'         => $sellsheet,  'Sellsheet URL'      => $sellsheet,  'SellsheetURL' => $sellsheet,
    'BGG'               => $view,       'View URL'           => $view,
    'BGG / View URL'    => $view,       'ViewURL'            => $view,       'View'         => $view,
    'Video'             => $video,      'Video URL'          => $video,      'VideoURL'     => $video,
];

// Use gadd.py (proven working) with 3-part arg: sheet_id|sheet_name|base64_json
$pythonPath = $_ENV['PYTHON'] ?? 'python3';
$encoded    = base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE));
$arg        = $sheetId . '|games|' . $encoded;

$cmd = escapeshellarg($pythonPath) . ' '
     . escapeshellarg(__DIR__ . '/gadd.py') . ' '
     . escapeshellarg($arg) . ' 2>&1';

$output = trim((string) shell_exec($cmd));

if ($output === '') {
    echo json_encode(['error' => 'No response from Python script']);
    exit;
}

$result = json_decode($output, true);
if ($result !== null && !empty($result['ok'])) {
    refreshJson($pythonPath, $sheetId, 'games');
}
echo $result !== null ? json_encode($result) : json_encode(['error' => $output]);
?>
