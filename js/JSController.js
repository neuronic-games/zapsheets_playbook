///////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////
// All global vars 
var touched = false
var isFullScreen = false
var maxLevel = 10; //12
var levelCounter = 1
var maxTime = 15
var bidAmount = Math.ceil(maxTime/3)
var pause = false
var selectBidPlayserID = -1
var isTimerStarted = false
var isOnEndScreen = false
// init user props at start
// Amount in Euro
var fPlayer_0 = {
  name: 'Player 1',
  total_amount : 30,
  amount_spent : 0,
}
var fPlayer_1 = {
  name: 'Player 2',
  total_amount : 30,
  amount_spent : 0,
}
// Level Wise bid
var player_0_Bid = false
var player_1_Bid = false

// For waiting user event
var player1_active = false
var player2_active = false

// Scoring counter
var player_0_count = 0
var player_1_count = 0

//var imgList = ["/img/flor_sym_blue.png", "/img/flor_sym_orange.png", "/img/flor_sym_purple.png", "/img/flor_sym_red.png", "/img/flor_sym_white.png", "/img/flor_sym_yellow.png"]

var imgList = ["img/mari_flower_yellow_1.png", "img/mari_flower_red_2.png", "img/mari_flower_green_3.png", "img/mari_flower_purple_4.png", "img/mari_flower_blue_5.png", "img/mari_flower_orange_6.png"]


var imgCounter = -1
var downloadTimer;
var bothActive = false

var pauseFrom = ''
var secondHand = ''
//////////////////////////////////////////////////////////////////////////////
var canvas, stage, exportRoot, anim_container, dom_overlay_container, fnStartAnimation;
var canvas_android, stage_android, exportRoot_android, anim_container_android, dom_overlay_container_android, fnStartAnimation_android;
var canvas_howto, stage_howto, exportRoot_howto, anim_container_howto, dom_overlay_container_howto, fnStartAnimation_howto;
//////////////////////////////////////////////////////////////////////////////
let pollTime = 20;
let isToggle = false;
let currentSheetVersion = 0;
let periodicVersion = 0
let RefreshAppVersionTime = 10; // Default time
let deviceUID = null
let systemMemoryUsed = ''
let systemName = ''
///////////////////////////////////////////////////////////////////////////////////////////
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
        newScript.src = 'js/version.js?version=' + Math.random();
        document.getElementsByTagName('head')[0].appendChild(newScript);
    }
}
///////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////
/**
 * // Getting current App module working (main.js)
 */
function getCurrentGameMainVersion() {
    if(window.navigator.onLine == true) {
        // Loading version.js dynamically for [mac fix]
        var floristryScript = document.createElement('script');
        floristryScript.type = 'text/javascript';
        floristryScript.id = 'floristry_Script';
        floristryScript.src = 'js/stepsMain.js?version=' + Math.random();
        floristryScript.onload = checkLoadStat()
        document.getElementsByTagName('head')[0].appendChild(floristryScript);
    }
}
// Getting current iOS module working (floristry_ios.js)
/* function getCurrentFloristryiOSVersion() {
    if(window.navigator.onLine == true) {
        // Loading version.js dynamically for [mac fix]
        var floristryiOSScript = document.createElement('script');
        floristryiOSScript.type = 'text/javascript';
        floristryiOSScript.id = 'floristryiOS_Script';
        floristryiOSScript.src = 'js/floristry_ios.js?version=' + Math.random();
        floristryiOSScript.onload = checkLoadStatiOS()
        document.getElementsByTagName('head')[0].appendChild(floristryiOSScript);
    }
} */
// Getting current iOS module working (floristry_ios.js)
/* function getCurrentFloristryAndroidVersion() {
    if(window.navigator.onLine == true) {
        // Loading version.js dynamically for [mac fix]
        var floristryAndroidScript = document.createElement('script');
        floristryAndroidScript.type = 'text/javascript';
        floristryAndroidScript.id = 'floristryAndroid_Script';
        floristryAndroidScript.src = 'js/floristry_android.js?version=' + Math.random();
        floristryAndroidScript.onload = checkLoadStatAndroid()
        document.getElementsByTagName('head')[0].appendChild(floristryAndroidScript);
    }
} */
// Getting current iOS module working (floristry_ios.js)
/* function getCurrentFloristryHowToVersion() {
    if(window.navigator.onLine == true) {
        // Loading version.js dynamically for [mac fix]
        var floristryRulesScript = document.createElement('script');
        floristryRulesScript.type = 'text/javascript';
        floristryRulesScript.id = 'floristryRules_Script';
        floristryRulesScript.src = 'js/floristry_rules.js?version=' + Math.random();
        floristryRulesScript.onload = checkLoadStatHowTo()
        document.getElementsByTagName('head')[0].appendChild(floristryRulesScript);
    }
} */
///////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 */
function checkLoadStat() {
    console.log("Step JS LOADED")
}
///////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////
/* function checkLoadStatiOS() {
    console.log("iOS JS LOADED")
}
function checkLoadStatAndroid() {
    console.log("Android JS LOADED")
}
function checkLoadStatHowTo() {
    console.log("Rules JS LOADED")
} */
///////////////////////////////////////////////////////////////////////////////////////////
//window.addEventListener('load', (event) => {
let currentRunningVersion = 0;
//console.log("JS FILE LOADED")
/* getCurrentVersion(); */
//////////////////////////////////////////////////////////////////////////////////////////
checkVersion();
///////////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 */
function checkVersion() {
    getCurrentVersion();
    let versionTimer = setTimeout(function() {
        clearTimeout(versionTimer)
        //console.log(typeof _version)
        if(typeof _version != 'undefined') {
            currentRunningVersion = _version;
            periodicVersion = _version;


            // Functions call : When app loads for first time
            getCurrentGameMainVersion();
            /* getCurrentFloristryAndroidVersion();
            getCurrentFloristryiOSVersion();
            getCurrentFloristryHowToVersion(); */



            // Undo If needed
            /* checkAppVersionStatus() */

        } else {
            // Loop the function untill we have active internet to fetch the data  
            checkVersion();
        }
    }, 2000)
}
//////////////////////////////////////////////////////////////////////////////////////////
/* getCurrentGameMainVersion();
getCurrentFloristryAndroidVersion();
getCurrentFloristryiOSVersion();
getCurrentFloristryHowToVersion();
checkAppVersionStatus() */
//})
//////////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 */
function checkAppVersionStatus() {
    //console.log("CHECKING VERS")
    let versionPeriodicTimer = setTimeout(function() {
        clearTimeout(versionPeriodicTimer)
        if(window.navigator.onLine == true) {
            // get new app version
            getCurrentVersion();
            /* if(window.navigator.onLine == true) { */
                if(_version != currentRunningVersion) {
                    currentRunningVersion = _version
                    console.log("NEW Version")
                    /* document.getElementsByTagName('head')[0].removeChild(document.getElementById("version_Script"));
                    document.getElementsByTagName('head')[0].removeChild(document.getElementById("floristry_Script"));
                    document.getElementsByTagName('head')[0].removeChild(document.getElementById("floristryiOS_Script"));
                    document.getElementsByTagName('head')[0].removeChild(document.getElementById("floristryAndroid_Script")); */

                    /* document.getElementById("version_Script").parentNode.removeChild(document.getElementById("version_Script"));
                    document.getElementById("floristry_Script").parentNode.removeChild(document.getElementById("floristry_Script"));
                    document.getElementById("floristryiOS_Script").parentNode.removeChild(document.getElementById("floristryiOS_Script"));
                    document.getElementById("floristryAndroid_Script").parentNode.removeChild(document.getElementById("floristryAndroid_Script")); */

                    //document.getElementsByTagName('head')[0].removeChild(floristryScript);
                    
                    /* window.removeEventListener('keyup', onKeyUpfunc);
                    window.removeEventListener('keydown', onKeyDownFunc); */

                    setTimeout(function() {
                    // Reload the mainMap js file
                        /* getCurrentGameMainVersion();
                        getCurrentFloristryAndroidVersion();
                        getCurrentFloristryiOSVersion(); */
                    }, 2000)
                }
           /*  } */
        }
        checkAppVersionStatus();
    }, RefreshAppVersionTime * 1000)
}
//////////////////////////////////////////////////////////////////////////////////////////