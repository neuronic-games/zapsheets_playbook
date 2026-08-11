<?php
/**
 * PulseBoard dynamic web app manifest.
 * URL: /{sheet_id}/pulseboard/manifest.json
 */
error_reporting(0);
header('Content-Type: application/manifest+json');
header('Cache-Control: public, max-age=3600');

$_bpFile = __DIR__ . '/../../dotEnv.php';
if (file_exists($_bpFile)) { require_once $_bpFile; }

$_rp = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$_bp = rtrim($_ENV['BASE_PATH'] ?? '', '/');
$_rp_stripped = ($_bp !== '' && str_starts_with($_rp, $_bp))
    ? substr($_rp, strlen($_bp)) : $_rp;

preg_match('#^/([A-Za-z0-9_\-]+)/pulseboard/manifest\.json#', $_rp_stripped, $_m);
$_sheet_id = $_m[1] ?? ($_GET['id'] ?? '');

$_app_name = 'PulseBoard';

$_start_url = $_bp . '/' . $_sheet_id . '/pulseboard/';  // trailing slash matches SW scope
$_icon_url  = $_bp . '/images/pb_icon_512.png';

echo json_encode([
    'name'             => $_app_name,
    'short_name'       => $_app_name,
    'start_url'        => $_start_url,
    'display'          => 'standalone',
    'background_color' => '#0f1923',
    'theme_color'      => '#0a1118',
    'icons'            => [
        ['src' => $_icon_url, 'sizes' => '512x512', 'type' => 'image/png'],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
