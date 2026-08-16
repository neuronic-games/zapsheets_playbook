<?php
/**
 * Create (or refresh) a view-only pitch token for a specific game.
 * Stores shares/pitch-collab-readonly/{token}.json → { sheet_id, game }
 * Returns the public view URL — no sheet ID exposed.
 */
error_reporting(0);
header('Content-Type: application/json');

$_bpFile = __DIR__ . '/../dotEnv.php';
if (file_exists($_bpFile)) { require_once $_bpFile; }

// GET = check-only; POST = create/refresh
$isGet    = ($_SERVER['REQUEST_METHOD'] === 'GET');
$sheetId  = trim(($isGet ? $_GET : $_POST)['id']     ?? '');
$gameName = trim(($isGet ? $_GET : $_POST)['game']   ?? '');
$sharer   = trim(($isGet ? $_GET : $_POST)['sharer'] ?? '');

if (!$sheetId || !$gameName) {
    echo json_encode(['error' => 'Missing data']);
    exit;
}

// Deterministic, stable token — same game always gets the same URL
$token = substr(md5($sheetId . '|' . $gameName), 0, 24);

$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host    = $_SERVER['HTTP_HOST'];
$appRoot = rtrim(dirname(dirname($_SERVER['REQUEST_URI'])), '/');
$viewUrl = $scheme . '://' . $host . $appRoot . '/pitchboard/share/' . $token;

// GET: just check whether the token file exists
$viewsDir = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . 'shares' . DIRECTORY_SEPARATOR . 'pitch-collab-readonly';
if ($isGet) {
    $exists = is_dir($viewsDir) && file_exists($viewsDir . DIRECTORY_SEPARATOR . $token . '.json');
    echo json_encode($exists ? ['ok' => true, 'viewUrl' => $viewUrl] : ['ok' => false]);
    exit;
}

// POST: create or refresh
if (!is_dir($viewsDir)) {
    mkdir($viewsDir, 0755, true);
    // Block direct directory listing and raw JSON access
    file_put_contents($viewsDir . DIRECTORY_SEPARATOR . '.htaccess',
        "Options -Indexes\nDeny from all\n");
}

$_payload = ['sheet_id' => $sheetId, 'game' => $gameName];
if ($sharer !== '') $_payload['sharer'] = $sharer;
file_put_contents(
    $viewsDir . DIRECTORY_SEPARATOR . $token . '.json',
    json_encode($_payload)
);

echo json_encode(['ok' => true, 'viewUrl' => $viewUrl]);
?>
