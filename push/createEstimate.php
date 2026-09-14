<?php
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';

$sheetId      = trim($_POST['id']             ?? '');
$game         = trim($_POST['game']           ?? '');
$client       = trim($_POST['client']         ?? '');
$numTests     = max(0, intval($_POST['num_tests']     ?? 2));
$numEdits     = max(0, intval($_POST['num_edits']     ?? 2));
$qty          = trim($_POST['qty']            ?? '1');
$unitPrice    = trim($_POST['unit_price']     ?? '');
$duration     = trim($_POST['duration']       ?? '');
$discountPct  = max(0, floatval($_POST['discount_pct']   ?? 0));
$discountLbl  = trim($_POST['discount_label'] ?? '');
$notes        = trim($_POST['notes']          ?? '');
$myName       = trim($_POST['my_name']        ?? '');
$myPhone      = trim($_POST['my_phone']       ?? '');
$myCompany    = trim($_POST['my_company']     ?? '');
$myLogo       = trim($_POST['my_logo']        ?? '');
$myAddress    = trim($_POST['my_address']     ?? '');

if (!$sheetId) {
    echo json_encode(['error' => 'Missing sheet ID']);
    exit;
}

$pythonPath = $_ENV['PYTHON'] ?? 'python3';
$payload = [
    'game'          => $game,
    'client'        => $client,
    'num_tests'     => $numTests,
    'num_edits'     => $numEdits,
    'qty'           => $qty,
    'unit_price'    => $unitPrice,
    'duration'      => $duration,
    'discount_pct'  => $discountPct,
    'discount_label'=> $discountLbl,
    'notes'         => $notes,
    'my_name'       => $myName,
    'my_phone'      => $myPhone,
    'my_company'    => $myCompany,
    'my_logo'       => $myLogo,
    'my_address'    => $myAddress,
];
$encoded = base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE));
$arg     = $sheetId . '|' . $encoded;

$cmd    = escapeshellarg($pythonPath) . ' '
        . escapeshellarg(__DIR__ . '/gcreateEstimate.py') . ' '
        . escapeshellarg($arg) . ' 2>&1';
$output = trim((string) shell_exec($cmd));

if ($output === '') {
    echo json_encode(['error' => 'No response from estimate script']);
    exit;
}

$result = json_decode($output, true);
if ($result === null) {
    echo json_encode(['error' => $output]);
    exit;
}

// On success, append to estimates.json
if (!empty($result['ok'])) {
    $estFile  = dirname(__DIR__) . '/sheets/' . $sheetId . '/estimates.json';
    $existing = file_exists($estFile)
        ? (json_decode(file_get_contents($estFile), true) ?: [])
        : [];
    $record = [
        'client'       => $client,
        'game'         => $game,
        'estimate_num' => $result['estimate_num'] ?? '',
        'amount'       => $result['amount']       ?? 0,
        'url'          => $result['url']           ?? '',
        'tab'          => $result['tab']           ?? '',
        'date'         => date('m/d/Y'),
    ];
    $existing[] = $record;
    file_put_contents($estFile, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $result['estimate_record'] = $record;
}

echo json_encode($result);
?>
