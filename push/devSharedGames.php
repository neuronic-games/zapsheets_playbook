<?php
/**
 * devSharedGames.php — the DevBoard games shared with you, kept with your GameBoard account so the list
 * follows you to every device. A game is added when you open its share link while signed in.
 *
 * POST cbt (the GameBoard sign-in token), action:
 *   add    — tokens (comma separated share-link tokens, 24 hex) → {ok, games}
 *   remove — token                                            → {ok, games}
 *   list   —                                                  → {ok, games}
 * games = [{t, g game, s shared by, i picture, d designers, url}] — only links that still exist, A–Z
 * own   = [{g game, i picture, d designers, sheet, url}] — the games in your own DevBoard sheets, when your account can use
 *         DevBoard (subscribed, the free week, a code or activated); devboard = whether it can
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
$desOf = function (array $row): string {
    return implode(', ', array_filter(array_map(function ($k) use ($row) { return trim((string) ($row['Designer' . $k] ?? $row['Designer ' . $k] ?? '')); }, [1, 2, 3, 4])));
};
$imgOf = function (array $row): string {
    $raw = trim((string) ($row['Image URL'] ?? $row['Image'] ?? $row['ImageURL'] ?? ''));
    $img = preg_match('/^=IMAGE\("([^"]*)"\)$/i', $raw, $im) ? $im[1] : $raw;
    return preg_match('#^https?://#', $img) ? $img : '';
};
foreach (array_keys($have) as $t) {
    if (!$valid($t)) { unset($have[$t]); $changed = true; continue; }
    $m = json_decode((string) @file_get_contents($shares . $t . '.json'), true) ?: [];
    $sid = (string) ($m['sheet_id'] ?? ''); $g = (string) ($m['game'] ?? '');
    if ($sid === '' || $g === '') continue;
    $img = ''; $des = '';
    if (!isset($gamesCache[$sid])) $gamesCache[$sid] = json_decode((string) @file_get_contents($root . '/sheets/' . basename($sid) . '/games.json'), true) ?: [];
    foreach ($gamesCache[$sid] as $row) {
        if (strcasecmp(trim((string) ($row['Name'] ?? '')), $g) !== 0) continue;
        $img = $imgOf($row); $des = $desOf($row);
        break;
    }
    $games[] = ['t' => $t, 'g' => $g, 's' => (string) ($m['sharer'] ?? ''), 'i' => $img, 'd' => $des, 'url' => rtrim(gbLinkBase(), '/') . '/devboard/share/' . $t];
}
if ($changed) @file_put_contents($file, json_encode($have), LOCK_EX);
usort($games, function ($a, $b) { return strcasecmp($a['g'], $b['g']); });

// Your own games: every game in the DevBoard sheets you set up — only while your account can use DevBoard
$acc = gbAppAccess($p, 'devboard');
$canOwn = in_array($acc['st'] ?? '', ['subscribed', 'code', 'trial', 'activated'], true) && (int) ($acc['u'] ?? 0) > time();
$own = [];
if ($canOwn) {
    $seen = [];
    foreach ((array) ($p['sh'] ?? []) as $sid) {
        $sid = (string) $sid;
        if (!stripeValidSheetId($sid)) continue;
        $dir = $root . '/sheets/' . $sid;
        $apps = json_decode((string) @file_get_contents($dir . '/_gb_apps.json'), true) ?: [];
        $isDev = isset($apps['devboard']) || is_file($dir . '/contracts.json') || is_file($dir . '/todos.json') || is_file($dir . '/estimates.json')
              || (bool) (@glob($dir . '/*dev.json') ?: []);
        if (!$isDev) continue;
        $rows = json_decode((string) @file_get_contents($dir . '/games.json'), true) ?: [];
        foreach ($rows as $row) {
            $g = trim((string) ($row['Name'] ?? ''));
            if ($g === '' || isset($seen[strtolower($sid . '|' . $g)])) continue;
            $seen[strtolower($sid . '|' . $g)] = 1;
            $own[] = ['g' => $g, 'i' => $imgOf($row), 'd' => $desOf($row), 'sheet' => $sid, 'url' => rtrim(gbLinkBase(), '/') . '/' . $sid . '/devboard#game=' . rawurlencode($g)];
        }
    }
    usort($own, function ($a, $b) { return strcasecmp($a['g'], $b['g']); });
}
out(['ok' => true, 'games' => $games, 'own' => $own, 'devboard' => $canOwn]);
