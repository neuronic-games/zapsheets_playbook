<?php
/**
 * cardboardSheets.php — the Designs page (/cardboard): the sheets someone has set up, and their pictures.
 *
 *   POST cbt (account token), seen (optional: comma-separated sheet IDs this browser has viewed)
 *        → {ok, sheets:[{id, title, decks:[…], designed:[…], edited (unix), thumb (url|''), mine (bool)}]}
 *   GET  ?thumb={id}           → the sheet's picture (its first card), or 404
 *   POST action=thumb, id, cbt, file (JPEG/PNG ≤ 600 KB) → {ok}   (the designer sends it after a save; owner only)
 */
error_reporting(0);
require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/gameboardAuth.php';
require_once __DIR__ . '/gameboardAuth.php'; gbBlockUninvited('cardboard');   // the Waiting Room: only the apps they were invited to

function csDir(string $id): string { return dirname(__DIR__) . '/sheets/' . $id; }
function csValid(string $id): bool { return (bool) preg_match('/^[A-Za-z0-9_\-]{20,128}$/', $id); }

// The picture: public, like the link preview (it's the first card of a deck shared by link)
if (isset($_GET['thumb'])) {
    $id = (string) $_GET['thumb'];
    $f = csDir($id) . '/_cardboard_thumb.jpg';
    if (!csValid($id) || !is_file($f)) { http_response_code(404); exit; }
    header('Content-Type: image/jpeg');
    header('Cache-Control: public, max-age=300');
    header('Content-Length: ' . filesize($f));
    readfile($f); exit;
}

header('Content-Type: application/json');
function csOut(array $a): void { echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }

$p = cbReadToken(cbRequestToken());
if (!$p) csOut(['error' => 'Sign in with your GameBoard account first.', 'need_auth' => true]);
if (($p['x'] ?? 0) < time()) csOut(['error' => 'Your sign-in needs refreshing.', 'need_auth' => true, 'refresh' => true]);
$mine = array_values(array_filter((array) ($p['sh'] ?? []), 'csValid'));

if (($_POST['action'] ?? '') === 'thumb') {
    $id = trim((string) ($_POST['id'] ?? ''));
    if (!csValid($id) || !in_array($id, $mine, true)) csOut(['error' => 'Only the sheet’s owner updates its picture']);
    if (!is_dir(csDir($id))) @mkdir(csDir($id), 0755, true);
    $tmp = $_FILES['file']['tmp_name'] ?? '';
    if (!$tmp || !is_uploaded_file($tmp) || filesize($tmp) > 600 * 1024) csOut(['error' => 'No picture']);
    $info = @getimagesize($tmp);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) csOut(['error' => 'Not a picture']);
    if (!@move_uploaded_file($tmp, csDir($id) . '/_cardboard_thumb.jpg')) csOut(['error' => 'Couldn’t keep the picture']);
    @touch(csDir($id) . '/_cardboard_edited');
    // the sheet's name and decks, as the designer has them (the tile shows them)
    $decks = json_decode((string) ($_POST['decks'] ?? ''), true);
    if (is_array($decks)) {
        $f = csDir($id) . '/_cardboard_info.json';
        $info = json_decode((string) @file_get_contents($f), true) ?: [];
        $info['decks'] = array_values(array_map(function ($d) { return mb_substr((string) $d, 0, 100); }, array_slice($decks, 0, 200)));
        $t = trim((string) ($_POST['title'] ?? '')); if ($t !== '') $info['title'] = mb_substr($t, 0, 200);
        $info += ['designed' => []];
        @file_put_contents($f, json_encode($info, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
    csOut(['ok' => true]);
}

// Yours (newest set-up first), then the ones this browser has looked at
$seen = array_filter(array_map('trim', explode(',', (string) ($_POST['seen'] ?? ''))), 'csValid');
$ids = array_values(array_unique(array_merge(array_reverse($mine), array_slice($seen, 0, 100))));
$out = [];
foreach ($ids as $id) {
    $d = csDir($id);
    $isMine = in_array($id, $mine, true);
    // Only sheets opened in CardBoard (the account's sheets are shared with DevBoard, PitchBoard, BillBoard)
    $used = false;
    foreach (['_cardboard_info.json', '_cardboard_title.txt', '_cardboard_thumb.jpg', '_cardboard_edited'] as $f) if (is_file($d . '/' . $f)) { $used = true; break; }
    if (!$used) continue;
    $info = json_decode((string) @file_get_contents($d . '/_cardboard_info.json'), true) ?: [];
    $title = trim((string) ($info['title'] ?? ''));
    if ($title === '') $title = trim((string) @file_get_contents($d . '/_cardboard_title.txt'));
    $edited = 0;
    foreach (['_cardboard_edited', '_cardboard_thumb.jpg', '_cardboard_info.json', '_cardboard_title.txt'] as $f) {
        if (is_file($d . '/' . $f)) $edited = max($edited, (int) filemtime($d . '/' . $f));
    }
    $hasThumb = is_file($d . '/_cardboard_thumb.jpg');
    $out[] = [
        'id' => $id, 'title' => $title, 'decks' => array_values((array) ($info['decks'] ?? [])), 'designed' => array_values((array) ($info['designed'] ?? [])),
        'edited' => $edited, 'mine' => $isMine,
        'thumb' => $hasThumb ? 'push/cardboardSheets.php?thumb=' . rawurlencode($id) . '&v=' . filemtime($d . '/_cardboard_thumb.jpg') : '',
    ];
}
csOut(['ok' => true, 'sheets' => $out]);
