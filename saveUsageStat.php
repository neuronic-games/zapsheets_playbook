<?php
    // Getting spreadsheet Id from script app
    $spreadsheetId = $_POST['id'];
    $osType = $_POST['OS'];
    $browserType = $_POST['Browser'];
    $deviceType = $_POST['Device'];
    $deviceId = $_POST['DeviceId'];


    if($spreadsheetId != '') {
        // Get current stored version id to respective spreadsheet named folder;
        $jsonFile = "./sheets/" . $spreadsheetId . "/usageStat.json";

        // Check if the folder is not exists then create one and 
        // create the version.json file there with default value (0.0)
        if (!file_exists($jsonFile)) {
            mkdir("./sheets/" . $spreadsheetId, 0777, true);
        }

        // New version generated and saved
        //$pushstatus = 'OS'. $osType . '' . 'Browser' . $browserType. '' . 'Device' . $deviceType . '';
        /* $data = array('OS' => ($osType), 'Browser' => ($browserType), 'Device' => ($deviceType));
        $json_object = json_encode($data);
        $output = file_put_contents($jsonFile, $json_object);  */


        $current_data = file_get_contents($jsonFile);  
        $array_data = json_decode($current_data, true);  
        $extra = array('OS' => ($osType), 'Browser' => ($browserType), 'Device' => ($deviceType), 'DeviceId' => ($deviceId)); 
        $array_data[] = $extra;

        $array_data = array_values( array_unique( $array_data, SORT_REGULAR ) );

        $final_data = json_encode($array_data);  
        if(file_put_contents($jsonFile, $final_data))  
        {  
            echo 'Stat saved.';
        } 


        
    }
?>