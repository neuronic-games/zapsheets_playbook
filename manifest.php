<?php
header('Content-Type: application/manifest+json');
header('Cache-Control: no-cache');

$id   = preg_replace('/[^A-Za-z0-9_\-]/', '', $_GET['id']   ?? '');
$app  = trim($_GET['app']  ?? 'pitchboard');
$base = trim($_GET['base'] ?? '/');
if (substr($base, -1) !== '/') $base .= '/';

if ($app === 'cardboard') {
    // CardBoard: added to the Home Screen under the spreadsheet's name (kept by push/cardboardData.php)
    $name = trim((string) @file_get_contents(__DIR__ . '/sheets/' . $id . '/_cardboard_title.txt'));
    if ($name === '') $name = trim(mb_substr(preg_replace('/[\x00-\x1f<>]/u', '', (string) ($_GET['name'] ?? '')), 0, 80));
    if ($name === '') $name = 'CardBoard';
    echo json_encode([
        'name'             => $name,
        'short_name'       => mb_strlen($name) > 14 ? mb_substr($name, 0, 13) . '…' : $name,
        'id'               => 'CardBoard_' . $id,
        'start_url'        => $base . $id . '/cardboard',
        'scope'            => $base . $id . '/cardboard',
        'theme_color'      => '#2e2763',
        'background_color' => '#2e2763',
        'display'          => 'standalone',
        'icons'            => [
            ['src' => $base . 'images/cardboard_icon_192.png', 'sizes' => '192x192', 'type' => 'image/png'],
            ['src' => $base . 'images/cardboard_icon_512.png', 'sizes' => '512x512', 'type' => 'image/png'],
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} elseif ($app === 'billboard') {
    // BillBoard: the catalog (or its public link) on the Home Screen, under the studio's name
    $name  = trim(mb_substr(preg_replace('/[\x00-\x1f<>]/u', '', (string) ($_GET['name'] ?? '')), 0, 80)) ?: 'BillBoard';
    $start = (string) ($_GET['start'] ?? '');
    if ($start === '' || strpos($start, $base) !== 0 || preg_match('#[^A-Za-z0-9_\-/.]#', $start)) $start = $base . $id . '/billboard/';
    echo json_encode([
        'name'             => $name,
        'short_name'       => mb_strlen($name) > 14 ? mb_substr($name, 0, 13) . '…' : $name,
        'id'               => 'BillBoard_' . md5($start),
        'start_url'        => $start,
        'scope'            => $start,
        'theme_color'      => '#1a1a2e',
        'background_color' => '#1a1a2e',
        'display'          => 'standalone',
        'icons'            => [
            ['src' => $base . 'images/bb_icon_192.png', 'sizes' => '192x192', 'type' => 'image/png'],
            ['src' => $base . 'images/bb_icon_512.png', 'sizes' => '512x512', 'type' => 'image/png'],
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} elseif ($app === 'slides') {
    $startUrl = $base . $id . '/pitchboard/slides';
    echo json_encode([
        'name'             => 'PitchBoard Slides',
        'short_name'       => 'Slides',
        'id'               => 'PBSlides_' . $id,
        'start_url'        => $startUrl,
        'scope'            => $base . $id . '/pitchboard/slides',
        'theme_color'      => '#0a0a14',
        'background_color' => '#0a0a14',
        'display'          => 'standalone',
        'orientation'      => 'portrait',
        'icons'            => [
            ['src' => $base . 'images/pitchboard_icon_192.png', 'sizes' => '192x192', 'type' => 'image/png'],
            ['src' => $base . 'images/pitchboard_icon_512.png', 'sizes' => '512x512', 'type' => 'image/png'],
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} elseif ($app === 'fitboard') {
    $startUrl = $base . $id . '/fitboard';
    echo json_encode([
        'name'             => 'FitBoard',
        'short_name'       => 'FitBoard',
        'id'               => 'FitBoard_' . $id,
        'start_url'        => $startUrl,
        'scope'            => $base,
        'theme_color'      => '#0f0f14',
        'background_color' => '#0f0f14',
        'display'          => 'standalone',
        'icons'            => [
            ['src' => $base . 'images/fb_icon_192.png', 'sizes' => '192x192', 'type' => 'image/png'],
            ['src' => $base . 'images/fb_icon_512.png', 'sizes' => '512x512', 'type' => 'image/png'],
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} else {
    // pitchboard (default)
    $startUrl = $base . $id . '/pitchboard';
    echo json_encode([
        'name'             => 'PitchBoard',
        'short_name'       => 'PitchBoard',
        'id'               => 'PitchBoard_' . $id,
        'start_url'        => $startUrl,
        'scope'            => $base,
        'theme_color'      => '#1a1a2e',
        'background_color' => '#1a1a2e',
        'display'          => 'standalone',
        'icons'            => [
            ['src' => $base . 'images/pitchboard_icon_192.png', 'sizes' => '192x192', 'type' => 'image/png'],
            ['src' => $base . 'images/pitchboard_icon_512.png', 'sizes' => '512x512', 'type' => 'image/png'],
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
