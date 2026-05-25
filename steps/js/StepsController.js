///////////////////////////////////////////////////////////////////////////////////////////
// All global vars 
// Home Icon Animation vars
let FULL_DASH_ARRAY = 283;
let RESET_DASH_ARRAY = `-57 ${FULL_DASH_ARRAY}`;
let timer;// = document.querySelector("#base-timer-path-remaining");
let timerFinal;// = document.querySelector("#base-timer-path-remaining-end");
//let timeLabel = document.getElementById("base-timer-label");
let TIME_LIMIT = 3; //in seconds
let timePassed = 1;
let timeLeft = TIME_LIMIT;
let timerInterval = null;

let modeType = 0;
let machineFPS = 0
let inSteps = true;
///////////////////////////////////////////////////////////////////////////////////////////
let jasonPath = '../'
///////////////////////////////////////////////////////////////////////////////////////////
/**
 * // Getting current App version (version.js)
 */
function getCurrentVersion() {
        // Loading version.js dynamically for [mac fix]
        var newScript = document.createElement('script');
        newScript.id = 'version_Script';
        newScript.type = 'text/javascript';
        newScript.src = '../js/main/version.js?version=' + UIVersion;
        document.getElementsByTagName('head')[0].appendChild(newScript);
}
///////////////////////////////////////////////////////////////////////////////////////////
/**
 * // Getting current App module working (main.js)
 */
function getCurrentGameMainVersion() {
        // Loading version.js dynamically for [mac fix]
        var floristryScript = document.createElement('script');
        floristryScript.type = 'text/javascript';
        floristryScript.id = 'floristry_Script';
        floristryScript.src = '../js/steps/stepsMain.js?version=' + UIVersion;
        floristryScript.onload = checkLoadStat()
        document.getElementsByTagName('head')[0].appendChild(floristryScript);
}
///////////////////////////////////////////////////////////////////////////////////////////
/**
 * Getting getZapsheetFunctions (zapsheetFunctions.js)
 */
function getZapsheetsCore() {
    var funtionScript = document.createElement('script');
    funtionScript.type = 'text/javascript';
    funtionScript.id = 'function_Script';
    funtionScript.src = '../js/core/zapsheetsCore.js?version=' + UIVersion;
    funtionScript.onload = checkLoadStatCore()
    document.getElementsByTagName('head')[0].appendChild(funtionScript);
}
///////////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 */
function checkLoadStat() {
}
///////////////////////////////////////////////////////////////////////////////////////////
/**
 * checkLoadStatFunctions
 */
function checkLoadStatCore() {
}
///////////////////////////////////////////////////////////////////////////////////////////
//window.addEventListener('load', (event) => {
let currentRunningVersion = 0;
//////////////////////////////////////////////////////////////////////////////////////////
// Core functions
getZapsheetsCore();
checkVersion();
///////////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 */
function checkVersion() {
    getCurrentVersion();
    let versionTimer = setTimeout(function() {
        clearTimeout(versionTimer)
        if(typeof _version != 'undefined') {
            currentRunningVersion = _version;
            periodicVersion = _version;
            // Functions call : When app loads for first time
            getCurrentGameMainVersion();
        } else {
            // Loop the function untill we have active internet to fetch the data  
            checkVersion();
        }
    }, 2000)
}
//////////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 */
function checkAppVersionStatus() {
    let versionPeriodicTimer = setTimeout(function() {
        clearTimeout(versionPeriodicTimer)
        if(window.navigator.onLine == true) {
            // get new app version
            getCurrentVersion();
            if(_version != currentRunningVersion) {
                currentRunningVersion = _version
            }
        }
        checkAppVersionStatus();
    }, RefreshAppVersionTime * 1000)
}
//////////////////////////////////////////////////////////////////////////////////////////