<?php 
    $spreadsheetId = $_POST['id'];
    $saveType = $_POST['type'];
    
    if($saveType == 'index') {
        $dir = '../sheets/' . $spreadsheetId . '/index.html';
        if (!file_exists('../sheets/' . $spreadsheetId)) {
            mkdir('../sheets/' . $spreadsheetId, 0777, true);
        }
        // the base name of file 
        $file_name = '../source/index.html';
    } else if($saveType == 'sw') {
        $dir = '../sheets/' . $spreadsheetId . '/sw_playbookHomeApp.js';
        if (!file_exists('../sheets/' . $spreadsheetId)) {
            mkdir('../sheets/' . $spreadsheetId, 0777, true);
        }
        // the base name of file 
        $file_name = '../source/sw_playbookHomeApp.js';
    }
    copy($file_name, $dir);

    echo "loaded"
?>