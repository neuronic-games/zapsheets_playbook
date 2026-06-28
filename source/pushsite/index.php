<?php
/**
 * pushsite/index.php — server-side sheet sync, no browser/JS required.
 *
 * Usage (GET or POST):
 *   /pushsite?id=<sheetId>
 *   /pushsite?id=<sheetId>&sheets=pitches,people   ← extra sheets on top
 *
 * Always pulls: settings, games, site
 * Then pulls:   per-game tabs (from games.json Name column)
 * Then pulls:   any extra sheets listed in ?sheets=
 * Finally:      copies source/site/ → sheets/{id}/site/
 */

header('Content-Type: text/plain; charset=UTF-8');
error_reporting(0);
ini_set('display_errors', '0');

require __DIR__ . '/../dotEnv.php';

$root       = dirname(__DIR__);
$pythonPath = $_ENV['PYTHON'] ?? 'python3';
$pushDir    = $root . '/push';

// ── Parameters ────────────────────────────────────────────────────────────────
$sheetId     = trim($_REQUEST['id']     ?? '');
$sheetsParam = trim($_REQUEST['sheets'] ?? $_REQUEST['sheet'] ?? '');

if ($sheetId === '') {
    echo "ERROR: missing ?id= parameter\n";
    exit(1);
}

// Extra sheets from URL (optional)
$extraSheets = $sheetsParam !== ''
    ? array_values(array_filter(array_map('trim', explode(',', $sheetsParam))))
    : [];

// ── Helpers ───────────────────────────────────────────────────────────────────
function runPy(string $pythonPath, string $script, string $arg): string {
    $cmd = escapeshellarg($pythonPath) . ' '
         . escapeshellarg($script) . ' '
         . escapeshellarg($arg) . ' 2>/dev/null';
    return trim((string) shell_exec($cmd));
}

// $pulled tracks what has already been fetched (lowercase) to avoid duplicates
$pulled = [];

// Sheets that use col-A/col-B key-value structure with no required header row.
// greadkv.py handles these instead of gread.py so blank/missing headers don't
// produce empty {} records.
$KV_SHEETS = ['site'];

function pullSheet(string $sheetName): void {
    global $pythonPath, $pushDir, $sheetDir, $sheetId, $pulled, $KV_SHEETS;

    $key = strtolower($sheetName);
    if (in_array($key, $pulled, true)) return;
    $pulled[] = $key;

    // Choose the right reader
    $script  = in_array($key, $KV_SHEETS, true) ? 'greadkv.py' : 'gread.py';
    $out = runPy($pythonPath, $pushDir . '/' . $script, $sheetId . 'sheetname' . $sheetName);

    if ($out === '') {
        echo "SKIP: " . $sheetName . " — no output from " . $script . "\n";
        return;
    }
    $decoded = json_decode($out, true);
    if (is_array($decoded) && isset($decoded['error'])) {
        echo "ERROR: " . $sheetName . ": " . $decoded['error'] . "\n";
        return;
    }
    $jsonFile = $sheetDir . '/' . $key . '.json';
    echo (file_exists($jsonFile) ? "OK: " : "WARN: file missing after " . $script . " — ") . $sheetName . "\n";
}

// ── Step 1: Increment version.json + write to Google Sheet ───────────────────
$versionFile = $root . '/version.json';
$versionData = ['Version' => 0, 'PublishedOn' => ''];
if (file_exists($versionFile)) {
    $ex = json_decode(file_get_contents($versionFile), true);
    if (is_array($ex)) $versionData = $ex;
}
$versionData['Version']     = (int)($versionData['Version'] ?? 0) + 1;
$versionData['PublishedOn'] = date('M j, Y g:i A');
file_put_contents($versionFile, json_encode($versionData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

$versionLabel = 'v' . $versionData['Version'] . ' · ' . $versionData['PublishedOn'];

$gwriteOut = runPy($pythonPath, $pushDir . '/gwrite.py', $sheetId . 'version' . $versionData['Version']);
echo ($gwriteOut !== '' ? $gwriteOut : 'WARN: gwrite.py returned no output') . "\n";

// ── Ensure sheet dir exists ───────────────────────────────────────────────────
$sheetDir = $root . '/sheets/' . $sheetId;
if (!is_dir($sheetDir) && !mkdir($sheetDir, 0777, true)) {
    echo "ERROR: could not create sheets/" . $sheetId . "/\n";
    exit(1);
}

// ── Step 2: Core sheets — always pulled ──────────────────────────────────────
echo "─────────────────────────────\n";
pullSheet('settings');
pullSheet('games');
pullSheet('site');

// ── Step 3: Extra sheets from ?sheets= ───────────────────────────────────────
foreach ($extraSheets as $name) {
    pullSheet($name);
}

// ── Step 4: Per-game tabs from games.json ─────────────────────────────────────
$gamesFile = $sheetDir . '/games.json';
if (!file_exists($gamesFile)) {
    echo "─────────────────────────────\n";
    echo "SKIP: games.json not found — cannot pull per-game tabs\n";
} else {
    $games = json_decode(file_get_contents($gamesFile), true);
    if (!is_array($games)) {
        echo "─────────────────────────────\n";
        echo "WARN: games.json could not be parsed\n";
    } else {
        $gameTabs = [];
        foreach ($games as $game) {
            $name = trim($game['Name'] ?? '');
            if ($name !== '' && !in_array(strtolower($name), array_map('strtolower', $gameTabs), true)) {
                $gameTabs[] = $name;
            }
        }
        if (!empty($gameTabs)) {
            echo "─────────────────────────────\n";
            echo "Pulling " . count($gameTabs) . " game tab(s)…\n";
            foreach ($gameTabs as $tab) {
                pullSheet($tab);
            }
        }
    }
}

// ── Step 5: Cache media files ─────────────────────────────────────────────────
$cacheScript = $pushDir . '/cachemedia.py';
echo "─────────────────────────────\n";
if (!file_exists($cacheScript)) {
    echo "SKIP: cachemedia.py not found\n";
} else {
    $cmd = escapeshellarg($pythonPath) . ' ' . escapeshellarg($cacheScript) . ' ' . escapeshellarg($sheetId) . ' 2>/dev/null';
    $out = trim((string) shell_exec($cmd));
    if ($out === '') {
        echo "WARN: cachemedia.py returned no output\n";
    } else {
        foreach (explode("\n", $out) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if (str_starts_with($line, 'OK'))     echo "  ✓ " . $line . "\n";
            elseif (str_starts_with($line, 'CACHED')) echo "  – " . $line . "\n";
            elseif (str_starts_with($line, 'FAIL'))  echo "  ✗ " . $line . "\n";
            elseif (str_starts_with($line, 'ERROR')) echo "  ✗ " . $line . "\n";
            else                                      echo "  " . $line . "\n";
        }
    }
}

// ── Step 6: Copy source/site/ → sheets/{id}/site/ ────────────────────────────
$sourceSiteDir = $root . '/source/site';
$destSiteDir   = $sheetDir . '/site';

echo "─────────────────────────────\n";
if (!is_dir($sourceSiteDir)) {
    echo "SKIP: source/site/ not found — skipping site deploy\n";
} else {
    if (!is_dir($destSiteDir)) mkdir($destSiteDir, 0777, true);
    $copied = 0;
    $failed = 0;
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceSiteDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iter as $item) {
        $rel  = substr($item->getPathname(), strlen($sourceSiteDir) + 1);
        $dest = $destSiteDir . '/' . $rel;
        if ($item->isDir()) {
            if (!is_dir($dest)) mkdir($dest, 0777, true);
        } else {
            if (copy($item->getPathname(), $dest)) $copied++;
            else { $failed++; echo "WARN: could not copy site/" . $rel . "\n"; }
        }
    }
    echo "OK: site files copied (" . $copied . " file" . ($copied !== 1 ? 's' : '') . ")"
       . ($failed ? ", " . $failed . " failed" : "") . "\n";
}

// ── Done ──────────────────────────────────────────────────────────────────────
echo "─────────────────────────────\n";
echo "Done — " . $versionLabel . "\n";
