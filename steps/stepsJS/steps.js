//////////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 * Ready events
 */
$(document).ready(function() {
    console.log('READY STEPS')
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

    let langLoadCount = 0;

    //return;
    //////////////////////////////////////////////////////////////////////////
    // Default value
    document.getElementById('prevIcon').style.opacity = '0.5'
    document.getElementById('prevIcon').style.pointerEvents = 'none';
    document.getElementById('nextIcon').style.pointerEvents = 'auto';

    ///////////////////////////////////////////////////////////////////////////
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
    // Exit Icon
    document.getElementById('ExitButtonPanel').style.display = 'block';
    document.getElementById('exitIcon').addEventListener('touchstart', onExitStart)
    document.getElementById('exitIcon').addEventListener('touchend', onExitClick)
    document.getElementById('exitIcon').addEventListener('mousedown', onExitStart)
    document.getElementById('exitIcon').addEventListener('mouseup', onExitClick)

    var activeLang = (getUrlVars()["code"]) ? getUrlVars()["code"].split('/')[0].toUpperCase() : navigator.language.split('-')[0].toUpperCase();

    var sheet_Id = (getUrlVars()["id"]) ? getUrlVars()["id"].split('/')[0] : '';

    // to get jump id
    var jumpId = (document.location.search.substr(1).split('&')[1].split('=')[0] != 'id' && document.location.search.substr(1).split('&')[1] != undefined) ? document.location.search.substr(1).split('&')[1] : '';
    /////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} str 
     * @returns 
     */
    let isJSONData = str => {
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

        // To check if entry point is there or not
        if(jumpId != '') {
            window.history.replaceState({}, "null", (winLoc + "?code=" + browserLang.toLowerCase() +"&"+ jumpId + "&id=" + sheet_Id));
        } else {
            window.history.replaceState({}, "null", (winLoc + "?code=" + browserLang.toLowerCase() + "&id=" + sheet_Id));
        }
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
                    enableButtons();
                }, 100)
            }, 10)
            
          } 
        }
      }
    //////////////////////LANG SETTINGS START///////////////////////////////////
    function loadSettingsData() {
        // Loading settings.json
        setTimeout(function() {
            var settingRequest = $.ajax({
                url: '../steps/sheets/' + sheet_Id + "/settings.json?version=" + Math.random(),
                cache: false, 
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
                            
                        })
                        /////////////////////LANG SETTINGS START////////////////////////
                        setTimeout(function() {
                            loadLanguageJSON()
                        }, 500) 
                    }
                },
                error: function(e) {
                    console.log("EEEEE - Setting data missing..")
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
    // Loading Language JSON
    /**
     * 
     */
    function loadLanguageJSON() {
        // Loading steps json
        var langRequest = $.ajax({
            url: '../steps/sheets/' + sheet_Id + "/steps-" + activeLang.split('-')[0].toLowerCase() + ".json?version=" + Math.random(),
            cache: false, 
            type: 'GET',
            dataType: "text",
            success: function (response) {
                //console.log(response, " READ DATA")
                if(response.length == 0) {
                    activeLang = "EN"
                    loadLanguageJSON()
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
                    // set default
                    if(jumpId == '') {
                        jumpId = languageStepsData[0].ID
                    } else {
                        let isEntryPoint = isEntryPointExists(jumpId)
                        if(isEntryPoint == false) {
                            jumpId = languageStepsData[0].ID
                        }
                    }
                    
                    // For View link Text
                    adjustFontSizeMultiple(getViewLinkText(), document.getElementById('viewLinkLabel'), 1, 'learnPlay')

                    // convert values to all defined format
                    if(lazyLoadImages == "FALSE" || lazyLoadImages == "False" || lazyLoadImages == "false" || lazyLoadImages == "0") {
                        PreloadAllToCache();
                    } else {
                        jumpToStepScreen()
                    }
                }
            },
            error: function (response) {
                console.log("NO FILE FOUND")
                if(activeLang.toLowerCase() != 'EN' && langLoadCount < 1) {
                    langLoadCount++
                    activeLang = 'EN'
                    loadLanguageJSON()
                } else {
                    langLoadCount = 0;
                    //console.log("EEEEE - Language data missing..")
                    document.getElementById("loadingText").innerHTML += '<font color="red">Error: Missing Sheet : ' + activeLang + '</font><br>'
                    document.getElementById("spinnerBox").style.display = 'none'
                }
            }
        })
        // Clear memory
        langRequest.onreadystatechange = null;
        langRequest.abort = null;
        langRequest = null;
    }
    ///////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} _entryPoint 
     * @returns 
     */
    function isEntryPointExists(_entryPoint) {
        let isExistsEntryPoint = false;
        for(var i=0; i<languageStepsData.length; i++) {
            if(_entryPoint == languageStepsData[i].ID) {
                isExistsEntryPoint = true
            }
        }
        return isExistsEntryPoint
    }
    ///////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} _entryPoint 
     * @returns 
     */
    function getViewLinkText() {
        let viewLinkText = '';
        for(var i=0; i<languageStepsData.length; i++) {
            if(languageStepsData[i].ViewLink.indexOf(',') != -1) {
                viewLinkText = languageStepsData[i].ViewLink.split(',')[0]
            }
        }
        return viewLinkText
    }
    ///////////////////////////////////////////////////////////////////////////
    //return;
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
        document.getElementById('prevIcon').style.scale = '0.95'
    }
    /////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} event 
     */
    function onNextStart(event) {
        event.preventDefault();
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
        document.getElementById('viewIcon').style.scale = '0.95'
    }
    /////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} event 
     */
    function onViewLinkClick(event) {
        event.preventDefault();
        document.getElementById('viewIcon').style.scale = '1'
        if(activeViewLink != '') {
            // open to new webpage
            window.open(activeViewLink, "_new")
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
        document.getElementById('exitIcon').style.scale = '1'
        window.parent.postMessage(JSON.stringify({'message': 'closeFrame'}), '*')
    }
    /////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} event 
     * @returns 
     */
    function onPrevClick(event) {
        event.preventDefault();
        document.getElementById('prevIcon').style.scale = '1'
        document.getElementById('prevIcon').style.pointerEvents = 'none';
        moveType = 'left'
        clearTimeout(autoPlay)
        if(languageStepsData[stepIndex-1] == undefined) {return}
        let stepType = languageStepsData[stepIndex-1].Type;
        let stepDuration = languageStepsData[stepIndex].Duration;
        let stepDurationPrev = languageStepsData[stepIndex-1].Duration;
        if(typeof(stepDuration) == 'string') {
            return;
        }
        if(stepType != '' && stepType != 'loading' && typeof(stepDurationPrev) != 'string') {
            // New Logic with index
            prevImage = getActiveAndNextImage(stepIndex)
            newImage = getActiveAndNextImage(stepIndex-1)
            setTimeout(function() {
                stepIndex--;
                // To jump
                if(typeof(languageStepsData[stepIndex].Duration) == 'string') {
                    stepIndex--;
                }
                updateMiddleImageSection(stepIndex)
            }, 500)
        } else {
            let prevStep = languageStepsData[stepIndex].Prev;
            if(prevStep == 'END') {
                console.log("EXIT FROM PREV")
                window.parent.postMessage(JSON.stringify({'message': 'closeFrame'}), '*')
            }
        }
    }
    ///////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} event 
     * @returns 
     */
    function onPrevClick_WRK(event) {
        event.preventDefault();
        if(stepIndex == 0) {return}
        $("#stepBGInage").fadeOut();
        $("#stepText").fadeOut();
        setTimeout(function() {
            let stepID = languageStepsData[stepIndex].Prev;
            if(stepID != '') {
                stepIndex = getIndexUsingID(stepID);
                updateTopInstructionText(stepIndex)
            } else {
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
        clearTimeout(autoPlay)
        document.getElementById('nextIcon').style.pointerEvents = 'none';
        // Restart pulsating effect after pause
        document.getElementById('nextIcon').style.animationPlayState = "running";
        moveType = 'right'
        if(languageStepsData[stepIndex+1] != undefined) {
            let stepType = languageStepsData[stepIndex+1].Type;
            if(stepType != '' && stepType != 'loading') {
                // New Logic with index
                prevImage = getActiveAndNextImage(stepIndex)
                newImage = getActiveAndNextImage(stepIndex+1)
                setTimeout(function() {
                stepIndex++
                // To jump
                if(typeof(languageStepsData[stepIndex].Duration) == 'string') {
                    stepIndex++;
                }
                updateMiddleImageSection(stepIndex)
                document.getElementById('prevIcon').style.opacity = '1'
                document.getElementById('nextIcon').style.opacity = '1'
                }, 500)
            } else {
                let nextStep = languageStepsData[stepIndex].Next;
                if(nextStep == 'END') {
                    console.log("EXIT FROM NEXT")
                    window.parent.postMessage(JSON.stringify({'message': 'closeFrame'}), '*')
                } else {
                }
            }
        }
    }
    //////////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} imgIndex 
     * @returns 
     */
    function getActiveAndNextImage(imgIndex) {
        let ImgPath = ''
        if(languageStepsData[imgIndex].Image != '') {
            let imagePath = ''
            if (languageStepsData[imgIndex]['Image'].includes("https://drive.google.com")) {
                let imgid = languageStepsData[imgIndex]['Image'].split('https://drive.google.com')[1].split('/')[3];
                let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                // Cache Image
                // cacheImages moved to spreadsheet id folder
                imagePath = './sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png';
                
            } else {
                // Cache Image
                let name = languageStepsData[imgIndex]['Image'].split('/')
                let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                // New Changes
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
        clearTimeout(autoPlay)
        let stepID = languageStepsData[stepIndex].Next;
        if(stepID == "END") {return}
        // Transition
        $("#stepBGInage").fadeOut();
        $("#stepText").fadeOut();
        setTimeout(function() {
            if(jumpId != '') {
                let stepID = languageStepsData[stepIndex].Next;
                if(stepID == "END") {return}
                if(stepID != '') {
                    stepIndex = getIndexUsingID(stepID);
                    updateTopInstructionText(stepIndex)
                } else {
                    if(languageStepsData[stepIndex+1].Type != 'step') {return}
                    stepIndex++;
                    updateTopInstructionText(stepIndex)
                }
            } else {
                if(languageStepsData[stepIndex+1].Type != 'step') {return}
                if(languageStepsData[stepIndex+1].Type == 'step') {    
                    let stepID = languageStepsData[stepIndex+1].Next;
                    if(stepID == "END") {return}
                    if(stepID != '') {
                        stepIndex = getIndexUsingID(stepID);
                        updateTopInstructionText(stepIndex)
                    } else {
                        stepIndex++;
                        updateTopInstructionText(stepIndex)
                    }
                } else {
                    stepIndex--;
                }
            }
        }, 500)
    }
    /////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @returns 
     */
    function checkAutoPlayStat() {
        let autoPlayTimer = languageStepsData[stepIndex].Duration
        if(autoPlayTimer == 0 || autoPlayTimer == '') {return}
        autoPlay = setTimeout(function() {
            clearTimeout(autoPlay)
            onNextClick(null)
        }, autoPlayTimer * 1000)
    }
    /////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     */
    function EnableCloseEvents() {
        document.getElementById('homeIcon').addEventListener('touchstart', onHomeStart)
        document.getElementById('homeIcon').addEventListener('touchend', onHomeClick)
        document.getElementById('homeIcon').addEventListener('mousedown', onHomeStart)
        document.getElementById('homeIcon').addEventListener('mouseup', onHomeClick)
    }
    /////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} event 
     */
    function onHomeClick(event) {
        event.preventDefault();
        //reset();
        document.getElementById('homeIcon').style.scale = '1'
        document.getElementById("base-timer-close").style.opacity = 0
        document.getElementById("base-timer-close").style.transition = "opacity 0.5s";
        reset();
        window.parent.postMessage(JSON.stringify({'message': 'toggleFrame'}), '*')
    }
    /////////////////////////////////////////////////////////////////////////////////
    /* Function to animate height: auto */
    /**
     * 
     * @param {*} element 
     * @param {*} time 
     */
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
        // Undo later on
        document.getElementById('stepText').innerHTML = languageStepsData[index].Text
        // Check whether to display top bar or not
        activeSlideIndex = index;
        if(languageStepsData[index].Text != '') {
            document.getElementById('topStepBar').style.display = 'flex' 
        } else {
            document.getElementById('topStepBar').style.display = 'none'
        }
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
        el.height(curHeight).animate({height: (autoHeight + animationPosition)}, 300);
    }
    /////////////////////////////////////////////////////////////////////////////////
    /**
     * Typewriter effect
     */
    function typeWriter() {
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
        if(languageStepsData[index].Image != '') {
            let imagePath = ''
            if (languageStepsData[index]['Image'].includes("https://drive.google.com")) {
                let imgid = languageStepsData[index]['Image'].split('https://drive.google.com')[1].split('/')[3];
                let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                // Cache Image
                // cacheImages moved to spreadsheet id folder
                imagePath = './sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();
            } else {
                // Cache Image
                let name = languageStepsData[index]['Image'].split('/')
                let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                // New Changes
                // cacheImages moved to spreadsheet id folder
                imagePath = './sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
            }
            if(preCachedDone == false) {
                // Use in case of slow internet
                loadImage(imagePath)
            } else {
                // Use in case of slow internet
                loadImage(preCacheImages[index].src)
            }
        } else {
            document.getElementById('stepBGInage').src = '' 
            moveToSlide()
        }
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
    //////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     */
    function PreloadAllToCache() {
        var imgLoaded = 0
        // Caching settings images
        if(getAllImageCount() > 0) {
            $.each(languageStepsData, function (i, row_setting) {
                if(languageStepsData[i]['Image'] != '') {
                    if (languageStepsData[i]['Image'].includes("https://drive.google.com")) {
                        let imgid = languageStepsData[i]['Image'].split('https://drive.google.com')[1].split('/')[3];
                        let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                        // Cache Image
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
                        // Cache Image
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
            })
        } else {
            showMessageInfo(imgLoaded)
        }
        setTimeout(function() {
        }, 4000)
    }
    ///////////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} imgCount 
     */
    function checkImageStatus(imgCount) {
        showMessageInfo(imgCount)
    }
    ///////////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     */
    function jumpToStepScreen() {
        $.each(settingDataList, function (index_setting, row_setting) {
            if(row_setting['Name'] == 'PrevButtonUrl') {
                if(row_setting['Value'] != '') {
                    let imagePathPrev = ''
                    if (row_setting['Value'].includes("https://drive.google.com")) {
                        let imgid = row_setting['Value'].split('https://drive.google.com')[1].split('/')[3];
                        let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                        // Cache Image
                        // cacheImages moved to spreadsheet id folder
                        imagePathPrev = './sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();
                    } else {
                        // Cache Image
                        let name = row_setting['Value'].split('/')
                        let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                        // cacheImages moved to spreadsheet id folder
                        imagePathPrev = './sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
                    }
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
                        // cacheImages moved to spreadsheet id folder
                        imagePathNext = './sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();
                    } else {
                        // Cache Image
                        let name = row_setting['Value'].split('/')
                        let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                        // cacheImages moved to spreadsheet id folder
                        imagePathNext = './sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
                    }
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
                        // cacheImages moved to spreadsheet id folder
                        imagePathQuit = './sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();
                    } else {
                        // Cache Image
                        let name = row_setting['Value'].split('/')
                        let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                        // cacheImages moved to spreadsheet id folder
                        imagePathQuit = './sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
                    }
                    document.getElementById('homeIcon').src = imagePathQuit;
                }
            }
        })
        setTimeout(function() {
            // Preload All Images in background
            PreloadAllToCacheInBackground()
        }, 10)

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
        if(startType == 'loading') {
            setTimeout(function() {
                stepIndex++;
                updateMiddleImageSection(stepIndex)
            }, startDuration * 1000)
        } else {
            updateMiddleImageSection(stepIndex)
        }
        console.log("Caching In Background")
    }
    ///////////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     */
    function PreloadAllToCacheInBackground() {
        var imgLoaded = 0
        $.each(languageStepsData, function (i, row_setting) {
            if(languageStepsData[i]['Image'] != '') {
                if (languageStepsData[i]['Image'].includes("https://drive.google.com")) {
                    let imgid = languageStepsData[i]['Image'].split('https://drive.google.com')[1].split('/')[3];
                    let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                    // Cache Image
                    // image from spreadsheet id folder
                    let imagePath = './sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();
                    checkIfImageExists(imagePath, (isExists) => {
                        if(isExists) {
                            let bgImage = new Image();
                            bgImage.src = imagePath
                            // Storing to the container
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
                } else {
                    // Cache Image
                    let name = languageStepsData[i]['Image'].split('/')
                    let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                    
                    // image from spreadsheet id folder
                    let imagePath = './sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
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
        showCacheMessageInfo(imgCount)
    }
    ///////////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} _count 
     */
    function showCacheMessageInfo(_count) {
        getDataColumnValueWhileLoading(_count)
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
     * 
     * @param {*} _count 
     */
    function showMessageInfo(_count) {
        getDataColumnValueWhileLoading(_count)
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
            $.each(settingDataList, function (index_setting, row_setting) {
                if(row_setting['Name'] == 'PrevButtonUrl') {
                    if(row_setting['Value'] != '') {
                        let imagePathPrev = ''
                        if (row_setting['Value'].includes("https://drive.google.com")) {
                            let imgid = row_setting['Value'].split('https://drive.google.com')[1].split('/')[3];
                            let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                            // Cache Image
                            // image from spreadsheet id folder
                            imagePathPrev = './sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();
                        } else {
                            // Cache Image
                            let name = row_setting['Value'].split('/')
                            let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                            // image from spreadsheet id folder
                            imagePathPrev = './sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
                        }
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
                            // image from spreadsheet id folder
                            imagePathNext = './sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();
                        } else {
                            // Cache Image
                            let name = row_setting['Value'].split('/')
                            let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                            // image from spreadsheet id folder
                            imagePathNext = './sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
                        }
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
                            // image from spreadsheet id folder
                            imagePathQuit = './sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();
                        } else {
                            // Cache Image
                            let name = row_setting['Value'].split('/')
                            let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                            // image from spreadsheet id folder
                            imagePathQuit = './sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
                        }
                        document.getElementById('homeIcon').src = imagePathQuit;
                    }
                }
            })
            setTimeout(function() {
            }, 500)

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
            if(startType == 'loading') {
                setTimeout(function() {
                    stepIndex++;
                    updateMiddleImageSection(stepIndex)
                }, startDuration * 1000)
            } else {
                updateMiddleImageSection(stepIndex)
            }
            console.log("IMAGES CACHED")
        } else {
        }
    }
    /////////////////////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} startInt 
     */
    function getDataColumnValueWhileLoading(startInt) {
        let index = getIndexUsingID(jumpId);
        if(languageStepsData[index + (startInt-1)] != undefined) {
            if(languageStepsData[index + (startInt-1)].Type == "Step" && endFound == false) {
                endFound = true
                document.getElementById('appInfo').innerHTML = "Loading..";
            } else if(languageStepsData[index + (startInt-1)].Next == "END") {
                endFound = true
            } else if(languageStepsData[index + (startInt-1)].Type == "loading" && endFound == false) {
                endFound = true
                dispText = languageStepsData[index].Text
                if(dispText == '') {
                    document.getElementById('appInfo').innerHTML = "Loading..";
                } else {
                    document.getElementById('appInfo').innerHTML = dispText;
                }
            }
        }
    }
    ///////////////////////////////////////////////////////////////////////
    /**
     * 
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
     * 
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
    //////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @param {*} _type 
     */

    // Do Animate circle
    FULL_DASH_ARRAY = 283;
    RESET_DASH_ARRAY = `-57 ${FULL_DASH_ARRAY}`;
    timerClose = document.querySelector("#base-timer-path-remaining-close");
    TIME_LIMIT = 3; //5; //in seconds
    timePassed = 1;
    timeLeft = TIME_LIMIT;
    timerInterval = null;

    function doAnimateCloseButton(_type) {
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
    timePassed = 1;
    TIME_LIMIT = 3; //5;
    timerInterval = setInterval(() => {
      clearInterval(timerInterval)
      ContinueStartTimer(_type)
      timeLeft = TIME_LIMIT - timePassed;
      setCircleDasharray(_type);
      if (timeLeft === 0) {
      }
    }, 100);
  }
  ////////////////////////////////////////////////////////////////////////////////////
  /**
   * 
   * @param {*} _type 
   */
  function ContinueStartTimer(_type) {
    timePassed = 1;
    // New
    TIME_LIMIT = 3; //5;
    timerInterval = setInterval(() => {
      timePassed = timePassed += 1;
      timeLeft = TIME_LIMIT - timePassed;
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
        reset();
        setTimeout(function() {
        }, 300)
    }
    ////////////////////////////////////////////////////////////////////////////////////
    /**
     * resetVars()
     */
    function resetVars() {
        if(timeLeft == 0 || timeLeft == 3) {
        }
        timePassed = 0;
        timeLeft = TIME_LIMIT;
        FULL_DASH_ARRAY = 283;
        RESET_DASH_ARRAY = `-57 ${FULL_DASH_ARRAY}`;
        TIME_LIMIT = 3; //in seconds
        // New
        timeLeft = TIME_LIMIT;
        timerInterval = null;
        timerClose.setAttribute("stroke-dasharray", RESET_DASH_ARRAY);
        // Reset All Timer values
        timerClose = document.querySelector("#base-timer-path-remaining-close");
        // Reset Vars
        document.getElementById("base-timer-close").style.opacity = 0
        // New Change
        FULL_DASH_ARRAY = 283;
        RESET_DASH_ARRAY = `-57 ${FULL_DASH_ARRAY}`;
        timerClose = document.querySelector("#base-timer-path-remaining-close");
        TIME_LIMIT = 3; //5; //in seconds
        timePassed = 0;
        timeLeft = TIME_LIMIT;
        timerInterval = null;
        let circleDasharray = `${(
        calculateTimeFraction() * FULL_DASH_ARRAY
        ).toFixed(0)} 283`;
        timerClose.setAttribute("stroke-dasharray", circleDasharray);
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
        timerClose.setAttribute("stroke-dasharray", circleDasharray);
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
     * 
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
     * 
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
            //console.log(e.loaded / e.total * 100, " perc");
        }
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     */
    function showImage() {
        var imageElement = "data:image/jpeg;base64," + base64Encode(request.responseText);
        // Fade Out
        if(prevImage != newImage) {
            $("#stepBGInage").fadeOut();
        }
        $("#stepText").fadeOut();
        $("#viewIcon").fadeOut();
        $("#viewIconText").fadeOut();
        setTimeout(function() {
            document.getElementById('stepBGInage').src = imageElement;
            moveToSlide();
        }, 500)
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     */
    function hideProgressBar() {
        document.getElementById('spinnerMiddleBox').style.display = 'none'
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
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

    ///////////////////////////////////////////////////////////////////////////
    /**
     * 
     * @returns 
     */
    function DetectSpecificDevice() {
    var OSType = null;
    if(deviceDetector.device == 'tablet' || deviceDetector.device == 'desktop') {
        OSType = 'iPad'
    } else {
        OSType = 'phone'
    }
    return OSType;
    }
    ///////////////////////////////////////////////////////////////////////////////
    /**
     * 
     */
    function moveToSlide() {
        setTimeout(function() {
            // Live
            document.getElementById('stepText').innerHTML = languageStepsData[stepIndex].Text
            if(languageStepsData[activeSlideIndex].Text != '') {
                document.getElementById('topStepBar').style.display = 'flex' 
            } else {
                document.getElementById('topStepBar').style.display = 'none'
            }
            $("#stepText").fadeIn();
            $("#stepBGInage").fadeIn();
            $("#viewIcon").fadeIn();
            $("#viewIconText").fadeOut();
            // Check whether the view link available or not
            let showViewLink = languageStepsData[stepIndex].ViewLink;
            if(showViewLink != '') {
                let checkshowLinkText = showViewLink.split(',');
                if(checkshowLinkText[0].includes("http") == false) {
                    document.getElementById('viewIcon').style.display = 'none'
                    document.getElementById('viewLinkLabel').innerHTML = checkshowLinkText[0];
                    document.getElementById('viewIconText').style.display = 'inline-flex'
                    // Animate buttons
                    var el = $('#viewIconText')
                    var curW = el.width()
                    var textLen = document.getElementById('viewLinkLabel').innerText.length
                    var wValue = 220
                    if(textLen < 10) {
                        wValue = DetectSpecificDevice() == 'iPad' ? 370 : 240;
                    } else if(textLen >= 10 && textLen <= 12) {
                        wValue = DetectSpecificDevice() == 'iPad' ? 530 : 260;
                    } else if(textLen >= 12 && textLen <= 15) {
                        wValue = DetectSpecificDevice() == 'iPad' ? 530 : 340;
                    } else if(textLen >= 15 && textLen <= 20) {
                        wValue = DetectSpecificDevice() == 'iPad' ? 670 : 450;
                    } else {
                        wValue = DetectSpecificDevice() == 'iPad' ? 850 : 550;
                    }
                    // Change border styling
                    document.getElementById('viewIconContainer').style.borderRadius = DetectSpecificDevice() == 'iPad' ? '25px' : '20px'
                    document.getElementById('viewIconContainer').style.border = DetectSpecificDevice() == 'iPad' ? '3.5px dashed #F7AE4F' : '1.8px dashed #F7AE4F'
                    activeViewLink = checkshowLinkText[1]
                } else {
                    document.getElementById('viewIcon').style.display = 'block'
                    document.getElementById('viewLinkLabel').innerHTML = '';
                    document.getElementById('viewIconText').style.display = 'none'
                    activeViewLink = showViewLink
                }
            } else {
                document.getElementById('viewIcon').style.display = 'none'
                document.getElementById('viewLinkLabel').innerHTML = '';
                document.getElementById('viewIconText').style.display = 'none'
                activeViewLink = ''
            }
            if(prevImage == '' || newImage == '') {
                // Moved here
                // Load Step screen
                document.getElementById('stepsScreen').style.display = 'block'
                document.getElementById('loadingScreen').style.display = 'none'
                setTimeout(function() {
                    animateTopBorder()
                }, 700)
            } else {
                animateTopBorder()
            }
            document.getElementById('prevIcon').style.pointerEvents = 'auto';
            document.getElementById('nextIcon').style.pointerEvents = 'auto';
            if(languageStepsData[stepIndex-1] != undefined) {
                let stepDuration = languageStepsData[stepIndex-1].Duration;
                if(typeof(stepDuration) == 'string') {
                    document.getElementById('prevIcon').style.opacity = '0.5'
                    document.getElementById('prevIcon').style.pointerEvents = 'none';
                    document.getElementById('nextIcon').style.opacity = '1'
                    document.getElementById('nextIcon').style.pointerEvents = 'auto';
                }
                let curType = languageStepsData[stepIndex].Type;
                let curDuration = languageStepsData[stepIndex].Duration;
                if(languageStepsData[stepIndex+1] != undefined) {
                    let nextType = languageStepsData[stepIndex+1].Type;
                    let nextStep = languageStepsData[stepIndex].Next;
                    if(nextType != 'step' && nextStep == '') {
                        document.getElementById('nextIcon').style.opacity = '0.5'
                        document.getElementById('nextIcon').style.pointerEvents = 'none';
                    } else {
                        document.getElementById('nextIcon').style.opacity = '1'
                        document.getElementById('nextIcon').style.pointerEvents = 'auto';
                    }
                    let prevType = languageStepsData[stepIndex-1].Type;
                    let prevStep = languageStepsData[stepIndex].Prev;
                    if(prevType != 'step' && prevStep == '') {
                        document.getElementById('prevIcon').style.opacity = '0.5'
                        document.getElementById('prevIcon').style.pointerEvents = 'none';
                    } else {
                        document.getElementById('prevIcon').style.opacity = '1'
                        document.getElementById('prevIcon').style.pointerEvents = 'auto';
                    }
                    if(typeof(languageStepsData[stepIndex-1].Duration) == 'string' && languageStepsData[stepIndex].Prev == '') {
                        document.getElementById('prevIcon').style.opacity = '0.5'
                        document.getElementById('prevIcon').style.pointerEvents = 'none';
                    } else {
                        document.getElementById('prevIcon').style.opacity = '1'
                        document.getElementById('prevIcon').style.pointerEvents = 'auto';
                    }
                    if(nextStep == 'END') {
                    } else {
                    }
                }
            }
            checkAutoPlayStat();
        }, 10)
    }
})
/////////////////////////////////////////////////////////////////////////////////
