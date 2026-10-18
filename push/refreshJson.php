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

/**
 * @return string|null  null on success; error message string on failure.
 */
function refreshJson($pythonPath, $sheetId, $tabName): ?string {
    $script = __DIR__ . '/gread.py';
    $arg    = $sheetId . 'sheetname' . $tabName;
    $cmd    = escapeshellarg($pythonPath) . ' '
            . escapeshellarg($script) . ' '
            . escapeshellarg($arg) . ' 2>/dev/null';
    $out    = trim((string) shell_exec($cmd));
    if ($out === '') return 'gread.py returned no output';

    // Guard: only write if the output is a JSON array (list of records).
    // If gread.py hit an error it returns {"error":"..."} — an object, not an array.
    // Never overwrite a good cache file with an error response.
    $decoded = json_decode($out, true);
    if (!is_array($decoded)) return 'gread.py output is not valid JSON';
    if (!array_is_list($decoded)) {
        // It's a JSON object ({"error":"..."}) — do NOT overwrite the cache.
        $errMsg = $decoded['error'] ?? 'unknown error from gread.py';
        return $errMsg;
    }

    $dir = dirname(__DIR__) . '/sheets/' . $sheetId;
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    file_put_contents($dir . '/' . strtolower($tabName) . '.json', $out);
    return null;
}
?>
