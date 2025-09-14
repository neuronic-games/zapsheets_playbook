<?php 
  // Initialize a file URL to the variable 
  //$url =  'https://www.dropbox.com/scl/fi/56x7232ergtv5c176qr53/cmh_wayfinding_bg.png?rlkey=dah8xxansk2syozv5nskewr5n&dl=1'; //'https://media.geeksforgeeks.org/wp-content/uploads/gfg-40.png'; 

  $url = $_POST['imgURL'];
  $spreadsheetId = $_POST['id'];

  //$urls = explode(',', $_POST['imgURL']);
  //$imageNames = explode(',', $_POST['imageName']);
  //$urlString += (name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1]) + ',' ;

  
  // For normal and dropbox images
  $nameImg = basename($url); 
  //$imageName = explode('?', $nameImg);
  $tempName = explode('?', $nameImg);

  // For Google drive images
  //$imageName = $url;
  //echo $nameImg;

  if($tempName[0] == 'thumbnail') {
    $fileId = explode('=', $nameImg)[1];
    $imageName = explode('&', $fileId)[0] . '.png';
  } else {
    $imageName = $tempName[0];
  }
  
  // Initialize the cURL session 
  //$ch = curl_init($url); 
  
  // Initialize directory name where 
  // file will be save 
  
  
  // Working earlier
  //$dir = './img/cacheImages/';
  
  $dir = './sheets/' . $spreadsheetId . '/cacheImages/';
  
  //$dir = 'C:/Users/Legion/Documents/Neuronic/liveImages/'; 
  //$dir = 'C:/map_cacheImages/'; 

  /* if (!file_exists('./img/cacheImages/')) {
    mkdir('./img/cacheImages/', 0777, true);
  } */

  if (!file_exists('./sheets/' . $spreadsheetId . '/cacheImages/')) {
    mkdir('./sheets/' . $spreadsheetId . '/cacheImages/', 0777, true);
  }


  /* $dir1    = 'c:/Users/';
  $files1 = scandir($dir1, SCANDIR_SORT_DESCENDING);
  print_r($files1); */

  //for ($i = 0; $i < count($urls); $i++) {

       /*      // Initialize the cURL session 
        $ch = curl_init($urls[$i]);  */
        //echo "The number is: $urls[0] <br>";
        // Use basename() function to return 
        // the base name of file 
        $file_name = $imageName; //basename($url); 

        echo $file_name;

        /* $content = file_get_contents($url);
        $output = file_put_contents($dir . $file_name, $content); */


        //if (!file_exists($dir . $file_name)) {

        if(!empty($url)) {
          if(!file_exists($file_name)) {
            $content = file_get_contents($url);
            $output = file_put_contents($dir . $file_name, $content);
          }
        }
        

        //header("Content-Length: ".filesize($url));

        //echo 'New File';

        //$file_name = basename($imageNames[$i]);

        //echo !file_exists($file_name);

        //if (!file_exists($file_name)) {

            //echo "ENTER";

            // Initialize the cURL session 
            //$ch = curl_init($urls[$i]); 
        
            // Save file into file location 
           /*  $save_file_loc = $dir . $file_name; 
            
            // Open file 
            $fp = fopen($save_file_loc, 'wb'); 
            
            // It set an option for a cURL transfer 
            curl_setopt($ch, CURLOPT_FILE, $fp); 
            curl_setopt($ch, CURLOPT_HEADER, 0); 
            

           
            
            
            // Perform a cURL session 
            curl_exec($ch); 
            
            // Closes a cURL session and frees all resources 
            curl_close($ch); 
            
            // Close file 
            fclose($fp);  */
        //} else {
          //echo 'File exists';
        //}
    //}

  echo "loaded"
?>