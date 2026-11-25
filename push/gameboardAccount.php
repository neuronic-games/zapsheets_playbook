<?php
/**
 * gameboardAccount.php — sign in to a GameBoard account (shared by the board game apps) with a code sent by email, and keep the
 * account token fresh. See gameboardAuth.php for the token and the free week.
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
 *   image    — cbt, file (a picture)       → {ok, token, account}   (profile picture: squared to 256 px WebP, kept in
 *                                            images/avatars/, its link in the Admin sheet's Image column; no file = remove)
 *   sheets   — cbt                         → {ok, sheets: [{id, app, title, summary, opened}]}  (your sheets, by app)
 *   password — cbt, current, password      → {ok, token, account}   (a new password; the current one first)
 *   send     — cbt, email, mode=change     → {ok}  (a code to a NEW email address, to move the account to it)
 *   email    — cbt, email, code, current   → {ok, token, account}   (the account's sign-in email becomes the new one;
 *                                            subscriptions follow; sign-ins from before the change must sign in again)
 *   redeem   — cbt, code                   → {ok, token, account, days, apps}  (a free code from the Admin sheet's Codes tab —
 *                                            every app, or only the apps in its App column —
 *                                            or an activation key: unlocked with no time limit)
 *   waitlist — cbt                         → {ok, users: [{email, name, created, waiting, apps}]}  (the Waiting Room, oldest
 *                                            first, and people let in to only some apps; admins only)
 *   letin    — cbt, emails (JSON list), apps (JSON list of app keys; none = every app)
 *                                         → {ok, users, done: [{email, apps, mailed}], errors}  (a free week of those apps
 *                                            starts now — the other apps stay closed — and each person is emailed; admins only)
 * Admins: Role = admin in their Admin-sheet row.
 * The Waiting Room (settings tab: WaitingRoomOn = TRUE): a new account waits (Status = waiting) instead of starting
 * its free week. Signing up, logging in or refreshing then answers {error, waiting: true} with no token, and the
 * apps go to {base}waiting.
 * app (optional, default cardboard) — the app asking: account.state/until/period_end are that app's.
 * account = {email, name, state: trial|code|subscribed|activated|locked, until, trial_end, period_end, status, sheet_unlocked,
 *            apps: {cardboard|devboard|pitchboard|billboard: {state, until, period_end, plan}}, all_in_one}
 */
error_reporting(0);
header('Content-Type: application/json');
require_once __DIR__ . '/gameboardAuth.php';

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
    $app = (string) ($_POST['app'] ?? 'cardboard');   // the app asking: state/until/period_end are its own
    if (!array_key_exists($app, GB_APP_COLS)) $app = 'cardboard';
    $apps = [];
    foreach (array_keys(GB_APP_COLS) as $k) {
        $x = gbAppAccess($a, $k);
        $apps[$k] = ['state' => $x['st'], 'until' => $d($x['u']), 'period_end' => $x['pe'], 'plan' => $x['p']];
    }
    return ['email' => $a['e'], 'name' => $a['n'], 'studio' => $a['studio'] ?? '', 'image' => $a['image'] ?? '', 'state' => $apps[$app]['state'], 'until' => $apps[$app]['until'],
            'trial_end' => $d($a['te']), 'period_end' => $apps[$app]['period_end'], 'status' => $a['status'] ?? '',
            'apps' => $apps, 'all_in_one' => !empty($a['all']),   // every app's access; the all-in-one plan
            'sheet_unlocked' => $sheet !== '' && cbSheetUnlocked($sheet),
            'sheet_paid' => $sheet !== '' && $app !== 'cardboard' && stripeSheetPlan($sheet, $app)['is_core'],   // paid on the sheet, before accounts
            'owner' => $sheet !== '' && in_array($sheet, $a['sh'] ?? [], true),   // set this sheet up: may change it (else view only)
            'portal_url' => $_ENV['STRIPE_PORTAL_URL'] ?? ''];
}
// Which apps a sheet is used in, and when each last opened it (sheets/{id}/_gb_apps.json) — the GameBoard home lists
// your sheets by app. Kept when the sheet's owner opens it in an app.
function gbNoteApp(string $sheet, string $app): void {
    if ($sheet === '' || !array_key_exists($app, GB_APP_COLS)) return;
    $dir = dirname(__DIR__) . '/sheets/' . $sheet;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return;
    $f = $dir . '/_gb_apps.json';
    $apps = json_decode((string) @file_get_contents($f), true) ?: [];
    if (($apps[$app] ?? 0) > time() - 600) return;   // at most every ten minutes
    $apps[$app] = time();
    @file_put_contents($f, json_encode($apps), LOCK_EX);
}
const GB_WAITING_MSG = 'Thank you for creating your account. During this period we have limited new accounts on our site. You are added to our waitlist and will be notified when you may log in.';
function cbSignedIn(array $u, string $sheet, bool $taken = true): void {
    if (strtolower(trim((string) ($u['Status'] ?? ''))) === 'waiting') fail(GB_WAITING_MSG, ['waiting' => true]);   // no token until let in
    $a = cbAccessFromUser($u);
    $acc = cbAccountOut($a, $sheet);
    if (!empty($acc['owner'])) gbNoteApp($sheet, (string) ($_POST['app'] ?? 'cardboard'));
    $acc['unclaimed'] = $sheet !== '' && !$acc['owner'] && !$taken;   // nobody has set this sheet up: it can be made yours
    ok(['token' => cbIssueToken($a), 'account' => $acc]);
}

/** The sign-in email in GameBoard's colors: navy band, cream/red wordmark, the code large. Tables and inline styles for mail apps. */
function cbCodeEmailHtml(string $code, string $mode = 'signup'): string {
    $base   = gbLinkBase();   // https://mygameboard.app/ in production
    $logo   = htmlspecialchars($base . 'images/gameboard_icon_180.png', ENT_QUOTES);
    $app    = htmlspecialchars($base . 'gameboard', ENT_QUOTES);
    // One box, spaced out but copied as plain digits (separate boxes copy with gaps between them)
    $digits = '<td style="padding:14px 22px 14px 30px;background:#f3f1ff;border:1px solid #ddd6ff;border-radius:12px;font-family:\'SFMono-Regular\',Menlo,Consolas,monospace;font-size:32px;font-weight:700;letter-spacing:10px;color:#2e2763;">' . $code . '</td>';
    $font = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";
    return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light only"><title>Your GameBoard code</title></head>' .
      '<body style="margin:0;padding:0;background:#f3f2fa;">' .
      '<div style="display:none;max-height:0;overflow:hidden;">Your GameBoard code is ' . $code . '. It works for 15 minutes.</div>' .
      '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f2fa;"><tr><td align="center" style="padding:32px 16px;">' .
      '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 18px rgba(46,39,99,.10);">' .
        '<tr><td style="background:#14132a;padding:22px 28px;">' .
          '<table role="presentation" cellpadding="0" cellspacing="0"><tr>' .
            '<td style="padding-right:12px;"><img src="' . $logo . '" width="40" height="40" alt="" style="display:block;border-radius:9px;"></td>' .
            '<td style="font-family:' . $font . ';font-size:22px;font-weight:800;letter-spacing:.5px;"><span style="color:#f6efe2;">Game</span><span style="color:#f0766a;">Board</span></td>' .
          '</tr></table></td></tr>' .
        '<tr><td style="padding:30px 28px 8px;font-family:' . $font . ';color:#1b1b2b;">' .
          '<div style="font-size:20px;font-weight:700;color:#2e2763;margin:0 0 8px;">' . ($mode === 'reset' ? 'Reset your password' : ($mode === 'change' ? 'Confirm your new email' : 'Confirm your email')) . '</div>' .
          '<div style="font-size:15px;line-height:1.55;color:#555a70;margin:0 0 22px;">' . ($mode === 'reset' ? 'Enter this code to choose a new password for your GameBoard account.' : ($mode === 'change' ? 'Enter this code to sign in to your GameBoard account with this email from now on.' : 'Enter this code to finish creating your GameBoard account.')) . ' It works for 15 minutes.</div>' .
          '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px;"><tr>' . $digits . '</tr></table>' .
          '<div style="font-size:13px;line-height:1.55;color:#8a8fa8;margin:0 0 22px;">One account for PitchBoard, DevBoard, CardBoard and BillBoard. Your first week is free, with every feature. If you didn’t ask for this code, you can ignore this email.</div>' .
        '</td></tr>' .
        '<tr><td style="padding:16px 28px 22px;border-top:1px solid #eeecf6;font-family:' . $font . ';font-size:12px;color:#9aa0b8;">' .
          '<a href="' . $app . '" style="color:#d9534a;text-decoration:none;font-weight:600;">Open GameBoard</a> &nbsp;·&nbsp; Tools for board game makers &nbsp;·&nbsp; © ' . gmdate('Y') . ' ZapSheets' .
        '</td></tr>' .
      '</table></td></tr></table></body></html>';
}

/** "DevBoard", "DevBoard and PitchBoard", "CardBoard, DevBoard and PitchBoard" */
function gbAppList(array $apps): string {
    if (count($apps) >= 4) return 'GameBoard';
    if (count($apps) <= 1) return (string) ($apps[0] ?? 'GameBoard');
    return implode(', ', array_slice($apps, 0, -1)) . ' and ' . end($apps);
}
/** The email that says someone's been let in from the Waiting Room (to some apps, or all): the same look as the code email. */
function gbLetInText(array $apps): string {
    $what = count($apps) >= 4 ? 'every GameBoard app' : gbAppList($apps);
    return "Good news: you can now use " . $what . ".\r\n\r\nLog in at " . gbLinkBase() . "gameboard with the email and password you signed up with. "
         . "Your free week of " . $what . ", with every feature, starts now.\r\n\r\n— GameBoard by ZapSheets";
}
function gbLetInHtml(string $name, array $apps): string {
    $what = htmlspecialchars(count($apps) >= 4 ? 'every GameBoard app' : gbAppList($apps), ENT_QUOTES);
    $base = gbLinkBase();
    $logo = htmlspecialchars($base . 'images/gameboard_icon_180.png', ENT_QUOTES);
    $app  = htmlspecialchars($base . 'gameboard', ENT_QUOTES);
    $font = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";
    $hi   = trim($name) !== '' ? 'Hi ' . htmlspecialchars(preg_split('/\s+/', trim($name))[0], ENT_QUOTES) . ', your' : 'Your';
    return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light only"><title>You can now log in to GameBoard</title></head>' .
      '<body style="margin:0;padding:0;background:#f3f2fa;">' .
      '<div style="display:none;max-height:0;overflow:hidden;">Your GameBoard account is ready — log in and start your free week.</div>' .
      '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f2fa;"><tr><td align="center" style="padding:32px 16px;">' .
      '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 18px rgba(46,39,99,.10);">' .
        '<tr><td style="background:#14132a;padding:22px 28px;">' .
          '<table role="presentation" cellpadding="0" cellspacing="0"><tr>' .
            '<td style="padding-right:12px;"><img src="' . $logo . '" width="40" height="40" alt="" style="display:block;border-radius:9px;"></td>' .
            '<td style="font-family:' . $font . ';font-size:22px;font-weight:800;letter-spacing:.5px;"><span style="color:#f6efe2;">Game</span><span style="color:#f0766a;">Board</span></td>' .
          '</tr></table></td></tr>' .
        '<tr><td style="padding:30px 28px 8px;font-family:' . $font . ';color:#1b1b2b;">' .
          '<div style="font-size:20px;font-weight:700;color:#2e2763;margin:0 0 8px;">You can now log in</div>' .
          '<div style="font-size:15px;line-height:1.55;color:#555a70;margin:0 0 22px;">' . $hi . ' GameBoard account is ready for <b style="color:#2e2763;">' . $what . '</b>. Log in with the email and password you signed up with — your free week, with every feature, starts now.</div>' .
          '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 26px;"><tr><td style="background:#e5604f;border-radius:10px;">' .
            '<a href="' . $app . '" style="display:inline-block;padding:13px 26px;font-family:' . $font . ';font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;">Log in to GameBoard</a></td></tr></table>' .
          '<div style="font-size:13px;line-height:1.55;color:#8a8fa8;margin:0 0 22px;">One account for PitchBoard, DevBoard, CardBoard and BillBoard.</div>' .
        '</td></tr>' .
        '<tr><td style="padding:16px 28px 22px;border-top:1px solid #eeecf6;font-family:' . $font . ';font-size:12px;color:#9aa0b8;">' .
          '<a href="' . $app . '" style="color:#d9534a;text-decoration:none;font-weight:600;">Open GameBoard</a> &nbsp;·&nbsp; Tools for board game makers &nbsp;·&nbsp; © ' . gmdate('Y') . ' ZapSheets' .
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
/** The signed-in person from the token (cbt), or a need_auth answer. A sign-in from before the account's email changed must sign in again. */
function cbTokenIn(): array {
    $p = cbReadToken(cbRequestToken());
    if (!$p || ($p['x'] ?? 0) < time() - 30 * 86400) fail('Please sign in again', ['need_auth' => true]);
    $mv = json_decode((string) @file_get_contents(cbPrivateDir('moved') . '/' . hash('sha256', strtolower($p['e'])) . '.json'), true);
    if ($mv && (($p['x'] ?? 0) - CB_TOKEN_HOURS * 3600) <= ($mv['t'] ?? 0)) fail('Your account’s email changed — please sign in with ' . ($mv['to'] ?? 'the new one'), ['need_auth' => true]);
    return $p;
}
function cbFind(string $email): ?array {
    $f = cbAdmin(['action' => 'find', 'field' => 'Email', 'value' => $email]);
    if (empty($f['ok'])) fail($f['error'] ?? 'Couldn’t check your account');
    return $f['user'] ?: null;
}

/** The emailed code is right (and for this kind of step): used up. Else fails. */
function cbCheckCode(string $file, string $email, string $code, string $from): void {
    $c = json_decode((string) @file_get_contents($file), true);
    if (!$c || ($c['x'] ?? 0) < time()) { @unlink($file); fail('That code has expired — ask for a new one'); }
    if ((($c['mode'] ?? '') === 'change') !== ($from !== '') || ($from !== '' && ($c['from'] ?? '') !== $from)) fail('That code isn’t right');
    if (!hash_equals($c['h'], hash_hmac('sha256', $email . '|' . $code, cbSecret()))) {
        $c['tries'] = ($c['tries'] ?? 0) + 1;
        if ($c['tries'] >= 5) { @unlink($file); fail('Too many wrong codes — ask for a new one'); }
        file_put_contents($file, json_encode($c), LOCK_EX); usleep(400000);
        fail('That code isn’t right');
    }
    @unlink($file);
}
/** The person's current password is right (an account without one yet passes). Else fails. */
function cbCheckPassword(string $email): array {
    if (!cbLimit('pwcheck|' . $email, 10)) fail('Too many tries — please wait a while and try again');
    $u = cbFind($email);
    if (!$u) fail('Please sign in again', ['need_auth' => true]);
    $hash = trim((string) ($u['Password Hash'] ?? ''));
    if ($hash !== '' && !password_verify((string) ($_POST['current'] ?? ''), $hash)) { usleep(500000); fail('Your current password isn’t right'); }
    return $u;
}

// Signing up and resetting a password both prove the email with a code first.
//   mode signup — a new account (an account that has no password yet may set one here)
//   mode reset  — an existing account chooses a new password
if ($action === 'send') {
    $email = cbEmailIn();
    $mode  = in_array($_POST['mode'] ?? '', ['reset', 'change'], true) ? $_POST['mode'] : 'signup';
    $from  = '';
    if ($mode === 'change') {   // moving a signed-in account to a new email: the code goes to the new address
        $me = cbTokenIn(); $from = strtolower($me['e']);
        if ($email === $from) fail('That’s already your email');
    }
    if (!cbLimit('send|' . $email, 5) || !cbLimit('sendip|' . $ip, 20)) fail('Too many codes asked for — please wait a while and try again');
    $u = cbFind($email);
    if ($mode === 'signup' && $u && trim((string) ($u['Password Hash'] ?? '')) !== '') fail('You already have a GameBoard account — log in instead.', ['has_account' => true]);
    if ($mode === 'change' && $u) fail('That email already has a GameBoard account');
    if ($mode === 'reset' && !$u) fail('There’s no GameBoard account for that email — start your free week instead.', ['no_account' => true]);
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $file = cbPrivateDir('codes') . '/' . hash('sha256', $email) . '.json';
    file_put_contents($file, json_encode(['h' => hash_hmac('sha256', $email . '|' . $code, cbSecret()), 'x' => time() + 900, 'tries' => 0, 'mode' => $mode, 'from' => $from]), LOCK_EX);
    $body = "Your GameBoard code is:\r\n\r\n    $code\r\n\r\nIt works for 15 minutes. If you didn't ask for it, you can ignore this email.\r\n\r\n— GameBoard by ZapSheets";
    $why  = cbSendMail($email, 'Your GameBoard code: ' . $code, $body, cbCodeEmailHtml($code, $mode));
    if ($why !== '' && empty($_ENV['GAMEBOARD_AUTH_DEBUG'] ?? ($_ENV['CARDBOARD_AUTH_DEBUG'] ?? ''))) { @unlink($file); fail('The email couldn’t be sent — please try again later'); }
    if ($mode === 'change') $u = null;
    ok(['existing' => (bool) $u] + (!empty($_ENV['GAMEBOARD_AUTH_DEBUG'] ?? ($_ENV['CARDBOARD_AUTH_DEBUG'] ?? '')) ? ['debug_code' => $code] : []));   // debug code only on a test server
}

// The code from the email, plus the password to keep: creates the account (free week) or sets the new password
if ($action === 'verify') {
    $email = cbEmailIn();
    $pw    = cbPasswordIn();
    $code  = preg_replace('/\D/', '', (string) ($_POST['code'] ?? ''));
    $file  = cbPrivateDir('codes') . '/' . hash('sha256', $email) . '.json';
    $c = json_decode((string) @file_get_contents($file), true);
    cbCheckCode($file, $email, $code, '');
    $r = cbAdmin(['action' => 'user', 'email' => $email, 'name' => trim((string) ($_POST['name'] ?? '')), 'sheet' => $sheet, 'signup' => true,
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
    $p = cbTokenIn();
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
    ok(['token' => cbIssueToken($a), 'account' => cbAccountOut($a, $sheet), 'days' => (int) ($r['days'] ?? 0), 'apps' => $r['apps'] ?? []]);   // apps: the code's apps ([] = every app)
}

// sheets — cbt → {ok, sheets: [{id, app, title, summary, opened (unix)}]}: the sheets this account set up, one entry for
// each app the sheet is used in (newest first). The app comes from _gb_apps.json, or for older sheets from what the
// server keeps for them (CardBoard's files, pitches, contracts, a BillBoard site).
if ($action === 'sheets') {
    $p = cbTokenIn();
    require_once __DIR__ . '/linkPreview.php';
    $out = [];
    foreach (array_reverse((array) ($p['sh'] ?? [])) as $id) {
        if (!stripeValidSheetId($id)) continue;
        $dir = dirname(__DIR__) . '/sheets/' . $id;
        $apps = json_decode((string) @file_get_contents($dir . '/_gb_apps.json'), true) ?: [];
        $has = function ($f) use ($dir) { return is_file($dir . '/' . $f); };
        $guess = [
            'cardboard'  => $has('_cardboard_info.json') || $has('_cardboard_title.txt') || $has('_cardboard_edited'),
            'pitchboard' => $has('pitches.json'),
            'devboard'   => $has('contracts.json') || $has('estimates.json') || $has('todos.json'),
            'billboard'  => $has('site.json') || $has('about.json'),
        ];
        foreach ($guess as $k => $yes) if ($yes && !isset($apps[$k])) $apps[$k] = (int) @filemtime($dir);
        if (!$apps) $apps = ['' => (int) @filemtime($dir)];   // not opened in an app yet
        foreach ($apps as $app => $when) {
            [$title, $what] = $app !== '' ? gbSheetSummary($app, $id) : ['', ''];
            $out[] = ['id' => $id, 'app' => $app, 'title' => $title, 'summary' => $what, 'opened' => (int) $when];
        }
    }
    usort($out, function ($x, $y) { return $y['opened'] <=> $x['opened']; });
    ok(['sheets' => $out]);
}

// The Waiting Room, for admins (Role = admin in the Admin sheet): who's waiting, and letting someone in
if ($action === 'waitlist' || $action === 'letin') {
    $p = cbTokenIn();
    $me = cbFind(strtolower($p['e']));
    if (!$me || strtolower(trim((string) ($me['Role'] ?? ''))) !== 'admin') fail('Only admins can see the Waiting Room', ['not_admin' => true]);
    $extra = [];
    if ($action === 'letin') {
        $emails = array_values(array_filter(array_map(function ($e) { return strtolower(trim((string) $e)); }, (array) json_decode((string) ($_POST['emails'] ?? '[]'), true)),
                  function ($e) { return filter_var($e, FILTER_VALIDATE_EMAIL); }));
        $apps = array_values(array_intersect(array_map('strtolower', (array) json_decode((string) ($_POST['apps'] ?? '[]'), true)), array_keys(GB_APP_COLS)));
        if (!$emails) fail('Choose who to invite');
        if (!$apps) fail('Choose at least one app');
        $r = cbAdmin(['action' => 'letin', 'emails' => $emails, 'apps' => $apps]);
        if (empty($r['ok'])) fail($r['error'] ?? 'Couldn’t invite them');
        $done = [];
        foreach ($r['done'] ?? [] as $d) {
            $why = cbSendMail($d['Email'], 'You can now use ' . gbAppList($d['Apps']), gbLetInText($d['Apps']), gbLetInHtml((string) $d['Name'], $d['Apps']));
            $done[] = ['email' => $d['Email'], 'apps' => $d['Apps'], 'mailed' => $why === ''];
        }
        $extra = ['done' => $done, 'errors' => $r['errors'] ?? []];
    }
    $w = cbAdmin(['action' => 'waiting']);
    if (empty($w['ok'])) fail($w['error'] ?? 'Couldn’t read the Waiting Room');
    $users = array_map(function ($x) { return ['email' => $x['Email'], 'name' => $x['Name'], 'created' => $x['Created'], 'waiting' => !empty($x['Waiting']), 'apps' => $x['Apps'] ?? []]; }, $w['users'] ?? []);
    ok(['users' => $users] + $extra);
}

// claim — cbt, sheet: set this sheet up as yours (Connect on the home page, or "Make it mine" when nobody has it)
// A new sign-in email: the code sent to it, and the current password
if ($action === 'email') {
    $p = cbTokenIn(); $old = strtolower($p['e']);
    $email = cbEmailIn();
    cbCheckPassword($old);
    cbCheckCode(cbPrivateDir('codes') . '/' . hash('sha256', $email) . '.json', $email, preg_replace('/\D/', '', (string) ($_POST['code'] ?? '')), $old);
    $r = cbAdmin(['action' => 'rename', 'email' => $old, 'new_email' => $email]);
    if (empty($r['ok'])) fail($r['error'] ?? 'Couldn’t change your email');
    // Sign-ins from before the change (other browsers) sign in again, with the new email
    @file_put_contents(cbPrivateDir('moved') . '/' . hash('sha256', $old) . '.json', json_encode(['to' => $email, 't' => time()]), LOCK_EX);
    // Subscriptions follow the account (renewals are matched by this email), and receipts go to the new address
    foreach ((stripeRequest('GET', 'subscriptions/search', ['query' => "metadata['cb_email']:'" . str_replace("'", "\\'", $old) . "'", 'limit' => 100])['data'] ?? []) as $s) {
        if (!empty($s['id'])) stripeRequest('POST', 'subscriptions/' . $s['id'], ['metadata[cb_email]' => $email]);
        if (!empty($s['customer']) && is_string($s['customer'])) stripeRequest('POST', 'customers/' . $s['customer'], ['email' => $email]);
    }
    if (!empty($r['user']['Stripe Customer'])) stripeRequest('POST', 'customers/' . trim((string) $r['user']['Stripe Customer']), ['email' => $email]);
    $r = cbAdmin(['action' => 'user', 'email' => $email, 'sheet' => $sheet]);
    if (empty($r['ok'])) fail($r['error'] ?? 'Couldn’t open your account');
    cbSignedIn($r['user'], $sheet, !empty($r['taken']));
}

if ($action === 'refresh' || $action === 'activate' || $action === 'profile' || $action === 'claim' || $action === 'image' || $action === 'password') {
    $p = cbTokenIn();
    if ($action === 'password') {
        cbCheckPassword($p['e']);
        $pw = cbPasswordIn();
        $r = cbAdmin(['action' => 'update', 'email' => $p['e'], 'fields' => ['Password Hash' => password_hash($pw, PASSWORD_DEFAULT)]]);
        if (empty($r['ok'])) fail($r['error'] ?? 'Couldn’t change your password');
    }
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
    if ($action === 'image') {
        $url = '';
        $tmp = $_FILES['file']['tmp_name'] ?? '';
        if ($tmp !== '') {
            if (!is_uploaded_file($tmp) || filesize($tmp) > 15 * 1024 * 1024) fail('That picture is too big (15 MB at most)');
            $info = @getimagesize($tmp);
            $src = !$info ? false : ($info[2] === IMAGETYPE_JPEG ? @imagecreatefromjpeg($tmp) : ($info[2] === IMAGETYPE_PNG ? @imagecreatefrompng($tmp)
                 : ($info[2] === IMAGETYPE_GIF ? @imagecreatefromgif($tmp) : ($info[2] === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : false))));
            if (!$src) fail('Use a JPEG, PNG, GIF or WebP picture');
            if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {   // phone photos: turn them the right way up
                $o = (int) (@exif_read_data($tmp)['Orientation'] ?? 1);
                if ($o === 3) $src = imagerotate($src, 180, 0); elseif ($o === 6) $src = imagerotate($src, -90, 0); elseif ($o === 8) $src = imagerotate($src, 90, 0);
            }
            $w = imagesx($src); $h = imagesy($src); $side = min($w, $h); $N = 256;   // the middle square, 256 px
            $dst = imagecreatetruecolor($N, $N);
            imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
            imagecopyresampled($dst, $src, 0, 0, (int) (($w - $side) / 2), (int) (($h - $side) / 2), $N, $N, $side, $side);
            $dir = dirname(__DIR__) . '/images/avatars';
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            $webp = function_exists('imagewebp');   // WebP (smaller); JPEG where the server can't make WebP
            $name = substr(hash('sha256', $p['e'] . '|' . cbSecret()), 0, 20) . '-' . time() . ($webp ? '.webp' : '.jpg');
            foreach (glob($dir . '/' . substr($name, 0, 20) . '-*.{jpg,webp}', GLOB_BRACE) ?: [] as $old) @unlink($old);   // the one before
            $okSave = $webp ? @imagewebp($dst, $dir . '/' . $name, 82) : @imagejpeg($dst, $dir . '/' . $name, 86);
            if ($okSave && $webp && @filesize($dir . '/' . $name) === 0) {   // some GD builds write an empty WebP: fall back to JPEG
                @unlink($dir . '/' . $name); $name = substr($name, 0, -5) . '.jpg'; $okSave = @imagejpeg($dst, $dir . '/' . $name, 86);
            }
            if (!$okSave) fail('Couldn’t keep the picture');
            $url = (function_exists('gbLinkBase') ? gbLinkBase() : '/') . 'images/avatars/' . $name;
        }
        $r = cbAdmin(['action' => 'update', 'email' => $p['e'], 'fields' => ['Image' => $url]]);
        if (empty($r['ok'])) fail($r['error'] ?? 'Couldn’t save your picture');
    }
    if ($action === 'claim' && $sheet === '') fail('Which sheet?');
    $r = cbAdmin(['action' => 'user', 'email' => $p['e'], 'sheet' => $sheet, 'claim' => $action === 'claim']);
    if (empty($r['ok'])) fail($r['error'] ?? 'Couldn’t open your account');
    cbSignedIn($r['user'], $sheet, !empty($r['taken']));
}

fail('Unknown action');
