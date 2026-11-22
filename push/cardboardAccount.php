<?php
/**
 * cardboardAccount.php — sign in to CardBoard with a code sent by email, and keep the
 * account token fresh. See cardboardAuth.php for the token and the free week.
 *
 * POST action and params:
 *   send     — email, mode (signup|reset)  → {ok, existing}  (emails a 6-digit code, valid 15 minutes;
 *                                            signup refuses an account that has a password; reset needs an account)
 *   verify   — email, code, password, name?, sheet? → {ok, token, account}  (creates the account or sets the password)
 *   login    — email, password, sheet?     → {ok, token, account}
 * Passwords are kept only as password_hash() in the Admin sheet's "Password Hash" column.
 *   refresh  — cbt (token), sheet?         → {ok, token, account}   (re-reads the Admin sheet)
 *   activate — cbt, key, sheet?            → {ok, token, account}   (activation key: everything, no time limit)
 *   profile  — cbt, name, studio           → {ok, token, account}   (saved in the person's Admin-sheet row)
 *   redeem   — cbt, code                   → {ok, token, account, days}  (a free code from the Admin sheet's Codes tab,
 *                                            or an activation key: unlocked with no time limit)
 * account = {email, name, state: trial|subscribed|activated|locked, until, trial_end, period_end, status, sheet_unlocked}
 */
error_reporting(0);
header('Content-Type: application/json');
require_once __DIR__ . '/cardboardAuth.php';

function fail(string $m, array $extra = []): void { echo json_encode(['error' => $m] + $extra); exit; }
function ok(array $d): void { echo json_encode(['ok' => true] + $d, JSON_UNESCAPED_UNICODE); exit; }

/** At most $max events per hour for a key; false when over. */
function cbLimit(string $key, int $max): bool {
    $f = cbPrivateDir('limits') . '/' . hash('sha256', $key) . '.json';
    $log = array_values(array_filter(json_decode((string) @file_get_contents($f), true) ?: [], function ($t) { return $t > time() - 3600; }));
    if (count($log) >= $max) return false;
    $log[] = time(); @file_put_contents($f, json_encode($log), LOCK_EX);
    return true;
}
function cbAccountOut(array $a, string $sheet): array {
    $d = function ($t) { return $t ? gmdate('c', $t) : ''; };
    return ['email' => $a['e'], 'name' => $a['n'], 'studio' => $a['studio'] ?? '', 'state' => $a['st'], 'until' => $d($a['u']), 'trial_end' => $d($a['te']),
            'period_end' => $a['pe'], 'status' => $a['status'] ?? '', 'sheet_unlocked' => $sheet !== '' && cbSheetUnlocked($sheet),
            'owner' => $sheet !== '' && in_array($sheet, $a['sh'] ?? [], true),   // set this sheet up: may change it (else view only)
            'portal_url' => $_ENV['STRIPE_PORTAL_URL'] ?? ''];
}
function cbSignedIn(array $u, string $sheet, bool $taken = true): void {
    $a = cbAccessFromUser($u);
    $acc = cbAccountOut($a, $sheet);
    $acc['unclaimed'] = $sheet !== '' && !$acc['owner'] && !$taken;   // nobody has set this sheet up: it can be made yours
    ok(['token' => cbIssueToken($a), 'account' => $acc]);
}

/** The sign-in email in CardBoard's colors: navy band, gold/lavender wordmark, the code large. Tables and inline styles for mail apps. */
function cbCodeEmailHtml(string $code, string $mode = 'signup'): string {
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = preg_replace('/[^a-z0-9.\-:]/i', '', $_SERVER['HTTP_HOST'] ?? 'zapsheets.com');
    $base   = $scheme . '://' . $host . rtrim($_ENV['BASE_PATH'] ?? '', '/') . '/';
    $logo   = htmlspecialchars($base . 'images/cardboard_icon_180.png', ENT_QUOTES);
    $app    = htmlspecialchars($base . 'cardboard', ENT_QUOTES);
    // One box, spaced out but copied as plain digits (separate boxes copy with gaps between them)
    $digits = '<td style="padding:14px 22px 14px 30px;background:#f3f1ff;border:1px solid #ddd6ff;border-radius:12px;font-family:\'SFMono-Regular\',Menlo,Consolas,monospace;font-size:32px;font-weight:700;letter-spacing:10px;color:#2e2763;">' . $code . '</td>';
    $font = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";
    return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light only"><title>Your CardBoard code</title></head>' .
      '<body style="margin:0;padding:0;background:#f3f2fa;">' .
      '<div style="display:none;max-height:0;overflow:hidden;">Your CardBoard code is ' . $code . '. It works for 15 minutes.</div>' .
      '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f2fa;"><tr><td align="center" style="padding:32px 16px;">' .
      '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 18px rgba(46,39,99,.10);">' .
        '<tr><td style="background:#2e2763;padding:22px 28px;">' .
          '<table role="presentation" cellpadding="0" cellspacing="0"><tr>' .
            '<td style="padding-right:12px;"><img src="' . $logo . '" width="40" height="40" alt="" style="display:block;border-radius:9px;"></td>' .
            '<td style="font-family:' . $font . ';font-size:22px;font-weight:800;letter-spacing:.5px;"><span style="color:#f2c14e;">Card</span><span style="color:#a99cff;">Board</span></td>' .
          '</tr></table></td></tr>' .
        '<tr><td style="padding:30px 28px 8px;font-family:' . $font . ';color:#1b1b2b;">' .
          '<div style="font-size:20px;font-weight:700;color:#2e2763;margin:0 0 8px;">' . ($mode === 'reset' ? 'Reset your password' : 'Confirm your email') . '</div>' .
          '<div style="font-size:15px;line-height:1.55;color:#555a70;margin:0 0 22px;">' . ($mode === 'reset' ? 'Enter this code in CardBoard to choose a new password.' : 'Enter this code in CardBoard to finish creating your account.') . ' It works for 15 minutes.</div>' .
          '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px;"><tr>' . $digits . '</tr></table>' .
          '<div style="font-size:13px;line-height:1.55;color:#8a8fa8;margin:0 0 22px;">New to CardBoard? Your first week is free, with every feature. If you didn’t ask for this code, you can ignore this email.</div>' .
        '</td></tr>' .
        '<tr><td style="padding:16px 28px 22px;border-top:1px solid #eeecf6;font-family:' . $font . ';font-size:12px;color:#9aa0b8;">' .
          '<a href="' . $app . '" style="color:#7b6cf6;text-decoration:none;font-weight:600;">Open CardBoard</a> &nbsp;·&nbsp; Card designs from your Google Sheet &nbsp;·&nbsp; © ' . gmdate('Y') . ' ZapSheets' .
        '</td></tr>' .
      '</table></td></tr></table></body></html>';
}

$action = $_POST['action'] ?? '';
$sheet  = trim((string) ($_POST['sheet'] ?? ''));
if ($sheet !== '' && !stripeValidSheetId($sheet)) $sheet = '';
$ip = $_SERVER['REMOTE_ADDR'] ?? '';

function cbEmailIn(): string {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 200) fail('Enter a valid email address');
    return $email;
}
function cbPasswordIn(): string {
    $pw = (string) ($_POST['password'] ?? '');
    // The same rules the sign-up form checks off as you type
    if (strlen($pw) < 8) fail('Use at least 8 characters for your password');
    if (!preg_match('/\pL/u', $pw)) fail('Include at least one letter in your password');
    if (!preg_match('/\d/', $pw)) fail('Include at least one number in your password');
    if (strlen($pw) > 200) fail('That password is too long');
    return $pw;
}
function cbFind(string $email): ?array {
    $f = cbAdmin(['action' => 'find', 'field' => 'Email', 'value' => $email]);
    if (empty($f['ok'])) fail($f['error'] ?? 'Couldn’t check your account');
    return $f['user'] ?: null;
}

// Signing up and resetting a password both prove the email with a code first.
//   mode signup — a new account (an account that has no password yet may set one here)
//   mode reset  — an existing account chooses a new password
if ($action === 'send') {
    $email = cbEmailIn();
    $mode  = ($_POST['mode'] ?? '') === 'reset' ? 'reset' : 'signup';
    if (!cbLimit('send|' . $email, 5) || !cbLimit('sendip|' . $ip, 20)) fail('Too many codes asked for — please wait a while and try again');
    $u = cbFind($email);
    if ($mode === 'signup' && $u && trim((string) ($u['Password Hash'] ?? '')) !== '') fail('You already have a CardBoard account — log in instead.', ['has_account' => true]);
    if ($mode === 'reset' && !$u) fail('There’s no CardBoard account for that email — start your free week instead.', ['no_account' => true]);
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $file = cbPrivateDir('codes') . '/' . hash('sha256', $email) . '.json';
    file_put_contents($file, json_encode(['h' => hash_hmac('sha256', $email . '|' . $code, cbSecret()), 'x' => time() + 900, 'tries' => 0, 'mode' => $mode]), LOCK_EX);
    $body = "Your CardBoard code is:\r\n\r\n    $code\r\n\r\nIt works for 15 minutes. If you didn't ask for it, you can ignore this email.\r\n\r\n— CardBoard by ZapSheets";
    $why  = cbSendMail($email, 'Your CardBoard code: ' . $code, $body, cbCodeEmailHtml($code, $mode));
    if ($why !== '' && empty($_ENV['CARDBOARD_AUTH_DEBUG'])) { @unlink($file); fail('The email couldn’t be sent — please try again later'); }
    ok(['existing' => (bool) $u] + (!empty($_ENV['CARDBOARD_AUTH_DEBUG']) ? ['debug_code' => $code] : []));   // debug code only on a test server
}

// The code from the email, plus the password to keep: creates the account (free week) or sets the new password
if ($action === 'verify') {
    $email = cbEmailIn();
    $pw    = cbPasswordIn();
    $code  = preg_replace('/\D/', '', (string) ($_POST['code'] ?? ''));
    $file  = cbPrivateDir('codes') . '/' . hash('sha256', $email) . '.json';
    $c = json_decode((string) @file_get_contents($file), true);
    if (!$c || ($c['x'] ?? 0) < time()) { @unlink($file); fail('That code has expired — ask for a new one'); }
    if (!hash_equals($c['h'], hash_hmac('sha256', $email . '|' . $code, cbSecret()))) {
        $c['tries'] = ($c['tries'] ?? 0) + 1;
        if ($c['tries'] >= 5) { @unlink($file); fail('Too many wrong codes — ask for a new one'); }
        file_put_contents($file, json_encode($c), LOCK_EX); usleep(400000);
        fail('That code isn’t right');
    }
    @unlink($file);
    $r = cbAdmin(['action' => 'user', 'email' => $email, 'name' => trim((string) ($_POST['name'] ?? '')), 'sheet' => $sheet,
                  'fields' => ['Password Hash' => password_hash($pw, PASSWORD_DEFAULT)]]);
    if (empty($r['ok'])) fail($r['error'] ?? 'Couldn’t open your account');
    cbSignedIn($r['user'], $sheet, !empty($r['taken']));
}

// Log in with email and password
if ($action === 'login') {
    $email = cbEmailIn();
    $pw    = (string) ($_POST['password'] ?? '');
    if ($pw === '') fail('Enter your password');
    if (!cbLimit('login|' . $email, 10) || !cbLimit('loginip|' . $ip, 40)) fail('Too many tries — please wait a while, or reset your password');
    $u = cbFind($email);
    $hash = $u ? trim((string) ($u['Password Hash'] ?? '')) : '';
    if ($u && $hash === '') fail('This account doesn’t have a password yet — use “Forgot password?” to set one.', ['no_password' => true]);
    if (!$u || !password_verify($pw, $hash)) { usleep(500000); fail('That email and password don’t match'); }
    $r = cbAdmin(['action' => 'user', 'email' => $email, 'sheet' => $sheet]);
    if (empty($r['ok'])) fail($r['error'] ?? 'Couldn’t open your account');
    cbSignedIn($r['user'], $sheet, !empty($r['taken']));
}

if ($action === 'redeem') {
    $p = cbReadToken(cbRequestToken());
    if (!$p || ($p['x'] ?? 0) < time() - 30 * 86400) fail('Please sign in again', ['need_auth' => true]);
    $code = trim((string) ($_POST['code'] ?? ''));
    if ($code === '' || strlen($code) > 100) fail('Enter a code');
    if (!cbLimit('redeem|' . $p['e'], 10)) fail('Too many tries — please wait an hour and try again');
    $r = cbAdmin(['action' => 'redeem', 'email' => $p['e'], 'code' => $code]);
    if (!empty($r['unknown'])) {   // not a free code: an activation key unlocks with no time limit
        if (!activationKeyValid($code)) { usleep(600000); fail('That code isn’t valid'); }
        $r = cbAdmin(['action' => 'update', 'email' => $p['e'], 'fields' => ['Activated' => gmdate('Y-m-d')]]);
    }
    if (empty($r['ok'])) fail($r['error'] ?? 'Couldn’t use that code');
    $a = cbAccessFromUser($r['user']);
    ok(['token' => cbIssueToken($a), 'account' => cbAccountOut($a, $sheet), 'days' => (int) ($r['days'] ?? 0)]);
}

// claim — cbt, sheet: set this sheet up as yours (Connect on the home page, or "Make it mine" when nobody has it)
if ($action === 'refresh' || $action === 'activate' || $action === 'profile' || $action === 'claim') {
    $p = cbReadToken(cbRequestToken());
    if (!$p || ($p['x'] ?? 0) < time() - 30 * 86400) fail('Please sign in again', ['need_auth' => true]);
    if ($action === 'activate') {
        $key = (string) ($_POST['key'] ?? '');
        if (trim($key) === '' || strlen($key) > 200) fail('Enter an activation key');
        if (!cbLimit('key|' . $p['e'], 10)) fail('Too many tries — please wait an hour and try again');
        if (!activationKeyValid($key)) { usleep(700000); fail('That activation key isn’t valid'); }
        $r = cbAdmin(['action' => 'update', 'email' => $p['e'], 'fields' => ['Activated' => gmdate('Y-m-d')]]);
        if (empty($r['ok'])) fail($r['error'] ?? 'Couldn’t update your account');
    }
    if ($action === 'profile') {
        $clean = function ($v) { return mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $v)), 0, 100); };
        $r = cbAdmin(['action' => 'update', 'email' => $p['e'], 'fields' => ['Name' => $clean($_POST['name'] ?? ''), 'Studio' => $clean($_POST['studio'] ?? '')]]);
        if (empty($r['ok'])) fail($r['error'] ?? 'Couldn’t save your profile');
    }
    if ($action === 'claim' && $sheet === '') fail('Which sheet?');
    $r = cbAdmin(['action' => 'user', 'email' => $p['e'], 'sheet' => $sheet, 'claim' => $action === 'claim']);
    if (empty($r['ok'])) fail($r['error'] ?? 'Couldn’t open your account');
    cbSignedIn($r['user'], $sheet, !empty($r['taken']));
}

fail('Unknown action');
