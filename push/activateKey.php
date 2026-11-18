<?php
/**
 * activateKey.php — unlock Pro for a sheet with an activation key (all apps).
 *
 * POST params: id — sheet ID, key — the activation key (any capitalization)
 * Response: {ok: true} | {error: "…"}
 */
error_reporting(0);
header('Content-Type: application/json');
require_once __DIR__ . '/stripeCommon.php';

$sheetId = trim($_POST['id'] ?? '');
$key     = (string) ($_POST['key'] ?? '');
if (!stripeValidSheetId($sheetId) || !is_dir(dirname(__DIR__) . '/sheets/' . $sheetId)) stripeFail('Open this from your sheet first');
if (trim($key) === '' || strlen($key) > 200) stripeFail('Enter an activation key');

// Slow down guessing: at most 10 tries per sheet per hour
$tries = dirname(__DIR__) . '/sheets/' . $sheetId . '/activation-tries.json';
$log   = json_decode((string) @file_get_contents($tries), true) ?: [];
$log   = array_values(array_filter($log, function ($t) { return $t > time() - 3600; }));
if (count($log) >= 10) stripeFail('Too many tries — please wait an hour and try again');

if (!activationKeyValid($key)) {
    $log[] = time();
    @file_put_contents($tries, json_encode($log));
    usleep(700000);
    stripeFail('That activation key isn’t valid');
}

file_put_contents(activationFile($sheetId), json_encode([
    'activated' => gmdate('c'),
    'key'       => substr(hash('sha256', strtolower(trim($key))), 0, 12),   // which key, without storing it
    'ip'        => substr(hash('sha256', $_SERVER['REMOTE_ADDR'] ?? ''), 0, 12),
]));
@unlink($tries);
echo json_encode(['ok' => true]);
