<?php
// tmSaveSettings.php — write settings.json for a sheet
// POST: id, name, company, phone, logo, address, payment
error_reporting(0); ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';

$sheetId = trim($_POST['id'] ?? '');
if (!$sheetId) { echo json_encode(['error' => 'Missing id']); exit; }

$dir  = dirname(__DIR__) . '/sheets/' . $sheetId;
$file = $dir . '/settings.json';

if (!is_dir($dir)) {
    if (!mkdir($dir, 0775, true)) {
        echo json_encode(['error' => 'Cannot create sheet directory']); exit;
    }
}

// Load existing settings to merge — only update fields that were explicitly posted
// If the file is in old array format (ZapSheets), discard it and start fresh.
$existing = [];
if (file_exists($file)) {
    $decoded = json_decode(file_get_contents($file), true);
    if (is_array($decoded) && !empty($decoded) && isset($decoded[0])) {
        $existing = []; // old indexed-array format — discard; start fresh flat object
    } else {
        $existing = $decoded ?: [];
    }
}

// Map of POST key → settings key, only written when the POST key is present
$postMap = [
    'name'        => 'Name',
    'company'     => 'Company',
    'phone'       => 'Phone',
    'logo'        => 'Logo URL',
    'address'     => 'Address',
    'payment'     => 'Payment Info',
    'gb_template' => 'Gameboard Template',
    'tagline'     => 'Tagline',
    'about'       => 'About',
    'instagram'   => 'Instagram',
    'twitter'     => 'Twitter',
    'facebook'    => 'Facebook',
    'youtube'     => 'YouTube',
    'bgg_url'      => 'BGG URL',
    'banner_color' => 'Banner Color',
    'copyright'          => 'Copyright',
    'footer_note'        => 'Footer Note',
    'subscribe_heading'  => 'Subscribe Heading',
    'subscribe_text'     => 'Subscribe Text',
];

$settings = $existing;
foreach ($postMap as $postKey => $settingsKey) {
    if (isset($_POST[$postKey])) {
        $settings[$settingsKey] = trim($_POST[$postKey]);
    }
}

// Collect carousel image URLs as "Image" array; remove legacy "Carousel N" keys
if (isset($_POST['carousel_1'])) { // only update when carousel fields were posted
    $images = [];
    for ($i = 1; $i <= 6; $i++) {
        $v = trim($_POST['carousel_' . $i] ?? '');
        if ($v !== '') $images[] = $v;
    }
    $settings['Image'] = $images;
    for ($i = 1; $i <= 6; $i++) unset($settings['Carousel ' . $i]);
}

if (file_put_contents($file, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
    echo json_encode(['error' => 'Could not write settings.json']); exit;
}

// ── Also sync to Google Sheets Settings tab ──────────────────────────────────
$pythonPath = $_ENV['PYTHON'] ?? 'python3';
$encoded    = base64_encode(json_encode($settings, JSON_UNESCAPED_UNICODE));
$arg        = $sheetId . '|' . $encoded;
$errFile    = sys_get_temp_dir() . '/gwritesettings_err_' . $sheetId . '.txt';
$cmd = escapeshellarg($pythonPath) . ' '
     . escapeshellarg(__DIR__ . '/gwritesettings.py') . ' '
     . escapeshellarg($arg) . ' 2>' . escapeshellarg($errFile);
$pyOut   = trim((string) shell_exec($cmd));
$pyErr   = file_exists($errFile) ? trim(file_get_contents($errFile)) : '';
$pyResult = $pyOut ? json_decode($pyOut, true) : null;
$sheetOk  = !empty($pyResult['ok']);
$sheetErr = '';
if (!$sheetOk) {
    $sheetErr = $pyResult['error'] ?? $pyErr ?: ($pyOut ?: 'Script did not respond');
}

echo json_encode(['ok' => true, 'sheetOk' => $sheetOk, 'sheetErr' => $sheetErr, '_file' => $file, '_keys' => array_keys($settings)]);
