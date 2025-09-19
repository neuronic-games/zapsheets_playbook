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
getCurrentVersion()
///////////////////////////////////////////////////////////////////////////////////////////
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
/* if(detectDeviceType() == 'Desktop') {
//////////////////////////////////////////////////////////////////////////////////////
buttonElem = `<p id='btnRefresh' style="position: absolute; right: 1em; top: .8em; font-weight: 700; border: 0; font-size: 1.2em; border-radius: 2px; padding:0.5em; color:#A7C1F6; cursor:pointer; z-index:99999; font-family: Font-default;">REFRESH</p>
    <p style="position: absolute; left: 0em; top: 1.2em; font-weight: 700; border: 0; font-size: 1.2em; border-radius: 2px; padding:0.5em">
    <img id='closeBox' src='images/close.png' alt="" style="filter: saturate(500%) contrast(800%) brightness(500%) 
    invert(100%) sepia(0%) hue-rotate(0deg); position: relative; width: 30px; top: -14px; left: 10px; cursor:pointer;" />
    </p>
    `
} else {
////////////////////////////////////////////////////////////////////////////////////////
buttonElem = `<p id='btnRefresh' style="position: absolute; right: 0.5em; top: 0.8em; font-weight: 700; border: 0; font-size: 1.4em; border-radius: 2px; padding:0.5em; color:#A7C1F6; cursor:pointer; z-index:99999; font-family: Font-default;">REFRESH</p>
    <p style="position: absolute; left: 0.5em; top: 0.9em; font-weight: 700; border: 0; font-size: 1.2em; border-radius: 2px; padding:0.5em">
    <img id='closeBox' src='images/close.png' alt="" style="filter: saturate(500%) contrast(800%) brightness(500%) 
    invert(100%) sepia(0%) hue-rotate(0deg); position: relative; width: 30px; cursor:pointer;" />
    </p>`
} */
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
//////////////////////////////////////////////////////////////////////////////////////////
// Image Holder
let tempLangHolder = []
let tempInstallHolder = []
// Language Holder
let languageLoadIndex = 0
let languageJSON = []
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
 * update message function
 */
function updateInfoTextView() {
    //document.getElementById("loadingTxt").scrollTop = 150;
    document.getElementById("loadingTxt").scrollTop += 100;
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
function UpdateAppVersion() {
    if(isSpecificSheet == '') {
        document.getElementById("loadingTxt").innerHTML += '<font color="red">Error: Sheet not defined.' + "</font><br>"
        updateInfoTextView()
    } else {
        checkIfSheetExists(isSpecificSheet.toLowerCase())
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
/////////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 */
/* function getSettingsDataFromSheet() {
    if(window.navigator.onLine == true) {
        var updateRequest = $.ajax({
            url: 'pushSheet.php?version=' + Math.random(), 
            type:'POST', 
            data:{'id' : sheet_Id, 'sheetname' : 'Settings'}, 
            cache: false, 
            success: function (response) {
                let settingResponse = response
                document.getElementById("loadingTxt").innerHTML += response.toString() + "<br>"
                updateInfoTextView()
                setTimeout(function() {
                    // In case either sheet not defined or not given the access to Service Account
                    checkIfUrlExists('../sheets/' + sheet_Id + "/settings.json?version=" + Math.random(), (exists) => {
                        if(!exists) {
                            document.getElementById("loadingTxt").innerHTML = "Publishing Sheet content..</br>"
                            updateInfoTextView()
                            document.getElementById("loadingTxt").innerHTML += '<font color="red">Error: Give Editor access to editor@zsheets-378406.iam.gserviceaccount.com' + "</font><br>"
                            updateInfoTextView()
                            return;
                        }
                    });
                    // Load and store setting data to list
                    var settingRequest = $.ajax({
                        url: '../sheets/' + sheet_Id + "/settings.json?version=" + Math.random(), 
                        cache: false, 
                        type: 'GET',
                        dataType: "text",
                        success: function (response) {
                            //console.log(response, " READ DATA")
                            if(response.length == 0) {
                                document.getElementById("loadingTxt").innerHTML += '<font color="red">Error: Settings data not available.' + "</font><br>"
                                updateInfoTextView()
                            } else { 
                                settingDataList = []
                                var mResponseSettings = response.replace(/�/g, "") 
                                var newSettingsData = eval(mResponseSettings)
                                for(var i=0; i<newSettingsData.length; i++) {
                                    var settingsDataSting = JSON.stringify(newSettingsData[i]);
                                    if(isJSONData(settingsDataSting) == false) {
                                        document.getElementById("loadingTxt").innerHTML += '<font color="red">Error: Settings Sheet : (Row: ' + i + ")</font><br>"
                                        updateInfoTextView()
                                    } else {
                                        settingDataList[i] = isJSONData(settingsDataSting)
                                    }
                                }
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
                                })
                                document.getElementById("loadingTxt").innerHTML += "App Version: " + Number(_version).toFixed(1) + "<br>"
                                updateInfoTextView()

                                // Settings message added
                                document.getElementById("loadingTxt").innerHTML += "Publising settings data to server.<br>"
                                updateInfoTextView()
                            }
                            if(isSpecificSheet.toLowerCase() == 'all') {
                                getInstallDataFromSheet()
                            } else {
                                checkIfSheetExists(isSpecificSheet.toLowerCase())
                            }
                        },
                    })
                    // Clear memory
                    settingRequest.onreadystatechange = null;
                    settingRequest.abort = null;
                    settingRequest = null;
                }, 100)
            }
        })
        // Clear memory
        updateRequest.onreadystatechange = null;
        updateRequest.abort = null;
        updateRequest = null;
    }
} */
////////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 */
// Check Sheet passsed
function checkIfSheetExists(_sheetName) {
    if(window.navigator.onLine == true) {
        var updateRequest = $.ajax({
            url: 'pushSheet.php?version=' + Math.random(), 
            type:'POST', 
            data:{'id' : sheet_Id, 'sheetname' : 'checkSheet', 'tab_name' : _sheetName}, 
            cache: false, 
            success: function (response) {
                //console.log(response, " >Sheet exists")s
                var mResponseSheet = response.replace(/�/g, "") 
                var newSheetData = JSON.parse(mResponseSheet)
                if(newSheetData.exists == "no") {
                    console.log(newSheetData.exists)
                    document.getElementById("loadingTxt").innerHTML += '<font color="red">Error: Sheet ' + _sheetName + ' does not exists.' + "</font><br>"
                    updateInfoTextView()
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
    //console.log(_sheetName, " in update")
    document.getElementById('defaultBGImage').style.display = 'none'
    //document.getElementById("loadingTxt").innerHTML = "Publishing Sheet content..<br>"
    var setVersion_Num = ''
    let currentDate = new Date();
    date_str = moment(currentDate).format('MM/DD/YYYY-HH:mm:ss').toLocaleString();
    const updateAppTimer = setTimeout(function() {
        clearTimeout(updateAppTimer)
        if(window.navigator.onLine == true) {
            var updateRequest = $.ajax({
                url: 'pushSheetUpdate.php?version=' + Math.random(), 
                type:'POST', 
                data:{'id' : sheet_Id, 'sheetname' : "Settings", 'date_string' : date_str}, 
                cache: false, 
                success: function (response) {
                    //console.log(response, " VERSION UPDATED")
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
            document.getElementById("loadingTxt").innerHTML = "Waiting for active internet...<br>Retrying..." 
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
            document.getElementById("loadingTxt").innerHTML = "Publishing Sheet content..</br>"
            updateInfoTextView()
            document.getElementById("loadingTxt").innerHTML += '<font color="red">Error: Give Editor access to editor@zsheets-378406.iam.gserviceaccount.com' + "</font><br>"
            updateInfoTextView()
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
                document.getElementById("loadingTxt").innerHTML += '<font color="red">Error: ' + _sheetName + ' data not available.' + "</font><br>"
                updateInfoTextView()
            } else { 
                settingDataList = []
                var mResponseSettings = response.replace(/�/g, "") 
                var newSettingsData = eval(mResponseSettings)
                for(var i=0; i<newSettingsData.length; i++) {
                    var settingsDataSting = JSON.stringify(newSettingsData[i]);
                    if(isJSONData(settingsDataSting) == false) {
                        document.getElementById("loadingTxt").innerHTML += '<font color="red">Error: ' + _sheetName + ' Sheet : (Row: ' + i + ")</font><br>"
                        updateInfoTextView()
                    } else {
                        settingDataList[i] = isJSONData(settingsDataSting)
                    }
                }
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
                })
                document.getElementById("loadingTxt").innerHTML += "App Version: " + Number(_version).toFixed(1) + "<br>"
                updateInfoTextView()

                // Settings message added
                document.getElementById("loadingTxt").innerHTML += "Publising " + _sheetName + " data to server.<br>"
                updateInfoTextView()

                // Check for loading image
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
                    }, 3000)
                }
            }
        },
        error: function(e) {
            console.log("No " + _sheetName + " sheet found")
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
                document.getElementById("loadingTxt").innerHTML += '<font color="red">Error: ' + _sheetName + ' data not available.' + "</font><br>"
                updateInfoTextView()
            } else { 
                installDataList = []
                var mResponseSettings = response.replace(/�/g, "") 
                var newSettingsData = eval(mResponseSettings)
                for(var i=0; i<newSettingsData.length; i++) {
                    var settingsDataSting = JSON.stringify(newSettingsData[i]);
                    if(isJSONData(settingsDataSting) == false) {
                        document.getElementById("loadingTxt").innerHTML += '<font color="red">Error: ' + _sheetName + ' Sheet : (Row: ' + i + ")</font><br>"
                        updateInfoTextView()
                    } else {
                        installDataList[i] = isJSONData(settingsDataSting)
                    }
                }

                document.getElementById("loadingTxt").innerHTML += "App Version: " + Number(_version).toFixed(1) + "<br>"
                updateInfoTextView()
                document.getElementById("loadingTxt").innerHTML += 'Sheet Id: ' + sheet_Id + '<br>'
                updateInfoTextView()
                /* document.getElementById("loadingTxt").innerHTML += 'Sheet Version: ' + sheetVersion + '<br>'
                updateInfoTextView() */
                document.getElementById("loadingTxt").innerHTML += 'Sheet Published on: ' + pub_date + '<br>'
                updateInfoTextView()
                // Settings message added
                document.getElementById("loadingTxt").innerHTML += "Publising " + _sheetName + " data to server.<br>"
                updateInfoTextView()

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
                    }, 3000)
                }
            }
        },
        error: function(e) {
            console.log("No " + _sheetName + " sheet found")
        }
    })
    // Clear memory
    settingRequest.onreadystatechange = null;
    settingRequest.abort = null;
    settingRequest = null;
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
        //url: '../sheets/' + sheet_Id + "/steps_" + languageToLoad.toLowerCase() + ".json?version=" + Math.random(),
        url: '../sheets/' + sheet_Id + "/" + languageToLoad.toLowerCase() + ".json?version=" + Math.random(), 
        cache: false, 
        async: false,
        type: 'GET',
        dataType: "text",
        success: function (response) {
            if(response.length == 0) {
                document.getElementById("loadingTxt").innerHTML += '<font color="red">Error: ' + _sheetName + ' data not available.' + "</font><br>"
                updateInfoTextView()
            } else {
                languageDataList[languageLoadIndex] = []
                var mResponsePrivate = response.replace(/�/g, "") 
                var newPrivateData = eval(mResponsePrivate)
                for(var i=0; i<newPrivateData.length; i++) {
                    var privateDataSting = JSON.stringify(newPrivateData[i]);
                    if(isJSONData(privateDataSting) == false) {
                        document.getElementById("loadingTxt").innerHTML += '<font color="red">Error:' + languageToLoad.toUpperCase() + 'Sheet : (Row: ' + i + ")</font><br>"
                        updateInfoTextView()
                    } else {
                        languageDataList[languageLoadIndex][i] = isJSONData(privateDataSting)
                    }
                }
            }
            document.getElementById("loadingTxt").innerHTML += "App Version: " + Number(_version).toFixed(1) + "<br>"
            updateInfoTextView()
            document.getElementById("loadingTxt").innerHTML += 'Sheet Id: ' + sheet_Id + '<br>'
            updateInfoTextView()
            
            /* document.getElementById("loadingTxt").innerHTML += 'Sheet Version: ' + sheetVersion + '<br>'
            updateInfoTextView() */

            document.getElementById("loadingTxt").innerHTML += 'Sheet Published on: ' + pub_date + '<br>'
            updateInfoTextView()

            // Settings message added
            document.getElementById("loadingTxt").innerHTML += "Publising " + _sheetName + " data to server.<br>"
            updateInfoTextView()
            
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
                }, 3000)
            }
        },
        error: function(e) {
            console.log("No " + _sheetName + " sheet found")
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
 * @param {*} _sheetName 
 * @param {*} sheetVersion 
 * @param {*} pub_date 
 */
function getSheetData(_sheetName, sheetVersion, pub_date) {
    //console.log("in get sheet data")
    //console.log(sheetVersion, " sv")

    //console.log(_sheetName, " AAAA")

    if(window.navigator.onLine == true) {
        var updateRequest = $.ajax({
            url: 'pushSheetUpdate.php?version=' + Math.random(), 
            type:'POST', 
            data:{'id' : sheet_Id, 'sheetname' : _sheetName, 'date_string' : ''}, 
            cache: false, 
            success: function (response) {
                //console.log(response, " >> RESP")
                //let settingResponse = response
                //document.getElementById("loadingTxt").innerHTML += response.toString() + "<br>"
                //updateInfoTextView()
                //return
                if(_sheetName.toLowerCase() == "settings") {
                    setTimeout(function() {
                        // In case either sheet not defined or not given the access to Service Account
                        getSheetSettings(_sheetName, sheetVersion, pub_date);
                    }, 100)
                } else if(_sheetName.toLowerCase() == "install") {
                    //console.log("Install data")
                    setTimeout(function() {
                        getSheetInstall(_sheetName, sheetVersion, pub_date);
                    }, 100)
                } else {
                    let languageToLoad = ''
                    languageJSON[0] = isSpecificSheet
                    languageToLoad = isSpecificSheet; 
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
////////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 */
// Get Install tab data
/* function getInstallDataFromSheet() {
    if(window.navigator.onLine == true) {
        var updateRequest = $.ajax({
            url: 'pushSheet.php?version=' + Math.random(), 
            type:'POST', 
            data:{'id' : sheet_Id, 'sheetname' : 'Install'}, 
            cache: false, 
            success: function (response) {
                //console.log(response, " Install Data")
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
                                updateInfoTextView()
                            }
                            if(isSpecificSheet == 'all' || (isSpecificSheet != 'install' && isSpecificSheet != '')) {
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
                                }
                            }
                        },
                        error: function(e) {
                            console.log("No Install sheet found")
                        }
                    })
                    // Clear memory
                    settingRequest.onreadystatechange = null;
                    settingRequest.abort = null;
                    settingRequest = null;
                }, 100)
            },
            
        })
        // Clear memory
        updateRequest.onreadystatechange = null;
        updateRequest.abort = null;
        updateRequest = null;
    }
} */
////////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 */
/* let languageLoadIndex = 0
let languageJSON = []
function getStepsDataFromSheet() {
    if(window.navigator.onLine == true) {
        let languageToLoad = ''
        if(isSpecificSheet == 'all') {
            $.each(settingDataList, function (index, row) {
                if(row['Name'] == 'AddLanguage') {
                    //console.log(row['Value'], " anguage value")
                    languageJSON = row['Value'].split(',')
                    languageToLoad = languageJSON[languageLoadIndex].replace(/\s/g, '')
                }
            })
        } else {
            languageJSON[0] = isSpecificSheet
            languageToLoad = isSpecificSheet; 
        }
        var updateRequest = $.ajax({
            url: 'pushSheet.php?version=' + Math.random(), 
            type:'POST', 
            data:{'id' : sheet_Id, 'sheetname' : languageToLoad.toUpperCase()}, 
            cache: false, 
            success: function (response) {
                console.log(response, "Steps Data")
                document.getElementById("loadingTxt").innerHTML += response.toString() + "<br>"
                updateInfoTextView()
                setTimeout(function() {
                    if(languageLoadIndex < languageJSON.length) {
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
                                    updateInfoTextView()
                                } else {
                                    languageDataList[languageLoadIndex] = []
                                    var mResponsePrivate = response.replace(/�/g, "") 
                                    var newPrivateData = eval(mResponsePrivate)
                                    for(var i=0; i<newPrivateData.length; i++) {
                                        var privateDataSting = JSON.stringify(newPrivateData[i]);
                                        if(isJSONData(privateDataSting) == false) {
                                            document.getElementById("loadingTxt").innerHTML += '<font color="red">Error:' + languageToLoad.toUpperCase() + 'Sheet : (Row: ' + i + ")</font><br>"
                                            updateInfoTextView()
                                        } else {
                                            languageDataList[languageLoadIndex][i] = isJSONData(privateDataSting)
                                        }
                                    }
                                }
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
                                    }
                                }
                            },
                        })
                        // Clear memory
                        languageRequest.onreadystatechange = null;
                        languageRequest.abort = null;
                        languageRequest = null;
                    } else {
                        document.getElementById("loadingTxt").innerHTML += "All steps data published.<br>"
                        updateInfoTextView()
                    }
                }, 300)
            }
        })
        // Clear memory
        updateRequest.onreadystatechange = null;
        updateRequest.abort = null;
        updateRequest = null;
    }
} */
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
    $.each(settingDataList, function (index_setting, row_setting) {
        if(row_setting['Name'] == 'BackgroundImage' || row_setting['Name'] == 'SplashImageUrl' || row_setting['Name'] == 'PrevButtonUrl' || row_setting['Name'] == 'NextButtonUrl' || row_setting['Name'] == 'QuitButtonUrl' || row_setting['Name'] == 'LoadingImageUrl' || row_setting['Name'] == 'DownloadButtonUrl' || row_setting["Name"] == '[DICE]' || row_setting["Name"] == '[BERRY]' || row_setting["Name"] == '[NUT]' || row_setting["Name"] == '[BUG]' || row_setting["Name"] == '[OOPS]') {
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
            console.log("RESONSE - ", response)
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

        /* console.log(settingDataList)
        return; */

        $.each(settingDataList, function (index_setting, row_setting) {
            //console.log(row_setting['Name'], " NAME")

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
            // For DICE Image
            if(row_setting['Name'] == '[DICE]') {
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
            }
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
                                //downloadImagesLocally("") 
                            }
                        }, (0));
                    } else {
                        // Cache Image
                        setTimeout(function() {
                            if(languageDataList[i][j].Image != '') {
                            tempLangHolder.push(languageDataList[i][j].Image)
                            } else {
                                //downloadImagesLocally("")
                            }
                        }, (0));
                    }
                } else {
                    //console.log('rules.en - ', languageDataList[i][j]['Type'])
                    if(languageDataList[i][j]['Type'] == 'image') {
                        //console.log(languageDataList[i][j]['Text'], " AAAA")
                        if (languageDataList[i][j]['Text'].includes("https://drive.google.com")) {
                            let imgid = languageDataList[i][j]['Text'].split('https://drive.google.com')[1].split('/')[3];
                            let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                            // Cache Image
                            setTimeout(function() {
                                if(languageDataList[i][j].Text != '') {
                                    tempLangHolder.push(imgPath)
                                } else {
                                    //downloadImagesLocally("") 
                                }
                            }, (0));
                        } else {
                            // Cache Image
                            setTimeout(function() {
                                if(languageDataList[i][j].Text != '') {
                                tempLangHolder.push(languageDataList[i][j].Text)
                                } else {
                                    //downloadImagesLocally("")
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
                        //downloadImagesLocally("") 
                    }
                }, (0));
            } else {
                // Cache Image
                setTimeout(function() {
                    if(installDataList[i].Image != '') {
                        tempInstallHolder.push(installDataList[i].Image)
                    } else {
                        //downloadImagesLocally("")
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
        console.log("Enter into publishing the content")
        UpdateAppVersion()
    } else {
        console.log('show missing sheet id message')
        document.getElementById("loadingTxt").innerHTML += "<font color='red'>ERROR: Sheet Id missing.<br>";
        updateInfoTextView()
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
    /* $('.loader-spinner').removeClass('d-none'); */
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

    //console.log(urlString, " imgPath")

    var saveRequest = $.ajax({
        url: '../saveAs.php?version=' + Math.random(), 
        type:'POST', 
        data:{'imgURL' : urlString, 'id' : sheet_Id}, 
        cache: false, 
       /*  async: false, */
        success: function (response) {
            var tempCount = 0
            $.each(settingDataList, function (index_setting, row_setting) {
                if(row_setting['Name'] == 'BackgroundImage' || row_setting['Name'] == 'SplashImageUrl' || row_setting['Name'] == 'PrevButtonUrl' || row_setting['Name'] == 'NextButtonUrl' || row_setting['Name'] == 'QuitButtonUrl' || row_setting["Name"] == 'LoadingImageUrl' || row_setting["Name"] == 'DownloadButtonUrl' || row_setting["Name"] == '[DICE]' || row_setting["Name"] == '[BERRY]' || row_setting["Name"] == '[NUT]' || row_setting["Name"] == '[BUG]' || row_setting["Name"] == '[OOPS]') {
                    if(row_setting['Value'] != '') {
                        tempCount++
                    }
                }
            })
            for(var i=0; i<languageDataList.length; i++) {
                for (var j=0; j<languageDataList[i].length; j++) {
                    if(languageDataList[i][j].Image != '') {
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
            if(imageLoadedCount < getAllImagesToPublish()) {
                imageLoadedCount++;
            } else {
                CheckImageStatus();
                pushVersionToServer();
                setTimeout(function() {
                    document.getElementById("loadingTxt").innerHTML += "All steps data published.<br>"
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
                CheckImageStatus();
                pushVersionToServer();
                setTimeout(function() {
                    document.getElementById("loadingTxt").innerHTML += "All steps data published.<br>"
                    updateInfoTextView()
                    // Call PHP to save data to the json file
                    //savePublishedStateToServer('true');
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
    console.log("Version publishing to server...")
    if(window.navigator.onLine == true) {
        let newVersion = 0
        $.each(settingDataList, function (index, row) {
            if(row['Name'] == 'Version') {
                console.log(row['Value'], " VERSION")
                newVersion = row['Value']
            }
        })
        var updateRequest = $.ajax({
            url: 'pushSheetUpdate.php?version=' + Math.random(), 
            type:'POST', 
            data:{'id' : sheet_Id, 'sheetname' : 'Server', 'nVersion' : newVersion, 'date_string' : ''}, 
            cache: false, 
            success: function (response) {
                console.log(response)
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
                            //console.log('Caching '  + imgid + '.png')
                            let bgImage = new Image();
                            bgImage.src = imagePath;
                        } else {
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
                    let imagePath = '../sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
                    checkIfImageExists(imagePath, (isExists) => {
                        if(isExists) {
                            //console.log('Caching '  + imageName)
                            let bgImage = new Image();
                            bgImage.src = imagePath
                        } else {
                            if(imageName != '') {
                                document.getElementById("loadingTxt").innerHTML += '<font color="red">Error: Missing Image '  + imageName + '</font><br>'
                                updateInfoTextView()
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
                            //console.log('Caching '  + imgid + '.png')
                            let bgImage = new Image();
                            bgImage.src = imagePath;
                        } else {
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
                    let imagePath = '../sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
                    checkIfImageExists(imagePath, (isExists) => {
                        if(isExists) {
                            //console.log('Caching '  + imageName)
                            let bgImage = new Image();
                            bgImage.src = imagePath
                        } else {
                            if(imageName != '') {
                                document.getElementById("loadingTxt").innerHTML += '<font color="red">Error: Missing Image '  + imageName + '</font><br>'
                                updateInfoTextView()
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
                            //console.log('Caching '  + imgid + '.png')
                            let bgImage = new Image();
                            bgImage.src = imagePath
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
                    let imagePath = '../sheets/' + sheet_Id + '/cacheImages/' + imageName + "?version=" + Math.random();
                    checkIfImageExists(imagePath, (isExists) => {
                        if(isExists) {
                            //console.log('Caching '  + imageName)
                            let bgImage = new Image();
                            bgImage.src = imagePath
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
                            //downloadImagesLocally("")
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
                            //downloadImagesLocally("")
                        }
                    }, (0));
                }
            } else {
                if(languageDataList[i][j]['Type'] == 'image') {
                    //console.log(languageDataList[i][j]['Text'], " AAAA")
                    if (languageDataList[i][j]['Text'].includes("https://drive.google.com")) {
                        let imgid = languageDataList[i][j]['Text'].split('https://drive.google.com')[1].split('/')[3];
                        let imgPath = "https://drive.google.com/thumbnail?id=" + imgid + "&sz=w3500";
                        // Cache Image
                        setTimeout(function() {
                            if(languageDataList[i][j].Text != '') {
                                tempLangHolder.push(imgPath)
                            } else {
                                //downloadImagesLocally("") 
                            }
                        }, (0));
                    } else {
                        // Cache Image
                        setTimeout(function() {
                            if(languageDataList[i][j].Text != '') {
                            tempLangHolder.push(languageDataList[i][j].Text)
                            } else {
                                //downloadImagesLocally("")
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
                        //console.log('Caching '  + imageName)
                        let bgImage = new Image();
                        bgImage.src = tempLangHolder[i]
                    } else {
                        //console.log('Error: '  + imageName + ' does not exists in server cache.')
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