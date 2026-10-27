<?php
/**
 * Save a DevBoard report HTML to a stable, publicly accessible file.
 *
 * POST params:
 *   id   — Google Spreadsheet ID (used only to derive the stable token)
 *   game — Game name
 *   html — Full HTML of the report
 *
 * The report is saved at:
 *   {app_root}/reports/{token}.html
 * where token = first 24 chars of md5(sheet_id . '|' . game).
 *
 * Regenerating overwrites the same file so the link always shows the latest.
 * The URL contains only the token — no sheet ID is exposed.
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

// Deterministic stable token — same game always gets the same URL
$token = substr(md5($sheetId . '|' . $gameName), 0, 24);

// Reports directory lives at the app root (same level as push/, source/, etc.)
$appRoot    = realpath(__DIR__ . '/..');
$reportsDir = $appRoot . DIRECTORY_SEPARATOR . 'reports';

if (!is_dir($reportsDir)) {
    mkdir($reportsDir, 0755, true);
    // Allow web access to .html files; block directory listing
    file_put_contents($reportsDir . DIRECTORY_SEPARATOR . '.htaccess',
        "Options -Indexes\n" .
        "<Files \"*.html\">\n  Allow from all\n</Files>\n"
    );
}

$filePath = $reportsDir . DIRECTORY_SEPARATOR . $token . '.html';
if (file_put_contents($filePath, $html) === false) {
    echo json_encode(['error' => 'Could not write report file']);
    exit;
}

// Build the public URL
$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host    = $_SERVER['HTTP_HOST'];
$appPath = rtrim(dirname(dirname($_SERVER['REQUEST_URI'])), '/');
$url     = $scheme . '://' . $host . $appPath . '/reports/' . $token . '.html';

echo json_encode(['ok' => true, 'url' => $url, 'token' => $token]);
?>
