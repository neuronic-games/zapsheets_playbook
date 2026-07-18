<?php
// generateFitboardPlan.php — generate a 3-month workout plan in a FitBoard sheet.
//
// Called from the onboarding page at /{id}/fitboard/onboard.
// Runs ggenerateworkout.py then refreshes week.json.
//
// POST params:
//   id          — Google Spreadsheet ID
//   goal        — strength | hypertrophy | weight_loss | general
//   days        — 3 | 4 | 5
//   level       — beginner | intermediate | advanced
//   start_date  — YYYY-MM-DD
//   weight_lbs  — current body weight in lbs (float, 0 = unknown)
//   age         — age in years (int, 0 = unknown)

error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';

$sheetId   = trim($_POST['id']         ?? '');
$goal      = trim($_POST['goal']       ?? 'general');
$days      = (int)($_POST['days']      ?? 4);
$level     = trim($_POST['level']      ?? 'beginner');
$startDate = trim($_POST['start_date'] ?? date('Y-m-d'));
$weightLbs = (float)($_POST['weight_lbs'] ?? 0.0);
$age       = (int)($_POST['age']          ?? 0);

if (!$sheetId) {
    echo json_encode(['error' => 'Missing sheet ID']);
    exit;
}

$pythonPath = $_ENV['PYTHON'] ?? 'python3';

// ── Step 1: Generate workout plan ─────────────────────────────────────────────
$params  = json_encode([
    'goal'       => $goal,
    'days'       => $days,
    'level'      => $level,
    'start_date' => $startDate,
    'weight_lbs' => $weightLbs,
    'age'        => $age,
], JSON_UNESCAPED_UNICODE);
$encoded = base64_encode($params);
$arg     = $sheetId . '|' . $encoded;

$cmd    = escapeshellarg($pythonPath) . ' '
        . escapeshellarg(__DIR__ . '/ggenerateworkout.py') . ' '
        . escapeshellarg($arg) . ' 2>&1';
$output = trim((string) shell_exec($cmd));

if ($output === '') {
    echo json_encode(['error' => 'No response from workout generator']);
    exit;
}

$pyResult = json_decode($output, true);
if ($pyResult === null) {
    echo json_encode(['error' => $output]);
    exit;
}

if (empty($pyResult['ok'])) {
    echo json_encode($pyResult);
    exit;
}

// ── Step 2: Refresh week.json ─────────────────────────────────────────────────
$sheetDir = realpath(__DIR__ . '/..') . '/sheets/' . $sheetId;
if (!is_dir($sheetDir)) {
    mkdir($sheetDir, 0777, true);
}

$readCmd = escapeshellarg($pythonPath) . ' '
         . escapeshellarg(__DIR__ . '/gread_week.py') . ' '
         . escapeshellarg($sheetId) . ' 2>/dev/null';
$readOut = trim((string) shell_exec($readCmd));

if ($readOut !== '') {
    $readResult = json_decode($readOut, true);
    if (!empty($readResult['data'])) {
        file_put_contents($sheetDir . '/week.json',
            json_encode($readResult['data'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $pyResult['week_json'] = 'refreshed';
    } else {
        $pyResult['week_json'] = 'no_data';
    }
} else {
    $pyResult['week_json'] = 'skipped';
}

echo json_encode($pyResult);
?>
