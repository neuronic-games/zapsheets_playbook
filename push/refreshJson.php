<?php
/**
 * refreshJson.php — re-reads a Google Sheet tab and saves it as the local JSON cache.
 *
 * Usage:  require_once __DIR__ . '/refreshJson.php';
 *         refreshJson($pythonPath, $sheetId, 'pitches');
 *
 * Calls gread.py (same script the sync process uses) and writes the result to
 * sheets/{sheetId}/{tabname}.json so the dashboard stays in sync after any write.
 */

/**
 * Generate an 8-char uppercase alphanumeric Ref Number.
 */
function tmGenRef(): string {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $ref = '';
    for ($i = 0; $i < 8; $i++) $ref .= $chars[random_int(0, 35)];
    return $ref;
}

function refreshJson($pythonPath, $sheetId, $tabName) {
    $script = __DIR__ . '/gread.py';
    $arg    = $sheetId . 'sheetname' . $tabName;
    $cmd    = escapeshellarg($pythonPath) . ' '
            . escapeshellarg($script) . ' '
            . escapeshellarg($arg) . ' 2>/dev/null';
    $out    = trim((string) shell_exec($cmd));
    if ($out === '') return;
    $dir = dirname(__DIR__) . '/sheets/' . $sheetId;
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    file_put_contents($dir . '/' . strtolower($tabName) . '.json', $out);
}
?>
