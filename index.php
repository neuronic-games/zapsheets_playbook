<?php require "./dotEnv.php"; ?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta content-type='text/javascript' charset="UTF-8" />
    <meta http-equiv='cache-control' content='no-cache, no-store, must-revalidate'>
    <meta http-equiv='expires' content='0'>
    <meta http-equiv='pragma' content='no-cache'>
    <!-- <meta http-equiv="EXPIRES" content="Thu, 16 Jan 2025 12:30:00 GMT" /> -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no" />
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes" />
    <title>Playboook</title>
    <link
      href="./css/bootstrap.min.css"
      rel="stylesheet"
      <?php if($_ENV['ENVIRONMENT'] != 'development') {
        echo 'integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC"';} 
      ?>
      crossorigin="anonymous"
    />
    <link
      rel="stylesheet"
      href="./css/all.min.css"
      <?php if($_ENV['ENVIRONMENT'] != 'development') {
        echo 'integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg=="';} ?>
      crossorigin="anonymous"
      referrerpolicy="no-referrer"
    />
    <link
      rel="stylesheet"
      href="./css/minireset.min.css"
    />
    <link rel="icon" type="image/x-icon" href="img/sheet_2_new.webp?version=1.0" />
    <link rel="apple-touch-icon" href="img/sheet_icon_new.webp?version=1.0" />
    <!-- For PWA Hack -->
    <link rel="manifest" href="manifest.json?version=1">
  </head>
  <body id="mainBody" style="position: fixed; min-height: 100vh !important;">
    <!-- Loaging screen default -->
   <div id="defaultScreen" style="position: relative; width: 100vw; height: 100vh; display: flex;
      flex-direction: column; align-items: center; justify-content: space-around;">
    <img  src="img/sheet_2_new.webp?version=1.0" style="position:relative; width:25vw;"  alt="" onContextMenu="return false;" >
    </div>
    <iframe id="content" title="" style="position: absolute; top: 0; left: 0; position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100vh;
    z-index: 9999999;
    display: block;"></iframe>
  </div>
  <!----------------------------------------------------------------------------------->
  <div id="useMode" style="position: absolute;
  display: flex;
  width: 100%;
  height: 100%;
  background-color: #F9F3E3;
  top: 0;
  left: 0;
  z-index: 99999999;
  align-content: center;
  align-items: center;
  justify-content: space-around;">
  <img id="useModeBG" src="img/floristry_bg.png" alt="" onContextMenu="return false;" style="position: absolute; top: 0; left: 0; width: 100%;
  height: 100%; display: none;">
  <img id="modeLogo" src="img/orientation.png?version=1.0" alt="" onContextMenu="return false;" style="position: absolute;
  width: 45vh;
  display: none;">
    <p id="modeMsg" style="font-size: 3vh;
    padding: 5vh;
    color: #808080;
    z-index: 9; text-align: center; display: block;"></p>
  </div>
  <!--------------------------------------------------------------------------->
    <include src="./result.html"></include>
    <script src="./js/common/jquery-3.5.1.min.js"></script>
    <script src="./js/common/jquery.cookie.min.js"></script>
    <script
      src="./js/common/bootstrap.bundle.min.js"
      <?php if($_ENV['ENVIRONMENT'] != 'development') {
        echo 'integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM"';} ?>
      crossorigin="anonymous"
    ></script>
    <script>
    </script>
    <!--------------------------------------------------------------------------->
    <script src="./js/common/devicedetector-min.js"></script>
    <script src="./js/common/localdata.js"></script>
    <script src="./js/common/moment.min.js?version=1.0"></script>
    <script src="./js/common/device-uuid.min.js?version=1.0"></script>
    <script src="./js/common/md5.js?version=1.0"></script>
    <script src="./js/common/createjs.min.js?version=1.0"></script>
    <!-------------------FILE HANDLES OFFLINE MODE------------------------------->
    <script>
      let isSWExists = false;
      let uiSpinnetLoaded = false;
      let localVersion = 2;
      let UIVersion = 0;
      let versionLoaded = false
      let sheetToLoad = ''
      //////////////////////////////////////////////////////////////////////////////
      // Get unique number using time stamp
      function getUniqueNumber() {
        const now = new Date();
        const timestamp = now.getTime(); // Get the timestamp in milliseconds
        return timestamp;
      }
      //////////////////////////////////////////////////////////////////////////////
      // Load uiSnippet html
      // Checking Cors issues
      const loadSnippet = async (_ver) => {
        var sheetType = (getUrlVars()["sheet"]) ? getUrlVars()["sheet"].split('/')[0] : 'menu-en';
        var sheet_Id = (getUrlVars()["id"]) ? getUrlVars()["id"].split('/')[0] : '';
        var activeLang = (getUrlVars()["code"]) ? getUrlVars()["code"].split('/')[0].toUpperCase() : navigator.language.split('-')[0].toUpperCase();

        var fromParent = (getUrlVars()["from"]) ? getUrlVars()["from"].split('/')[0] : '';
        var faqsSheetId = (getUrlVars()["faqsId"]) ? getUrlVars()["faqsId"].split('/')[0] : '';
        var standalone = (getUrlVars()["standalone"]) ? getUrlVars()["standalone"].split('/')[0] : '';

        // Reset Styling
        if(fromParent == 'floristry') {
          // Set Styling
          document.getElementById('content').style.setProperty('--screen-width', '100%');
          document.getElementById('content').style.setProperty('--screen-left', '0%');

        } else {
          // Set Styling
          document.getElementById('content').style.setProperty('--screen-width', '70%');
          document.getElementById('content').style.setProperty('--screen-left', '15%');
        }
        
        switch(sheetType.split('-')[0]) {
          case 'menu':
          case 'menus':
            console.log("MENU")
            sheetToLoad = './menu/index.php?version=' + _ver + "&code=" + activeLang
            break;
          case 'steps':
          case 'step':
            console.log("STEPS")
            sheetToLoad = './steps/index.php?version=' + _ver + "&code=" + activeLang + "&steps&" + fromParent + "&" + faqsSheetId + "&standalone=" + standalone 
            break;
          case 'faqs':
          case 'faq':
            console.log("FAQS")
            sheetToLoad = './menu/index.php?version=' + _ver + "&code=" + activeLang + "&faqs&" + fromParent + "&" + faqsSheetId
            break;
          case 'rules':
          case 'rule':
            console.log("RULES")
            sheetToLoad = './menu/index.php?version=' + _ver + "&code=" + activeLang + "&rules"
            break;
        }

        const res = await fetch(sheetToLoad, {
          method: 'GET',
          mode: 'cors',
          headers: {
            'Content-Type': 'application/json'
          },
        }).then(res => {
          if(res.ok) {
            return res.text()
          }
        }).then(htmlSnippet => {

          setTimeout(function() {
            $("#content").attr("src", sheetToLoad + "?code=" + activeLang + "&id=" + sheet_Id);
          }, 250) 
          uiSpinnetLoaded = true;

          // Loading updated Controller
          getControllerVersion(_ver)

          // Hide default screen
          document.getElementById('defaultScreen').style.display = 'none'
          const registerServiceWorker = async () => {
            if ("serviceWorker" in navigator) {
              try {
                const registration = await navigator.serviceWorker.register("sw_playbook.js?version=" + _ver, {
                  scope: "",
                });
                if (registration.installing) {
                  isSWExists = false
                } else if (registration.waiting) {
                  isSWExists = false
                } else if (registration.active) {
                  isSWExists = true
                }
              } catch (error) {
                console.error(`Registration failed with ${error}`);
              }
            }
          };
          registerServiceWorker();
          if(window.navigator.onLine == true) {
            CheckNetConnection();
          } else {
          }
          // Checking internet speed
          function CheckNetConnection() {
            if(window.navigator.onLine == true) {
              var netStartTime = new Date().getTime();
              var img = new Image();
              img.onload = function() {
                var netLoadTime = new Date().getTime() - netStartTime;
                checkConnectionSpeed(netLoadTime);
              }
              img.src = "./img/zapsheets.png?version=" + Math.random()
            }
          }
          function checkConnectionSpeed(milliseconds) {
            if(window.navigator.onLine == true) {
              let downloadSize = 399000; //1024 * 1024 * 5;
              var duration = (milliseconds) / 1000;
              var bitsLoaded = downloadSize * 8;
              var bps = (bitsLoaded / duration).toFixed(2);
              var kbps = (bps / 1024).toFixed(2);
              var mbps = (kbps / 1024).toFixed(2);
            }
          }
        })
      }
      /////////////////////////////////////////////////////////////////////////////////
      function loadInternetSettings(_ver) {
        var inetScript = document.createElement('script');
        inetScript.id = 'inet_Script';
        inetScript.type = 'text/javascript';
        inetScript.src = './js/main/getInternetStat.js?version=' + _ver;
        document.getElementsByTagName('head')[0].appendChild(inetScript);
      }
      /////////////////////////////////////////////////////////////////////////////////
      /*
      * getControllerVersion
      */
      function getControllerVersion(_ver) {
        var conScript = document.createElement('script');
        conScript.id = 'controller_Script';
        conScript.type = 'text/javascript';
        conScript.src = './js/main/JSController.js?version=' + _ver;
        document.getElementsByTagName('head')[0].appendChild(conScript);
      }
      /////////////////////////////////////////////////////////////////////////////////
      /*
      * getLatestGameCSSFile
      */
      function getLatestGameCSSFile(_ver) {
        let cssLink = document.createElement('link');
        cssLink.rel = 'stylesheet';
        cssLink.type = 'text/css';
        cssLink.href = './css/style.css?version=' + _ver;
        document.getElementsByTagName('head')[0].appendChild(cssLink);
      }
      /////////////////////////////////////////////////////////////////////////////////
      /**
       * 
       * @returns 
       */
      function getUrlVars() {
          var vars = [], hash;
          var hashes = window.location.href.slice(window.location.href.indexOf('?') + 1).split('&');
          for (var i = 0; i < hashes.length; i++) {
              hash = hashes[i].split('=');
              vars.push(hash[0]);
              vars[hash[0]] = hash[1];
          }
        return vars;
      }
      //////////////////////////////////////////////////////////////////////////
      setTimeout(function() {
        if(window.ldb != undefined) {
          window.ldb.get('appPlaybookUIVersion', function (value) {
            if(navigator.onLine == true) {
              UIVersion = getUniqueNumber();
              window.ldb.set('appPlaybookUIVersion', UIVersion)
              getLatestGameCSSFile(UIVersion)
              loadSnippet(UIVersion)
            } else {
              UIVersion = value
              getLatestGameCSSFile(UIVersion)
              loadSnippet(UIVersion)
            }
          })
        } else {
          UIVersion = getUniqueNumber();
          window.ldb.set('appPlaybookUIVersion', UIVersion)
          getLatestGameCSSFile(UIVersion)
          loadSnippet(UIVersion)
        }
      }, 3000)
    </script>
    <!----------------------------------------------------------------------------------->
    <script src="./js/common/jquery-ui.js"></script>
    <script src="./js/common/devicedetector-min.js"></script>
    <!----------------------------------------------------------------------------------->
  </body>
</html>
