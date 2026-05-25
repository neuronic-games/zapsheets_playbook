<?php
    // DIAGNOSTIC FOR PYTHON

    echo "<pre>";

    // 1. Check disabled functions in web context
    echo "Disabled functions: " . ini_get('disable_functions') . "\n\n";

    // 2. Test basic shell_exec
    $test = shell_exec('echo shell_exec_works');
    echo "shell_exec test: " . ($test ?? "NULL - shell_exec is disabled") . "\n";

    // 3. Check which python3 is found
    $which = shell_exec('which python3');
    echo "which python3: " . ($which ?? "NULL") . "\n";

    // 4. Try the virtualenv python
    require __DIR__ . '/dotEnv.php';
    $pythonPath = $_ENV['PYTHON'];
    echo "PYTHON from .env: " . $pythonPath . "\n";
    $pyTest = shell_exec(escapeshellarg($pythonPath) . ' -c "print(\'python works\')" 2>&1');
    echo "virtualenv python test: " . ($pyTest ?? "NULL") . "\n";

    // 5. Try running checkSheetStatus.py directly
    $script = escapeshellarg($pythonPath) . ' ' . escapeshellarg(__DIR__ . '/push/checkSheetStatus.py') . ' ' . escapeshellarg('1qFZqXwiEixdRzO1Ae57_ON9oKzoa-uBiUAOoMcGzoM4sheetnamesettings') . ' 2>&1';
    echo "Full command: " . $script . "\n";
    $result = shell_exec($script);
    echo "Result: " . ($result ?? "NULL") . "\n";

    echo "</pre>";
?>
