<?php
    // Getting spreadsheet Id from script app
    // header('Content-Type: text/html; charset=ISO-8859-1');
    
    $spreadsheetId = $_POST['id'];
    $sheet = $_POST['sheetname'];
    $dateStr = $_POST['date_string'];


    if($dateStr != '') {
        //echo "Changing version";
        
        //$dateStr = $_POST['date_string'];
        // Get current stored version id to respective spreadsheet named folder;
        $jsonFile = "../sheets/" . $spreadsheetId . "/version.json";
        // Check if the folder is not exists then create one and 
        // create the version.json file there with default value (0.0)
        if (!file_exists($jsonFile)) {
            mkdir("../sheets/" . $spreadsheetId, 0777, true);
        }
        // Call python to get the current spreadsheet version
        // And update version.json file place on the server under the 
        // spreadsheet id folder name
        // For local testing
        $sheetName = 'Settings';
        $python_file_name = "greadPush.py "; 
        $python_execution = "python ".$python_file_name .$spreadsheetId .'sheetname' .$sheetName .'dateString' .$dateStr; 
        $versionNum = shell_exec($python_execution);
        $versionNum = str_replace("\r\n","",$versionNum);
        /////////////////////////////////////////////////////////////
        // For Server
        /* $sheetName = 'Settings';
        $py_command = escapeshellcmd('source /home/zapsheets/virtualenv/public_html/steps/3.11/bin/python3 greadPush.py ' .$spreadsheetId .'sheetname' .$sheetName .'dateString' .$dateStr); 
        $versionNum = shell_exec($py_command);
        $versionNum = str_replace("\r\n","", $versionNum); */
        /////////////////////////////////////////////////////////////
        // Return Message to console
        echo $versionNum;
    } else if($sheet == 'Server') {
        $updatedVersion = $_POST['nVersion'];
        $jsonFile = "../sheets/" . $spreadsheetId . "/version.json";
        $data = array('version' => ($updatedVersion));
        $json_object = json_encode($data);
        $output = file_put_contents($jsonFile, $json_object); 
        echo 'Sheet version updated to server';
    } else {
        //echo "Getting Settings values";
        // For Local
        $sheetName = $sheet;
        $python_file_name = "gread.py "; 
        $python_execution = "python ".$python_file_name .$spreadsheetId .'sheetname' .$sheetName; 
        $settingsData = shell_exec($python_execution);
        /////////////////////////////////////////////////////////////
        // For Server
        /* $sheetName = $sheet;
        $py_command = escapeshellcmd('source /home/zapsheets/virtualenv/public_html/steps/3.11/bin/python3 gread.py ' .$spreadsheetId .'sheetname' .$sheetName); 
        $settingsData = shell_exec($py_command); */
        /////////////////////////////////////////////////////////////
        echo 'Publishing '. $sheetName .' data to server';
    } 
    
?>