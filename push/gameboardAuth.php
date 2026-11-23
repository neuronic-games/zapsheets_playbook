<?php
/**
 * gameboardAuth.php — GameBoard accounts (shared by the board game apps; CardBoard uses them today): a free week, then a subscription.
 *
 * Each person is one row (by email) in the GameBoard Admin sheet (gaccounts.py). After signing
 * in with a code sent to their email, the browser keeps a signed account token. The token
 * carries what the person may do and until when, so the server checks it without reading the
 * Admin sheet; it lasts 12 hours, then the browser asks for a fresh one (gameboardAccount.php).
 *
 *   token = base64url(JSON {e: email, n: name, st: state, u: access until, te: trial end,
 *                           pe: period end, a: {app: {st, u, pe, p}}, all: all-in-one,
 *                           x: token expires}) . "." . HMAC-SHA256
 *   state = trial | code | subscribed | activated | locked — for each app in a (st/u/pe are CardBoard's)
 *   Which apps are paid for is kept in the Admin sheet's app columns (CardBoard … BillBoard, All Apps).
 *
 * An activation key or a comped sheet (STRIPE_PRO_SHEET_IDS) unlocks a spreadsheet for anyone.
 * The signing secret is GAMEBOARD_AUTH_SECRET in .env (CARDBOARD_AUTH_SECRET still works), or one derived from credentials.json.
 */
require_once __DIR__ . '/stripeCommon.php';

const CB_TOKEN_HOURS = 12;
const CB_ADMIN_SHEET = '1heNPe8GCggJVoUlGvDVwBt697LvD7vtQT5yDfsnV4M0';

function cbSecret(): string {
    $s = $_ENV['GAMEBOARD_AUTH_SECRET'] ?? ($_ENV['CARDBOARD_AUTH_SECRET'] ?? '');
    if ($s !== '') return $s;
    return hash('sha256', (string) @file_get_contents(dirname(__DIR__) . '/credentials.json') . '|cardboard-auth');
}
function cbB64(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function cbUnB64(string $s): string { return (string) base64_decode(strtr($s, '-_', '+/')); }

/** Private folder for sign-in codes and rate limits (never served). */
function cbPrivateDir(string $sub = ''): string {
    $d = dirname(__DIR__) . '/sheets/_cardboard_auth';
    if (!is_dir($d)) {
        @mkdir($d, 0700, true);
        @file_put_contents("$d/.htaccess", "Require all denied\nDeny from all\n");
    }
    if ($sub !== '') { $d .= '/' . $sub; if (!is_dir($d)) @mkdir($d, 0700, true); }
    return $d;
}

/** Run gaccounts.py with a request; returns its answer. */
function cbAdmin(array $req): array {
    $python = $_ENV['PYTHON'] ?? 'python3';
    $req['admin'] = $_ENV['GAMEBOARD_ADMIN_SHEET'] ?? ($_ENV['CARDBOARD_ADMIN_SHEET'] ?? CB_ADMIN_SHEET);
    $cmd = escapeshellarg($python) . ' ' . escapeshellarg(__DIR__ . '/gaccounts.py') . ' '
         . escapeshellarg(base64_encode(json_encode($req, JSON_UNESCAPED_UNICODE))) . ' 2>&1';
    $raw = trim((string) shell_exec($cmd));
    foreach (array_reverse(preg_split('/\R/', $raw)) as $line) {
        $line = trim($line);
        if ($line !== '' && $line[0] === '{' && is_array($r = json_decode($line, true))) return $r;
    }
    return ['error' => 'The account service failed' . ($raw !== '' ? ': ' . mb_substr($raw, -200) : '')];
}

/** The Admin-sheet column for each app's paid time; "All Apps" is the all-in-one plan. */
const GB_APP_COLS = ['cardboard' => 'CardBoard', 'devboard' => 'DevBoard', 'pitchboard' => 'PitchBoard', 'billboard' => 'BillBoard'];
const GB_ALL_COL  = 'All Apps';
const GB_FOREVER  = 4102444800;   // 2100

/** An app column's value → [paid until (with a couple of days' grace for renewals), plan, date as written]. */
function gbPaidUntil(string $v): array {
    $v = trim($v);
    if ($v === '') return [0, '', ''];
    if (preg_match('/^(perpetual|forever|lifetime|unlimited|permanent)$/i', $v)) return [GB_FOREVER, 'pro', ''];
    $date = preg_match('/\d{4}-\d\d-\d\d/', $v, $m) ? $m[0] : (preg_split('/\s+/', $v)[0] ?? '');
    $t = strtotime($date . ' UTC');
    return [$t ? $t + 3 * 86400 : 0, stripos($v, 'core') !== false ? 'core' : 'pro', $t ? gmdate('Y-m-d', $t) : ''];
}

/**
 * What a person's Admin-sheet row allows, app by app. The free week, free codes and activation count for
 * every app; a subscription counts for its own app (or every app with the all-in-one).
 * Before the app columns, CardBoard's subscription was kept in Status and Period End: that still counts for CardBoard.
 */
function cbAccessFromUser(array $u): array {
    $now   = time();
    $te    = strtotime((string) ($u['Trial End'] ?? '') . ' UTC') ?: 0;
    $act   = in_array(strtolower(trim((string) ($u['Activated'] ?? ''))), ['yes', 'true', '1', 'y'], true)
          || preg_match('/^\d{4}-\d\d-\d\d/', (string) ($u['Activated'] ?? ''));
    $cu    = strtotime((string) ($u['Code Until'] ?? '') . ' UTC') ?: 0;   // a free code from the Codes tab
    $all   = gbPaidUntil((string) ($u[GB_ALL_COL] ?? ''));
    $codes = [];   // App Codes: "DevBoard 2026-12-20 10:00:00, CardBoard Perpetual" — free codes made for one app
    foreach (explode(',', (string) ($u['App Codes'] ?? '')) as $part) {
        $bits = preg_split('/\s+/', trim($part), 2);
        if (count($bits) === 2) $codes[strtolower($bits[0])] = preg_match('/^perpetual/i', $bits[1]) ? GB_FOREVER : (strtotime($bits[1] . ' UTC') ?: 0);
    }
    $apps  = [];
    foreach (GB_APP_COLS as $app => $colName) {
        [$t, $plan, $pe] = gbPaidUntil((string) ($u[$colName] ?? ''));
        if ($app === 'cardboard' && $t === 0 && in_array(strtolower((string) ($u['Status'] ?? '')), STRIPE_LIVE_STATUSES, true)) {
            $pe = (string) ($u['Period End'] ?? '');   // the older single subscription
            $t  = $pe !== '' ? (strtotime($pe . ' UTC') ?: 0) + 2 * 86400 : $now + 3 * 86400;
            $plan = 'pro';
        }
        if ($all[0] > $t) { $t = $all[0]; $plan = 'pro'; $pe = $all[2]; }
        $c = max($cu, $codes[strtolower($colName)] ?? 0);   // a code for every app, or one for this app
        if ($act)            { $st = 'activated';  $until = GB_FOREVER; $plan = 'pro'; }
        elseif ($t > $now)   { $st = 'subscribed'; $until = max($t, $te, $c); }
        elseif ($c > $now)   { $st = 'code';       $until = max($c, $te); }
        elseif ($te > $now)  { $st = 'trial';      $until = $te; }
        else                 { $st = 'locked';     $until = max($te, $t, $c); }
        $apps[$app] = ['st' => $st, 'u' => $until, 'pe' => $t > $now ? $pe : '', 'p' => $t > $now || $act ? $plan : ''];
    }
    $sh = array_values(array_filter(array_map('trim', explode(',', (string) ($u['Sheets'] ?? ''))), 'stripeValidSheetId'));
    $c  = $apps['cardboard'];
    return [
        'e' => strtolower(trim((string) ($u['Email'] ?? ''))), 'n' => (string) ($u['Name'] ?? ''),
        'st' => $c['st'], 'u' => $c['u'], 'te' => $te, 'pe' => $c['pe'], 'sh' => $sh,   // st/u/pe: CardBoard's, for older pages
        'a' => $apps, 'all' => $all[0] > $now,   // a: each app's {st, u, pe, p}; all: has the all-in-one
        'status' => (string) ($u['Status'] ?? ''), 'studio' => (string) ($u['Studio'] ?? ''),
        'image' => (string) ($u['Image'] ?? ''),   // profile picture (a link)
    ];
}

/** One app's access from a token or cbAccessFromUser(); tokens from before the app columns only knew CardBoard. */
function gbAppAccess(array $p, string $app): array {
    if (isset($p['a'][$app])) return $p['a'][$app];
    return ['st' => $p['st'] ?? 'locked', 'u' => $p['u'] ?? 0, 'pe' => $p['pe'] ?? '', 'p' => ''];
}

function cbIssueToken(array $a): string {
    $p = ['e' => $a['e'], 'n' => $a['n'], 'st' => $a['st'], 'u' => $a['u'], 'te' => $a['te'], 'pe' => $a['pe'],
          'a' => $a['a'] ?? [], 'all' => !empty($a['all']),
          'sh' => array_values($a['sh'] ?? []), 'x' => time() + CB_TOKEN_HOURS * 3600];
    $body = cbB64(json_encode($p, JSON_UNESCAPED_UNICODE));
    return $body . '.' . cbB64(hash_hmac('sha256', $body, cbSecret(), true));
}

/** The token's contents if its signature is good (expired or not), else null. */
function cbReadToken(string $tok): ?array {
    $parts = explode('.', trim($tok));
    if (count($parts) !== 2) return null;
    if (!hash_equals(cbB64(hash_hmac('sha256', $parts[0], cbSecret(), true)), $parts[1])) return null;
    $p = json_decode(cbUnB64($parts[0]), true);
    return is_array($p) && !empty($p['e']) ? $p : null;
}

function cbRequestToken(): string {
    return (string) ($_POST['cbt'] ?? ($_SERVER['HTTP_X_CB_TOKEN'] ?? ''));
}

/** Activation key or comped: the spreadsheet works for everyone. */
function cbSheetUnlocked(string $sheetId): bool {
    if (!stripeValidSheetId($sheetId)) return false;
    $comped = array_filter(array_map('trim', explode(',', $_ENV['STRIPE_PRO_SHEET_IDS'] ?? '')));
    return in_array($sheetId, $comped, true) || activationActive($sheetId);
}

/** Stop any request (even reading the sheet) from someone who hasn't signed in. */
function cbRequireSignIn(): void {
    $p = cbReadToken(cbRequestToken());
    if (!$p) { echo json_encode(['error' => 'Sign in with your GameBoard account first.', 'need_auth' => true]); exit; }
    if (($p['x'] ?? 0) < time()) { echo json_encode(['error' => 'Your sign-in needs refreshing.', 'need_auth' => true, 'refresh' => true]); exit; }
}

/**
 * Stop a request that changes something unless the person is in their free week, subscribed,
 * activated, or the spreadsheet is unlocked. Answers the browser with need_auth / locked.
 */
function cbRequireAccess(string $sheetId, string $app = 'cardboard'): void {
    $p = cbReadToken(cbRequestToken());
    if (!$p) { echo json_encode(['error' => 'Sign in with your GameBoard account to make changes.', 'need_auth' => true]); exit; }
    if (($p['x'] ?? 0) < time()) { echo json_encode(['error' => 'Your sign-in needs refreshing.', 'need_auth' => true, 'refresh' => true]); exit; }
    // Only the person who set the sheet up changes it; everyone else views it. A sign-in from before owners
    // existed has no list: it's refreshed first (the browser does that by itself and tries again).
    if (!isset($p['sh'])) { echo json_encode(['error' => 'Your sign-in needs refreshing.', 'need_auth' => true, 'refresh' => true]); exit; }
    if (!in_array($sheetId, (array) $p['sh'], true)) { echo json_encode(['error' => 'This sheet belongs to another GameBoard account — you can view it, not change it.', 'view_only' => true]); exit; }
    if (cbSheetUnlocked($sheetId)) return;
    if (gbAppAccess($p, $app)['u'] < time()) {
        $name = array_key_exists($app, GB_APP_COLS) ? GB_APP_COLS[$app] : 'this app';
        echo json_encode(['error' => 'Your free week has ended — subscribe to ' . $name . ' (or get all four) to keep going.', 'locked' => true]); exit;
    }
}

/**
 * Send an email (plain text, plus an HTML version when given), using the first that's set up in .env:
 *   RESEND_API_KEY                         — Resend (https://resend.com), over HTTPS
 *   SMTP_HOST, SMTP_PORT (465 or 587), SMTP_USER, SMTP_PASS — any mail server (Gmail app password, your host's mail…)
 *   otherwise PHP's mail(), which needs a mail program on the server
 * The sender is GAMEBOARD_MAIL_FROM (or CARDBOARD_MAIL_FROM; e.g. "GameBoard <no-reply@zapsheets.com>"); with SMTP it
 * must be an address that server may send from. Each try is logged (without the address) to
 * sheets/_cardboard_auth/mail.log. Returns '' when sent, else what went wrong.
 */
function cbSendMail(string $to, string $subject, string $text, string $html = ''): string {
    // With an HTML version, mail() and SMTP send both parts (multipart/alternative); mail apps pick one
    $boundary = 'cb' . bin2hex(random_bytes(8));
    $mime = $html === '' ? ['Content-Type: text/plain; charset=UTF-8', $text]
        : ['MIME-Version: 1.0' . "\r\n" . 'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
           "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n" . str_replace("\n", "\r\n", str_replace("\r\n", "\n", $text)) .
           "\r\n--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html)) .
           "--$boundary--"];
    $from = $_ENV['GAMEBOARD_MAIL_FROM'] ?? ($_ENV['CARDBOARD_MAIL_FROM'] ?? ($_ENV['SMTP_FROM'] ?? 'GameBoard <no-reply@zapsheets.com>'));
    if (!empty($_ENV['RESEND_API_KEY'])) {
        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $_ENV['RESEND_API_KEY'], 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode(['from' => $from, 'to' => [$to], 'subject' => $subject, 'text' => $text] + ($html !== '' ? ['html' => $html] : []))]);
        $out = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
        $why = ($code >= 200 && $code < 300) ? '' : ('Resend ' . $code . ' ' . ($err ?: mb_substr((string) $out, 0, 200)));
        $via = 'resend';
    } elseif (!empty($_ENV['SMTP_HOST'])) {
        $why = cbSmtp($to, $from, $subject, $mime); $via = 'smtp';
    } else {
        $ok = @mail($to, $subject, $mime[1], 'From: ' . $from . "\r\n" . $mime[0]);
        $why = $ok ? '' : 'mail() failed — this server has no mail program; set RESEND_API_KEY or SMTP_HOST in .env';
        $via = 'mail()';
    }
    @file_put_contents(cbPrivateDir() . '/mail.log', gmdate('c') . "  $via  " . preg_replace('/^.*@/', '…@', $to) . '  ' . ($why === '' ? 'sent' : $why) . "\n", FILE_APPEND | LOCK_EX);
    return $why;
}

/** A small SMTP client: port 465 (TLS) or 587/25 (STARTTLS), AUTH LOGIN. Returns '' when sent. */
function cbSmtp(string $to, string $from, string $subject, array $mime): string {   // $mime: [content headers, body]
    $host = $_ENV['SMTP_HOST']; $port = (int) ($_ENV['SMTP_PORT'] ?? 587);
    $fs = @stream_socket_client(($port === 465 ? 'ssl://' : 'tcp://') . $host . ':' . $port, $en, $es, 20);
    if (!$fs) return "SMTP connect to $host:$port failed: $es";
    stream_set_timeout($fs, 20);
    $read = function () use ($fs) { $r = ''; while (($l = fgets($fs, 1000)) !== false) { $r .= $l; if (strlen($l) < 4 || $l[3] === ' ') break; } return $r; };
    $cmd  = function ($c, $want) use ($fs, $read) { if ($c !== null) fwrite($fs, $c . "\r\n"); $r = $read(); if ((int) substr($r, 0, 3) !== $want) throw new Exception(trim(($c && stripos($c, 'AUTH') === false ? $c . ' → ' : '') . $r)); return $r; };
    preg_match('/<([^>]+)>/', $from, $m); $fromAddr = $m[1] ?? trim($from);
    try {
        $cmd(null, 220);
        $me = preg_replace('/[^a-z0-9.\-]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
        $ehlo = $cmd('EHLO ' . $me, 250);
        if ($port !== 465 && stripos($ehlo, 'STARTTLS') !== false) {
            $cmd('STARTTLS', 220);
            if (!stream_socket_enable_crypto($fs, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new Exception('STARTTLS failed');
            $cmd('EHLO ' . $me, 250);
        }
        if (!empty($_ENV['SMTP_USER'])) {
            $cmd('AUTH LOGIN', 334); $cmd(base64_encode($_ENV['SMTP_USER']), 334); $cmd(base64_encode($_ENV['SMTP_PASS'] ?? ''), 235);
        }
        $cmd('MAIL FROM:<' . $fromAddr . '>', 250);
        $cmd('RCPT TO:<' . $to . '>', 250);
        $cmd('DATA', 354);
        $msg = 'From: ' . $from . "\r\nTo: <$to>\r\nSubject: " . $subject . "\r\nDate: " . date('r') . "\r\nMessage-ID: <" . bin2hex(random_bytes(8)) . '@' . $me . ">\r\n" .
               (strpos($mime[0], 'MIME-Version') === 0 ? '' : "MIME-Version: 1.0\r\n") . $mime[0] . "\r\n\r\n" . preg_replace('/^\./m', '..', str_replace("\n", "\r\n", str_replace("\r\n", "\n", $mime[1])));
        $cmd($msg . "\r\n.", 250);
        $cmd('QUIT', 221);
        fclose($fs); return '';
    } catch (Exception $e) { @fclose($fs); return 'SMTP: ' . $e->getMessage(); }
}

