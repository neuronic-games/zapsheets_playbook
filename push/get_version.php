<?php
    $jsonFile = $_POST['jsonPath'];
    /* if (!file_exists($jsonFile)) {
        mkdir($jsonFile, 0777, true);
    } */
    $tempFolder = explode('.json', $jsonFile);
    $folderName = explode('/version', $tempFolder[0]);
    if (!file_exists($jsonFile)) {
        mkdir($folderName[0], 0777, true);
        $data = array('version' => '0.0');
        $json_object = json_encode($data);
        $output = file_put_contents(($folderName[0] . '/version.json'), $json_object);
    }
    $json_object = file_get_contents($jsonFile);
    $data = json_decode($json_object, true);
    $output = $data['version'];
    echo $output;  
?>