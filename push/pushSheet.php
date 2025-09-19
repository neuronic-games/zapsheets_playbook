<?php
    // Getting spreadsheet Id from script app
    // header('Content-Type: text/html; charset=ISO-8859-1');
    
    $spreadsheetId = $_POST['id'];
    $sheet = $_POST['sheetname'];

    //$pythonPath = '/usr/bin/python3';

    if($sheet == '') {

        $dateStr = $_POST['date_string'];

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
        //echo $versionNum;

        /////////////////////////////////////////////////////////////
        // For Server
        /* $sheetName = 'Settings';
        $py_command = escapeshellcmd('source /home/zapsheets/virtualenv/public_html/steps/3.11/bin/python3 greadPush.py ' .$spreadsheetId .'sheetname' .$sheetName .'dateString' .$dateStr); 
        $versionNum = shell_exec($py_command);
        $versionNum = str_replace("\r\n","", $versionNum); */
        /////////////////////////////////////////////////////////////

        // Now update version so that the running app looks for updated version
        // And reload the contents from spreadsheet
        // New version generated and saved
        /* $jsonVersionNum = ''. ((int) $versionNum ) . ''; */
        /* $jsonVersionNum = ''. ((int) $updatedVers ) . ''; */

        //////////////////////////////////////////////////////////////////////
        // Moved to last block
        /* $data = array('version' => ($jsonVersionNum));
        $json_object = json_encode($data);
        $output = file_put_contents($jsonFile, $json_object);  */
        ///////////////////////////////////////////////////////////////////////
        // Return Message to console
        echo $versionNum;
    } else if($sheet == 'Settings') {
        //$jsonFile = "../sheets/" . $spreadsheetId . "/settings.json";

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
        
        //$json_object = json_encode($settingsData);
        //$output = file_put_contents($jsonFile, $settingsData); 
        echo 'Publishing settings data to server';
        //echo $settingsData;
    } else if ($sheet == 'Install') {
        $jsonFile = "../sheets/" . $spreadsheetId . "/install.json";

        // For Local
        $sheetName = $sheet;
        $python_file_name = "gread.py "; 
        $python_execution = "python ".$python_file_name .$spreadsheetId .'sheetname' .$sheetName; 
        $directoryData = shell_exec($python_execution);

        /////////////////////////////////////////////////////////////
        // For Server
        /* $sheetName = $sheet;
        $py_command = escapeshellcmd('source /home/zapsheets/virtualenv/public_html/steps/3.11/bin/python3 gread.py ' .$spreadsheetId .'sheetname' .$sheetName); 
        $directoryData = shell_exec($py_command); */
        /////////////////////////////////////////////////////////////

        //$json_object = json_encode($temp8);
        //$output = file_put_contents($jsonFile, $directoryData); 
        echo 'Publishing Install data to server';
    } else if ($sheet == 'Events') {
        $jsonFile = "../sheets/" . $spreadsheetId . "/events.json";

        // For Local
        $sheetName = $sheet;
        $python_file_name = "gread.py "; 
        $python_execution = "python ".$python_file_name .$spreadsheetId .'sheetname' .$sheetName; 
        $eventsData = shell_exec($python_execution);

        /////////////////////////////////////////////////////////////
        // For Server
        /* $sheetName = $sheet;
        $py_command = escapeshellcmd('source /home/zapsheets/virtualenv/public_html/steps/3.11/bin/python3 gread.py ' .$spreadsheetId .'sheetname' .$sheetName); 
        $eventsData = shell_exec($py_command); */
        /////////////////////////////////////////////////////////////
       
        //$json_object = json_encode($settingsData);
        $output = file_put_contents($jsonFile, $eventsData); 
        echo 'Publishing events data to server';
    } else if ($sheet == 'Kiosks') {

        $jsonFile = "../sheets/" . $spreadsheetId . "/kiosks.json";

        // For Local
        $sheetName = $sheet;
        $python_file_name = "gread.py "; 
        $python_execution = "python ".$python_file_name .$spreadsheetId .'sheetname' .$sheetName; 
        $kiosksData = shell_exec($python_execution);

        /////////////////////////////////////////////////////////////
        // For Server
        /* $sheetName = $sheet;
        $py_command = escapeshellcmd('source /home/zapsheets/virtualenv/public_html/steps/3.11/bin/python3 gread.py ' .$spreadsheetId .'sheetname' .$sheetName); 
        $kiosksData = shell_exec($py_command); */
        /////////////////////////////////////////////////////////////
       
        //$json_object = json_encode($settingsData);
        $output = file_put_contents($jsonFile, $kiosksData); 
        echo 'Publishing kiosk data to server';
    } else if($sheet == 'Server') {
        $updatedVersion = $_POST['nVersion'];
        $jsonFile = "../sheets/" . $spreadsheetId . "/version.json";
        $data = array('version' => ($updatedVersion));
        $json_object = json_encode($data);
        $output = file_put_contents($jsonFile, $json_object); 
        echo 'Sheet version updated to server';
    } else if($sheet != '' && $sheet != 'checkSheet') {
        //$jsonFile = "../sheets/" . $spreadsheetId . "/steps_" . strtolower($sheet) . '.json' ;

        // For Local
        $sheetName = $sheet;
        $python_file_name = "gread.py "; 
        $python_execution = "python ".$python_file_name .$spreadsheetId .'sheetname' .$sheetName; 
        $stepsLang = shell_exec($python_execution);

        /////////////////////////////////////////////////////////////
        // For Server
        /* $sheetName = $sheet;
        $py_command = escapeshellcmd('source /home/zapsheets/virtualenv/public_html/steps/3.11/bin/python3 gread.py ' .$spreadsheetId .'sheetname' .$sheetName); 
        $stepsLang = shell_exec($py_command); */
        /////////////////////////////////////////////////////////////
       
        //$json_object = json_encode($settingsData);
        //$output = file_put_contents($jsonFile, $stepsLang); 
        echo 'Publishing ', $sheet . ' data to server';
    } else if($sheet == 'checkSheet') {
       // For Local
        $tabName = $_POST['tab_name'];
        $sheetName = $tabName;


        // Local
        $python_file_name = "checkSheetStatus.py "; 
        $python_execution = "python ".$python_file_name .$spreadsheetId .'sheetname' .$sheetName; 
        $isSheet = shell_exec($python_execution); 

        
        /* $py_command = escapeshellcmd('source /home/zapsheets/virtualenv/public_html/steps/3.11/bin/python3 checkSheetStatus.py ' .$spreadsheetId .'sheetname' .$sheetName); 
        $isSheet = shell_exec($py_command); */

        echo $isSheet;
    }
    
?>