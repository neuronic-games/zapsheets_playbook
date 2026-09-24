<?php
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/refreshJson.php';

$sheetId       = trim($_POST['id']             ?? '');
$origName      = trim($_POST['orig_name']      ?? '');
$name          = trim($_POST['name']           ?? '');
$tagline       = trim($_POST['tagline']        ?? '');
$description   = trim($_POST['description']    ?? '');
$status        = trim($_POST['status']         ?? '');
$dateStarted   = trim($_POST['date_started']   ?? '');
$dateSigned    = trim($_POST['date_signed']    ?? '');
$datePublished = trim($_POST['date_published'] ?? '');
$designer1     = trim($_POST['designer1']      ?? '');
$designer2     = trim($_POST['designer2']      ?? '');
$designer3     = trim($_POST['designer3']      ?? '');
$designer4     = trim($_POST['designer4']      ?? '');
$rules         = trim($_POST['rules']          ?? '');
$play          = trim($_POST['play']           ?? '');
$print         = trim($_POST['print']          ?? '');
$sellsheet     = trim($_POST['sellsheet']      ?? '');
$view          = trim($_POST['view']           ?? '');
$video         = trim($_POST['video']          ?? '');
$imageRaw      = trim($_POST['image']          ?? '');
$image         = $imageRaw ? '=IMAGE("' . $imageRaw . '")' : '';

if (!$sheetId) {
    echo json_encode(['error' => 'Missing sheet ID']);
    exit;
}

if (!$origName) {
    echo json_encode(['error' => 'Missing original game name']);
    exit;
}

$data = [
    'orig_name'      => $origName,
    'name'           => $name          ?: $origName,
    'tagline'        => $tagline,
    'description'    => $description,
    'status'         => $status,
    'date_started'   => $dateStarted,
    'date_signed'    => $dateSigned,
    'date_published' => $datePublished,
    'designer1'      => $designer1,
    'designer2'      => $designer2,
    'designer3'      => $designer3,
    'designer4'      => $designer4,
    'rules'          => $rules,
    'play'           => $play,
    'print'          => $print,
    'sellsheet'      => $sellsheet,
    'view'           => $view,
    'video'          => $video,
    'image'          => $image,
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
if ($result !== null && !empty($result['ok'])) {
    refreshJson($pythonPath, $sheetId, 'games');

    // If the game was renamed, also refresh pitches, rename local JSON, and update share files
    $isRename = $name && $origName && $name !== $origName;
    if ($isRename) {
        refreshJson($pythonPath, $sheetId, 'pitches');

        // Rename the per-game JSON file
        $sheetsDir   = dirname(__DIR__) . '/sheets/' . $sheetId;
        $oldSafe     = str_replace(['/', '\\'], '-', $origName);
        $newSafe     = str_replace(['/', '\\'], '-', $name);
        $oldJsonFile = $sheetsDir . '/game-' . $oldSafe . '-en.json';
        $newJsonFile = $sheetsDir . '/game-' . $newSafe . '-en.json';
        if (file_exists($oldJsonFile)) {
            rename($oldJsonFile, $newJsonFile);
            $result['game_json_renamed'] = true;
        }

        // Update any collab share files that reference the old game name
        $sharesDir      = dirname(__DIR__) . '/shares';
        $sharesUpdated  = 0;
        if (is_dir($sharesDir)) {
            foreach (glob($sharesDir . '/*.json') as $shareFile) {
                $raw  = file_get_contents($shareFile);
                $data = json_decode($raw, true);
                if (!is_array($data)) continue;
                $changed = false;
                // Update game.Name
                if (isset($data['game']['Name']) && $data['game']['Name'] === $origName) {
                    $data['game']['Name'] = $name;
                    $changed = true;
                }
                // Update pitches[].Game
                if (!empty($data['pitches']) && is_array($data['pitches'])) {
                    foreach ($data['pitches'] as &$pitch) {
                        if (isset($pitch['Game']) && $pitch['Game'] === $origName) {
                            $pitch['Game'] = $name;
                            $changed = true;
                        }
                    }
                    unset($pitch);
                }
                if ($changed) {
                    file_put_contents($shareFile, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                    $sharesUpdated++;
                }
            }
        }
        if ($sharesUpdated > 0) $result['shares_updated'] = $sharesUpdated;
    }
}
echo $result !== null ? json_encode($result) : json_encode(['error' => $output]);
?>
