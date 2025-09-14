//////////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 * Ready events
 */
/* $(document).ready(function() { */
window.addEventListener("load", (event) => {
    //console.log('READY')
    /* console.log('READY')
    let stepElement = `<div id="stepsScreen" style="display: none; width: 100%; height: 100%; background-color: white; position: absolute; z-index: 99999;">
        <img src="img/floristry_mobile_scn_auction.png" alt="" />
        <div style="display: flex; position: absolute; top: 0; height: 20vh; width: 100%; background-color: #00000095; flex-direction: column;
        justify-content: center;
        align-items: center;">
            <span style="font-size: 4vh;
        color: white; width: 90%; text-align: center;">This is Floritry game instructions.</span>
        </div>
        <div style="position: absolute; bottom: 0vh; height: 14vh; width: 100%; background-color: #00000095;">
            <div style="display: flex; flex-direction: row;
        justify-content: space-around; padding-top: 1.5vh;">
            <img id="prevIcon" src="img/floristry_mobile_btn_prev.png" style="width: 10vh; height: 10vh;" alt="" />
            <img id="homeIcon" src="img/floristry_mobile_btn_home.png" style="width: 10vh; height: 10vh;" alt="" />
            <img id="nextIcon" src="img/floristry_mobile_btn_next.png" style="width: 10vh; height: 10vh;" alt="" />
            </div>
        </div> 
        </div>`

    console.log(document.getElementById('mainBody'), " >>>>")
     document.getElementById('mainBody').append(stepElement)
    //document.getElementsByTagName('body')[0].appendChild(stepElement); */

    // Steps Variables
    let stepIndex = 0
    let languageStepsData = [];
    let settingDataList = []
    let autoPlay;

    let moveType = 'right'

    // For lazy load
    // For LOCAL TESTING
    //let lazyLoadImages = 'TRUE'

    // For LIVE
    let lazyLoadImages = ''

    // Transition effect applying to different image not as same on concurrent images
    let prevImage = ''
    let newImage = '' 

    // For Precache Images
    var preCacheImages = []
    var preCachedDone = false;

    // For typewriter effect
    let letters = ''
    let letterIndex = 0;

    // To store active slide index
    let activeSlideIndex = 0

    // To store active viewlink
    let activeViewLink = ''

    // To store openIn value
    let openInType = ''

    // To get the list of message
    let endFound = false
    let dispInfoCount = 0
    let dispMessageList = []

    //return;

    ////////////////////////////////////////////////////////////////////////////////////////////////////
    // Save usage stat
    /**
     * saveUsageStat
     */
    /* function saveUsageStat() {
        let deviceUID = md5(new DeviceUUID().get()).toString();
        //console.log(getOS(), " -- ", getBrowerType(), " ==== ", deviceDetector.device, ' ++++ ', deviceUID)
        // Call PHP to save data
        if(window.navigator.onLine == true) {
            var saveRequest = $.ajax({
                url: 'saveUsageStat.php?version=' + Math.random(), 
                type:'POST', 
                data:{'id' : sheet_Id, 'OS' : getOS(), 'Browser' : getBrowerType(), 'Device' : deviceDetector.device, 'DeviceId' : deviceUID}, 
                cache: false, 
                // async: false,
                success: function (response) {
                    console.log(response, " stat response")
                }
            })
        }
    } */
    /////////////////////////////////////////////////////////////////////////////////////////////////////
    // Default value
    document.getElementById('prevIcon').style.opacity = '0.5'
    document.getElementById('prevIcon').style.pointerEvents = 'none';
    document.getElementById('nextIcon').style.pointerEvents = 'auto';

    /////////////////////////////////////////////////////////////////////////////////////////////////////
    // Positioning of bottom container
    var standalone = (getUrlVars()["standalone"]) ? getUrlVars()["standalone"].split('/')[0] : 'false';
    if (standalone == 'true') {
        document.getElementById('bottomButtonLayer').style.setProperty("bottom","0vh");
        document.getElementById('spinnerBox').style.setProperty("padding-bottom","20vh");
        document.getElementById('ExitButtonPanel').style.setProperty("bottom","0vh");
    } else {
        document.getElementById('bottomButtonLayer').style.setProperty("bottom","9vh");
        document.getElementById('spinnerBox').style.setProperty("padding-bottom","27vh");
        document.getElementById('ExitButtonPanel').style.setProperty("bottom","9vh");
    }
    /////////////////////////////////////////////////////////////////////////////////////////////////////
    // For Testing Purpose
    // Root URL
    /* let rootURL = 'https://uncommonspublishing.com/app/floristry/steps-test' */
    /* let jasonPath = 'https://zapsheets.com/steps/' */

    //////////////////////////////////////////////////////////////////////////////////////////////////////
    // Checks if the internet is not active or down
    //if(window.navigator.onLine == false) {
        //document.getElementById("spinnerBox").style.display = 'none'
        //document.getElementById("loadingText").innerHTML += '<font color="red">Error: No active internet available.' + "</font><br>"

        // Exit Icon
        document.getElementById('ExitButtonPanel').style.display = 'block';
        document.getElementById('exitIcon').addEventListener('touchstart', onExitStart)
        document.getElementById('exitIcon').addEventListener('touchend', onExitClick)
        document.getElementById('exitIcon').addEventListener('mousedown', onExitStart)
        document.getElementById('exitIcon').addEventListener('mouseup', onExitClick)

        //console.log(document.getElementById('exitIcon'), " aaaa")
        //return;
    //}
    //////////////////////////////////////////////////////////////////////////////////////////////////////
    // Check the browser language if the language is not passed in querystring (&EN, &ES etc)
    // Default is "EN" if not language passed or the language sheet is not defined based on brower language

    // Previous
    //var activeLang = (document.location.search.substr(1).split('&')[1] != '' && document.location.search.substr(1).split('&')[1] != undefined) ? document.location.search.substr(1).split('&')[1] : navigator.language;
    //console.log(activeLang, " aL")
    var activeLang = (getUrlVars()["code"]) ? getUrlVars()["code"].split('/')[0].toUpperCase() : navigator.language.split('-')[0].toUpperCase();

    //console.log(activeLang, " aL")
    
    var sheet_Id = (getUrlVars()["id"]) ? getUrlVars()["id"].split('/')[0] : '';
    //var sheet_Id = '1FYSBRB9OUDpMWYMxRjfxOE-dpP9PyCc3JWBuCT9L2w8';

    // to get jump id
    var jumpId = (document.location.search.substr(1).split('&')[1] != '' && document.location.search.substr(1).split('&')[1] != undefined) ? document.location.search.substr(1).split('&')[1] : 'HOWTO';

    //console.log("ENTER -- ", sheet_Id)
    


    /////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} str 
     * @returns 
     */
    let isJSONData = str => {
        //if (typeof str === 'string'){
        try {
            let p = JSON.parse(str)
            return p
        } catch(e){
        }
        //}
        return false
    }
    /////////////////////////////////////////////////////////////////////////////////
    
    if(sheet_Id == '') {
        console.log('show Error screen')
        document.getElementById('loadingScreen').style.display = 'none';
        document.getElementById('sheetIdError').style.display = 'flex';
        document.getElementById('sheetIdBtn').addEventListener('touchstart', onCheckUserDataStart)
        document.getElementById('sheetIdBtn').addEventListener('touchend', onCheckUserDataClick)
        document.getElementById('sheetIdBtn').addEventListener('mousedown', onCheckUserDataStart)
        document.getElementById('sheetIdBtn').addEventListener('mouseup', onCheckUserDataClick)
        return;
    } else {
        let winLoc = window.location.href.split("?")[0];
        var browserLang = (getUrlVars()["code"]) ? getUrlVars()["code"].split('/')[0].toUpperCase() : navigator.language.split('-')[0].toLowerCase();
        window.history.replaceState({}, "null", (winLoc + "?code=" + browserLang.toLowerCase() +"&"+ jumpId + "&id=" + sheet_Id));

        loadSettingsData()
        enableButtons();
    }
    /////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} event 
     */
    function onCheckUserDataStart(event) {
        if(event != null) {event.preventDefault();}
        document.getElementById('sheetIdBtn').style.scale = '0.95'
    }
    /////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} event 
     */
    function onCheckUserDataClick(event) {
        if(event != null) {event.preventDefault();}
        console.log("check user data")
        document.getElementById('sheetIdBtn').style.scale = '1'
        checkUserFillData();

    }
    /////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @returns 
     */
    function getBrowerType() {
        let browserType = ''
        if ((navigator.userAgent.indexOf("Opera") || navigator.userAgent.indexOf('OPR')) != -1) {
          browserType = 'Opera';
          
        } else if (navigator.userAgent.indexOf("Edg") != -1) {
          browserType = 'Edge';
        } else if (navigator.userAgent.indexOf("Chrome") != -1) {
          browserType = 'Chrome';
        } else if (navigator.userAgent.indexOf("Safari") != -1) {
          browserType = 'Safari';
        } else if (navigator.userAgent.indexOf("Firefox") != -1) {
          browserType = 'Firefox';
        } else if ((navigator.userAgent.indexOf("MSIE") != -1) || (!!document.documentMode == true)) //IF IE > 10
        {
          browserType = 'IE';
        } else {
          browserType = 'unknown';
        }
        return browserType;
    }
    /////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @returns 
     */
    function getOS() {
        const userAgent = window.navigator.userAgent;
        const platform = window.navigator.platform;
        const macosPlatforms = ['Macintosh', 'MacIntel', 'MacPPC', 'Mac68K'];
        const windowsPlatforms = ['Win32', 'Win64', 'Windows', 'WinCE'];
        const iosPlatforms = ['iPhone', 'iPad', 'iPod'];
        let os = null;
        if (macosPlatforms.indexOf(platform) !== -1) {
            os = 'Mac OS';
        } else if (iosPlatforms.indexOf(platform) !== -1) {
            os = 'iOS';
        } else if (windowsPlatforms.indexOf(platform) !== -1) {
            os = 'Windows';
        } else if (/Android/.test(userAgent)) {
            os = 'Android';
        } else if (!os && /Linux/.test(platform)) {
            os = 'Linux';
        }
        return os;
    }
    /////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     */
    function checkUserFillData() {
        //console.log("Click")
        let user_name = ''
        //let codeType = ''
        //let jumpType = ''
        // Store the actual link of the app
        //let winLoc = window.location.href.split("?")[0]

        //console.log(window.location.href.split("?")[0])
        //codeType = window.location.href.split("?")[1] != undefined ? window.location.href.split("?")[1].split('&')[0] : 'HOWTO';

        //console.log(jumpId)

        let winLoc = window.location.href.split("?")[0];
        //jumpType = jumpId == '' ? 'HOWTO' : jumpId 
        //console.log(jumpType, " ---- ")
        // get the values from the search form
        let uSheetId = document.getElementById("usheetId").value
        //console.log(uSheetId.length, " len ", uSheetId)
        // For getting browser language
        var browserLang = (getUrlVars()["code"]) ? getUrlVars()["code"].split('/')[0].toUpperCase() : navigator.language.split('-')[0].toLowerCase();

        if(uSheetId != "") {
          // Mac Fix
          let splitParam = "https://docs.google.com/spreadsheets/d/"
          if(uSheetId.length > 30) {
            let correctURL = uSheetId.includes(splitParam)
            if(correctURL) {
              // Get Google Sheet url
              sheet_Id = uSheetId.split(splitParam)[1].split("/")[0]
            } else {
              sheet_Id = uSheetId
            }
            //console.log("AAAAAAA - IN ID")
            //showloader()
            setTimeout(function() {
                window.history.replaceState({}, "null", (winLoc + "?code=" + browserLang.toLowerCase() +"&"+ jumpId + "&id=" + sheet_Id));
                
                document.getElementById('loadingScreen').style.display = 'flex';
                document.getElementById('sheetIdError').style.display = 'none';
                setTimeout(function() {
                    loadSettingsData();
                    enableButtons();
                }, 100)
            }, 10)
            
          } 
        }
        //console.log(uSheetId, " --- ", user_name, " --- ")
      }

    //return;

    // Learn To Play / FullScreen instrcutions
    //var jumpId = (document.location.search.substr(1).split('&')[2] != '' && document.location.search.substr(1).split('&')[2] != undefined) ? document.location.search.substr(1).split('&')[2] : '';
    // New Changes
    //var jumpId = (document.location.search.substr(1).split('&')[1] != '' && document.location.search.substr(1).split('&')[1] != undefined) ? document.location.search.substr(1).split('&')[1] : 'HOWTO';
    //////////////////////////////////////////////////////////////////////////////////////////////////////

    //console.log(jumpId, " jumpId")
    //document.getElementById("loadingText").innerHTML += 'Loading: ' + activeLang.split('-')[0].toUpperCase() + " steps<br>";
    ////////////////////////////////////LANG SETTINGS START///////////////////////////////////////////////
    function loadSettingsData() {
        // Loading settings.json
        setTimeout(function() {
            var settingRequest = $.ajax({
                url: './sheets/' + sheet_Id + "/settings.json?version=" + Math.random(), 
                cache: false, 
                //async: false,
                type: 'GET',
                dataType: "text",
                success: function (response) {
                    //console.log(response, " READ DATA")
                    if(response.length == 0) {
                        document.getElementById("loadingText").innerHTML += '<font color="red">Error: Language data not available.' + "</font><br>"
                    } else { 
                        ////////////////////////////////////////////////////////////////////////////////////
                        ////////////////////////////////////////////////////////////////////////////////////
                        settingDataList = []
                        var mResponseSet = response.replace(/�/g, "") 
                        var newSettingData = eval(mResponseSet)
                        for(var i=0; i<newSettingData.length; i++) {
                            var settingDataSting = JSON.stringify(newSettingData[i]);
                            //console.log(isJSON(pp), " --- ")
                            //newstr += JSON.stringify(isJSON(pp))
                            if(isJSONData(settingDataSting) == false) {
                                document.getElementById("loadingText").innerHTML += '<font color="red">Error: Settings Sheet : (Row: ' + i + ")</font><br>"
                                updateInfoTextView()
                            } else {
                                settingDataList[i] = isJSONData(settingDataSting)
                            }
                        }
                        ////////////////////////////////////////////////////////////////////////////////////
                        /////////////////////LANG SETTINGS START////////////////////////////////////////////
                        // Store LazyLoadValue here
                        $.each(settingDataList, function (index_setting, row_setting) {
                            if(row_setting['Name'] == 'LazyLoad') {
                                if(row_setting['Value'] == '' ) {
                                    lazyLoadImages = 'FALSE'
                                } else {
                                    lazyLoadImages = row_setting['Value']
                                }
                            }
                            if(row_setting['Name'] == 'Version') {
                                document.getElementById('versionInfo').innerHTML = _version + " - " + row_setting["Value"] + " - " + activeLang;
                            }

                            /* if(row_setting['Name'] == 'OpenIn') {
                                if(row_setting['Value'] == '' || row_setting['Value'].toLowerCase() == 'browser') {
                                    openInType = 'browser'
                                    document.getElementById('homeIcon').style.display = 'none'
                                    //document.getElementById('homeIcon').style.opacity = '0'
                                } else {
                                    openInType = 'inline'
                                    document.getElementById('homeIcon').style.display = 'block'
                                    //document.getElementById('homeIcon').style.opacity = '1'
                                }
                            } */
                        })
                        //console.log(lazyLoadImages, " >>>>>")
                        ////////////////////////////////////////////////////////////////////////////////////
                        /////////////////////LANG SETTINGS START////////////////////////////////////////////
                        setTimeout(function() {
                            loadLanguageJSON()
                            //console.log('sheets/' + sheet_Id + "/steps_" + activeLang.split('-')[0].toLowerCase() + ".json?version=" + Math.random(), " >>url")
                            ////////////////////////////////////LANG LOAD START///////////////////////////////////////////////
                            // Loading steps json
                            /* var langRequest = $.ajax({
                            url: './sheets/' + sheet_Id + "/steps_" + activeLang.split('-')[0].toLowerCase() + ".json?version=" + Math.random(), 
                            cache: false, 
                            //async: false,
                            type: 'GET',
                            dataType: "text",
                            success: function (response) {
                                //console.log(response, " READ DATA")
                                if(response.length == 0) {
                                    //document.getElementById("loadingText").innerHTML += '<font color="red">Error: Language data not available.' + "</font><br>"
                                } else { 
                                    //////////////////////////////////////////////////////////////////////////////
                                    languageStepsData = []
                                    var mResponseLang = response.replace(/�/g, "") 
                                    var newLangData = eval(mResponseLang)
                                    for(var i=0; i<newLangData.length; i++) {
                                        var langDataSting = JSON.stringify(newLangData[i]);
                                        //console.log(isJSON(pp), " --- ")
                                        //newstr += JSON.stringify(isJSON(pp))
                                        if(isJSONData(langDataSting) == false) {
                                            document.getElementById("loadingText").innerHTML += '<font color="red">Error: ' + activeLang.split('-')[0] + ' Sheet : (Row: ' + i + ")</font><br>"
                                            updateInfoTextView()
                                        } else {
                                            languageStepsData[i] = isJSONData(langDataSting)
                                        }
                                    }
                    
                                    if(lazyLoadImages == "FALSE") {
                                        PreloadAllToCache();
                                    } else {
                                        jumpToStepScreen()
                                    }
                                    //console.log(languageDataList, " LDL")
                                //  document.getElementById('stepsScreen').style.display = 'block'
                                //     // Auto fill default sections
                                //     updateTopInstructionText(stepIndex)
                                }
                            },
                        })*/
                        ///////////////////
                        // Clear memory
                        /* langRequest.onreadystatechange = null;
                        langRequest.abort = null;
                        langRequest = null; */
                        ///////////////////
                        }, 500) 
                ///////////////////////////////////////////////////////////////////////////////////
                    }
                },
            })
            ///////////////////
            // Clear memory
            settingRequest.onreadystatechange = null;
            settingRequest.abort = null;
            settingRequest = null;
            ///////////////////
        }, 1000)
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////
    // Loading Language JSON
    function loadLanguageJSON() {
        //console.log('sheets/' + sheet_Id + "/steps_" + activeLang.split('-')[0].toLowerCase() + ".json?version=" + Math.random(), " >>url")
        ////////////////////////////////////LANG LOAD START///////////////////////////////////////////////
        // Loading steps json
        var langRequest = $.ajax({
            url: './sheets/' + sheet_Id + "/steps_" + activeLang.split('-')[0].toLowerCase() + ".json?version=" + Math.random(), 
            cache: false, 
            //async: false,
            type: 'GET',
            dataType: "text",
            success: function (response) {
                //console.log(response, " READ DATA")
                if(response.length == 0) {
                    //document.getElementById("loadingText").innerHTML += '<font color="red">Error: Language data not available.' + "</font><br>"
                    activeLang = "EN"
                    loadLanguageJSON()
                } else { 
                    //////////////////////////////////////////////////////////////////////////////
                    languageStepsData = []
                    var mResponseLang = response.replace(/�/g, "") 
                    var newLangData = eval(mResponseLang)
                    for(var i=0; i<newLangData.length; i++) {
                        var langDataSting = JSON.stringify(newLangData[i]);
                        //console.log(isJSON(pp), " --- ")
                        //newstr += JSON.stringify(isJSON(pp))
                        if(isJSONData(langDataSting) == false) {
                            document.getElementById("loadingText").innerHTML += '<font color="red">Error: ' + activeLang.split('-')[0] + ' Sheet : (Row: ' + i + ")</font><br>"
                            updateInfoTextView()
                        } else {
                            languageStepsData[i] = isJSONData(langDataSting)
                        }
                    }

                    // convert values to all defined format
                    if(lazyLoadImages == "FALSE" || lazyLoadImages == "False" || lazyLoadImages == "false" || lazyLoadImages == "0") {
                        //console.log('PreloadAllToCache')
                        PreloadAllToCache();
                    } else {
                        //console.log('jumpToStepScreen')
                        jumpToStepScreen()
                    }

                    //console.log(languageStepsData, " languageStepsData")
                    //console.log(languageDataList, " LDL")
                //  document.getElementById('stepsScreen').style.display = 'block'
                //     // Auto fill default sections
                //     updateTopInstructionText(stepIndex)
                }
            },
            error: function (response) {
                console.log("NO FILE FOUND")
                /* document.getElementById('loaderPre').style.display = 'none'
                // Reload the default language
                //document.getElementById("loadingText").innerHTML += '<font color="red">Error: Language data not available.' + "</font><br>"
                document.getElementById("loadingText").innerHTML += 'Error: Loading Language data' + "<br>Try again later." */
                activeLang = 'EN'
                loadLanguageJSON()
              }
            
        })
        ///////////////////
        // Clear memory
        langRequest.onreadystatechange = null;
        langRequest.abort = null;
        langRequest = null;
        ///////////////////
    }
    ////////////////////////////////////////////////////////////////////////////////////////////////

    //return;
    ////////////////////////////////////LANG SETTINGS START///////////////////////////////////////////////
   /*  setTimeout(function() {
    //console.log('sheets/' + sheet_Id + "/steps_" + activeLang.split('-')[0].toLowerCase() + ".json?version=" + Math.random(), " >>url")
    ////////////////////////////////////LANG LOAD START///////////////////////////////////////////////
    // Loading steps json
    var langRequest = $.ajax({
        url: './sheets/' + sheet_Id + "/steps_" + activeLang.split('-')[0].toLowerCase() + ".json?version=" + Math.random(), 
        cache: false, 
        //async: false,
        type: 'GET',
        dataType: "text",
        success: function (response) {
            //console.log(response, " READ DATA")
            if(response.length == 0) {
                document.getElementById("loadingText").innerHTML += '<font color="red">Error: Language data not available.' + "</font><br>"
            } else { 
                //////////////////////////////////////////////////////////////////////////////
                languageStepsData = []
                var mResponseLang = response.replace(/�/g, "") 
                var newLangData = eval(mResponseLang)
                for(var i=0; i<newLangData.length; i++) {
                    var langDataSting = JSON.stringify(newLangData[i]);
                    //console.log(isJSON(pp), " --- ")
                    //newstr += JSON.stringify(isJSON(pp))
                    if(isJSONData(langDataSting) == false) {
                        document.getElementById("loadingText").innerHTML += '<font color="red">Error: ' + activeLang.split('-')[0] + ' Sheet : (Row: ' + i + ")</font><br>"
                        updateInfoTextView()
                    } else {
                        languageStepsData[i] = isJSONData(langDataSting)
                    }
                }

                PreloadAllToCache();
                //console.log(languageDataList, " LDL")
                // document.getElementById('stepsScreen').style.display = 'block'
                // // Auto fill default sections
                // updateTopInstructionText(stepIndex)
            }
        },
    })
    ///////////////////
    // Clear memory
    langRequest.onreadystatechange = null;
    langRequest.abort = null;
    langRequest = null;
    ///////////////////
    }, 1000) */

    ////////////////////////////////////LANG LOAD END///////////////////////////////////////////////
    function enableButtons() {
        // Steps Buttons Events
        // Prev Icon click Event
        document.getElementById('prevIcon').addEventListener('touchstart', onPrevStart)
        document.getElementById('prevIcon').addEventListener('touchend', onPrevClick)
        document.getElementById('prevIcon').addEventListener('mousedown', onPrevStart)
        document.getElementById('prevIcon').addEventListener('mouseup', onPrevClick)

        // Next Icon Click Event
        document.getElementById('nextIcon').addEventListener('touchstart', onNextStart)
        document.getElementById('nextIcon').addEventListener('touchend', onNextClick)
        document.getElementById('nextIcon').addEventListener('mousedown', onNextStart)
        document.getElementById('nextIcon').addEventListener('mouseup', onNextClick)

        // Home Icon Click Event
        document.getElementById('homeIcon').addEventListener('touchstart', onHomeStart)
        document.getElementById('homeIcon').addEventListener('touchend', onHomeClick)
        document.getElementById('homeIcon').addEventListener('mousedown', onHomeStart)
        document.getElementById('homeIcon').addEventListener('mouseup', onHomeClick)

        // For view link icon click
        /* document.getElementById('stepBGInage').addEventListener('touchstart', onViewLinkStart)
        document.getElementById('stepBGInage').addEventListener('touchend', onViewLinkClick)
        document.getElementById('stepBGInage').addEventListener('mousedown', onViewLinkStart)
        document.getElementById('stepBGInage').addEventListener('mouseup', onViewLinkClick) */

        // View Icon event
        document.getElementById('viewIcon').addEventListener('touchstart', onViewLinkStart)
        document.getElementById('viewIcon').addEventListener('touchend', onViewLinkClick)
        document.getElementById('viewIcon').addEventListener('mousedown', onViewLinkStart)
        document.getElementById('viewIcon').addEventListener('mouseup', onViewLinkClick)

        // View Icon Text event
        document.getElementById('viewIconText').addEventListener('touchstart', onViewLinkStart)
        document.getElementById('viewIconText').addEventListener('touchend', onViewLinkClick)
        document.getElementById('viewIconText').addEventListener('mousedown', onViewLinkStart)
        document.getElementById('viewIconText').addEventListener('mouseup', onViewLinkClick)
    }

    /////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} event 
     */
    function onHomeStart(event) {
        event.preventDefault();
        //console.log("CHANGE COLOR")

        /////////////////////////////////////////////////////////////////////
        // undo 15/1/24
        //document.getElementById('homeIcon').style.scale = '0.95'

       
        //console.log("AAAAAA")
        doAnimateCloseButton('close');
        document.getElementById("base-timer-close").style.opacity = 1
        document.getElementById("base-timer-close").style.transition = "opacity 0.5s";
    }
    /////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} event 
     */
    function onPrevStart(event) {
        event.preventDefault();
        //console.log("CHANGE COLOR")
        document.getElementById('prevIcon').style.scale = '0.95'
    }
    /////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} event 
     */
    function onNextStart(event) {
        event.preventDefault();
        //console.log("CHANGE COLOR")

        // commented after adding pulse animation
        //document.getElementById('nextIcon').style.scale = '0.95'
        //document.getElementById('nextIcon').style.animation = 'none'

        // Pause pulsating effect
        document.getElementById('nextIcon').style.animationPlayState = "paused";
    }
    /////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} event 
     */
    function onViewLinkStart(event) {
        event.preventDefault();
        //console.log("CHANGE COLOR")
        //document.getElementById('viewText').style.color = 'rgb(0, 0, 0)'
        document.getElementById('viewIcon').style.scale = '0.95'
    }
    /////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} event 
     */
    function onViewLinkClick(event) {
        event.preventDefault();
        //console.log("CHANGE COLOR")
        //document.getElementById('viewText').style.color = 'rgb(41, 171, 226)'
        document.getElementById('viewIcon').style.scale = '1'
        console.log(activeViewLink, " >>>>")
        if(activeViewLink != '') {
            // open to new webpage
            window.open(activeViewLink, "_new")
            /* let hackWindow = window.open();
            hackWindow.location.href = activeViewLink */
        }
    }
    /////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} event 
     */
    function onExitStart(event) {
        event.preventDefault();
        document.getElementById('exitIcon').style.scale = '0.95'
    }
    /////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} event 
     */
    function onExitClick(event) {
        event.preventDefault();
        //console.log("EXIT BUTTON CLICK")
        document.getElementById('exitIcon').style.scale = '1'
        window.parent.postMessage(JSON.stringify({'message': 'closeFrame'}), '*')
    }

    /* // Auto fill default sections
    updateTopInstructionText(stepIndex) */
    /////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} event 
     * @returns 
     */
    function onPrevClick(event) {
        //console.log('Prev Click - ', stepIndex)
        event.preventDefault();

        document.getElementById('prevIcon').style.scale = '1'
        document.getElementById('prevIcon').style.pointerEvents = 'none';

        moveType = 'left'

        clearTimeout(autoPlay)

        /* let stepType = languageStepsData[stepIndex-1].Type;
        let stepDuration = languageStepsData[stepIndex-1].Duration; */

        if(languageStepsData[stepIndex-1] == undefined) {return}

        let stepType = languageStepsData[stepIndex-1].Type;
        let stepDuration = languageStepsData[stepIndex].Duration;
        let stepDurationPrev = languageStepsData[stepIndex-1].Duration;

        //console.log(stepType, " --- ", stepDuration)
        if(typeof(stepDuration) == 'string') {
            return;
        }
        if(stepType != '' && stepType != 'Loading' && typeof(stepDurationPrev) != 'string') {
            /* if(languageStepsData[stepIndex-1].Duration == '') {
                //stepIndex++;
                return
            } */
            //if(languageStepsData[stepIndex-1].Duration == '') {
                //stepIndex++;
            //} else {
            ///////////////////////////////////////////
            // Storing Next/Active Image to compare
            //prevImage = languageStepsData[stepIndex].Image;
            //newImage = languageStepsData[stepIndex-1].Image

            // New Logic with index
            prevImage = getActiveAndNextImage(stepIndex)
            newImage = getActiveAndNextImage(stepIndex-1)

            // Only for test
            // Dec 4
            /* $("#stepBGInage").animate({
                "left": "-50%"
            }, 0); */

            ///////////////////////////////////////////
            // 9/1/25
            /* if(prevImage != newImage) {
                $("#stepBGInage").fadeOut();
            }
            $("#stepText").fadeOut(); */
            ///////////////////////////////////////////

            /* moveType = 'left'
            $("#stepText").fadeOut();
            // For Next
            $( "#stepBGInage" ).animate({
                left: "150%",
                opacity: '0'
              }, {
                duration: 500,
                specialEasing: {
                  width: "linear",
                  //height: "easeOutBounce"
                },
            }) */

            ///////////////////////////////////////////
            //}
            setTimeout(function() {
                /* let stepID = languageStepsData[stepIndex-1].ID;
                stepIndex = getIndexUsingID(stepID); */
                stepIndex--;

                // To jump
                if(typeof(languageStepsData[stepIndex].Duration) == 'string') {
                    //console.log("INCREASING INDEX")
                    stepIndex--;
                }

                // 9/1/25
                //updateTopInstructionText(stepIndex)
                updateMiddleImageSection(stepIndex)
            }, 500)
        } else {
            //console.log("SECTION END")

            //console.log("NO TYPE found")
            let prevStep = languageStepsData[stepIndex].Prev;
            if(prevStep == 'END') {
                console.log("EXIT FROM PREV")
                window.parent.postMessage(JSON.stringify({'message': 'closeFrame'}), '*')
            }
            
            /* stepIndex++;
            updateTopInstructionText(stepIndex) */
        }
        //console.log(stepType, " CHECKING ", stepIndex)

        return;

        ///////////////////////////////////////////////////////////////////////////////
        if(stepIndex == 0) {return}
        //if(languageStepsData[stepIndex].Type == 'END') {return}
        $("#stepBGInage").fadeOut();
        $("#stepText").fadeOut();

        setTimeout(function() {
            let stepID = languageStepsData[stepIndex].Prev;
            if(stepID != '') {
                //console.log(getIndexUsingID(stepID))
                stepIndex = getIndexUsingID(stepID);
                updateTopInstructionText(stepIndex)
            } else {
                stepIndex--;
                updateTopInstructionText(stepIndex)
            }
        }, 500 )
    }
    ///////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} event 
     * @returns 
     */
    function onPrevClick_WRK(event) {
        //console.log('Prev Click - ', stepIndex)
        event.preventDefault();
        if(stepIndex == 0) {return}
        //if(languageStepsData[stepIndex].Type == 'END') {return}
        $("#stepBGInage").fadeOut();
        $("#stepText").fadeOut();

        setTimeout(function() {
            let stepID = languageStepsData[stepIndex].Prev;
            if(stepID != '') {
                //console.log(getIndexUsingID(stepID))
                stepIndex = getIndexUsingID(stepID);
                updateTopInstructionText(stepIndex)
            } else {

                /* let prevStep = languageStepsData[stepIndex].Prev;
                if(prevStep != 'END') {
                    console.log("EXIT")
                    window.parent.postMessage(JSON.stringify({'message': 'closeFrame'}), '*')
                } */

                stepIndex--;
                updateTopInstructionText(stepIndex)
            }
        }, 500 )
    }
    /////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} id 
     * @returns
     * Find index of elements from json using ID 
     */
    function getIndexUsingID(id) {
        let _id = -1;
        //console.log(languageStepsData)
        for(var i=0; i<languageStepsData.length; i++) {
            if(id == languageStepsData[i].ID) {
                _id = i
            }
        }
        return _id
    }
    /////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} event 
     * @returns 
     */
    function onNextClick(event=null) {
        if(event != null) {event.preventDefault();}

        // commented after adding pulse animation
        //document.getElementById('nextIcon').style.scale = '1'
        //document.getElementById('nextIcon').style.animation = 'pulse-animation 2s infinite;'

        //console.log("Next Click")
        clearTimeout(autoPlay)

        document.getElementById('nextIcon').style.pointerEvents = 'none';

        // Restart pulsating effect after pause
        document.getElementById('nextIcon').style.animationPlayState = "running";
        

        moveType = 'right'


        let stepType = languageStepsData[stepIndex+1].Type;
        /* let stepType = languageStepsData[stepIndex].Type; */
        //console.log(stepType, " ---- ONC ")
        if(stepType != '' && stepType != 'Loading') {
            ///////////////////////////////////////////
            // Only for test
            ///////////////////////////////////////////

            //console.log(languageStepsData[stepIndex].Image, " Active")
            //console.log(languageStepsData[stepIndex+1].Image, " NEXT")

            // Storing Next/Active Image to compare
            /* prevImage = languageStepsData[stepIndex].Image;
            newImage = languageStepsData[stepIndex+1].Image */

            // New Logic with index
            prevImage = getActiveAndNextImage(stepIndex)
            newImage = getActiveAndNextImage(stepIndex+1)

            //console.log(prevImage, " === ", newImage)

            /* $("#stepBGInage").animate({
                "left": "150%"
            }, 0); */
            // Dev 4

            //////////////////////////////////////////////////////
            // 9/1/25
            // Undo later on
           /*  if(prevImage != newImage) {
                $("#stepBGInage").fadeOut();
            }
            $("#stepText").fadeOut(); */
            //////////////////////////////////////////////////////

            /* $("#stepText").fadeOut();
            moveType = 'right'

            // For Next
            $( "#stepBGInage" ).animate({
                left: "-50%",
                opacity: '0'
              }, {
                duration: 500,
                specialEasing: {
                  width: "linear",
                  //height: "easeOutBounce"
                },
                // complete: function(){
                //     alert('end ani');
                // }
            }) */

            ///////////////////////////////////////////
            setTimeout(function() {
            /* let stepID = languageStepsData[stepIndex+1].ID;
            stepIndex = getIndexUsingID(stepID); */

            stepIndex++
             // To jump
            if(typeof(languageStepsData[stepIndex].Duration) == 'string') {
                //console.log("INCREASING INDEX")
                stepIndex++;
            }

            // 9/1/25
            //updateTopInstructionText(stepIndex)
            updateMiddleImageSection(stepIndex)
            document.getElementById('prevIcon').style.opacity = '1'
            document.getElementById('nextIcon').style.opacity = '1'

            //stepIndex++
            }, 500)
        } else {
            //console.log("NO TYPE found")
            let nextStep = languageStepsData[stepIndex].Next;
            if(nextStep == 'END') {
                console.log("EXIT FROM NEXT")
                window.parent.postMessage(JSON.stringify({'message': 'closeFrame'}), '*')
            } else {
               /*  $("#stepBGInage").fadeOut();
                $("#stepText").fadeOut();
                setTimeout(function() {
                    stepIndex++
                    updateTopInstructionText(stepIndex)
                }, 500) */
            }
            //stepIndex--;
        }
        //console.log(stepType, " CHECKING ", stepIndex)

        return
        //////////////////////////////////////////////////////////////////////////////

        let stepID = languageStepsData[stepIndex].Next;
        if(stepID == "END") {return}
        // Transition
       /*  $("#stepText").fadeOut(); */
        $("#stepBGInage").fadeOut();
        $("#stepText").fadeOut();

        setTimeout(function() {

            if(jumpId != '') {
                //console.log("111")
                //let stepID = languageStepsData[stepIndex+1].Next;
                let stepID = languageStepsData[stepIndex].Next;
                //console.log(stepID, " ---- ")
                if(stepID == "END") {return}
                if(stepID != '') {
                    //console.log(getIndexUsingID(stepID))
                    stepIndex = getIndexUsingID(stepID);
                    updateTopInstructionText(stepIndex)
                } else {
                    if(languageStepsData[stepIndex+1].Type != 'Step') {return}
                    //if(languageStepsData[stepIndex+1].Type == 'END') {return}
                    stepIndex++;
                    /* console.log(stepIndex, " Index") */
                    updateTopInstructionText(stepIndex)
                }
            } else {

                //console.log('22222')

                if(languageStepsData[stepIndex+1].Type != 'Step') {return}
                //if(languageStepsData[stepIndex+1].Type == 'END') {return}
                /* stepIndex++; */
                //console.log(languageStepsData[stepIndex+1].Type, " ---- ")
                if(languageStepsData[stepIndex+1].Type == 'Step') {
                    
                    let stepID = languageStepsData[stepIndex+1].Next;
                    if(stepID == "END") {return}
                    if(stepID != '') {
                        //console.log(getIndexUsingID(stepID))
                        stepIndex = getIndexUsingID(stepID);
                        updateTopInstructionText(stepIndex)
                    } else {
                        stepIndex++;
                        /* console.log(stepIndex, " Index") */
                        updateTopInstructionText(stepIndex)
                    }
                    //updateTopInstructionText(stepIndex)
                } else {
                    stepIndex--;
                    /* stepIndex = 0
                    updateTopInstructionText(stepIndex) */
                    //document.getElementById('stepsScreen').style.display = 'none'
                    /* inSteps = false; */
                    /* document.getElementById('useMode').style.display = 'none'
                    document.getElementById('loadingScreen').style.display = 'none'
                    $(".bodyWrapper.oddPage").css({display:"flex"}) */
                    // Calling Periodically
                    //fetchAppDetailsPeriodically(event) 
                }
            }
        }, 500)

        //console.log(languageStepsData[stepIndex].Type, " TYPE ", stepIndex)
    }
    //////////////////////////////////////////////////////////////////////////////////////
    function getActiveAndNextImage(imgIndex) {
        let ImgPath = ''
        if(languageStepsData[imgIndex].Image != '') {
            let imagePath = ''
            if (languageStepsData[imgIndex]['Image'].includes("https://drive.google.com")) {
                let imgid = languageStepsData[imgIndex]['Image'].split('https://drive.google.com')[1].split('/')[3];
                let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                
                // Cache Image
                //imagePath = 'img/cacheImages/' + imgid + '.png';
                // cacheImages moved to spreadsheet id folder
                imagePath = './sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png';
                
            } else {
                // Cache Image
                let name = languageStepsData[imgIndex]['Image'].split('/')
                let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                // New Changes
                //imagePath = 'img/cacheImages/' + imageName
                // cacheImages moved to spreadsheet id folder
                imagePath = './sheets/' + sheet_Id + '/cacheImages/' + imageName;
            }
            ImgPath = imagePath
        } else {
            ImgPath = ''
        }

        return ImgPath;
    }
    //////////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} event 
     * @returns 
     */
    function onNextClick_WRK(event=null) {
        if(event != null) {event.preventDefault();}
        //console.log("Next Click")
        clearTimeout(autoPlay)

        let stepID = languageStepsData[stepIndex].Next;
        if(stepID == "END") {return}

        // Transition
       /*  $("#stepText").fadeOut(); */
        ///////////////////////////////////////////
        // Only for test
        $("#stepBGInage").fadeOut();
        $("#stepText").fadeOut();
        ///////////////////////////////////////////

        setTimeout(function() {

            if(jumpId != '') {
                //console.log("111")
                //let stepID = languageStepsData[stepIndex+1].Next;
                let stepID = languageStepsData[stepIndex].Next;
                //console.log(stepID, " ---- ")
                if(stepID == "END") {return}
                if(stepID != '') {
                    //console.log(getIndexUsingID(stepID))
                    stepIndex = getIndexUsingID(stepID);
                    updateTopInstructionText(stepIndex)
                } else {
                    if(languageStepsData[stepIndex+1].Type != 'Step') {return}
                    //if(languageStepsData[stepIndex+1].Type == 'END') {return}
                    stepIndex++;
                    /* console.log(stepIndex, " Index") */
                    updateTopInstructionText(stepIndex)
                }
            } else {

                //console.log('22222')

                if(languageStepsData[stepIndex+1].Type != 'Step') {return}
                //if(languageStepsData[stepIndex+1].Type == 'END') {return}
                /* stepIndex++; */
                //console.log(languageStepsData[stepIndex+1].Type, " ---- ")
                if(languageStepsData[stepIndex+1].Type == 'Step') {
                    
                    let stepID = languageStepsData[stepIndex+1].Next;
                    if(stepID == "END") {return}
                    if(stepID != '') {
                        //console.log(getIndexUsingID(stepID))
                        stepIndex = getIndexUsingID(stepID);
                        updateTopInstructionText(stepIndex)
                    } else {
                        stepIndex++;
                        /* console.log(stepIndex, " Index") */
                        updateTopInstructionText(stepIndex)
                    }
                    //updateTopInstructionText(stepIndex)
                } else {
                    stepIndex--;
                    //////////////////////////////////////////////////////////////////////
                    /* stepIndex = 0
                    updateTopInstructionText(stepIndex) */
                    //document.getElementById('stepsScreen').style.display = 'none'
                    /* inSteps = false; */
                    /* document.getElementById('useMode').style.display = 'none'
                    document.getElementById('loadingScreen').style.display = 'none'
                    $(".bodyWrapper.oddPage").css({display:"flex"}) */
                    // Calling Periodically
                    //fetchAppDetailsPeriodically(event)
                    //////////////////////////////////////////////////////////////////////
                }
            }
        }, 500)

        //console.log(languageStepsData[stepIndex].Type, " TYPE ", stepIndex)
    }
    /////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @returns 
     */
    function checkAutoPlayStat() {
        //console.log(languageStepsData[stepIndex].Duration, " Duration check")
        let autoPlayTimer = languageStepsData[stepIndex].Duration
        if(autoPlayTimer == 0 || autoPlayTimer == '') {return}
        autoPlay = setTimeout(function() {
            clearTimeout(autoPlay)
            onNextClick(null)
        }, autoPlayTimer * 1000)
    }
    /////////////////////////////////////////////////////////////////////////////////
    function EnableCloseEvents() {
        document.getElementById('homeIcon').addEventListener('touchstart', onHomeStart)
        document.getElementById('homeIcon').addEventListener('touchend', onHomeClick)
        document.getElementById('homeIcon').addEventListener('mousedown', onHomeStart)
        document.getElementById('homeIcon').addEventListener('mouseup', onHomeClick)
    }
    /**
     * 
     * @param {*} event 
     */
    function onHomeClick(event) {
        event.preventDefault();

        /* document.getElementById('homeIcon').removeEventListener('touchstart', onHomeStart)
        document.getElementById('homeIcon').removeEventListener('touchend', onHomeClick)
        document.getElementById('homeIcon').removeEventListener('mousedown', onHomeStart)
        document.getElementById('homeIcon').removeEventListener('mouseup', onHomeClick) */

        //reset();

        document.getElementById('homeIcon').style.scale = '1'

        ////////////////////////////////////////////////////////////////////////////////
        //console.log(window.frameElement, " iFrame ", window.parent)
        //window.parent['HideIFrame']();
        // Close parent iFrame
        /* window.parent.top.document.getElementById('content').style.display = 'none'
        window.parent.top.document.getElementById('content').src = '' */
        ////////////////////////////////////////////////////////////////////////////////

        ////////////////////////////////////////////////////////////////////////////////
        document.getElementById("base-timer-close").style.opacity = 0
        document.getElementById("base-timer-close").style.transition = "opacity 0.5s";
    
        // Circular anim issue fix [iOS]
        /* if(timeLeft == 0 || timeLeft == 3) {
            //window.parent.postMessage(JSON.stringify({'message': 'closeFrame'}), '*')  
            return
        } else { */
        reset();
            window.parent.postMessage(JSON.stringify({'message': 'toggleFrame'}), '*')
            
        /* } */
        
        ////////////////////////////////////////////////////////////////////////////////

        ////////////////////////////////////////////////////////////////////////////////
        // Post message to parent window
        //window.parent.postMessage(JSON.stringify({'message': 'closeFrame'}), '*')
        ////////////////////////////////////////////////////////////////////////////////

        ////////////////////////////////////////////////////////////////////////////////
        //let loadedFrame = window.frameElement.id; 
        //document.getElementById(loadedFrame).style.display = 'none'
        //console.log("Home Click")
       /*  if(jumpId != '') {
            stepIndex = getIndexUsingID(jumpId)
        } else {
            stepIndex = 0;
        }
        updateTopInstructionText(stepIndex) */

        /* setTimeout(function() {
            EnableCloseEvents()
        }, 1000) */
        

    }
    /////////////////////////////////////////////////////////////////////////////////
    /* Function to animate height: auto */
    function autoHeightAnimate(element, time){
        var curHeight = element.height(), // Get Default Height
        autoHeight = element.css('height', 'auto').height(); // Get Auto Height
            element.height(curHeight); // Reset to Default Height
            element.stop().animate({ height: autoHeight }, time); // Animate to Auto Height
    }
    /////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} index 
     */
    function updateTopInstructionText(index) {
        //console.log(languageDataList[index].Text)
        /* $("#stepText").fadeIn();
        $("#stepBGInage").fadeIn(); */


        

        // Undo later on
        document.getElementById('stepText').innerHTML = languageStepsData[index].Text
        // Check whether to display top bar or not

      
        ///////////////////////////////////////////////////////////////////////////////
        //console.log(languageStepsData[index].Text, " ...")
        // undo If needed
        activeSlideIndex = index;
        if(languageStepsData[index].Text != '') {
            document.getElementById('topStepBar').style.display = 'flex' 
        } else {
            document.getElementById('topStepBar').style.display = 'none'
        }
        ///////////////////////////////////////////////////////////////////////////////


        //animateTime = 500
        //if($('#topStepBar').height() === 0){
            //autoHeightAnimate($('#topStepBar'), animateTime);
        //} else {
            //nav.stop().animate({ height: '0' }, animateTime);
        //}


        // 9/1/25
        // checking current / next imagePath
        //updateMiddleImageSection(index)
    }
    /////////////////////////////////////////////////////////////////////////////////
    /**
     * {} to animate top gray bar
     */
    function animateTopBorder() {
        let animationPosition = 45
        let showViewLink = languageStepsData[stepIndex].ViewLink;
        if(showViewLink != '') {
            let checkshowLinkText = showViewLink.split(',');
            if(checkshowLinkText[0].includes("http") == false) {
                animationPosition = 65;
            } else {
                animationPosition = 60
            }
        } else {
            animationPosition = 45
        }

        var el = $('#topStepBar'),
        curHeight = el.height(),
        autoHeight = el.css('height', 'auto').height();
        //el.height(curHeight).animate({height: (autoHeight + 50)}, 300); // 70
        el.height(curHeight).animate({height: (autoHeight + animationPosition)}, 300);
    }
    /////////////////////////////////////////////////////////////////////////////////
    /**
     * Typewriter effect
     */
    function typeWriter() {
        //console.log(letterIndex, " --- ", letters)
        if (letterIndex < letters.length) {
          document.getElementById("stepText").innerHTML += letters.charAt(letterIndex);
          letterIndex++;
          setTimeout(typeWriter, 30);
        }
    }
    /////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} index 
     */
    function updateMiddleImageSection(index) {
        /* console.log(languageStepsData[index].Image, " AAAAAA")
        return; */

        ////////////////////////////////////////////////////////////////////////////
        /* if(moveType == 'right') {
            // console.log('Move NEXT')
            // console.log(languageStepsData[index-1].Image, " prev")
            // console.log(languageStepsData[index].Image, " next")
            // prevImage = languageStepsData[index+1].Image;
            // newImage = languageStepsData[index].Image;
        } else {
            // console.log("Move PREV")
            // console.log(languageStepsData[index+1].Image, " prev")
            // console.log(languageStepsData[index].Image, " next")
            // prevImage = languageStepsData[index+1].Image;
            // newImage = languageStepsData[index].Image;
            // console.log(languageStepsData[index+1].Image, " prev")
            // console.log(languageStepsData[index].Image, " next")
        } */
        ////////////////////////////////////////////////////////////////////////////

        if(languageStepsData[index].Image != '') {
            let imagePath = ''
            if (languageStepsData[index]['Image'].includes("https://drive.google.com")) {
                let imgid = languageStepsData[index]['Image'].split('https://drive.google.com')[1].split('/')[3];
                let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                // Cache Image
                //imagePath = 'img/cacheImages/' + imgid + '.png?version=' + Math.random();
                // cacheImages moved to spreadsheet id folder
                imagePath = './sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();
            } else {
                // Cache Image
                let name = languageStepsData[index]['Image'].split('/')
                let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                // New Changes
                //imagePath = 'img/cacheImages/' + imageName + "?version=" + Math.random();
                // cacheImages moved to spreadsheet id folder
                imagePath = './sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
            }
           /*  document.getElementById('stepBGInage').src = imagePath; */
            ///console.log(preCacheImages[index], " = ImagePath = ", imagePath)

           

            if(preCachedDone == false) {
                //console.log("AAAAA")
                /* document.getElementById('topStepBar').style.display = 'none' */
                //document.getElementById('spinnerMiddleBox').style.display = 'block'
                /* console.log("NOT IN CACHE...")
                document.getElementById('stepBGInage').src = '' */

                // Use this for fast processing
                /* document.getElementById('stepBGInage').src = imagePath;
                document.getElementById('stepBGInage').onload = moveToSlide() */

                // Use in case of slow internet
                loadImage(imagePath)
            } else {

                /* 
                //console.log("Picking up image")
                document.getElementById('stepBGInage').src = preCacheImages[index].src;
                //document.getElementById('stepBGInage').src = ''
                //loadImage(imagePath)
                document.getElementById('stepBGInage').onload = moveToSlide() */

                // Use in case of slow internet
                loadImage(preCacheImages[index].src)


            }
            /////////////////////////////////////////////////////////////////////////////////
            //document.getElementById('stepBGInage').onload = moveToSlide()
            /*//console.log(document.getElementById('stepBGInage').complete, " CCCCCCCCCCCCCCCC...............")
            document.getElementById('stepBGInage').onprogress = function(e) {
                console.log(e.loaded, " ............ ", e.total)
            } */
           ///////////////////////////////////////////////////////////////////////////////////
            
        } else {
            document.getElementById('stepBGInage').src = '' 
            moveToSlide()
        }
        /////////////////////////////////////////////////////////////////////////////////////////
        /**
         * 
         */
        function moveToSlide1() {
            
            //console.log("IMG LOADED...")

            setTimeout(function() {
                //console.log(languageStepsData[stepIndex+1], " data ", languageStepsData[stepIndex])
                ///////////////////////////////////////////
                // Only for test
                // Transition
                // Fade in the BG image
                // Dev 4
                ////////////////////////////////////////////////////////////////
                // jQuery Effects
                //$( "#stepText" ).effect( "clip", "fast" );
                ////////////////////////////////////////////////////////////////

                ////////////////////////////////////////////////////////////////
                // Left Right sliding
                /* if(moveType == 'right') {
                    $('#stepText').show("slide", { direction: "right" }, 700);
                } else {
                    $('#stepText').show("slide", { direction: "left" }, 700);
                }

                $("#stepBGInage").animate({
                    "left": "50%"
                }, 500); */
                ////////////////////////////////////////////////////////////////
                //document.getElementById('spinnerMiddleBox').style.display = 'none'
                ////////////////////////////////////////////////////////////////
                $("#stepText").fadeIn();
                ////////////////////////////////////////////////////////////////
                //console.log(prevImage, " === ", newImage)
                //if(prevImage != newImage) {
                $("#stepBGInage").fadeIn();

                ////////////////////////////////////////////////////////////////
                if(prevImage == '' || newImage == '') {
                    setTimeout(function() {
                        // Smooth transition
                        // Transition Top Gray Bar
                        /* var el = $('#topStepBar'),
                        curHeight = el.height(),
                        autoHeight = el.css('height', 'auto').height();
                        console.log(curHeight, " --- ", autoHeight)
                        el.height(curHeight).animate({height: (autoHeight + 30)}, 250); */
                        animateTopBorder()
                    }, 700)
                } else {
                    animateTopBorder()
                    ///////////////////////////////////////////////////////////
                    // Typewriter effect
                    /* letterIndex = 0
                    letters = ''
                    setTimeout(function() {
                        letters = document.getElementById("stepText").innerHTML
                        document.getElementById("stepText").innerHTML = ''
                        typeWriter()
                    }, 50) */
                    ///////////////////////////////////////////////////////////
                }
                ////////////////////////////////////////////////////////////////
                //}
                ///////////////////////////////////////////
                /* $("#stepText").fadeIn();
                if(moveType == 'right') {
                    $( "#stepBGInage" ).animate({
                        left: "150%",
                        opacity: '0'
                    }, {
                        duration: 0,
                        specialEasing: {
                        width: "linear",
                        //height: "easeOutBounce"
                        },
                    })
                    // Move to correct pos
                    $( "#stepBGInage" ).animate({
                        left: "50%",
                        opacity: '1'
                    }, {
                        duration: 500,
                        specialEasing: {
                        width: "linear",
                        //height: "easeOutBounce"
                        },
                    })
                } else {
                    $( "#stepBGInage" ).animate({
                        left: "-50%",
                        opacity: '0'
                    }, {
                        duration: 0,
                        specialEasing: {
                        width: "linear",
                        //height: "easeOutBounce"
                        },
                    })
                    // Move to correct pos
                    $( "#stepBGInage" ).animate({
                        left: "50%",
                        opacity: '1'
                    }, {
                        duration: 500,
                        specialEasing: {
                        width: "linear",
                        //height: "easeOutBounce"
                        },
                    })
                } */
                /////////////////////////////////////////////////////////////////////////////
                document.getElementById('prevIcon').style.pointerEvents = 'auto';
                document.getElementById('nextIcon').style.pointerEvents = 'auto';
                /////////////////////////////////////////////////////////////////////////////
                // Trying New Logic
            /*  console.log("AAAAA - ", stepIndex)
                let nextStepType = languageStepsData[stepIndex+1].Type;
                let prevStepType = languageStepsData[stepIndex-1].Type;
                let curStepType = languageStepsData[stepIndex].Type;
                let nextNext = languageStepsData[stepIndex+1].Next;
                let PrevPrev = languageStepsData[stepIndex-2].Prev;
                console.log(nextStepType, "-- nextstep --", nextNext, " prev --", prevStepType, " :: ", PrevPrev)
                // if(nextStepType != 'Step' || curStepType != 'Step') {
                //     document.getElementById('prevIcon').style.opacity = '0.5'
                //     document.getElementById('prevIcon').style.pointerEvents = 'none';
                //     document.getElementById('nextIcon').style.opacity = '0.5'
                //     document.getElementById('nextIcon').style.pointerEvents = 'none';
                // } 
                return; */
                /////////////////////////////////////////////////////////////////////////////



                // Check Type value
                /* let stepType = languageStepsData[stepIndex].Type;
                console.log(stepType, " AFTER ANIM VALUE ", stepIndex)
                if(stepType != 'Step') {
                    document.getElementById('prevIcon').style.opacity = '0.5'
                    document.getElementById('nextIcon').style.opacity = '0.5'
                    document.getElementById('prevIcon').style.pointerEvents = 'none';
                    document.getElementById('nextIcon').style.pointerEvents = 'none';
                } else { */

                    ///////////////////////////////////////////////////////////////////////
                    /* console.log(languageStepsData[stepIndex-1], " CCC")
                    return; */
                    
                    //console.log(languageStepsData[stepIndex-1], " CCC")

                    ///////////////////////////////////////////////////////////////////////
                    if(languageStepsData[stepIndex-1] != undefined) {
                        let stepDuration = languageStepsData[stepIndex-1].Duration;
                        if(typeof(stepDuration) == 'string') {
                            document.getElementById('prevIcon').style.opacity = '0.5'
                            document.getElementById('prevIcon').style.pointerEvents = 'none';
                            

                            document.getElementById('nextIcon').style.opacity = '1'
                            document.getElementById('nextIcon').style.pointerEvents = 'auto';
                        }

                        //////////////////////////////////////////////////////////////////////
                        let curType = languageStepsData[stepIndex].Type;
                        //console.log(curType, " >>>>curType")
                        let curDuration = languageStepsData[stepIndex].Duration;
                        //console.log(curType, " >>>>curType")

                        let nextType = languageStepsData[stepIndex+1].Type;
                        //console.log(nextType, " nextType")
                        let nextStep = languageStepsData[stepIndex].Next;
                        //console.log(nextStep, " nextStep")

                        if(nextType != 'Step' && nextStep == '') {
                            /* document.getElementById('prevIcon').style.opacity = '0.5'
                            document.getElementById('prevIcon').style.pointerEvents = 'none'; */

                            document.getElementById('nextIcon').style.opacity = '0.5'
                            document.getElementById('nextIcon').style.pointerEvents = 'none';
                        } else {
                        /*  document.getElementById('prevIcon').style.opacity = '1'
                            document.getElementById('prevIcon').style.pointerEvents = 'auto'; */

                            document.getElementById('nextIcon').style.opacity = '1'
                            document.getElementById('nextIcon').style.pointerEvents = 'auto';
                        }

                        //////////////////////////////////////////////////////////////////////

                        /* if(curType != 'Step' && typeof(curDuration) == 'number') {
                            document.getElementById('nextIcon').style.opacity = '0.5'
                            document.getElementById('nextIcon').style.pointerEvents = 'none';
                        } else {
                            document.getElementById('nextIcon').style.opacity = '1'
                            document.getElementById('nextIcon').style.pointerEvents = 'auto';
                        } */


                        //////////////////////////////////////////////////////////////////////
                    
                        let prevType = languageStepsData[stepIndex-1].Type;
                        let prevStep = languageStepsData[stepIndex].Prev;

                        /* console.log(prevStep, " prevStep ", prevType)
                        if(nextStep != 'Step' && prevStep == 'END') {
                            console.log("ENTER HERE")
                        } else */ 
                        if(prevType != 'Step' && prevStep == '') {
                            document.getElementById('prevIcon').style.opacity = '0.5'
                            document.getElementById('prevIcon').style.pointerEvents = 'none';
                        } else {
                            document.getElementById('prevIcon').style.opacity = '1'
                            document.getElementById('prevIcon').style.pointerEvents = 'auto';
                        }
                        ///////////////////////////////////////////////////////////////////////
                        // Checing current type value
                        // Disable all buttons
                        /* let curType = languageStepsData[stepIndex].Type;
                        console.log(curType, " >>>>curType")
                        if(curType != 'Step') {
                            document.getElementById('prevIcon').style.opacity = '0.5'
                            document.getElementById('prevIcon').style.pointerEvents = 'none';
                            document.getElementById('nextIcon').style.opacity = '0.5'
                            document.getElementById('nextIcon').style.pointerEvents = 'none';
                        } else {
                            document.getElementById('nextIcon').style.opacity = '1'
                            document.getElementById('nextIcon').style.pointerEvents = 'auto';
                        } */
                        ///////////////////////////////////////////////////////////////////////
                        /* let curType = languageStepsData[stepIndex].Type;
                        console.log(curType, " >>>>curType") */

                        /* if(languageStepsData[stepIndex-1].Type != 'Step') {
                            document.getElementById('nextIcon').style.opacity = '0.5'
                            document.getElementById('nextIcon').style.pointerEvents = 'none';
                        } */
                        ///////////////////////////////////////////////////////////////////////
                        // New Changes
                        if(typeof(languageStepsData[stepIndex-1].Duration) == 'string' && languageStepsData[stepIndex].Prev == '') {
                            //stepIndex++;
                            document.getElementById('prevIcon').style.opacity = '0.5'
                            document.getElementById('prevIcon').style.pointerEvents = 'none';
                        } else {
                            document.getElementById('prevIcon').style.opacity = '1'
                            document.getElementById('prevIcon').style.pointerEvents = 'auto';
                        }

                        if(nextStep == 'END') {
                            //document.getElementById('nextIcon').style.opacity = '0.5'
                            //console.log("CLOSE THE APP")
                        } else {
                            //document.getElementById('nextIcon').style.opacity = '1'
                            //document.getElementById('nextIcon').style.opacity = '0.5'
                        }
                    }
            /*  } */
                checkAutoPlayStat();
            }, 10)
           /*  }, 0) */
        }
        /* }, 150) */

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
    /////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} str 
     * @returns 
     */
    /* let isJSONData = str => {
        //if (typeof str === 'string'){
        try {
            let p = JSON.parse(str)
            return p
        } catch(e){
        }
        //}
        return false
    } */
    //////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     */
    function PreloadAllToCache() {
        //console.log("aaa")
        var imgLoaded = 0

        ////////////////////////////////////////////////////////
        // Caching settings images
        //console.log(getAllImageCount(), " >>>>>")

        if(getAllImageCount() > 0) {
            /* $.each(settingDataList, function (index_setting, row_setting) {
                if(row_setting['Name'] == 'BackgroundImage' || row_setting['Name'] == 'SplashImageUrl' || row_setting['Name'] == 'PrevButtonUrl' || row_setting['Name'] == 'NextButtonUrl' || row_setting['Name'] == 'QuitButtonUrl') {
                    if(row_setting['Value'] != '' ) {
                        if (row_setting['Value'].includes("https://drive.google.com")) {
                            let imgid = row_setting['Value'].split('https://drive.google.com')[1].split('/')[3];
                            let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                            // Cache Image
                            let imagePath = 'img/cacheImages/' + imgid + '.png?version=' + Math.random();
                            checkIfImageExists(imagePath, (isExists) => {
                                if(isExists) {
                                    let bgImage = new Image();
                                    bgImage.src = imagePath
                                    imgLoaded++
                                    showMessageInfo(imgLoaded)
                                } else {
                                    imgLoaded++
                                    showMessageInfo(imgLoaded)
                                }
                            })
                        } else {
                            // Cache Image
                            let name = row_setting['Value'].split('/')
                            let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                            //console.log(imageName, " imageName")
                            // New Changes
                            let imagePath = 'img/cacheImages/' + imageName + "?version=" + Math.random();
                            checkIfImageExists(imagePath, (isExists) => {
                                if(isExists) {
                                    let mapImage = new Image();
                                    mapImage.src = imagePath
                                    imgLoaded++
                                    showMessageInfo(imgLoaded)
                                } else {
                                    imgLoaded++
                                    showMessageInfo(imgLoaded)
                                }
                            })
                        }
                    }
                }
            })  */
        ////////////////////////////////////////////////////////
        //for (var i=0; i<privateDataList.length; i++) {
        // if(getAllImageCount() > 0) {
            $.each(languageStepsData, function (i, row_setting) {
                if(languageStepsData[i]['Image'] != '') {
                    if (languageStepsData[i]['Image'].includes("https://drive.google.com")) {
                        let imgid = languageStepsData[i]['Image'].split('https://drive.google.com')[1].split('/')[3];
                        let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                        // Cache Image
                        //let imagePath = 'img/cacheImages/' + imgid + '.png?version=' + Math.random();
                        // cacheImages moved to spreadsheet id folder
                        let imagePath = './sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();

                        checkIfImageExists(imagePath, (isExists) => {
                            if(isExists) {
                                let bgImage = new Image();
                                bgImage.src = imagePath

                                document.getElementById('stepBGInage').src = imagePath;

                                // To Precache
                                preCacheImages[i] = new Image()
                                preCacheImages[i].src = imagePath
                                
                                imgLoaded++
                                //showMessageInfo(imgLoaded)
                                // New to show only When load completed
                                preCacheImages[i].onload = checkImageStatus(imgLoaded)
                            } else {
                                imgLoaded++
                                showMessageInfo(imgLoaded)
                            }
                        })
                    } else {
                        // Cache Image
                        let name = languageStepsData[i]['Image'].split('/')
                        let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                        
                        // New Changes
                        //let imagePath = 'img/cacheImages/' + imageName + "?version=" + Math.random();

                        // cacheImages moved to spreadsheet id folder
                        let imagePath = './sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();

                        checkIfImageExists(imagePath, (isExists) => {
                            if(isExists) {
                                let mapImage = new Image();
                                mapImage.src = imagePath
                                document.getElementById('stepBGInage').src = imagePath;
                                //console.log(document.getElementById('stepBGInage').src, " >>>>>")

                                // To Precache
                                preCacheImages[i] = new Image()
                                preCacheImages[i].src = imagePath

                                imgLoaded++
                                //showMessageInfo(imgLoaded)
                                // New to show only When load completed
                                preCacheImages[i].onload = checkImageStatus(imgLoaded)
                            } else {
                                imgLoaded++
                                showMessageInfo(imgLoaded)
                            }
                        })
                    }
                } 
                // else {
                //     showMessageInfo(imgLoaded)
                // }
            })
        } else {
            showMessageInfo(imgLoaded)
        }

        //showMessageInfo(imgLoaded)

        setTimeout(function() {
            /* document.getElementById('stepsScreen').style.display = 'block'
            // Auto fill default sections
            updateTopInstructionText(stepIndex)
            //console.log("ALL IMAGES CACHED")
            // Start Auto Play timer
            //checkAutoPlayStat() */
        }, 4000)
    }
    ///////////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} imgCount 
     */
    function checkImageStatus(imgCount) {
        //console.log(imgCount, " >imgCount")
        showMessageInfo(imgCount)
    }
    ///////////////////////////////////////////////////////////////////////////////////////
    function jumpToStepScreen() {
        ////////////////////////////////////////////////////////////////////////
        // Update button urls if any
        // Prev Button
        // Next Button
        // Quit(Home) Button
        ///////////////////////////////////////////////////////////////////////
        $.each(settingDataList, function (index_setting, row_setting) {
            if(row_setting['Name'] == 'PrevButtonUrl') {
                if(row_setting['Value'] != '') {
                    let imagePathPrev = ''
                    if (row_setting['Value'].includes("https://drive.google.com")) {
                        let imgid = row_setting['Value'].split('https://drive.google.com')[1].split('/')[3];
                        let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                        // Cache Image
                        //imagePathPrev = 'img/cacheImages/' + imgid + '.png?version=' + Math.random();
                        // cacheImages moved to spreadsheet id folder
                        imagePathPrev = './sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();
                    } else {
                        // Cache Image
                        let name = row_setting['Value'].split('/')
                        let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                        //imagePathPrev = 'img/cacheImages/' + imageName + "?version=" + Math.random();
                        // cacheImages moved to spreadsheet id folder
                        imagePathPrev = './sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
                    }
                    //document.getElementById('prevIcon').src = 'img/cacheImages/' + row_setting['Value'] + '?version=' + Math.random()
                    document.getElementById('prevIcon').src = imagePathPrev;
                }
            }
            if(row_setting['Name'] == 'NextButtonUrl') {
                if(row_setting['Value'] != '') {
                    let imagePathNext = ''
                    if (row_setting['Value'].includes("https://drive.google.com")) {
                        let imgid = row_setting['Value'].split('https://drive.google.com')[1].split('/')[3];
                        let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                        // Cache Image
                        //imagePathNext = 'img/cacheImages/' + imgid + '.png?version=' + Math.random();
                        // cacheImages moved to spreadsheet id folder
                        imagePathNext = './sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();
                    } else {
                        // Cache Image
                        let name = row_setting['Value'].split('/')
                        let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                        //imagePathNext = 'img/cacheImages/' + imageName + "?version=" + Math.random();
                        // cacheImages moved to spreadsheet id folder
                        imagePathNext = './sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
                    }
                    //document.getElementById('nextIcon').src = 'img/cacheImages/' + row_setting['Value'] + '?version=' + Math.random()
                    document.getElementById('nextIcon').src = imagePathNext;
                }
            }
            if(row_setting['Name'] == 'QuitButtonUrl') {
                if(row_setting['Value'] != '') {
                    let imagePathQuit = ''
                    if (row_setting['Value'].includes("https://drive.google.com")) {
                        let imgid = row_setting['Value'].split('https://drive.google.com')[1].split('/')[3];
                        let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                        // Cache Image
                        //imagePathQuit = 'img/cacheImages/' + imgid + '.png?version=' + Math.random();
                        // cacheImages moved to spreadsheet id folder
                        imagePathQuit = './sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();
                    } else {
                        // Cache Image
                        let name = row_setting['Value'].split('/')
                        let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];

                        //console.log(imageName, " QUIT IMAGE")

                        //imagePathQuit = 'img/cacheImages/' + imageName + "?version=" + Math.random();
                        // cacheImages moved to spreadsheet id folder
                        imagePathQuit = './sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
                    }
                    //document.getElementById('homeIcon').src = 'img/cacheImages/' + row_setting['Value'] + '?version=' + Math.random()
                    document.getElementById('homeIcon').src = imagePathQuit;
                }
            }
        })
        setTimeout(function() {
            ////////////////////////////////////////////////////////////////////////
            // [Commented for now 8/1/25]
            /* // Load Step screen
            document.getElementById('stepsScreen').style.display = 'block'
            document.getElementById('loadingScreen').style.display = 'none' */


            // Preload All Images in background
            PreloadAllToCacheInBackground()


        }, 10)

        // Auto fill default sections
        // Check motion
        let startType = ''
        let startDuration = 0
        if(jumpId != '') {
            stepIndex = getIndexUsingID(jumpId);

            

            //console.log(stepIndex, " DEFAULT INDEX")
            //console.log((languageStepsData[stepIndex].Type), " >>>>DURATION")
            startType = languageStepsData[stepIndex].Type;
            startDuration = languageStepsData[stepIndex].Duration;
            //console.log(startDuration, " --- ")
            //return
            //if(languageStepsData[stepIndex] != undefined) {
                if(typeof(languageStepsData[stepIndex].Duration) == 'string') {
                    //console.log("INCREASING INDEX")
                    stepIndex++;
                } else {
                    // New changes
                    //stepIndex++;
                }
            //}
        } else {
            stepIndex = 0
        }
        //console.log(stepIndex, " move to")


        // Check whether the view link available or not
        //console.log(languageStepsData[stepIndex].ViewLink, " View Link")

        // 9/1/25
        //updateTopInstructionText(stepIndex)

        //return;
        //console.log(startType, " startType")
        if(startType == 'Loading') {
            setTimeout(function() {
                stepIndex++;
                updateMiddleImageSection(stepIndex)
            }, startDuration * 1000)
        } else {
            updateMiddleImageSection(stepIndex)
        }
        console.log("Caching In Background")

        // saving user stat
        //saveUsageStat()
    }
    ///////////////////////////////////////////////////////////////////////////////////////
    function PreloadAllToCacheInBackground() {
        var imgLoaded = 0
        
        //console.log(stepIndex, " Step Index")

        /* $.each(settingDataList, function (index_setting, row_setting) {
            if(row_setting['Name'] == 'BackgroundImage' || row_setting['Name'] == 'SplashImageUrl' || row_setting['Name'] == 'PrevButtonUrl' || row_setting['Name'] == 'NextButtonUrl' || row_setting['Name'] == 'QuitButtonUrl') {
                if(row_setting['Value'] != '' ) {
                    if (row_setting['Value'].includes("https://drive.google.com")) {
                        let imgid = row_setting['Value'].split('https://drive.google.com')[1].split('/')[3];
                        let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                        // Cache Image
                        let imagePath = 'img/cacheImages/' + imgid + '.png?version=' + Math.random();
                        checkIfImageExists(imagePath, (isExists) => {
                            if(isExists) {
                                let bgImage = new Image();
                                bgImage.src = imagePath
                                imgLoaded++

                            } else {
                                imgLoaded++
                            }
                        })
                    } else {
                        // Cache Image
                        let name = row_setting['Value'].split('/')
                        let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                        //console.log(imageName, " imageName")
                        // New Changes
                        let imagePath = 'img/cacheImages/' + imageName + "?version=" + Math.random();
                        checkIfImageExists(imagePath, (isExists) => {
                            if(isExists) {
                                let mapImage = new Image();
                                mapImage.src = imagePath
                                imgLoaded++

                            } else {
                                imgLoaded++

                            }
                        })
                    }
                }
                    // /usr/bin/python3.6
            }
        }) */ 
    ////////////////////////////////////////////////////////
    //for (var i=0; i<privateDataList.length; i++) {
    // if(getAllImageCount() > 0) {
        $.each(languageStepsData, function (i, row_setting) {
            if(languageStepsData[i]['Image'] != '') {
                if (languageStepsData[i]['Image'].includes("https://drive.google.com")) {
                    let imgid = languageStepsData[i]['Image'].split('https://drive.google.com')[1].split('/')[3];
                    let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                    // Cache Image
                    //let imagePath = 'img/cacheImages/' + imgid + '.png?version=' + Math.random();
                    // image from spreadsheet id folder
                    let imagePath = './sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();
                    checkIfImageExists(imagePath, (isExists) => {
                        if(isExists) {
                            let bgImage = new Image();
                            bgImage.src = imagePath
                            // Storing to the container
                            /* document.getElementById('stepBGInage').src = imagePath; */

                            // To Precache
                            preCacheImages[i] = new Image()
                            preCacheImages[i].src = imagePath
                            


                            imgLoaded++
                            // uncomment if needed
                            //showCacheMessageInfo(imgLoaded)

                            // New to show only When load completed
                            preCacheImages[i].onload = checkImageLoadStatus(imgLoaded)
                            

                        } else {
                            imgLoaded++
                            showCacheMessageInfo(imgLoaded)
                        }
                    })
                } else {
                    // Cache Image
                    let name = languageStepsData[i]['Image'].split('/')
                    let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                    
                    // New Changes
                    //let imagePath = 'img/cacheImages/' + imageName + "?version=" + Math.random();
                    // image from spreadsheet id folder
                    let imagePath = './sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
                    checkIfImageExists(imagePath, (isExists) => {
                        if(isExists) {
                            let mapImage = new Image();
                            mapImage.src = imagePath
                            /* document.getElementById('stepBGInage').src = imagePath;
                            document.getElementById('stepBGInage').src = '' */
                            //console.log(document.getElementById('stepBGInage').src, " >>>>>")

                            // To Precache
                            preCacheImages[i] = new Image()
                            preCacheImages[i].src = imagePath

                            imgLoaded++
                            // uncomment if needed
                            //showCacheMessageInfo(imgLoaded)

                            // New to show only When load completed
                            preCacheImages[i].onload = checkImageLoadStatus(imgLoaded)

                        } else {
                            imgLoaded++
                            showCacheMessageInfo(imgLoaded)
                        }
                    })
                }
            } 
        })
    }
    ///////////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} imgCount 
     */
    function checkImageLoadStatus(imgCount) {
        //console.log(imgCount, " imageLoaded")
        showCacheMessageInfo(imgCount)
    }
    ///////////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} _count 
     */
    function showCacheMessageInfo(_count) {
        //let jumpIdList = []
        //let itemIndex = 0

        //console.log(_count, " >>>>")

        getDataColumnValueWhileLoading(_count)

        ////////////////////////////////////////////////////////////////////////
        // For Log Messages
        $.each(settingDataList, function (index_setting, row_setting) {
            if(row_setting['Name'] == 'Version') {
                document.getElementById('versionInfo').innerHTML = _version + " - " + row_setting["Value"] + " - " + activeLang + "<br>";
                document.getElementById('versionInfo').innerHTML += "Caching Images (" + _count + "/" + getAllImageCount() + ")<br>";
            }
        })
        ////////////////////////////////////////////////////////////////////////
        /* let displayString = getDataColumnValueWhileLoading(_count)
        document.getElementById('versionInfo').innerHTML += displayString; */
        if(_count == getAllImageCount() /* && dispInfoCount == dispMessageList.length */) {
            console.log("ALL IMAGES IN CACHE NOW...")
            preCachedDone = true;
            //console.log(preCacheImages[stepIndex].src)
            //////////////////////////////////////////////////////////
            // Checking elements for that jumpIndex
            /* $.each(languageStepsData, function (i, row_setting) {
                if(i >= stepIndex && languageStepsData[i].Name == 'How to play') {
                    //console.log(languageStepsData[i], " Values")
                    //jumpIdList[itemIndex++] = (languageStepsData[i])
                    //setTimeout(function() {
                        //createElements(jumpIdList)
                    //}, 0)
                    
                }
            }) */
            //////////////////////////////////////////////////////////
        }
    }
   
    ///////////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} _count 
     */
    function showMessageInfo(_count) {
        //console.log(_count, " >>>> CACHE")
        // Check all Preload Cache Image and the move to enable app section
        //console.log(_count, " === ", getAllImageCount())

        getDataColumnValueWhileLoading(_count)

        ////////////////////////////////////////////////////////////////////////
        // For Log Messsage
        $.each(settingDataList, function (index_setting, row_setting) {
            if(row_setting['Name'] == 'Version') {
                document.getElementById('versionInfo').innerHTML = _version + " - " + row_setting["Value"] + " - " + activeLang + "<br>";
                document.getElementById('versionInfo').innerHTML += "Caching Images (" + _count + "/" + getAllImageCount() + ")";
            }
        })
        ////////////////////////////////////////////////////////////////////////
        if(_count == getAllImageCount() /* && dispInfoCount == dispMessageList.length */) {
            preCachedDone = true;
            //console.log('loading.. ' + (_count) + " / " + (getAllImageCount()))
            //console.log("ENTER HERER - ", errorMessage)
            ////////////////////////////////////////////////////////////////////////
            // Update button urls if any
            // Prev Button
            // Next Button
            // Quit(Home) Button
            ///////////////////////////////////////////////////////////////////////
            $.each(settingDataList, function (index_setting, row_setting) {
                if(row_setting['Name'] == 'PrevButtonUrl') {
                    if(row_setting['Value'] != '') {
                        let imagePathPrev = ''
                        if (row_setting['Value'].includes("https://drive.google.com")) {
                            let imgid = row_setting['Value'].split('https://drive.google.com')[1].split('/')[3];
                            let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                            // Cache Image
                            //imagePathPrev = 'img/cacheImages/' + imgid + '.png?version=' + Math.random();
                            // image from spreadsheet id folder
                            imagePathPrev = './sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();
                        } else {
                            // Cache Image
                            let name = row_setting['Value'].split('/')
                            let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                            //imagePathPrev = 'img/cacheImages/' + imageName + "?version=" + Math.random();
                            // image from spreadsheet id folder
                            imagePathPrev = './sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
                        }
                        //document.getElementById('prevIcon').src = 'img/cacheImages/' + row_setting['Value'] + '?version=' + Math.random()
                        document.getElementById('prevIcon').src = imagePathPrev;
                    }
                }

                if(row_setting['Name'] == 'NextButtonUrl') {
                    if(row_setting['Value'] != '') {
                        let imagePathNext = ''
                        if (row_setting['Value'].includes("https://drive.google.com")) {
                            let imgid = row_setting['Value'].split('https://drive.google.com')[1].split('/')[3];
                            let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                            // Cache Image
                            //imagePathNext = 'img/cacheImages/' + imgid + '.png?version=' + Math.random();
                            // image from spreadsheet id folder
                            imagePathNext = './sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();
                        } else {
                            // Cache Image
                            let name = row_setting['Value'].split('/')
                            let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                            //imagePathNext = 'img/cacheImages/' + imageName + "?version=" + Math.random();
                            // image from spreadsheet id folder
                            imagePathNext = './sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
                        }
                        //document.getElementById('nextIcon').src = 'img/cacheImages/' + row_setting['Value'] + '?version=' + Math.random()
                        document.getElementById('nextIcon').src = imagePathNext;
                    }
                }
                if(row_setting['Name'] == 'QuitButtonUrl') {
                    if(row_setting['Value'] != '') {
                        let imagePathQuit = ''
                        if (row_setting['Value'].includes("https://drive.google.com")) {
                            let imgid = row_setting['Value'].split('https://drive.google.com')[1].split('/')[3];
                            let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                            // Cache Image
                            //imagePathQuit = 'img/cacheImages/' + imgid + '.png?version=' + Math.random();
                            // image from spreadsheet id folder
                            imagePathQuit = './sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();
                        } else {
                            // Cache Image
                            let name = row_setting['Value'].split('/')
                            let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];

                            //console.log(imageName, " QUIT IMAGE")

                            //imagePathQuit = 'img/cacheImages/' + imageName + "?version=" + Math.random();
                            // image from spreadsheet id folder
                            imagePathQuit = './sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
                        }
                        //document.getElementById('homeIcon').src = 'img/cacheImages/' + row_setting['Value'] + '?version=' + Math.random()
                        document.getElementById('homeIcon').src = imagePathQuit;
                    }
                }
            })
            setTimeout(function() {
                ////////////////////////////////////////////////////////////////////////
                // Load Step screen
                //document.getElementById('stepsScreen').style.display = 'block'
                //document.getElementById('loadingScreen').style.display = 'none'
            /* }, 1500) */
            }, 500)

            ////////////////////////////////////////////////////////////////////
            // Auto fill default sections
            /* if(jumpId != '') {
                stepIndex = getIndexUsingID(jumpId);
                //console.log(stepIndex, " DEFAULT INDEX")
                //console.log(typeof(languageStepsData[stepIndex]), " >>>>DURATION")
                //return
                //if(languageStepsData[stepIndex] != undefined) {
                    if(typeof(languageStepsData[stepIndex].Duration) == 'string') {
                        //console.log("INCREASING INDEX")
                        stepIndex++;
                    }
                //}
            } else {
                stepIndex = 0
            }
            //console.log(stepIndex, " move to")
            document.getElementById('spinnerMiddleBox').style.display = 'none'
            updateTopInstructionText(stepIndex) */
            /////////////////////////////////////////////////////////////////////////
            // Auto fill default sections
            // Check motion
            let startType = ''
            let startDuration = 0
            if(jumpId != '') {
                stepIndex = getIndexUsingID(jumpId);
                startType = languageStepsData[stepIndex].Type;
                startDuration = languageStepsData[stepIndex].Duration;
                if(typeof(languageStepsData[stepIndex].Duration) == 'string') {
                    stepIndex++;
                } else {
                }
            } else {
                stepIndex = 0
            }
            if(startType == 'Loading') {
                setTimeout(function() {
                    stepIndex++;
                    updateMiddleImageSection(stepIndex)
                }, startDuration * 1000)
            } else {
                updateMiddleImageSection(stepIndex)
            }
            console.log("IMAGES CACHED")
        } else {
            //console.log('loading.. ' + (_count) + " / " + (getAllImageCount()))
        }
    }
    /////////////////////////////////////////////////////////////////////////////////////////
    function getDataColumnValueWhileLoading(startInt) {

        //let dispText = ''
        
        let index = getIndexUsingID(jumpId);
        //console.log(index, " --- ", startInt)
        
        //if(languageStepsData[index + (startInt+1)].Type == "") {return}
        //console.log(languageStepsData[index + (startInt)].Type, "---")
        /* if(languageStepsData[index + (startInt-1)].Type == "Step") {
            if(languageStepsData[index + (startInt-1)].Name != '') {
                console.log(languageStepsData[index + (startInt-1)].Name, " -- TYPE")
                //console.log(languageStepsData[index + (startInt-1)])
            }
        } else {
            return;
        } */

        //console.log(index, " --- ", startInt)
        /* if(languageStepsData[index + (startInt-1)].Type == "Step" && languageStepsData[index + (startInt-1)].Name != "") {
            if(languageStepsData[index + (startInt-1)].Name != '') {
                if(languageStepsData[index + (startInt-1)].Duration == "" && endFound == false) {
                    endFound = true
                    console.log(languageStepsData[index + (startInt-1)].Name)

                }
            }
        } */

        if(languageStepsData[index + (startInt-1)] != undefined) {
            /* if(languageStepsData[index + (startInt-1)].Type == "Step" && endFound == false && languageStepsData[index + (startInt-1)].Name != "") { */
            if(languageStepsData[index + (startInt-1)].Type == "Step" && endFound == false) {
                endFound = true
                //console.log(languageStepsData[index + (startInt-1)].Name, " NAME")
                //dispText = languageStepsData[index].Name
                //document.getElementById('appInfo').innerHTML = "<font color='gray' size='5vh'>Loading</font><br>";
                //if(dispText == '') {
                    document.getElementById('appInfo').innerHTML = "Loading..";
                //} else {
                    //document.getElementById('appInfo').innerHTML = dispText;
                    //console.log(dispText, " ----")
                //}
                //dispMessageList.push(dispText)
                //dispInfoCount++
            } else if(languageStepsData[index + (startInt-1)].Next == "END") {
                endFound = true
            } else if(languageStepsData[index + (startInt-1)].Type == "Loading" && endFound == false) {
                endFound = true
                dispText = languageStepsData[index].Text
                //console.log(dispText, " ---- 1")
                if(dispText == '') {
                    document.getElementById('appInfo').innerHTML = "Loading..";
                } else {
                    document.getElementById('appInfo').innerHTML = dispText;
                    //console.log(dispText, " ----")
                }
            }
        }

        //return dispText

        /* if(jumpId == "HOWTO") {
            if(languageStepsData[index + (startInt-1)].Type == "Step" && languageStepsData[index + (startInt-1)].Name != "" && languageStepsData[index + (startInt-1)].Next != 'END' && endFound == false && languageStepsData[index + (startInt-1)].Name != 'Fullscreen IOS') {
                console.log(startInt, " index ", languageStepsData[index + (startInt-1)].Name)
            }
        } else if(jumpId == 'FULLSCREEN_IOS') {
            if(languageStepsData[index + (startInt-1)].Type == "Step" && languageStepsData[index + (startInt-1)].Name != "" && languageStepsData[index + (startInt-1)].Next != 'END' && endFound == false && languageStepsData[index + (startInt-1)].Name != 'Fullscreen Android') {
                console.log(startInt, " index ", languageStepsData[index + (startInt-1)].Name)
            } 
        } */
    }
    /////////////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @returns 
     */
    function getAllImageCount() {
        var tempCount = 0
        /* $.each(settingDataList, function (index_setting, row_setting) {
            if(row_setting['Name'] == 'BackgroundImage' || row_setting['Name'] == 'SplashImageUrl' || row_setting['Name'] == 'PrevButtonUrl' || row_setting['Name'] == 'NextButtonUrl' || row_setting['Name'] == 'QuitButtonUrl') {
                //console.log("row_setting['Value'] >>>> ", row_setting['Value'])
                if(row_setting['Value'] != '' ) {
                    tempCount++
                }
            }
        }) */
        //console.log(languageStepsData, " >languageStepsData")

        ///////////////////////////////////////////////////////////////////////////
        $.each(languageStepsData, function (i, row) {
            if(languageStepsData[i].Image != '') {
                tempCount++
            } 
        })
        ///////////////////////////////////////////////////////////////////////////

        /* console.log(languageStepsData)

        console.log("AFTER FILTER")

        let tempImageList = []
        $.each(languageStepsData, function (i, row) {
            if(languageStepsData[i].Image != '') {
                tempImageList.push(languageStepsData[i].Image)
            } 
        })
        

        let filteredImages = tempImageList.filter((item, index) => tempImageList.indexOf(item) === index);


        console.log(filteredImages)

        $.each(filteredImages, function (i, row) {
            if(filteredImages[i].Image != '') {
                tempCount++
            } 
        }) */

        //console.log(tempCount, " in setting data")
        return tempCount;
    }
    ///////////////////////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} url 
     * @param {*} callback 
     */
    function checkIfImageExists(url, callback) {
        //const img = new Image();
        let img = new Image();
        img.src = url;
        if (img.complete) {
            callback(true);
        } else {
        img.onload = () => {
            callback(true);
        };
        img.onerror = () => {
            callback(false);
        };
        }
    }
    ////////////////////////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} _type 
     */

    // Do Animate circle
    FULL_DASH_ARRAY = 283;
    RESET_DASH_ARRAY = `-57 ${FULL_DASH_ARRAY}`;
    timerClose = document.querySelector("#base-timer-path-remaining-close");
    //let timeLabel = document.getElementById("base-timer-label");
    TIME_LIMIT = 3; //5; //in seconds
    timePassed = 1;
    timeLeft = TIME_LIMIT;
    timerInterval = null;

    function doAnimateCloseButton(_type) {

        //console.log("doAnimateCloseButton")

        //timeLabel.innerHTML = formatTime(TIME_LIMIT);
        FULL_DASH_ARRAY = 283;
        RESET_DASH_ARRAY = `-57 ${FULL_DASH_ARRAY}`;
        TIME_LIMIT = 3; //5; //in seconds
        timePassed = 1;
        timeLeft = TIME_LIMIT;
        timerInterval = null;
        startTimer(_type)
    }
    ////////////////////////////////////////////////////////////////////////////////////
/**
 * stop()
 */
function stop() {
    clearInterval(timerInterval);
  }
  /////////////////////////////////////////////////////////////////////////////
  /**
   * 
   * @param {*} _type 
   */
  function startTimer(_type) {
    //console.log("timer start")
    timePassed = 1;
    TIME_LIMIT = 3; //5;
    //timeLeft = TIME_LIMIT - timePassed;


    //setCircleDasharray(_type);


    /* ContinueStartTimer(_type)
    return */
    
    timerInterval = setInterval(() => {
      clearInterval(timerInterval)
      ContinueStartTimer(_type)
      ///////////////////////////////////////////////
      //console.log("CALLING")
      //timePassed = timePassed += 1;
      timeLeft = TIME_LIMIT - timePassed;
      //timeLabel.innerHTML = formatTime(timeLeft);
      setCircleDasharray(_type);
      if (timeLeft === 0) {
        //timeIsUp();
      }
      ///////////////////////////////////////////////
  
    }, 100);
  }
  ////////////////////////////////////////////////////////////////////////////////////
  /**
   * 
   * @param {*} _type 
   */
  function ContinueStartTimer(_type) {
    //console.log("timer start")
    timePassed = 1;
    // New
    TIME_LIMIT = 3; //5;
    //timeLeft = TIME_LIMIT - timePassed;
    //setCircleDasharray(_type);

    timerInterval = setInterval(() => {
      timePassed = timePassed += 1;
      timeLeft = TIME_LIMIT - timePassed;
      //timeLabel.innerHTML = formatTime(timeLeft);
      setCircleDasharray(_type);
      if (timeLeft === 0) {
        window.parent.postMessage(JSON.stringify({'message': 'closeFrame'}), '*')
        timeIsUp();
      }
    }, 1000);
  }
  /////////////////////////////////////////////////////////////////////////////
  /**
   * timeIsUp()
   */
    function timeIsUp() {
      clearInterval(timerInterval);
      /* let confirmReset = confirm("Time is UP! Wanna restart?"); */
      /* if (confirmReset) {
        reset();
        startTimer();
      } else { */
        reset();
     /*  } */
     //$(".bodyWrapper").css({display:"none"})
     setTimeout(function() {
        //ResetGameonRunningState();
        /* levelCounter = 1
        $(".bodyWrapper.oddPage").css({display:"flex"})
        $(".bodyWrapper.second-screen").css({display:"none"})
        $(".bodyWrapper.evenPage").css({display:"none"})
        $(".counter-end-page-quit").css({display:"none"})
        document.getElementById('pauseButton').style.display = 'block';
        document.getElementById('pauseGame').style.display = 'block' */
    }, 300)
  
    }
    ////////////////////////////////////////////////////////////////////////////////////
    /**
     * resetVars()
     */
    function resetVars() {
      /* timePassed = 1; */
      //console.log(timeLeft, " time left")

      //console.log(timeLeft, " timeLeft")

      if(timeLeft == 0 || timeLeft == 3) {
            //console.log("RESET VARS")
            //window.parent.postMessage(JSON.stringify({'message': 'closeFrame'}), '*')
        }

  
      timePassed = 0;
      timeLeft = TIME_LIMIT;
      //console.log(timePassed, timeLeft);
      //timeLabel.innerHTML = formatTime(TIME_LIMIT);
  
      FULL_DASH_ARRAY = 283;
      RESET_DASH_ARRAY = `-57 ${FULL_DASH_ARRAY}`;
      //timer = document.querySelector("#base-timer-path-remaining");
      //let timeLabel = document.getElementById("base-timer-label");
      TIME_LIMIT = 3; //in seconds
  
      //////////////////////////////////////////////
      // New
      timeLeft = TIME_LIMIT;
      timerInterval = null;
      timerClose.setAttribute("stroke-dasharray", RESET_DASH_ARRAY);
      //timerFinal.setAttribute("stroke-dasharray", RESET_DASH_ARRAY);
  
      // Reset All Timer values
      timerClose = document.querySelector("#base-timer-path-remaining-close");


        //////////////////////////////////////////////////////////////////////////
        // Reset Vars
        document.getElementById("base-timer-close").style.opacity = 0
        //////////////////////////////////////////////////////////////////////

        // New Change
        FULL_DASH_ARRAY = 283;
        RESET_DASH_ARRAY = `-57 ${FULL_DASH_ARRAY}`;
        timerClose = document.querySelector("#base-timer-path-remaining-close");
        //let timeLabel = document.getElementById("base-timer-label");
        TIME_LIMIT = 3; //5; //in seconds
        timePassed = 0;
        timeLeft = TIME_LIMIT;
        timerInterval = null;
        //////////////////////////////////////////////////////////////////////////
        let circleDasharray = `${(
        calculateTimeFraction() * FULL_DASH_ARRAY
        ).toFixed(0)} 283`;
        timerClose.setAttribute("stroke-dasharray", circleDasharray);
        //////////////////////////////////////////////////////////////////////////
    }
    ////////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} time 
     * @returns 
     */
    function formatTime(time) {
      let minutes = Math.floor(time / 60);
      let seconds = time % 60;
    
      if (seconds < 10) {
        seconds = `0${seconds}`;
      }
    
      return `${minutes}:${seconds}`;
    }
    ////////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @returns 
     */
    function calculateTimeFraction() {
      let rawTimeFraction = timeLeft / TIME_LIMIT;
      return rawTimeFraction - (1 / TIME_LIMIT) * (1 - rawTimeFraction);
    }
    ////////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} _type 
     */
    function setCircleDasharray(_type) {
      let circleDasharray = `${(
        calculateTimeFraction() * FULL_DASH_ARRAY
      ).toFixed(0)} 283`;
      //console.log("setCircleDashArray: ", circleDasharray);
        timerClose.setAttribute("stroke-dasharray", circleDasharray);
    }
    ////////////////////////////////////////////////////////////////////////////////////
    /**
     * reset() for times up 
     */
    function reset() {
      clearInterval(timerInterval);
      resetVars();
      /* timer.setAttribute("stroke-dasharray", RESET_DASH_ARRAY);
      timerFinal.setAttribute("stroke-dasharray", RESET_DASH_ARRAY); */
    }
    ////////////////////////////////////////////////////////////////////////////////////////////////////
    
    function loadImage(imageURI) {
        let imgURL = ''
        imgURL = imageURI
        request = new XMLHttpRequest();
        request.onloadstart = showProgressBar;
        request.onprogress = updateProgressBar;
        request.onload = showImage;
        request.onloadend = hideProgressBar;
        request.open("GET", imageURI, true);
        request.overrideMimeType('text/plain; charset=x-user-defined');
        request.send(null);
    }
    
    function showProgressBar() {
        //progressbar.innerHTML = 0;
        //console.log("show loaded")
        if(preCachedDone == false) {
            document.getElementById('spinnerMiddleBox').style.display = 'block'
        } else {
            document.getElementById('spinnerMiddleBox').style.display = 'none'
        }
    }
    
    function updateProgressBar(e) {
        if (e.lengthComputable) {
            //console.log(e.loaded / e.total * 100, " perc");
        }
    }
    
    function showImage() {
        var imageElement = "data:image/jpeg;base64," + base64Encode(request.responseText);
        //document.body.style.backgroundImage = 'url("' + imageElement + '")';
        //console.log("display image now")
        //document.getElementById('stepBGInage').src = imageElement;

        //////////////////////////////////////////////////////////////////
    
        // Fade Out
        if(prevImage != newImage) {
            $("#stepBGInage").fadeOut();
        }
        $("#stepText").fadeOut();

        $("#viewIcon").fadeOut();
        $("#viewIconText").fadeOut();
        

        //updateTopInstructionText(stepIndex)
        //////////////////////////////////////////////////////////////////
        setTimeout(function() {
            document.getElementById('stepBGInage').src = imageElement;
            moveToSlide();
        }, 500)
    }
    
    function hideProgressBar() {
        //progressbar.innerHTML = 100;
        //console.log("hide loaded")
        document.getElementById('spinnerMiddleBox').style.display = 'none'
    }

    function base64Encode(inputStr) {
        var b64 = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=";
        var outputStr = "";
        var i = 0;
    
        while (i < inputStr.length) {
            //all three "& 0xff" added below are there to fix a known bug 
            //with bytes returned by xhr.responseText
            var byte1 = inputStr.charCodeAt(i++) & 0xff;
            var byte2 = inputStr.charCodeAt(i++) & 0xff;
            var byte3 = inputStr.charCodeAt(i++) & 0xff;
    
            var enc1 = byte1 >> 2;
            var enc2 = ((byte1 & 3) << 4) | (byte2 >> 4);
    
            var enc3, enc4;
            if (isNaN(byte2)) {
                enc3 = enc4 = 64;
            } else {
                enc3 = ((byte2 & 15) << 2) | (byte3 >> 6);
                if (isNaN(byte3)) {
                    enc4 = 64;
                } else {
                    enc4 = byte3 & 63;
                }
            }
    
            outputStr += b64.charAt(enc1) + b64.charAt(enc2) + b64.charAt(enc3) + b64.charAt(enc4);
        }
    
        return outputStr;
    }

    function moveToSlide() {
        //console.log("IMG LOADED...")
        setTimeout(function() {
            ////////////////////////////////////////////////////////////////////

            //console.log(languageStepsData[stepIndex].Text.indexOf('\n'), " >>>>>")
            //let replaceValue = languageStepsData[stepIndex].Text.split('\n')
            document.getElementById('stepText').innerHTML = languageStepsData[stepIndex].Text
            /* console.log(replaceValue[0], " --- ", replaceValue[1])
            document.getElementById('stepText').innerHTML = replaceValue[0]
            document.getElementById('stepText').innerHTML += replaceValue[1] */
            if(languageStepsData[activeSlideIndex].Text != '') {
                document.getElementById('topStepBar').style.display = 'flex' 
            } else {
                document.getElementById('topStepBar').style.display = 'none'
            }

            //return
            ////////////////////////////////////////////////////////////////////
            //console.log(languageStepsData[stepIndex+1], " data ", languageStepsData[stepIndex])
            ///////////////////////////////////////////
            // Only for test
            // Transition
            // Fade in the BG image
            // Dev 4
            ////////////////////////////////////////////////////////////////
            // jQuery Effects
            //$( "#stepText" ).effect( "clip", "fast" );
            ////////////////////////////////////////////////////////////////

            ////////////////////////////////////////////////////////////////
            // Left Right sliding
            /* if(moveType == 'right') {
                $('#stepText').show("slide", { direction: "right" }, 700);
            } else {
                $('#stepText').show("slide", { direction: "left" }, 700);
            }

            $("#stepBGInage").animate({
                "left": "50%"
            }, 500); */
            ////////////////////////////////////////////////////////////////
            //document.getElementById('spinnerMiddleBox').style.display = 'none'
            ////////////////////////////////////////////////////////////////
            $("#stepText").fadeIn();
            ////////////////////////////////////////////////////////////////
            //console.log(prevImage, " === ", newImage)
            //if(prevImage != newImage) {
            ////////////////////////////////////////////////////////////////
            $("#stepBGInage").fadeIn();

            $("#viewIcon").fadeIn();
            $("#viewIconText").fadeOut();
            ////////////////////////////////////////////////////////////////

            //console.log("FADE IN")

            // Check whether the view link available or not
            //console.log(languageStepsData[stepIndex].ViewLink, " View Link ABC")
            let showViewLink = languageStepsData[stepIndex].ViewLink;
            //console.log(showViewLink, " svl")
            if(showViewLink != '') {
                //if(openInType == "inline") {
                let checkshowLinkText = showViewLink.split(',');
                //console.log(checkshowLinkText.includes("http"), " tttt")
                //if(languageStepsData[stepIndex].Name != '') {
                
                if(checkshowLinkText[0].includes("http") == false) {
                    document.getElementById('viewIcon').style.display = 'none'
                    document.getElementById('viewLinkLabel').innerHTML = checkshowLinkText[0]; //showViewLink; //languageStepsData[stepIndex].Name;
                    document.getElementById('viewIconText').style.display = 'inline-flex'
                    activeViewLink = checkshowLinkText[1]
                } else {
                    document.getElementById('viewIcon').style.display = 'block'
                    document.getElementById('viewLinkLabel').innerHTML = '';
                    document.getElementById('viewIconText').style.display = 'none'
                    activeViewLink = showViewLink
                }
                /* } else {
                    document.getElementById('viewIcon').style.opacity = '1'
                } */
                //activeViewLink = showViewLink
                
                

            } else {
                //document.getElementById('viewLink').style.display = 'none'
                //if(openInType == "inline") {
                    document.getElementById('viewIcon').style.display = 'none'
                    document.getElementById('viewLinkLabel').innerHTML = '';
                    document.getElementById('viewIconText').style.display = 'none'
                /* } else {
                    document.getElementById('viewIcon').style.opacity = '0'
                } */
                activeViewLink = ''
            }

            ////////////////////////////////////////////////////////////////
            if(prevImage == '' || newImage == '') {
                // Moved here
                // Load Step screen
                document.getElementById('stepsScreen').style.display = 'block'
                document.getElementById('loadingScreen').style.display = 'none'
                setTimeout(function() {
                    // Smooth transition
                    // Transition Top Gray Bar
                    /* var el = $('#topStepBar'),
                    curHeight = el.height(),
                    autoHeight = el.css('height', 'auto').height();
                    console.log(curHeight, " --- ", autoHeight)
                    el.height(curHeight).animate({height: (autoHeight + 30)}, 250); */
                    animateTopBorder()
                }, 700)
            } else {
                animateTopBorder()
                ///////////////////////////////////////////////////////////
                // Typewriter effect
                /* letterIndex = 0
                letters = ''
                setTimeout(function() {
                    letters = document.getElementById("stepText").innerHTML
                    document.getElementById("stepText").innerHTML = ''
                    typeWriter()
                }, 50) */
                ///////////////////////////////////////////////////////////
            }
            ////////////////////////////////////////////////////////////////
            // Pulsate Next Button
            //$( "#nextIcon" ).effect( "pulsate", "fast" );
            ////////////////////////////////////////////////////////////////
            //}
            ///////////////////////////////////////////
            /* $("#stepText").fadeIn();
            if(moveType == 'right') {
                $( "#stepBGInage" ).animate({
                    left: "150%",
                    opacity: '0'
                }, {
                    duration: 0,
                    specialEasing: {
                    width: "linear",
                    //height: "easeOutBounce"
                    },
                })
                // Move to correct pos
                $( "#stepBGInage" ).animate({
                    left: "50%",
                    opacity: '1'
                }, {
                    duration: 500,
                    specialEasing: {
                    width: "linear",
                    //height: "easeOutBounce"
                    },
                })
            } else {
                $( "#stepBGInage" ).animate({
                    left: "-50%",
                    opacity: '0'
                }, {
                    duration: 0,
                    specialEasing: {
                    width: "linear",
                    //height: "easeOutBounce"
                    },
                })
                // Move to correct pos
                $( "#stepBGInage" ).animate({
                    left: "50%",
                    opacity: '1'
                }, {
                    duration: 500,
                    specialEasing: {
                    width: "linear",
                    //height: "easeOutBounce"
                    },
                })
            } */
            /////////////////////////////////////////////////////////////////////////////
            document.getElementById('prevIcon').style.pointerEvents = 'auto';
            document.getElementById('nextIcon').style.pointerEvents = 'auto';
            /////////////////////////////////////////////////////////////////////////////
            // Trying New Logic
        /*  console.log("AAAAA - ", stepIndex)
            let nextStepType = languageStepsData[stepIndex+1].Type;
            let prevStepType = languageStepsData[stepIndex-1].Type;
            let curStepType = languageStepsData[stepIndex].Type;
            let nextNext = languageStepsData[stepIndex+1].Next;
            let PrevPrev = languageStepsData[stepIndex-2].Prev;
            console.log(nextStepType, "-- nextstep --", nextNext, " prev --", prevStepType, " :: ", PrevPrev)
            // if(nextStepType != 'Step' || curStepType != 'Step') {
            //     document.getElementById('prevIcon').style.opacity = '0.5'
            //     document.getElementById('prevIcon').style.pointerEvents = 'none';
            //     document.getElementById('nextIcon').style.opacity = '0.5'
            //     document.getElementById('nextIcon').style.pointerEvents = 'none';
            // } 
            return; */
            /////////////////////////////////////////////////////////////////////////////



            // Check Type value
            /* let stepType = languageStepsData[stepIndex].Type;
            console.log(stepType, " AFTER ANIM VALUE ", stepIndex)
            if(stepType != 'Step') {
                document.getElementById('prevIcon').style.opacity = '0.5'
                document.getElementById('nextIcon').style.opacity = '0.5'
                document.getElementById('prevIcon').style.pointerEvents = 'none';
                document.getElementById('nextIcon').style.pointerEvents = 'none';
            } else { */

                ///////////////////////////////////////////////////////////////////////
                /* console.log(languageStepsData[stepIndex-1], " CCC")
                return; */
                
                //console.log(languageStepsData[stepIndex-1], " CCC")

                ///////////////////////////////////////////////////////////////////////
                if(languageStepsData[stepIndex-1] != undefined) {
                    let stepDuration = languageStepsData[stepIndex-1].Duration;
                    if(typeof(stepDuration) == 'string') {
                        document.getElementById('prevIcon').style.opacity = '0.5'
                        document.getElementById('prevIcon').style.pointerEvents = 'none';
                        

                        document.getElementById('nextIcon').style.opacity = '1'
                        document.getElementById('nextIcon').style.pointerEvents = 'auto';
                    }

                    //////////////////////////////////////////////////////////////////////
                    let curType = languageStepsData[stepIndex].Type;
                    //console.log(curType, " >>>>curType")
                    let curDuration = languageStepsData[stepIndex].Duration;
                    //console.log(curType, " >>>>curType")

                    let nextType = languageStepsData[stepIndex+1].Type;
                    //console.log(nextType, " nextType")
                    let nextStep = languageStepsData[stepIndex].Next;
                    //console.log(nextStep, " nextStep")

                    if(nextType != 'Step' && nextStep == '') {
                        /* document.getElementById('prevIcon').style.opacity = '0.5'
                        document.getElementById('prevIcon').style.pointerEvents = 'none'; */

                        document.getElementById('nextIcon').style.opacity = '0.5'
                        document.getElementById('nextIcon').style.pointerEvents = 'none';
                    } else {
                        /*document.getElementById('prevIcon').style.opacity = '1'
                        document.getElementById('prevIcon').style.pointerEvents = 'auto'; */

                        document.getElementById('nextIcon').style.opacity = '1'
                        document.getElementById('nextIcon').style.pointerEvents = 'auto';
                    }

                    //////////////////////////////////////////////////////////////////////

                    /* if(curType != 'Step' && typeof(curDuration) == 'number') {
                        document.getElementById('nextIcon').style.opacity = '0.5'
                        document.getElementById('nextIcon').style.pointerEvents = 'none';
                    } else {
                        document.getElementById('nextIcon').style.opacity = '1'
                        document.getElementById('nextIcon').style.pointerEvents = 'auto';
                    } */


                    //////////////////////////////////////////////////////////////////////
                
                    let prevType = languageStepsData[stepIndex-1].Type;
                    let prevStep = languageStepsData[stepIndex].Prev;

                    /* console.log(prevStep, " prevStep ", prevType)
                    if(nextStep != 'Step' && prevStep == 'END') {
                        console.log("ENTER HERE")
                    } else */ 
                    if(prevType != 'Step' && prevStep == '') {
                        document.getElementById('prevIcon').style.opacity = '0.5'
                        document.getElementById('prevIcon').style.pointerEvents = 'none';
                    } else {
                        document.getElementById('prevIcon').style.opacity = '1'
                        document.getElementById('prevIcon').style.pointerEvents = 'auto';
                    }
                    ///////////////////////////////////////////////////////////////////////
                    // Checing current type value
                    // Disable all buttons
                    /* let curType = languageStepsData[stepIndex].Type;
                    console.log(curType, " >>>>curType")
                    if(curType != 'Step') {
                        document.getElementById('prevIcon').style.opacity = '0.5'
                        document.getElementById('prevIcon').style.pointerEvents = 'none';
                        document.getElementById('nextIcon').style.opacity = '0.5'
                        document.getElementById('nextIcon').style.pointerEvents = 'none';
                    } else {
                        document.getElementById('nextIcon').style.opacity = '1'
                        document.getElementById('nextIcon').style.pointerEvents = 'auto';
                    } */
                    ///////////////////////////////////////////////////////////////////////
                    /* let curType = languageStepsData[stepIndex].Type;
                    console.log(curType, " >>>>curType") */

                    /* if(languageStepsData[stepIndex-1].Type != 'Step') {
                        document.getElementById('nextIcon').style.opacity = '0.5'
                        document.getElementById('nextIcon').style.pointerEvents = 'none';
                    } */
                    ///////////////////////////////////////////////////////////////////////

                    // New Changes
                    if(typeof(languageStepsData[stepIndex-1].Duration) == 'string' && languageStepsData[stepIndex].Prev == '') {
                        //stepIndex++;
                        document.getElementById('prevIcon').style.opacity = '0.5'
                        document.getElementById('prevIcon').style.pointerEvents = 'none';
                    } else {
                        document.getElementById('prevIcon').style.opacity = '1'
                        document.getElementById('prevIcon').style.pointerEvents = 'auto';
                    }
                    ///////////////////////////////////////////////////////////////////////

                    if(nextStep == 'END') {
                        //document.getElementById('nextIcon').style.opacity = '0.5'
                        //console.log("CLOSE THE APP")
                    } else {
                        //document.getElementById('nextIcon').style.opacity = '1'
                        //document.getElementById('nextIcon').style.opacity = '0.5'
                    }

                    // Check whether the view link available or not at end
                    //console.log(languageStepsData[stepIndex].ViewLink, " View Link")
                }
        /*  } */
            checkAutoPlayStat();
        }, 10)
       /*  }, 0) */
    }
    ////////////////////////////////////////////////////////////////////////////////////////////////////
})
