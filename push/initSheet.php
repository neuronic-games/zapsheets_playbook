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

    // Copy every file from /source into the sheet folder
    $copied = [];
    $failed = [];
    $files  = scandir($sourceDir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $src  = $sourceDir . '/' . $file;
        $dest = $sheetDir  . '/' . $file;
        if (is_file($src)) {
            if (copy($src, $dest)) {
                $copied[] = $file;
            } else {
                $failed[] = $file;
            }
        }
    }

    echo json_encode([
        'status'  => 'ok',
        'copied'  => $copied,
        'failed'  => $failed,
        'dir'     => $sheetDir
    ]);
?>
