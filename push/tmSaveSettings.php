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
    'carousel_1'  => 'Carousel 1',
    'carousel_2'  => 'Carousel 2',
    'carousel_3'  => 'Carousel 3',
    'carousel_4'  => 'Carousel 4',
    'carousel_5'  => 'Carousel 5',
    'carousel_6'  => 'Carousel 6',
    'instagram'   => 'Instagram',
    'twitter'     => 'Twitter',
    'facebook'    => 'Facebook',
    'youtube'     => 'YouTube',
    'bgg_url'      => 'BGG URL',
    'banner_color' => 'Banner Color',
];

$settings = $existing;
foreach ($postMap as $postKey => $settingsKey) {
    if (isset($_POST[$postKey])) {
        $settings[$settingsKey] = trim($_POST[$postKey]);
    }
}

if (file_put_contents($file, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
    echo json_encode(['error' => 'Could not write settings.json']); exit;
}

echo json_encode(['ok' => true]);
