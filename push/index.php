<?php require __DIR__ . '/../dotEnv.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta http-equiv='cache-control' content='no-cache'>
  <meta http-equiv='expires' content='0'>
  <meta http-equiv='pragma' content='no-cache'>
 <!--  <meta http-equiv="EXPIRES" content="Sat, 27 July 2024 11:00:00 GMT" /> -->
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="apple-mobile-web-app-capable" content="yes" />
  <meta name="mobile-web-app-capable" content="yes">
  <title>Playbook</title>
  <link rel="stylesheet" href="../css/bootstrap.min.css"
    <?php if($_ENV['ENVIRONMENT'] != 'development') {
    echo '
';} ?>
    crossorigin="anonymous" />
  <link rel="icon" type="image/x-icon" href="../images/sheet_2_new.webp?version=1.8" />
  <link rel="apple-touch-icon" href="../images/sheet_icon_new.webp?version=1.7" />
</head>
<script src="../js/common/bootstrap.bundle.min.js"
    <?php if($_ENV['ENVIRONMENT'] != 'development') {
    echo '
';} ?>
    crossorigin="anonymous"></script>
<script src="../js/common/jquery-3.5.1.min.js?v=3"
    <?php if($_ENV['ENVIRONMENT'] != 'development') {
    echo '
';} ?>
    crossorigin="anonymous"></script>
<script src="../js/common/moment.min.js"
  <?php if($_ENV['ENVIRONMENT'] != 'development') {
  echo '
';} ?>
  crossorigin="anonymous"></script>
<script src="../js/common/popper.min.js"
  <?php if($_ENV['ENVIRONMENT'] != 'development') {
  echo '
';} ?>
  crossorigin="anonymous"></script>
<script src="../js/common/bootstrap.min.js"
  <?php if($_ENV['ENVIRONMENT'] != 'development') {
  echo '
';} ?>
  crossorigin="anonymous"></script>

<body class="page_greek d-flex flex-column min-vh-100" style="background-color: #FFFFFF !important;">
  <header id="page-header" class="game_header pt-4">
		<div class="container">
			<div class="game_logo" onclick="">
        <img src="../images/step_icon_new.webp?version=1.7" alt="" class="img-fluid mr-3" width="60"/>
				<img src="../images/zapsheets.png?version=1.7" alt="" class="img-fluid" width="250"/>
			</div>
			<h1 id="pushTitle" class="h2 header_title mt-md-5 mt-4 font-poppins"></h1>
		</div>
    <p id="versionId" class="versionContiner"></p>
	</header>
  <div class="container mt-2 mb-5">
    <div class="row">
      <div class="col-md-12" class="cardListHolder">
        <div class="cardList"></div>
      </div>
      <div id="mainAppContainer" class="mainContainer text-uppercase">
        <div id="defaultBG" class="defaultBGImageContainer">
          <img id="defaultBGImage" class="defaultBGImg" src="" alt="" />
        </div>
        <div id="topSlider" class="slideshowContainer">
          <div class="related_events"></div>
          <div class="sliderOutline"></div>
          <p id="slideLoading" class="slideLoading"></p>
        </div>
      </div>
      <div id="spinningLoader" class="text-center loader-spinner-text"/>
        <h4 id="loadingText">Publishing Playbook Assets...<br></h4>
      </div>
    </div>
  </div>
  <footer id="page-footer" class="game_footer mt-auto pb-4">
		<div class="container">
			<div class="copyright text-center text-light"><!-- © 2023 zsheets 2023 All Right Reserved --></div>
		</div>
	</footer>
  <script>
    // Unregister any service worker that might intercept requests on this page.
    // The playbook SW is for the game viewer, not the push tool.
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.getRegistrations().then(function(regs) {
        regs.forEach(function(reg) { reg.unregister(); });
      });
    }
    //////////////////////////////////////////////////////////////////////////////
    // Get unique number using time stamp
    function getUniqueNumber() {
      const now = new Date();
      const timestamp = now.getTime(); // Get the timestamp in milliseconds
      return timestamp;
    }
    //////////////////////////////////////////////////////////////////////////////
    let UIVersion = getUniqueNumber();
    getLatestPushCSSFile(UIVersion);
    getZapsheetsCore();
    getCurrentVersion();
    getCurrentGamePushVersion();
    /////////////////////////////////////////////////////////////////////////////////
    function getLatestPushCSSFile(_ver) {
      let cssLink = document.createElement('link');
      cssLink.rel = 'stylesheet';
      cssLink.type = 'text/css';
      cssLink.href = '../css/style.css?version=' + _ver;
      document.getElementsByTagName('head')[0].appendChild(cssLink);
    }
    function getCurrentVersion() {
      var newScript = document.createElement('script');
      newScript.id = 'version_Script';
      newScript.type = 'text/javascript';
      newScript.src = '../js/main/version.js?version=' + UIVersion;
      document.getElementsByTagName('head')[0].appendChild(newScript);
    }
    function getCurrentGamePushVersion() {
      var floristryScript = document.createElement('script');
      floristryScript.type = 'text/javascript';
      floristryScript.id = 'floristry_Script';
      floristryScript.src = '../js/push/pushSteps.js?version=' + UIVersion;
      document.getElementsByTagName('head')[0].appendChild(floristryScript);
    }
    function getZapsheetsCore() {
      var funtionScript = document.createElement('script');
      funtionScript.type = 'text/javascript';
      funtionScript.id = 'function_Script';
      funtionScript.src = '../js/core/zapsheetsCore.js?version=' + UIVersion;
      document.getElementsByTagName('head')[0].appendChild(funtionScript);
    }
  </script>
</body>
</html>