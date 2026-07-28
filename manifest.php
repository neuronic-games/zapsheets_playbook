<?php
header('Content-Type: application/manifest+json');
header('Cache-Control: no-cache');

$id   = preg_replace('/[^A-Za-z0-9_\-]/', '', $_GET['id']   ?? '');
$app  = trim($_GET['app']  ?? 'pitchboard');
$base = trim($_GET['base'] ?? '/');
if (substr($base, -1) !== '/') $base .= '/';

if ($app === 'fitboard') {
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
            ['src' => $base . 'images/pb_icon_192.png', 'sizes' => '192x192', 'type' => 'image/png'],
            ['src' => $base . 'images/pb_icon_512.png', 'sizes' => '512x512', 'type' => 'image/png'],
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
