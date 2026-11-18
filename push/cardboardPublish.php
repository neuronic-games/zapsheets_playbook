<?php
/**
 * cardboardPublish.php — host exported card sheets at links that stay the same.
 *
 * CardBoard draws the sheets in the browser and uploads them here. Each sheet
 * and target gets its own folder, and every publish overwrites the same file
 * names, so a link pasted into Tabletop Simulator keeps working and shows the
 * newest cards.
 *
 *   images/{id}/cardboard/{tab-slug}-{target}/faces-1.png, back.png, …, manifest.json
 *
 * POST params: id, action, tab, target, and per action:
 *   upload — name (e.g. faces-1.jpg), file (PNG or JPEG, matching the name)
 *   finish — manifest (JSON: files, grid, settings); files not listed are removed
 *   list   — (tab only) → {target: manifest} for this sheet tab
 */
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');
require dirname(__DIR__) . '/dotEnv.php';

function done($d) { echo json_encode($d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); exit; }

$sheetId = trim($_POST['id'] ?? '');
if (!preg_match('/^[A-Za-z0-9_\-]{20,}$/', $sheetId) || !is_dir(dirname(__DIR__) . '/sheets/' . $sheetId)) done(['error' => 'Unknown sheet']);
$tab = trim($_POST['tab'] ?? '');
if ($tab === '' || mb_strlen($tab) > 100) done(['error' => 'Missing sheet tab']);
$targets = ['tts', 'screentop', 'images'];

// A readable, collision-free folder name for the tab
$slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $tab) ?: '')), '-');
$slug = ($slug !== '' ? substr($slug, 0, 40) . '-' : '') . substr(md5($tab), 0, 6);
$root = dirname(__DIR__) . '/images/' . $sheetId . '/cardboard';

$bp     = rtrim($_ENV['BASE_PATH'] ?? '', '/');
$scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base   = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $bp . '/images/' . $sheetId . '/cardboard/';

$action = $_POST['action'] ?? '';

if ($action === 'list') {
    $out = [];
    foreach ($targets as $t) {
        $m = json_decode((string) @file_get_contents("$root/$slug-$t/manifest.json"), true);
        if (is_array($m)) $out[$t] = $m;
    }
    done(['ok' => true, 'published' => (object) $out]);
}

$target = $_POST['target'] ?? '';
if (!in_array($target, $targets, true)) done(['error' => 'Unknown target']);
$dir = "$root/$slug-$target";
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
    done(['ok' => true, 'url' => $base . "$slug-$target/$name"]);
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
    foreach ($m['files'] as $f) $files[] = array_merge($f, ['url' => $base . "$slug-$target/" . $f['name']]);
    $man = [
        'target'   => $target,
        'tab'      => $tab,
        'files'    => $files,
        'grid'     => is_array($m['grid'] ?? null) ? $m['grid'] : null,
        'settings' => is_array($m['settings'] ?? null) ? $m['settings'] : null,
        'count'    => (int) ($m['count'] ?? 0),
        'updated'  => gmdate('c'),
    ];
    file_put_contents("$dir/manifest.json", json_encode($man, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    done(['ok' => true, 'published' => $man]);
}

if ($action === 'remove') {
    foreach (glob("$dir/*") ?: [] as $p) @unlink($p);
    foreach (glob("$dir/.*") ?: [] as $p) if (is_file($p)) @unlink($p);
    @rmdir($dir);
    done(['ok' => true]);
}

done(['error' => 'Unknown action']);
