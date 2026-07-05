<?php
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';

$sheetId   = trim($_POST['id']        ?? '');
$origName  = trim($_POST['orig_name'] ?? '');
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

if (!$origName) {
    echo json_encode(['error' => 'Missing original game name']);
    exit;
}

$data = [
    'orig_name' => $origName,
    'name'      => $name      ?: $origName,
    'designer1' => $designer1,
    'designer2' => $designer2,
    'designer3' => $designer3,
    'designer4' => $designer4,
    'rules'     => $rules,
    'play'      => $play,
    'print'     => $print,
    'sellsheet' => $sellsheet,
    'view'      => $view,
    'video'     => $video,
];

$pythonPath = $_ENV['PYTHON'] ?? 'python3';
$encoded    = base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE));
$arg        = $sheetId . '|' . $encoded;

$cmd = escapeshellarg($pythonPath) . ' '
     . escapeshellarg(__DIR__ . '/gupdategame.py') . ' '
     . escapeshellarg($arg) . ' 2>&1';

$output = trim((string) shell_exec($cmd));

if ($output === '') {
    echo json_encode(['error' => 'No response from Python script']);
    exit;
}

$result = json_decode($output, true);
echo $result !== null ? json_encode($result) : json_encode(['error' => $output]);
?>
