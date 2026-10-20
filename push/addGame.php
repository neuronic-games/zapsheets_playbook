<?php
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/refreshJson.php';

$sheetId       = trim($_POST['id']             ?? '');
$name          = trim($_POST['name']           ?? '');
$tagline       = trim($_POST['tagline']        ?? '');
$description   = trim($_POST['description']    ?? '');
$status        = trim($_POST['status']         ?? '');
$availability  = trim($_POST['availability']   ?? '');
$count         = trim($_POST['count']          ?? '');
$duration      = trim($_POST['duration']       ?? '');
$designer1     = trim($_POST['designer1']      ?? '');
$designer2     = trim($_POST['designer2']      ?? '');
$designer3     = trim($_POST['designer3']      ?? '');
$designer4     = trim($_POST['designer4']      ?? '');
$dateStarted   = trim($_POST['date_started']   ?? '');
$dateSigned    = trim($_POST['date_signed']    ?? '');
$datePublished = trim($_POST['date_published'] ?? '');
$coverRaw      = trim($_POST['cover_url']      ?? '');
$cover         = $coverRaw ? '=IMAGE("' . $coverRaw . '")' : '';
$imageRaw      = trim($_POST['image']          ?? '');   // already wrapped by JS or empty
$image         = $imageRaw;
$video         = trim($_POST['video']          ?? '');
$view          = trim($_POST['view']           ?? '');
$sellsheet     = trim($_POST['sellsheet']      ?? '');
$rules         = trim($_POST['rules']          ?? '');
$play          = trim($_POST['play']           ?? '');
$print         = trim($_POST['print']          ?? '');

if (!$sheetId) { echo json_encode(['error' => 'Missing sheet ID']); exit; }
if (!$name)    { echo json_encode(['error' => 'Game name is required']); exit; }

$data = compact(
    'name','tagline','description','status','availability',
    'count','duration','designer1','designer2','designer3','designer4',
    'dateStarted','dateSigned','datePublished',
    'cover','image','video','view','sellsheet','rules','play','print'
);

$pythonPath = $_ENV['PYTHON'] ?? 'python3';
$encoded    = base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE));
$arg        = $sheetId . '|' . $encoded;

$errFile = sys_get_temp_dir() . '/gaddgame_err_' . $sheetId . '.txt';
$cmd = escapeshellarg($pythonPath) . ' '
     . escapeshellarg(__DIR__ . '/gaddgame.py') . ' '
     . escapeshellarg($arg) . ' 2>' . escapeshellarg($errFile);

$output = trim((string) shell_exec($cmd));
$stderr = file_exists($errFile) ? trim(file_get_contents($errFile)) : '';

if ($output === '') {
    echo json_encode(['error' => 'No response from script', 'stderr' => $stderr]);
    exit;
}

$result = json_decode($output, true);
if ($result !== null && !empty($result['ok'])) {
    refreshJson($pythonPath, $sheetId, 'games');
}

echo $result !== null ? json_encode($result) : json_encode(['error' => $output, 'stderr' => $stderr]);
?>
