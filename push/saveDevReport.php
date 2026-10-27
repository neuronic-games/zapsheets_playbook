<?php
/**
 * Save a DevBoard report as a PDF (primary) and HTML (fallback).
 *
 * POST params:
 *   id          — Google Spreadsheet ID (used only to derive the stable token)
 *   game        — Game name
 *   html        — Full HTML of the report (fallback if PDF gen fails)
 *   reportData  — base64-encoded JSON for the Python PDF generator
 *
 * Files saved:
 *   {app_root}/reports/{token}.pdf   ← primary link returned
 *   {app_root}/reports/{token}.html  ← fallback
 *
 * Regenerating overwrites the same files so the link always shows the latest.
 * The URL exposes only the token, not the sheet ID.
 */
error_reporting(0);
header('Content-Type: application/json');

$_bpFile = __DIR__ . '/../dotEnv.php';
if (file_exists($_bpFile)) { require_once $_bpFile; }

$sheetId    = trim($_POST['id']         ?? '');
$gameName   = trim($_POST['game']       ?? '');
$html       = $_POST['html']            ?? '';
$reportData = trim($_POST['reportData'] ?? '');

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

// Reports directory at the app root
$appRoot    = realpath(__DIR__ . '/..');
$reportsDir = $appRoot . DIRECTORY_SEPARATOR . 'reports';

if (!is_dir($reportsDir)) {
    mkdir($reportsDir, 0755, true);
    file_put_contents($reportsDir . DIRECTORY_SEPARATOR . '.htaccess',
        "Options -Indexes\n" .
        "<Files \"*.pdf\">\n  Allow from all\n</Files>\n" .
        "<Files \"*.html\">\n  Allow from all\n</Files>\n"
    );
}

// Always save the HTML fallback
$htmlPath = $reportsDir . DIRECTORY_SEPARATOR . $token . '.html';
file_put_contents($htmlPath, $html);

$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host    = $_SERVER['HTTP_HOST'];
$appPath = rtrim(dirname(dirname($_SERVER['REQUEST_URI'])), '/');

// ── Try to generate a PDF via Python + reportlab ─────────────────────────────
$pdfGenerated = false;
$pdfPath      = $reportsDir . DIRECTORY_SEPARATOR . $token . '.pdf';

if ($reportData) {
    $pythonPath = $_ENV['PYTHON'] ?? 'python3';
    $pyScript   = __DIR__ . '/genPdfReport.py';

    if (file_exists($pyScript)) {
        // Inject the server-side outPath into the report data
        $decoded = json_decode(base64_decode($reportData), true);
        if ($decoded) {
            $decoded['outPath'] = $pdfPath;
            $reportData = base64_encode(json_encode($decoded));
        }
        $arg = $appRoot . '|' . $reportData;
        $cmd = escapeshellarg($pythonPath) . ' '
             . escapeshellarg($pyScript) . ' '
             . escapeshellarg($arg) . ' 2>&1';
        $out = trim((string) shell_exec($cmd));
        $res = $out ? json_decode($out, true) : null;
        if ($res && !empty($res['ok']) && file_exists($pdfPath)) {
            $pdfGenerated = true;
        }
    }
}

if ($pdfGenerated) {
    $url = $scheme . '://' . $host . $appPath . '/reports/' . $token . '.pdf';
    echo json_encode(['ok' => true, 'url' => $url, 'token' => $token, 'format' => 'pdf']);
} else {
    // Fall back to the HTML report
    $url = $scheme . '://' . $host . $appPath . '/reports/' . $token . '.html';
    echo json_encode(['ok' => true, 'url' => $url, 'token' => $token, 'format' => 'html']);
}
?>
