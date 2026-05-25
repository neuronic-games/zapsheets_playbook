<?php require "../dotEnv.php"; ?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta http-equiv='cache-control' content='no-cache, no-store, must-revalidate'>
    <meta http-equiv='expires' content='0'>
    <meta http-equiv='pragma' content='no-cache'>
    <!-- <meta http-equiv="EXPIRES" content="Wed, 17 May 2023 15:00:00 GMT" /> -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes" />
    <title>Playbook Menu</title>
    <link
      href="../css/bootstrap.min.css"
      rel="stylesheet"
      <?php if($_ENV['ENVIRONMENT'] != 'development') {
        echo 'integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC"';} ?>
      crossorigin="anonymous"
    />
    <link
      rel="stylesheet"
      href="../css/all.min.css"
      <?php if($_ENV['ENVIRONMENT'] != 'development') {
        echo 'integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg=="';} ?>
      crossorigin="anonymous"
      referrerpolicy="no-referrer"
    />
    <link
      rel="stylesheet"
      href="../css/minireset.min.css"
    />

    <link rel="icon" type="image/x-icon" href="../images/sheet_2_new.webp?version=3.2" />
    <link rel="apple-touch-icon" href="../images/sheet_icon_new.webp?version=3.2" />
  </head>
  <body id="menuBody" style="position: fixed; min-height: 100vh !important; background-color: #F9F3E3 !important" onpagehide="CloseIFrame()">
    <!--------------------------------------------------------------------->
    <div id="menuSnippetContainer" style="position: absolute; width: 100%; height: 100vh;">
      <div id="loadingScreen" style="position: absolute; top: 0; left: 0; z-index: 99999; width: 100%; display: flex; justify-content: space-evenly; height: 100vh;">
        <!------------------------------------------------------------------>
        <p id="versionInfo" style="position: absolute; width: 90%; text-align: right; top: 3vh; font-size: 1.7vh; color: #666666;"></p>
        <p id="appInfo" style="position: absolute; width: 100%; text-align: center; top: 37%; font-size: 4vh; color: #666666; padding: 15%;"></p>
        <!------------------------------------------------------------------>
        <div id="spinnerBox" class="spinner-box">
          <div class="circle-border">
            <div class="circle-core"></div>
          </div>  
        </div>
        <!------------------------------------------------------------------->
        <div id="loadingText" style="position: absolute;
        top: 40vh;
        left: 50%;
        width: 89%;
        font-size: 2.5vh;
        text-align: center;
        color: #666666;
        display: none;
        transform: translate(-50%, 0%); font-family: Oswald-Bold; line-height: 2.6vh; font-weight: 500;"><br>LOADING</div>
      </div>
      <!-------------------------------------------------------------------------->
      <div id="menuScreen" style="display: none; width: 100%; height: 100vh; background-color: white; position: absolute; z-index: 99999;">
        <!---------------------------SLIDE 1 START--------------------------------->
        <div id="menu" style="position: absolute; top: 0; left: 0; width: 100%; height: 100vh;">
          <div id="menuImg" style="position: absolute; top: 0; left: 0; width: 100%; height: 100vh; z-index: 0;">
            <img id="menuBGInage" src="../images/floristry_mobile_scn_auction.webp" alt="" style="position: relative;
            left: 50%;
            top: 50%;
            height: 100vh;
            width: 100vw;
            transform: translateY(-50%) translateX(-50%); z-index: 0; object-fit: cover;" />
          </div>
        </div>
        <!----------BUTTONS-------------------------------------------------------------->
        <div id="buttonContainer" style="position: absolute; width: 100%; height: 25%; /* background-color: #683F97; */ bottom: 15vh; display: flex; justify-content: flex-end; align-items: center; flex-direction: column; font-size: 3vh !important;">
            <div id="touchSection" style="display: flex; position: relative; align-items: center; justify-content: space-around;">
              <div style="position: absolute;
                width: 13vh;
                height: 13vh;
                background-color: #eea41ca1;" 
                class="touch-pulse-button">
              </div>
              <img src="../images/btn_touch.png" style="position: relative; width: 8vh;" alt=""  />
            </div>
            <div id="viewIconText" style="position: relative; display: none/* flex */; justify-content: center; margin-top: 2.5vh; width: 70%; flex-direction: column;">
            </div>
        </div>
        <div id="infoBGGSection" style="position: absolute; width: 100%; display: none /* flex */; flex-direction: row; justify-content: flex-end; height: 10vh;">
          <img id="infoIconBtn" src="../images/btn_info.webp?version=1" style="position: absolute; width: 10vh; height: 10vh; top: 4vh; left: 70%; cursor: pointer;" class="animated-img img1" alt="" />
          <img id="bggIconBtn" src="../images/btn_bgg.png?version=1" style="position: absolute; width: 10vh; height: 10vh; top: 4vh; left: 70%; cursor: pointer;" class="animated-img img2" alt="" />
        </div>
        <!---------------------------------------------------------------------------->
        <div id="spinnerMiddleBox" class="spinner-box-middle">
          <div class="circle-border-middle">
            <div class="circle-core-middle"></div>
          </div>  
        </div>
        <!----------------------------------------------------------------------------->
      </div>
      <!------------------------------------------------------------------>
      <div id="menuPage" style="position: absolute; top: 0; left: 0; width: 100%; height: 100vh; background-color: #2D2C2B; display: none; z-index: 99999;">
        <div style="position: absolute; display: flex; flex-direction: row; align-content: center; justify-content: space-between; align-items: center; width: 100vw; left: 0;padding: 5vh; background-color: #2D2C2B; height: 0; z-index: 99;">
          <img id="backToMenuBtn" src="../images/floristry_mobile_btn_prev_orange.png" style="position: relative; width: 4vh; z-index: 999; cursor: pointer;" alt="" />
          <p id="menuTitle" style="color: white; position: absolute; font-size: 3.5vh;margin-left: 5vh;">Title</p>
          <sup id="small-sub" class="sup-small" data-objectid=""></sup>
          <img id="downloadBtn" src="" style="position: relative; width: 5vh; z-index: 999; cursor: pointer;" alt="" />
        </div>
        <div id="menuList" style="position: relative;
          display: flex;
          flex-direction: column;
          align-content: flex-start;
          justify-content: flex-start;
          width: 94vw !important;
          left: 0;
          padding: 5vh;
          top: 4vh;
          padding-bottom: 15vh;
          height: 95%; overflow: scroll;">
        </div>
        <iframe id="contentSteps" title="" style="position: absolute; top: 0; left: 0; position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100vh;
        z-index: 99999999;
        display: none;"></iframe>
      </div>
      <div id="menuDetailsPage" style="position: absolute; top: 0; left: 0; width: 100%; height: 100vh; background-color: #2D2C2B; display: none; z-index: 99999;">
        <div style="position: absolute; display: flex; flex-direction: row; align-content: center; justify-content: space-between; align-items: center; width: 100vw; left: 0;padding: 5vh; background-color: #2D2C2B; height: 0; z-index: 99;">
          <img id="backToRuleMenuBtn" src="../images/floristry_mobile_btn_prev_orange.png" style="position: relative; width: 4vh; z-index: 999; cursor: pointer;" alt="" />
          <p id="menuDetailsTitle" style="color: white; position: absolute; font-size: 3.5vh;margin-left: 5vh;">Menu Title</p>
        </div>
        <div id="menuDetails" style="position: relative;
          display: flex;
          flex-direction: column;
          align-content: flex-start;
          justify-content: flex-start;
          width: 100vw;
          left: 0;
          padding: 5vh;
          top: 4vh;
          height: 90%; overflow: scroll; background-color: 
          #2D2C2B;"></div>

        <div id="rulesNavigation" style="position: absolute; bottom:0vh; width: 90%; left: 5%; height: 10vh; background-color: #2D2C2B; /* display: flex; */ color:#F7AE50; font-size: 2.5vh; align-items: center; justify-content: space-between; display: none;">

        <div style="position: relative;
    display: flex;
    align-items: center;
    justify-content: flex-start;
    flex-direction: row;">
          <img id="prevArrow" src="../images/floristry_mobile_btn_prev_orange.png" style="position: relative;display:none;
    width: 3vh;
    z-index: 999;
    cursor: pointer;
    height: 3vh;
    margin-right: 0.5vh;" alt="" />
          <div id="prevElement"></div>
        </div>
        <div style="position: relative;
    display: flex;
    align-items: center;
    justify-content: flex-start;
    flex-direction: row;">
          <div id="nextElement"></div>
          <img id="nextArrow" src="../images/floristry_mobile_btn_next_orange.png" style="position: relative;display:none;
    width: 3vh;
    z-index: 999;
    cursor: pointer;
    height: 3vh;
    margin-left: 0.5vh;" alt="" />
        </div>
        </div>

      </div>
      
      <!------------------------------------------------------------------>
      <div id="sheetIdError" class="text-center pt-4" style="position: absolute; width:100%; height:100vh; display:none; flex-direction:column;justify-content:center; z-index:9999999999 !important;">
        <h5 class="text-center" style="color:red; display:block; font-family:AcuminVariableConcept;">**Sheet Id missing.</h5>
        <h4 class="text-center h3 mb-3" style="position:relative;top: 1em; z-index:9; font-family:AcuminVariableConcept; letter-spacing: -1px; font-weight: 700; width: 80%; left: 10%;">ENTER YOUR GOOGLE SHEET ID, OR GOOGLE SHEET URL</h4>
        <div id="settingBox" class="text-center mt-4 pt-4" style="width: 80%; position:relative; height: auto; left: 10%; margin-top: 0em !important; border-radius: 2px;">
        <div style="border: 2px solid #FFFFFF; padding: 0.5vh; border-radius: 5px; position: relative; width: 96%; left: 2%; top: -10px; height: 5.6vh;">
            <input class="form-control" type="text" id="usheetId" name="usheetId" style="position: relative; margin-top: 0px; width: 100%;margin-left: 0em; height: 4.2vh; font-family: AcuminVariableConcept;" pattern="[A-Za-z0-9]+" onkeydown="if(['Space'].includes(arguments[0].code)){return false;}"/>
        </div>
        <img id="sheetIdBtn" src="../images/floristry_mobile_btn_next.webp" class="img-fluid" style="width: 10vh;" alt="" />
        </div>
        </div>
    </div>
  </div>
    <!------------------------------------------------------------------------>
    <script src="../js/common/jquery-3.5.1.min.js"></script>
    <script src="../js/common/jquery.cookie.min.js"></script>
    <script
      src="../js/common/bootstrap.bundle.min.js"
      <?php if($_ENV['ENVIRONMENT'] != 'development') {
        echo 'integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM"';} ?>
      crossorigin="anonymous"
    ></script>
    <script>
    </script>
    <!------------------------------------------------------------------------->
    <script src="../js/common/moment.min.js?version=1.0"></script>
    <script src="../js/common/device-uuid.min.js?version=1.0"></script>
    <script src="../js/common/md5.js?version=1.0"></script>
    <script src="../js/common/createjs.min.js?version=3.9"></script>
    <script src="../js/common/devicedetector-min.js"></script>
    <!------------------------------------------------------------------------->
    <script>
      // To get UIVersion from Parent HTML for cache/dynamic loading
      const selfUrl = new URL(self.location);

      //console.log(selfUrl.searchParams.get('version').split('?')[0]);
      let UIVersion = selfUrl.searchParams.get('version').split('?')[0]

      // Load JSController File for menu section
      getLatestGameCSSFile(UIVersion)
      getControllerVersion(UIVersion)
      /////////////////////////////////////////////////////////////////////////////////
      /*
      * getControllerVersion
      */
      function getControllerVersion(_ver) {
        var conScript = document.createElement('script');
        conScript.id = 'controller_Script';
        conScript.type = 'text/javascript';
        conScript.src = './js/MenuController.js?version=' + _ver;
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
        cssLink.href = '../css/style.css?version=' + _ver;
        document.getElementsByTagName('head')[0].appendChild(cssLink);

        // Display Loading text
        setTimeout(function() {
          document.getElementById('loadingText').style.display = 'block';
        }, 250)
      }

      if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone) {
        document.getElementById('buttonContainer').style.setProperty("bottom","2vh");
      }
      function CloseIFrame() {
        window.parent.postMessage(JSON.stringify({'message': 'closeFrame'}), '*') 
      }
    </script>
    <!-------------------------------------------------------------------------->
  </body>
</html>
