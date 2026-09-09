<?php
/**
 * collabUpdateEmail.php — rename an account's email key in accounts.json
 *
 * POST params:
 *   id        — sheet ID (accounts.json lives inside sheets/{id}/)
 *   old_email — current email address
 *   new_email — replacement email address
 */
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

$oldEmail = strtolower(trim($_POST['old_email'] ?? ''));
$newEmail = strtolower(trim($_POST['new_email'] ?? ''));
$sheetId  = trim($_POST['id'] ?? '');

if (!$oldEmail || !$newEmail || !$sheetId) {
    echo json_encode(['error' => 'Missing required fields.']);
    exit;
}
if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['error' => 'New email address is not valid.']);
    exit;
}
if ($oldEmail === $newEmail) {
    echo json_encode(['ok' => true]);  // nothing to do
    exit;
}

$accountsFile = dirname(__DIR__) . '/sheets/' . $sheetId . '/accounts.json';

if (!file_exists($accountsFile)) {
    echo json_encode(['error' => 'Account not found.']);
    exit;
}

$accounts = json_decode(file_get_contents($accountsFile), true) ?: [];

if (!isset($accounts[$oldEmail])) {
    echo json_encode(['error' => 'Account not found.']);
    exit;
}
if (isset($accounts[$newEmail])) {
    echo json_encode(['error' => 'That email is already registered.']);
    exit;
}

// Rename key, preserving insertion order
$updated = [];
foreach ($accounts as $key => $val) {
    $updated[$key === $oldEmail ? $newEmail : $key] = $val;
}

file_put_contents($accountsFile, json_encode($updated, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo json_encode(['ok' => true]);
?>
