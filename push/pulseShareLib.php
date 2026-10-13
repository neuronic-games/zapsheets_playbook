<?php
/**
 * pulseShareLib.php — read-only share links for one PulseBoard tab.
 *
 * Shares live in shares/pulse-group-readonly/{token}.json:
 *   { sheet_id, tab, tab_safe, created }
 * and are served at {BASE_PATH}/share/{token} by source/pulseboard/shared/index.php.
 */

/** Same filename-safe tab name gpulsepulseboard.py uses for the JSON cache. */
function pulseboard_tab_safe(string $tab): string {
    return preg_replace('#[/\\\\]#', '-', $tab);
}

/**
 * Return the share token for this sheet + tab, creating one if it doesn't exist.
 * Returns ['token' => string, 'created' => bool], or null on failure.
 */
function pulseboard_share_token(string $sheet_id, string $tab, string $tab_safe = ''): ?array {
    if ($sheet_id === '' || $tab === '') return null;
    if ($tab_safe === '') $tab_safe = pulseboard_tab_safe($tab);

    $share_dir = __DIR__ . '/../shares/pulse-group-readonly';
    if (!is_dir($share_dir) && !@mkdir($share_dir, 0755, true)) return null;

    // Reuse the existing link for this sheet+tab
    foreach (glob($share_dir . '/*.json') ?: [] as $file) {
        $data = json_decode((string) @file_get_contents($file), true);
        if (($data['sheet_id'] ?? '') === $sheet_id && ($data['tab'] ?? '') === $tab) {
            return ['token' => basename($file, '.json'), 'created' => false];
        }
    }

    $token   = bin2hex(random_bytes(16));
    $payload = json_encode([
        'sheet_id' => $sheet_id,
        'tab'      => $tab,
        'tab_safe' => $tab_safe,
        'created'  => date('c'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if (@file_put_contents($share_dir . '/' . $token . '.json', $payload) === false) return null;
    return ['token' => $token, 'created' => true];
}

/** Full URL for a share token, e.g. https://zapsheets.com/app/share/{token} */
function pulseboard_share_url(string $token): string {
    $base   = rtrim($_ENV['BASE_PATH'] ?? '', '/');
    $host   = $_SERVER['HTTP_HOST'] ?? '';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
              || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
    return ($host ? $scheme . '://' . $host : '') . $base . '/share/' . $token;
}
