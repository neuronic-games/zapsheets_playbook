<?php
// extractImportData.php — one-time helper:
// Reads the console-log tool result file, reassembles the session chunks,
// writes sheets/import/spacetime_sessions.json, then runs batchDevImport.php
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain');

// Tool result file written by Claude's read_console_messages call
$toolFile = '/var/folders/_2/vbmdcqr1105flrpxcn244x540000gn/T/claude-hostloop-plugins/036b8ab3f06173bc/projects/-Users-tam-Library-Application-Support-Claude-local-agent-mode-sessions-27081fa0-9996-4f71-8a50-e55a7e4b81d6-7f648bde-6889-40d7-adc4-955ae3729699-local-0e4c600c-9484-4be2-8f5c-6d5b380b793a-outputs/c26b0821-8c84-425f-bf9d-1e17ad7acf96/tool-results/toolu_01M5ka6GAiqFsMN8evGAu6Rz.json';

if (!file_exists($toolFile)) {
    echo "Tool result file not found at: $toolFile\n";
    exit(1);
}

$raw = file_get_contents($toolFile);
$data = json_decode($raw, true);
if (!$data) {
    echo "Failed to parse tool result JSON\n";
    exit(1);
}

$text = $data[0]['text'] ?? '';
echo "Got text length: " . strlen($text) . "\n";

// Extract chunks
preg_match_all('/IMPORT_CHUNK_(\d+):(.+?)(?=\n\[|\n\nTab Context|\Z)/s', $text, $matches);
$chunks = [];
foreach ($matches[1] as $i => $idx) {
    $chunks[(int)$idx] = rtrim($matches[2][$i], "\n");
}
ksort($chunks);
echo "Found " . count($chunks) . " chunks\n";

$fullJson = implode('', $chunks);
$sessions = json_decode($fullJson, true);
if (!$sessions) {
    echo "Failed to parse sessions JSON (json_last_error=" . json_last_error() . ")\n";
    echo "JSON preview: " . substr($fullJson, 0, 200) . "\n";
    exit(1);
}
echo "Parsed " . count($sessions) . " sessions\n";

// Save to import file
$outDir = dirname(__DIR__) . '/sheets/import';
if (!is_dir($outDir)) mkdir($outDir, 0777, true);
$outFile = $outDir . '/spacetime_sessions.json';
file_put_contents($outFile, json_encode($sessions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "Saved to: $outFile\n\n";

// Now run the import
require __DIR__ . '/../dotEnv.php';

$sheetId  = '1vtQS1SNl5-dVZ8VdxbnfWCXHJm1kMmPlFAfhvE7QBaE';
$gameName = 'Spacetime';
$tabName  = '[' . $gameName . '] dev';
$pythonPath = $_ENV['PYTHON'] ?? 'python3';
$gadd       = __DIR__ . '/gadd.py';
$gaddtester = __DIR__ . '/gaddtesterrow.py';

function safe_run2($cmd) {
    $out = trim((string) shell_exec($cmd . ' 2>&1'));
    $res = json_decode($out, true);
    return $res ?: ['error' => $out ?: 'no output'];
}

$rowCount = 0;
$errCount = 0;

foreach ($sessions as $s) {
    $num     = intval($s['num'] ?? 0);
    $date    = trim($s['date'] ?? '');
    $loc     = trim($s['loc']  ?? '');
    $testers = $s['testers'] ?? [];
    $obs     = $s['obs']     ?? [];
    $label   = 'Playtest ' . $num;

    // Header row
    $row     = ['Date' => $date, 'Event' => $label, 'Observation' => $loc, 'Solution' => ''];
    $encoded = base64_encode(json_encode($row, JSON_UNESCAPED_UNICODE));
    $arg     = $sheetId . '|' . $tabName . '|' . $encoded;
    $cmd     = escapeshellarg($pythonPath) . ' ' . escapeshellarg($gadd) . ' ' . escapeshellarg($arg);
    $res     = safe_run2($cmd);
    if (!empty($res['ok'])) { $rowCount++; echo "  ✓ Header: Playtest $num ($date)\n"; }
    else { $errCount++; echo "  ✗ Header error: " . json_encode($res) . "\n"; }

    // Tester rows
    foreach ($testers as $t) {
        $t = trim($t);
        if (!$t) continue;
        $arg = $sheetId . '|' . $tabName . '|' . $t . '|';
        $cmd = escapeshellarg($pythonPath) . ' ' . escapeshellarg($gaddtester) . ' ' . escapeshellarg($arg);
        $res = safe_run2($cmd);
        if (!empty($res['ok'])) $rowCount++;
        else { $errCount++; echo "  ✗ Tester error ($t): " . json_encode($res) . "\n"; }
    }

    // Obs rows
    foreach ($obs as $o) {
        $o = trim($o);
        if (!$o) continue;
        $row     = ['Date' => '', 'Event' => '', 'Observation' => $o, 'Solution' => ''];
        $encoded = base64_encode(json_encode($row, JSON_UNESCAPED_UNICODE));
        $arg     = $sheetId . '|' . $tabName . '|' . $encoded;
        $cmd     = escapeshellarg($pythonPath) . ' ' . escapeshellarg($gadd) . ' ' . escapeshellarg($arg);
        $res     = safe_run2($cmd);
        if (!empty($res['ok'])) $rowCount++;
        else { $errCount++; echo "  ✗ Obs error: " . json_encode($res) . "\n"; }
    }
}

echo "\n=== DONE: $rowCount rows written, $errCount errors ===\n";
?>
