<?php
/**
 * Minimal .env loader — no Composer / vendor directory required.
 * Reads KEY=VALUE pairs from .env and populates $_ENV.
 * Lines starting with # are comments. Quoted values are supported.
 */
$envFile = __DIR__ . '/.env';

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;          // skip comments
        if (strpos($line, '=') === false) continue;              // skip invalid lines
        list($key, $value) = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);
        // Strip optional surrounding quotes
        if (strlen($value) >= 2 &&
            (($value[0] === '"'  && substr($value, -1) === '"') ||
             ($value[0] === "'"  && substr($value, -1) === "'"))) {
            $value = substr($value, 1, -1);
        }
        if ($key !== '' && !array_key_exists($key, $_ENV)) {
            $_ENV[$key]    = $value;
            putenv("$key=$value");
        }
    }
}

// Safe defaults if .env is missing or incomplete
if (empty($_ENV['ENVIRONMENT'])) { $_ENV['ENVIRONMENT'] = 'production'; }
if (empty($_ENV['PYTHON']))      { $_ENV['PYTHON']      = 'python3'; }
if (empty($_ENV['BASE_PATH']))   { $_ENV['BASE_PATH']   = '/'; }
if (empty($_ENV['APP_DOMAIN']))  { $_ENV['APP_DOMAIN']  = 'http://localhost:8000'; }

// ── mygameboard.app: the home of the board game apps (PitchBoard, DevBoard, CardBoard, BillBoard,
// GameBoard). Its document root is this same folder, so there everything sits at the root ("/")
// rather than under BASE_PATH ("/app/" on zapsheets.com). Old zapsheets.com links keep working;
// links the apps make from now on (share links, published links, uploads, emails) use
// mygameboard.app — see gbLinkBase().
if (empty($_ENV['GAMEBOARD_HOST'])) { $_ENV['GAMEBOARD_HOST'] = 'mygameboard.app'; }
if (!function_exists('gbRequestHost')) {
    function gbRequestHost(): string { return strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '')); }
    function gbOnGameDomain(): bool { $h = gbRequestHost(); $g = strtolower($_ENV['GAMEBOARD_HOST']); return $h !== '' && ($h === $g || $h === 'www.' . $g); }
    /** Where new links for the board game apps point: https://mygameboard.app/ in production (from
     *  either domain); this server and BASE_PATH anywhere else (local development). Ends with "/". */
    function gbLinkBase(): string {
        $h = gbRequestHost();
        if (gbOnGameDomain() || preg_match('/(^|\.)zapsheets\.com$/', $h)) return 'https://' . $_ENV['GAMEBOARD_HOST'] . '/';
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $bp = trim($_ENV['BASE_PATH'] ?? '/', '/');
        return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/' . ($bp !== '' ? $bp . '/' : '');
    }
}
if (gbOnGameDomain()) { $_ENV['BASE_PATH'] = '/'; putenv('BASE_PATH=/'); }

// The web-server process often runs without HOME set, which breaks Python
// virtualenv wrapper scripts (e.g. set_env_vars.py) that call os.environ['HOME'].
// Derive HOME from the project path if it is not already in the environment.
if (empty(getenv('HOME'))) {
    preg_match('#^(/home/[^/]+)#', __FILE__, $_hm);
    putenv('HOME=' . ($_hm[1] ?? sys_get_temp_dir()));
    unset($_hm);
}
