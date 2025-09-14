////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// For Refresh the page
//document.getElementById('versionId').innerHTML = 'Checking updates...'
///////////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 */
function getCurrentVersion() {
 // Loading version.js dynamically for [mac fix]
 var newScript = document.createElement('script');
 newScript.type = 'text/javascript';
 newScript.src = 'js-package/version.js?version=' + Math.random();
 document.getElementsByTagName('head')[0].appendChild(newScript);
}
///////////////////////////////////////////////////////////////////////////////////////////
//return;
getCurrentVersion()
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 * @returns 
 */
const detectDeviceType = () =>
    /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)
        ? 'Mobile'
        : 'Desktop';
// Called whenever an update has been found and is installing
//listener.onupdateinstalling = installingevent => console.log('Update is installing.');
// Called whenever an update is done installing and is waiting
var buttonElem = ''
if(detectDeviceType() == 'Desktop') {
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
buttonElem = `<p id='btnRefresh' style="position: absolute; right: 1em; top: .8em; font-weight: 700; border: 0; font-size: 1.2em; border-radius: 2px; padding:0.5em; color:#A7C1F6; cursor:pointer; z-index:99999; font-family: Font-default;">REFRESH</p>
    <p style="position: absolute; left: 0em; top: 1.2em; font-weight: 700; border: 0; font-size: 1.2em; border-radius: 2px; padding:0.5em">
    <img id='closeBox' src='images/close.png' alt="" style="filter: saturate(500%) contrast(800%) brightness(500%) 
    invert(100%) sepia(0%) hue-rotate(0deg); position: relative; width: 30px; top: -14px; left: 10px; cursor:pointer;" />
    </p>
    `
} else {
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
buttonElem = `<p id='btnRefresh' style="position: absolute; right: 0.5em; top: 0.8em; font-weight: 700; border: 0; font-size: 1.4em; border-radius: 2px; padding:0.5em; color:#A7C1F6; cursor:pointer; z-index:99999; font-family: Font-default;">REFRESH</p>
    <p style="position: absolute; left: 0.5em; top: 0.9em; font-weight: 700; border: 0; font-size: 1.2em; border-radius: 2px; padding:0.5em">
    <img id='closeBox' src='images/close.png' alt="" style="filter: saturate(500%) contrast(800%) brightness(500%) 
    invert(100%) sepia(0%) hue-rotate(0deg); position: relative; width: 30px; cursor:pointer;" />
    </p>`
}
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//localStorage.setItem("refreshStatus", "null")
/**
 * Global Variables
 */
var loadType = ""
var settingLoaded = false
var privateLoaded = false
var gameLoaded = false
var deviceType = ""
var clickedSlideID = -1
var slides_loaded = false
let gameMsg = ""
let eventSliderActive = false
let MODE_TYPE = ""
let pollTime = 10

let prevRenderEvents = 0
let activeLanguage = "eng"
let inLanguageProcess = false
let activeMenuIndex = -1

// To store active click object
let activeEventObject = null
let activeEventIndex = -1;

let activeLayout = ''
let addLanguage = ''

let onEvents = false;
let downIndex = -1;
let upIndex = -1;
let imageLoadedCount = 1

/////////////////////////////////////////////////////////////////////////////////////////////////
// Image Holder
let tempLangHolder = []
let tempInstallHolder = []
//////////////////////////////////////////////////////////////////////////////////////////////////
/**
 * Checking open browser stats
 */
if (window.performance) {
    //console.log("ENTER HERE")
    if (performance.navigation.type == 1) {
        //alert( "This page is reloaded" );
        // For loading cache always
        loadType = "refresh"
    } else {
        //alert( "This page is not reloaded");
        loadType = "normal"
    }
}
//////////////////////////////////////////////////////////////////////////////////////////////////
/**
 * IDLE TIMEOUT
 */
let idleFrom = ''
let idleTime;
let idleTimeOut = 60 // Idle threshold 3 MINS
let idleStatus = false
// Event to check for
//////////////////////////////////////////////////////////////////////////////////////////////////
/**
 * function to get the url variables passed in url
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
//////////////////////////////////////////////////////////////////////////////////////////////////
// TO LOAD PRIVATE DATA IN THE BEGINNING
let privateDataList = []
let eventsDataList = []
let kioskDataList = []
let languageDataList = []
let sheet_Name = ''
let splash_img = ''
let splashDelaySec = 0
// For version
let currentVersion = ''
let slideShowLoaded = false;
let backgroundWorker = null
//////////////////////////////////////////////////////////////////////////////////////////////////
/**
 * iOS FIX
 * @returns 
 */
function checkCookieStatus(){
    var cookieEnabled = navigator.cookieEnabled;
    return cookieEnabled;
}
//////////////////////////////////////////////////////////////////////////////////////////////////
/**
 * update message function
 */
function updateInfoTextView() {
    //document.getElementById("loadingTxt").scrollTop = 150;
    document.getElementById("loadingTxt").scrollTop += 100;
}
//////////////////////////////////////////////////////////////////////////////////////////////////
/**
 * function tp load Setting from spreadsheet
 */
let settingDataList = []
let installDataList = []
//////////////////////////////////////////////////////////////////////////////////////////////////
/**
 * variable to store the init urlvars
 */
var sheet_Id = (getUrlVars()["id"]) ? getUrlVars()["id"].split('/')[0] : '';
var isPreloadImages = (document.location.search.substr(1).split('&')[1] != '' && document.location.search.substr(1).split('&')[1] != undefined) ? document.location.search.substr(1).split('&')[1] : 'download_images';
//var setVersion_Id = (getUrlVars()["set_version"]) ? getUrlVars()["set_version"].split('/')[0] : 'undefined';
var setVersion_Id = (getUrlVars()["publish_id"]) ? getUrlVars()["publish_id"].split('/')[0] : 'undefined';
//var setVersion_Num = (getUrlVars()["version"]) ? getUrlVars()["version"].split('/')[0] : '';
var getKiosk_Num = (getUrlVars()["kiosk"]) ? getUrlVars()["kiosk"].split('/')[0] : '';
//console.log(sheet_Id, " ---- ", setVersion_Id, " ======= ", setVersion_Num)
//console.log(sheet_Id, " ---- ",)
//console.log(getKiosk_Num, " getKiosk_Num")
var game_action = ''
//////////////////////////////////////////////////////////////////////////////////////////////////
function UpdateAppVersion() {
    //document.getElementById('versionId').innerHTML = 'Version ' + Number(_version).toFixed(1);
    document.getElementById('defaultBGImage').style.display = 'none'
    document.getElementById("loadingTxt").innerHTML = "Publishing Sheet content..<br>"
   /*  document.getElementById("loadingTxt").innerHTML = "App Version: " + Number(_version).toFixed(1) + "<br>"
    updateInfoTextView() */
    var setVersion_Num = ''
    // change status
    //savePublishedStateToServer('false');
    let currentDate = new Date();
   /*  document.getElementById("loadingTxt").innerHTML += "Checking server on " + moment(currentDate).format('MM/DD/YYYY HH:mm:ss').toLocaleString() + "<br>"
    updateInfoTextView() */
    let date_str = moment(currentDate).format('MM/DD/YYYY-HH:mm:ss').toLocaleString();
    //return
    const updateAppTimer = setTimeout(function() {
        clearTimeout(updateAppTimer)
        if(window.navigator.onLine == true) {
            //console.log("INTERNET ACTIVE")
            //document.getElementById("loadingTxt").innerHTML = "Publishing sheet content..<br>"
            //console.log('sheets/' + setVersion_Id + '/version.json')
            /////////////////////////////////////////////////////////////////////////////////
            // Modifying push stat
            var updateRequest = $.ajax({
                url: 'pushSheet.php?version=' + Math.random(), 
                type:'POST', 
                data:{'id' : sheet_Id, 'sheetname' : '', 'date_string' : date_str}, 
                cache: false, 
                // async: false,
                success: function (response) {
                    //console.log(response, " VERSION UPDATED")
                    // document.getElementById("loadingTxt").innerHTML += "Sheet Version: " + response.toString() + "<br>"
                    // updateInfoTextView()
                    document.getElementById('defaultBGImage').style.display = 'none'

                    setTimeout(function() {
                        // let currentDate = new Date();
                        // document.getElementById("loadingTxt").innerHTML += "Checking server on " + moment(currentDate).format('YYYY/MM/DD HH:mm:ss') + "<br>"
                        // updateInfoTextView()
                        // Load Setting Data from server and save it to server settings.json file
                        getSettingsDataFromSheet();
                    }, 100)

                }
            })
            ///////////////////
            // Clear memory
            updateRequest.onreadystatechange = null;
            updateRequest.abort = null;
            updateRequest = null;
            ///////////////////
            /* setTimeout(function() {
                // let currentDate = new Date();
                // document.getElementById("loadingTxt").innerHTML += "Checking server on " + moment(currentDate).format('YYYY/MM/DD HH:mm:ss') + "<br>"
                // updateInfoTextView()
                // Load Setting Data from server and save it to server settings.json file
                getSettingsDataFromSheet();
            }, 100) */
        } else {
            //console.log("NO INTERNET")
            document.getElementById("loadingTxt").innerHTML = "Waiting for active internet...<br>Retrying..." 
            UpdateAppVersion()
        }
    }, 5000)
}
//////////////////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 */
function getSettingsDataFromSheet() {
    if(window.navigator.onLine == true) {
        //console.log("INTERNET ACTIVE")
        //document.getElementById("loadingTxt").innerHTML = "Publishing sheet content..<br>"
        //console.log('sheets/' + setVersion_Id + '/version.json')
        /////////////////////////////////////////////////////////////////////////////////
        var updateRequest = $.ajax({
            url: 'pushSheet.php?version=' + Math.random(), 
            type:'POST', 
            data:{'id' : sheet_Id, 'sheetname' : 'Settings'}, 
            cache: false, 
           /*  async: false, */
            success: function (response) {
                //console.log(response, " Setting Data")
                //return;
                let settingResponse = response
                document.getElementById("loadingTxt").innerHTML += response.toString() + "<br>"
                updateInfoTextView()
                //document.getElementById('defaultBGImage').style.display = 'none'
                //var languageJSON = ''
                setTimeout(function() {
                    //let currentDate = new Date();
                    //document.getElementById("loadingTxt").innerHTML += "Checking server on " + moment(currentDate).format('YYYY/MM/DD HH:mm:ss') + "<br>"
                    //updateInfoTextView()
                    // Load and store setting data to list
                    var settingRequest = $.ajax({
                        url: '../sheets/' + sheet_Id + "/settings.json?version=" + Math.random(), 
                        cache: false, 
                        //async: false,
                        type: 'GET',
                        dataType: "text",
                        success: function (response) {
                            //console.log(response, " READ DATA")
                            if(response.length == 0) {
                                document.getElementById("loadingTxt").innerHTML += '<font color="red">Error: Settings data not available.' + "</font><br>"
                                updateInfoTextView()
                            } else { 
                                //////////////////////////////////////////////////////////////////////////////
                                settingDataList = []
                                var mResponseSettings = response.replace(/�/g, "") 
                                var newSettingsData = eval(mResponseSettings)
                                for(var i=0; i<newSettingsData.length; i++) {
                                    var settingsDataSting = JSON.stringify(newSettingsData[i]);
                                    //console.log(isJSON(pp), " --- ")
                                    //newstr += JSON.stringify(isJSON(pp))
                                    if(isJSONData(settingsDataSting) == false) {
                                        document.getElementById("loadingTxt").innerHTML += '<font color="red">Error: Settings Sheet : (Row: ' + i + ")</font><br>"
                                        updateInfoTextView()
                                    } else {
                                        settingDataList[i] = isJSONData(settingsDataSting)
                                    }
                                }
                                //////////////////////////////////////////////////////////////////////////////
                                $.each(settingDataList, function (index, row) {
                                    if(row['Name'] == 'Title') {
                                        document.getElementById("loadingTxt").innerHTML = 'Sheet Title: ' + row['Value'] + '<br>'
                                        updateInfoTextView()
                                    }
                                    if(row['Name'] == 'SheetId') {
                                        document.getElementById("loadingTxt").innerHTML += 'Sheet Id: ' + row['Value'] + '<br>'
                                        updateInfoTextView()
                                    }
                                    if(row['Name'] == 'Version') {
                                        document.getElementById("loadingTxt").innerHTML += 'Sheet Version: ' + row['Value'] + '<br>'
                                        updateInfoTextView()
                                    }
                                    if(row['Name'] == 'PublishedOn') {
                                        document.getElementById("loadingTxt").innerHTML += 'Sheet Published on: ' + row['Value'] + '<br>'
                                        updateInfoTextView()
                                    }
                                    /* if(row['Name'] == 'AddLanguage') {
                                        //console.log(row['Value'], " anguage value")
                                        languageJSON = [row['Value']]
                                    } */
                                })
                                document.getElementById("loadingTxt").innerHTML += "App Version: " + Number(_version).toFixed(1) + "<br>"
                                updateInfoTextView()


                                // Settings message added
                                document.getElementById("loadingTxt").innerHTML += "Publishing settings data to server.<br>"
                                updateInfoTextView()


                                /* document.getElementById("loadingTxt").innerHTML += settingResponse.toString() + "<br>"
                                updateInfoTextView() */
                                /////////////////////////////////////////////////////////////////////////////////////
                                // Show Setting data to the log
                                //document.getElementById("loadingTxt").innerHTML += "Sheet settings details..<br>"
                                //updateInfoTextView()
                                /* $.each(settingDataList, function (index_setting, row_setting) {
                                   if(row_setting['Name'] != '' && row_setting['Value'] != '') {
                                        document.getElementById("loadingTxt").innerHTML += row_setting['Name'] + ': ' + row_setting['Value'] + "<br>"
                                        updateInfoTextView()
                                   }
                                }) */
                                /////////////////////////////////////////////////////////////////////////////////////////

                            }
                            /* $.each(settingDataList, function (index, row) {
                                if(row['Name'] == 'AddLanguage') {
                                    //console.log(row['Value'], " anguage value")

                                    var languageJSON = row['Value'].split(',')
                                    for (var i=0; i< languageJSON.length; i++) {
                                        //console.log(languageJSON[i].trimStart().trimEnd(), "HERERE")
                                        console.log(languageJSON[i].replace(/\s/g, ''), "HERERE")
                                    }
                                }
                            }) */
                            //getDirectoryDataFromSheet()

                            // Changes here 8/4/25
                            //getStepsDataFromSheet()
                            getInstallDataFromSheet()

                        },
                    })
                    ///////////////////
                    // Clear memory
                    settingRequest.onreadystatechange = null;
                    settingRequest.abort = null;
                    settingRequest = null;
                    ///////////////////
                    // Load Directory Data from server and save it to server settings.json file
                    //getDirectoryDataFromSheet()
                }, 100)
            }
        })
        ///////////////////
        // Clear memory
        updateRequest.onreadystatechange = null;
        updateRequest.abort = null;
        updateRequest = null;
        ///////////////////
    }
}
// Get Install tab data
function getInstallDataFromSheet() {
    if(window.navigator.onLine == true) {
        //console.log("INTERNET ACTIVE")
        //document.getElementById("loadingTxt").innerHTML = "Publishing sheet content..<br>"
        //console.log('sheets/' + setVersion_Id + '/version.json')
        /////////////////////////////////////////////////////////////////////////////////
        var updateRequest = $.ajax({
            url: 'pushSheet.php?version=' + Math.random(), 
            type:'POST', 
            data:{'id' : sheet_Id, 'sheetname' : 'Install'}, 
            cache: false, 
           /*  async: false, */
            success: function (response) {
                //console.log(response, " Setting Data")
                //return;
                let installResponse = response
                document.getElementById("loadingTxt").innerHTML += response.toString() + "<br>"
                updateInfoTextView()
                setTimeout(function() {
                    var settingRequest = $.ajax({
                        url: '../sheets/' + sheet_Id + "/install.json?version=" + Math.random(), 
                        cache: false, 
                        //async: false,
                        type: 'GET',
                        dataType: "text",
                        success: function (response) {
                            //console.log(response, " READ DATA")
                            if(response.length == 0) {
                                document.getElementById("loadingTxt").innerHTML += '<font color="red">Error: Install data not available.' + "</font><br>"
                                updateInfoTextView()
                            } else { 
                                //////////////////////////////////////////////////////////////////////////////
                                installDataList = []
                                var mResponseSettings = response.replace(/�/g, "") 
                                var newSettingsData = eval(mResponseSettings)
                                for(var i=0; i<newSettingsData.length; i++) {
                                    var settingsDataSting = JSON.stringify(newSettingsData[i]);
                                    //console.log(isJSON(pp), " --- ")
                                    //newstr += JSON.stringify(isJSON(pp))
                                    if(isJSONData(settingsDataSting) == false) {
                                        document.getElementById("loadingTxt").innerHTML += '<font color="red">Error: Install Sheet : (Row: ' + i + ")</font><br>"
                                        updateInfoTextView()
                                    } else {
                                        installDataList[i] = isJSONData(settingsDataSting)
                                    }
                                }
                                /////////////////////////////////////////////////////////////////
                                // Settings message added
                                //document.getElementById("loadingTxt").innerHTML += "Publishing install data to server.<br>"
                                updateInfoTextView()
                            }
                            // Changes here 8/4/25
                            getStepsDataFromSheet()
                        },
                    })
                    ///////////////////
                    // Clear memory
                    settingRequest.onreadystatechange = null;
                    settingRequest.abort = null;
                    settingRequest = null;
                    ///////////////////
                    // Load Directory Data from server and save it to server settings.json file
                    //getDirectoryDataFromSheet()
                }, 100)
            }
        })
        ///////////////////
        // Clear memory
        updateRequest.onreadystatechange = null;
        updateRequest.abort = null;
        updateRequest = null;
        ///////////////////
    }
}
//////////////////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 */
let languageLoadIndex = 0
let languageJSON = []
function getStepsDataFromSheet() {
    if(window.navigator.onLine == true) {
        //console.log("INTERNET ACTIVE")
        //document.getElementById("loadingTxt").innerHTML = "Publishing sheet content..<br>"
        //console.log('sheets/' + setVersion_Id + '/version.json')
        /////////////////////////////////////////////////////////////////////////////////
        let languageToLoad = ''
        $.each(settingDataList, function (index, row) {
            if(row['Name'] == 'AddLanguage') {
                //console.log(row['Value'], " anguage value")
                languageJSON = row['Value'].split(',')
                //for (var i=0; i< languageJSON.length; i++) {
                    //console.log(languageJSON[i].trimStart().trimEnd(), "HERERE")
                    languageToLoad = languageJSON[languageLoadIndex].replace(/\s/g, '')
                //}
            }
        })
        //console.log(languageToLoad.toUpperCase(), " TO LOAD SHEET")
        //return;
        /////////////////////////////////////////////////////////////////////////////////
        var updateRequest = $.ajax({
            url: 'pushSheet.php?version=' + Math.random(), 
            type:'POST', 
            data:{'id' : sheet_Id, 'sheetname' : languageToLoad.toUpperCase()}, 
            cache: false, 
           /*  async: false, */
            success: function (response) {
                console.log(response, "Steps Data")
                //return;
                document.getElementById("loadingTxt").innerHTML += response.toString() + "<br>"
                updateInfoTextView()
                //document.getElementById('defaultBGImage').style.display = 'none'
                //return;
                setTimeout(function() {
                    //console.log(languageLoadIndex , '<', (languageJSON.length-1))
                    if(languageLoadIndex < languageJSON.length) {
                        /* languageLoadIndex++;
                        getStepsDataFromSheet() */
                        //console.log("Load all the languages data available in the folder list - ", languageToLoad.toLowerCase())
                        var languageRequest = $.ajax({
                            url: '../sheets/' + sheet_Id + "/steps_" + languageToLoad.toLowerCase() + ".json?version=" + Math.random(), 
                            cache: false, 
                            async: false,
                            type: 'GET',
                            dataType: "text",
                            success: function (response) {
                                //console.log(response, " READ DATA")
                                if(response.length == 0) {
                                    document.getElementById("loadingTxt").innerHTML += '<font color="red">Error: Steps data not available.' + "</font><br>"
                                    //document.getElementById("loadingTxt").innerHTML += '<font color="red">' + "</font><br>"
                                    updateInfoTextView()
                                } else {
                                    //////////////////////////////////////////////////////////////////////////////
                                    languageDataList[languageLoadIndex] = []
                                    var mResponsePrivate = response.replace(/�/g, "") 
                                    var newPrivateData = eval(mResponsePrivate)
                                    for(var i=0; i<newPrivateData.length; i++) {
                                        var privateDataSting = JSON.stringify(newPrivateData[i]);
                                        //console.log(isJSON(pp), " --- ")
                                        //newstr += JSON.stringify(isJSON(pp))
                                        if(isJSONData(privateDataSting) == false) {
                                            document.getElementById("loadingTxt").innerHTML += '<font color="red">Error:' + languageToLoad.toUpperCase() + 'Sheet : (Row: ' + i + ")</font><br>"
                                            updateInfoTextView()
                                        } else {
                                            languageDataList[languageLoadIndex][i] = isJSONData(privateDataSting)
                                        }
                                    }
                                }
                                //console.log(languageDataList[languageLoadIndex], " LANG DATA")
                                if(languageLoadIndex != languageJSON.length-1) {
                                    languageLoadIndex++;
                                    getStepsDataFromSheet()
                                } else {
                                    if(isPreloadImages == 'download_images') {
                                        console.log("Preload Images")
                                        
                                        // Added new line break
                                        document.getElementById("loadingTxt").innerHTML += "<br>"
                                        

                                        // Preload All Images
                                        PreloadAllImagesToServer();
                                    } else {
                                        pushVersionToServer();
                                        setTimeout(function() {
                                            document.getElementById("loadingTxt").innerHTML += "All steps data published.<br>"
                                            updateInfoTextView()
                                            // Call PHP to save data to the json file
                                            //savePublishedStateToServer('true');
                                        }, 3000)
                                        /* document.getElementById("loadingTxt").innerHTML += "All steps data published.<br>"
                                        updateInfoTextView() */
                                    }
                                }
                            },
                        })
                        ///////////////////
                        // Clear memory
                        languageRequest.onreadystatechange = null;
                        languageRequest.abort = null;
                        languageRequest = null;
                        ///////////////////
                    } else {
                        document.getElementById("loadingTxt").innerHTML += "All steps data published.<br>"
                        updateInfoTextView()
                    }
                    // Load Events Data from server and save it to server settings.json file
                    //getEventsDataFromSheet() 
                }, 300)
            }
        })
        ///////////////////
        // Clear memory
        updateRequest.onreadystatechange = null;
        updateRequest.abort = null;
        updateRequest = null;
        ///////////////////
    }
}
//////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 */
isJSONData = str => {
    //if (typeof str === 'string'){
      try {
        let p = JSON.parse(str)
        return p
      } catch(e){
      }
    //}
    return false
}
//////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 * @param {*} txt 
 * @returns 
 */
function validateTimeString(txt) {
    var isValid = /^([0-1]?[0-9]|2[0-4]):([0-5][0-9])(:[0-5][0-9])?$/.test(txt);
    return isValid;
  }
//////////////////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 * @returns 
 */
function getAllImagesToPublish() {
    var tempCount = 0
    $.each(settingDataList, function (index_setting, row_setting) {
        /* if(row_setting['Name'] == 'TextImage' || row_setting['Name'] == 'DefaultMapImage' || row_setting['Name'] == 'BackgroundImage' || row_setting['Name'] == 'SplashImageUrl') {
            if(row_setting['Value'] != '' && row_setting['Value'] != undefined) {
                tempCount++
            }
            if(row_setting['Value ES'] != '' && row_setting['Value ES'] != undefined) {
                tempCount++
            }
        } */
        if(row_setting['Name'] == 'BackgroundImage' || row_setting['Name'] == 'SplashImageUrl' || row_setting['Name'] == 'PrevButtonUrl' || row_setting['Name'] == 'NextButtonUrl' || row_setting['Name'] == 'QuitButtonUrl') {
            if(row_setting['Value'] != '') {
                tempCount++
            }
        }
    })
    //////////////////////////////////////////////////////////////////////////////////////////
    // Unfiltered list
    /* for(var i=0; i<languageDataList.length; i++) {
        for (var j=0; j<languageDataList[i].length; j++) {
            if(languageDataList[i][j].Image != '') {
                //console.log(languageDataList[i][j].Image, " AAS")
                tempCount++
                //tempImages.push(languageDataList[i][j].Image)
            }
        }
    } */
    //////////////////////////////////////////////////////////////////////////////////////////
    // Filtered list
    let filteredImages = tempLangHolder.filter((item, index) => tempLangHolder.indexOf(item) === index);
    //console.log(filteredImages.length, " LEN")
    for (var j=0; j<filteredImages.length; j++) {
        tempCount++
    }

    // Install tab images
    let filteredInstallImages = tempInstallHolder.filter((item, index) => tempInstallHolder.indexOf(item) === index);
    for (var k=0; k<filteredInstallImages.length; k++) {
        tempCount++
    }
    //////////////////////////////////////////////////////////////////////////////////////////
    return tempCount;
}
//////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 * @param {*} _value 
 */
function savePublishedStateToServer(_value) {
    var saveRequest = $.ajax({
        url: 'savePushStatus.php?version=' + Math.random(), 
        type:'POST', 
        data:{'id' : sheet_Id, 'value' : _value}, 
        cache: false, 
       /*  async: false, */
        success: function (response) {
            console.log("RESONSE - ", response)
        }
    })
    ///////////////////
    // Clear memory
    saveRequest.onreadystatechange = null;
    saveRequest.abort = null;
    saveRequest = null;
    ///////////////////
}
//////////////////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 */
function PreloadAllImagesToServer() {
    // Caching Directory Map Images
    if(window.navigator.onLine == true) {
        let settingTimeout = 10
        $.each(settingDataList, function (index_setting, row_setting) {
            if(row_setting['Name'] == 'BackgroundImage') {
                if(row_setting['Value'] != '') {
                    if (row_setting['Value'].includes("https://drive.google.com")) {
                        let imgid = row["Value"].split('https://drive.google.com')[1].split('/')[3];
                        let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                        // Cache Image
                        setTimeout(function() {
                            if(row_setting['Value'] != '') {
                                downloadImagesLocally(imgPath)
                            } else {
                                downloadImagesLocally("")
                            }
                        }, (settingTimeout * index_setting));
                    } else {
                        // Cache Image
                        setTimeout(function() {
                            if(row_setting['Value'] != '') {
                                downloadImagesLocally(row_setting['Value'])
                            } else {
                                downloadImagesLocally("")
                            }
                        }, (settingTimeout * index_setting));
                    }
                }
            }
            if(row_setting['Name'] == 'SplashImageUrl') {
                if(row_setting['Value'] != '') {
                    if (row_setting['Value'].includes("https://drive.google.com")) {
                        let imgid = row["Value"].split('https://drive.google.com')[1].split('/')[3];
                        let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                        // Cache Image
                        setTimeout(function() {
                            if(row_setting['Value'] != '') {
                                downloadImagesLocally(imgPath)
                            } else {
                                downloadImagesLocally("")
                            }
                        }, (settingTimeout * index_setting));
                    } else {
                        // Cache Image
                        setTimeout(function() {
                            if(row_setting['Value'] != '') {
                                downloadImagesLocally(row_setting['Value'])
                            } else {
                                downloadImagesLocally("")
                            }
                        }, (settingTimeout * index_setting));
                    }
                }
            }
            

            ////////////////////////////////////////////////////////////////////////////

            // For Prev Button
            if(row_setting['Name'] == 'PrevButtonUrl') {
                if(row_setting['Value'] != '') {
                    if (row_setting['Value'].includes("https://drive.google.com")) {
                        let imgid = row["Value"].split('https://drive.google.com')[1].split('/')[3];
                        let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                        // Cache Image
                        setTimeout(function() {
                            if(row_setting['Value'] != '') {
                                downloadImagesLocally(imgPath)
                            } else {
                                downloadImagesLocally("")
                            }
                        }, (settingTimeout * index_setting));
                    } else {
                        // Cache Image
                        setTimeout(function() {
                            if(row_setting['Value'] != '') {
                                downloadImagesLocally(row_setting['Value'])
                            } else {
                                downloadImagesLocally("")
                            }
                        }, (settingTimeout * index_setting));
                    }
                }
            }

            // For Next Button
            if(row_setting['Name'] == 'NextButtonUrl') {
                if(row_setting['Value'] != '') {
                    if (row_setting['Value'].includes("https://drive.google.com")) {
                        let imgid = row["Value"].split('https://drive.google.com')[1].split('/')[3];
                        let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                        // Cache Image
                        setTimeout(function() {
                            if(row_setting['Value'] != '') {
                                downloadImagesLocally(imgPath)
                            } else {
                                downloadImagesLocally("")
                            }
                        }, (settingTimeout * index_setting));
                    } else {
                        // Cache Image
                        setTimeout(function() {
                            if(row_setting['Value'] != '') {
                                downloadImagesLocally(row_setting['Value'])
                            } else {
                                downloadImagesLocally("")
                            }
                        }, (settingTimeout * index_setting));
                    }
                }
            }

            // For Next Button
            if(row_setting['Name'] == 'QuitButtonUrl') {
                if(row_setting['Value'] != '') {
                    if (row_setting['Value'].includes("https://drive.google.com")) {
                        let imgid = row["Value"].split('https://drive.google.com')[1].split('/')[3];
                        let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                        // Cache Image
                        setTimeout(function() {
                            if(row_setting['Value'] != '') {
                                downloadImagesLocally(imgPath)
                            } else {
                                downloadImagesLocally("")
                            }
                        }, (settingTimeout * index_setting));
                    } else {
                        // Cache Image
                        setTimeout(function() {
                            if(row_setting['Value'] != '') {
                                downloadImagesLocally(row_setting['Value'])
                            } else {
                                downloadImagesLocally("")
                            }
                        }, (settingTimeout * index_setting));
                    }
                }
            }

            ////////////////////////////////////////////////////////////////////////////

        })
        //////////////////////// For steps data ///////////////////////////
        //console.log(languageDataList)
        let langTimeout = 300
        //let tempLangHolder = []
        tempLangHolder = []
        $.each(languageDataList, function (i, row) {
            $.each(languageDataList[i], function (j, row_data) {
                if (languageDataList[i][j]['Image'].includes("https://drive.google.com")) {
                    let imgid = languageDataList[i][j]['Image'].split('https://drive.google.com')[1].split('/')[3];
                    let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                    // Cache Image
                    setTimeout(function() {
                        if(languageDataList[i][j].Image != '') {
                            //downloadImagesLocally(imgPath)
                            tempLangHolder.push(imagePath)
                            /* console.log("IMG FROM GOOGLE")
                            console.log(tempLangHolder.length, " IMAGWA") */
                        } else {
                            //downloadImagesLocally("") 
                        }
                    }, (0));
                } else {
                    // Cache Image
                    setTimeout(function() {
                        if(languageDataList[i][j].Image != '') {
                            //console.log("IMG FROM DB")
                           // downloadImagesLocally(languageDataList[i][j]['Image'])
                           tempLangHolder.push(languageDataList[i][j].Image)
                           //console.log(tempLangHolder.length, " IMAGWA")
                        } else {
                            //downloadImagesLocally("")
                        }
                    }, (0));
                }
                
            })
        })
        setTimeout(function() {
            //console.log(tempLangHolder.length, " IMAGWA")
            ////////////////////////////////////////////////////////////
            // To remove duplicate values
            let filteredImages = tempLangHolder.filter((item, index) => tempLangHolder.indexOf(item) === index);
           /*  console.log(tempLangHolder)
            console.log('After filter')
            console.log(filteredImages) */
            ////////////////////////////////////////////////////////////
            // Undo later on [unfiltered list]
            /* $.each(tempLangHolder, function (i, row) {
                setTimeout(function() {
                    downloadImagesLocally(tempLangHolder[i])
                }, (langTimeout * i));
            }) */
            ////////////////////////////////////////////////////////////
            // Filtered list
            $.each(filteredImages, function (i, row) {
                setTimeout(function() {
                    //downloadImagesLocally(tempLangHolder[i])
                    downloadImagesLocally(filteredImages[i])
                }, (langTimeout * i));
            })
           ////////////////////////////////////////////////////////////
        }, 300)

        // To Save Install Images
        let installTimeout = 500
        tempInstallHolder = []
        $.each(installDataList, function (i, row) {
            if (installDataList[i]['Image'].includes("https://drive.google.com")) {
                let imgid = installDataList[i]['Image'].split('https://drive.google.com')[1].split('/')[3];
                let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                // Cache Image
                setTimeout(function() {
                    if(installDataList[i].Image != '') {
                        tempInstallHolder.push(imgPath)
                    } else {
                        //downloadImagesLocally("") 
                    }
                }, (0));
            } else {
                // Cache Image
                setTimeout(function() {
                    if(installDataList[i].Image != '') {
                        //console.log("IMG FROM DB")
                        // downloadImagesLocally(languageDataList[i][j]['Image'])
                        tempInstallHolder.push(installDataList[i].Image)
                        //console.log(tempLangHolder.length, " IMAGWA")
                    } else {
                        //downloadImagesLocally("")
                    }
                }, (0));
            }
                
        })
        setTimeout(function() {
            //console.log(tempLangHolder.length, " IMAGWA")
            ////////////////////////////////////////////////////////////
            // To remove duplicate values
            let filteredInstallImages = tempInstallHolder.filter((item, index) => tempInstallHolder.indexOf(item) === index);
           /*  console.log(tempLangHolder)
            console.log('After filter')
            console.log(filteredImages) */
            ////////////////////////////////////////////////////////////
            // Undo later on [unfiltered list]
            /* $.each(tempLangHolder, function (i, row) {
                setTimeout(function() {
                    downloadImagesLocally(tempLangHolder[i])
                }, (langTimeout * i));
            }) */
            ////////////////////////////////////////////////////////////
            // Filtered list
            $.each(filteredInstallImages, function (i, row) {
                setTimeout(function() {
                    //downloadImagesLocally(tempLangHolder[i])
                    downloadImagesLocally(filteredInstallImages[i])
                }, (installTimeout * i));
            })
           ////////////////////////////////////////////////////////////
        }, 300)
    } 
}
//////////////////////////////////////////////////////////////////////////////////////////////////
// Getting return message from backgroundAPI worker
//////////////////////////////////////////////////////////////////////////////////////////////////
/**
 * Window onload function
 */
window.addEventListener('load', (event) => {
    //console.log(globalStatus, " check Status ")
    //console.log(isPreloadImages, " check Image download option")
    // Show Push Title
    document.getElementById('pushTitle').innerHTML = isPreloadImages == 'download_images' ? 'Publish All Steps Content' : 'Publish Only Steps Text'

    //return

    if(sheet_Id != '') {
        console.log("Enter into publishing the content")
        UpdateAppVersion()
    } else {
        console.log('show missing sheet id message')
        document.getElementById("loadingTxt").innerHTML += "<font color='red'>ERROR: Sheet Id missing.<br>";
        updateInfoTextView()
        
    }
    //getGamesSettingData();
    return;
})
//////////////////////////////////////////////////////////////////////////////////////////////////
/**
 * To check json in correct format
 * @param {*} str 
 * @returns 
 */
const isJson = (str) => {
    try{
        JSON.parse(str);
    }catch (e){
        //Error
        //JSON is not okay
        return false;
    }
    return true;
}
//////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////
/**
 * Hide preloader
 */
function hideloader() {
    /* $('.loader-spinner').addClass('d-none'); */
    $('.loader-spinner-text').addClass('d-none');
}
//////////////////////////////////////////////////////////////////////////////////////////////////
/**
 * Show preloader
 */
function showloader() {
    /* $('.loader-spinner').removeClass('d-none'); */
    $('.loader-spinner-text').removeClass('d-none');
}
//////////////////////////////////////////////////////////////////////////////////////////////////
/**
 * Check and return the actual conbination of urls
 * @param {*} str 
 * @returns 
 */
function combinations(str) {
    var fn = function(active, rest, a) {
        if (!active && !rest)
            return;
        if (!rest) {
            a.push(active);
        } else {
            fn(active + rest[0], rest.slice(1), a);
            fn(active, rest.slice(1), a);
        }
        return a;
    }
    return fn("", str, []);
}
//////////////////////////////////////////////////////////////////////////////////////////////////
/**
 * Reinit the window offset at top
 * @param {*} element 
 */
function scrollPage(element) {
    $('html, body').animate({
        scrollTop: $(element).offset().top
    });
}
//////////////////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 * @param {*} url 
 * @param {*} callback 
 */
function checkIfImageExists(url, callback) {
    const img = new Image();
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
 * @param {*} urlString 
 */
function downloadImagesLocally(urlString) {
    let dispImgName = ''
    if (urlString.includes("https://drive.google.com")) {
        imgid = urlString.split('https://drive.google.com')[1].split('/')[3];
        dispImgName = imgid + ".png"
    } else {
        let name =  urlString.split('/')
        let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
        dispImgName = imageName
    }

    ///////////////////////////////////////////////////////
    // For LOCAL debugging
    /* console.log(urlString, " --- URL")
    return; */
    ///////////////////////////////////////////////////////

    var saveRequest = $.ajax({
        url: '../saveAs.php?version=' + Math.random(), 
        type:'POST', 
        data:{'imgURL' : urlString, 'id' : sheet_Id}, 
        cache: false, 
       /*  async: false, */
        success: function (response) {
            var tempCount = 0
            $.each(settingDataList, function (index_setting, row_setting) {
                /* if(row_setting['Name'] == 'TextImage' || row_setting['Name'] == 'DefaultMapImage' || row_setting['Name'] == 'BackgroundImage' || row_setting['Name'] == 'SplashImageUrl') {
                    if(row_setting['Value'] != '' && row_setting['Value'] != undefined) {
                        tempCount++
                    }
                    if(row_setting['Value ES'] != '' && row_setting['Value ES'] != undefined) {
                        tempCount++
                    }
                } */
                if(row_setting['Name'] == 'BackgroundImage' || row_setting['Name'] == 'SplashImageUrl' || row_setting['Name'] == 'PrevButtonUrl' || row_setting['Name'] == 'NextButtonUrl' || row_setting['Name'] == 'QuitButtonUrl') {
                    if(row_setting['Value'] != '') {
                        tempCount++
                    }
                }
            })

            //let tempLangList = []
            for(var i=0; i<languageDataList.length; i++) {
                for (var j=0; j<languageDataList[i].length; j++) {
                    if(languageDataList[i][j].Image != '') {
                        //console.log(languageDataList[i][j].Image, " AAS")
                        tempCount++
                    }
                }
            }

            for(var i=0; i<installDataList.length; i++) {
                if(installDataList[i].Image != "") {
                    //console.log(installDataList[i].Image, " AAS")
                    tempCount++
                }
            }
            

            var AllImageCount = tempCount; 
            ///////////////////////////////////////////////
            var lastline = document.getElementById("loadingTxt").innerHTML.split('<br>')
            var prevMessage = ''
            for (var i=0; i<lastline.length; i++) {
                if(i < lastline.length-2) {
                    prevMessage += lastline[i] + "<br>";
                } else {
                    //prevMessage += '';
                }
            }
            var newMessage = "Publishing Images (" + (imageLoadedCount) + "/" + getAllImagesToPublish() + ")...<br>";
            document.getElementById("loadingTxt").innerHTML = prevMessage + newMessage;
            updateInfoTextView()
            ///////////////////////////////////////////////
            if(imageLoadedCount < getAllImagesToPublish()) {
                imageLoadedCount++;
            } else {
                //globalStatus = true
                // Store the status to a cache object
                /* window.ldb.set(sheet_Id.toString() + '_published', 'true') */
                CheckImageStatus();
                pushVersionToServer();
                
                setTimeout(function() {
                    document.getElementById("loadingTxt").innerHTML += "All data published.<br>"
                    updateInfoTextView()
                    // Call PHP to save data to the json file
                    //savePublishedStateToServer('true');
                }, 3000)
            }
        },
        error: function(e) {
            if(dispImgName != '') {
                document.getElementById("loadingTxt").innerHTML += "<font color='red'>ERROR: Missing Image " + dispImgName + ".</font><br>"
                updateInfoTextView()
            }

            if(imageLoadedCount < AllImageCount) {
                imageLoadedCount++;
            } else {
                /* window.ldb.set(sheet_Id.toString() + '_published', 'true') */
                CheckImageStatus();
                pushVersionToServer();
                setTimeout(function() {
                    document.getElementById("loadingTxt").innerHTML += "All data published.<br>"
                    updateInfoTextView()

                    // Call PHP to save data to the json file
                    //savePublishedStateToServer('true');
                }, 3000) 
            }
        }
    })
    ///////////////////
    // Clear memory
    saveRequest.onreadystatechange = null;
    saveRequest.abort = null;
    saveRequest = null;
    ///////////////////
}
////////////////////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 */
function pushVersionToServer() {
    console.log("Version publishing to server...")
    if(window.navigator.onLine == true) {
        let newVersion = 0
        $.each(settingDataList, function (index, row) {
            if(row['Name'] == 'Version') {
                console.log(row['Value'], " VERSION")
                newVersion = row['Value']
            }
        })

        //return;

        //let currentDate = new Date();
        //let date_str = moment(currentDate).format('MM/DD/YYYY-HH:mm:ss').toLocaleString();
        var updateRequest = $.ajax({
            url: 'pushSheet.php?version=' + Math.random(), 
            type:'POST', 
            data:{'id' : sheet_Id, 'sheetname' : 'Server', 'nVersion' : newVersion}, 
            cache: false, 
            // async: false,
            success: function (response) {
                console.log(response)
            }
        })
        ///////////////////
        // Clear memory
        updateRequest.onreadystatechange = null;
        updateRequest.abort = null;
        updateRequest = null;
        ///////////////////
    }
}
////////////////////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 */
function CheckImageStatus() {
    //var dailyEvent = eventsDataList; //filterAllEventsBasedOnDayTime();
    $.each(settingDataList, function (index_setting, row_setting) {
        if(row_setting['Name'] == 'BackgroundImage') {
            if(row_setting['Value'] != '') {
                if (row_setting['Value'].includes("https://drive.google.com")) {
                    let imgid = row["Value"].split('https://drive.google.com')[1].split('/')[3];
                    let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                    // Cache Image
                    //let imagePath = '../img/cacheImages/' + imgid + '.png?version=' + Math.random();
                    let imagePath = '../sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();
                    checkIfImageExists(imagePath, (isExists) => {
                        if(isExists) {
                            //console.log('Caching '  + imgid + '.png')
                            let bgImage = new Image();
                            bgImage.src = imagePath;
                           /*  document.getElementById("loadingTxt").innerHTML += 'Loading image '  + imgid + '.png from server.<br>'
                            updateInfoTextView() */
                        } else {
                            //console.log('Error: '  + imgid + '.png does not exists in server cache.')
                            if(imgid != '') {
                                document.getElementById("loadingTxt").innerHTML += '<font color="red">Error: Missing Image ' + imgid + '.png</font><br>'
                                updateInfoTextView()
                            }
                        }
                    })
                    //document.body.appendChild(bgImage);
                } else {
                    // Cache Image
                    let name = row_setting['Value'].split('/')
                    let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                    
                    // New Changes
                    //let imagePath = '../img/cacheImages/' + imageName + "?version=" + Math.random();
                    let imagePath = '../sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
                    checkIfImageExists(imagePath, (isExists) => {
                        
                        if(isExists) {
                            //console.log('Caching '  + imageName)
                            let bgImage = new Image();
                            bgImage.src = imagePath
                            // document.getElementById("loadingTxt").innerHTML += 'Loading image '  + imageName + ' from server.<br>'
                            // updateInfoTextView()
                        } else {
                            //console.log('Error: '  + imageName + ' does not exists in server cache.')
                            if(imageName != '') {
                                document.getElementById("loadingTxt").innerHTML += '<font color="red">Error: Missing Image '  + imageName + '</font><br>'
                                updateInfoTextView()
                            }
                        }
                    })
                }
            }
            /* if(row_setting['Value ES'] != '') {
                if (row_setting['Value ES'].includes("https://drive.google.com")) {
                    let imgid = row["Value ES"].split('https://drive.google.com')[1].split('/')[3];
                    let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                    // Cache Image
                    let imagePath = '../img/cacheImages/' + imgid + '.png?version=' + Math.random();
                    checkIfImageExists(imagePath, (isExists) => {
                        if(isExists) {
                            //console.log('Caching '  + imgid + '.png')
                            let bgImage = new Image();
                            bgImage.src = imagePath;
                            // document.getElementById("loadingTxt").innerHTML += 'Loading image '  + imgid + '.png from server.<br>'
                            // updateInfoTextView()
                        } else {
                            //console.log('Error: '  + imgid + '.png does not exists in server cache.')
                            if(imgid != '') {
                                document.getElementById("loadingTxt").innerHTML += '<font color="red">Error: Missing Image ' + imgid + '.png</font><br>'
                                updateInfoTextView()
                            }
                        }
                    })

                } else {
                    // Cache Image
                    let name = row_setting['Value ES'].split('/')
                    let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                    
                    // New Changes
                    let imagePath = '../img/cacheImages/' + imageName + "?version=" + Math.random();
                    checkIfImageExists(imagePath, (isExists) => {
                        if(isExists) {
                            //console.log('Caching '  + imageName)
                            let bgImage = new Image();
                            bgImage.src = imagePath
                            document.getElementById("loadingTxt").innerHTML += 'Loading image '  + imageName + ' from server.<br>'
                            updateInfoTextView()
                        } else {
                            //console.log('Error: '  + imageName + ' does not exists in server cache.')
                            if(imageName != '') {
                                document.getElementById("loadingTxt").innerHTML += '<font color="red">Error: Missing Image '  + imageName + '</font><br>'
                                updateInfoTextView()
                            }
                        }
                    })
                }
            } */
        }
        if(row_setting['Name'] == 'DefaultMapImage') {
            /* if(row_setting['Value'] != '') {
                if (row_setting['Value'].includes("https://drive.google.com")) {
                    let imgid = row["Value"].split('https://drive.google.com')[1].split('/')[3];
                    let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                    // Cache Image
                    let imagePath = '../img/cacheImages/' + imgid + '.png?version=' + Math.random();

                    checkIfImageExists(imagePath, (isExists) => {
                        if(isExists) {
                            //console.log('Caching '  + imgid + '.png')
                            let bgImage = new Image();
                            bgImage.src = imagePath;
                           
                        } else {
                            //console.log('Error: '  + imgid + '.png does not exists in server cache.')
                            if(imgid != '') {
                                document.getElementById("loadingTxt").innerHTML += '<font color="red">Error: Missing Image ' + imgid + '.png</font><br>'
                                updateInfoTextView()
                            }
                        }
                    })

                } else {
                    // Cache Image
                    let name = row_setting['Value'].split('/')
                    let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                    
                    // New Changes
                    let imagePath = '../img/cacheImages/' + imageName + "?version=" + Math.random();
                    
                    checkIfImageExists(imagePath, (isExists) => {
                        //console.log(isExists, " isExists map images ", ' ==== ', 'images/map/cacheImages/' + imageName)
                        if(isExists) {
                            let bgImage = new Image();
                            bgImage.src = imagePath;
                            //console.log('Caching '  + imageName)
                           
                        } else {
                            //console.log('Error: '  + imageName + ' does not exists in server cache.')
                            if(imageName != '') {
                                document.getElementById("loadingTxt").innerHTML += '<font color="red">Error: Missing Image '  + imageName + '</font><br>'
                                updateInfoTextView()
                            }
                        }
                    })
                }
            } */

            /* if(row_setting['Value ES'] != '') {
                if (row_setting['Value ES'].includes("https://drive.google.com")) {
                    let imgid = row["Value ES"].split('https://drive.google.com')[1].split('/')[3];
                    let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                    // Cache Image
                    let imagePath = '../img/cacheImages/' + imgid + '.png?version=' + Math.random();
                    
                    checkIfImageExists(imagePath, (isExists) => {
                        if(isExists) {
                            //console.log('Caching '  + imgid + '.png')
                            let bgImage = new Image();
                            bgImage.src = imagePath
                            document.getElementById("loadingTxt").innerHTML += 'Loading image '  + imgid + '.png from server.<br>'
                            updateInfoTextView()
                        } else {
                            //console.log('Error: '  + imgid + '.png does not exists in server cache.')
                            if(imgid != '') {
                                document.getElementById("loadingTxt").innerHTML += '<font color="red">Error: Missing Image ' + imgid + '.png</font><br>'
                                updateInfoTextView()
                            }
                        }
                    })
                } else {
                    // Cache Image
                    let name = row_setting['Value ES'].split('/')
                    let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                    
                    // New Changes
                    let imagePath = '../img/cacheImages/' + imageName + "?version=" + Math.random();
                    

                    //let isExists = checkIfImageExists('images/map/cacheImages/' + imageName)
                    checkIfImageExists(imagePath, (isExists) => {
                        if(isExists) {
                            //console.log('Caching '  + imageName)
                            let bgImage = new Image();
                            bgImage.src = imagePath
                            document.getElementById("loadingTxt").innerHTML += 'Loading image '  + imageName + ' from server.<br>'
                            updateInfoTextView()
                        } else {
                            //console.log('Error: '  + imageName + ' does not exists in server cache.')
                            if(imageName != '') {
                                document.getElementById("loadingTxt").innerHTML += '<font color="red">Error: Missing Image '  + imageName + '</font><br>'
                                updateInfoTextView()
                            }
                        }
                    })

                }
            } */
        }

        if(row_setting['Name'] == 'SplashImageUrl') {
            if(row_setting['Value'] != '') {
                if (row_setting['Value'].includes("https://drive.google.com")) {
                    let imgid = row["Value"].split('https://drive.google.com')[1].split('/')[3];
                    let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                    // Cache Image
                    //let imagePath = '../img/cacheImages/' + imgid + '.png?version=' + Math.random();

                    let imagePath = '../sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();
                  
                    checkIfImageExists(imagePath, (isExists) => {
                        if(isExists) {
                            //console.log('Caching '  + imgid + '.png')
                            let bgImage = new Image();
                            bgImage.src = imagePath
                            /* document.getElementById("loadingTxt").innerHTML += 'Loading image '  + imgid + '.png from server.<br>'
                            updateInfoTextView() */
                        } else {
                            //console.log('Error: '  + imgid + '.png does not exists in server cache.')
                            if(imgid != '') {
                                document.getElementById("loadingTxt").innerHTML += '<font color="red">Error: Missing Image ' + imgid + '.png</font><br>'
                                updateInfoTextView()
                            }
                        }
                    })

                } else {
                    // Cache Image
                    let name = row_setting['Value'].split('/')
                    let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                    
                    // New Changes
                    //let imagePath = '../img/cacheImages/' + imageName + "?version=" + Math.random();

                    let imagePath = '../sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
                   
                    checkIfImageExists(imagePath, (isExists) => {
                        if(isExists) {
                            //console.log('Caching '  + imageName)
                            let bgImage = new Image();
                            bgImage.src = imagePath
                            /* document.getElementById("loadingTxt").innerHTML += 'Loading image '  + imageName + ' from server.<br>'
                            updateInfoTextView() */
                        } else {
                            //console.log('Error: '  + imageName + ' does not exists in server cache.')
                            if(imageName != '') {
                                document.getElementById("loadingTxt").innerHTML += '<font color="red">Error: Missing Image '  + imageName + '</font><br>'
                                updateInfoTextView()
                            }
                        }
                    })

                }
            }

            /* if(row_setting['Value ES'] != '') {
                if (row_setting['Value ES'].includes("https://drive.google.com")) {
                    let imgid = row["Value ES"].split('https://drive.google.com')[1].split('/')[3];
                    let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                    // Cache Image
                    let imagePath = '../img/cacheImages/' + imgid + '.png?version=' + Math.random();
                   
                    checkIfImageExists(imagePath, (isExists) => {
                        if(isExists) {
                            //console.log('Caching '  + imgid + '.png')
                            let bgImage = new Image();
                            bgImage.src = imagePath
                            document.getElementById("loadingTxt").innerHTML += 'Loading image '  + imgid + '.png from server.<br>'
                            updateInfoTextView()
                        } else {
                            //console.log('Error: '  + imgid + '.png does not exists in server cache.')
                            if(imgid != '') {
                                document.getElementById("loadingTxt").innerHTML += '<font color="red">Error: Missing Image ' + imgid + '.png</font><br>'
                                updateInfoTextView()
                            }
                        }
                    })

                } else {
                    // Cache Image
                    let name = row_setting['Value ES'].split('/')
                    let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                    
                    // New Changes
                    let imagePath = '../img/cacheImages/' + imageName + "?version=" + Math.random();
                    
                    checkIfImageExists(imagePath, (isExists) => {
                        if(isExists) {
                            //console.log('Caching '  + imageName)
                            let bgImage = new Image();
                            bgImage.src = imagePath
                            document.getElementById("loadingTxt").innerHTML += 'Loading image '  + imageName + ' from server.<br>'
                            updateInfoTextView()
                        } else {
                            //console.log('Error: '  + imageName + ' does not exists in server cache.')
                            if(imageName != '') {
                                document.getElementById("loadingTxt").innerHTML += '<font color="red">Error: Missing Image '  + imageName + '</font><br>'
                                updateInfoTextView()
                            }
                        }
                    })
                }
            } */
        }

        
        if(row_setting['Name'] == 'TextImage') {
            /* if(row_setting['Value'] != '') {
                if (row_setting['Value'].includes("https://drive.google.com")) {
                    let imgid = row["Value"].split('https://drive.google.com')[1].split('/')[3];
                    let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                    // Cache Image
                    let imagePath = '../img/cacheImages/' + imgid + '.png?version=' + Math.random();
                   
                    checkIfImageExists(imagePath, (isExists) => {
                        if(isExists) {
                            //console.log('Caching '  + imgid + '.png')
                            let bgImage = new Image();
                            bgImage.src = imagePath; 
                            // document.getElementById("loadingTxt").innerHTML += 'Loading image '  + imgid + '.png from server.<br>'
                            // updateInfoTextView()
                        } else {
                            //console.log('Error: '  + imgid + '.png does not exists in server cache.')
                            if(imgid != '') {
                                document.getElementById("loadingTxt").innerHTML += '<font color="red">Error: Missing Image ' + imgid + '.png</font><br>'
                                updateInfoTextView()
                            }
                        }
                    })

                } else {
                    // Cache Image
                    let name = row_setting['Value'].split('/')
                    let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                    
                    // New Changes
                    let imagePath = '../img/cacheImages/' + imageName + "?version=" + Math.random();
                   
                    checkIfImageExists(imagePath, (isExists) => {
                        if(isExists) {
                            //console.log('Caching '  + imageName)
                            let bgImage = new Image();
                            bgImage.src = imagePath
                            // document.getElementById("loadingTxt").innerHTML += 'Loading image '  + imageName + ' from server.<br>'
                            // updateInfoTextView()
                        } else {
                            //console.log('Error: '  + imageName + ' does not exists in server cache.')
                            if(imageName != '') {
                                document.getElementById("loadingTxt").innerHTML += '<font color="red">Error: Missing Image '  + imageName + '</font><br>'
                                updateInfoTextView()
                            }
                        }
                    })
                }
            } */
            /* if(row_setting['Value ES'] != '') {
                if (row_setting['Value ES'].includes("https://drive.google.com")) {
                    let imgid = row["Value ES"].split('https://drive.google.com')[1].split('/')[3];
                    let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                    // Cache Image
                    let imagePath = 'img/cacheImages/' + imgid + '.png?version=' + Math.random();
                  
                    checkIfImageExists(imagePath, (isExists) => {
                        if(isExists) {
                            //console.log('Caching '  + imgid + '.png')
                            let bgImage = new Image();
                            bgImage.src = imagePath
                            document.getElementById("loadingTxt").innerHTML += 'Loading image '  + imgid + '.png from server.<br>'
                            updateInfoTextView()
                        } else {
                            //console.log('Error: '  + imgid + '.png does not exists in server cache.')
                            if(imgid != '') {
                                document.getElementById("loadingTxt").innerHTML += '<font color="red">Error: Missing Image ' + imgid + '.png</font><br>'
                                updateInfoTextView()
                            }
                        }
                    })

                } else {
                    // Cache Image
                    let name = row_setting['Value ES'].split('/')
                    let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                    
                    // New Changes
                    let imagePath = '../img/cacheImages/' + imageName + "?version=" + Math.random();
                   
                    checkIfImageExists(imagePath, (isExists) => {
                        if(isExists) {
                            //console.log('Caching '  + imageName)
                            let bgImage = new Image();
                            bgImage.src = imagePath
                            document.getElementById("loadingTxt").innerHTML += 'Loading image '  + imageName + ' from server.<br>'
                            updateInfoTextView()
                        } else {
                            //console.log('Error: '  + imageName + ' does not exists in server cache.')
                            if(imageName != '') {
                                document.getElementById("loadingTxt").innerHTML += '<font color="red">Error: Missing Image '  + imageName + '</font><br>'
                                updateInfoTextView()
                            }
                        }
                    })

                }
            } */
        }
    })
    //////////////////////// For steps data ///////////////////////////
    //console.log(languageDataList)
    let langTimeout = 300
    let tempLangHolder = []
    $.each(languageDataList, function (i, row) {
        $.each(languageDataList[i], function (j, row_data) {
            if (languageDataList[i][j]['Image'].includes("https://drive.google.com")) {
                let imgid = languageDataList[i][j]['Image'].split('https://drive.google.com')[1].split('/')[3];
                let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                // Cache Image
                //let imagePath = '../img/cacheImages/' + imgid + '.png?version=' + Math.random();
                let imagePath = '../sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();
                setTimeout(function() {
                    if(languageDataList[i][j].Image != '') {
                        tempLangHolder.push(imagePath)
                    } else {
                        //downloadImagesLocally("")
                    }
                }, (0));
            } else {
                // Cache Image
                // Cache Image
                let name = languageDataList[i][j]['Image'].split('/')
                let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                
                // New Changes
                //let imagePath = '../img/cacheImages/' + imageName + "?version=" + Math.random();
                let imagePath = '../sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
                setTimeout(function() {
                    if(languageDataList[i][j].Image != '') {
                        tempLangHolder.push(imagePath)
                    } else {
                        //downloadImagesLocally("")
                    }
                }, (0));
            }
            
        })
    })
    setTimeout(function() {
        //console.log(tempLangHolder.length, " IMAGWA")

        let filteredImages = tempLangHolder.filter((item, index) => tempLangHolder.indexOf(item) === index);

        //$.each(tempLangHolder, function (i, row) {
        $.each(filteredImages, function (i, row) {
            setTimeout(function() {
                //downloadImagesLocally(tempLangHolder[i])
                checkIfImageExists(tempLangHolder[i], (isExists) => {
                    if(isExists) {
                        //console.log('Caching '  + imageName)
                        let bgImage = new Image();
                        bgImage.src = tempLangHolder[i]
                       /*  document.getElementById("loadingTxt").innerHTML += 'Loading image '  + imageName + ' from server.<br>'
                        updateInfoTextView() */
                    } else {
                        //console.log('Error: '  + imageName + ' does not exists in server cache.')
                        if(tempLangHolder[i] != '') {

                            let name = tempLangHolder[i].split('/')
                            let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];

                            /* document.getElementById("loadingTxt").innerHTML += '<font color="red">Error: Missing Image '  + imageName + '</font><br>'
                            updateInfoTextView() */
                        }
                    }
                })
            }, (langTimeout * i));
        })
    }, 300)


    // To Save Install Tab Images


}
//////////////////////////////////////////////////////////////////////////////////////////////////