///////////////////////////////////////////////////////////////////////////////////////////
// All global vars 
var touched = false
var isFullScreen = false
var pause = false
var isOnEndScreen = false
var downloadTimer;
var pauseFrom = ''
let pollTime = 20;
let isToggle = false;
let currentSheetVersion = 0;
let periodicVersion = 0
let RefreshAppVersionTime = 5; // Default time
let deviceUID = null
let systemMemoryUsed = ''
let systemName = ''
///////////////////////////////////////////////////////////////////////////////////////////
// Home Icon Animation vars
let FULL_DASH_ARRAY = 283;
let RESET_DASH_ARRAY = `-57 ${FULL_DASH_ARRAY}`;
let timer;// = document.querySelector("#base-timer-path-remaining");
let timerFinal;// = document.querySelector("#base-timer-path-remaining-end");
let timerGame;
//let timeLabel = document.getElementById("base-timer-label");
let TIME_LIMIT = 3; //in seconds
let timePassed = 1;
let timeLeft = TIME_LIMIT;
let timerInterval = null;
let modeType = 0;
let machineFPS = 0
let inSteps = true;
let languageStepsData = []
let fromStep = false;
// For Settings JSON
let currentJSONVersion = 0;
let newJSONVersion = 0
let inStart = true;
// For setting list holder
let settingDataList = []
// For install list holder
let installDataList = []
// For controller version update
let controllerVersion = ''
// document Ready status
let documentReadyStat = false
// Cut-Off time
let cutOffTime = 5;
let cutOffCount = 0
let cutOffCountLang = 0
let cutOffTimePassed = false
let processStartTime = 0;
let processEndTime = 0;
// Controller version
let controllerVerion = 4
// For standalone
let isStandalone = false;
// Layout
let portrait = window.matchMedia("(orientation: portrait)");
//////////////////////////////////////////////////////////////
// SET YOUR SPREADSHEET ID HERE
let activeSheet_id = (getUrlVars()["id"]) ? getUrlVars()["id"].split('/')[0].toUpperCase() : '1CWWzaAXQbI-SkvU6I0kx6lHn32VKC-Ee4XadnueBZKI'
//////////////////////////////////////////////////////////////
// Path of the steps language url [sprecially from different server like zapsheets.com]
// Change the path accordingly.
// For Local Testing
let jasonPath = './steps/'
///////////////////////////////////////////////////////////////
// For Online
/* let jasonPath = 'https://zapsheets.com/playbook/steps/' */
///////////////////////////////////////////////////////////////////////////////////////////
function getCurrentLiveVersion() {
    var newScript = document.createElement('script');
    newScript.id = 'version_Script';
    newScript.type = 'text/javascript';
    newScript.src = 'js/version.js?version=' + UIVersion;
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
    floristryScript.src = 'js/main.js?version=' + UIVersion;
    floristryScript.onload = checkLoadStatMain()
    document.getElementsByTagName('head')[0].appendChild(floristryScript);
}
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
    })();s
} */
///////////////////////////////////////////////////////////////////////////////////////////
/**
 * checkSettingLoad
 */
function checkSettingLoad() {
    console.log('JS VERSION LOADED')
}
///////////////////////////////////////////////////////////////////////////////////////////
/**
 * checkLoadStatMain
 */
function checkLoadStatMain() {
    console.log('MAIN JS VERSION LOADED')
}
///////////////////////////////////////////////////////////////////////////////////////////
/**
 * doCheckOrientation
 */
function doCheckOrientation() {
    console.log(DetectSpecificDevice(), " DT")
    if(DetectSpecificDevice() == 'desktop') {
        document.getElementById('useMode').style.display = 'none'
        if(window.orientation != 0) {
            
            pause = false;
            document.getElementById('useModeBG').style.display = 'none';
        }

        //console.log(window.innerWidth, " --- ")
        //document.getElementById('content').style.width = '400px !important'
        //document.getElementById('content').style.left = (window.innerWidth-400)/2 + 'px !important'; 

        return
    }
    if(portrait.matches) {
        document.getElementById('useMode').style.display = 'none'
        pause = false;
        document.getElementById('useModeBG').style.display = 'none';
    } else {
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