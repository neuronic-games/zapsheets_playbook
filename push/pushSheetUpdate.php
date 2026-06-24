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

        // Increment version in root version.json and write PublishedOn + Version
        // back to the Google Sheet's Settings tab via gwrite.py — same as deploySource.php.
        $root        = dirname(__DIR__);
        $versionFile = $root . '/version.json';
        $versionData = ['Version' => 0, 'PublishedOn' => ''];
        if (file_exists($versionFile)) {
            $ex = json_decode(file_get_contents($versionFile), true);
            if (is_array($ex)) $versionData = $ex;
        }
        $versionData['Version']     = (int)($versionData['Version'] ?? 0) + 1;
        $versionData['PublishedOn'] = date('M j, Y g:i A');
        file_put_contents($versionFile, json_encode($versionData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $gwriteScript = __DIR__ . '/gwrite.py';
        $arg          = $spreadsheetId . 'version' . $versionData['Version'];
        $cmd          = escapeshellarg($pythonPath) . ' '
                      . escapeshellarg($gwriteScript) . ' '
                      . escapeshellarg($arg) . ' 2>/dev/null';
        $out = trim((string) shell_exec($cmd));

        // Return the new version number so the JS can display it
        echo $versionData['Version'];

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
        $trimmed = trim($sheetData);
        if (!empty($trimmed)) {
            // Check if gread returned an error object instead of real data
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded) && isset($decoded['error'])) {
                echo 'ERROR:' . $sheetName . ':' . $decoded['error'];
            } else {
                if (!file_exists("../sheets/" . $spreadsheetId)) {
                    mkdir("../sheets/" . $spreadsheetId, 0777, true);
                }
                file_put_contents($jsonFile, $trimmed);

                // If this is the Settings sheet, sync PublishedOn + Version → version.json
                if (strtolower($sheetName) === 'settings') {
                    $records = json_decode($trimmed, true);
                    if (is_array($records)) {
                        $pubOn = null;
                        $ver   = null;
                        foreach ($records as $row) {
                            $name = trim($row['Name']  ?? '');
                            $val  = trim($row['Value'] ?? '');
                            if ($name === 'PublishedOn' && $val !== '') $pubOn = $val;
                            if ($name === 'Version'     && $val !== '') $ver   = $val;
                        }
                        if ($pubOn !== null || $ver !== null) {
                            $vf   = dirname(__DIR__) . '/version.json';
                            $vdat = ['Version' => 0, 'PublishedOn' => ''];
                            if (file_exists($vf)) {
                                $ex = json_decode(file_get_contents($vf), true);
                                if (is_array($ex)) $vdat = $ex;
                            }
                            if ($pubOn !== null) $vdat['PublishedOn'] = $pubOn;
                            if ($ver   !== null) $vdat['Version']     = $ver;
                            file_put_contents($vf, json_encode($vdat, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                        }
                    }
                }

                echo 'Publishing ' . $sheetName . ' data to server';
            }
        } else {
            echo 'SKIP:' . $sheetName;
        }
    }

?>
