<?php
// runSpacetimeImport.php — triggers gimportspacetime.py and returns output.
error_reporting(E_ALL);
ini_set('display_errors', '1');
set_time_limit(300);

require __DIR__ . '/../dotEnv.php';
$pythonPath = $_ENV['PYTHON'] ?? 'python3';
$script     = __DIR__ . '/gimportspacetime.py';
$cwd        = dirname(__DIR__);

header('Content-Type: text/plain; charset=utf-8');

echo "=== Spacetime DevBoard Import ===\n";
echo date('Y-m-d H:i:s') . "\n\n";
echo "Running: $pythonPath $script\n\n";

// Run Python script synchronously, capture all output
$output = [];
$returnCode = 0;
$cmd = escapeshellarg($pythonPath) . ' -u ' . escapeshellarg($script) . ' 2>&1';
exec($cmd, $output, $returnCode);

echo implode("\n", $output) . "\n";
echo "\nExit code: $returnCode\n";
?>
