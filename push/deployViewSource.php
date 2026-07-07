<?php
/**
 * push/deployViewSource.php
 * View is now served directly from source/view/index.php via routing —
 * no per-sheet copy is needed. This endpoint is kept for backward compatibility
 * (the VP dialog still calls it) and simply returns ok.
 */
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');
echo json_encode(['ok' => true]);
?>
