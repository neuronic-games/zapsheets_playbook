<?php
// cardboardData.php — data for the CardBoard card designer.
//
// POST params: id (sheet ID), action, and per action:
//   tabs    —                       → {ok, title, tabs:[{title,rows,cols}]}
//   rows    — tab, force (optional)  → {ok, rows:[{header: value}]}   (cached in sheets/{id}/{tab}.json)
//   layouts —                       → {ok, layouts:{tab: layout}}
//   save    — tab, layout (JSON)     → {ok, saved, updated}

error_reporting(0);
header('Content-Type: application/json');
require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/refreshJson.php';

$sheetId = trim($_POST['id'] ?? '');
$action  = trim($_POST['action'] ?? '');
if (!preg_match('/^[A-Za-z0-9_-]{10,128}$/', $sheetId)) { echo json_encode(['error' => 'Invalid sheet id']); exit; }
$python = $_ENV['PYTHON'] ?? 'python3';

function cbRun(string $python, string $sheetId, array $data): array {
    $cmd = escapeshellarg($python) . ' ' . escapeshellarg(__DIR__ . '/gcardboard.py') . ' '
         . escapeshellarg($sheetId . '|' . base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE))) . ' 2>&1';
    $raw = trim((string) shell_exec($cmd));
    foreach (array_reverse(preg_split('/\R/', $raw)) as $line) {   // the answer is the last JSON line
        $line = trim($line);
        if ($line !== '' && $line[0] === '{' && is_array($r = json_decode($line, true))) return $r;
    }
    return ['error' => 'The CardBoard script failed: ' . ($raw !== '' ? mb_substr($raw, -300) : 'no output')];
}

if ($action === 'tabs')    { echo json_encode(cbRun($python, $sheetId, ['action' => 'tabs'])); exit; }
if ($action === 'layouts') { echo json_encode(cbRun($python, $sheetId, ['action' => 'layouts'])); exit; }

if ($action === 'save') {
    $tab    = trim($_POST['tab'] ?? '');
    $layout = json_decode($_POST['layout'] ?? '', true);
    if ($tab === '' || !is_array($layout)) { echo json_encode(['error' => 'Missing tab or layout']); exit; }
    echo json_encode(cbRun($python, $sheetId, ['action' => 'save', 'sheet' => $tab, 'layout' => $layout]));
    exit;
}

if ($action === 'rows') {
    $tab = trim($_POST['tab'] ?? '');
    if ($tab === '' || strpbrk($tab, "/\\") !== false || strtolower($tab) === 'cardboard') {
        echo json_encode(['error' => 'Invalid tab']); exit;
    }
    $file = dirname(__DIR__) . '/sheets/' . $sheetId . '/' . strtolower($tab) . '.json';
    if (!empty($_POST['force']) || !file_exists($file)) {
        $err = refreshJson($python, $sheetId, $tab);
        if ($err !== null && !file_exists($file)) { echo json_encode(['error' => $err]); exit; }
    }
    $rows = json_decode((string) @file_get_contents($file), true);
    echo json_encode(['ok' => true, 'rows' => is_array($rows) ? $rows : []], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['error' => 'Unknown action']);
