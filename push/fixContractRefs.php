<?php
/**
 * fixContractRefs.php — contracts made before Ref Numbers were codes (a plain number, or none) get a standard
 * 8-character code; the local caches follow. POST id → {ok, changed: [{row, old, new}]}
 */
require_once __DIR__ . '/gbGate.php'; gbGate(['devboard']);   // GameBoard account: signed in, the sheet's owner, the app unlocked
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');
require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/refreshJson.php';

$sheetId = trim($_POST['id'] ?? '');
if (!preg_match('/^[A-Za-z0-9_-]{20,80}$/', $sheetId)) { echo json_encode(['error' => 'Missing sheet']); exit; }
$python = $_ENV['PYTHON'] ?? 'python3';
$out = trim((string) shell_exec(escapeshellarg($python) . ' ' . escapeshellarg(__DIR__ . '/gfixcontractrefs.py') . ' ' . escapeshellarg($sheetId) . ' 2>&1'));
$r = null;
foreach (array_reverse(preg_split('/\R/', $out)) as $line) { if ($line !== '' && $line[0] === '{' && is_array($j = json_decode($line, true))) { $r = $j; break; } }
if (!$r || empty($r['ok'])) { echo json_encode(['error' => $r['error'] ?? ('Couldn’t update the Ref Numbers' . ($out !== '' ? ': ' . mb_substr($out, -200) : ''))]); exit; }

if (!empty($r['changed'])) {
    // The documents DevBoard keeps for a contract (estimates.json) follow the new codes
    $map = [];
    foreach ($r['changed'] as $c) if ((string) $c['old'] !== '') $map[(string) $c['old']] = $c['new'];
    $f = dirname(__DIR__) . '/sheets/' . $sheetId . '/estimates.json';
    if ($map && is_file($f)) {
        $ests = json_decode((string) file_get_contents($f), true) ?: [];
        $hit = false;
        foreach ($ests as &$e) {
            foreach (['contract_id', 'from_estimate'] as $k) if (isset($e[$k], $map[(string) $e[$k]])) { $e[$k] = $map[(string) $e[$k]]; $hit = true; }
        }
        unset($e);
        if ($hit) file_put_contents($f, json_encode($ests, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
    refreshJson($python, $sheetId, 'contracts');
}
echo json_encode(['ok' => true, 'changed' => $r['changed']]);
