<?php
/**
 * Create (or refresh) a T&MBoard share token for a specific project.
 * Stores shares/tmboard/{token}.json → { sheet_id, project, client }
 * Returns the staff view URL — no sheet ID exposed.
 *
 * GET  → check if token exists
 * POST → create/refresh token
 */
error_reporting(0);
header('Content-Type: application/json');

$_bpFile = __DIR__ . '/../dotEnv.php';
if (file_exists($_bpFile)) { require_once $_bpFile; }

$isGet   = ($_SERVER['REQUEST_METHOD'] === 'GET');
$src     = $isGet ? $_GET : $_POST;
$sheetId = trim($src['id']      ?? '');
$project = trim($src['project'] ?? '');
$client  = trim($src['client']  ?? '');

if (!$sheetId || !$project) {
    echo json_encode(['error' => 'Missing id or project']);
    exit;
}

// Deterministic token — same project always gets the same URL
$token = substr(md5($sheetId . '|tm|' . $project), 0, 24);

$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host    = $_SERVER['HTTP_HOST'];
$appRoot = rtrim(dirname(dirname($_SERVER['REQUEST_URI'])), '/');
$viewUrl = $scheme . '://' . $host . $appRoot . '/tmboard/share/' . $token;

$viewsDir = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . 'shares' . DIRECTORY_SEPARATOR . 'tmboard';

if ($isGet) {
    $exists = is_dir($viewsDir) && file_exists($viewsDir . DIRECTORY_SEPARATOR . $token . '.json');
    echo json_encode($exists ? ['ok' => true, 'viewUrl' => $viewUrl] : ['ok' => false]);
    exit;
}

if (!is_dir($viewsDir)) {
    mkdir($viewsDir, 0755, true);
    file_put_contents($viewsDir . DIRECTORY_SEPARATOR . '.htaccess',
        "Options -Indexes\nDeny from all\n");
}

file_put_contents(
    $viewsDir . DIRECTORY_SEPARATOR . $token . '.json',
    json_encode(['sheet_id' => $sheetId, 'project' => $project, 'client' => $client])
);

echo json_encode(['ok' => true, 'viewUrl' => $viewUrl]);
?>
