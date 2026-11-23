<?php
/**
 * uploadImage.php — upload an image file for a BillBoard game page.
 *
 * POST params:
 *   id   — Google Spreadsheet ID (used to namespace the folder)
 *   file — the uploaded image (multipart/form-data)
 *
 * Returns: {"ok": true, "url": "https://…/images/{id}/filename.jpg"}
 *          {"error": "reason"}
 */
require_once __DIR__ . '/gbGate.php'; gbGate(['billboard'], false);   // GameBoard account: signed in, the sheet's owner, the app unlocked
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require dirname(__DIR__) . '/dotEnv.php';

$sheetId = trim($_POST['id'] ?? '');
if (!$sheetId || !preg_match('/^[A-Za-z0-9_\-]+$/', $sheetId)) {
    echo json_encode(['error' => 'Missing or invalid sheet ID']);
    exit;
}

// CardBoard: uploads need the free week, a subscription or an unlocked sheet
if (($_POST['app'] ?? '') === 'cardboard') {
    require_once __DIR__ . '/gameboardAuth.php';
    cbRequireAccess($sheetId);
}

if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $code = $_FILES['file']['error'] ?? -1;
    echo json_encode(['error' => 'Upload error code ' . $code]);
    exit;
}

$file = $_FILES['file'];

// Validate MIME type by content (not just extension)
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($mime, $allowed)) {
    echo json_encode(['error' => 'Unsupported image type: ' . $mime]);
    exit;
}

$maxBytes = 10 * 1024 * 1024; // 10 MB
if ($file['size'] > $maxBytes) {
    echo json_encode(['error' => 'File too large (max 10 MB)']);
    exit;
}

// Save with a random name (always .webp after conversion)
$tmpBase  = bin2hex(random_bytes(10));
$extMap   = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
$origExt  = $extMap[$mime] ?? 'jpg';
$tmpName  = $tmpBase . '.' . $origExt;   // temp name for move_uploaded_file
$filename = $tmpBase . '.webp';           // final WebP name

$dir = dirname(__DIR__) . '/images/' . $sheetId . '/';
if (!is_dir($dir)) {
    if (!mkdir($dir, 0755, true)) {
        echo json_encode(['error' => 'Could not create upload directory']);
        exit;
    }
}

$tmpPath  = $dir . $tmpName;
$destPath = $dir . $filename;

if (!move_uploaded_file($file['tmp_name'], $tmpPath)) {
    echo json_encode(['error' => 'Could not save file']);
    exit;
}

// ── Convert to WebP and resize to ≤1200 px on longest side ──────────────────
$converted = false;
if (function_exists('imagewebp')) {
    $loaders = [
        'image/jpeg' => 'imagecreatefromjpeg',
        'image/png'  => 'imagecreatefrompng',
        'image/gif'  => 'imagecreatefromgif',
        'image/webp' => 'imagecreatefromwebp',
    ];
    $loader = $loaders[$mime] ?? null;
    if ($loader && function_exists($loader)) {
        $src = @$loader($tmpPath);
        if ($src) {
            // WebP can't be written from a palette image (8-bit PNG/GIF): GD writes an empty file. Make it full colour, keeping transparency.
            if (!imageistruecolor($src)) { imagepalettetotruecolor($src); imagealphablending($src, false); imagesavealpha($src, true); }
            $ow = imagesx($src);
            $oh = imagesy($src);
            $maxDim = 1200;
            if ($ow > $maxDim || $oh > $maxDim) {
                $scale = $maxDim / max($ow, $oh);
                $nw = (int)round($ow * $scale);
                $nh = (int)round($oh * $scale);
                $dst = imagecreatetruecolor($nw, $nh);
                // Preserve transparency for PNG/GIF/WebP
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $ow, $oh);
                imagedestroy($src);
                $src = $dst;
            }
            if (@imagewebp($src, $destPath, 85) && @filesize($destPath) > 0) {
                $converted = true;
            } else {
                @unlink($destPath);   // an empty or broken WebP: keep the original instead
            }
            clearstatcache();
            imagedestroy($src);
        }
    }
}

if ($converted) {
    if ($tmpPath !== $destPath) @unlink($tmpPath); // remove original only when paths differ
} else {
    // GD unavailable or conversion failed — serve the original as-is
    rename($tmpPath, $destPath);
    // Keep original extension in the filename for correct serving
    $filename = $tmpBase . '.' . $origExt;
    $destPath = $dir . $filename;
}

// Build the public URL
$bp     = rtrim($_ENV['BASE_PATH'] ?? '', '/');
$scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$url    = rtrim(gbLinkBase(), '/') . '/images/' . $sheetId . '/' . $filename;

echo json_encode(['ok' => true, 'url' => $url, 'filename' => $filename]);
