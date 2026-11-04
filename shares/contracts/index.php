<?php
/**
 * shares/contracts/index.php — serve a contract PDF via share token.
 *
 * URL (routed by .htaccess): /shares/contracts/{hash}
 * The token is passed as ?token={hash}
 *
 * Reads shares/contracts/{hash}.json to find the private PDF path,
 * then streams the file. Sheet ID never appears in the public URL.
 */
error_reporting(0);

$token = preg_replace('/[^A-Za-z0-9]/', '', $_GET['token'] ?? '');
if (!$token) { http_response_code(404); echo 'Not found'; exit; }

$base     = dirname(dirname(__DIR__));   // app root (two levels up from shares/contracts/)
$jsonFile = $base . '/shares/contracts/' . $token . '.json';
if (!file_exists($jsonFile)) { http_response_code(404); echo 'Not found'; exit; }

$meta    = json_decode(file_get_contents($jsonFile), true) ?: [];
$relPath = $meta['file'] ?? '';
if (!$relPath) { http_response_code(500); echo 'Invalid share'; exit; }

$pdfFile = $base . '/' . $relPath;
if (!file_exists($pdfFile)) { http_response_code(404); echo 'File not found'; exit; }

$docType = preg_replace('/[^A-Za-z0-9_\-]/', '', $meta['doc_type'] ?? 'Document');
$docNum  = preg_replace('/[^A-Za-z0-9_\-]/', '', $meta['doc_num']  ?? '');
$fname   = $docType . ($docNum ? "-$docNum" : '') . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $fname . '"');
// The PDF is regenerated in place when a contract is edited, so browsers must
// check for a newer version every time (cheap 304 when unchanged).
$mtime = filemtime($pdfFile);
$etag  = '"' . md5($token . '|' . $mtime . '|' . filesize($pdfFile)) . '"';
header('Cache-Control: no-cache, private');
header('ETag: ' . $etag);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) { http_response_code(304); exit; }
header('Content-Length: ' . filesize($pdfFile));
readfile($pdfFile);
?>
