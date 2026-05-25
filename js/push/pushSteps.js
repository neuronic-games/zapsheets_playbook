///////////////////////////////////////////////////////////////////////////////////////////
/**
 * To detect device type
 * @returns 
 */
const detectDeviceType = () =>
    /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)
        ? 'Mobile'
        : 'Desktop';
// Called whenever an update has been found and is installing
// Called whenever an update is done installing and is waiting
var buttonElem = ''
///////////////////////////////////////////////////////////////////////////////////////////
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
let activeLanguage = (getUrlVars()["code"]) ? getUrlVars()["code"].split('/')[0].toLowerCase() : "en"
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
//////////////////////////////////////////////////////////////////////////////////////////
// Image Holder
let tempLangHolder = []
let tempInstallHolder = []
let tempTagsHolder = []
let tempSplashHolder = []
// Language Holder
let languageLoadIndex = 0
let languageJSON = []
let isMoreSheets = []
let sheetIndex = 0;
let bggIndex = 0;
//////////////////////////////////////////////////////////////////////////////////////////
/**
 * Checking open browser stats
 */
if (window.performance) {
    if (performance.navigation.type == 1) {
        loadType = "refresh"
    } else {
        loadType = "normal"
    }
}
/////////////////////////////////////////////////////////////////////////////////////////
/**
 * IDLE TIMEOUT
 */
let idleFrom = ''
let idleTime;
let idleTimeOut = 60 // Idle threshold 3 MINS
let idleStatus = false
// Event to check for
/////////////////////////////////////////////////////////////////////////////////////////
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
////////////////////////////////////////////////////////////////////////////////////////
/**
 * TO LOAD PRIVATE DATA IN THE BEGINNING
 */
let privateDataList = []
let eventsDataList = []
let kioskDataList = []
let languageDataList = []
let bggDataList = []
let tagsDataList = []
let splashDataList = []
let sheet_Name = ''
let splash_img = ''
let splashDelaySec = 0
// For version
let currentVersion = ''
let slideShowLoaded = false;
let backgroundWorker = null
////////////////////////////////////////////////////////////////////////////////////////
/**
 * iOS FIX
 * @returns 
 */
function checkCookieStatus(){
    var cookieEnabled = navigator.cookieEnabled;
    return cookieEnabled;
}
////////////////////////////////////////////////////////////////////////////////////////
/**
 * function tp load Setting from spreadsheet
 */
let settingDataList = []
let installDataList = []
/////////////////////////////////////////////////////////////////////////////////////////
/**
 * variable to store the init urlvars
 */
var sheet_Id = (getUrlVars()["id"]) ? getUrlVars()["id"].split('/')[0] : '';
var isPreloadImages = (document.location.search.substr(1).split('&')[2] != '' && document.location.search.substr(1).split('&')[2] != undefined) ? document.location.search.substr(1).split('&')[2] : 'download_images';
var isSpecificSheet = (getUrlVars()["sheet"]) ? getUrlVars()["sheet"].split('/')[0] : '';
var setVersion_Id = (getUrlVars()["publish_id"]) ? getUrlVars()["publish_id"].split('/')[0] : 'undefined';
var getKiosk_Num = (getUrlVars()["kiosk"]) ? getUrlVars()["kiosk"].split('/')[0] : '';
var game_action = ''
//////////////////////////////////////////////////////////////////////////////////////////
/**
 * To update app version
 */
function UpdateAppVersion() {
    if(isSpecificSheet == '') {
        logLoadMsg('<font color="red">Error: Sheet not defined.' + "</font><br>")
    } else {
        isMoreSheets = isSpecificSheet.replaceAll('%20', '').split(',')
        if(isMoreSheets.length > 1) {
            isSpecificSheet = isMoreSheets[sheetIndex]
            checkIfSheetExists(isSpecificSheet)
        } else {
            checkIfSheetExists(isSpecificSheet)
        }
    }
}
/////////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 * @param {*} url 
 * @param {*} callback 
 */
function checkIfUrlExists(url, callback) {
  const http = new XMLHttpRequest();
  http.open('HEAD', url);
  http.onreadystatechange = () => {
    if (http.readyState === XMLHttpRequest.DONE) {
      callback(http.status !== 404);
    }
  };
  http.send();
}
////////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 * @param {*} _sheetName 
 */
function checkIfSheetExists(_sheetName) {
    if(window.navigator.onLine == true) {
        var updateRequest = $.ajax({
            url: 'pushSheet.php?version=' + Math.random(), 
            type:'POST', 
            data:{'id' : sheet_Id, 'sheetname' : 'checkSheet', 'tab_name' : _sheetName}, 
            cache: false, 
            success: function (response) {
                var mResponseSheet = response.replace(/�/g, "").trim()
                var newSheetData
                try {
                    newSheetData = JSON.parse(mResponseSheet)
                } catch(e) {
                    logLoadMsg('<font color="red">Error: Could not read sheet status for ' + _sheetName + '.</font><br>')
                    return
                }
                if(newSheetData.exists == "no") {
                    logLoadMsg('<font color="red">Error: Sheet ' + _sheetName + ' does not exist.' + "</font><br>")
                } else {
                    // If the sheet exists
                    let returnSheet = newSheetData.sheet;
                    UpdateSheetVersion(returnSheet)
                }
            },
        })
        // Clear memory
        updateRequest.onreadystatechange = null;
        updateRequest.abort = null;
        updateRequest = null;
    }
}
/////////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 * @param {*} _sheetName 
 */
let date_str = ''
function UpdateSheetVersion(_sheetName) {
    document.getElementById('defaultBGImage').style.display = 'none'
    var setVersion_Num = ''
    let currentDate = new Date();
    date_str = moment(currentDate).format('MM/DD/YYYY-HH:mm:ss').toLocaleString();
    const updateAppTimer = setTimeout(function() {
        clearTimeout(updateAppTimer)
        if(window.navigator.onLine == true) {
            var updateRequest = $.ajax({
                url: 'pushSheetUpdate.php?version=' + Math.random(), 
                type:'POST', 
                data:{'id' : sheet_Id, 'sheetname' : "settings", 'date_string' : date_str}, 
                cache: false, 
                success: function (response) {
                    document.getElementById('defaultBGImage').style.display = 'none'
                    setTimeout(function() {
                        getSheetData(_sheetName, response, date_str)
                    }, 100)
                }
            })
            // Clear memory
            updateRequest.onreadystatechange = null;
            updateRequest.abort = null;
            updateRequest = null;
        } else {
            document.getElementById("loadingText").innerHTML = "Waiting for active internet...<br>Retrying..." 
            UpdateAppVersion()
        }
    }, 2000)
}
////////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 * @param {*} _sheetName 
 * @param {*} sheetVersion 
 * @param {*} pub_date 
 */
function getSheetSettings(_sheetName, sheetVersion, pub_date) {
    checkIfUrlExists('../sheets/' + sheet_Id + "/" + _sheetName.toLowerCase() + ".json?version=" + Math.random(), (exists) => {
        if(!exists) {
            document.getElementById("loadingText").innerHTML = "Publishing Sheet content..</br>"
            updateInfoTextView()
            logLoadMsg('<font color="red">Error: Give Editor access to editor@zsheets-378406.iam.gserviceaccount.com' + "</font><br>")
            return;
        }
    });
    // Load and store setting data to list
    var settingRequest = $.ajax({
        url: '../sheets/' + sheet_Id + "/" + _sheetName.toLowerCase() + ".json?version=" + Math.random(), 
        cache: false, 
        type: 'GET',
        dataType: "text",
        success: function (response) {
            if(response.length == 0) {
                logLoadMsg('<font color="red">Error: ' + _sheetName + ' data not available.' + "</font><br>")
            } else { 
                settingDataList = []
                var mResponseSettings = response.replace(/�/g, "") 
                var newSettingsData = eval(mResponseSettings)
                for(var i=0; i<newSettingsData.length; i++) {
                    var settingsDataSting = JSON.stringify(newSettingsData[i]);
                    if(isJSONData(settingsDataSting) == false) {
                        logLoadMsg('<font color="red">Error: ' + _sheetName + ' Sheet : (Row: ' + i + ")</font><br>")
                    } else {
                        settingDataList[i] = isJSONData(settingsDataSting)
                    }
                }
                
                if(languageLoadIndex == 0) {
                    $.each(settingDataList, function (index, row) {
                        if(row['Name'] == 'Title') {
                            document.getElementById("loadingText").innerHTML = 'Sheet Title: ' + row['Value'] + '<br>'
                            updateInfoTextView()
                        }
                        if(row['Name'] == 'SheetId') {
                            logLoadMsg('Sheet Id: ' + row['Value'] + '<br>')
                        }
                        if(row['Name'] == 'Version') {
                            logLoadMsg('Sheet Version: ' + row['Value'] + '<br>')
                        }
                        if(row['Name'] == 'PublishedOn') {
                            logLoadMsg('Sheet Published on: ' + row['Value'] + '<br>')
                        }
                    })
               
                    logLoadMsg("App Version: " + Number(_version).toFixed(1) + "<br>")
                }

                // Settings message added
                logLoadMsg("Publising " + _sheetName + " data to server.<br>")

                if(isMoreSheets.length > 1 && languageLoadIndex < isMoreSheets.length-1) {
                    languageLoadIndex++;
                    if(isMoreSheets[languageLoadIndex].toLowerCase() == "install") {
                        setTimeout(function() {
                            getSheetData(isMoreSheets[languageLoadIndex].toLowerCase(), sheetVersion, pub_date);
                        }, 100)
                    //} else if(isMoreSheets[languageLoadIndex].toLowerCase() == "bgg-en") {
                    } else if(isMoreSheets[languageLoadIndex].toLowerCase() == "bgg-" + activeLanguage.toLowerCase()) {
                        setTimeout(function() {
                            getSheetData(isMoreSheets[languageLoadIndex].toLowerCase(), sheetVersion, pub_date);
                        }, 100)
                    } else if(isMoreSheets[languageLoadIndex].toLowerCase() == "tags") {
                        setTimeout(function() {
                            getSheetData(isMoreSheets[languageLoadIndex].toLowerCase(), sheetVersion, pub_date);
                        }, 100)
                    //} else if(isMoreSheets[languageLoadIndex].toLowerCase() == "splash-en") {
                    } else if(isMoreSheets[languageLoadIndex].toLowerCase() == "splash-" + activeLanguage.toLowerCase()) {
                        setTimeout(function() {
                            getSheetData(isMoreSheets[languageLoadIndex].toLowerCase(), sheetVersion, pub_date);
                        }, 100)
                    } else {
                        setTimeout(function() {
                            getSheetData(isMoreSheets[languageLoadIndex].toLowerCase(), sheetVersion, pub_date);
                        }, 100)
                    }
                } else {
                    //if(_sheetName.toLowerCase() == 'bgg-en') {
                    if(_sheetName.toLowerCase() == 'bgg-' + activeLanguage.toLowerCase()) {
                        loadBGGSheetData(languageDataList[languageLoadIndex])
                    } else {
                        // Check for loading image
                        if(isPreloadImages == 'download_images') {
                            // Added new line break
                            logLoadMsg("<br>")
                            // Preload All Images
                            PreloadAllImagesToServer();
                        } else {
                            pushVersionToServer();
                            saveIndexFile();
                            setTimeout(function() {
                                logLoadMsg("All sheet data published.<br>")
                            }, 3000)
                        }
                    }
                }
            }
        },
        error: function(e) {
        }
    })
    // Clear memory
    settingRequest.onreadystatechange = null;
    settingRequest.abort = null;
    settingRequest = null;
}
/////////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 * @param {*} _sheetName 
 * @param {*} sheetVersion 
 * @param {*} pub_date 
 */
function getSheetInstall(_sheetName, sheetVersion, pub_date) {
    var settingRequest = $.ajax({
        url: '../sheets/' + sheet_Id + "/" + _sheetName.toLowerCase() + ".json?version=" + Math.random(), 
        cache: false, 
        type: 'GET',
        dataType: "text",
        success: function (response) {
            if(response.length == 0) {
                logLoadMsg('<font color="red">Error: ' + _sheetName + ' data not available.' + "</font><br>")
            } else { 
                installDataList = []
                var mResponseSettings = response.replace(/�/g, "") 
                var newSettingsData = eval(mResponseSettings)
                for(var i=0; i<newSettingsData.length; i++) {
                    var settingsDataSting = JSON.stringify(newSettingsData[i]);
                    if(isJSONData(settingsDataSting) == false) {
                        logLoadMsg('<font color="red">Error: ' + _sheetName + ' Sheet : (Row: ' + i + ")</font><br>")
                    } else {
                        installDataList[i] = isJSONData(settingsDataSting)
                    }
                }
                if(languageLoadIndex == 0) {
                    logLoadMsg("App Version: " + Number(_version).toFixed(1) + "<br>")
                    logLoadMsg('Sheet Id: ' + sheet_Id + '<br>')
                    logLoadMsg('Sheet Published on: ' + pub_date + '<br>')
                } 
                // Settings message added
                logLoadMsg("Publising " + _sheetName + " data to server.<br>")

                if(isMoreSheets.length > 1 && languageLoadIndex < isMoreSheets.length-1) {
                    languageLoadIndex++;
                    if(isMoreSheets[languageLoadIndex].toLowerCase() == "settings") {
                        setTimeout(function() {
                            getSheetData(isMoreSheets[languageLoadIndex].toLowerCase(), sheetVersion, pub_date);
                        }, 100)
                    //} else if(isMoreSheets[languageLoadIndex].toLowerCase() == "bgg-en") {
                    } else if(isMoreSheets[languageLoadIndex].toLowerCase() == "bgg-" + activeLanguage.toLocaleLowerCase()) {
                        setTimeout(function() {
                            getSheetData(isMoreSheets[languageLoadIndex].toLowerCase(), sheetVersion, pub_date);
                        }, 100)
                    } else if(isMoreSheets[languageLoadIndex].toLowerCase() == "tags") {
                        setTimeout(function() {
                            getSheetData(isMoreSheets[languageLoadIndex].toLowerCase(), sheetVersion, pub_date);
                        }, 100)
                    //} else if(isMoreSheets[languageLoadIndex].toLowerCase() == "splash-en") {
                    } else if(isMoreSheets[languageLoadIndex].toLowerCase() == "splash-" + activeLanguage.toLowerCase()) {
                        setTimeout(function() {
                            getSheetData(isMoreSheets[languageLoadIndex].toLowerCase(), sheetVersion, pub_date);
                        }, 100)
                    } else {
                        setTimeout(function() {
                            getSheetData(isMoreSheets[languageLoadIndex].toLowerCase(), sheetVersion, pub_date);
                        }, 100)
                    }
                } else {
                    //if(_sheetName.toLowerCase() == 'bgg-en') {
                    if(_sheetName.toLowerCase() == 'bgg-' + activeLanguage.toLowerCase()) {
                        loadBGGSheetData(languageDataList[languageLoadIndex])
                    } else {
                        // Check for loading image
                        if(isPreloadImages == 'download_images') {
                            // Added new line break
                            logLoadMsg("<br>")
                            // Preload All Images
                            PreloadAllImagesToServer();
                        } else {
                            pushVersionToServer();
                            saveIndexFile();
                            setTimeout(function() {
                                logLoadMsg("All sheet data published.<br>")
                            }, 3000)
                        }
                    }
                }
                
            }
        },
        error: function(e) {
        }
    })
    // Clear memory
    settingRequest.onreadystatechange = null;
    settingRequest.abort = null;
    settingRequest = null;
}
/////////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 * @param {*} _sheetName 
 * @param {*} sheetVersion 
 * @param {*} pub_date 
 */
function getSheetTags(_sheetName, sheetVersion, pub_date) {
    var settingRequest = $.ajax({
        url: '../sheets/' + sheet_Id + "/" + _sheetName.toLowerCase() + ".json?version=" + Math.random(), 
        cache: false, 
        type: 'GET',
        dataType: "text",
        success: function (response) {
            if(response.length == 0) {
                logLoadMsg('<font color="red">Error: ' + _sheetName + ' data not available.' + "</font><br>")
            } else { 
                tagsDataList = []
                var mResponseSettings = response.replace(/�/g, "") 
                var newSettingsData = eval(mResponseSettings)
                for(var i=0; i<newSettingsData.length; i++) {
                    var settingsDataSting = JSON.stringify(newSettingsData[i]);
                    if(isJSONData(settingsDataSting) == false) {
                        logLoadMsg('<font color="red">Error: ' + _sheetName + ' Sheet : (Row: ' + i + ")</font><br>")
                    } else {
                        tagsDataList[i] = isJSONData(settingsDataSting)
                    }
                }
                if(languageLoadIndex == 0) {
                    logLoadMsg("App Version: " + Number(_version).toFixed(1) + "<br>")
                    logLoadMsg('Sheet Id: ' + sheet_Id + '<br>')
                    logLoadMsg('Sheet Published on: ' + pub_date + '<br>')
                } 
                // Settings message added
                logLoadMsg("Publising " + _sheetName + " data to server.<br>")

                if(isMoreSheets.length > 1 && languageLoadIndex < isMoreSheets.length-1) {
                    languageLoadIndex++;
                    if(isMoreSheets[languageLoadIndex].toLowerCase() == "settings") {
                        setTimeout(function() {
                            getSheetData(isMoreSheets[languageLoadIndex].toLowerCase(), sheetVersion, pub_date);
                        }, 100)
                    //} else if(isMoreSheets[languageLoadIndex].toLowerCase() == "bgg-en") {
                    } else if(isMoreSheets[languageLoadIndex].toLowerCase() == "bgg-" + activeLanguage.toLowerCase()) {
                        setTimeout(function() {
                            getSheetData(isMoreSheets[languageLoadIndex].toLowerCase(), sheetVersion, pub_date);
                        }, 100)
                    } else if(isMoreSheets[languageLoadIndex].toLowerCase() == "install") {
                        setTimeout(function() {
                            getSheetData(isMoreSheets[languageLoadIndex].toLowerCase(), sheetVersion, pub_date);
                        }, 100)
                    //} else if(isMoreSheets[languageLoadIndex].toLowerCase() == "splash-en") {
                    } else if(isMoreSheets[languageLoadIndex].toLowerCase() == "splash-" + activeLanguage.toLowerCase()) {
                        setTimeout(function() {
                            getSheetData(isMoreSheets[languageLoadIndex].toLowerCase(), sheetVersion, pub_date);
                        }, 100)
                    } else {
                        setTimeout(function() {
                            getSheetData(isMoreSheets[languageLoadIndex].toLowerCase(), sheetVersion, pub_date);
                        }, 100)
                    }
                } else {
                    //if(_sheetName.toLowerCase() == 'bgg-en') {
                    if(_sheetName.toLowerCase() == 'bgg-' + activeLanguage.toLowerCase()) {
                        loadBGGSheetData(languageDataList[languageLoadIndex])
                    } else {
                        // Check for loading image
                        if(isPreloadImages == 'download_images') {
                            // Added new line break
                            logLoadMsg("<br>")
                            // Preload All Images
                            PreloadAllImagesToServer();
                        } else {
                            pushVersionToServer();
                            saveIndexFile();
                            setTimeout(function() {
                                logLoadMsg("All sheet data published.<br>")
                            }, 3000)
                        }
                    }
                }
                
            }
        },
        error: function(e) {
        }
    })
    // Clear memory
    settingRequest.onreadystatechange = null;
    settingRequest.abort = null;
    settingRequest = null;
}
/////////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 * @param {*} _sheetName 
 * @param {*} sheetVersion 
 * @param {*} pub_date 
 */
function getSheetSplash(_sheetName, sheetVersion, pub_date) {
    var settingRequest = $.ajax({
        url: '../sheets/' + sheet_Id + "/" + _sheetName.toLowerCase() + ".json?version=" + Math.random(), 
        cache: false, 
        type: 'GET',
        dataType: "text",
        success: function (response) {
            if(response.length == 0) {
                logLoadMsg('<font color="red">Error: ' + _sheetName + ' data not available.' + "</font><br>")
            } else { 
                splashDataList = []
                var mResponseSettings = response.replace(/�/g, "") 
                var newSettingsData = eval(mResponseSettings)
                for(var i=0; i<newSettingsData.length; i++) {
                    var settingsDataSting = JSON.stringify(newSettingsData[i]);
                    if(isJSONData(settingsDataSting) == false) {
                        logLoadMsg('<font color="red">Error: ' + _sheetName + ' Sheet : (Row: ' + i + ")</font><br>")
                    } else {
                        splashDataList[i] = isJSONData(settingsDataSting)
                    }
                }
                if(languageLoadIndex == 0) {
                    logLoadMsg("App Version: " + Number(_version).toFixed(1) + "<br>")
                    logLoadMsg('Sheet Id: ' + sheet_Id + '<br>')
                    logLoadMsg('Sheet Published on: ' + pub_date + '<br>')
                } 
                // Settings message added
                logLoadMsg("Publising " + _sheetName + " data to server.<br>")

                if(isMoreSheets.length > 1 && languageLoadIndex < isMoreSheets.length-1) {
                    languageLoadIndex++;
                    if(isMoreSheets[languageLoadIndex].toLowerCase() == "settings") {
                        setTimeout(function() {
                            getSheetData(isMoreSheets[languageLoadIndex].toLowerCase(), sheetVersion, pub_date);
                        }, 100)
                    //} else if(isMoreSheets[languageLoadIndex].toLowerCase() == "bgg-en") {
                    } else if(isMoreSheets[languageLoadIndex].toLowerCase() == "bgg-" + activeLanguage.toLowerCase()) {
                        setTimeout(function() {
                            getSheetData(isMoreSheets[languageLoadIndex].toLowerCase(), sheetVersion, pub_date);
                        }, 100)
                    } else if(isMoreSheets[languageLoadIndex].toLowerCase() == "install") {
                        setTimeout(function() {
                            getSheetData(isMoreSheets[languageLoadIndex].toLowerCase(), sheetVersion, pub_date);
                        }, 100)
                    } else if(isMoreSheets[languageLoadIndex].toLowerCase() == "tags") {
                        setTimeout(function() {
                            getSheetData(isMoreSheets[languageLoadIndex].toLowerCase(), sheetVersion, pub_date);
                        }, 100)
                    } else {
                        setTimeout(function() {
                            getSheetData(isMoreSheets[languageLoadIndex].toLowerCase(), sheetVersion, pub_date);
                        }, 100)
                    }
                } else {
                    //if(_sheetName.toLowerCase() == 'bgg-en') {
                    if(_sheetName.toLowerCase() == 'bgg-' + activeLanguage.toLowerCase()) {
                        loadBGGSheetData(languageDataList[languageLoadIndex])
                    } else {
                        // Check for loading image
                        if(isPreloadImages == 'download_images') {
                            // Added new line break
                            logLoadMsg("<br>")
                            // Preload All Images
                            PreloadAllImagesToServer();
                        } else {
                            pushVersionToServer();
                            saveIndexFile();
                            setTimeout(function() {
                                logLoadMsg("All sheet data published.<br>")
                            }, 3000)
                        }
                    }
                }
                
            }
        },
        error: function(e) {
        }
    })
    // Clear memory
    settingRequest.onreadystatechange = null;
    settingRequest.abort = null;
    settingRequest = null;
}
///////////////////////////////////////////////////////////////////////////////////////
function getBGGIndex() {
    let bggPosIndex = -1;
    for (i=0; i<isMoreSheets.length; i++) {
        //if(isMoreSheets[i] == 'bgg-en') {
        if(isMoreSheets[i] == 'bgg-' + activeLanguage.toLowerCase()) {
            bggPosIndex = i;
        }
    }
    return bggPosIndex;
}
///////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 * @param {*} languageToLoad 
 * @param {*} sheetVersion 
 * @param {*} pub_date 
 * @param {*} _sheetName 
 */
function getSheetLanguage(languageToLoad, sheetVersion, pub_date, _sheetName) {
    var languageRequest = $.ajax({
        url: '../sheets/' + sheet_Id + "/" + languageToLoad.toLowerCase() + ".json?version=" + Math.random(), 
        cache: false, 
        async: false,
        type: 'GET',
        dataType: "text",
        success: function (response) {
            if(response.length == 0) {
                logLoadMsg('<font color="red">Error: ' + _sheetName + ' data not available.' + "</font><br>")
            } else {
                languageDataList[languageLoadIndex] = []
                var mResponsePrivate = response.replace(/�/g, "") 
                var newPrivateData = eval(mResponsePrivate)
                for(var i=0; i<newPrivateData.length; i++) {
                    var privateDataSting = JSON.stringify(newPrivateData[i]);
                    if(isJSONData(privateDataSting) == false) {
                        logLoadMsg('<font color="red">Error:' + languageToLoad.toUpperCase() + 'Sheet : (Row: ' + i + ")</font><br>")
                    } else {
                        languageDataList[languageLoadIndex][i] = isJSONData(privateDataSting)
                    }
                }

                // Trigger BGG fetch as soon as the bgg sheet is parsed, regardless of position
                if(_sheetName.toLowerCase() == 'bgg-' + activeLanguage.toLowerCase()) {
                    bggIndex = getBGGIndex();
                    loadBGGSheetData(languageDataList[bggIndex]);
                    if(languageLoadIndex == isMoreSheets.length-1) {
                        return; // bgg-en is last — loadBGGSheetData handles finalisation
                    }
                    // bgg-en is not last — fall through to continue processing remaining sheets
                }
            }

            if(languageLoadIndex == 0) {
                logLoadMsg("App Version: " + Number(_version).toFixed(1) + "<br>")
                logLoadMsg('Sheet Id: ' + sheet_Id + '<br>')
                logLoadMsg('Sheet Published on: ' + pub_date + '<br>')
            }

            // Settings message added
            logLoadMsg("Publising " + _sheetName + " data to server.<br>")

            if(isMoreSheets.length > 1 && languageLoadIndex < isMoreSheets.length-1) {
                languageLoadIndex++;
                // Load New Sheet
                if(isMoreSheets[languageLoadIndex].toLowerCase() == "settings") {
                    setTimeout(function() {
                        getSheetData(isMoreSheets[languageLoadIndex].toLowerCase(), sheetVersion, pub_date);
                    }, 100)
                //} else if(isMoreSheets[languageLoadIndex].toLowerCase() == "bgg-en") {
                } else if(isMoreSheets[languageLoadIndex].toLowerCase() == "bgg-" + activeLanguage.toLowerCase()) {
                    setTimeout(function() {
                        getSheetData(isMoreSheets[languageLoadIndex].toLowerCase(), sheetVersion, pub_date);
                    }, 100)
                } else if(isMoreSheets[languageLoadIndex].toLowerCase() == "install") {
                    setTimeout(function() {
                        getSheetData(isMoreSheets[languageLoadIndex].toLowerCase(), sheetVersion, pub_date);
                    }, 100)
                //} else if(isMoreSheets[languageLoadIndex].toLowerCase() == "splash-en") {
                } else if(isMoreSheets[languageLoadIndex].toLowerCase() == "splash-" + activeLanguage.toLowerCase()) {
                    setTimeout(function() {
                        getSheetData(isMoreSheets[languageLoadIndex].toLowerCase(), sheetVersion, pub_date);
                    }, 100)
                } else if(isMoreSheets[languageLoadIndex].toLowerCase() == "tags") {
                    setTimeout(function() {
                        getSheetData(isMoreSheets[languageLoadIndex].toLowerCase(), sheetVersion, pub_date);
                    }, 100)
                } else {
                    setTimeout(function() {
                        getSheetData(isMoreSheets[languageLoadIndex].toLowerCase(), sheetVersion, pub_date);
                    }, 100)
                }
            } else {
                if(isPreloadImages == 'download_images') {
                    // Added new line break
                    logLoadMsg("<br>")
                    // Preload All Images
                    PreloadAllImagesToServer();
                } else {
                    pushVersionToServer();
                    saveIndexFile();
                    setTimeout(function() {
                        logLoadMsg("All sheet data published.<br>")
                    }, 3000)
                }
            }
        },
        error: function(e) {
            logLoadMsg('<font color="red">Error: ' + _sheetName + ' data not available.' + "</font><br>")
        }
    })
    // Clear memory
    languageRequest.onreadystatechange = null;
    languageRequest.abort = null;
    languageRequest = null;
}
///////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 * @param {*} bggJSON 
 */
function loadBGGSheetData(bggJSON) {
    let bggUserId = ''
    let bggGameId = ''
    $.each(bggJSON, function (index_bgg, row_bgg) {
        if(row_bgg['Name'] == 'BggGameId') {
            bggGameId = row_bgg['Value']
        }
        if(row_bgg['Name'] == 'BggUserId') {
            bggUserId = row_bgg['Value']
        }
    })

    const BGG_MAX_ATTEMPTS = 6
    const BGG_RETRY_DELAY  = 5000   // ms between retries when BGG returns 202
    let   bggAttempt       = 0
    let   countdownTimer   = null

    function showCountdown(seconds) {
        clearInterval(countdownTimer)
        let remaining = seconds
        logLoadMsg('Retrying in <span id="bggCountdown">' + remaining + '</span>s...<br>')
        countdownTimer = setInterval(function() {
            remaining--
            let el = document.getElementById("bggCountdown")
            if(el) el.textContent = remaining
            if(remaining <= 0) clearInterval(countdownTimer)
        }, 1000)
    }

    function attemptBGGFetch() {
        bggAttempt++
        logLoadMsg('Fetching BGG data — attempt ' + bggAttempt + ' of ' + BGG_MAX_ATTEMPTS + '...<br>')

        $.ajax({
            url: './getBggData.php?version=' + Math.random(),
            type: 'POST',
            data: { 'Id': sheet_Id, 'bggUsername': bggUserId, 'bggGameId': bggGameId },
            cache: false,
            dataType: 'JSON',
            timeout: 30000,
            success: function(response) {
                clearInterval(countdownTimer)

                if(response.status == 202) {
                    // BGG is still preparing the collection
                    logLoadMsg('<font color="orange">BGG collection not ready yet.</font><br>')
                    if(bggAttempt < BGG_MAX_ATTEMPTS) {
                        showCountdown(BGG_RETRY_DELAY / 1000)
                        setTimeout(attemptBGGFetch, BGG_RETRY_DELAY)
                    } else {
                        logLoadMsg('<font color="red">Error: BGG did not respond after ' + BGG_MAX_ATTEMPTS + ' attempts. Try pushing bgg-' + activeLanguage + ' again later.</font><br>')
                    }
                    return
                }

                if(response.status == 404 || response.error) {
                    logLoadMsg('<font color="red">Error: ' + (response.error || 'BGG data not available.') + '</font><br>')
                    return
                }

                if(!response.boardgame || response.boardgame.length == 0) {
                    logLoadMsg('<font color="red">Error: BGG returned no game data.</font><br>')
                    return
                }

                bggDataList = response
                logLoadMsg('<font color="green">BGG data loaded successfully.</font><br>')

                if(isPreloadImages == 'download_images') {
                    logLoadMsg("<br>")
                    PreloadAllImagesToServer()
                } else {
                    pushVersionToServer()
                    saveIndexFile()
                    setTimeout(function() {
                        logLoadMsg("All sheet data published.<br>")
                    }, 3000)
                }
            },
            error: function(xhr, status) {
                clearInterval(countdownTimer)
                if(status === 'timeout') {
                    logLoadMsg('<font color="red">Error: BGG request timed out.</font><br>')
                } else {
                    logLoadMsg('<font color="red">Error: Could not reach BGG (' + status + ').</font><br>')
                }
            }
        })
    }

    attemptBGGFetch()
}
///////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 * @param {*} _sheetName 
 * @param {*} sheetVersion 
 * @param {*} pub_date 
 */
function getSheetData(_sheetName, sheetVersion, pub_date) {
    if(window.navigator.onLine == true) {
        var updateRequest = $.ajax({
            url: 'pushSheetUpdate.php?version=' + Math.random(), 
            type:'POST', 
            data:{'id' : sheet_Id, 'sheetname' : _sheetName, 'date_string' : ''}, 
            cache: false, 
            success: function (response) {
                if(_sheetName.toLowerCase() == "settings") {
                    setTimeout(function() {
                        // In case either sheet not defined or not given the access to Service Account
                        getSheetSettings(_sheetName, sheetVersion, pub_date);
                    }, 100)
                } else if(_sheetName.toLowerCase() == "install") {
                    setTimeout(function() {
                        getSheetInstall(_sheetName, sheetVersion, pub_date);
                    }, 100)
                } else if(_sheetName.toLowerCase() == "tags") {
                    setTimeout(function() {
                        getSheetTags(_sheetName, sheetVersion, pub_date);
                    }, 100)
                //} else if(_sheetName.toLowerCase() == "splash-en") {
                } else if(_sheetName.toLowerCase() == "splash-"+activeLanguage.toLowerCase()) {
                    setTimeout(function() {
                        getSheetSplash(_sheetName, sheetVersion, pub_date);
                    }, 100)
                } else {
                    let languageToLoad = ''
                    languageJSON = isMoreSheets
                    languageToLoad = isSpecificSheet = isMoreSheets[languageLoadIndex]; //isSpecificSheet;
                    setTimeout(function() {
                       if(languageLoadIndex < languageJSON.length) {
                            getSheetLanguage(languageToLoad, sheetVersion, pub_date, _sheetName);
                        } else {
                        }
                    }, 300)
                }
            }
        })
        // Clear memory
        updateRequest.onreadystatechange = null;
        updateRequest.abort = null;
        updateRequest = null;
    }
}
///////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 */
isJSONData = str => {
    try {
        let p = JSON.parse(str)
        return p
    } catch(e) {
    }
    return false
}
///////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 * @param {*} txt 
 * @returns 
 */
function validateTimeString(txt) {
    var isValid = /^([0-1]?[0-9]|2[0-4]):([0-5][0-9])(:[0-5][0-9])?$/.test(txt);
    return isValid;
  }
////////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 * @returns 
 */
function getAllImagesToPublish() {
    var tempCount = 0
    $.each(bggDataList.boardgame, function (i, row) {
        if(bggDataList.boardgame[i].image != '') {
            tempCount++;
        }
    })
    
    $.each(settingDataList, function (index_setting, row_setting) {
        if(row_setting['Name'] == 'BackgroundImage' || row_setting['Name'] == 'SplashImageUrl' || row_setting['Name'] == 'PrevButtonUrl' || row_setting['Name'] == 'NextButtonUrl' || row_setting['Name'] == 'QuitButtonUrl' || row_setting['Name'] == 'LoadingImageUrl' || row_setting['Name'] == 'DownloadButtonUrl' || row_setting["Name"] == 'AppIconImageUrl') {
            if(row_setting['Value'] != '') {
                tempCount++
            }
        }
    })
    // Filtered list
    let filteredImages = tempLangHolder.filter((item, index) => tempLangHolder.indexOf(item) === index);
    for (var j=0; j<filteredImages.length; j++) {
        tempCount++
    }
    // Install tab images
    let filteredInstallImages = tempInstallHolder.filter((item, index) => tempInstallHolder.indexOf(item) === index);
    for (var k=0; k<filteredInstallImages.length; k++) {
        tempCount++
    }

    // Tags tab images
    let filteredTagsImages = tempTagsHolder.filter((item, index) => tempTagsHolder.indexOf(item) === index);
    for (var k=0; k<filteredTagsImages.length; k++) {
        tempCount++
    }

    // splash tab images
    let filteredSplashContent = tempSplashHolder.filter((item, index) => tempSplashHolder.indexOf(item) === index);
    for (var k=0; k<filteredSplashContent.length; k++) {
        tempCount++
    }

    return tempCount;
}
/////////////////////////////////////////////////////////////////////////////////////////
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
        success: function (response) {
        }
    })
    // Clear memory
    saveRequest.onreadystatechange = null;
    saveRequest.abort = null;
    saveRequest = null;
}
///////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 */
function PreloadAllImagesToServer() {
    // Caching Directory Map Images
    if(window.navigator.onLine == true) {
        let settingTimeout = 10
        $.each(settingDataList, function (index_setting, row_setting) {
            // For Loading Screen Image
            if(row_setting['Name'] == 'LoadingImageUrl') {
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
            // For Background Image
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
            // For Splash Screen
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
            // For Quit Button
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
            // For Download Button
            if(row_setting['Name'] == 'DownloadButtonUrl') {
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
            // For App Icon
            if(row_setting['Name'] == 'AppIconImageUrl') {
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
            // For DICE Image
            /* if(row_setting['Name'] == '[DICE]') {
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
            // For BERRY Image
            if(row_setting['Name'] == '[BERRY]') {
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
            // For NUT Image
            if(row_setting['Name'] == '[NUT]') {
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
            // For BUG Image
            if(row_setting['Name'] == '[BUG]') {
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
            // For BERRY Image
            if(row_setting['Name'] == '[OOPS]') {
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
            } */
        })
        //////////////////////// For steps data ///////////////////////////
        let langTimeout = 300
        tempLangHolder = []
        $.each(languageDataList, function (i, row) {
            $.each(languageDataList[i], function (j, row_data) {
                if(languageDataList[i][j]['Image'] != undefined) {
                    if (languageDataList[i][j]['Image'].includes("https://drive.google.com")) {
                        let imgid = languageDataList[i][j]['Image'].split('https://drive.google.com')[1].split('/')[3];
                        let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                        // Cache Image
                        setTimeout(function() {
                            if(languageDataList[i][j].Image != '') {
                                tempLangHolder.push(imgPath)
                            } else {
                            }
                        }, (0));
                    } else {
                        // Cache Image
                        setTimeout(function() {
                            if(languageDataList[i][j].Image != '') {
                            tempLangHolder.push(languageDataList[i][j].Image)
                            } else {
                            }
                        }, (0));
                    }
                } else {
                    if(languageDataList[i][j]['Type'] == 'image') {
                        if (languageDataList[i][j]['Text'].includes("https://drive.google.com")) {
                            let imgid = languageDataList[i][j]['Text'].split('https://drive.google.com')[1].split('/')[3];
                            let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                            // Cache Image
                            setTimeout(function() {
                                if(languageDataList[i][j].Text != '') {
                                    tempLangHolder.push(imgPath)
                                } else {
                                }
                            }, (0));
                        } else {
                            // Cache Image
                            setTimeout(function() {
                                if(languageDataList[i][j].Text != '') {
                                tempLangHolder.push(languageDataList[i][j].Text)
                                } else {
                                }
                            }, (0));
                        }
                    }
                }
            })
        })
        setTimeout(function() {
            // To remove duplicate values
            let filteredImages = tempLangHolder.filter((item, index) => tempLangHolder.indexOf(item) === index);
            // Filtered list
            $.each(filteredImages, function (i, row) {
                setTimeout(function() {
                    downloadImagesLocally(filteredImages[i])
                }, (langTimeout * i));
            })
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
                    }
                }, (0));
            } else {
                // Cache Image
                setTimeout(function() {
                    if(installDataList[i].Image != '') {
                        tempInstallHolder.push(installDataList[i].Image)
                    } else {
                    }
                }, (0));
            }
        })
        setTimeout(function() {
            // To remove duplicate values
            let filteredInstallImages = tempInstallHolder.filter((item, index) => tempInstallHolder.indexOf(item) === index);
            // Filtered list
            $.each(filteredInstallImages, function (i, row) {
                setTimeout(function() {
                    downloadImagesLocally(filteredInstallImages[i])
                }, (installTimeout * i));
            })
        }, 300)

        // To Preload Images
        let bggGameTimeout = 700
        $.each(bggDataList.boardgame, function (i, row) {
            if(bggDataList.boardgame[i].boardgame != '' /* && bggGamesDataList.boardgame[i] != undefined */) {
                if (bggDataList.boardgame[i].boardgame.image.includes("https://drive.google.com")) {
                    let imgid = bggDataList.boardgame[i].boardgame.image.split('https://drive.google.com')[1].split('/')[3];
                    let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                    // Cache Image
                    setTimeout(function() {
                        if(privateDataList[i] != '') {
                            downloadImagesLocally(imgPath)
                        } else {
                            downloadImagesLocally("") 
                        }
                    }, (bggGameTimeout * i));
                } else {
                    // Cache Image
                    setTimeout(function() {
                        if(privateDataList[i] != '') {
                            downloadImagesLocally(bggDataList.boardgame[i].boardgame.image)
                        } else {
                            downloadImagesLocally("")
                        }
                    }, (bggGameTimeout * i));
                }
            }
        })

        // To Save Tags Images
        let tagsTimeout = 900
        tempTagsHolder = []
        $.each(tagsDataList, function (i, row) {
            if (tagsDataList[i]['Value'].includes("https://drive.google.com")) {
                let imgid = tagsDataList[i]['Image'].split('https://drive.google.com')[1].split('/')[3];
                let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                // Cache Image
                setTimeout(function() {
                    if(tagsDataList[i].Value != '') {
                        tempTagsHolder.push(imgPath)
                    } else {
                    }
                }, (0));
            } else {
                // Cache Image
                setTimeout(function() {
                    if(tagsDataList[i].Value != '') {
                        tempTagsHolder.push(tagsDataList[i].Value)
                    } else {
                    }
                }, (0));
            }
        })
        setTimeout(function() {
            // To remove duplicate values
            let filteredTagsImages = tempTagsHolder.filter((item, index) => tempTagsHolder.indexOf(item) === index);
            // Filtered list
            $.each(filteredTagsImages, function (i, row) {
                setTimeout(function() {
                    downloadImagesLocally(filteredTagsImages[i])
                }, (tagsTimeout * i));
            })
        }, 300)

        // To Save spplash Images
        let splashTimeout = 900
        tempSplashHolder = []
        $.each(splashDataList, function (i, row) {
            if (splashDataList[i]['Content'].includes("https://drive.google.com")) {
                let imgid = splashDataList[i]['Content'].split('https://drive.google.com')[1].split('/')[3];
                let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                // Cache Image
                setTimeout(function() {
                    if(tagsDataList[i].Value != '') {
                        tempSplashHolder.push(imgPath)
                    } else {
                    }
                }, (0));
            } else {
                // Cache Image
                setTimeout(function() {
                    if(splashDataList[i].Content != '') {
                        tempSplashHolder.push(splashDataList[i].Content)
                    } else {
                    }
                }, (0));
            }
        })
        setTimeout(function() {
            // To remove duplicate values
            let filteredSplashImages = tempSplashHolder.filter((item, index) => tempSplashHolder.indexOf(item) === index);
            // Filtered list
            $.each(filteredSplashImages, function (i, row) {
                setTimeout(function() {
                    downloadImagesLocally(filteredSplashImages[i])
                }, (splashTimeout * i));
            })
        }, 300)

    } 
}
////////////////////////////////////////////////////////////////////////////////////////
/**
 * Window onload function
 */
window.addEventListener('load', (event) => {
    // Show Push Title
    document.getElementById('pushTitle').innerHTML = isPreloadImages == 'download_images' ? 'Publish All Playbook Content' : 'Publish Only Playbook Text'
    if(sheet_Id != '') {
        UpdateAppVersion()
    } else {
        logLoadMsg("<font color='red'>ERROR: Sheet Id missing.<br>")
    }
    return;
})
////////////////////////////////////////////////////////////////////////////////////////
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
/////////////////////////////////////////////////////////////////////////////////////////
/**
 * Hide preloader
 */
function hideloader() {
    $('.loader-spinner-text').addClass('d-none');
}
/////////////////////////////////////////////////////////////////////////////////////////
/**
 * Show preloader
 */
function showloader() {
    $('.loader-spinner-text').removeClass('d-none');
}
/////////////////////////////////////////////////////////////////////////////////////////
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
/////////////////////////////////////////////////////////////////////////////////////////
/**
 * Reinit the window offset at top
 * @param {*} element 
 */
function scrollPage(element) {
    $('html, body').animate({
        scrollTop: $(element).offset().top
    });
}
//////////////////////////////////////////////////////////////////////////////////////////
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
/////////////////////////////////////////////////////////////////////////////////////////
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
    var saveRequest = $.ajax({
        url: './saveAs.php?version=' + Math.random(), 
        type:'POST', 
        data:{'imgURL' : urlString, 'id' : sheet_Id}, 
        cache: false, 
        success: function (response) {
            var tempCount = 0

            // Load Board Game Images
            $.each(bggDataList.boardgame, function (i, row) {
                if(bggDataList.boardgame[i].boardgame.image != '') {
                    tempCount++;
                }
            })

            $.each(settingDataList, function (index_setting, row_setting) {
                if(row_setting['Name'] == 'BackgroundImage' || row_setting['Name'] == 'SplashImageUrl' || row_setting['Name'] == 'PrevButtonUrl' || row_setting['Name'] == 'NextButtonUrl' || row_setting['Name'] == 'QuitButtonUrl' || row_setting["Name"] == 'LoadingImageUrl' || row_setting["Name"] == 'DownloadButtonUrl' || row_setting["Name"] == 'AppIconImageUrl') {
                    if(row_setting['Value'] != '') {
                        tempCount++
                    }
                }
            })
            for(var i=0; i<languageDataList.length; i++) {
                if(languageDataList[i] != undefined) {
                    for (var j=0; j<languageDataList[i].length; j++) {
                        if(languageDataList[i][j].Image != '') {
                            tempCount++
                        }
                    }
                }
            }
            for(var i=0; i<installDataList.length; i++) {
                if(installDataList[i].Image != "") {
                    tempCount++
                }
            }
            for(var i=0; i<tagsDataList.length; i++) {
                if(tagsDataList[i].Value != "") {
                    tempCount++
                }
            }
            for(var i=0; i<splashDataList.length; i++) {
                if(splashDataList[i].Content != "") {
                    tempCount++
                }
            }
            var AllImageCount = tempCount; 
            var lastline = document.getElementById("loadingText").innerHTML.split('<br>')
            var prevMessage = ''
            for (var i=0; i<lastline.length; i++) {
                if(i < lastline.length-2) {
                    prevMessage += lastline[i] + "<br>";
                } else {
                }
            }
            var newMessage = "Publishing Images (" + (imageLoadedCount) + "/" + getAllImagesToPublish() + ")...<br>";
            document.getElementById("loadingText").innerHTML = prevMessage + newMessage;
            updateInfoTextView()
            if(imageLoadedCount < getAllImagesToPublish()) {
                imageLoadedCount++;
            } else {
                CheckImageStatus();
                pushVersionToServer();
                saveIndexFile();
                setTimeout(function() {
                    logLoadMsg("All sheet data published.<br>")
                }, 3000)
            }
        },
        error: function(e) {
            if(dispImgName != '') {
                logLoadMsg("<font color='red'>ERROR: Missing Image " + dispImgName + ".</font><br>")
            }
            if(imageLoadedCount < AllImageCount) {
                imageLoadedCount++;
            } else {
                CheckImageStatus();
                pushVersionToServer();
                saveIndexFile();
                setTimeout(function() {
                    logLoadMsg("All sheet data published.<br>")
                }, 3000) 
            }
        }
    })
    // Clear memory
    saveRequest.onreadystatechange = null;
    saveRequest.abort = null;
    saveRequest = null;
}
/////////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 */
function pushVersionToServer() {
    if(window.navigator.onLine == true) {
        let newVersion = 0
        $.each(settingDataList, function (index, row) {
            if(row['Name'] == 'Version') {
                newVersion = row['Value']
            }
        })
        var updateRequest = $.ajax({
            url: 'pushSheetUpdate.php?version=' + Math.random(), 
            type:'POST', 
            data:{'id' : sheet_Id, 'sheetname' : 'Server', 'nVersion' : newVersion, 'date_string' : ''}, 
            cache: false, 
            success: function (response) {
            }
        })
        // Clear memory
        updateRequest.onreadystatechange = null;
        updateRequest.abort = null;
        updateRequest = null;
    }
}
/////////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 */
function CheckImageStatus() {
    $.each(settingDataList, function (index_setting, row_setting) {
        if(row_setting['Name'] == 'LoadingImageUrl') {
            if(row_setting['Value'] != '') {
                if (row_setting['Value'].includes("https://drive.google.com")) {
                    let imgid = row["Value"].split('https://drive.google.com')[1].split('/')[3];
                    let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                    // Cache Image
                    let imagePath = '../sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();
                    checkIfImageExists(imagePath, (isExists) => {
                        if(isExists) {
                            let bgImage = new Image();
                            bgImage.src = imagePath;
                        } else {
                            if(imgid != '') {
                                logLoadMsg('<font color="red">Error: Missing Image ' + imgid + '.png</font><br>')
                            }
                        }
                    })
                } else {
                    // Cache Image
                    let name = row_setting['Value'].split('/')
                    let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                    let imagePath = '../sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
                    checkIfImageExists(imagePath, (isExists) => {
                        if(isExists) {
                            let bgImage = new Image();
                            bgImage.src = imagePath
                        } else {
                            if(imageName != '') {
                                logLoadMsg('<font color="red">Error: Missing Image '  + imageName + '</font><br>')
                            }
                        }
                    })
                }
            }
        }
        if(row_setting['Name'] == 'BackgroundImage') {
            if(row_setting['Value'] != '') {
                if (row_setting['Value'].includes("https://drive.google.com")) {
                    let imgid = row["Value"].split('https://drive.google.com')[1].split('/')[3];
                    let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                    // Cache Image
                    let imagePath = '../sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();
                    checkIfImageExists(imagePath, (isExists) => {
                        if(isExists) {
                            let bgImage = new Image();
                            bgImage.src = imagePath;
                        } else {
                            if(imgid != '') {
                                logLoadMsg('<font color="red">Error: Missing Image ' + imgid + '.png</font><br>')
                            }
                        }
                    })
                } else {
                    // Cache Image
                    let name = row_setting['Value'].split('/')
                    let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                    let imagePath = '../sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
                    checkIfImageExists(imagePath, (isExists) => {
                        if(isExists) {
                            let bgImage = new Image();
                            bgImage.src = imagePath
                        } else {
                            if(imageName != '') {
                                logLoadMsg('<font color="red">Error: Missing Image '  + imageName + '</font><br>')
                            }
                        }
                    })
                }
            }
        }
        if(row_setting['Name'] == 'DefaultMapImage') {
        }
        if(row_setting['Name'] == 'SplashImageUrl') {
            if(row_setting['Value'] != '') {
                if (row_setting['Value'].includes("https://drive.google.com")) {
                    let imgid = row["Value"].split('https://drive.google.com')[1].split('/')[3];
                    let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                    // Cache Image
                    let imagePath = '../sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();
                    checkIfImageExists(imagePath, (isExists) => {
                        if(isExists) {
                            let bgImage = new Image();
                            bgImage.src = imagePath
                        } else {
                            if(imgid != '') {
                                logLoadMsg('<font color="red">Error: Missing Image ' + imgid + '.png</font><br>')
                            }
                        }
                    })
                } else {
                    // Cache Image
                    let name = row_setting['Value'].split('/')
                    let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                    let imagePath = '../sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
                    checkIfImageExists(imagePath, (isExists) => {
                        if(isExists) {
                            let bgImage = new Image();
                            bgImage.src = imagePath
                        } else {
                            if(imageName != '') {
                                logLoadMsg('<font color="red">Error: Missing Image '  + imageName + '</font><br>')
                            }
                        }
                    })
                }
            }
        }
        if(row_setting['Name'] == 'TextImage') {
        }
    })
    //////////////////////// For steps data ///////////////////////////
    let langTimeout = 300
    let tempLangHolder = []
    $.each(languageDataList, function (i, row) {
        $.each(languageDataList[i], function (j, row_data) {
            if(languageDataList[i][j]['Image'] != undefined) {
                if (languageDataList[i][j]['Image'].includes("https://drive.google.com")) {
                    let imgid = languageDataList[i][j]['Image'].split('https://drive.google.com')[1].split('/')[3];
                    let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                    // Cache Image
                    let imagePath = '../sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + Math.random();
                    setTimeout(function() {
                        if(languageDataList[i][j].Image != '') {
                            tempLangHolder.push(imagePath)
                        } else {
                        }
                    }, (0));
                } else {
                    // Cache Image
                    let name = languageDataList[i][j]['Image'].split('/')
                    let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                    let imagePath = '../sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
                    setTimeout(function() {
                        if(languageDataList[i][j].Image != '') {
                            tempLangHolder.push(imagePath)
                        } else {
                        }
                    }, (0));
                }
            } else {
                if(languageDataList[i][j]['Type'] == 'image') {
                    if (languageDataList[i][j]['Text'].includes("https://drive.google.com")) {
                        let imgid = languageDataList[i][j]['Text'].split('https://drive.google.com')[1].split('/')[3];
                        let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                        // Cache Image
                        setTimeout(function() {
                            if(languageDataList[i][j].Text != '') {
                                tempLangHolder.push(imgPath)
                            } else {
                            }
                        }, (0));
                    } else {
                        // Cache Image
                        setTimeout(function() {
                            if(languageDataList[i][j].Text != '') {
                            tempLangHolder.push(languageDataList[i][j].Text)
                            } else {
                            }
                        }, (0));
                    }
                }
            }
        })
    })
    setTimeout(function() {
        let filteredImages = tempLangHolder.filter((item, index) => tempLangHolder.indexOf(item) === index);
        $.each(filteredImages, function (i, row) {
            setTimeout(function() {
                checkIfImageExists(tempLangHolder[i], (isExists) => {
                    if(isExists) {
                        let bgImage = new Image();
                        bgImage.src = tempLangHolder[i]
                    } else {
                        if(tempLangHolder[i] != '') {
                            let name = tempLangHolder[i].split('/')
                            let imageName = name[name.length-1].indexOf('?') ? name[name.length-1].split('?')[0] : name[name.length-1];
                        }
                    }
                })
            }, (langTimeout * i));
        })
    }, 300)
}
/////////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 * @param {*} _value 
 */
function saveIndexFile() {
    var saveRequest = $.ajax({
        url: './saveIndex.php?version=' + Math.random(), 
        type:'POST', 
        data:{'id' : sheet_Id, 'type' : 'index'}, 
        cache: false, 
        success: function (response) {
            saveServiceWorker();
        }
    })
    // Clear memory
    saveRequest.onreadystatechange = null;
    saveRequest.abort = null;
    saveRequest = null;
}
/////////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 * @param {*} _value 
 */
function saveServiceWorker() {
    var saveRequest = $.ajax({
        url: './saveIndex.php?version=' + Math.random(), 
        type:'POST', 
        data:{'id' : sheet_Id, 'type' : 'sw'}, 
        cache: false, 
        success: function (response) {
        }
    })
    // Clear memory
    saveRequest.onreadystatechange = null;
    saveRequest.abort = null;
    saveRequest = null;
}
/////////////////////////////////////////////////////////////////////////////////////////