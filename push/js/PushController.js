///////////////////////////////////////////////////////////////////////////////////////////
/**
 * // Getting current App version (version.js)
 */
function getCurrentVersion() {
    if(window.navigator.onLine == true) {
        // Loading version.js dynamically for [mac fix]
        var newScript = document.createElement('script');
        newScript.id = 'version_Script';
        newScript.type = 'text/javascript';
        newScript.src = '../js/main/version.js?version=' + Math.random();
        document.getElementsByTagName('head')[0].appendChild(newScript);
    }
}
///////////////////////////////////////////////////////////////////////////////////////////
/**
 * // Getting current App module working (main.js)
 */
function getCurrentGamePushVersion() {
    if(window.navigator.onLine == true) {
        // Loading version.js dynamically for [mac fix]
        var floristryScript = document.createElement('script');
        floristryScript.type = 'text/javascript';
        floristryScript.id = 'floristry_Script';
        floristryScript.src = '../js/push/pushSteps.js?version=' + Math.random();
        floristryScript.onload = checkLoadStat()
        document.getElementsByTagName('head')[0].appendChild(floristryScript);
    }
}
///////////////////////////////////////////////////////////////////////////////////////////
/**
 * Getting getZapsheetFunctions (zapsheetFunctions.js)
 */
function getZapsheetsCore() {
    var funtionScript = document.createElement('script');
    funtionScript.type = 'text/javascript';
    funtionScript.id = 'function_Script';
    funtionScript.src = '../js/core/zapsheetsCore.js?version=' + Math.random();
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
// Core functions
getZapsheetsCore();
getCurrentVersion();
getCurrentGamePushVersion();
///////////////////////////////////////////////////////////////////////////////////////////