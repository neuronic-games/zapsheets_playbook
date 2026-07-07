<?php
/**
 * push/deployViewSource.php
 * Copies source/view/index.php → sheets/{id}/view/index.php
 * so the live view page always reflects the latest source code.
 *
 * POST params:
 *   id  — Google Sheet ID
 *
 * Returns: {"ok": true} or {"error": "..."}
 */
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

$root    = dirname(__DIR__);
$srcFile = $root . '/source/view/index.php';
$sheetId = trim($_POST['id'] ?? '');

if (!$sheetId) {
    echo json_encode(['error' => 'Missing sheet ID']);
    exit;
}

if (!file_exists($srcFile)) {
    echo json_encode(['error' => 'source/view/index.php not found']);
    exit;
}

$destDir = $root . '/sheets/' . $sheetId . '/view';
if (!is_dir($destDir) && !mkdir($destDir, 0777, true)) {
    echo json_encode(['error' => 'Could not create view directory']);
    exit;
}

$dest = $destDir . '/index.php';
if (!copy($srcFile, $dest)) {
    echo json_encode(['error' => 'Could not write to sheets/' . $sheetId . '/view/index.php']);
    exit;
}

echo json_encode(['ok' => true]);
?>
