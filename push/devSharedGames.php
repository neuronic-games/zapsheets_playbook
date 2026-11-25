<?php
/**
 * devSharedGames.php — the DevBoard games shared with you, kept with your GameBoard account so the list
 * follows you to every device. A game is added when you open its share link while signed in.
 *
 * POST cbt (the GameBoard sign-in token), action:
 *   add    — tokens (comma separated share-link tokens, 24 hex) → {ok, games}
 *   remove — token                                            → {ok, games}
 *   list   —                                                  → {ok, games}
 * games = [{t, g game, s shared by, i picture, url}] — only links that still exist, A–Z
 */
error_reporting(0);
header('Content-Type: application/json');
require_once __DIR__ . '/gameboardAuth.php';

function out(array $d): void { echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }

$p = cbReadToken(cbRequestToken());
if (!$p || ($p['x'] ?? 0) < time() - 30 * 86400) out(['error' => 'Please sign in again', 'need_auth' => true]);
$email = strtolower(trim((string) $p['e']));
$mv = json_decode((string) @file_get_contents(cbPrivateDir('moved') . '/' . hash('sha256', $email) . '.json'), true);
if ($mv && (($p['x'] ?? 0) - CB_TOKEN_HOURS * 3600) <= ($mv['t'] ?? 0)) out(['error' => 'Please sign in again', 'need_auth' => true]);

$root   = dirname(__DIR__);
$shares = $root . '/shares/dev-collab/';
$file   = cbPrivateDir('shared-games') . '/' . hash('sha256', $email) . '.json';
$have   = json_decode((string) @file_get_contents($file), true) ?: [];   // token → time added
$valid  = function ($t) use ($shares) { return is_string($t) && preg_match('/^[a-f0-9]{24}$/', $t) && is_file($shares . $t . '.json'); };

$action = (string) ($_POST['action'] ?? 'list');
$changed = false;
if ($action === 'add') {
    foreach (array_slice(explode(',', (string) ($_POST['tokens'] ?? '')), 0, 60) as $t) {
        $t = trim($t);
        if ($valid($t) && !isset($have[$t])) { $have[$t] = time(); $changed = true; }
    }
} elseif ($action === 'remove') {
    $t = trim((string) ($_POST['token'] ?? ''));
    if (isset($have[$t])) { unset($have[$t]); $changed = true; }
}

// Only links that still exist; the game's name, who shared it and its picture
$games = [];
$gamesCache = [];
foreach (array_keys($have) as $t) {
    if (!$valid($t)) { unset($have[$t]); $changed = true; continue; }
    $m = json_decode((string) @file_get_contents($shares . $t . '.json'), true) ?: [];
    $sid = (string) ($m['sheet_id'] ?? ''); $g = (string) ($m['game'] ?? '');
    if ($sid === '' || $g === '') continue;
    $img = '';
    if (!isset($gamesCache[$sid])) $gamesCache[$sid] = json_decode((string) @file_get_contents($root . '/sheets/' . basename($sid) . '/games.json'), true) ?: [];
    foreach ($gamesCache[$sid] as $row) {
        if (strcasecmp(trim((string) ($row['Name'] ?? '')), $g) !== 0) continue;
        $raw = trim((string) ($row['Image URL'] ?? $row['Image'] ?? $row['ImageURL'] ?? ''));
        $img = preg_match('/^=IMAGE\("([^"]*)"\)$/i', $raw, $im) ? $im[1] : $raw;
        if (!preg_match('#^https?://#', $img)) $img = '';
        break;
    }
    $games[] = ['t' => $t, 'g' => $g, 's' => (string) ($m['sharer'] ?? ''), 'i' => $img, 'url' => rtrim(gbLinkBase(), '/') . '/devboard/share/' . $t];
}
if ($changed) @file_put_contents($file, json_encode($have), LOCK_EX);
usort($games, function ($a, $b) { return strcasecmp($a['g'], $b['g']); });
out(['ok' => true, 'games' => $games]);
