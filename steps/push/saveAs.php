<?php 
  // Initialize a file URL to the variable 
  $url = $_POST['imgURL'];
  // For normal and dropbox images
  $nameImg = basename($url); 
  $tempName = explode('?', $nameImg);
  if($tempName[0] == 'thumbnail') {
    $fileId = explode('=', $nameImg)[1];
    $imageName = explode('&', $fileId)[0] . '.png';
  } else {
    $imageName = $tempName[0];
  }
  // Initialize directory name where 
  // file will be save 
  $dir = './img/cacheImages/'; 
  if (!file_exists('./img/cacheImages/')) {
    mkdir('./img/cacheImages/', 0777, true);
  }
  $file_name = $imageName; //basename($url); 
  echo $file_name;
  if(!empty($url)) {
    $content = file_get_contents($url);
    $output = file_put_contents($dir . $file_name, $content);
  }
  echo "loaded"
?>