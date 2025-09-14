<?php 
    /* header('Content-Type: text/html; charset=ISO-8859-1'); */
    /*
    Arguments from index file
    */
    
    $sheetId = $_POST['sheetId'];
    $sheet = $_POST['sheet'];

    // Local Testing
    $python_file_name = "gread.py "; 
    $python_execution = "python ".$python_file_name .$sheetId .'sheetname' .$sheet; 
    $output = shell_exec($python_execution); 
    echo $output;   
    
    // For Server
    /* $py_command = escapeshellcmd('source /home/neuronic/virtualenv/public_html/earshot/3.9/bin/python gread.py ' .$sheetId .'sheetname' .$sheet); 
    $com_output = shell_exec($py_command); 
    echo $com_output; */
?>