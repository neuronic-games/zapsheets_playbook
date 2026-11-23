<?php
/**
 * linkPreview.php — the card a link shows when it's pasted into Discord, Slack, iMessage, social media…
 *
 *   require_once …/push/linkPreview.php;
 *   echo gbLinkPreview('cardboard', $sheetId);                 // in <head>: the app's logo, and the sheet's name and contents
 *   echo gbLinkPreview('devboard', $sheetId, ['title' => …]);  // or say what to show
 *
 * Apps: cardboard, devboard, pitchboard, billboard. What it says comes from what this server keeps for the
 * sheet (sheets/{id}/): its name, decks, games, playtests, pitches — whatever there is. The picture is the
 * app's logo, at the GameBoard address (mygameboard.app) so it loads wherever the link is shared.
 */
require_once dirname(__DIR__) . '/dotEnv.php';

const GB_PREVIEW_APPS = [
    // the app's name, its logo, and its tag line (the headline of its features page)
    'cardboard'  => ['CardBoard',  'cardboard_icon_512.png',  'Design, print, and share your card deck — fast.'],
    'devboard'   => ['DevBoard',   'db_icon_512.png',         'Every playtest, remembered.'],
    'pitchboard' => ['PitchBoard', 'pitchboard_icon_512.png', 'From first sketch to signed deal — tracked.'],
    'billboard'  => ['BillBoard',  'bb_icon_512.png',         'Your games. A polished page — instantly.'],
];

function gbPreviewJson(string $sheetId, string $file): array {
    if (!preg_match('/^[A-Za-z0-9_\-]{10,128}$/', $sheetId)) return [];
    $f = dirname(__DIR__) . '/sheets/' . $sheetId . '/' . $file;
    return is_file($f) ? (json_decode((string) @file_get_contents($f), true) ?: []) : [];
}
function gbPreviewClean($v): string { return trim(ltrim(trim((string) $v), "'")); }
// "A, B and 3 more"
function gbPreviewList(array $names, int $show = 3): string {
    $names = array_values(array_filter(array_map('gbPreviewClean', $names), 'strlen'));
    $n = count($names); if (!$n) return '';
    $head = array_slice($names, 0, $show);
    if ($n > $show) return implode(', ', $head) . ' and ' . ($n - $show) . ' more';
    if ($n === 1) return $head[0];
    return implode(', ', array_slice($head, 0, -1)) . ' and ' . end($head);
}
function gbPreviewPlural(int $n, string $one, string $many = ''): string { return $n . ' ' . ($n === 1 ? $one : ($many ?: $one . 's')); }

// The sheet's own name and what's in it, from what the server keeps
function gbSheetSummary(string $app, string $sheetId): array {
    $title = ''; $parts = [];
    $settings = gbPreviewJson($sheetId, 'settings.json'); $who = '';
    foreach ($settings as $s) {
        $n = $s['Name'] ?? $s['name'] ?? ''; $v = gbPreviewClean($s['Value'] ?? $s['value'] ?? '');
        if ($n === 'Company' && $v !== '') $who = $v;
        if ($n === 'My Name' && $v !== '' && $who === '') $who = $v;
    }
    $games = array_values(array_filter(gbPreviewJson($sheetId, 'games.json'), function ($g) { return gbPreviewClean($g['Name'] ?? '') !== ''; }));
    $gameNames = array_map(function ($g) { return $g['Name'] ?? ''; }, $games);

    if ($app === 'cardboard') {
        $info = gbPreviewJson($sheetId, '_cardboard_info.json');
        $title = gbPreviewClean($info['title'] ?? '');
        if ($title === '' && preg_match('/^[A-Za-z0-9_\-]{10,128}$/', $sheetId)) $title = trim((string) @file_get_contents(dirname(__DIR__) . '/sheets/' . $sheetId . '/_cardboard_title.txt'));
        $decks = $info['decks'] ?? [];
        if ($decks) $parts[] = gbPreviewPlural(count($decks), 'deck') . ': ' . gbPreviewList($decks);
        $designed = $info['designed'] ?? [];
        if ($designed) $parts[] = gbPreviewPlural(count($designed), 'card design');
    } elseif ($app === 'devboard') {
        $title = $who;
        if ($games) $parts[] = gbPreviewPlural(count($games), 'game') . ': ' . gbPreviewList($gameNames);
        $people = gbPreviewJson($sheetId, 'people.json');
        if ($people) $parts[] = gbPreviewPlural(count($people), 'collaborator');
    } elseif ($app === 'pitchboard') {
        $title = $who;
        if ($games) $parts[] = gbPreviewPlural(count($games), 'game') . ': ' . gbPreviewList($gameNames);
        $pitches = gbPreviewJson($sheetId, 'pitches.json');
        if ($pitches) {
            $pubs = array_unique(array_filter(array_map(function ($p) { return gbPreviewClean($p['Publisher'] ?? ''); }, $pitches), 'strlen'));
            $parts[] = gbPreviewPlural(count($pitches), 'pitch', 'pitches') . ($pubs ? ' to ' . gbPreviewPlural(count($pubs), 'publisher') : '');
        }
    } elseif ($app === 'billboard') {
        $title = $who;
        $pub = array_filter($games, function ($g) { return stripos((string) ($g['Status'] ?? ''), 'publish') !== false; });
        $show = $pub ?: $games;
        if ($show) $parts[] = gbPreviewPlural(count($show), 'game') . ': ' . gbPreviewList(array_map(function ($g) { return $g['Name'] ?? ''; }, $show));
    }
    return [$title, implode(' · ', $parts)];
}

function gbLinkPreview(string $app, string $sheetId = '', array $o = []): string {
    $a = GB_PREVIEW_APPS[$app] ?? GB_PREVIEW_APPS['cardboard'];
    [$sheetTitle, $what] = $sheetId !== '' ? gbSheetSummary($app, $sheetId) : ['', ''];
    $title = $o['title'] ?? ($sheetTitle !== '' ? $sheetTitle : $a[0]);   // the sheet's own name (the app shows above it)
    $desc  = $o['description'] ?? trim(($what !== '' ? $what . '. ' : '') . $a[2]);
    $base  = function_exists('gbLinkBase') ? gbLinkBase() : '/';
    $img   = $o['image'] ?? ($base . 'images/' . $a[1]);
    $e = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES); };
    $desc = mb_substr($desc, 0, 300);
    return "\n" .
        '<meta name="description" content="' . $e($desc) . '" />' . "\n" .
        '<meta property="og:site_name" content="GameBoard · ' . $e($a[0]) . '" />' . "\n" .
        '<meta property="og:type" content="website" />' . "\n" .
        '<meta property="og:title" content="' . $e($title) . '" />' . "\n" .
        '<meta property="og:description" content="' . $e($desc) . '" />' . "\n" .
        '<meta property="og:image" content="' . $e($img) . '" />' . "\n" .
        '<meta property="og:image:alt" content="' . $e($a[0]) . ' logo" />' . "\n" .
        '<meta name="twitter:card" content="summary" />' . "\n" .
        '<meta name="twitter:title" content="' . $e($title) . '" />' . "\n" .
        '<meta name="twitter:description" content="' . $e($desc) . '" />' . "\n" .
        '<meta name="twitter:image" content="' . $e($img) . '" />' . "\n" .
        '<meta name="theme-color" content="#1a1a2e" />' . "\n";
}
