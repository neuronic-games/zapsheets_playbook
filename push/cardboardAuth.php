<?php
/**
 * cardboardAuth.php — CardBoard accounts: a free week, then a subscription.
 *
 * Each person is one row (by email) in the CardBoard Admin sheet (gcbadmin.py). After signing
 * in with a code sent to their email, the browser keeps a signed account token. The token
 * carries what the person may do and until when, so the server checks it without reading the
 * Admin sheet; it lasts 12 hours, then the browser asks for a fresh one (cardboardAccount.php).
 *
 *   token = base64url(JSON {e: email, n: name, st: state, u: access until, te: trial end,
 *                           pe: period end, x: token expires}) . "." . HMAC-SHA256
 *   state = trial | subscribed | activated | locked
 *
 * An activation key or a comped sheet (STRIPE_PRO_SHEET_IDS) unlocks a spreadsheet for anyone.
 * The signing secret is CARDBOARD_AUTH_SECRET in .env, or one derived from credentials.json.
 */
require_once __DIR__ . '/stripeCommon.php';

const CB_TOKEN_HOURS = 12;
const CB_ADMIN_SHEET = '1heNPe8GCggJVoUlGvDVwBt697LvD7vtQT5yDfsnV4M0';

function cbSecret(): string {
    $s = $_ENV['CARDBOARD_AUTH_SECRET'] ?? '';
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

/** Run gcbadmin.py with a request; returns its answer. */
function cbAdmin(array $req): array {
    $python = $_ENV['PYTHON'] ?? 'python3';
    $req['admin'] = $_ENV['CARDBOARD_ADMIN_SHEET'] ?? CB_ADMIN_SHEET;
    $cmd = escapeshellarg($python) . ' ' . escapeshellarg(__DIR__ . '/gcbadmin.py') . ' '
         . escapeshellarg(base64_encode(json_encode($req, JSON_UNESCAPED_UNICODE))) . ' 2>&1';
    $raw = trim((string) shell_exec($cmd));
    foreach (array_reverse(preg_split('/\R/', $raw)) as $line) {
        $line = trim($line);
        if ($line !== '' && $line[0] === '{' && is_array($r = json_decode($line, true))) return $r;
    }
    return ['error' => 'The account service failed' . ($raw !== '' ? ': ' . mb_substr($raw, -200) : '')];
}

/** What a person's Admin-sheet row allows. */
function cbAccessFromUser(array $u): array {
    $now   = time();
    $te    = strtotime((string) ($u['Trial End'] ?? '') . ' UTC') ?: 0;
    $pe    = (string) ($u['Period End'] ?? '');
    $peT   = $pe !== '' ? (strtotime($pe . ' UTC') ?: 0) + 2 * 86400 : 0;   // a couple of days' grace for renewals
    $live  = in_array(strtolower((string) ($u['Status'] ?? '')), STRIPE_LIVE_STATUSES, true);
    $act   = in_array(strtolower(trim((string) ($u['Activated'] ?? ''))), ['yes', 'true', '1', 'y'], true)
          || preg_match('/^\d{4}-\d\d-\d\d/', (string) ($u['Activated'] ?? ''));
    $subUntil = $live ? ($peT ?: $now + 3 * 86400) : 0;
    if ($act)                       { $st = 'activated';  $until = 4102444800; }   // 2100
    elseif ($subUntil > $now)       { $st = 'subscribed'; $until = max($subUntil, $te); }
    elseif ($te > $now)             { $st = 'trial';      $until = $te; }
    else                            { $st = 'locked';     $until = max($te, $subUntil); }
    return [
        'e' => strtolower(trim((string) ($u['Email'] ?? ''))), 'n' => (string) ($u['Name'] ?? ''),
        'st' => $st, 'u' => $until, 'te' => $te, 'pe' => $pe,
        'status' => (string) ($u['Status'] ?? ''),
    ];
}

function cbIssueToken(array $a): string {
    $p = ['e' => $a['e'], 'n' => $a['n'], 'st' => $a['st'], 'u' => $a['u'], 'te' => $a['te'], 'pe' => $a['pe'],
          'x' => time() + CB_TOKEN_HOURS * 3600];
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

/**
 * Stop a request that changes something unless the person is in their free week, subscribed,
 * activated, or the spreadsheet is unlocked. Answers the browser with need_auth / locked.
 */
function cbRequireAccess(string $sheetId): void {
    if (cbSheetUnlocked($sheetId)) return;
    $p = cbReadToken(cbRequestToken());
    if (!$p) { echo json_encode(['error' => 'Sign in to CardBoard to make changes.', 'need_auth' => true]); exit; }
    if (($p['x'] ?? 0) < time()) { echo json_encode(['error' => 'Your sign-in needs refreshing.', 'need_auth' => true, 'refresh' => true]); exit; }
    if (($p['u'] ?? 0) < time()) { echo json_encode(['error' => 'Your free week has ended — subscribe to keep designing.', 'locked' => true]); exit; }
}
