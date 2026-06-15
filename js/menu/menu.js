//////////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 * Ready events
 */
$(document).ready(function() {

    //alert('menujs')
    let languageStepsData = [];
    let settingDataList = [];
    let rulesDataList = [];
    let faqsDataList = [];
    let statsDataList = [];
    let bggInfoData = [];
    let videosDataList = [];
    let tagsDataList = [];
    let splashDataList = []

    // tagImageMap and applyTagReplacements are defined in zapsheetsCore.js

    // Timeout
    let idleTimeOut = 0;

    // For lazy load
    // For LOCAL TESTING
    //let lazyLoadImages = 'TRUE'
    // For LIVE
    let lazyLoadImages = ''
    // For Precache Images
    var preCacheImages = []
    var preCachedDone = false;
    let langLoadCount = 0;
    let faqTouchMoved = false;
    ///////////////////////////////////////////////////////////////////////////
    // Total events
    let events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'];
    // Idle status
    let idleStatus = false;
    let idleTime = 0;

    var fromParent = ''
    //var faqsSheedId = ''
    let slideIndex = 0;
    let slideshowTimeout;
    ///////////////////////////////////////////////////////////////////////////
    // Positioning of bottom container
    var standalone = (getUrlVars()["standalone"]) ? getUrlVars()["standalone"].split('/')[0] : 'false';
    // To get active browser language OR language passed from QS 'code'
    var activeLang = (getUrlVars()["code"]) ? getUrlVars()["code"].split('/')[0].split('?')[0].toUpperCase() : navigator.language.split('-')[0].toUpperCase();

    // To get spreadsheet id passed from QS 'id'
    var sheet_Id = (getUrlVars()["id"]) ? getUrlVars()["id"] : '';
    // Fallback: extract from URL path /sheets/{id}/ when id param is missing or invalid
    if (!sheet_Id) {
        var _pathParts = window.location.pathname.split('/');
        var _sheetsIdx = _pathParts.indexOf('sheets');
        if (_sheetsIdx >= 0 && _pathParts[_sheetsIdx + 1]) sheet_Id = _pathParts[_sheetsIdx + 1];
    }
    // To jump faqs or rules
    //var sheetInnerParam = document.location.search.substr(1).split('&')[1].split('?')[0];
    var sheetInnerParam = document.location.search.substr(1).split('&')[2].split('?')[0];
    // To check where it comes
    //return;

    if(sheetInnerParam == 'faqs' || sheetInnerParam == 'faq' || sheetInnerParam == 'steps' || sheetInnerParam == 'step' || sheetInnerParam == 'rules' || sheetInnerParam == 'rule') {
        //fromParent = document.location.search.substr(1).split('&')[2].split('?')[0];
        fromParent = document.location.search.substr(1).split('&')[3].split('?')[0];
        if(fromParent == 'app' || fromParent == 'web') {
            jasonPath = 'https://zapsheets.com/playbook/'
        }
    }
    //return;
    /////////////////////////////////////////////////////////////////////////////////
    /**
     * IDLE TIMEOUT
     */
    // Event to check for
    function EnableTimeoutEvents() {
        events.forEach(function (name) {
            document.addEventListener(name, resetIdleTimer, true);
        });
    }
    /////////////////////////////////////////////////////////////////////////////////
    /**
     * idle out function
     * @returns 
     */
    function idleOut() {
        if(idleTimeOut == 0 || fromParent != '' || idleStatus == true) {return}
        idleStatus = true
        clearTimeout(idleTime);
        idleTime = setTimeout(idleOut, (idleTimeOut * 1000))
        // Move to home screen
        ShowSplashScreen();
    }
    /////////////////////////////////////////////////////////////////////////////////
    /**
     * Restart idle timer
     */
    function resetIdleTimer() {
        if(idleStatus == true || idleStatus == undefined) {
            idleStatus = false
        }

        // Hide Splash
        document.getElementById('splashScreen').style.display = 'none';
        //timeLine.pause();
        HideSplashSlides();

        clearTimeout(idleTime);
        idleTime = setTimeout(idleOut, (idleTimeOut * 1000))
    }
    // isJSONData is defined in zapsheetsCore.js
    /////////////////////////////////////////////////////////////////////////////////
    if(sheet_Id == '') {
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
        loadTagsData(function() { setTimeout(function() { jumpToMenuScreen(); }, 250); }, sheet_Id);
        loadSettingsData();

        // Splash Screen
        loadSplashData();
    }
    /////////////////////////////////////////////////////////////////////////////////
    /**
     * onCheckUserDataStart
     * @param {*} event 
     */
    function onCheckUserDataStart(event) {
        if(event != null) {event.preventDefault();}
        document.getElementById('sheetIdBtn').style.scale = '0.95'
    }
    /////////////////////////////////////////////////////////////////////////////////
    /**
     * onCheckUserDataClick
     * @param {*} event 
     */
    function onCheckUserDataClick(event) {
        if(event != null) {event.preventDefault();}
        document.getElementById('sheetIdBtn').style.scale = '1'
        checkUserFillData();

    }
    /////////////////////////////////////////////////////////////////////////////////
    /**
     * getOS
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
     * checkUserFillData
     */
    function checkUserFillData() {
        let user_name = ''
        let winLoc = window.location.href.split("?")[0];
        // get the values from the search form
        let uSheetId = document.getElementById("usheetId").value
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
            sheetInnerParam = 'id=' + sheet_Id;
            //showloader()
            setTimeout(function() {
                //window.history.replaceState({}, "null", (winLoc + "?code=" + browserLang.toLowerCase() +"&"+ jumpId + "&id=" + sheet_Id));
                //return;
                window.history.replaceState({}, "null", (winLoc + "?code=" + browserLang.toLowerCase() + "&id=" + sheet_Id));
                document.getElementById('loadingScreen').style.display = 'flex';
                document.getElementById('sheetIdError').style.display = 'none';
                setTimeout(function() {
                    loadTagsData(function() { setTimeout(function() { jumpToMenuScreen(); }, 250); }, sheet_Id);
                    loadSettingsData();

                    // Splash Screen
                    loadSplashData();
                }, 100)
            }, 10)
            
          } 
        }
    }
    //////////////////////LANG SETTINGS START///////////////////////////////////
    /**
     * loadSettingsData
     */
    function loadSettingsData() {
        // Loading menu-en.json
        setTimeout(function() {
            var settingRequest = $.ajax({
                //url: '../sheets/' + sheet_Id + "/settings.json?version=" + UIVersion,
                url: jasonPath + 'sheets/' + sheet_Id + "/settings.json?version=" + UIVersion,
                cache: true,
                type: 'GET',
                dataType: "text",
                success: function (response) {
                    if(response.length == 0) {
                        logLoadMsg('<font color="red">Error: Settings data not available.' + "</font><br>")
                    } else { 
                        settingDataList = []
                        var mResponseSet = response.replace(/�/g, "") 
                        var newSettingData = eval(mResponseSet)
                        for(var i=0; i<newSettingData.length; i++) {
                            var settingDataSting = JSON.stringify(newSettingData[i]);
                            if(isJSONData(settingDataSting) == false) {
                                logLoadMsg('<font color="red">Error: Settings Sheet : (Row: ' + i + ")</font><br>")
                            } else {
                                settingDataList[i] = isJSONData(settingDataSting)
                            }
                        }
                        /////////////////////LANG SETTINGS START///////////////////////////
                        // Store LazyLoadValue here
                        $.each(settingDataList, function (index_setting, row_setting) {
                            
                            if(row_setting['Name'] == 'Title') {
                                if(row_setting['Value'] != '' ) {
                                    window.parent.document.getElementById('appTitle').innerHTML = row_setting['Value']
                                } 
                            }

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
                            
                            if(row_setting['Name'] == 'DownloadButtonUrl') {
                                let imagePath = ''
                                if (row_setting['Value'].includes("https://drive.google.com")) {
                                    let imgid = row_setting['Value'].split('https://drive.google.com')[1].split('/')[3];
                                    //imagePath = '../sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();
                                    imagePath = '../sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + UIVersion;
                                } else {
                                    // Cache Image
                                    let name = row_setting['Value'].split('/')
                                    let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                                    // image from spreadsheet id folder
                                    //imagePath = '../sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
                                    imagePath = '../sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + UIVersion;
                                }
                                document.getElementById('downloadBtn').src = imagePath
                            }

                            if(row_setting['Name'] == 'TimeoutSec') {
                                if(row_setting['Value'] == '' ) {
                                    idleTimeOut = 0
                                } else {
                                    idleTimeOut = row_setting['Value']
                                }
                            }

                            // Change App Icon
                            if(row_setting['Name'] == 'AppIconImageUrl') {
                                if(row_setting['Value'] != '' ) {

                                    let appIconPath = ''
                                    if (row_setting['Value'].includes("https://drive.google.com")) {
                                        let imgid = row_setting['Value'].split('https://drive.google.com')[1].split('/')[3];
                                        //imagePath = '../sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();
                                        appIconPath = '../../sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + UIVersion;
                                    } else {
                                        // Cache Image
                                        let name = row_setting['Value'].split('/')
                                        let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                                        // image from spreadsheet id folder
                                        //imagePath = '../sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
                                        appIconPath = '../../sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + UIVersion;
                                    }

                                    window.parent.document.getElementById('appIconiOS').href = appIconPath;
                                    window.parent.document.getElementById('appIconAndroid').href = appIconPath; 
                                    
                                } 
                            }

                            // Other Icons
                            // BERRY
                            /* if(row_setting['Name'] == '[BERRY]') {
                                if (row_setting['Value'].includes("https://drive.google.com")) {
                                    let imgid = row_setting['Value'].split('https://drive.google.com')[1].split('/')[3];
                                    berryImgPath = '../sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();
                                } else {
                                    // Cache Image
                                    let name = row_setting['Value'].split('/')
                                    let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                                    // image from spreadsheet id folder
                                    berryImgPath = '../sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
                                }
                            }
                            // DICE
                            if(row_setting['Name'] == '[DICE]') {
                                if (row_setting['Value'].includes("https://drive.google.com")) {
                                    let imgid = row_setting['Value'].split('https://drive.google.com')[1].split('/')[3];
                                    diceImgPath = '../sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();
                                } else {
                                    // Cache Image
                                    let name = row_setting['Value'].split('/')
                                    let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                                    // image from spreadsheet id folder
                                    diceImgPath = '../sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
                                }
                            }
                            // BUG
                            if(row_setting['Name'] == '[BUG]') {
                                if (row_setting['Value'].includes("https://drive.google.com")) {
                                    let imgid = row_setting['Value'].split('https://drive.google.com')[1].split('/')[3];
                                    bugImgPath = '../sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();
                                } else {
                                    // Cache Image
                                    let name = row_setting['Value'].split('/')
                                    let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                                    // image from spreadsheet id folder
                                    bugImgPath = '../sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
                                }
                            }
                            // NUT
                            if(row_setting['Name'] == '[NUT]') {
                                if (row_setting['Value'].includes("https://drive.google.com")) {
                                    let imgid = row_setting['Value'].split('https://drive.google.com')[1].split('/')[3];
                                    nutImgPath = '../sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();
                                } else {
                                    // Cache Image
                                    let name = row_setting['Value'].split('/')
                                    let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                                    // image from spreadsheet id folder
                                    nutImgPath = '../sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
                                }
                            }
                            // OOPS
                            if(row_setting['Name'] == '[OOPS]') {
                                if (row_setting['Value'].includes("https://drive.google.com")) {
                                    let imgid = row_setting['Value'].split('https://drive.google.com')[1].split('/')[3];
                                    oopsImgPath = '../sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();
                                } else {
                                    // Cache Image
                                    let name = row_setting['Value'].split('/')
                                    let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                                    // image from spreadsheet id folder
                                    oopsImgPath = '../sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
                                }
                            } */
                        })
                        /////////////////////MENU JSON START////////////////////////
                        setTimeout(function() {
                            if(fromParent == '') {
                                loadMenuJSON()
                                //loadBggGameInfo();
                                //LoadStatsData();
                                
                                // EnameTim out events
                                EnableTimeoutEvents();
                            }
                        }, 500) 
                    }
                },
                error: function(e) {
                    logLoadMsg('<br><font color="red">Error: Missing Sheet : Settings</font><br>')
                    document.getElementById("spinnerBox").style.display = 'none'
                }
            })
            // Clear memory
            settingRequest.onreadystatechange = null;
            settingRequest.abort = null;
            settingRequest = null;
        }, 1000)
    }
    //////////////////////TAGS START///////////////////////////////////
    // loadTagsData is defined in zapsheetsCore.js
    //////////////////////SPLASH START///////////////////////////////////
    /**
     * loadTagsData
     */
    function loadSplashData() {
        // Loading tags.json
        setTimeout(function() {
            var settingRequest = $.ajax({
                url: jasonPath + 'sheets/' + sheet_Id + "/splash-" + activeLang.toLowerCase() + ".json?version=" + UIVersion,
                cache: true,
                type: 'GET',
                dataType: "text",
                success: function (response) {
                    if(response.length == 0) {
                        logLoadMsg('<font color="red">Error: Tags data not available.' + "</font><br>")
                    } else { 
                        splashDataList = []
                        var mResponseSet = response.replace(/�/g, "") 
                        var newSettingData = eval(mResponseSet)
                        for(var i=0; i<newSettingData.length; i++) {
                            var settingDataSting = JSON.stringify(newSettingData[i]);
                            if(isJSONData(settingDataSting) == false) {
                                logLoadMsg('<font color="red">Error: Tags Sheet : (Row: ' + i + ")</font><br>")
                            } else {
                                splashDataList[i] = isJSONData(settingDataSting)
                            }
                        }
                        /////////////////////LANG SETTINGS START///////////////////////////
                        // Create splash screen timeline
                        let splashData = ''
                        $.each(splashDataList, function (index_setting, row_setting) {
                            let contentPath = ''
                            if (row_setting['Content'].includes("https://drive.google.com")) {
                                let imgid = row_setting['Content'].split('https://drive.google.com')[1].split('/')[3];
                                if(row_setting['Type'] == "Image") {
                                    contentPath = '../sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + UIVersion;
                                } else {
                                    contentPath = '../sheets/' + sheet_Id + '/cacheImages/' + imgid + '.mp4?version=' + UIVersion;
                                }
                            } else {
                                // Cache Image
                                let name = row_setting['Content'].split('/')
                                let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                                contentPath = '../sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + UIVersion;
                            }
                            splashData += 
                            `<div class="splashSlide" id='t_${index_setting}' style='position: absolute;
                                left: 50%; top: 50%; height: 100vh; width: 100vw; transform: translateY(-50%) translateX(-50%); z-index: 0; object-fit: cover; opacity:1; background-color:#2D2C2B' data-delay=${row_setting["DelaySec"]} >
                                ${row_setting['Type'].toLowerCase() == 'image' ?
                                    `<img id='img_${index_setting}' src='${contentPath}' style='position: relative;
                                left: 50%; top: 50%; height: 100vh; width: 100vw; transform: translateY(-50%) translateX(-50%); z-index: 0; object-fit: cover;'></img>`
                                : 
                                    `<video id='vid_${index_setting}' src="${contentPath}" muted style='position: relative;
                                left: 50%; top: 50%; height: 100vh; width: 100vw; transform: translateY(-50%) translateX(-50%); z-index: 0; object-fit: cover;' playsinline></video>`
                                }
                                
                            </div>`
                        })
                        // Add element
                        document.getElementById('splashScreen').innerHTML = splashData;
                    }
                },
                error: function(e) {
                    logLoadMsg('<br><font color="red">Error: Missing Sheet : Splash</font><br>')
                    document.getElementById("spinnerBox").style.display = 'none'
                }
            })
            // Clear memory
            settingRequest.onreadystatechange = null;
            settingRequest.abort = null;
            settingRequest = null;
        }, 1000)
    }
    /////////////////////////////////////////////////////////////////////////
    /**
     * ShowSplashSlides
     */
    function ShowSplashSlides() {
        let i;
        let slides = document.getElementsByClassName("splashSlide");

        // Hide all slides
        for (i = 0; i < slides.length; i++) {
            slides[i].style.display = "none";
        }

        // Move to the next slide
        slideIndex++;
        if (slideIndex > slides.length) {slideIndex = 1}
        let currentSlide = slides[slideIndex-1];
        currentSlide.style.display = "block"; // Display the current slide
        // Play if its a video element
        let animType = currentSlide.children[0].id.split("_")
        if(animType[0] == "vid") {
            var videoElement = document.getElementById('vid_' + animType[1]);
            videoElement.play();
        }
        // Get the custom delay from the data-delay attribute, default to 5000ms if not specified
        let delay = parseInt(currentSlide.getAttribute("data-delay")) * 1000 || 5000;
        // Clear any existing timeout and set a new one for the next slide
        clearTimeout(slideshowTimeout);
        // Use setTimeout to call ShowSplashSlides again after the specified delay
        slideshowTimeout = setTimeout(ShowSplashSlides, delay);
    }
    /////////////////////////////////////////////////////////////////////////
    /**
     * HideSplashSlides
     */
    function HideSplashSlides() {
        clearTimeout(slideshowTimeout);
        let slides = document.getElementsByClassName("splashSlide");
        // Hide all slides
        for (i = 0; i < slides.length; i++) {
            slides[i].style.display = "none";
            let animType = slides[i].children[0].id.split("_")
            if(animType[0] == "vid") {
                var videoElement = document.getElementById('vid_' + animType[1]);
                videoElement.pause();
                videoElement.currentTime = 0;
            }
        }
    }
    /////////////////////////////////////////////////////////////////////////
    /**
     * loadBggGameInfo
     */
    function loadBggGameInfo() {
        var langRequest = $.ajax({
            //url: '../sheets/' + sheet_Id + "/game.json?version=" + UIVersion,
            url: jasonPath + 'sheets/' + sheet_Id + "/game-" + activeLang.split('-')[0].toLowerCase() + ".json?version=" + UIVersion,
            cache: true, 
            type: 'GET',
            dataType: "text",
            success: function (response) {
                if(response.length == 0) {
                    activeLang = "EN"
                    loadBggGameInfo()
                } else { 
                    bggInfoData = []
                    var mResponseLang = response.replace(/�/g, "") 
                    var newLangData = eval(mResponseLang)
                    for(var i=0; i<newLangData.length; i++) {
                        var langDataSting = JSON.stringify(newLangData[i]);
                        if(isJSONData(langDataSting) == false) {
                            logLoadMsg('<font color="red">Error: ' + activeLang.split('-')[0] + ' Sheet : (Row: ' + i + ")</font><br>")
                        } else {
                            bggInfoData[i] = isJSONData(langDataSting)
                        }
                    }

                }
            },
            error: function (response) {
                /* if(activeLang.toLowerCase() != 'EN') {
                    langLoadCount++
                    loadBggGameInfo()
                } else { */
                    langLoadCount = 0;
                    activeLang = "EN"
                    loadBggGameInfo()
                    /* logLoadMsg('<font color="red">Error: Missing Sheet : bgg-' + activeLang + '</font><br>')
                    document.getElementById("spinnerBox").style.display = 'none'
                } */
            }
        })
        langRequest.onreadystatechange = null;
        langRequest.abort = null;
        langRequest = null;
    }
    /////////////////////////////////////////////////////////////////////////
    // Loading Menu JSON
    /**
     * loadMenuJSON
     */
    function loadMenuJSON() {
        // Loading steps json
        var langRequest = $.ajax({
            //url: '../sheets/' + sheet_Id + "/menu-" + activeLang.split('-')[0].toLowerCase() + ".json?version=" + UIVersion, 
            url: jasonPath + 'sheets/' + sheet_Id + "/menu-" + activeLang.split('-')[0].toLowerCase() + ".json?version=" + UIVersion, 
            /* cache: false, */
            cache: true, 
            //async: false,
            type: 'GET',
            dataType: "text",
            success: function (response) {
                if(response.length == 0) {
                    activeLang = "EN"
                    loadMenuJSON()
                } else { 
                    languageStepsData = []
                    var mResponseLang = response.replace(/�/g, "") 
                    var newLangData = eval(mResponseLang)
                    for(var i=0; i<newLangData.length; i++) {
                        var langDataSting = JSON.stringify(newLangData[i]);
                        if(isJSONData(langDataSting) == false) {
                            logLoadMsg('<font color="red">Error: ' + activeLang.split('-')[0] + ' Sheet : (Row: ' + i + ")</font><br>")
                        } else {
                            languageStepsData[i] = isJSONData(langDataSting)
                        }
                    }
                    // convert values to all defined format
                    if(lazyLoadImages == "FALSE" || lazyLoadImages == "False" || lazyLoadImages == "false" || lazyLoadImages == "0") {
                        PreloadAllToCache();
                    } else {

                        // Loading moved here from settings block..
                        loadBggGameInfo();
                        LoadStatsData();

                        jumpToMenuScreen()
                    }
                }
            },
            error: function (response) {
                /* if(activeLang.toLowerCase() != 'EN' && langLoadCount < 1) {
                    langLoadCount++
                    loadLanguageJSON()
                } else { */
                    langLoadCount++;
                    if(langLoadCount < 5) {
                        activeLang = "EN"
                        loadMenuJSON()
                    } else {
                        logLoadMsg('<font color="red">Error: Missing Sheet : menu-' + activeLang.toLowerCase() + '</font><br>')
                        document.getElementById("spinnerBox").style.display = 'none'
                    }
                 /* } */
            }
        })
        langRequest.onreadystatechange = null;
        langRequest.abort = null;
        langRequest = null;
    }
    /////////////////////////////////////////////////////////////////////////////////
    /**
     * getUrlVars
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
    //////////////////////////////////////////////////////////////////////////////////
    /**
     * PreloadAllToCache
     */
    function PreloadAllToCache() {
        var imgLoaded = 0
        // Caching images
        if(getAllImageCount() > 0) {
            /* $.each(languageStepsData, function (i, row_setting) {
                if(languageStepsData[i]['Image'] != '') {
                    if (languageStepsData[i]['Image'].includes("https://drive.google.com")) {
                        let imgid = languageStepsData[i]['Image'].split('https://drive.google.com')[1].split('/')[3];
                        let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
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
                                // New to show only When load completed
                                preCacheImages[i].onload = checkImageStatus(imgLoaded)
                            } else {
                                imgLoaded++
                                showMessageInfo(imgLoaded)
                            }
                        })
                    } else {
                        let name = languageStepsData[i]['Image'].split('/')
                        let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                        // cacheImages moved to spreadsheet id folder
                        let imagePath = './sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();

                        checkIfImageExists(imagePath, (isExists) => {
                            if(isExists) {
                                let mapImage = new Image();
                                mapImage.src = imagePath
                                document.getElementById('stepBGInage').src = imagePath;
                                // To Precache
                                preCacheImages[i] = new Image()
                                preCacheImages[i].src = imagePath
                                imgLoaded++
                                // New to show only When load completed
                                preCacheImages[i].onload = checkImageStatus(imgLoaded)
                            } else {
                                imgLoaded++
                                showMessageInfo(imgLoaded)
                            }
                        })
                    }
                } 
            }) */
        } else {
            showMessageInfo(imgLoaded)
        }
        setTimeout(function() {
        }, 4000)
    }
    ///////////////////////////////////////////////////////////////////////////////////////
    /**
     * checkImageStatus
     * @param {*} imgCount 
     */
    function checkImageStatus(imgCount) {
        showMessageInfo(imgCount)
    }
    ///////////////////////////////////////////////////////////////////////////////////////
    /**
     * jumpToMenuScreen
     */
    function jumpToMenuScreen() {
        setTimeout(function() {
            // Preload All Images in background
            PreloadAllToCacheInBackground()
        }, 10)
        // Auto fill default sections
        // Check motion
        
        if(fromParent == 'app' || fromParent == 'web') {
            setTimeout(function() {
                if(sheetInnerParam == 'steps') {
                    $("#menuScreen").fadeOut();
                } else {
                    $("#menuScreen").fadeIn();
                }
                generateMenuButtons()
            }, 0)
        }
    }
    ///////////////////////////////////////////////////////////////////////////////////////
    /**
     * PreloadAllToCacheInBackground
     */
    function PreloadAllToCacheInBackground() {
        var imgLoaded = 0
        $.each(languageStepsData, function (i, row_setting) {
            if(languageStepsData[i]['ID'] == 'BACKGROUND_IMG') {
                if (languageStepsData[i]['ID'].includes("https://drive.google.com")) {
                    let imgid = languageStepsData[i]['Image'].split('https://drive.google.com')[1].split('/')[3];
                    let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                    // image from spreadsheet id folder
                    //let imagePath = '../sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();
                    let imagePath = '../sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + UIVersion;
                    checkIfImageExists(imagePath, (isExists) => {
                        if(isExists) {
                            let bgImage = new Image();
                            bgImage.src = imagePath
                            // To Precache
                            preCacheImages[i] = new Image()
                            preCacheImages[i].src = imagePath
                            imgLoaded++
                            // New to show only When load completed
                            preCacheImages[i].onload = checkImageLoadStatus(imgLoaded)
                        } else {
                            imgLoaded++
                            showCacheMessageInfo(imgLoaded)
                        }
                    })
                    //document.getElementById('menuBGInage').src = imagePath;
                    loadImage(imagePath)
                } else {
                    // Cache Image
                    let name = languageStepsData[i]['Image'].split('/')
                    let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                    // image from spreadsheet id folder
                    //let imagePath = '../sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
                    let imagePath = '../sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + UIVersion;
                    checkIfImageExists(imagePath, (isExists) => {
                        if(isExists) {
                            let mapImage = new Image();
                            mapImage.src = imagePath
                            // To Precache
                            preCacheImages[i] = new Image()
                            preCacheImages[i].src = imagePath
                            imgLoaded++
                            // New to show only When load completed
                            preCacheImages[i].onload = checkImageLoadStatus(imgLoaded)
                        } else {
                            imgLoaded++
                            showCacheMessageInfo(imgLoaded)
                        }
                    })
                    loadImage(imagePath)
                }
            } 
        })
    }
    ///////////////////////////////////////////////////////////////////////////////////////
    /**
     * checkImageLoadStatus
     * @param {*} imgCount 
     */
    function checkImageLoadStatus(imgCount) {
        showCacheMessageInfo(imgCount)
    }
    ///////////////////////////////////////////////////////////////////////////////////////
    /**
     * showCacheMessageInfo
     * @param {*} _count 
     */
    function showCacheMessageInfo(_count) {
        // For Log Messages
        $.each(settingDataList, function (index_setting, row_setting) {
            if(row_setting['Name'] == 'Version') {
                document.getElementById('versionInfo').innerHTML = _version + " - " + row_setting["Value"] + " - " + activeLang + "<br>";
                document.getElementById('versionInfo').innerHTML += "Caching Images (" + _count + "/" + getAllImageCount() + ")<br>";
            }
        })
        if(_count == getAllImageCount()) {
            preCachedDone = true;
        }
    }
    ///////////////////////////////////////////////////////////////////////////////////////
    /**
     * showMessageInfo
     * @param {*} _count 
     */
    function showMessageInfo(_count) {
        // For Log Messsage
        $.each(settingDataList, function (index_setting, row_setting) {
            if(row_setting['Name'] == 'Version') {
                document.getElementById('versionInfo').innerHTML = _version + " - " + row_setting["Value"] + " - " + activeLang + "<br>";
                document.getElementById('versionInfo').innerHTML += "Caching Images (" + _count + "/" + getAllImageCount() + ")";
            }
        })
        ////////////////////////////////////////////////////////////////////////
        if(_count == getAllImageCount()) {
            preCachedDone = true;
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
                            // image from spreadsheet id folder
                            //imagePathPrev = '../sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();
                            imagePathPrev = '../sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + UIVersion;
                        } else {
                            // Cache Image
                            let name = row_setting['Value'].split('/')
                            let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                            // image from spreadsheet id folder
                            //imagePathPrev = '../sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
                            imagePathPrev = '../sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + UIVersion;
                        }
                    }
                }

                if(row_setting['Name'] == 'NextButtonUrl') {
                    if(row_setting['Value'] != '') {
                        let imagePathNext = ''
                        if (row_setting['Value'].includes("https://drive.google.com")) {
                            let imgid = row_setting['Value'].split('https://drive.google.com')[1].split('/')[3];
                            let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                            // image from spreadsheet id folder
                            //imagePathNext = '../sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();
                            imagePathNext = '../sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + UIVersion;
                        } else {
                            // Cache Image
                            let name = row_setting['Value'].split('/')
                            let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                            // image from spreadsheet id folder
                            //imagePathNext = '../sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
                            imagePathNext = '../sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + UIVersion;
                        }
                    }
                }
                if(row_setting['Name'] == 'QuitButtonUrl') {
                    if(row_setting['Value'] != '') {
                        let imagePathQuit = ''
                        if (row_setting['Value'].includes("https://drive.google.com")) {
                            let imgid = row_setting['Value'].split('https://drive.google.com')[1].split('/')[3];
                            let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                            // image from spreadsheet id folder
                            //imagePathQuit = '../sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();
                            imagePathQuit = '../sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + UIVersion;
                        } else {
                            // Cache Image
                            let name = row_setting['Value'].split('/')
                            let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                            // image from spreadsheet id folder
                            //imagePathQuit = '../sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
                            imagePathQuit = '../sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + UIVersion;
                        }
                    }
                }
            })
            setTimeout(function() {
            }, 500)
        } else {
        }
    }
    ///////////////////////////////////////////////////////////////////////
    /**
     * getAllImageCount
     * @returns 
     */
    function getAllImageCount() {
        var tempCount = 0
        $.each(languageStepsData, function (i, row) {
            if(languageStepsData[i].Image != '') {
                tempCount++
            } 
        })
        return tempCount;
    }
    //////////////////////////////////////////////////////////////////////
    /**
     * checkIfImageExists
     * @param {*} url 
     * @param {*} callback 
     */
    function checkIfImageExists(url, callback) {
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
    ////////////////////////////////////////////////////////////////////////////////////
    /**
     * stop()
     */
    function stop() {
        clearInterval(timerInterval);
    }
    ////////////////////////////////////////////////////////////////////////////////////
    /**
     * reset() for times up 
     */
    function reset() {
      clearInterval(timerInterval);
      resetVars();
    }
    ////////////////////////////////////////////////////////////////////////////////////
    /**
     * loadImage
     * @param {*} imageURI 
     */
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
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * showProgressBar
     */
    function showProgressBar() {
        if(preCachedDone == false) {
            document.getElementById('spinnerMiddleBox').style.display = 'block'
        } else {
            document.getElementById('spinnerMiddleBox').style.display = 'none'
        }
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} e 
     */
    function updateProgressBar(e) {
        if (e.lengthComputable) {
        }
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * showImage
     */
    function showImage() {
        var imageElement = "data:image/jpeg;base64," + base64Encode(request.responseText);
        // Fade Out
        $("#menuScreen").fadeOut();
        if(fromParent == 'app' || fromParent == 'web' || (sheetInnerParam == 'faqs' || sheetInnerParam == 'faq') || (sheetInnerParam == 'steps' || sheetInnerParam == 'step')) {
            document.getElementById('menuScreen').style.display = 'none'
        } else {
            document.getElementById('menuScreen').style.display = 'block'
        }
        document.getElementById('menuBGInage').src = imageElement;

        // Check for loading
        if(fromParent != 'app') {
            setTimeout(function() {
                if(sheetInnerParam == 'steps') {
                    $("#menuScreen").fadeOut();
                } else {
                    $("#menuScreen").fadeIn();
                }
                generateMenuButtons()
            }, 10)
        }
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * generateMenuButtons
     */
    function generateMenuButtons() {
        let buttonElement = ''
        for(var i=0; i<languageStepsData.length; i++) {
            if(languageStepsData[i].Type == 'button') {
                buttonElement += `<div id="viewIconContainer" style="-webkit-touch-callout: none; user-select: none; -moz-user-select: none; -webkit-user-select: none; width: 100%; height: 7vh; border-radius: 15px; background-color: rgba(247, 174, 79, 0.8); position: relative; margin-bottom: 2vh; cursor: pointer;">
                <p id="menuButtonLabel_${i}" style="position: relative;
                top: 50%;
                width: 95%;
                height: auto;
                left: 50%;
                z-index: 999999;
                text-align: center;
                line-height: 1;
                font-size: 2.5vh;
                letter-spacing: 0px;
                color: white;
                font-family: AcuminVariableConcept;
                font-weight: 800;
                transform: translateX(-50%) translateY(-50%);
                -webkit-user-select: none;
                -ms-user-select: none;
                user-select: none;
                overflow: hidden;
                word-break: break-all;
                margin-top: .3vh;
                cursor: pointer;">
                </p>
              </div>`
            }
        }
        document.getElementById('viewIconText').innerHTML = buttonElement


        // Load Pages based on Param
        if(sheetInnerParam != 'steps') {
            if(sheetInnerParam.length < 10) {
                ShowAutoMenuPages(sheetInnerParam)
            }
        }
        setTimeout(function() {
            for(var i=0; i<languageStepsData.length; i++) {
                if(languageStepsData[i].Type == 'button') {
                    adjustFontSizeMultiple(languageStepsData[i].Text, document.getElementById('menuButtonLabel_' + i), 1, 'learnPlay')
                    // Add events
                    document.getElementById('menuButtonLabel_' + i).addEventListener('touchstart', onMenuTouchStart)
                    document.getElementById('menuButtonLabel_' + i).addEventListener('touchend', onMenuTouchEnd)

                    document.getElementById('menuButtonLabel_' + i).addEventListener('mousedown', onMenuTouchStart)
                    document.getElementById('menuButtonLabel_' + i).addEventListener('mouseup', onMenuTouchEnd)
                    document.getElementById('menuButtonLabel_' + i).addEventListener('mouseout', onMenuTouchOut)

                }
            }

            // Info Icon Button events
            document.getElementById('infoIconBtn').addEventListener('touchstart', onAnimateBtnTouchStart)
            document.getElementById('infoIconBtn').addEventListener('touchend', onAnimateBtnTouchEnd)

            document.getElementById('infoIconBtn').addEventListener('mousedown', onAnimateBtnTouchStart)
            document.getElementById('infoIconBtn').addEventListener('mouseup', onAnimateBtnTouchEnd)
            document.getElementById('infoIconBtn').addEventListener('mouseout', onAnimateBtnTouchOut)

            // On menuBGInage Touch
            document.getElementById('menuScreen').addEventListener('touchstart', onMenuScreenTouchStart)
            document.getElementById('menuScreen').addEventListener('touchend', onMenuScreenTouchEnd)

            document.getElementById('menuScreen').addEventListener('mousedown', onMenuScreenTouchStart)
            document.getElementById('menuScreen').addEventListener('mouseup', onMenuScreenTouchEnd)
            document.getElementById('menuScreen').addEventListener('mouseout', onMenuScreenTouchOut)

            // Rules Navigation events
            document.getElementById('nextElement').addEventListener('touchstart', onRuleItemTouchStart)
            document.getElementById('nextElement').addEventListener('touchend', onRuleItemTouchEnd)
            document.getElementById('nextElement').addEventListener('mousedown', onRuleItemTouchStart)
            document.getElementById('nextElement').addEventListener('mouseup', onRuleItemTouchEnd)
            document.getElementById('nextElement').addEventListener('mouseout', onRuleItemTouchEnd)

            document.getElementById('nextArrow').addEventListener('touchstart', onRuleItemTouchStart)
            document.getElementById('nextArrow').addEventListener('touchend', onRuleItemTouchEnd)
            document.getElementById('nextArrow').addEventListener('mousedown', onRuleItemTouchStart)
            document.getElementById('nextArrow').addEventListener('mouseup', onRuleItemTouchEnd)
            document.getElementById('nextArrow').addEventListener('mouseout', onRuleItemTouchEnd)

            document.getElementById('prevElement').addEventListener('touchstart', onRuleItemTouchStart)
            document.getElementById('prevElement').addEventListener('touchend', onRuleItemTouchEnd)
            document.getElementById('prevElement').addEventListener('mousedown', onRuleItemTouchStart)
            document.getElementById('prevElement').addEventListener('mouseup', onRuleItemTouchEnd)
            document.getElementById('prevElement').addEventListener('mouseout', onRuleItemTouchEnd)

            document.getElementById('prevArrow').addEventListener('touchstart', onRuleItemTouchStart)
            document.getElementById('prevArrow').addEventListener('touchend', onRuleItemTouchEnd)
            document.getElementById('prevArrow').addEventListener('mousedown', onRuleItemTouchStart)
            document.getElementById('prevArrow').addEventListener('mouseup', onRuleItemTouchEnd)
            document.getElementById('prevArrow').addEventListener('mouseout', onRuleItemTouchEnd)

            // Show steps
            if(sheetInnerParam == 'steps' || sheetInnerParam == 'step') {
                document.getElementById('downloadBtn').style.display = 'none'
                document.getElementById('menuPage').style.display = 'block'
                document.getElementById('menuPage').style.backgroundColor = '#F9F3E3'
                
                //document.getElementById('contentSteps').style.display = 'block'
                document.getElementById('menuTopContainer').style.display = 'none'
                LoadGameSteps();
            }

        }, 250)
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * RemoveMenuScreenListner
     */
    function RemoveMenuScreenListner() {
        document.getElementById('menuScreen').removeEventListener('touchstart', onMenuScreenTouchStart)
        document.getElementById('menuScreen').removeEventListener('touchend', onMenuScreenTouchEnd)

        document.getElementById('menuScreen').removeEventListener('mousedown', onMenuScreenTouchStart)
        document.getElementById('menuScreen').removeEventListener('mouseup', onMenuScreenTouchEnd)
        document.getElementById('menuScreen').removeEventListener('mouseout', onMenuScreenTouchOut)
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * AddMenuScreenListner
     */
    function AddMenuScreenListner() {
        document.getElementById('menuScreen').addEventListener('touchstart', onMenuScreenTouchStart)
        document.getElementById('menuScreen').addEventListener('touchend', onMenuScreenTouchEnd)

        document.getElementById('menuScreen').addEventListener('mousedown', onMenuScreenTouchStart)
        document.getElementById('menuScreen').addEventListener('mouseup', onMenuScreenTouchEnd)
        document.getElementById('menuScreen').addEventListener('mouseout', onMenuScreenTouchOut)
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} event 
     */
    function onMenuScreenTouchStart(event) {
        event.preventDefault();
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} event 
     */
    function onMenuScreenTouchEnd(event) {
        event.preventDefault();
        RemoveMenuScreenListner();

        // Animate menuItems
        AnimateMenuBtns()

    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * AnimateMenuBtns
     */
    function AnimateMenuBtns() {
        document.getElementById('touchSection').style.display = 'none'
        document.getElementById('buttonContainer').style.bottom = '10vh'
        document.getElementById('buttonContainer').style.opacity = '0'
        document.getElementById('viewIconText').style.display = 'flex'
        document.getElementById('infoBGGSection').style.display = 'flex'
        // Apply the animation
        $("#buttonContainer").animate({bottom: '15vh', opacity: '1'}, 300);
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * ShowSplashScreen
     */
    function ShowSplashScreen() {

        if(fromParent == 'app' || fromParent == 'web') {return}

        // Start timeline animation
        //timeLine.seek(0);
        //timeLine.resume();


        $("#menuScreen").fadeIn();
        document.getElementById('splashScreen').style.display = 'block';
        ShowSplashSlides();
        //timeLine.resume();

        document.getElementById('touchSection').style.display = 'flex'
        document.getElementById('buttonContainer').style.bottom = '15vh'
        document.getElementById('viewIconText').style.opacity = '1'
        document.getElementById('viewIconText').style.display = 'none'
        document.getElementById('infoBGGSection').style.display = 'none'
        document.getElementById('menuDetailsPage').style.display = 'none'
        //document.getElementById('menuPage').style.display = 'none'

        HideIFrame();
        // Add menu event listener
        AddMenuScreenListner();

        
        
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} event 
     */
    function onMenuScreenTouchOut(event) {
        event.preventDefault();
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} event 
     */
    function onAnimateBtnTouchEnd(event) {
        event.preventDefault();

        let touchStatus = handleTouchEnd(event);
        if(touchStatus == false) {
            return;
        }

        document.getElementById('downloadBtn').style.display = 'none'
        document.getElementById('contentSteps').style.display = 'none'
        document.getElementById('menuTopContainer').style.display = 'flex'
        if(statsDataList && statsDataList.boardgame) {
            FillBGGScreenData(statsDataList)
        }
        
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * LoadStatsData
     */
    function LoadStatsData() {
        setTimeout(function() {
            var StatsRequest = $.ajax({
                //url: '../sheets/' + sheet_Id + "/bgg.json?version=" + UIVersion,
                url: jasonPath + 'sheets/' + sheet_Id + "/bgg.json?version=" + UIVersion,
                cache: true,
                type: 'GET',
                dataType: "JSON",
                success: function (response) {
                    if(!response || (Array.isArray(response) && response.length == 0)) {
                        logLoadMsg('<font color="red">Error: Stats data not available.' + "</font><br>")
                    } else {
                        statsDataList = response
                    }
                },
                error: function(e) {
                    logLoadMsg('<br><font color="red">Error: Missing Sheet : stats</font><br>')
                    document.getElementById("spinnerBox").style.display = 'none'
                }
            })
            // Clear memory
            StatsRequest.onreadystatechange = null;
            StatsRequest.abort = null;
            StatsRequest = null;
        }, 1000)
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} data 
     */
    function FillBGGScreenData(data) {
        if (!data || !data.boardgame || !data.boardgame[0]) {
            logLoadMsg('<font color="red">Error: BGG stats data not available.</font><br>')
            return;
        }
        document.getElementById('small-sub').innerHTML = ''
        let objectid = data.boardgame[0].boardgame['@attributes']['objectid'];
        let gameName = getBoardGameName(data)
        let boardGameDesigner = getBoardGameDesigner(data);
        let boardGameDesignerTitle = boardGameDesigner == undefined ? '' : boardGameDesigner
        let yearPublish = (data.boardgame[0].boardgame.yearpublished == undefined || data.boardgame[0].boardgame.yearpublished == 0 ) ? '' : data.boardgame[0].boardgame.yearpublished

        let name = data.boardgame[0].boardgame.image.split('/')
        let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
        let iPath = '../sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + UIVersion;
        let imagePath = data.boardgame[0].boardgame.image == undefined ? '../images/earshot-games_splash.png' : iPath
        let playerStats = (data.minplayers == data.boardgame[0].boardgame.maxplayers) ? data.boardgame[0].boardgame.maxplayers : data.boardgame[0].boardgame.minplayers+"-"+data.boardgame[0].boardgame.maxplayers;
        let playtimeStats = (data.boardgame[0].boardgame.minplaytime == data.boardgame[0].boardgame.maxplaytime) ? data.boardgame[0].boardgame.maxplaytime : data.boardgame[0].minplaytime+"-"+data.boardgame[0].boardgame.maxplaytime; 

        let gameRatingAverage = getBoardGameRatingAverage(data);
        let ratingInPercentage = ((gameRatingAverage - 5)*5/3) * 20;


        if(ratingInPercentage < 0) {
            ratingInPercentage = 0
        } else if(ratingInPercentage > 100) {
            ratingInPercentage = 100
        }

        // Weight and Game Price Value
        let weightValue = '';
        let valueWeight = 0;
        let gamePrice = 0;
        $.each(bggInfoData, function (i, row_bgg) {
            if(row_bgg['Name'] == "Weight") {
                let weightNumber = Number(row_bgg['Value']);
                valueWeight = (weightNumber / 5) * 100;
                if(weightNumber > 4) {
                    weightValue = "HEAVY"
                } else if(weightNumber > 3 && weightNumber <= 4) {
                    weightValue = "MEDIUM HEAVY"
                } else if(weightNumber > 2 && weightNumber <= 3) {
                    weightValue = "MEDIUM"
                } else if(weightNumber > 1 && weightNumber <= 2) {
                    weightValue = "MEDIUM LIGHT"
                } else if(weightNumber > 0 && weightNumber <= 1) {
                    weightValue = "LIGHT"
                }
            }
            if(row_bgg['Name'] == "Price") {
                gamePrice = row_bgg['Value']
            }
        }) 
        
        //FillSelectedMenuData('bggGame', gameName);
        document.getElementById('contentSteps').style.display = 'none'
        document.getElementById('downloadBtn').style.display = 'none'

        /* <h3 class="font-DINCondensed game-title text-primary">${gameName} <sup class="sup-small" data-objectid="${data.boardgame[0].objectid}">${yearPublish}</sup></h3>
        <h5 class="font-DINCondensed game-designer">${boardGameDesignerTitle}</h5> */

        // Fill small sub
        document.getElementById('small-sub').innerHTML = yearPublish;

        // Fill Game Data
        let gameHTML = `<div class="bg-light border-0 card game-card">
          <div class="card-body">
            <div class="row">
              <div class="col-lg-9 col-md-8">

                <div class="row mt-3">
                  <div class="col-md-4">
                    <div class="img-box"><img src="${imagePath}" class="img-fluid" loading="lazy"/></div>
                    <div class="gamePrivateDataHTML details-game-${objectid}-private-data"></div>
                  </div>
                                      
                </div>
              </div>

              <div style="display:flex; width: 100%; flex-direction: row; justify-content: space-between;">
                <div style="width:48%;">
                    <div style="100%; height: 3.5vh; position: relative; top: 3vh;background-color: #CCCCCC; border-radius: 0.4vh;">
                    <div style="width:${valueWeight.toFixed(0)}%; height: 3.5vh; position: relative; top: 0vh;background-color: #0F75BC; border-radius: 0.4vh;"></div>
                    <p style="position:absolute; width:100%; font-size:2.4vh; color:#FFFFFF;text-align:center; top:0">${weightValue}</p>
                    </div>
                    <div class="card text-left my-4">
                    <div class="card-header bg-alto py-1 px-2">MECHANICS</div>
                    <div class="card-body p-2" style="border: 0.2vh solid #93959840;"><div class="boardgamemechanic"> </div></div>
                    </div>

                    <div style="position: relative; display: flex; font-size: 3.5vh;  flex-direction: row; align-items: center; margin-bottom: 2vh;">
                        <div style="position: relative; width: 2vh; height: 2vh;background-color: #0F75BC; z-index: 9999; border-radius: 50%;margin-right: 1vh;"></div>
                        <p style="position: relative; color: white;">${gamePrice}</p>
                    </div>

                </div>

                <div style="width:50%; display: inline-block; text-align: end;">
                    <div class="metaGroup_details_filter">
                    <span>
                        <img src="../images/earshot-games_player.png" width="25vh" loading="lazy" style="position:relative; height:3vh; width:auto;" />${playerStats}</span><span><img src="../images/earshot-games_time.png" width="35vh" loading="lazy" style="position:relative; height:3vh; width:auto;" />${playtimeStats}
                    </span>
                    <span>
                        <img src="../images/earshot-games_age.png"  width="25vh" loading="lazy" style="position:relative; height:3vh; width:auto;" />${data.boardgame[0].boardgame.age}+
                    </span>
                    ${data.boardgame[0].boardgamemechanic != undefined && data.boardgame[0].boardgame.boardgamemechanic.includes("Cooperative Game") == true
                    ?
                    `<span>
                        <img src="../images/earshot-games_coop.png?version=1.4" width="25vh" style="margin-top:-4px" loading="lazy" style="position:relative; height:3vh; width:auto;" /></span>`
                    : ``}
                </div>
                <div id="${objectid}_d" class="ratingWrapper">
                <div id="star_d" class="ratingStar" style="width: ${ratingInPercentage}%"> 
                    <span>&#x2605;&#x2605;&#x2605;&#x2605;&#x2605;</span>
                </div> 
                </div>
                <div style="position: relative; width: 100%; display: flex; flex-direction: row; justify-content: flex-end; margin-top: 2vh;">
                    <a href="https://boardgamegeek.com/boardgame/${objectid}" target="_blank" rel="noopener noreferrer">
                        <img src="../images/btn_bgg_2.webp" style="width:7vh" alt="View on BoardGameGeek" loading="lazy" />
                    </a>
                </div>
                </div>
              </div>

              <div class="col-lg-3 col-md-4 text-md-right">
                <div class="col-md-8 text-desc" style="margin-top:-1vh; color:#FFFFFF">
                    <div class="font-DINCondensed-bgg text-large">${data.boardgame[0].boardgame.description}</div>                        
                </div>

              </div>
            </div>
          </div>
        </div>
        `


        let menuTitleText = ''
        document.getElementById('menuPage').style.display = 'block'
        menuTitleText = gameName + `<br><p class="bggTitle">${boardGameDesignerTitle}</p>`;
        document.getElementById('menuTitle').innerHTML = menuTitleText;
        // event listener back button
        document.getElementById('backToMenuBtn').addEventListener('touchstart', onBackMenuTouchStart)
        document.getElementById('backToMenuBtn').addEventListener('touchend', onBackMenuTouchEnd)

        document.getElementById('backToMenuBtn').addEventListener('mousedown', onBackMenuTouchStart)
        document.getElementById('backToMenuBtn').addEventListener('mouseup', onBackMenuTouchEnd)
        document.getElementById('menuList').innerHTML = gameHTML
        // Render Boardgame mechanics data
        boardgameMechanicData(data);
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} data 
     */
    function boardgameMechanicData(data) {
        var boardgameMechanic = '';
        if ($.isArray(data.boardgame[0].boardgame.boardgamemechanic)) {
            $.each(data.boardgame[0].boardgame.boardgamemechanic, function (gameMechanic, index) {
            boardgameMechanic += '<div style="line-height:1.1;">' + index + '</div>';
            });
        } else if(data.boardgame[0].boardgame.boardgamemechanic){
            boardgameMechanic = data.boardgame[0].boardgame.boardgamemechanic;
        }
        boardgameMechanic = (boardgameMechanic == '') ? 'No Mechanics' : boardgameMechanic;
        $('.boardgamemechanic').append(boardgameMechanic);
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} data 
     * @returns 
     */
    function getBoardGameDesigner(data) {
        if ($.isArray(data.boardgame[0].boardgame.boardgamedesigner)) {
            var boardgamedesigner = '';
            $.each(data.boardgame[0].boardgame.boardgamedesigner, function (index, value) {
            boardgamedesigner += (boardgamedesigner != '') ? ' & ' + value : value;
            });
            return boardgamedesigner;
        } else {
            return data.boardgame[0].boardgame.boardgamedesigner;
        }
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} data 
     * @returns 
     */
    function getBoardGameRatingAverage(data) {
        if(data == undefined) {return}
        var objectid = data.boardgame[0].boardgame['@attributes']['objectid'];
        return data.boardgameBasicData[objectid]['rating'];
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} data 
     * @returns 
     */
    function getBoardGameName(data) {
        var objectid = data.boardgame[0].boardgame['@attributes']['objectid'];
        return data.boardgameBasicData[objectid]['name'];
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} event 
     */
    function onAnimateBtnTouchStart(event) {
        event.preventDefault();
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} event 
     */
    function onAnimateBtnTouchOut(event) {
        event.preventDefault();
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} sheetParam 
     */
    function getMenuItemData(sheetParam) {
        let paramIndex = -1;
        for(var i=0; i<languageStepsData.length; i++) {
            if(languageStepsData[i].Type == 'button') {
                if(languageStepsData[i].Text.toLowerCase().indexOf(sheetParam) != -1) {
                    paramIndex = i
                }
            }
        }
        return paramIndex;
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} sheetParam 
     */
    function ShowAutoMenuPages(sheetParam) {
        let menuTitleText = ''
        document.getElementById('menuPage').style.display = 'block'
        if(fromParent == '') {
            let sheetParamData = getMenuItemData(sheetParam);
            if(languageStepsData[sheetParamData].Text.toLowerCase().indexOf('faq') != -1) {
                menuTitleText = languageStepsData[sheetParamData].Text.split(" ")[1]
            } else {
                menuTitleText = languageStepsData[sheetParamData].Text.split(" ")[1]
            }
            document.getElementById('menuTitle').innerHTML = menuTitleText;
            // Fill data based on menu clicked
            FillSelectedMenuData(languageStepsData[sheetParamData].Text, languageStepsData[sheetParamData].Next)
        } else {
            let dispTitle = ''
            if(sheetInnerParam == 'faqs' || sheetInnerParam == 'faq') {
                dispTitle = 'FAQs'
            } else {
                dispTitle = sheetInnerParam.toUpperCase();
            }
            document.getElementById('menuTitle').innerHTML = dispTitle;
            // Fill data based on menu clicked
            FillSelectedMenuData(sheetInnerParam.toUpperCase(), sheetInnerParam)
        }


        // Hiding Top container
        if(fromParent == 'web') {
            document.getElementById('menuTopContainer').style.display = 'none';
        }

        document.getElementById('backToMenuBtn').style.opacity = '1';
        // event listener back button
        document.getElementById('backToMenuBtn').addEventListener('touchstart', onBackMenuTouchStart)
        document.getElementById('backToMenuBtn').addEventListener('touchend', onBackMenuTouchEnd)

        document.getElementById('backToMenuBtn').addEventListener('mousedown', onBackMenuTouchStart)
        document.getElementById('backToMenuBtn').addEventListener('mouseup', onBackMenuTouchEnd)

        /* // Fill data based on menu clicked
        FillSelectedMenuData(languageStepsData[sheetParamData].Text, languageStepsData[sheetParamData].Next) */
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} event 
     */
    function onMenuTouchStart(event) {
        //event.preventDefault();
        if(event.cancelable) event.preventDefault();
        // Add pulsating effect
        event.target.parentElement.classList.add('pulse-button');
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} event 
     */
    function onMenuTouchOut(event) {
        event.preventDefault();
        event.target.parentElement.classList.remove('pulse-button');
    }
    ///////////////////////////////////////////////////////////////////////////////s
    /**
     * 
     * @param {*} event 
     */
    function onMenuTouchEnd(event) {
        event.preventDefault();
        event.target.parentElement.classList.remove('pulse-button');

        let touchStatus = handleTouchEnd(event);
        if(touchStatus == false) {
            return;
        }

        let menuTitleText = ''
        document.getElementById('menuPage').style.display = 'block'
        if(languageStepsData[event.target.id.split('_')[1]].Text.toLowerCase().indexOf('faq') != -1) {
            menuTitleText = languageStepsData[event.target.id.split('_')[1]].Text.split(" ")[1]
        } else {
            menuTitleText = languageStepsData[event.target.id.split('_')[1]].Text.split(" ")[1]
        }
        document.getElementById('menuTitle').innerHTML = menuTitleText;

        // event listener back button
        document.getElementById('backToMenuBtn').addEventListener('touchstart', onBackMenuTouchStart)
        document.getElementById('backToMenuBtn').addEventListener('touchend', onBackMenuTouchEnd)

        document.getElementById('backToMenuBtn').addEventListener('mousedown', onBackMenuTouchStart)
        document.getElementById('backToMenuBtn').addEventListener('mouseup', onBackMenuTouchEnd)
        document.getElementById('backToMenuBtn').addEventListener('mouseout', onBackMenuTouchOut)
        // Fill data based on menu clicked
        FillSelectedMenuData(languageStepsData[event.target.id.split('_')[1]].Text, languageStepsData[event.target.id.split('_')[1]].Next)
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} event 
     */
    function onBackMenuTouchOut(event) {
        event.preventDefault();
        event.target.classList.remove('pulse-button');
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} event 
     */
    function onBackMenuTouchStart(event) {
        event.preventDefault();
        event.target.classList.add('pulse-button');
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} event 
     */
    function onBackMenuTouchEnd(event) {
        event.preventDefault();
        event.target.classList.remove('pulse-button');
        let touchStatus = handleTouchEnd(event);
        if(touchStatus == false) {
            return;
        }
        
        if(fromParent == 'app' || fromParent == 'web') {
            window.parent.parent.postMessage(JSON.stringify({'message': 'closeFrame'}), '*')
        } else {
            document.getElementById('menuTopContainer').style.display = 'flex'
            document.getElementById('menuPage').style.display = 'none'
            document.getElementById('menuTitle').innerHTML = '';
        }
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} menuType 
     */
    function FillSelectedMenuData(menuType, toHold) {
        document.getElementById('menuList').innerHTML = ''
        document.getElementById('small-sub').innerHTML = ''
        /* if(menuType.toLowerCase().indexOf('faq') != -1) {
            document.getElementById('downloadBtn').style.display = 'none'
            document.getElementById('contentSteps').style.display = 'none'
            LoadGameFaqs()
        } else if(menuType.toLowerCase().indexOf('rule') != -1) {
            document.getElementById('downloadBtn').style.display = 'block'
            document.getElementById('contentSteps').style.display = 'none'
            LoadGameRules()
        } else {
            document.getElementById('downloadBtn').style.display = 'none'
            document.getElementById('contentSteps').style.display = 'block'
            LoadGameSteps();
        } */
       if(toHold == 'faqs' || toHold == 'faq') {
            document.getElementById('downloadBtn').style.display = 'none'
            document.getElementById('contentSteps').style.display = 'none'
            document.getElementById('menuPage').style.backgroundColor = '#2D2C2B'
            document.getElementById('menuTopContainer').style.display = 'flex'
            LoadGameFaqs()
        } else if(toHold == 'rules' || toHold == 'rule') {
            document.getElementById('downloadBtn').style.display = 'block'
            document.getElementById('contentSteps').style.display = 'none'
            document.getElementById('menuPage').style.backgroundColor = '#2D2C2B'
            document.getElementById('menuTopContainer').style.display = 'flex'
            LoadGameRules()
        } else if(toHold == 'steps' || toHold == 'step') {
            document.getElementById('downloadBtn').style.display = 'none'
            document.getElementById('menuPage').style.display = 'block'
            document.getElementById('menuPage').style.backgroundColor = '#F9F3E3'
            //document.getElementById('contentSteps').style.display = 'block'
            document.getElementById('menuTopContainer').style.display = 'none'
            LoadGameSteps();
        } else if(toHold == 'videos' || toHold == 'vidoes') {
            // 'vidoes' handles the typo in menu-en.json Next field
            document.getElementById('downloadBtn').style.display = 'none'
            document.getElementById('contentSteps').style.display = 'none'
            document.getElementById('menuPage').style.backgroundColor = '#2D2C2B'
            document.getElementById('menuTopContainer').style.display = 'flex'
            LoadGameVideos()
        }
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * For TEACH ME link on home page
     * LoadGameSteps
     */
    function LoadGameSteps() {
        // "?code=" + browserLang + "&id=" + faqSheetId + "&sheet=steps&from=floristry&faqsId=" + activeSheet_id + "&standalone=" + isStandalone
        //let _sheet = '../steps/index.php?version=' + UIVersion;
        /* let _sheet = '../steps/index.php?version=' + UIVersion;
        $("#contentSteps").attr("src", _sheet + "?code=" + activeLang + "&id=" + sheet_Id);
        $("#contentSteps").fadeIn(500); */

        //'./menu/index.php?version=' + _ver + "&code=" + activeLang + "&steps&" + fromParent + "&" + faqsSheetId + "&standalone=" + standalone
        let stepsType = ''
        /* if(fromParent == 'floristry') {
            stepsType = "?code=" + activeLang + "&id=" + faqsSheedId + "&standalone=" + standalone.split('?')[0]
        } else {
            stepsType = "?code=" + activeLang + "&id=" + sheet_Id + "&standalone=" + standalone.split('?')[0]
        } */

        // With Docker
        //stepsType = '../steps/index.php?version=' + UIVersion + "&code=" + activeLang + "&id=" + sheet_Id + "&steps&" + fromParent + "&standalone=" + standalone.split('?')[0]

        stepsType = '../steps/index.html?version=' + UIVersion + "&code=" + activeLang + "&id=" + sheet_Id + "&steps&" + fromParent + "&standalone=" + standalone.split('?')[0]

       //stepsType = '../menu/index.php?version=' + UIVersion + "&code=" + activeLang + "&id=" + sheet_Id + "&steps&" + fromParent + "&standalone=" + standalone.split('?')[0]

        $("#contentSteps").attr("src", stepsType);
        $("#contentSteps").fadeIn(500);
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * Download Button events 
     * DoDownloadRulesBtnEvents
     */
    function DoDownloadRulesBtnEvents() {
        document.getElementById('downloadBtn').addEventListener('touchstart', onDownloadBtnTouchStart)
        document.getElementById('downloadBtn').addEventListener('touchend', onDownloadBtnTouchEnd)

        document.getElementById('downloadBtn').addEventListener('mousedown', onDownloadBtnTouchStart)
        document.getElementById('downloadBtn').addEventListener('mouseup', onDownloadBtnTouchEnd)
        document.getElementById('downloadBtn').addEventListener('mouseout', onDownloadBtnTouchOut)
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} event 
     */
    function onDownloadBtnTouchOut(event) {
        event.preventDefault();
        event.target.classList.remove('pulse-button');
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} event 
     */
    function onDownloadBtnTouchStart(event) {
        event.preventDefault();
        event.target.classList.add('pulse-button');
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} event 
     */
    function onDownloadBtnTouchEnd(event) {
        event.preventDefault();
        event.target.classList.remove('pulse-button');
        let touchStatus = handleTouchEnd(event);
        if(touchStatus == false) {
            return;
        }

        let downloadURL = ''
        for(var i=0; i<rulesDataList.length; i++) {
            if(rulesDataList[i].ID == 'DOWNLOAD_URL') {
                downloadURL = rulesDataList[i].Text;
            }
        }
        if(downloadURL != '') {
            window.open(downloadURL)
        }
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * LoadGameFaqs
     */
    function LoadGameFaqs() {
        setTimeout(function() {
            let sheetName = ''
            fromParent == 'app' || 'web' ? sheetName = 'faqs-' : sheetName = 'faqs-'

            var FaqsRequest = $.ajax({

                //url: fromParent == 'app' ? jasonPath + '/sheets/' + faqsSheedId + "/" + sheetName + activeLang.split('-')[0].toLowerCase() + ".json?version=" + UIVersion : jasonPath + '/sheets/' + sheet_Id + "/" + sheetName + activeLang.split('-')[0].toLowerCase() + ".json?version=" + UIVersion,

                url: jasonPath + 'sheets/' + sheet_Id + "/" + sheetName + activeLang.split('-')[0].toLowerCase() + ".json?version=" + UIVersion,



                /* cache: false, */ 
                cache: true,
                type: 'GET',
                dataType: "text",
                success: function (response) {
                    if(response.length == 0) {
                        logLoadMsg('<font color="red">Error: Rules data not available.' + "</font><br>")
                    } else { 
                        faqsDataList = []
                        var mResponseSet = response.replace(/�/g, "") 
                        var newSettingData = eval(mResponseSet)
                        for(var i=0; i<newSettingData.length; i++) {
                            var settingDataSting = JSON.stringify(newSettingData[i]);
                            if(isJSONData(settingDataSting) == false) {
                                logLoadMsg('<font color="red">Error: Rules Sheet : (Row: ' + i + ")</font><br>")
                            } else {
                                faqsDataList[i] = isJSONData(settingDataSting)
                            }
                        }
                        setTimeout(function() {
                            let faqList = ''
                            for(var i=0; i<faqsDataList.length; i++) {
                                if(faqsDataList[i].Type == 'faq') {
                                    // TAGS Support
                                    // For Question
                                    let mWordFlowerQuestion = applyTagReplacements(faqsDataList[i].Question, 'specialIconsFaqs')

                                    // For Answer
                                    let mWordFlowerAnswer = applyTagReplacements(faqsDataList[i].Answer, 'specialIconsFaqs')

                                    // get Image
                                    let faqImage = getImagePath(faqsDataList[i].Image)

                                    faqList += `<li id="faqItem_${i}" style="color: lightgray; left: 4vh; position: relative;font-size: 3.5vh; top: 2vh; height:auto; line-height:1; margin-bottom:2vh; cursor: pointer;"> <span style="position: relative; top: 0vh !important; color: #F7AE50; font-size: 3.5vh;">${mWordFlowerQuestion}</span><div id="faq_${i}" style="color: #2D2C2B; left: 0vh; position: relative;font-size: 3vh; top: 2vh; height:auto; line-height:1; margin-bottom:2vh; display:none"><p style="position: relative; top: 0vh !important; color: #FFFFFF; font-size: 3vh;">${mWordFlowerAnswer}</p>`
                                    faqImage != '' ? 
                                    faqList += `<div style="margin-top: 3vh; font-size: 3vh; line-height: 1.1; width: 100%;height: auto; position: relative;"><p id="spinner_${i}" class="spinner"></p><img id="faqImg_${i}" src=${faqImage} alt="" loading="lazy" style="position:relative; width:auto; height:auto;"></img></div></div></li>` : 
                                    faqList += `</div></li>`
                                }
                            }
                            document.getElementById('menuList').innerHTML = faqList;

                            

                            //activateFaqElement(0)
                            // Event Listener
                            setTimeout(function() {
                                for(var i=0; i<faqsDataList.length; i++) {
                                    if(faqsDataList[i].Type == 'faq') {
                                        // Add events
                                        document.getElementById('faqItem_' + i).addEventListener('touchstart', onFaqItemTouchStart)
                                        document.getElementById('faqItem_' + i).addEventListener('touchend', onFaqItemTouchEnd)
                                        document.getElementById('faqItem_' + i).addEventListener('touchmove', onFaqItemTouchMove)

                                        document.getElementById('faqItem_' + i).addEventListener('mousedown', onFaqItemTouchStart)
                                        document.getElementById('faqItem_' + i).addEventListener('mouseup', onFaqItemTouchEnd)

                                    }
                                }
                                
                            }, 250)
                        }, 10) 
                    }
                },
                error: function(e) {
                    activeLang = "EN";
                    LoadGameFaqs();
                    /* logLoadMsg('<font color="red">Error: Missing Sheet : Faqs</font><br>')
                    document.getElementById("spinnerBox").style.display = 'none' */
                }
            })
            // Clear memory
            FaqsRequest.onreadystatechange = null;
            FaqsRequest.abort = null;
            FaqsRequest = null;
        }, 10)
    }
    ///////////////////////////////////////////////////////////////////////////////
    function getImagePath(imgPath) {
        let imagePath = ''
        for(var i=0; i<faqsDataList.length; i++) {
            if(faqsDataList[i].Image != '') {
                if(faqsDataList[i].Image == imgPath) {
                    if (faqsDataList[i].Image.includes("https://drive.google.com")) {
                        let imgid = faqsDataList[i].Image.split('https://drive.google.com')[1].split('/')[3];
                        let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                        //imagePath = fromParent == 'app' ? (jasonPath + '/sheets/' + faqsSheedId + '/cacheImages/' + imgid + '.png?version=' + Math.random()) : (jasonPath + '/sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random());
                        //imagePath = (jasonPath + '/sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random());
                        imagePath = (jasonPath + '/sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + UIVersion);
                    } else {
                        let name = faqsDataList[i].Image.split('/')
                        let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                        //imagePath = fromParent == 'app' ? (jasonPath + '/sheets/' + faqsSheedId + '/cacheImages/' + imageName + "?version=" + Math.random()) : (jasonPath + '/sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random());
                        //imagePath = (jasonPath + '/sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random());
                        imagePath = (jasonPath + '/sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + UIVersion);
                    }
                }
            } 
        }
        return imagePath;
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} event 
     */
    function onFaqItemTouchStart(event) {
        faqTouchMoved = false
        event.stopPropagation();
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} event 
     */
    function onFaqItemTouchMove(event) {
        faqTouchMoved = true;
        event.stopPropagation();
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} event 
     */
    function onFaqItemTouchEnd(event) {
        if(event.cancelable) event.preventDefault();

        event.stopPropagation();

        let touchStatus = handleTouchEnd(event);
        if(touchStatus == false || faqTouchMoved == true) {
            return;
        }

        let faqItemId = event.target.parentElement.id.split('_')[1];
        if(faqItemId == undefined) return;
        activateFaqElement(faqItemId)

        // Spinner Hide
        const imgElement = document.getElementById('faqImg_' + faqItemId);
        if(imgElement != null) {
            imgElement.addEventListener('load', () => {
                document.getElementById('spinner_' + faqItemId).style.display = 'none';
            })
        }


    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} _id 
     */
    function activateFaqElement(_id) {
        for(var i=0; i<faqsDataList.length; i++) {
            if(faqsDataList[i].Type == 'faq') {
                if(_id == i) {
                    let elementToScroll = document.getElementById('faq_'+i);
                    //document.getElementById('faq_'+i).style.display = 'block';
                    //$('#faq_' + i).slideUp();
                    //document.getElementById('faq_'+i).style.opacity = 0;

                    
                    // Toggle
                    $('#faq_' + i).stop().slideToggle();

                    // Move Element to View Area
                    if (elementToScroll && _id != 0) {
                        elementToScroll.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center' // Center the element within the viewport
                        });
                    }


                    /* setTimeout(function() {
                    var elem = document.getElementById('menuList');
                    const computedStyle = window.getComputedStyle(element);
                    // 3. Access the 'height' property
                    const heightInPx = computedStyle.height;
                    let scrollH = Math.ceil(heightInPx.split('px')[0]) 
                    elem.scrollTop = scrollH;
                    }, 100) */
                } else {
                   //document.getElementById('faq_'+i).style.display = 'none'; 
                   //$('#faq_' + i).slideUp(200).fadeOut(200);
                }
            }
        }
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * LoadGameVideos — renders video links from videos-{lang}.json
     */
    function LoadGameVideos() {
        var langCode = activeLang.split('-')[0].toLowerCase();
        var videosRequest = $.ajax({
            url: jasonPath + 'sheets/' + sheet_Id + '/videos-' + langCode + '.json?version=' + UIVersion,
            cache: true,
            type: 'GET',
            dataType: 'text',
            success: function(response) {
                if (!response || response.length === 0) {
                    document.getElementById('menuList').innerHTML =
                        '<p style="color:#F7AE50; font-size:3vh; padding:2vh;">No videos available.</p>';
                    return;
                }
                videosDataList = [];
                try {
                    var parsed = JSON.parse(response);
                    for (var i = 0; i < parsed.length; i++) {
                        var row = parsed[i];
                        if (row.Content && row.Content.trim() !== '') {
                            videosDataList.push(row);
                        }
                    }
                } catch(e) {
                    document.getElementById('menuList').innerHTML =
                        '<p style="color:red; font-size:3vh; padding:2vh;">Error reading videos data.</p>';
                    return;
                }

                var html = '<div style="height: 4vh;"></div>';
                for (var j = 0; j < videosDataList.length; j++) {
                    var item = videosDataList[j];
                    var title = item.Title || ('Video ' + (j + 1));
                    var url   = item.Content.trim();

                    // Detect platform for icon label
                    var platform = '';
                    if (url.indexOf('youtube.com') !== -1 || url.indexOf('youtu.be') !== -1) {
                        platform = 'YouTube';
                    } else if (url.indexOf('tiktok.com') !== -1) {
                        platform = 'TikTok';
                    } else {
                        platform = 'Watch';
                    }

                    html += `<div id="videoItem_${j}"
                        data-url="${url}"
                        style="display:flex; align-items:center; justify-content:space-between;
                               width:100%; padding:2vh 3vh; margin-bottom:1.5vh;
                               background:rgba(255,255,255,0.07); border-radius:1.5vh;
                               cursor:pointer;">
                        <div style="font-size:3vh; color:#FFFFFF; line-height:1.2;">${title}</div>
                        <div style="font-size:2.2vh; color:#F7AE50; white-space:nowrap; padding-left:2vh;">${platform} &#8599;</div>
                    </div>`;
                }

                if (html === '') {
                    html = '<p style="color:#F7AE50; font-size:3vh; padding:2vh;">No video links found.</p>';
                }

                document.getElementById('menuList').innerHTML = html;

                // Attach click handlers — open each link in a new tab
                setTimeout(function() {
                    for (var k = 0; k < videosDataList.length; k++) {
                        (function(idx) {
                            var el = document.getElementById('videoItem_' + idx);
                            if (!el) return;
                            el.addEventListener('mouseup', function() {
                                window.open(this.getAttribute('data-url'), '_blank');
                            });
                            el.addEventListener('touchend', function(e) {
                                e.preventDefault();
                                window.open(this.getAttribute('data-url'), '_blank');
                            });
                        })(k);
                    }
                }, 100);
            },
            error: function() {
                document.getElementById('menuList').innerHTML =
                    '<p style="color:red; font-size:3vh; padding:2vh;">Error: videos-' + langCode + '.json not found.</p>';
            }
        });
        videosRequest.onreadystatechange = null;
        videosRequest.abort = null;
        videosRequest = null;
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * LoadGameRules
     */
    function LoadGameRules() {
        setTimeout(function() {
            var RulesRequest = $.ajax({
                //url: '../sheets/' + sheet_Id + "/rules-" + activeLang.split('-')[0].toLowerCase() + ".json?version=" + UIVersion,
                url: jasonPath + 'sheets/' + sheet_Id + "/rules-" + activeLang.split('-')[0].toLowerCase() + ".json?version=" + UIVersion,
                cache: true,
                type: 'GET',
                dataType: "text",
                success: function (response) {
                    if(response.length == 0) {
                        logLoadMsg('<font color="red">Error: Rules data not available.' + "</font><br>")
                    } else { 
                        rulesDataList = []
                        var mResponseSet = response.replace(/�/g, "") 
                        var newSettingData = eval(mResponseSet)
                        for(var i=0; i<newSettingData.length; i++) {
                            var settingDataSting = JSON.stringify(newSettingData[i]);
                            if(isJSONData(settingDataSting) == false) {
                                logLoadMsg('<font color="red">Error: Rules Sheet : (Row: ' + i + ")</font><br>")
                            } else {
                                rulesDataList[i] = isJSONData(settingDataSting)
                            }
                        }
                        setTimeout(function() {
                            let ruleList = ''
                            for(var i=0; i<rulesDataList.length; i++) {
                                if(rulesDataList[i].Type == 'menu') {
                                    ruleList += `<li id="rulesItem_${i}" style="color: lightgray; left: 4vh; position: relative;font-size: 3.5vh; top: 2vh; height:auto; line-height:1; margin-bottom:2vh; cursor: pointer;"> <span id="rulesSpan_${i}" style="position: relative; top: 0vh !important; color: #F7AE50; font-size: 3.5vh;">${rulesDataList[i].ID}</span></li>`
                                }
                            }
                            document.getElementById('menuList').innerHTML = ruleList;

                            // List events
                            for(var i=0; i<rulesDataList.length; i++) {
                                if(rulesDataList[i].Type == 'menu') {
                                    // Add events
                                    document.getElementById('rulesItem_' + i).addEventListener('touchstart', onRuleItemTouchStart)
                                    document.getElementById('rulesItem_' + i).addEventListener('touchend', onRuleItemTouchEnd)

                                    document.getElementById('rulesItem_' + i).addEventListener('mousedown', onRuleItemTouchStart)
                                    document.getElementById('rulesItem_' + i).addEventListener('mouseup', onRuleItemTouchEnd)
                                    document.getElementById('rulesItem_' + i).addEventListener('mouseout', onRuleItemTouchEnd)

                                }
                            }
                            DoDownloadRulesBtnEvents();
                        }, 10) 
                    }
                },
                error: function(e) {
                    activeLang = "EN";
                    LoadGameRules();
                    /* logLoadMsg('<font color="red">Error: Missing Sheet : Rules</font><br>')
                    document.getElementById("spinnerBox").style.display = 'none' */
                }
            })
            // Clear memory
            RulesRequest.onreadystatechange = null;
            RulesRequest.abort = null;
            RulesRequest = null;
        }, 10)
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} event 
     */
    function onRuleItemTouchStart(event) {
        if(event.cancelable) event.preventDefault();
        /* let touchStatus = handleTouchEnd(event);
        if(touchStatus == false) {
            return;
        } */

        //if(event.target.parentElement.id == 'rulesNavigation') {
            //document.getElementById(event.target.id).style.color = '#FFFFFF'

            if(event.target.id == 'prevElement' || event.target.id == 'prevArrow') {
                document.getElementById('prevArrow').classList.add('pulse-button');
                document.getElementById('prevElement').style.color = '#FFFFFF'
            } else if(event.target.id == 'nextElement' || event.target.id == 'nextArrow') {
                document.getElementById('nextArrow').classList.add('pulse-button');
                document.getElementById('nextElement').style.color = '#FFFFFF'
            } else {
                document.getElementById(event.target.id).style.color = '#FFFFFF'
            }

            //event.target.classList.add('pulse-button');
        /* } else {
            document.getElementById(event.target.id).style.color = '#FFFFFF'
        } */

    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} event 
     */
    function onRuleItemTouchEnd(event) {
        if(event.cancelable) event.preventDefault();
        let touchStatus = handleTouchEnd(event);
        if(touchStatus == false) {
            //if(event.target.parentElement.id == 'rulesNavigation') {
                //document.getElementById(event.target.id).style.color = '#F7AE50'
                //event.target.classList.remove('pulse-button');
            /* } else {
                document.getElementById(event.target.id).style.color = '#F7AE50'
            } */
           if(event.target.id == 'prevElement' || event.target.id == 'prevArrow') {
                document.getElementById('prevArrow').classList.remove('pulse-button');
                document.getElementById('prevElement').style.color = '#F7AE50'
            } else if(event.target.id == 'nextElement' || event.target.id == 'nextArrow') {
                document.getElementById('nextArrow').classList.remove('pulse-button');
                document.getElementById('nextElement').style.color = '#F7AE50'
            } else {
               document.getElementById(event.target.id).style.color = '#F7AE50' 
            }
            return;
        }



        //document.getElementById(event.target.id).style.color = '#F7AE50'
        let ruleItemId = -1
        let ruleName = ''
        if(event.target.parentElement.parentElement.id == 'rulesNavigation') {
            //document.getElementById(event.target.id).style.color = '#F7AE50'

            if(event.target.id == 'prevElement' || event.target.id == 'prevArrow') {
                document.getElementById('prevArrow').classList.remove('pulse-button');
                document.getElementById('prevElement').style.color = '#F7AE50'
                ruleName = document.getElementById('prevElement').innerHTML;
            } else if(event.target.id == 'nextElement' || event.target.id == 'nextArrow') {
                document.getElementById('nextArrow').classList.remove('pulse-button');
                document.getElementById('nextElement').style.color = '#F7AE50'
                ruleName = document.getElementById('nextElement').innerHTML;
            } else {
                document.getElementById(event.target.id).style.color = '#F7AE50'
            }

            //let ruleName = document.getElementById(event.target.id).innerHTML;
            ruleItemId = getRuleItemEndIndex(ruleName)
        } else {
            //document.getElementById(event.target.id).style.color = '#F7AE50'
            ruleItemId = event.target.parentElement.id.split('_')[1];
            document.getElementById(event.target.id).style.color = '#F7AE50'
        }
        
        if(ruleItemId == undefined) return;
        document.getElementById('menuDetailsPage').style.display = 'flex'
        document.getElementById('menuDetailsTitle').innerHTML = rulesDataList[ruleItemId].ID;

        // Add Listener
        document.getElementById('backToRuleMenuBtn').addEventListener('touchstart', onBackToRuleTouchStart)
        document.getElementById('backToRuleMenuBtn').addEventListener('touchend', onBackToRuleTouchEnd)

        document.getElementById('backToRuleMenuBtn').addEventListener('mousedown', onBackToRuleTouchStart)
        document.getElementById('backToRuleMenuBtn').addEventListener('mouseup', onBackToRuleTouchEnd)
        document.getElementById('backToRuleMenuBtn').addEventListener('mouseout', onBackToRuleTouchOut)

        // Fill data based on menu clicked
        FillSelectedMenuDetailsData(ruleItemId)
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} event 
     */
    function onBackToRuleTouchOut(event) {
        event.preventDefault();
        event.target.classList.remove('pulse-button');
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} ruleItemId 
     */
    function FillSelectedMenuDetailsData(ruleItemId) {
        //FillSelectedMenuDetailsData()
        document.getElementById('menuDetails').innerHTML = ''

        document.getElementById('rulesNavigation').style.display = "flex"

        let endIndex = getMenuDetailItemEndIndex(ruleItemId)


        let menuItemToDisplay = ''

        // Show Navigation data
        let prevRule = getPrevRule(ruleItemId)
        let nextRule = getNextRule(ruleItemId)
        if(prevRule.Text != undefined) {
            document.getElementById('prevElement').innerHTML = prevRule.ID;
            document.getElementById('prevArrow').style.display = 'block';
        } else {
            document.getElementById('prevElement').innerHTML = '';
            document.getElementById('prevArrow').style.display = 'none';
        }
        if(nextRule.Text != undefined) {
            document.getElementById('nextElement').innerHTML = nextRule.ID;
            document.getElementById('nextArrow').style.display = 'block';
        } else {
            document.getElementById('nextElement').innerHTML = ''
            document.getElementById('nextArrow').style.display = 'none';
        }

        for(var i=Number(ruleItemId)+1; i<endIndex; i++) {
            if(rulesDataList[i].Type == 'text') {
                //menuItemToDisplay = rulesDataList[i].Text
                // Format Text for adding special icons
                let mWordFlower = applyTagReplacements(rulesDataList[i].Text, 'specialIcons')

                //document.getElementById('menuDetails').innerHTML += `<p style="margin-top:3vh; font-size:3vh; line-height:1.1; width:100%; color:white" >${rulesDataList[i].Text}</p>`

                document.getElementById('menuDetails').innerHTML += `<p style="margin-top:3vh; font-size:3vh; line-height:1.1; width:100%; color:white" >${mWordFlower}</p>`

            } else if(rulesDataList[i].Type == 'image') {
                let imagePath = ''
                if (rulesDataList[i].Text.includes("https://drive.google.com")) {
                    let imgid = rulesDataList[i].Text.split('https://drive.google.com')[1].split('/')[3];
                    let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                    // image from spreadsheet id folder
                    //imagePath = '../sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();
                    imagePath = '../sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + UIVersion;
                } else {
                    // Cache Image
                    let name = rulesDataList[i].Text.split('/')
                    let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                    // image from spreadsheet id folder
                    //imagePath = '../sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
                    imagePath = '../sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + UIVersion;
                }
                document.getElementById('menuDetails').innerHTML += `<div style="margin-top:3vh; font-size:3vh; line-height:1.1; width:100%"><img src=${imagePath} alt="" loading="lazy"></img></div>`
            }
        }
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} index 
     * @returns 
     */
    function getRuleItemEndIndex(ruleName) {
        let ruleIndex = -1
        for(var i=0; i<rulesDataList.length; i++) {
           if(rulesDataList[i].Type == 'menu' && rulesDataList[i].ID == ruleName) {
            ruleIndex = i;
            return ruleIndex;
           }
        }
        return ruleIndex;
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} index 
     */
    function getNextRule(index) {
        let ruleObj = []
        for(var i=Number(index)+1; i<rulesDataList.length; i++) {
           if(rulesDataList[i].Type == 'menu') {
            ruleObj = rulesDataList[i];
            return ruleObj;
           }
        }
        return ruleObj;
    }
    //////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} index 
     * @returns 
     */
    function getPrevRule(index) {
        let ruleObj = []
        for(var i=Number(index)-1; i>=0; i--) {
           if(rulesDataList[i].Type == 'menu') {
            ruleObj = rulesDataList[i];
            return ruleObj;
           }
        }
        return ruleObj;
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} index 
     */
    function getMenuDetailItemEndIndex(index) {
        //let lastIndex = -1
        let lastIndex = rulesDataList.length;
        for(var i=Number(index)+1; i<rulesDataList.length; i++) {
            /* if(rulesDataList[i].ID != '') {
                lastIndex = i */
                /* return lastIndex
            }  */
           if(rulesDataList[i].Type == 'menu') {
            lastIndex = i;
            return lastIndex;
           }
        }
        return lastIndex;
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} event 
     */
    function onBackToRuleTouchStart(event) {
        if(event.cancelable) event.preventDefault();
        event.target.classList.add('pulse-button');
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} event 
     */
    function onBackToRuleTouchEnd(event) {
        if(event.cancelable) event.preventDefault();
        event.target.classList.remove('pulse-button');
        let touchStatus = handleTouchEnd(event);
        if(touchStatus == false) {
            return;
        }

        document.getElementById('menuDetailsPage').style.display = 'none'
        document.getElementById('menuDetailsTitle').innerHTML = '';
    }

    ///////////////////////////////////////////////////////////////////////////////
    /**
     * hideProgressBar
     */
    function hideProgressBar() {
        document.getElementById('spinnerMiddleBox').style.display = 'none'
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * base64Encode
     * @param {*} inputStr 
     * @returns 
     */
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
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} event 
     * @returns 
     */
    function handleTouchEnd(event) {
        let inButton = false;
        let touch = null;
        if(event.type == 'mouseout') {
            inButton = false;
            return inButton;
        } else if(event.type == 'mouseup') {
            touch = event;
        } else {
            touch = event.changedTouches[0];
        }
        const element = event.target; // Or use the element from step 1
        const rect = element.parentElement.getBoundingClientRect();


        if(touch.clientX >= rect.left && touch.clientX <= rect.right && touch.clientY >= rect.top && touch.clientY <= rect.bottom) {
            inButton = true;
        } else {
            inButton = false
        }
        return inButton;
    }
    /////////////////////////////////////////////////////////////////////////////////
    /**
     * iFrame message listener
     */
    window.addEventListener('message', function(event) {
        if(JSON.parse(event.data).message == 'closeFrame') {
            HideIFrame();
        }
        if(JSON.parse(event.data).message == 'toggleFrame') {
            ToggleIFrame();
        }
        if(JSON.parse(event.data).message == 'disableTimer') {
            resetIdleTimer();
        }
    })
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * Hide the frame
     * HideIFrame
     */
    function HideIFrame() {
        //$("#contentSteps").effect( "drop", "fast" );
        //$("#contentSteps").fadeOut(500);
        $("#contentSteps").attr("src",'');
        document.getElementById('menuPage').style.backgroundColor = '#2D2C2B'
        document.getElementById('menuPage').style.display = 'none'
        document.getElementById('menuTitle').innerHTML = '';
        document.getElementById("menuScreen").style.display = 'block'
    }
    /////////////////////////////////////////////////////////////////////////////////
    /**
     * ToggleIFrame
     */
    function ToggleIFrame(event) {
        //$( "#contentSteps" ).effect( "drop", "fast" );
    }
    ///////////////////////////////////////////////////////////////////////////////
})
    
