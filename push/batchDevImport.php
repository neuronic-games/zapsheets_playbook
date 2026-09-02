<?php
// batchDevImport.php — import multiple DevBoard sessions from a JSON payload.
//
// GET  → serves an HTML receiver page that accepts postMessage data, then POSTs back here
// POST body (JSON): { "sheetId": "...", "gameName": "...", "sessions": [...] }
// Each session: { "num": 82, "date": "2026-08-23", "loc": "...", "testers": [...], "obs": [...] }
//
// Adds rows to [gameName] dev tab using the 4-column DevBoard schema:
//   Header row : Date=date, Event="Playtest N", Observation=loc, Solution=""
//   Tester rows: Date="",   Event="",            Observation=name, Solution=""
//   Obs rows   : Date="",   Event="",            Observation=obs,  Solution=""

error_reporting(0);
ini_set('display_errors', '0');

// Serve HTML receiver page on GET so cross-origin postMessage can deliver the payload here
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: text/html');
    echo <<<'HTML'
<!DOCTYPE html><html><head><meta charset="utf-8"><title>DevBoard Import</title>
<style>body{font-family:monospace;padding:20px;background:#111;color:#0f0;}
#log{white-space:pre-wrap;max-height:80vh;overflow-y:auto;}
.err{color:#f55;}.ok{color:#0f0;}</style></head>
<body>
<h2>DevBoard Import Receiver</h2>
<div id="log">Waiting for data via postMessage…</div>
<script>
var log = document.getElementById('log');
function append(msg, cls) {
  var d = document.createElement('div');
  d.className = cls||'';
  d.textContent = msg;
  log.appendChild(d);
  log.scrollTop = log.scrollHeight;
}
window.addEventListener('message', async function(e) {
  if (!e.data || e.data.type !== 'DEVBOARD_IMPORT') return;
  append('Received ' + e.data.sessions.length + ' sessions. Starting import…');
  var payload = {
    sheetId: e.data.sheetId,
    gameName: e.data.gameName,
    sessions: e.data.sessions
  };
  try {
    append('POSTing to import endpoint…');
    var r = await fetch(window.location.href, {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    });
    var d = await r.json();
    if (d.error) { append('Error: ' + d.error, 'err'); return; }
    append('Done! Sessions: ' + d.sessions + ' | Rows written: ' + d.rows, 'ok');
    // Show per-row detail
    if (d.detail) {
      var errs = d.detail.filter(function(x){return !x.result || !x.result.ok;});
      if (errs.length) {
        append(errs.length + ' errors:', 'err');
        errs.forEach(function(x){ append(JSON.stringify(x), 'err'); });
      }
    }
    // Signal back to opener
    if (window.opener) window.opener.postMessage({type:'IMPORT_DONE', rows: d.rows}, '*');
  } catch(ex) {
    append('Fetch error: ' + ex.message, 'err');
  }
});
append('Ready. Send postMessage({type:"DEVBOARD_IMPORT", sheetId, gameName, sessions}) to this window.');
</script></body></html>
HTML;
    exit;
}

header('Content-Type: application/json');
// CORS (also covered globally by .htaccess, but be explicit for OPTIONS)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require __DIR__ . '/../dotEnv.php';

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    echo json_encode(['error' => 'Invalid JSON']); exit;
}

$sheetId  = trim($data['sheetId']  ?? '');
$gameName = trim($data['gameName'] ?? '');
$sessions = $data['sessions'] ?? [];

if (!$sheetId || !$gameName || !$sessions) {
    echo json_encode(['error' => 'Missing sheetId, gameName, or sessions']); exit;
}

$tabName    = '[' . $gameName . '] dev';
$pythonPath = $_ENV['PYTHON'] ?? 'python3';
$gadd       = __DIR__ . '/gadd.py';
$gaddtester = __DIR__ . '/gaddtesterrow.py';

function safe_run($cmd) {
    $out = trim((string) shell_exec($cmd . ' 2>&1'));
    $res = json_decode($out, true);
    return $res ?: ['error' => $out ?: 'no output'];
}

$results = [];
$rowCount = 0;

foreach ($sessions as $s) {
    $num      = intval($s['num'] ?? 0);
    $date     = trim($s['date'] ?? '');
    $loc      = trim($s['loc']  ?? '');
    $testers  = $s['testers'] ?? [];
    $obs      = $s['obs']     ?? [];

    $label = 'Playtest ' . $num;

    // 1. Header row
    $row     = ['Date' => $date, 'Event' => $label, 'Observation' => $loc, 'Solution' => ''];
    $encoded = base64_encode(json_encode($row, JSON_UNESCAPED_UNICODE));
    $arg     = $sheetId . '|' . $tabName . '|' . $encoded;
    $cmd     = escapeshellarg($pythonPath) . ' ' . escapeshellarg($gadd) . ' ' . escapeshellarg($arg);
    $res     = safe_run($cmd);
    $results[] = ['type' => 'header', 'session' => $num, 'result' => $res];
    if (!empty($res['ok'])) $rowCount++;

    // 2. Tester rows
    foreach ($testers as $t) {
        $t = trim($t);
        if (!$t) continue;
        $arg = $sheetId . '|' . $tabName . '|' . $t . '|';
        $cmd = escapeshellarg($pythonPath) . ' ' . escapeshellarg($gaddtester) . ' ' . escapeshellarg($arg);
        $res = safe_run($cmd);
        $results[] = ['type' => 'tester', 'name' => $t, 'result' => $res];
        if (!empty($res['ok'])) $rowCount++;
    }

    // 3. Observation rows
    foreach ($obs as $o) {
        $o = trim($o);
        if (!$o) continue;
        $row     = ['Date' => '', 'Event' => '', 'Observation' => $o, 'Solution' => ''];
        $encoded = base64_encode(json_encode($row, JSON_UNESCAPED_UNICODE));
        $arg     = $sheetId . '|' . $tabName . '|' . $encoded;
        $cmd     = escapeshellarg($pythonPath) . ' ' . escapeshellarg($gadd) . ' ' . escapeshellarg($arg);
        $res     = safe_run($cmd);
        $results[] = ['type' => 'obs', 'result' => $res];
        if (!empty($res['ok'])) $rowCount++;
    }
}

echo json_encode([
    'ok'       => true,
    'sessions' => count($sessions),
    'rows'     => $rowCount,
    'detail'   => $results,
]);
?>
