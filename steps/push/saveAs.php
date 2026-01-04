<!-- <?php 
  // Initialize a file URL to the variable 
  $url = $_POST['imgURL'];
  $spreadsheetId = $_POST['id'];
  // For normal and dropbox images
  $nameImg = basename($url); 
  $tempName = explode('?', $nameImg);
  // For Google drive images
  if($tempName[0] == 'thumbnail') {
    $fileId = explode('=', $nameImg)[1];
    $imageName = explode('&', $fileId)[0] . '.png';
  } else {
    $imageName = $tempName[0];
  }
  $dir = './sheets/' . $spreadsheetId . '/cacheImages/';
  if (!file_exists('./sheets/' . $spreadsheetId . '/cacheImages/')) {
    mkdir('./sheets/' . $spreadsheetId . '/cacheImages/', 0777, true);
  }
  // Use basename() function to return 
  // the base name of file 
  $file_name = $imageName; //basename($url); 
  echo $file_name;
  if(!empty($url)) {
    if(!file_exists($file_name)) {
      $content = file_get_contents($url);
      $output = file_put_contents($dir . $file_name, $content);
    }
  }
  echo "loaded"
?>