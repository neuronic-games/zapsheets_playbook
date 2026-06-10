<?php
    // Suppress PHP notices/warnings so they don't corrupt JSON responses
    error_reporting(0);
    ini_set('display_errors', '0');

    $spreadsheetId = $_POST['id'];

    if (empty($spreadsheetId)) {
        echo json_encode(['status' => 'error', 'message' => 'Missing sheet id.']);
        exit;
    }

    $sheetDir  = '../sheets/' . $spreadsheetId;
    $sourceDir = '../source';

    // Create the sheet folder if it doesn't exist
    if (!file_exists($sheetDir)) {
        mkdir($sheetDir, 0777, true);
    }

    // Create the cacheImages sub-folder if it doesn't exist
    $cacheDir = $sheetDir . '/cacheImages';
    if (!file_exists($cacheDir)) {
        mkdir($cacheDir, 0777, true);
    }

    // Recursively copy /source into the sheet folder (files + subdirectories)
    $copied = [];
    $failed = [];

    function copySourceDir($src, $dest, &$copied, &$failed) {
        if (!file_exists($dest)) {
            mkdir($dest, 0777, true);
        }
        foreach (scandir($src) as $item) {
            if ($item === '.' || $item === '..') continue;
            $srcPath  = $src  . '/' . $item;
            $destPath = $dest . '/' . $item;
            if (is_dir($srcPath)) {
                copySourceDir($srcPath, $destPath, $copied, $failed);
            } else {
                if (copy($srcPath, $destPath)) {
                    $copied[] = $destPath;
                } else {
                    $failed[] = $destPath;
                }
            }
        }
    }

    copySourceDir($sourceDir, $sheetDir, $copied, $failed);

    echo json_encode([
        'status'  => 'ok',
        'copied'  => $copied,
        'failed'  => $failed,
        'dir'     => $sheetDir
    ]);
?>
