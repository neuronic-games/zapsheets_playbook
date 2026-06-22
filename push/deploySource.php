<?php
// Copies source/dashboard/index.php → sheets/*/dashboard/index.php

$root    = dirname(__DIR__);
$srcFile = $root . '/source/dashboard/index.php';

if (!file_exists($srcFile)) {
    echo 'ERROR: source/dashboard/index.php not found';
    exit;
}

$dirs = glob($root . '/sheets/*/dashboard', GLOB_ONLYDIR);
if (empty($dirs)) {
    echo 'SKIP: no sheet dashboard directories found';
    exit;
}

$count = 0;
foreach ($dirs as $dir) {
    $dest = $dir . '/index.php';
    if (!copy($srcFile, $dest)) {
        echo 'ERROR: could not write to ' . basename(dirname($dir)) . '/dashboard/index.php';
        exit;
    }
    $count++;
}

echo 'source deployed to ' . $count . ' sheet' . ($count === 1 ? '' : 's');
