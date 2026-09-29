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

$token = preg_replace('/[^a-f0-9]/', '', $_GET['token'] ?? '');
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
header('Content-Length: ' . filesize($pdfFile));
header('Cache-Control: private, max-age=3600');
readfile($pdfFile);
?>
