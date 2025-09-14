<?php
    // Getting spreadsheet Id from script app
    $spreadsheetId = $_POST['id'];
    $statusValue = $_POST['value'] != '' ? $_POST['value'] : 'false';

    if($spreadsheetId != '') {
        // Get current stored version id to respective spreadsheet named folder;
        $jsonFile = "../sheets/" . $spreadsheetId . "/pushstatus.json";

        // Check if the folder is not exists then create one and 
        // create the version.json file there with default value (0.0)
        /* if (!file_exists($jsonFile)) {
            mkdir("../sheets/" . $spreadsheetId, 0777, true);
        } */

        // New version generated and saved
        $pushstatus = ''. $statusValue . '';
        /* $jsonVersionNum = ''. ((int) $updatedVers ) . ''; */
        $data = array('push' => ($pushstatus));
        $json_object = json_encode($data);
        $output = file_put_contents($jsonFile, $json_object); 
        echo 'Push status saved.';
    }
?>