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
$existing = [];
if (file_exists($file)) {
    $existing = json_decode(file_get_contents($file), true) ?: [];
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
