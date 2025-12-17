//////////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 * Ready events
 */
$(document).ready(function() {
    console.log('READY MENU')
    let languageStepsData = [];
    let settingDataList = [];
    let rulesDataList = [];
    let faqsDataList = [];

    // Special Icons
    let berryImgPath = ''
    let bugImgPath = ''
    let nutImgPath = ''
    let diceImgPath = ''
    let oopsImgPath = ''

    // For lazy load
    // For LOCAL TESTING
    //let lazyLoadImages = 'TRUE'
    // For LIVE
    let lazyLoadImages = ''
    // For Precache Images
    var preCacheImages = []
    var preCachedDone = false;
    let langLoadCount = 0;
    ///////////////////////////////////////////////////////////////////////////
    // Positioning of bottom container
    var standalone = (getUrlVars()["standalone"]) ? getUrlVars()["standalone"].split('/')[0] : 'false';
    // To get active browser language OR language passed from QS 'code'
    var activeLang = (getUrlVars()["code"]) ? getUrlVars()["code"].split('/')[0].toUpperCase() : navigator.language.split('-')[0].toUpperCase();
    // To get spreadsheet id passed from QS 'id'
    var sheet_Id = (getUrlVars()["id"]) ? getUrlVars()["id"].split('/')[0] : '';
    // To jump faqs or rules
    var sheetInnerParam = document.location.search.substr(1).split('&')[1].split('?')[0];
    /////////////////////////////////////////////////////////////////////////////////
    /**
     * isJSONData
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
        loadSettingsData()
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
        console.log("check user data")
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
            //showloader()
            setTimeout(function() {
                window.history.replaceState({}, "null", (winLoc + "?code=" + browserLang.toLowerCase() +"&"+ jumpId + "&id=" + sheet_Id));
                document.getElementById('loadingScreen').style.display = 'flex';
                document.getElementById('sheetIdError').style.display = 'none';
                setTimeout(function() {
                    loadSettingsData();
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
                //url: '../steps/sheets/' + sheet_Id + "/settings.json?version=" + Math.random(),
                url: '../steps/sheets/' + sheet_Id + "/settings.json?version=" + UIVersion, 
                /* cache: false, */ 
                cache: true,
                type: 'GET',
                dataType: "text",
                success: function (response) {
                    //console.log(response, " READ DATA")
                    if(response.length == 0) {
                        document.getElementById("loadingText").innerHTML += '<font color="red">Error: Settings data not available.' + "</font><br>"
                    } else { 
                        settingDataList = []
                        var mResponseSet = response.replace(/�/g, "") 
                        var newSettingData = eval(mResponseSet)
                        for(var i=0; i<newSettingData.length; i++) {
                            var settingDataSting = JSON.stringify(newSettingData[i]);
                            if(isJSONData(settingDataSting) == false) {
                                document.getElementById("loadingText").innerHTML += '<font color="red">Error: Settings Sheet : (Row: ' + i + ")</font><br>"
                                updateInfoTextView()
                            } else {
                                settingDataList[i] = isJSONData(settingDataSting)
                            }
                        }
                        /////////////////////LANG SETTINGS START///////////////////////////
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
                            if(row_setting['Name'] == 'DownloadButtonUrl') {
                                let imagePath = ''
                                if (row_setting['Value'].includes("https://drive.google.com")) {
                                    let imgid = row_setting['Value'].split('https://drive.google.com')[1].split('/')[3];
                                    imagePath = '../steps/sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();
                                } else {
                                    // Cache Image
                                    let name = row_setting['Value'].split('/')
                                    let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                                    // image from spreadsheet id folder
                                    imagePath = '../steps/sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
                                }
                                document.getElementById('downloadBtn').src = imagePath
                            }

                            // Other Icons
                            // BERRY
                            if(row_setting['Name'] == '[BERRY]') {
                                if (row_setting['Value'].includes("https://drive.google.com")) {
                                    let imgid = row_setting['Value'].split('https://drive.google.com')[1].split('/')[3];
                                    berryImgPath = '../steps/sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();
                                } else {
                                    // Cache Image
                                    let name = row_setting['Value'].split('/')
                                    let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                                    // image from spreadsheet id folder
                                    berryImgPath = '../steps/sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
                                }
                            }
                            // DICE
                            if(row_setting['Name'] == '[DICE]') {
                                if (row_setting['Value'].includes("https://drive.google.com")) {
                                    let imgid = row_setting['Value'].split('https://drive.google.com')[1].split('/')[3];
                                    diceImgPath = '../steps/sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();
                                } else {
                                    // Cache Image
                                    let name = row_setting['Value'].split('/')
                                    let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                                    // image from spreadsheet id folder
                                    diceImgPath = '../steps/sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
                                }
                            }
                            // BUG
                            if(row_setting['Name'] == '[BUG]') {
                                if (row_setting['Value'].includes("https://drive.google.com")) {
                                    let imgid = row_setting['Value'].split('https://drive.google.com')[1].split('/')[3];
                                    bugImgPath = '../steps/sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();
                                } else {
                                    // Cache Image
                                    let name = row_setting['Value'].split('/')
                                    let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                                    // image from spreadsheet id folder
                                    bugImgPath = '../steps/sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
                                }
                            }
                            // NUT
                            if(row_setting['Name'] == '[NUT]') {
                                if (row_setting['Value'].includes("https://drive.google.com")) {
                                    let imgid = row_setting['Value'].split('https://drive.google.com')[1].split('/')[3];
                                    nutImgPath = '../steps/sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();
                                } else {
                                    // Cache Image
                                    let name = row_setting['Value'].split('/')
                                    let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                                    // image from spreadsheet id folder
                                    nutImgPath = '../steps/sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
                                }
                            }
                            // OOPS
                            if(row_setting['Name'] == '[OOPS]') {
                                if (row_setting['Value'].includes("https://drive.google.com")) {
                                    let imgid = row_setting['Value'].split('https://drive.google.com')[1].split('/')[3];
                                    oopsImgPath = '../steps/sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();
                                } else {
                                    // Cache Image
                                    let name = row_setting['Value'].split('/')
                                    let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                                    // image from spreadsheet id folder
                                    oopsImgPath = '../steps/sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
                                }
                            }
                        })
                        /////////////////////MENU JSON START////////////////////////
                        setTimeout(function() {
                            loadMenuJSON()
                        }, 500) 
                    }
                },
                error: function(e) {
                    console.log("ERROR - Setting data missing..")
                    document.getElementById("loadingText").innerHTML += '<font color="red">Error: Missing Sheet : Settings</font><br>'
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
    // Loading Menu JSON
    /**
     * loadMenuJSON
     */
    function loadMenuJSON() {
        // Loading steps json
        var langRequest = $.ajax({
            //url: '../steps/sheets/' + sheet_Id + "/menu-" + activeLang.split('-')[0].toLowerCase() + ".json?version=" + Math.random(), 
            url: '../steps/sheets/' + sheet_Id + "/menu-" + activeLang.split('-')[0].toLowerCase() + ".json?version=" + UIVersion, 
            /* cache: false, */
            cache: true, 
            //async: false,
            type: 'GET',
            dataType: "text",
            success: function (response) {
                //console.log(response, " READ DATA")
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
                            document.getElementById("loadingText").innerHTML += '<font color="red">Error: ' + activeLang.split('-')[0] + ' Sheet : (Row: ' + i + ")</font><br>"
                            updateInfoTextView()
                        } else {
                            languageStepsData[i] = isJSONData(langDataSting)
                        }
                    }
                    // convert values to all defined format
                    if(lazyLoadImages == "FALSE" || lazyLoadImages == "False" || lazyLoadImages == "false" || lazyLoadImages == "0") {
                        PreloadAllToCache();
                    } else {
                        console.log("CREATE UI SCREEN FOR MENU....")
                        jumpToMenuScreen()
                    }
                }
            },
            error: function (response) {
                console.log("NO FILE FOUND")
                if(activeLang.toLowerCase() != 'EN' && langLoadCount < 1) {
                    langLoadCount++
                    loadLanguageJSON()
                } else {
                    langLoadCount = 0;
                    document.getElementById("loadingText").innerHTML += '<font color="red">Error: Missing Sheet : ' + activeLang + '</font><br>'
                    document.getElementById("spinnerBox").style.display = 'none'
                }
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
        console.log("Caching In Background")
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
                    let imagePath = '../steps/sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();
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
                    let imagePath = '../steps/sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
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
            console.log("ALL IMAGES IN CACHE NOW...")
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
                            imagePathPrev = '../steps/sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();
                        } else {
                            // Cache Image
                            let name = row_setting['Value'].split('/')
                            let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                            // image from spreadsheet id folder
                            imagePathPrev = '../steps/sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
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
                            imagePathNext = '../steps/sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();
                        } else {
                            // Cache Image
                            let name = row_setting['Value'].split('/')
                            let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                            // image from spreadsheet id folder
                            imagePathNext = '../steps/sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
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
                            imagePathQuit = '../steps/sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();
                        } else {
                            // Cache Image
                            let name = row_setting['Value'].split('/')
                            let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                            // image from spreadsheet id folder
                            imagePathQuit = '../steps/sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
                        }
                    }
                }
            })
            setTimeout(function() {
            }, 500)
            console.log("IMAGES CACHED")
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
        //console.log("show loaded")
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
            //console.log(e.loaded / e.total * 100, " perc");
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
        document.getElementById('menuScreen').style.display = 'block'
        document.getElementById('menuBGInage').src = imageElement;
        setTimeout(function() {
            $("#menuScreen").fadeIn();
            generateMenuButtons()
        }, 500)
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * generateMenuButtons
     */
    function generateMenuButtons() {
        let buttonElement = ''
        for(var i=0; i<languageStepsData.length; i++) {
            if(languageStepsData[i].Type == 'button') {
                buttonElement += `<div id="viewIconContainer" style="-webkit-touch-callout: none; user-select: none; -moz-user-select: none; -webkit-user-select: none; width: 100%; height: 5vh; border-radius: 15px; background-color: #F7AE4F; position: relative; margin-bottom: 2vh; cursor: pointer;">
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
                font-weight: 600;
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
        if(sheetInnerParam.length < 10) {
            ShowAutoMenuPages(sheetInnerParam)
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

                    console.log("MENU DONE")
                }
            }
        }, 250)
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
        let sheetParamData = getMenuItemData(sheetParam);
        if(languageStepsData[sheetParamData].Text.toLowerCase().indexOf('faq') != -1) {
            menuTitleText = languageStepsData[sheetParamData].Text.split(" ")[1]
        } else {
            menuTitleText = languageStepsData[sheetParamData].Text
        }
        document.getElementById('menuTitle').innerHTML = menuTitleText;
        // event listener back button
        document.getElementById('backToMenuBtn').addEventListener('touchstart', onBackMenuTouchStart)
        document.getElementById('backToMenuBtn').addEventListener('touchend', onBackMenuTouchEnd)

        document.getElementById('backToMenuBtn').addEventListener('mousedown', onBackMenuTouchStart)
        document.getElementById('backToMenuBtn').addEventListener('mouseup', onBackMenuTouchEnd)

        // Fill data based on menu clicked
        FillSelectedMenuData(languageStepsData[sheetParamData].Text)
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} event 
     */
    function onMenuTouchStart(event) {
        event.preventDefault();
        // Add pulsating effect
        //console.log(event.target.parentElement)
        event.target.parentElement.classList.add('pulse-button');
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} event 
     */
    function onMenuTouchOut(event) {
        //console.log("ASASASASA")
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
            menuTitleText = languageStepsData[event.target.id.split('_')[1]].Text
        }
        document.getElementById('menuTitle').innerHTML = menuTitleText;
        // event listener back button
        document.getElementById('backToMenuBtn').addEventListener('touchstart', onBackMenuTouchStart)
        document.getElementById('backToMenuBtn').addEventListener('touchend', onBackMenuTouchEnd)

        document.getElementById('backToMenuBtn').addEventListener('mousedown', onBackMenuTouchStart)
        document.getElementById('backToMenuBtn').addEventListener('mouseup', onBackMenuTouchEnd)
        document.getElementById('backToMenuBtn').addEventListener('mouseout', onBackMenuTouchOut)
        // Fill data based on menu clicked
        FillSelectedMenuData(languageStepsData[event.target.id.split('_')[1]].Text)
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

        console.log('Back menu click')
        document.getElementById('menuPage').style.display = 'none'
        document.getElementById('menuTitle').innerHTML = '';
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} menuType 
     */
    function FillSelectedMenuData(menuType) {
        console.log(menuType, " --- ")
        document.getElementById('menuList').innerHTML = ''
        if(menuType.toLowerCase().indexOf('faq') != -1) {
            console.log('faqs selected')
            document.getElementById('downloadBtn').style.display = 'none'
            LoadGameFaqs()
        } else if(menuType.toLowerCase().indexOf('rule') != -1) {
            console.log('rules selected')
            document.getElementById('downloadBtn').style.display = 'block'
            LoadGameRules()
        } else {
            console.log('teach me selected')
            document.getElementById('downloadBtn').style.display = 'none' 
        }
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * Download Button events 
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
            var FaqsRequest = $.ajax({
                //url: '../steps/sheets/' + sheet_Id + "/faqs-" + activeLang.split('-')[0].toLowerCase() + ".json?version=" + Math.random(),
                url: '../steps/sheets/' + sheet_Id + "/faqs-" + activeLang.split('-')[0].toLowerCase() + ".json?version=" + UIVersion,
                /* cache: false, */ 
                cache: true,
                type: 'GET',
                dataType: "text",
                success: function (response) {
                    //console.log(response, " READ DATA")
                    if(response.length == 0) {
                        document.getElementById("loadingText").innerHTML += '<font color="red">Error: Rules data not available.' + "</font><br>"
                    } else { 
                        faqsDataList = []
                        var mResponseSet = response.replace(/�/g, "") 
                        var newSettingData = eval(mResponseSet)
                        for(var i=0; i<newSettingData.length; i++) {
                            var settingDataSting = JSON.stringify(newSettingData[i]);
                            if(isJSONData(settingDataSting) == false) {
                                document.getElementById("loadingText").innerHTML += '<font color="red">Error: Rules Sheet : (Row: ' + i + ")</font><br>"
                                updateInfoTextView()
                            } else {
                                faqsDataList[i] = isJSONData(settingDataSting)
                            }
                        }
                        setTimeout(function() {
                            let faqList = ''
                            for(var i=0; i<faqsDataList.length; i++) {
                                if(faqsDataList[i].Type == 'faq') {
                                    faqList += `<li id="faqItem_${i}" style="color: lightgray; left: 4vh; position: relative;font-size: 3.5vh; top: 2vh; height:auto; line-height:1; margin-bottom:2vh; cursor: pointer;"> <span style="position: relative; top: 0vh !important; color: #F7AE50; font-size: 3.5vh;">${faqsDataList[i].Question}</span><li id="faq_${i}" style="color: #2D2C2B; left: 2vh; position: relative;font-size: 3vh; top: 2vh; height:auto; line-height:1; margin-bottom:2vh; display:none"><span style="position: relative; top: 0vh !important; color: #FFFFFF; font-size: 3vh;">${faqsDataList[i].Answer}</span></li></li>`
                                }
                            }
                            document.getElementById('menuList').innerHTML = faqList;
                            activateFaqElement(0)
                            // Event Listener
                            setTimeout(function() {
                                for(var i=0; i<faqsDataList.length; i++) {
                                    if(faqsDataList[i].Type == 'faq') {
                                        // Add events
                                        document.getElementById('faqItem_' + i).addEventListener('touchstart', onFaqItemTouchStart)
                                        document.getElementById('faqItem_' + i).addEventListener('touchend', onFaqItemTouchEnd)

                                        document.getElementById('faqItem_' + i).addEventListener('mousedown', onFaqItemTouchStart)
                                        document.getElementById('faqItem_' + i).addEventListener('mouseup', onFaqItemTouchEnd)

                                        console.log("FAQs DONE")
                                    }
                                }
                            }, 250)

                        }, 10) 
                    }
                },
                error: function(e) {
                    console.log("ERROR - Faqs data missing..")
                    document.getElementById("loadingText").innerHTML += '<font color="red">Error: Missing Sheet : Faqs</font><br>'
                    document.getElementById("spinnerBox").style.display = 'none'
                }
            })
            // Clear memory
            FaqsRequest.onreadystatechange = null;
            FaqsRequest.abort = null;
            FaqsRequest = null;
        }, 1000)
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} event 
     */
    function onFaqItemTouchStart(event) {
        if(event.cancelable) event.preventDefault();
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} event 
     */
    function onFaqItemTouchEnd(event) {
        if(event.cancelable) event.preventDefault();

        let touchStatus = handleTouchEnd(event);
        if(touchStatus == false) {
            return;
        }

        let faqItemId = event.target.parentElement.id.split('_')[1];
        if(faqItemId == undefined) return;
        activateFaqElement(faqItemId)
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
                    document.getElementById('faq_'+i).style.display = 'block';
                } else {
                   document.getElementById('faq_'+i).style.display = 'none'; 
                }
            }
        }
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * LoadGameRules
     */
    function LoadGameRules() {
        setTimeout(function() {
            var RulesRequest = $.ajax({
                //url: '../steps/sheets/' + sheet_Id + "/rules-" + activeLang.split('-')[0].toLowerCase() + ".json?version=" + Math.random(),
                url: '../steps/sheets/' + sheet_Id + "/rules-" + activeLang.split('-')[0].toLowerCase() + ".json?version=" + UIVersion,
                /* cache: false, */ 
                cache: true,
                type: 'GET',
                dataType: "text",
                success: function (response) {
                    //console.log(response, " READ DATA")
                    if(response.length == 0) {
                        document.getElementById("loadingText").innerHTML += '<font color="red">Error: Rules data not available.' + "</font><br>"
                    } else { 
                        rulesDataList = []
                        var mResponseSet = response.replace(/�/g, "") 
                        var newSettingData = eval(mResponseSet)
                        for(var i=0; i<newSettingData.length; i++) {
                            var settingDataSting = JSON.stringify(newSettingData[i]);
                            if(isJSONData(settingDataSting) == false) {
                                document.getElementById("loadingText").innerHTML += '<font color="red">Error: Rules Sheet : (Row: ' + i + ")</font><br>"
                                updateInfoTextView()
                            } else {
                                rulesDataList[i] = isJSONData(settingDataSting)
                            }
                        }
                        setTimeout(function() {
                            let ruleList = ''
                            for(var i=0; i<rulesDataList.length; i++) {
                                if(rulesDataList[i].Type == 'menu') {
                                    ruleList += `<li id="rulesItem_${i}" style="color: lightgray; left: 4vh; position: relative;font-size: 3.5vh; top: 2vh; height:auto; line-height:1; margin-bottom:2vh; cursor: pointer;"> <span style="position: relative; top: 0vh !important; color: #F7AE50; font-size: 3.5vh;">${rulesDataList[i].ID}</span></li>`
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

                                    console.log("RULES DONE")
                                }
                            }
                            DoDownloadRulesBtnEvents();
                        }, 10) 
                    }
                },
                error: function(e) {
                    console.log("ERROR - Rules data missing..")
                    document.getElementById("loadingText").innerHTML += '<font color="red">Error: Missing Sheet : Rules</font><br>'
                    document.getElementById("spinnerBox").style.display = 'none'
                }
            })
            // Clear memory
            RulesRequest.onreadystatechange = null;
            RulesRequest.abort = null;
            RulesRequest = null;
        }, 1000)
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} event 
     */
    function onRuleItemTouchStart(event) {
        if(event.cancelable) event.preventDefault();
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
            return;
        }

        let ruleItemId = event.target.parentElement.id.split('_')[1];
        //console.log(ruleItemId, " --- ruleItemId")
        if(ruleItemId == undefined) return;
        document.getElementById('menuDetailsPage').style.display = 'flex'
        //console.log(rulesDataList[ruleItemId])
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
        //console.log(getMenuDetailItemEndIndex(ruleItemId))
        //console.log(languageStepsData[ruleItemId])
        //FillSelectedMenuDetailsData()
        document.getElementById('menuDetails').innerHTML = ''

        let endIndex = getMenuDetailItemEndIndex(ruleItemId)
        let menuItemToDisplay = ''
        for(var i=Number(ruleItemId)+1; i<endIndex; i++) {
            //console.log(rulesDataList[i], ' DATA TO DISPLAY')
            if(rulesDataList[i].Type == 'text') {
                //menuItemToDisplay = rulesDataList[i].Text
                // Format Text for adding special icons
                let mWordBerry = rulesDataList[i].Text.replaceAll('[BERRY]', `<img class="specialIcons" src="${berryImgPath}"</img>`)
                let mWordDice = mWordBerry.replaceAll('[DICE]', `<img class="specialIcons" src="${diceImgPath}"</img>`)
                let mWordNut = mWordDice.replaceAll('[NUT]', `<img class="specialIcons" src="${nutImgPath}"</img>`)
                let mWordBug = mWordNut.replaceAll('[BUG]', `<img class="specialIcons" src="${bugImgPath}"</img>`)
                let mWordOops = mWordBug.replaceAll('[OOPS]', `<img class="specialIcons" src="${oopsImgPath}"</img>`)
                //console.log(mWordOops, " final")

                //document.getElementById('menuDetails').innerHTML += `<p style="margin-top:3vh; font-size:3vh; line-height:1.1; width:100%; color:white" >${rulesDataList[i].Text}</p>`

                document.getElementById('menuDetails').innerHTML += `<p style="margin-top:3vh; font-size:3vh; line-height:1.1; width:100%; color:white" >${mWordOops}</p>`

            } else if(rulesDataList[i].Type == 'image') {
                let imagePath = ''
                if (rulesDataList[i].Text.includes("https://drive.google.com")) {
                    let imgid = rulesDataList[i].Text.split('https://drive.google.com')[1].split('/')[3];
                    let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                    // image from spreadsheet id folder
                    imagePath = '../steps/sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();
                } else {
                    // Cache Image
                    let name = rulesDataList[i].Text.split('/')
                    let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                    // image from spreadsheet id folder
                    imagePath = '../steps/sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
                }
                document.getElementById('menuDetails').innerHTML += `<div style="margin-top:3vh; font-size:3vh; line-height:1.1; width:100%"><img src=${imagePath} alt=""</img></div>`
            }
        }
        console.log(menuItemToDisplay)
        //document.getElementById('menuDetails').innerHTML = menuItemToDisplay;
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} index 
     */
    function getMenuDetailItemEndIndex(index) {
        let lastIndex = -1
        for(var i=Number(index)+1; i<rulesDataList.length; i++) {
            /* if(rulesDataList[i].ID != '') {
                lastIndex = i */
                //console.log(i+1)
                //console.log(rulesDataList[i], " --- ")
                /* return lastIndex
            }  */
           //console.log(rulesDataList[i])
           if(rulesDataList[i].Type == 'menu') {
            // console.log(i, " menu Index")
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
        if(event.type == 'mouseup') {
            touch = event;
        } else {
            touch = event.changedTouches[0];
        }
        const element = event.target; // Or use the element from step 1
        const rect = element.parentElement.getBoundingClientRect();

        //console.log(element, " --- ", element.parentElement)

        if(touch.clientX >= rect.left && touch.clientX <= rect.right && touch.clientY >= rect.top && touch.clientY <= rect.bottom) {
            //console.log("Inside button")
            inButton = true;
        } else {
            //console.log("outside button")
            inButton = false
        }
        return inButton;
    }
    ///////////////////////////////////////////////////////////////////////////////
})
    
