<?php
// cardboardData.php — data for the CardBoard card designer.
//
// POST params: id (sheet ID), action, and per action:
//   tabs    —                       → {ok, title, tabs:[{title,rows,cols}]}
//   rows    — tab, force (optional)  → {ok, rows:[{header: value}]}   (cached in sheets/{id}/{tab}.json)
//   layouts —                       → {ok, layouts:{tab: layout}}
//   save    — tab, layout (JSON)     → {ok, saved, updated}
//   start   — tab (optional)         → {ok, title, tabs, layouts, tab, rows, full}  (first rows only unless cached)
//   peek    — tab                    → {ok, rows, full}

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

// Nothing is read before signing in; anything that changes the spreadsheet also needs the
// free week, a subscription or an unlocked sheet
require_once __DIR__ . '/cardboardAuth.php';
cbRequireSignIn();
if (in_array($action, ['save', 'addfield', 'renamefield', 'setcell', 'addrow', 'deleterow', 'deletefield', 'settag', 'deltag'], true)) {
    require_once __DIR__ . '/cardboardAuth.php';
    cbRequireAccess($sheetId);
}

if ($action === 'tabs')    { echo json_encode(cbRun($python, $sheetId, ['action' => 'tabs'])); exit; }
if ($action === 'layouts') { echo json_encode(cbRun($python, $sheetId, ['action' => 'layouts'])); exit; }

// Tags: [name] → text or a picture, kept in the "tags" tab.
if ($action === 'tags') { echo json_encode(cbRun($python, $sheetId, ['action' => 'tags']), JSON_UNESCAPED_UNICODE); exit; }
if ($action === 'settag' || $action === 'deltag') {
    $tag = trim((string) ($_POST['tag'] ?? ''), " \t{}[]");
    if ($tag === '' || mb_strlen($tag) > 60 || strpbrk($tag, '{}[]') !== false) { echo json_encode(['error' => 'Use up to 60 characters for the tag name, without brackets or braces inside']); exit; }
    $req = ['action' => $action, 'tag' => $tag];
    if ($action === 'settag') {
        $req['value'] = (string) ($_POST['value'] ?? '');
        if (mb_strlen($req['value']) > 5000) { echo json_encode(['error' => 'That value is too long']); exit; }
        if (isset($_POST['old'])) $req['old'] = (string) $_POST['old'];
    }
    echo json_encode(cbRun($python, $sheetId, $req), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'save') {
    $tab    = trim($_POST['tab'] ?? '');
    $layout = json_decode($_POST['layout'] ?? '', true);
    if ($tab === '' || !is_array($layout)) { echo json_encode(['error' => 'Missing tab or layout']); exit; }
    echo json_encode(cbRun($python, $sheetId, ['action' => 'save', 'sheet' => $tab, 'layout' => $layout]));
    exit;
}

if ($action === 'renamefield') {
    $tab  = trim($_POST['tab'] ?? '');
    $old  = trim((string) ($_POST['old'] ?? ''));
    $name = trim(preg_replace('/\s+/u', ' ', $_POST['name'] ?? ''));
    if ($tab === '' || strpbrk($tab, "/\\") !== false || strtolower($tab) === 'layouts') { echo json_encode(['error' => 'Invalid tab']); exit; }
    if ($old === '' || $name === '' || mb_strlen($name) > 60 || in_array(strtolower($name), ['_row', '_notes'], true) || strpbrk($name, '{}[]') !== false) {
        echo json_encode(['error' => 'Use up to 60 characters, without { } [ ] (and not _row or _notes)']); exit;
    }
    $res = cbRun($python, $sheetId, ['action' => 'renamefield', 'sheet' => $tab, 'old' => $old, 'name' => $name]);
    if (!empty($res['ok'])) @unlink(dirname(__DIR__) . '/sheets/' . $sheetId . '/' . strtolower($tab) . '.json');   // re-read next time
    echo json_encode($res);
    exit;
}

if ($action === 'addfield') {
    $tab  = trim($_POST['tab'] ?? '');
    $name = trim(preg_replace('/\s+/u', ' ', $_POST['name'] ?? ''));
    if ($tab === '' || strpbrk($tab, "/\\") !== false || strtolower($tab) === 'layouts') { echo json_encode(['error' => 'Invalid tab']); exit; }
    if ($name === '' || mb_strlen($name) > 60 || in_array(strtolower($name), ['_row', '_notes'], true) || strpbrk($name, '{}') !== false) {
        echo json_encode(['error' => 'Use up to 60 characters, without { or } (and not _row or _notes)']); exit;
    }
    $res = cbRun($python, $sheetId, ['action' => 'addfield', 'sheet' => $tab, 'name' => $name]);
    if (!empty($res['ok'])) @unlink(dirname(__DIR__) . '/sheets/' . $sheetId . '/' . strtolower($tab) . '.json');   // re-read next time
    echo json_encode($res);
    exit;
}

// Data tab: the sheet as typed, and edits written straight back
if (in_array($action, ['grid', 'setcell', 'addrow', 'deleterow', 'deletefield'], true)) {
    $tab = trim($_POST['tab'] ?? '');
    if ($tab === '' || strpbrk($tab, "/\\") !== false || strtolower($tab) === 'layouts') { echo json_encode(['error' => 'Invalid tab']); exit; }
    $req = ['action' => $action, 'sheet' => $tab];
    if ($action === 'setcell') {
        $req += ['row' => (int) ($_POST['row'] ?? 0), 'field' => (string) ($_POST['field'] ?? ''), 'value' => (string) ($_POST['value'] ?? '')];
        if (isset($_POST['old'])) $req['old'] = (string) $_POST['old'];
        if (mb_strlen($req['value']) > 50000) { echo json_encode(['error' => 'That is too long for one cell']); exit; }
    }
    if ($action === 'addrow') {
        $vals = json_decode($_POST['values'] ?? '{}', true);
        $req['values'] = is_array($vals) ? $vals : [];
    }
    if ($action === 'deleterow') {
        $vals = json_decode($_POST['values'] ?? '[]', true);
        $req += ['row' => (int) ($_POST['row'] ?? 0), 'values' => is_array($vals) ? array_values($vals) : []];
    }
    if ($action === 'deletefield') $req['field'] = trim((string) ($_POST['field'] ?? ''));
    $res = cbRun($python, $sheetId, $req);
    if ($action !== 'grid' && !empty($res['ok'])) @unlink(dirname(__DIR__) . '/sheets/' . $sheetId . '/' . strtolower($tab) . '.json');   // cached rows are stale now
    echo json_encode($res, JSON_UNESCAPED_UNICODE);
    exit;
}

// Quick start: tabs, layouts and the first rows of the tab to show, in one Google call.
// When this server already has the tab's rows saved they're sent whole ("full").
function cbCached(string $sheetId, string $tab): ?array {
    if ($tab === '' || strpbrk($tab, "/\\") !== false) return null;
    $f = dirname(__DIR__) . '/sheets/' . $sheetId . '/' . strtolower($tab) . '.json';
    if (!is_file($f)) return null;
    $rows = json_decode((string) @file_get_contents($f), true);
    return is_array($rows) ? $rows : null;
}
if ($action === 'start') {
    $res = cbRun($python, $sheetId, ['action' => 'start', 'tab' => trim($_POST['tab'] ?? '')]);
    if (!empty($res['ok'])) {
        $all = cbCached($sheetId, (string) ($res['tab'] ?? ''));
        if ($all !== null) { $res['rows'] = $all; $res['full'] = true; } else $res['full'] = false;
        if (empty($res['layouts'])) $res['layouts'] = new stdClass();   // {} rather than [] in the JSON
    }
    echo json_encode($res, JSON_UNESCAPED_UNICODE);
    exit;
}
if ($action === 'peek') {
    $tab = trim($_POST['tab'] ?? '');
    if ($tab === '' || strpbrk($tab, "/\\") !== false || strtolower($tab) === 'layouts') { echo json_encode(['error' => 'Invalid tab']); exit; }
    $all = cbCached($sheetId, $tab);
    if ($all !== null) { echo json_encode(['ok' => true, 'rows' => $all, 'full' => true], JSON_UNESCAPED_UNICODE); exit; }
    $res = cbRun($python, $sheetId, ['action' => 'peek', 'tab' => $tab]);
    if (!empty($res['ok'])) $res['full'] = false;
    echo json_encode($res, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'rows') {
    $tab = trim($_POST['tab'] ?? '');
    if ($tab === '' || strpbrk($tab, "/\\") !== false || strtolower($tab) === 'layouts') {
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
