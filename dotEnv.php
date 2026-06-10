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
