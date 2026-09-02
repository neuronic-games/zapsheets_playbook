<?php
// storeDevImport.php — temporary receiver for browser-posted session import data.
// Writes JSON to sheets/import/spacetime_sessions.json, then deletes itself.
// POST: { sessions: [...] }
error_reporting(0);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data || empty($data['sessions'])) {
    echo json_encode(['error' => 'No sessions']);
    exit;
}

$dir = dirname(__DIR__) . '/sheets/import';
if (!is_dir($dir)) mkdir($dir, 0777, true);
file_put_contents($dir . '/spacetime_sessions.json', json_encode($data['sessions'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode(['ok' => true, 'count' => count($data['sessions'])]);
?>
