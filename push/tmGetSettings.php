<?php
// tmGetSettings.php — return settings.json for a sheet (GET: id)
error_reporting(0); ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';

$sheetId = trim($_GET['id'] ?? '');
if (!$sheetId) { echo json_encode([]); exit; }

$file = dirname(__DIR__) . '/sheets/' . $sheetId . '/settings.json';
if (!file_exists($file)) { echo json_encode([]); exit; }

echo file_get_contents($file);
