<?php
/**
 * cardboardPublish.php — host exported card sheets at links that stay the same.
 *
 * Same share system as contracts: files are kept privately with the sheet's
 * data, and the public link carries only a share token — never the sheet ID.
 *
 *   private:  sheets/{id}/files/cardboard/{token}/faces-1.jpg, back.jpg, …, manifest.json
 *   share:    shares/cardboard/{token}.json  → {sheet_id, tab, target}
 *   link:     {base}/shares/cardboard/{token}/faces-1.jpg   (served by shares/cardboard/index.php)
 *
 * Every publish overwrites the same file names, so a link pasted into Tabletop
 * Simulator keeps working and shows the newest cards.
 *
 * POST params: id, action, tab, target, and per action:
 *   upload — name (e.g. faces-1.jpg), file (PNG or JPEG, matching the name)
 *   finish — manifest (JSON: files, grid, settings); files not listed are removed
 *   list   — (tab only) → {target: manifest} for this sheet tab
 *   remove — delete the files and the share
 */
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');
require dirname(__DIR__) . '/dotEnv.php';

function done($d) { echo json_encode($d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); exit; }

$app     = dirname(__DIR__);
$sheetId = trim($_POST['id'] ?? '');
if (!preg_match('/^[A-Za-z0-9_\-]{20,}$/', $sheetId) || !is_dir("$app/sheets/$sheetId")) done(['error' => 'Unknown sheet']);
$tab = trim($_POST['tab'] ?? '');
if ($tab === '' || mb_strlen($tab) > 100) done(['error' => 'Missing sheet tab']);
$targets = ['tts', 'screentop', 'images'];

$bp     = rtrim($_ENV['BASE_PATH'] ?? '', '/');
$scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base   = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $bp . '/shares/cardboard/';

// Stable token per sheet + tab + target: the same deck always gets the same links
function cbToken($sheetId, $tab, $target) { return substr(md5($sheetId . '|cardboard|' . $tab . '|' . $target), 0, 24); }
function cbDir($app, $sheetId, $token) { return "$app/sheets/$sheetId/files/cardboard/$token"; }
function cbShare($app, $token) { return "$app/shares/cardboard/$token.json"; }
function cbUrls(array $m, $base, $token) {
    foreach ($m['files'] as &$f) $f['url'] = $base . $token . '/' . $f['name'];
    return $m;
}

// First version kept files at images/{id}/cardboard/{slug}-{target}/ — move them into the share system
function cbMigrate($app, $sheetId, $tab, $target, $token, $base) {
    $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $tab) ?: '')), '-');
    $slug = ($slug !== '' ? substr($slug, 0, 40) . '-' : '') . substr(md5($tab), 0, 6);
    $old  = "$app/images/$sheetId/cardboard/$slug-$target";
    if (!is_file("$old/manifest.json")) return;
    $dir = cbDir($app, $sheetId, $token);
    if (!is_file("$dir/manifest.json")) {
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        foreach (glob("$old/*.{png,jpg}", GLOB_BRACE) ?: [] as $p) rename($p, "$dir/" . basename($p));
        $m = json_decode((string) file_get_contents("$old/manifest.json"), true);
        if (is_array($m) && isset($m['files'])) {
            file_put_contents("$dir/manifest.json", json_encode(cbUrls($m, $base, $token), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            cbWriteShare($app, $token, $sheetId, $tab, $target);
        }
    }
    foreach (glob("$old/*") ?: [] as $p) @unlink($p);
    @rmdir($old); @rmdir(dirname($old));
}
function cbWriteShare($app, $token, $sheetId, $tab, $target) {
    $sd = "$app/shares/cardboard";
    if (!is_dir($sd)) {
        mkdir($sd, 0755, true);
        file_put_contents("$sd/.htaccess", "Options -Indexes\n<FilesMatch \"\\.json$\">\n  Deny from all\n</FilesMatch>\n");
    }
    file_put_contents(cbShare($app, $token), json_encode(['sheet_id' => $sheetId, 'tab' => $tab, 'target' => $target, 'app' => 'cardboard']));
}

$action = $_POST['action'] ?? '';

if ($action === 'list') {
    $out = [];
    foreach ($targets as $t) {
        $tok = cbToken($sheetId, $tab, $t);
        cbMigrate($app, $sheetId, $tab, $t, $tok, $base);
        $m = json_decode((string) @file_get_contents(cbDir($app, $sheetId, $tok) . '/manifest.json'), true);
        if (is_array($m) && is_file(cbShare($app, $tok))) $out[$t] = cbUrls($m, $base, $tok);
    }
    done(['ok' => true, 'published' => (object) $out]);
}

// Hosting sheets uses server space: CardBoard Pro (removing stays open to everyone)
if (in_array($action, ['upload', 'finish'], true)) {
    require_once __DIR__ . '/stripeCommon.php';
    if (!stripeSheetPlan($sheetId, 'cardboard')['is_pro']) done(['error' => 'Links are part of CardBoard Pro. Upgrade to Pro to host your card sheets.', 'need_pro' => true]);
}

$target = $_POST['target'] ?? '';
if (!in_array($target, $targets, true)) done(['error' => 'Unknown target']);
$token = cbToken($sheetId, $tab, $target);
$dir   = cbDir($app, $sheetId, $token);
$NAME = '/^(?:(?:faces|sheet|backs)-\d{1,3}|back|card-\d{3,4}(?:-back)?)\.(png|jpg)$/';

if ($action === 'upload') {
    $name = $_POST['name'] ?? '';
    if (!preg_match($NAME, $name)) done(['error' => 'Bad file name']);
    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) done(['error' => 'Upload error ' . ($_FILES['file']['error'] ?? -1)]);
    if ($_FILES['file']['size'] > 40 * 1024 * 1024) done(['error' => 'Sheet is over 40 MB — choose a smaller size']);
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($_FILES['file']['tmp_name']);
    if ($mime !== (substr($name, -4) === '.jpg' ? 'image/jpeg' : 'image/png')) done(['error' => 'Not a ' . strtoupper(substr($name, -3)) . ' picture']);
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) done(['error' => 'Could not create the folder']);
    // Write beside the old file, then swap, so a download never sees half a file
    $tmp = "$dir/.$name." . bin2hex(random_bytes(4));
    if (!move_uploaded_file($_FILES['file']['tmp_name'], $tmp) || !rename($tmp, "$dir/$name")) { @unlink($tmp); done(['error' => 'Could not save']); }
    done(['ok' => true]);
}

if ($action === 'finish') {
    $m = json_decode($_POST['manifest'] ?? '', true);
    if (!is_array($m) || !isset($m['files']) || !is_array($m['files'])) done(['error' => 'Bad manifest']);
    $keep = [];
    foreach ($m['files'] as $f) {
        $n = is_array($f) ? ($f['name'] ?? '') : '';
        if (!preg_match($NAME, $n) || !is_file("$dir/$n")) done(['error' => 'Missing file ' . $n]);
        $keep[$n] = true;
    }
    foreach (glob("$dir/*.{png,jpg}", GLOB_BRACE) ?: [] as $p) if (empty($keep[basename($p)])) @unlink($p);   // e.g. a sheet no longer needed
    $files = [];
    foreach ($m['files'] as $f) $files[] = ['name' => $f['name'], 'kind' => (string) ($f['kind'] ?? ''), 'sheet' => (int) ($f['sheet'] ?? 0), 'cards' => (int) ($f['cards'] ?? 0)];
    $man = [
        'target'   => $target,
            'files'    => $files,
        'grid'     => is_array($m['grid'] ?? null) ? $m['grid'] : null,
        'settings' => is_array($m['settings'] ?? null) ? $m['settings'] : null,
        'count'    => (int) ($m['count'] ?? 0),
        'updated'  => gmdate('c'),
    ];
    file_put_contents("$dir/manifest.json", json_encode($man, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    cbWriteShare($app, $token, $sheetId, $tab, $target);
    done(['ok' => true, 'published' => cbUrls($man, $base, $token)]);
}

if ($action === 'remove') {
    foreach (glob("$dir/*") ?: [] as $p) @unlink($p);
    foreach (glob("$dir/.*") ?: [] as $p) if (is_file($p)) @unlink($p);
    @rmdir($dir);
    @unlink(cbShare($app, $token));   // the link stops working
    done(['ok' => true]);
}

done(['error' => 'Unknown action']);
