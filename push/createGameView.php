<?php
/**
 * Create (or check) a token-based public game page link.
 * Stores shares/pitch-game-view/{token}.json → { sheet_id, game }
 * Returns a clean public URL — no sheet ID exposed.
 *
 * GET  ?id=&game=  → check only, returns { ok: bool, viewUrl? }
 * POST id=&game=   → create/refresh, returns { ok: true, viewUrl }
 */
error_reporting(0);
header('Content-Type: application/json');

$_bpFile = __DIR__ . '/../dotEnv.php';
if (file_exists($_bpFile)) { require_once $_bpFile; }

$isGet    = ($_SERVER['REQUEST_METHOD'] === 'GET');
$sheetId  = trim(($isGet ? $_GET : $_POST)['id']   ?? '');
$gameName = trim(($isGet ? $_GET : $_POST)['game'] ?? '');

if (!$sheetId || !$gameName) {
    echo json_encode(['error' => 'Missing data']);
    exit;
}

// Deterministic token — salt differs from pitch-collab-readonly to avoid collision
$token = substr(md5($sheetId . '|game|' . $gameName), 0, 24);

$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host    = $_SERVER['HTTP_HOST'];
$appRoot = rtrim(dirname(dirname($_SERVER['REQUEST_URI'])), '/');
$viewUrl = $scheme . '://' . $host . $appRoot . '/game/' . $token;

$viewsDir = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . 'shares' . DIRECTORY_SEPARATOR . 'pitch-game-view';

// GET: check only — no file write
if ($isGet) {
    $exists = is_dir($viewsDir) && file_exists($viewsDir . DIRECTORY_SEPARATOR . $token . '.json');
    echo json_encode($exists ? ['ok' => true, 'viewUrl' => $viewUrl] : ['ok' => false]);
    exit;
}

// POST: create or refresh
if (!is_dir($viewsDir)) {
    mkdir($viewsDir, 0755, true);
    file_put_contents($viewsDir . DIRECTORY_SEPARATOR . '.htaccess',
        "Options -Indexes\nDeny from all\n");
}

file_put_contents(
    $viewsDir . DIRECTORY_SEPARATOR . $token . '.json',
    json_encode(['sheet_id' => $sheetId, 'game' => $gameName])
);

echo json_encode(['ok' => true, 'viewUrl' => $viewUrl]);
?>
