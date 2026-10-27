<?php
/**
 * Save a DevBoard report HTML to a stable, publicly accessible file.
 *
 * POST params:
 *   id   — Google Spreadsheet ID (used only to derive the stable token)
 *   game — Game name
 *   html — Full HTML of the report
 *
 * Saved at {app_root}/reports/{token}.html
 * Same game always gets the same token, so regenerating overwrites in place.
 * The URL exposes only the token — no sheet ID.
 */
error_reporting(0);
header('Content-Type: application/json');

$_bpFile = __DIR__ . '/../dotEnv.php';
if (file_exists($_bpFile)) { require_once $_bpFile; }

$sheetId  = trim($_POST['id']   ?? '');
$gameName = trim($_POST['game'] ?? '');
$html     = $_POST['html']      ?? '';

if (!$sheetId || !$gameName) {
    echo json_encode(['error' => 'Missing sheet ID or game name']);
    exit;
}
if (strlen($html) < 100) {
    echo json_encode(['error' => 'HTML content too short']);
    exit;
}

$token = substr(md5($sheetId . '|' . $gameName), 0, 24);

$appRoot    = realpath(__DIR__ . '/..');
$reportsDir = $appRoot . DIRECTORY_SEPARATOR . 'reports';

if (!is_dir($reportsDir)) {
    mkdir($reportsDir, 0755, true);
    file_put_contents($reportsDir . DIRECTORY_SEPARATOR . '.htaccess',
        "Options -Indexes\n<Files \"*.html\">\n  Allow from all\n</Files>\n");
}

if (file_put_contents($reportsDir . DIRECTORY_SEPARATOR . $token . '.html', $html) === false) {
    echo json_encode(['error' => 'Could not write report file']);
    exit;
}

$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host    = $_SERVER['HTTP_HOST'];
$appPath = rtrim(dirname(dirname($_SERVER['REQUEST_URI'])), '/');
$url     = $scheme . '://' . $host . $appPath . '/reports/' . $token . '.html';

echo json_encode(['ok' => true, 'url' => $url, 'token' => $token]);
?>
