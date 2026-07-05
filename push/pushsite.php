<?php
/**
 * push/pushsite.php — site publisher with streaming HTML output.
 *
 * Usage (GET or POST):
 *   /pushsite?id=<sheetId>
 *   /pushsite?id=<sheetId>&sheets=pitches,people   ← extra sheets on top
 *
 * Outputs the same Bootstrap page layout as /push, with log lines
 * streamed to the browser as each step completes.
 */

// router.php includes PHP files inside serveFile() — a function scope.
// Variables used by pullSheet() via `global` must be declared global here
// so they land in PHP's global scope rather than serveFile()'s local scope.
global $pythonPath, $pushDir, $sheetDir, $sheetId, $pulled, $KV_SHEETS;

// ── Streaming setup ───────────────────────────────────────────────────────────
// Flush PHP's own output buffer layers so lines reach the browser immediately.
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', false);
@ini_set('implicit_flush', true);
while (@ob_end_flush()) {}
ob_implicit_flush(true);

// Tell Nginx not to buffer this response.
header('X-Accel-Buffering: no');
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-cache');

error_reporting(0);
ini_set('display_errors', '0');

require __DIR__ . '/../dotEnv.php';

$root       = dirname(__DIR__);
$pythonPath = $_ENV['PYTHON'] ?? 'python3';
$pushDir    = $root . '/push';

// ── Parameters ────────────────────────────────────────────────────────────────
$sheetId     = trim($_REQUEST['id']     ?? '');
$sheetsParam = trim($_REQUEST['sheets'] ?? $_REQUEST['sheet'] ?? '');

$extraSheets = $sheetsParam !== ''
    ? array_values(array_filter(array_map('trim', explode(',', $sheetsParam))))
    : [];

// ── Page shell (emitted immediately so the browser renders the chrome) ────────
// A padding comment ensures the initial chunk is large enough to flush through
// any upstream proxy buffers (most need ≥ 4 KB before they start sending).
$pad = str_repeat(' ', 4096);
echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Publish Site</title>
  <link rel="stylesheet" href="../css/bootstrap.min.css">
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" type="image/x-icon" href="../images/sheet_2_new.webp">
  <style>
    .log-wrap {
      background: #111827;
      border-radius: 8px;
      padding: 1.25rem 1.5rem;
      font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
      font-size: .82rem;
      line-height: 1.7;
      color: #d1d5db;
      min-height: 200px;
    }
    .log-line   { white-space: pre-wrap; word-break: break-all; }
    .log-ok     { color: #4ade80; }
    .log-warn   { color: #fbbf24; }
    .log-error  { color: #f87171; font-weight: 600; }
    .log-skip   { color: #6b7280; }
    .log-sep    { color: #374151; border-top: 1px solid #1f2937; margin: .25rem 0; }
    .log-done   { color: #38bdf8; font-weight: 600; }
    .log-info   { color: #a78bfa; }
    .ps-badge {
      display: inline-block; font-size: .7rem; font-weight: 600;
      padding: .15rem .5rem; border-radius: 3px; margin-right: .4rem;
      text-transform: uppercase; letter-spacing: .05em; vertical-align: middle;
    }
    .badge-ok    { background: #166534; color: #4ade80; }
    .badge-warn  { background: #713f12; color: #fbbf24; }
    .badge-error { background: #7f1d1d; color: #f87171; }
    .badge-skip  { background: #1f2937; color: #9ca3af; }
  </style>
  <!-- $pad -->
</head>
<body class="page_greek d-flex flex-column min-vh-100" style="background-color:#fff !important;">

<header id="page-header" class="game_header pt-4">
  <div class="container">
    <div class="game_logo">
      <img src="../images/step_icon_new.webp" alt="" class="img-fluid mr-3" width="60">
      <img src="../images/zapsheets.png" alt="" class="img-fluid" width="250">
    </div>
    <h1 class="h2 header_title mt-md-5 mt-4 font-poppins">Publishing Site…</h1>
  </div>
</header>

<div class="container mt-4 mb-5">
  <div class="log-wrap" id="logWrap">

HTML;
flush();

// ── Log helper ────────────────────────────────────────────────────────────────
function logLine(string $text): void {
    if ($text === '' || preg_match('/^─+$/', $text)) {
        echo '<div class="log-line log-sep"></div>' . "\n";
    } elseif (str_starts_with($text, 'OK')) {
        $rest = htmlspecialchars(substr($text, 2), ENT_QUOTES, 'UTF-8');
        echo '<div class="log-line log-ok"><span class="ps-badge badge-ok">OK</span>' . ltrim($rest, ': ') . '</div>' . "\n";
    } elseif (str_starts_with($text, 'WARN')) {
        $rest = htmlspecialchars(substr($text, 4), ENT_QUOTES, 'UTF-8');
        echo '<div class="log-line log-warn"><span class="ps-badge badge-warn">WARN</span>' . ltrim($rest, ': ') . '</div>' . "\n";
    } elseif (str_starts_with($text, 'ERROR')) {
        $rest = htmlspecialchars(substr($text, 5), ENT_QUOTES, 'UTF-8');
        echo '<div class="log-line log-error"><span class="ps-badge badge-error">ERROR</span>' . ltrim($rest, ': ') . '</div>' . "\n";
    } elseif (str_starts_with($text, 'SKIP')) {
        $rest = htmlspecialchars(substr($text, 4), ENT_QUOTES, 'UTF-8');
        echo '<div class="log-line log-skip"><span class="ps-badge badge-skip">SKIP</span>' . ltrim($rest, ': ') . '</div>' . "\n";
    } elseif (str_starts_with($text, 'Done')) {
        echo '<div class="log-line log-done">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</div>' . "\n";
    } elseif (str_starts_with($text, 'Pulling') || str_starts_with($text, 'Publish')) {
        echo '<div class="log-line log-info">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</div>' . "\n";
    } else {
        echo '<div class="log-line">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</div>' . "\n";
    }
    flush();
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function runPy(string $pythonPath, string $script, string $arg): string {
    $cmd = escapeshellarg($pythonPath) . ' '
         . escapeshellarg($script) . ' '
         . escapeshellarg($arg) . ' 2>/dev/null';
    return trim((string) shell_exec($cmd));
}

$pulled    = [];
$KV_SHEETS = ['site'];

function pullSheet(string $sheetName): void {
    global $pythonPath, $pushDir, $sheetDir, $sheetId, $pulled, $KV_SHEETS;

    $key = strtolower($sheetName);
    if (in_array($key, $pulled, true)) return;
    $pulled[] = $key;

    $script = in_array($key, $KV_SHEETS, true) ? 'greadkv.py' : 'gread.py';
    $out    = runPy($pythonPath, $pushDir . '/' . $script, $sheetId . 'sheetname' . $sheetName);

    if ($out === '') {
        logLine('SKIP: ' . $sheetName . ' — no output from ' . $script);
        return;
    }
    $decoded = json_decode($out, true);
    if (is_array($decoded) && isset($decoded['error'])) {
        logLine('ERROR: ' . $sheetName . ': ' . $decoded['error']);
        return;
    }
    $jsonFile = $sheetDir . '/' . $key . '.json';
    logLine(file_exists($jsonFile) ? 'OK: ' . $sheetName : 'WARN: file missing after ' . $script . ' — ' . $sheetName);
}

// ── Validate ID ───────────────────────────────────────────────────────────────
if ($sheetId === '') {
    logLine('ERROR: missing ?id= parameter');
    echo '</div></div></body></html>';
    exit(1);
}

// ── Step 1: Increment version + write to Google Sheet ────────────────────────
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
logLine($gwriteOut !== '' ? $gwriteOut : 'WARN: gwrite.py returned no output');

// ── Ensure sheet dir exists ───────────────────────────────────────────────────
$sheetDir = $root . '/sheets/' . $sheetId;
if (!is_dir($sheetDir) && !mkdir($sheetDir, 0777, true)) {
    logLine('ERROR: could not create sheets/' . $sheetId . '/');
    echo '</div></div></body></html>';
    exit(1);
}

// ── Step 2: Core sheets ───────────────────────────────────────────────────────
logLine('─────────────────────────────');
pullSheet('settings');
pullSheet('games');
pullSheet('site');

// ── Step 2b: Optional sheets (pulled only when the tab exists in the sheet) ───
function sheetExists(string $pythonPath, string $pushDir, string $sheetId, string $sheetName): bool {
    $cmd = escapeshellarg($pythonPath) . ' '
         . escapeshellarg($pushDir . '/checkSheetStatus.py') . ' '
         . escapeshellarg($sheetId . 'sheetname' . $sheetName) . ' 2>/dev/null';
    $out = trim((string) shell_exec($cmd));
    $chk = json_decode($out, true);
    return ($chk['exists'] ?? '') === 'yes';
}

$OPTIONAL_SHEETS = ['News', 'About'];
foreach ($OPTIONAL_SHEETS as $opt) {
    if (sheetExists($pythonPath, $pushDir, $sheetId, $opt)) {
        logLine('Pulling optional: ' . $opt);
        pullSheet($opt);
    } else {
        logLine('SKIP: ' . $opt . ' tab not found in sheet');
    }
}

// ── Step 3: Extra sheets from ?sheets= ───────────────────────────────────────
foreach ($extraSheets as $name) {
    pullSheet($name);
}

// ── Step 4: Per-game tabs ─────────────────────────────────────────────────────
$gamesFile = $sheetDir . '/games.json';
if (!file_exists($gamesFile)) {
    logLine('─────────────────────────────');
    logLine('SKIP: games.json not found — cannot pull per-game tabs');
} else {
    $games = json_decode(file_get_contents($gamesFile), true);
    if (!is_array($games)) {
        logLine('─────────────────────────────');
        logLine('WARN: games.json could not be parsed');
    } else {
        $gameTabs = [];
        foreach ($games as $game) {
            $name = trim($game['Name'] ?? '');
            if ($name !== '' && !in_array(strtolower($name), array_map('strtolower', $gameTabs), true)) {
                $gameTabs[] = $name;
            }
        }
        if (!empty($gameTabs)) {
            logLine('─────────────────────────────');
            logLine('Pulling ' . count($gameTabs) . ' game tab(s)…');
            foreach ($gameTabs as $tab) {
                pullSheet($tab);
            }
        }
    }
}

// ── Step 5: BGG data ──────────────────────────────────────────────────────────
$bggScript = $pushDir . '/fetchbgg.py';
logLine('─────────────────────────────');
if (!file_exists($bggScript)) {
    logLine('SKIP: fetchbgg.py not found');
} else {
    $cmd = escapeshellarg($pythonPath) . ' ' . escapeshellarg($bggScript) . ' ' . escapeshellarg($sheetId) . ' 2>/dev/null';
    $out = trim((string) shell_exec($cmd));
    if ($out === '') {
        logLine('WARN: fetchbgg.py returned no output');
    } else {
        foreach (explode("\n", $out) as $line) {
            $line = trim($line);
            if ($line !== '') logLine($line);
        }
    }
}

// ── Step 6: Cache media ───────────────────────────────────────────────────────
$cacheScript = $pushDir . '/cachemedia.py';
logLine('─────────────────────────────');
if (!file_exists($cacheScript)) {
    logLine('SKIP: cachemedia.py not found');
} else {
    $cmd = escapeshellarg($pythonPath) . ' ' . escapeshellarg($cacheScript) . ' ' . escapeshellarg($sheetId) . ' 2>/dev/null';
    $out = trim((string) shell_exec($cmd));
    if ($out === '') {
        logLine('WARN: cachemedia.py returned no output');
    } else {
        foreach (explode("\n", $out) as $line) {
            $line = trim($line);
            if ($line !== '') logLine($line);
        }
    }
}

// ── Step 7: Copy source/site/ → sheets/{id}/site/ ────────────────────────────
$sourceSiteDir = $root . '/source/site';
$destSiteDir   = $sheetDir . '/site';

logLine('─────────────────────────────');
if (!is_dir($sourceSiteDir)) {
    logLine('SKIP: source/site/ not found — skipping site deploy');
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
            else { $failed++; logLine('WARN: could not copy site/' . $rel); }
        }
    }
    logLine('OK: site files copied (' . $copied . ' file' . ($copied !== 1 ? 's' : '') . ')'
          . ($failed ? ', ' . $failed . ' failed' : ''));
}

// ── Done ──────────────────────────────────────────────────────────────────────
logLine('─────────────────────────────');
logLine('Done — ' . $versionLabel);

// ── Close page ────────────────────────────────────────────────────────────────
echo <<<HTML

  </div><!-- /.log-wrap -->
</div><!-- /.container -->

<footer id="page-footer" class="game_footer mt-auto pb-4">
  <div class="container">
    <div class="copyright text-center text-light"></div>
  </div>
</footer>

<script>
  // Auto-scroll to bottom as lines stream in, stop once done.
  (function () {
    var done  = false;
    var wrap  = document.getElementById('logWrap');
    function tick() {
      window.scrollTo(0, document.body.scrollHeight);
      if (!done) requestAnimationFrame(tick);
    }
    tick();
    // Mark done when the last .log-done line appears (MutationObserver).
    var obs = new MutationObserver(function () {
      if (wrap.querySelector('.log-done')) { done = true; obs.disconnect(); }
    });
    obs.observe(wrap, { childList: true });
  })();
</script>
</body>
</html>
HTML;
flush();
