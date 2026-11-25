<?php
/**
 * collabAuth.php — sign in / sign up for collab (shared) view
 *
 * POST params:
 *   email    — user email
 *   password — plain-text password (hashed before storage)
 *   id       — Google Spreadsheet ID (used to load bio)
 *
 * Accounts are stored globally in sheets/accounts.json:
 *   { "email@example.com": { "hash": "$2y$..." }, ... }
 *
 * Returns JSON:
 *   { ok: true, new: bool, email: string, bio: { name?, image?, description?, ... } }
 *   { error: string }
 */
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

$email    = strtolower(trim($_POST['email']    ?? ''));
$password = trim($_POST['password']            ?? '');
$sheetId  = trim($_POST['id']                  ?? '');

// ── Forgotten password: a 6-digit code to the email, then a new password ──────────────────────────
//   action=reset_send — email, id                 → {ok}       (needs a profile for that email on this sheet)
//   action=reset      — email, id, code, password → {ok}       (then sign in as usual)
$action = (string) ($_POST['action'] ?? '');
if ($action === 'reset_send' || $action === 'reset') {
    require_once __DIR__ . '/gameboardAuth.php';   // cbSendMail, cbSecret, cbPrivateDir
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[A-Za-z0-9_\-]{10,80}$/', $sheetId)) { echo json_encode(['error' => 'Enter your email address.']); exit; }
    $accFile = dirname(__DIR__) . '/sheets/' . $sheetId . '/accounts.json';
    $accts = file_exists($accFile) ? (json_decode(file_get_contents($accFile), true) ?: []) : [];
    if (!isset($accts[$email])) { usleep(300000); echo json_encode(['error' => 'There’s no profile for that email here — sign in with a new email to create one.']); exit; }
    $key = hash('sha256', $sheetId . '|' . $email);
    $cf  = cbPrivateDir('collab-codes') . '/' . $key . '.json';
    $c   = json_decode((string) @file_get_contents($cf), true) ?: [];
    if ($action === 'reset_send') {
        $sent = array_values(array_filter($c['sent'] ?? [], function ($ts) { return $ts > time() - 3600; }));
        if (count($sent) >= 5) { echo json_encode(['error' => 'Too many codes asked for — please wait a while and try again.']); exit; }
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $sent[] = time();
        file_put_contents($cf, json_encode(['h' => hash_hmac('sha256', $email . '|' . $code, cbSecret()), 'x' => time() + 900, 'tries' => 0, 'sent' => $sent]), LOCK_EX);
        $text = "Your code to choose a new password is:\r\n\r\n    $code\r\n\r\nIt works for 15 minutes. If you didn't ask for it, you can ignore this email.\r\n\r\n— GameBoard by ZapSheets";
        $html = '<div style="font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;max-width:460px;margin:0 auto;padding:24px;color:#1b1b2b">'
              . '<div style="font-size:19px;font-weight:700;color:#2e2763;margin:0 0 8px">Reset your password</div>'
              . '<div style="font-size:15px;line-height:1.55;color:#555a70;margin:0 0 20px">Enter this code to choose a new password for your profile. It works for 15 minutes.</div>'
              . '<div style="display:inline-block;padding:14px 22px;background:#f3f1ff;border:1px solid #ddd6ff;border-radius:12px;font:700 30px SFMono-Regular,Menlo,Consolas,monospace;letter-spacing:10px;color:#2e2763">' . $code . '</div>'
              . '<div style="font-size:13px;color:#8a8fa8;margin:22px 0 0">If you didn’t ask for this code, you can ignore this email.</div></div>';
        $why = cbSendMail($email, 'Your password code: ' . $code, $text, $html);
        $debug = !empty($_ENV['GAMEBOARD_AUTH_DEBUG'] ?? ($_ENV['CARDBOARD_AUTH_DEBUG'] ?? ''));
        if ($why !== '' && !$debug) { echo json_encode(['error' => 'The email couldn’t be sent — please try again later.']); exit; }
        echo json_encode(['ok' => true] + ($debug ? ['debug_code' => $code] : [])); exit;
    }
    // reset
    $code = preg_replace('/\D/', '', (string) ($_POST['code'] ?? ''));
    if (empty($c['h']) || ($c['x'] ?? 0) < time()) { echo json_encode(['error' => 'That code has expired — ask for a new one.']); exit; }
    if (!hash_equals($c['h'], hash_hmac('sha256', $email . '|' . $code, cbSecret()))) {
        $c['tries'] = ($c['tries'] ?? 0) + 1;
        if ($c['tries'] >= 5) { unset($c['h']); }
        file_put_contents($cf, json_encode($c), LOCK_EX); usleep(400000);
        echo json_encode(['error' => $c['tries'] >= 5 ? 'Too many wrong codes — ask for a new one.' : 'That code isn’t right.']); exit;
    }
    if (strlen($password) < 4) { echo json_encode(['error' => 'Password must be at least 4 characters.']); exit; }
    unset($c['h']); file_put_contents($cf, json_encode($c), LOCK_EX);
    $accts[$email]['hash'] = password_hash($password, PASSWORD_DEFAULT);
    file_put_contents($accFile, json_encode($accts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    echo json_encode(['ok' => true]); exit;
}

if (!$email || !$password || !$sheetId) {
    echo json_encode(['error' => 'Email, password, and sheet ID are required.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['error' => 'Please enter a valid email address.']);
    exit;
}
if (strlen($password) < 4) {
    echo json_encode(['error' => 'Password must be at least 4 characters.']);
    exit;
}

$sheetsRoot   = dirname(__DIR__) . '/sheets';
$sheetDir     = $sheetsRoot . '/' . $sheetId;
if ($sheetId && !is_dir($sheetDir)) { mkdir($sheetDir, 0777, true); }
$accountsFile = $sheetDir . '/accounts.json';

// Load or init accounts map
$accounts = [];
if (file_exists($accountsFile)) {
    $accounts = json_decode(file_get_contents($accountsFile), true) ?: [];
}

$isNew       = !isset($accounts[$email]);
$confirmNew  = !empty($_POST['confirm_new']);

if ($isNew) {
    if (!$confirmNew) {
        // Ask the client to confirm before creating
        echo json_encode(['prompt_create' => true, 'email' => $email]);
        exit;
    }
    // Confirmed — register new account
    $accounts[$email] = ['hash' => password_hash($password, PASSWORD_DEFAULT)];
    file_put_contents($accountsFile, json_encode($accounts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
} else {
    // Verify existing account
    if (!password_verify($password, $accounts[$email]['hash'] ?? '')) {
        echo json_encode(['error' => 'Incorrect password. Try again.']);
        exit;
    }
}

// Build bio from sheet data
$bio = [];

// Display name saved on the account (set via tmSetName.php) — unique per sheet
if (!empty($accounts[$email]['name'])) $bio['name'] = $accounts[$email]['name'];

// Otherwise use the Name on the T&MBoard Staff tab for this email, if any
if (empty($bio['name']) && $sheetId && file_exists($sheetDir . '/staff.json')) {
    foreach ((json_decode(file_get_contents($sheetDir . '/staff.json'), true) ?: []) as $st) {
        if (strtolower(trim(ltrim($st['Email'] ?? '', "'"))) === $email) {
            $stName = trim(ltrim($st['Name'] ?? '', "'"));
            if ($stName !== '') $bio['name'] = $stName;
            break;
        }
    }
}

if ($sheetId) {
    // Name from people.json
    $peopleFile = $sheetsRoot . '/' . $sheetId . '/people.json';
    if (file_exists($peopleFile)) {
        $people = json_decode(file_get_contents($peopleFile), true) ?: [];
        foreach ($people as $p) {
            $pEmail = strtolower(ltrim(trim($p['Email'] ?? ''), "'"));
            if ($pEmail === $email) {
                $name = ltrim(trim($p['Name'] ?? ''), "'");
                if ($name && empty($bio['name'])) $bio['name'] = $name;
                break;
            }
        }
    }

    // Extended bio from bios.json
    $biosFile = $sheetsRoot . '/' . $sheetId . '/bios.json';
    if (file_exists($biosFile)) {
        $bios = json_decode(file_get_contents($biosFile), true) ?: [];
        foreach ($bios as $b) {
            $bEmail = strtolower(ltrim(trim($b['Email'] ?? ''), "'"));
            if ($bEmail === $email) {
                $rawImg = trim($b['Image'] ?? '');
                if (preg_match('/^=IMAGE\("([^"]*)"\)$/i', $rawImg, $im)) {
                    $bio['image'] = $im[1];
                } elseif ($rawImg) {
                    $bio['image'] = ltrim($rawImg, "'");
                }
                $fields = ['description' => 'Description', 'skills' => 'Skills',
                           'location' => 'Address',  'discord' => 'Messaging',
                           'phone' => 'Phone', 'payment' => 'Payment', 'notes' => 'Notes'];
                foreach ($fields as $key => $col) {
                    $val = ltrim(trim($b[$col] ?? ''), "'");
                    if ($val) $bio[$key] = $val;
                }
                break;
            }
        }
    }
}

echo json_encode(['ok' => true, 'new' => $isNew, 'email' => $email, 'bio' => $bio]);
?>
