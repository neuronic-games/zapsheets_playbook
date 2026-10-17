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

$settings = [
    'Name'               => trim($_POST['name']        ?? ''),
    'Company'            => trim($_POST['company']     ?? ''),
    'Phone'              => trim($_POST['phone']       ?? ''),
    'Logo URL'           => trim($_POST['logo']        ?? ''),
    'Address'            => trim($_POST['address']     ?? ''),
    'Payment Info'       => trim($_POST['payment']     ?? ''),
    'Gameboard Template' => trim($_POST['gb_template'] ?? ''),
];

if (file_put_contents($file, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
    echo json_encode(['error' => 'Could not write settings.json']); exit;
}

echo json_encode(['ok' => true]);
