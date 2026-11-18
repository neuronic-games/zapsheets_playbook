<?php
/**
 * shares/cardboard/index.php — serve a published CardBoard sheet via share token.
 *
 * URL (routed by .htaccess): /shares/cardboard/{token}/{file}
 * Reads shares/cardboard/{token}.json to find the sheet, then streams the file
 * from sheets/{id}/files/cardboard/{token}/. The sheet ID never appears in the URL.
 * The files are replaced when the cards change, so clients re-check every time
 * (a cheap 304 when nothing changed).
 */
error_reporting(0);

$token = preg_replace('/[^a-f0-9]/', '', $_GET['token'] ?? '');
$file  = $_GET['file'] ?? '';
if (strlen($token) !== 24 || !preg_match('/^(?:(?:faces|sheet|backs)-\d{1,3}|back|card-\d{3,4}(?:-back)?)\.(png|jpg)$/', $file, $fm)) {
    http_response_code(404); echo 'Not found'; exit;
}

$app  = dirname(dirname(__DIR__));
$meta = json_decode((string) @file_get_contents("$app/shares/cardboard/$token.json"), true) ?: [];
$sid  = $meta['sheet_id'] ?? '';
if (!preg_match('/^[A-Za-z0-9_\-]{20,}$/', $sid)) { http_response_code(404); echo 'Not found'; exit; }

$path = "$app/sheets/$sid/files/cardboard/$token/$file";
if (!is_file($path)) { http_response_code(404); echo 'Not found'; exit; }

$mtime = filemtime($path);
$etag  = '"' . md5($token . '|' . $file . '|' . $mtime . '|' . filesize($path)) . '"';
header('Content-Type: ' . ($fm[1] === 'jpg' ? 'image/jpeg' : 'image/png'));
header('Cache-Control: no-cache');
header('ETag: ' . $etag);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
header('Access-Control-Allow-Origin: *');   // game sites may load the pictures from their own pages
header('X-Content-Type-Options: nosniff');
if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) { http_response_code(304); exit; }
header('Content-Length: ' . filesize($path));
readfile($path);
