<?php
/**
 * paymentSlip.php — make the PDF payment slip for a payment to a collaborator (a Contracts row with Type "Payment").
 *
 * pbMakePaymentSlip($sheetId, $row, $me) → ['ok' => true, 'url' => …] or ['error' => …]
 *   $row — the Contracts row: Ref Number, Date, Game, Client, Payee, Quote, Notes
 *   $me  — the sender: my_name, my_phone, my_company, my_logo, my_address
 * The slip uses the same layout as estimates and invoices (gcreateDocPdf.py, doc_type "Payment"). The payee's
 * payment details come from their bio (People → Email → Bios "Payment"). The slip's link is written to the row's
 * Files column, in the sheet and in contracts.json.
 */
function pbPayeePayment(string $sheetId, string $payee): string {
    $dir = dirname(__DIR__) . '/sheets/' . $sheetId;
    $clean = function ($v) { return ltrim(trim((string) $v), "'"); };
    $email = '';
    foreach ((json_decode((string) @file_get_contents($dir . '/people.json'), true) ?: []) as $p) {
        if (strcasecmp($clean($p['Name'] ?? ''), $payee) === 0) { $email = strtolower($clean($p['Email'] ?? '')); break; }
    }
    if ($email === '' && filter_var($payee, FILTER_VALIDATE_EMAIL)) $email = strtolower($payee);
    if ($email === '') return '';
    foreach ((json_decode((string) @file_get_contents($dir . '/bios.json'), true) ?: []) as $b) {
        if (strtolower($clean($b['Email'] ?? '')) === $email) return $clean($b['Payment'] ?? '');
    }
    return '';
}

function pbMakePaymentSlip(string $sheetId, array $row, array $me): array {
    $ref = (string) ($row['Ref Number'] ?? ($row['ID'] ?? ''));
    if ($ref === '') return ['error' => 'This payment has no reference number'];
    $date = (string) ($row['Date'] ?? '');
    $payload = [
        'doc_type'      => 'Payment',
        'doc_id'        => $ref,
        'ref_code'      => $ref,
        'doc_date'      => $date,
        'game'          => (string) ($row['Game'] ?? ''),
        'client'        => (string) ($row['Client'] ?? ''),
        'payee'         => (string) ($row['Payee'] ?? ''),
        'payee_payment' => pbPayeePayment($sheetId, (string) ($row['Payee'] ?? '')),
        'quote'         => (string) ($row['Quote'] ?? ''),
        'notes'         => (string) ($row['Notes'] ?? ''),
        'summary'       => (string) ($row['Summary'] ?? ''),
        'my_name'       => (string) ($me['my_name'] ?? ''),
        'my_phone'      => (string) ($me['my_phone'] ?? ''),
        'my_company'    => (string) ($me['my_company'] ?? ''),
        'my_logo'       => (string) ($me['my_logo'] ?? ''),
        'my_address'    => (string) ($me['my_address'] ?? ''),
        'base_url'      => rtrim(function_exists('gbLinkBase') ? gbLinkBase() : (($_ENV['APP_DOMAIN'] ?? '') . '/' . trim($_ENV['BASE_PATH'] ?? '', '/')), '/'),
    ];
    $python = $_ENV['PYTHON'] ?? 'python3';
    $arg = $sheetId . '|' . base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE));
    $out = trim((string) shell_exec(escapeshellarg($python) . ' ' . escapeshellarg(__DIR__ . '/gcreateDocPdf.py') . ' ' . escapeshellarg($arg) . ' 2>&1'));
    $r = json_decode($out, true);
    if (!is_array($r)) return ['error' => $out !== '' ? $out : 'No response from the PDF script'];
    if (empty($r['ok'])) return $r;

    // Keep contracts.json in step with the sheet's Files column
    $file = dirname(__DIR__) . '/sheets/' . $sheetId . '/contracts.json';
    if (file_exists($file)) {
        $rows = json_decode(file_get_contents($file), true) ?: [];
        foreach ($rows as &$c) {
            if ((string) ($c['Ref Number'] ?? '') === $ref) { $c['Files'] = $r['url']; break; }
        }
        unset($c);
        file_put_contents($file, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    return ['ok' => true, 'url' => $r['url']];
}
