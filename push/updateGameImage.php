<?php
/**
 * updateGameImage.php — update the Image column for a single game in the Games sheet.
 *
 * POST params:
 *   id         — sheet ID
 *   name       — exact game name (col A)
 *   image_url  — uploaded image URL (bare URL; will be wrapped as =IMAGE("url"))
 */

header('Content-Type: application/json');
require __DIR__ . '/../dotEnv.php';

$sheetId  = trim($_POST['id']        ?? '');
$gameName = trim($_POST['name']      ?? '');
$imageUrl = trim($_POST['image_url'] ?? '');

if (!$sheetId || !$gameName || !$imageUrl) {
    echo json_encode(['error' => 'id, name, and image_url are all required']);
    exit;
}

if (!preg_match('/^[A-Za-z0-9_-]+$/', $sheetId)) {
    echo json_encode(['error' => 'Invalid sheet ID']);
    exit;
}

$pythonPath = $_ENV['PYTHON'] ?? 'python3';
$scriptPath = __DIR__ . '/gupdategamecolumn.py';

// Use "Image URL" — gupdategamecolumn.py aliases to "Image" if needed
$payload = json_encode([
    'name'   => $gameName,
    'column' => 'Image URL',
    'value'  => '=IMAGE("' . $imageUrl . '")',
]);

$arg = $sheetId . '|' . base64_encode($payload);
$cmd = escapeshellarg($pythonPath) . ' ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($arg) . ' 2>&1';
$out = shell_exec($cmd);

$result = json_decode(trim($out ?? ''), true);
if (!$result) {
    echo json_encode(['error' => 'Script returned invalid JSON', 'raw' => $out]);
    exit;
}
if (!empty($result['error'])) {
    echo json_encode(['error' => $result['error']]);
    exit;
}

echo json_encode(['ok' => true, 'image_url' => $imageUrl]);
