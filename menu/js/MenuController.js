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
        floristryScript.src = '../js/menu/menuMain.js?version=' + UIVersion;
        floristryScript.onload = checkLoadStat()
        document.getElementsByTagName('head')[0].appendChild(floristryScript);
}
///////////////////////////////////////////////////////////////////////////////////////////
/**
 * getCurrentMenuVersion
 */
function getCurrentMenuVersion() {
        // Loading version.js dynamically for [mac fix]
        var menuScript = document.createElement('script');
        menuScript.type = 'text/javascript';
        menuScript.id = 'floristry_Script';
        menuScript.src = '../js/menu/menu.js?version=' + UIVersion;
        menuScript.onload = checkLoadStatMenu()
        document.getElementsByTagName('head')[0].appendChild(menuScript);
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
    //alert('main menu')
    getCurrentMenuVersion()
}
function checkLoadStatMenu() {
}
///////////////////////////////////////////////////////////////////////////////////////////
/**
 * checkLoadStatFunctions
 */
function checkLoadStatCore() {
}
///////////////////////////////////////////////////////////////////////////////////////////
let currentRunningVersion = 0;
let jasonPath = '../'
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
            getCurrentVersion(UIVersion);
                if(_version != currentRunningVersion) {
                    currentRunningVersion = _version
                }
        }
        checkAppVersionStatus();
    }, RefreshAppVersionTime * 1000)
}
//////////////////////////////////////////////////////////////////////////////////////////