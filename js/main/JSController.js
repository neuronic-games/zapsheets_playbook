///////////////////////////////////////////////////////////////////////////////////////////
// All global vars
// Controller version
console.log('[JSController] script loaded');
let controllerVerion = 4
// For standalone
let isStandalone = false;
// Layout
let portrait = window.matchMedia("(orientation: portrait)");
//////////////////////////////////////////////////////////////
// SET YOUR SPREADSHEET ID HERE
let activeSheet_id = (getUrlVars()["id"]) ? getUrlVars()["id"].split('/')[0].toUpperCase() : '1qFZqXwiEixdRzO1Ae57_ON9oKzoa-uBiUAOoMcGzoM4'
//////////////////////////////////////////////////////////////
// Path of the steps language url [sprecially from different server like zapsheets.com]
// Change the path accordingly.
let jasonPath = './'
///////////////////////////////////////////////////////////////////////////////////////////
function getCurrentLiveVersion() {
    var newScript = document.createElement('script');
    newScript.id = 'version_Script';
    newScript.type = 'text/javascript';
    newScript.src = '../../js/main/version.js?version=' + UIVersion;
    newScript.onload = checkSettingLoad();
    document.getElementsByTagName('head')[0].appendChild(newScript);
}
///////////////////////////////////////////////////////////////////////////////////////////
/**
 * getCurrentGameMainVersion
 */
function getCurrentGameMainVersion() {
    var floristryScript = document.createElement('script');
    floristryScript = document.createElement('script');
    floristryScript.type = 'text/javascript';
    floristryScript.id = 'floristry_Script';
    floristryScript.src = '../../js/main/main.js?version=' + UIVersion;
    floristryScript.onload = checkLoadStatMain()
    document.getElementsByTagName('head')[0].appendChild(floristryScript);
}
///////////////////////////////////////////////////////////////////////////////////////////
/**
 * getCurrentGameMenuVersion
 */
/* function getCurrentGameMenuVersion() {
    var menuMainScript = document.createElement('script');
    menuMainScript = document.createElement('script');
    menuMainScript.type = 'text/javascript';
    menuMainScript.id = 'floristry_Script';
    menuMainScript.src = './js/menuMain.js?version=' + UIVersion;
    menuMainScript.onload = checkLoadStatMainMenu()
    document.getElementsByTagName('head')[0].appendChild(menuMainScript);
} */
///////////////////////////////////////////////////////////////////////////////////////////
/**
 * getCurrentMenuVersion
 */
/* function getCurrentMenuVersion() {
    var menuScript = document.createElement('script');
    menuScript.type = 'text/javascript';
    menuScript.id = 'floristry_Script';
    menuScript.src = './js/menu.js?version=' + UIVersion;
    menuScript.onload = checkLoadStatMenu()
    document.getElementsByTagName('head')[0].appendChild(menuScript);
} */
///////////////////////////////////////////////////////////////////////////////////////////
// Matomo Tracking Scripts
/* function doTrackOnMatomo() {
    var _paq = window._paq = window._paq || [];
    //tracker methods like "setCustomDimension" should be called before "trackPageView"
    _paq.push(['trackPageView']);
    _paq.push(['enableLinkTracking']);
    (function() {
      var u="//zapsheets.com/stats/";
      _paq.push(['setTrackerUrl', u+'matomo.php']);
      _paq.push(['setSiteId', '1']);
      var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
      g.async=true; g.src=u+'matomo.js'; s.parentNode.insertBefore(g,s);
    })();
} */
///////////////////////////////////////////////////////////////////////////////////////////
/**
 * checkSettingLoad
 */
function checkSettingLoad() {
}
///////////////////////////////////////////////////////////////////////////////////////////
/**
 * checkLoadStatMainMenu
 */
function checkLoadStatMainMenu() {
}
///////////////////////////////////////////////////////////////////////////////////////////
/**
 * checkLoadStatMenu
 */
function checkLoadStatMenu() {
}
///////////////////////////////////////////////////////////////////////////////////////////
/**
 * checkLoadStatMain
 */
function checkLoadStatMain() {
}
///////////////////////////////////////////////////////////////////////////////////////////
/**
 * doCheckOrientation
 */
function doCheckOrientation() {
    var device = DetectSpecificDevice();
    var isPortrait = portrait.matches;
    console.log('[JSController] doCheckOrientation — device:', device, 'portrait:', isPortrait);
    if(device == 'desktop') {
        document.getElementById('useMode').style.display = 'none'
        if(window.orientation != 0) {
            pause = false;
            document.getElementById('useModeBG').style.display = 'none';
        }
        return
    }
    if(isPortrait) {
        document.getElementById('useMode').style.display = 'none'
        pause = false;
        document.getElementById('useModeBG').style.display = 'none';
    } else {
        // Landscape on a non-desktop device: show rotation prompt
        document.getElementById('useMode').style.display = 'flex'
        document.getElementById('modeLogo').style.display = 'block'
        document.getElementById('modeLogo').style.width = '45vh'
        document.getElementById('useModeBG').style.display = 'block';
        pause = true
    }
}
///////////////////////////////////////////////////////////////////////////////////////////
let currentRunningVersion = 0;
checkVersion();
///////////////////////////////////////////////////////////////////////////////////////////
/**
 * checkVersion
 */
function checkVersion() {
    doCheckOrientation();
    if(window.navigator.onLine == true) {
        // Version files
        getCurrentLiveVersion();
        // Game file
        getCurrentGameMainVersion();

        // Matomo Tacking
        //doTrackOnMatomo();
    } else {
        // Version files
        getCurrentLiveVersion();
        // Game file
        getCurrentGameMainVersion();
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
//////////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 * @returns 
 */
function DetectSpecificDevice() {
    var OSType = null;
    if(deviceDetector.device == 'tablet') {
        OSType = 'iPad'
    } else if(deviceDetector.device == 'desktop') {
        OSType = 'desktop'
    } else {
        OSType = 'phone'
    }
    return OSType;
}