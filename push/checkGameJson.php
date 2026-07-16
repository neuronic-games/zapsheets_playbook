<?php
/**
 * push/checkGameJson.php
 * Returns {"exists": true/false} for a game-specific JSON file.
 *
 * GET params:
 *   id   — sheet ID
 *   game — game name (unescaped; we compute the safe filename here)
 */
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');

require __DIR__ . '/../dotEnv.php';

$id   = trim($_GET['id']   ?? '');
$game = trim($_GET['game'] ?? '');

if (!$id || !$game) {
    echo json_encode(['exists' => false]);
    exit;
}

$safe = str_replace(['/', '\\'], '-', $game);
$path = __DIR__ . '/../sheets/' . $id . '/game-' . $safe . '-en.json';

echo json_encode(['exists' => file_exists($path)]);
