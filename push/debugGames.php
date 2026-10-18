<?php
/**
 * debugGames.php — temporary diagnostic: dump games.json content.
 * GET param: id — sheet ID
 * DELETE THIS FILE once the issue is resolved.
 */
header('Content-Type: application/json');
$sheetId = trim($_GET['id'] ?? '');
if (!$sheetId || !preg_match('/^[A-Za-z0-9_-]+$/', $sheetId)) {
    echo json_encode(['error' => 'missing or invalid id']);
    exit;
}
$file = dirname(__DIR__) . '/sheets/' . $sheetId . '/games.json';
if (!file_exists($file)) {
    echo json_encode(['error' => 'file not found', 'path_checked' => $file]);
    exit;
}
$raw     = file_get_contents($file);
$decoded = json_decode($raw, true);
if (!is_array($decoded)) {
    echo json_encode(['error' => 'invalid JSON', 'raw_preview' => substr($raw, 0, 300)]);
    exit;
}
$total = count($decoded);

$published = array_values(array_filter($decoded, function($g) {
    return strtolower(trim(isset($g['Status']) ? $g['Status'] : '')) === 'published';
}));

$sample = array_map(function($g) {
    return [
        'Name'   => isset($g['Name']) ? $g['Name'] : '?',
        'Status' => isset($g['Status']) ? $g['Status'] : '(missing)',
    ];
}, array_slice($decoded, 0, 5));

// Also check all unique Status values
$statuses = array_unique(array_map(function($g) {
    return isset($g['Status']) ? $g['Status'] : '(missing)';
}, $decoded));

// Show available keys from first record
$firstKeys = $total > 0 ? array_keys($decoded[0]) : [];

echo json_encode([
    'total_records'    => $total,
    'published_count'  => count($published),
    'unique_statuses'  => array_values($statuses),
    'first_5_records'  => $sample,
    'first_record_keys'=> $firstKeys,
    'file_modified'    => date('Y-m-d H:i:s', filemtime($file)),
], JSON_PRETTY_PRINT);
