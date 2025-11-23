<?php
    $jsonFile = $_POST['jsonPath'];
    $tempFolder = explode('.json', $jsonFile);
    $folderName = explode('/version', $tempFolder[0]);
    if (!file_exists($jsonFile)) {
        mkdir($folderName[0], 0777, true);
        $data = array('version' => '0.0');
        $json_object = json_encode($data);
        $output = file_put_contents(($folderName[0] . '/version.json'), $json_object);
    } 
    $jsonVersionNum = $_POST['versionNum'];
    $data = array('version' => $jsonVersionNum);
    $json_object = json_encode($data);
    $output = file_put_contents($jsonFile, $json_object);
?>