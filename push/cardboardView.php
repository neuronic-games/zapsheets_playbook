<?php
/**
 * cardboardView.php — view-only share links for CardBoard that don't show the sheet's ID.
 *
 * The link is {base}v-{token}/cardboard. The page and the server speak of the sheet as "v-{token}"; the server
 * looks the real sheet up here (shares/cardboard-view/{token}.json) and only reads — nothing can be changed
 * through a view link, not even by the owner.
 *
 *   cbViewId($id)        → true for "v-{token}"
 *   cbViewSheet($id)     → the real sheet ID for "v-{token}" ('' when unknown or stopped); other ids come back as they are
 *   cbViewToken($sheet)  → the sheet's current token, or ''
 *   cbViewCreate($sheet) → a token (the existing one if there is one)
 *   cbViewStop($sheet)   → the link stops working
 */

function cbViewDir(): string {
    $d = dirname(__DIR__) . '/shares/cardboard-view';
    if (!is_dir($d)) {
        @mkdir($d, 0755, true);
        @file_put_contents("$d/.htaccess", "Options -Indexes\nDeny from all\n");
    }
    return $d;
}
function cbViewId(string $id): bool { return (bool) preg_match('/^v-[a-f0-9]{24}$/', $id); }
function cbViewSheet(string $id): string {
    if (!cbViewId($id)) return $id;
    $m = json_decode((string) @file_get_contents(cbViewDir() . '/' . substr($id, 2) . '.json'), true);
    $s = is_array($m) ? (string) ($m['sheet_id'] ?? '') : '';
    return preg_match('/^[A-Za-z0-9_\-]{20,128}$/', $s) ? $s : '';
}
function cbViewToken(string $sheet): string {
    if (!preg_match('/^[A-Za-z0-9_\-]{20,128}$/', $sheet)) return '';
    $t = trim((string) @file_get_contents(dirname(__DIR__) . '/sheets/' . $sheet . '/_cardboard_view.txt'));
    return preg_match('/^[a-f0-9]{24}$/', $t) && cbViewSheet('v-' . $t) === $sheet ? $t : '';
}
function cbViewCreate(string $sheet): string {
    $t = cbViewToken($sheet);
    if ($t !== '') return $t;
    $t = bin2hex(random_bytes(12));
    @file_put_contents(cbViewDir() . "/$t.json", json_encode(['sheet_id' => $sheet, 'created' => gmdate('c')]), LOCK_EX);
    $d = dirname(__DIR__) . '/sheets/' . $sheet;
    if (!is_dir($d)) @mkdir($d, 0755, true);
    @file_put_contents("$d/_cardboard_view.txt", $t, LOCK_EX);
    return $t;
}
function cbViewStop(string $sheet): void {
    $t = cbViewToken($sheet);
    if ($t !== '') @unlink(cbViewDir() . "/$t.json");
    @unlink(dirname(__DIR__) . '/sheets/' . $sheet . '/_cardboard_view.txt');
}
