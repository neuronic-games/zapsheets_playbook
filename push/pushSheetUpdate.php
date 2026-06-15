<?php
    // Suppress PHP notices/warnings so they don't corrupt JSON responses
    error_reporting(0);
    ini_set('display_errors', '0');

    require __DIR__ . '/../dotEnv.php';

    $spreadsheetId = $_POST['id'];
    $sheet = $_POST['sheetname'];
    $dateStr = $_POST['date_string'];

    // Python interpreter path — set via PYTHON in .env
    // Local:  PYTHON=python3
    // Server: PYTHON=/home/zapsheets/virtualenv/public_html/playbook-test/push/3.11/bin/python3
    $pythonPath = $_ENV['PYTHON'];

    // Build a shell command: python + script + one argument, stderr suppressed
    // $dir should be __DIR__ so scripts are found by absolute path regardless of cwd
    function pyCmd($pythonPath, $dir, $script, $arg) {
        return escapeshellarg($pythonPath) . ' ' . escapeshellarg($dir . '/' . $script) . ' ' . escapeshellarg($arg) . ' 2>/dev/null';
    }

    if($dateStr != '') {

        // Get current stored version id to respective spreadsheet named folder;
        $jsonFile = "../sheets/" . $spreadsheetId . "/version.json";
        // Check if the folder is not exists then create one and
        // create the version.json file there with default value (0.0)
        if (!file_exists($jsonFile)) {
            mkdir("../sheets/" . $spreadsheetId, 0777, true);
        }

        $sheetName = 'Settings';
        $py_command = pyCmd($pythonPath, __DIR__, 'greadPush.py', $spreadsheetId . 'sheetname' . $sheetName . 'dateString' . $dateStr);
        $versionNum = shell_exec($py_command);
        $versionNum = str_replace("\r\n", "", $versionNum);

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

        $sheetName = $sheet;
        $jsonFile = "../sheets/" . $spreadsheetId . "/" . strtolower($sheetName) . ".json";

        $py_command = pyCmd($pythonPath, __DIR__, 'gread.py', $spreadsheetId . 'sheetname' . $sheetName);
        $sheetData = shell_exec($py_command);

        // Save fetched data to the sheet's JSON file
        if (!empty(trim($sheetData))) {
            if (!file_exists("../sheets/" . $spreadsheetId)) {
                mkdir("../sheets/" . $spreadsheetId, 0777, true);
            }
            file_put_contents($jsonFile, $sheetData);
        }
        echo 'Publishing ' . $sheetName . ' data to server';
    }

?>
