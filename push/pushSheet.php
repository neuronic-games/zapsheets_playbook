<?php
    // Suppress PHP notices/warnings so they don't corrupt JSON responses
    error_reporting(0);
    ini_set('display_errors', '0');

    require __DIR__ . '/../dotEnv.php';

    $spreadsheetId = $_POST['id'];
    $sheet = $_POST['sheetname'];

    // Python interpreter path — set via PYTHON in .env
    // Local:  PYTHON=python3
    // Server: PYTHON=/home/zapsheets/virtualenv/public_html/playbook-test/push/3.11/bin/python3
    $pythonPath = $_ENV['PYTHON'];

    // Build a shell command: python + script + one argument, stderr suppressed
    // $dir should be __DIR__ so scripts are found by absolute path regardless of cwd
    function pyCmd($pythonPath, $dir, $script, $arg) {
        return escapeshellarg($pythonPath) . ' ' . escapeshellarg($dir . '/' . $script) . ' ' . escapeshellarg($arg) . ' 2>/dev/null';
    }

    if($sheet == '') {

        $dateStr = $_POST['date_string'];

        // Get current stored version id to respective spreadsheet named folder;
        $jsonFile = "../sheets/" . $spreadsheetId . "/version.json";

        // Check if the folder is not exists then create one and
        // create the version.json file there with default value (0.0)
        if (!file_exists($jsonFile)) {
            mkdir("../sheets/" . $spreadsheetId, 0777, true);
        }

        $sheetName = 'Settings';

        // For Server
        $py_command = pyCmd($pythonPath, __DIR__, 'greadPush.py', $spreadsheetId . 'sheetname' . $sheetName . 'dateString' . $dateStr);
        $versionNum = shell_exec($py_command);
        $versionNum = str_replace("\r\n", "", $versionNum);


        // Return Message to console
        echo $versionNum;

    } else if($sheet == 'Settings') {

        $sheetName = $sheet;

        // For Server
        $py_command = pyCmd($pythonPath, __DIR__, 'gread.py', $spreadsheetId . 'sheetname' . $sheetName);
        $settingsData = shell_exec($py_command);


        echo 'Publishing settings data to server';

    } else if ($sheet == 'Install') {

        $jsonFile = "../sheets/" . $spreadsheetId . "/install.json";
        $sheetName = $sheet;

        // For Server
        $py_command = pyCmd($pythonPath, __DIR__, 'gread.py', $spreadsheetId . 'sheetname' . $sheetName);
        $directoryData = shell_exec($py_command);


        echo 'Publishing Install data to server';

    } else if ($sheet == 'Events') {

        $jsonFile = "../sheets/" . $spreadsheetId . "/events.json";
        $sheetName = $sheet;

        // For Server
        $py_command = pyCmd($pythonPath, __DIR__, 'gread.py', $spreadsheetId . 'sheetname' . $sheetName);
        $eventsData = shell_exec($py_command);


        $output = file_put_contents($jsonFile, $eventsData);
        echo 'Publishing events data to server';

    } else if ($sheet == 'Kiosks') {

        $jsonFile = "../sheets/" . $spreadsheetId . "/kiosks.json";
        $sheetName = $sheet;

        // For Server
        $py_command = pyCmd($pythonPath, __DIR__, 'gread.py', $spreadsheetId . 'sheetname' . $sheetName);
        $kiosksData = shell_exec($py_command);


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

        $sheetName = $sheet;
        // product-* sheets all map to product.json
        if (stripos($sheetName, 'product-') === 0) {
            $jsonFile = "../sheets/" . $spreadsheetId . "/product.json";
        } else {
            $jsonFile = "../sheets/" . $spreadsheetId . "/" . strtolower($sheetName) . ".json";
        }

        // For Server
        $py_command = pyCmd($pythonPath, __DIR__, 'gread.py', $spreadsheetId . 'sheetname' . $sheetName);
        $stepsLang = shell_exec($py_command);


        // Save fetched data to the sheet's JSON file
        if (!empty(trim($stepsLang))) {
            if (!file_exists("../sheets/" . $spreadsheetId)) {
                mkdir("../sheets/" . $spreadsheetId, 0777, true);
            }
            file_put_contents($jsonFile, $stepsLang);
        }
        echo 'Publishing ', $sheet . ' data to server';

    } else if($sheet == 'checkSheet') {

        $tabName = $_POST['tab_name'];
        $sheetName = $tabName;

        // For Server
        $py_command = pyCmd($pythonPath, __DIR__, 'checkSheetStatus.py', $spreadsheetId . 'sheetname' . $sheetName);
        $isSheet = shell_exec($py_command);


        // Guard: if the Python script returned nothing, echo a safe JSON error
        if(empty(trim($isSheet))) {
            echo json_encode(['exists' => 'no', 'error' => 'checkSheetStatus script returned no output']);
        } else {
            echo $isSheet;
        }
    }

?>
