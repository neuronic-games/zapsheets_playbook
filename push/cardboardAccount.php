<?php
/**
 * cardboardAccount.php — sign in to CardBoard with a code sent by email, and keep the
 * account token fresh. See cardboardAuth.php for the token and the free week.
 *
 * POST action and params:
 *   send     — email                       → {ok}            (emails a 6-digit code, valid 15 minutes)
 *   verify   — email, code, name?, sheet?  → {ok, token, account}
 *   refresh  — cbt (token), sheet?         → {ok, token, account}   (re-reads the Admin sheet)
 *   activate — cbt, key, sheet?            → {ok, token, account}   (activation key: everything, no time limit)
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
    return ['email' => $a['e'], 'name' => $a['n'], 'state' => $a['st'], 'until' => $d($a['u']), 'trial_end' => $d($a['te']),
            'period_end' => $a['pe'], 'status' => $a['status'] ?? '', 'sheet_unlocked' => $sheet !== '' && cbSheetUnlocked($sheet),
            'portal_url' => $_ENV['STRIPE_PORTAL_URL'] ?? ''];
}
function cbSignedIn(array $u, string $sheet): void {
    $a = cbAccessFromUser($u);
    ok(['token' => cbIssueToken($a), 'account' => cbAccountOut($a, $sheet)]);
}

$action = $_POST['action'] ?? '';
$sheet  = trim((string) ($_POST['sheet'] ?? ''));
if ($sheet !== '' && !stripeValidSheetId($sheet)) $sheet = '';
$ip = $_SERVER['REMOTE_ADDR'] ?? '';

if ($action === 'send') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 200) fail('Enter a valid email address');
    if (!cbLimit('send|' . $email, 5) || !cbLimit('sendip|' . $ip, 20)) fail('Too many codes asked for — please wait a while and try again');
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $file = cbPrivateDir('codes') . '/' . hash('sha256', $email) . '.json';
    file_put_contents($file, json_encode(['h' => hash_hmac('sha256', $email . '|' . $code, cbSecret()), 'x' => time() + 900, 'tries' => 0]), LOCK_EX);
    $body = "Your CardBoard sign-in code is:\r\n\r\n    $code\r\n\r\nIt works for 15 minutes. If you didn't ask for it, you can ignore this email.\r\n\r\n— ZapSheets";
    $why  = cbSendMail($email, 'Your CardBoard code: ' . $code, $body);
    if ($why !== '' && empty($_ENV['CARDBOARD_AUTH_DEBUG'])) { @unlink($file); fail('The email couldn’t be sent — please try again later'); }
    ok(!empty($_ENV['CARDBOARD_AUTH_DEBUG']) ? ['debug_code' => $code] : []);   // debug only on a test server
}

if ($action === 'verify') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
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
    $r = cbAdmin(['action' => 'user', 'email' => $email, 'name' => trim((string) ($_POST['name'] ?? '')), 'sheet' => $sheet]);
    if (empty($r['ok'])) fail($r['error'] ?? 'Couldn’t open your account');
    cbSignedIn($r['user'], $sheet);
}

if ($action === 'refresh' || $action === 'activate') {
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
    $r = cbAdmin(['action' => 'user', 'email' => $p['e'], 'sheet' => $sheet]);
    if (empty($r['ok'])) fail($r['error'] ?? 'Couldn’t open your account');
    cbSignedIn($r['user'], $sheet);
}

fail('Unknown action');
