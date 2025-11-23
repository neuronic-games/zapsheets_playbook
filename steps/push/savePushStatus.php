<?php
    // Getting spreadsheet Id from script app
    $spreadsheetId = $_POST['id'];
    $statusValue = $_POST['value'] != '' ? $_POST['value'] : 'false';

    if($spreadsheetId != '') {
        // Get current stored version id to respective spreadsheet named folder;
        $jsonFile = "../sheets/" . $spreadsheetId . "/pushstatus.json";
        // New version generated and saved
        $pushstatus = ''. $statusValue . '';
        $data = array('push' => ($pushstatus));
        $json_object = json_encode($data);
        $output = file_put_contents($jsonFile, $json_object); 
        echo 'Push status saved.';
    }
?>