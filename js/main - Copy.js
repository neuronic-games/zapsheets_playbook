/////////////////////////////////////////////////////////////////////////////////

/////////////////////////////////////////////////////////////////////////////////
// Global vars
//var tapCount = 0
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
var imgList = ["/img/flor_sym_blue.png", "/img/flor_sym_orange.png", "/img/flor_sym_purple.png", "/img/flor_sym_red.png", "/img/flor_sym_white.png", "/img/flor_sym_yellow.png"]
var imgCounter = -1

var downloadTimer;

var bothActive = false

/////////////////////////////////////////////////////////////////////////////////
$(".bodyWrapper").css({display:"none"})
$(".counter-end-page").css({display:"none"})
$(".counter-end-page-quit").css({display:"none"})
$(".bodyWrapper.oddPage").css({display:"flex"})
/////////////////////////////////////////////////////////////////////////////////
const secondHand = document.querySelector('[data-second-hand]')
secondHand.style.setProperty('--rotation', 270)
/////////////////////////////////////////////////////////////////////////////////
document.getElementById('clockHandler').addEventListener('click', onClockClick)

/////////////////////////////////////////////////////////////////////////////////
function onClockClick(event) {
  document.getElementById('clockHandler').removeEventListener('click', onClockClick)
  pause = true
  isTimerStarted = true
  $(".counter-end-page").css({display:"block"})
  $(".counterBox").css({zIndex:"1061"})
  $(".modal").css({zIndex:"999"})
  
  // Disable screen content
  document.getElementById('greenCounterAmount').style.opacity = 0.2
  document.getElementById('greenSmAmount').style.opacity = 0.2
  document.getElementById('yellowSmAmount').style.opacity = 0.2
  document.getElementById('yellowCounterAmount').style.opacity = 0.2

  
  setTimeout(function() {
    /* document.getElementById('resumeGame').addEventListener('click', onResumeClick) */
    document.getElementById('resetGame').addEventListener('click', onResetClick)
  }, 1000)
}
/////////////////////////////////////////////////////////////////////////////////
function onResumeClick(event) {
  pause = false
  isTimerStarted = false
  $(".counter-end-page").css({display:"none"})
  $(".counterBox").css({zIndex:"999"})
  
  // Enable screen content
  document.getElementById('greenCounterAmount').style.opacity = 1
  document.getElementById('greenSmAmount').style.opacity = 1
  document.getElementById('yellowSmAmount').style.opacity = 1
  document.getElementById('yellowCounterAmount').style.opacity = 1

  setTimeout(function() {
    document.getElementById('clockHandler').addEventListener('click', onClockClick)
  }, 1000)
}
/////////////////////////////////////////////////////////////////////////////////
function onResetClick(event) {
  pause = false
  isTimerStarted = false
  maxTime = 15 * (1000/10)
  document.getElementById("0_0_0").innerHTML = ""
  document.getElementById("1_1").innerHTML = ""
  document.getElementById("0_0_0").innerHTML = "€" + Number(fPlayer_0['total_amount'] - fPlayer_0['amount_spent']) 
  document.getElementById("1_1").innerHTML = "€" + Number(fPlayer_1['total_amount'] - fPlayer_1['amount_spent'])

  var bidAmt = Math.ceil(maxTime/3)
  document.getElementById("0_0").innerHTML = "€" + bidAmt
  document.getElementById("1_0").innerHTML = "€" + bidAmt
  secondHand.style.setProperty('--rotation', 270)

  $(".counter-end-page").css({display:"none"})
  $(".counterBox").css({zIndex:"999"})

  // Enable screen content
  document.getElementById('greenCounterAmount').style.opacity = 1
  document.getElementById('greenSmAmount').style.opacity = 1
  document.getElementById('yellowSmAmount').style.opacity = 1
  document.getElementById('yellowCounterAmount').style.opacity = 1

  // Go to READY screen
  onGameContinue(event)

  setTimeout(function() {
    document.getElementById('clockHandler').addEventListener('click', onClockClick)
  }, 1000)
}
/////////////////////////////////////////////////////////////////////////////////
function onGameRestart(event) {
  //event.preventDefault();
  if (event.cancelable) event.preventDefault();
  /////////////////////////////////////////////
  $(".bodyWrapper").css({display:"none"})
  $(".bodyWrapper.oddPage").css({display:"flex"})
  
  // Reset All values
  touched = false
  isFullScreen = false
  maxLevel = 10; //12
  levelCounter = 1
  maxTime = 15
  bidAmount = Math.ceil(maxTime/3)
  pause = false
  selectBidPlayserID = -1
  isTimerStarted = false
  isOnEndScreen = false
  // init user props at start
  // Amount in Euro
  /////////////////////////////////////////////
  fPlayer_0["total_amount"] = 30
  fPlayer_0["amount_spent"] = 0

  fPlayer_1["total_amount"] = 30
  fPlayer_1["amount_spent"] = 0
  /////////////////////////////////////////////
  document.getElementById('score_0').innerHTML = ""
  document.getElementById('score_1').innerHTML = ""
  /////////////////////////////////////////////
  secondHand.style.setProperty('--rotation', 270)
  ///////////////////////////////////////////////
  // Level Wise bid
  player_0_Bid = false
  player_1_Bid = false
  /////////////////////////////////////////////
  updateAllPlayersStats()
  ResetPlayersArea()
  /////////////////////////////////////////////
  player1_active = false
  player2_active = false
  player_0_count = 0
  player_1_count = 0
  /////////////////////////////////////////////
  // Reset image counter
  imgCounter = -1
  /////////////////////////////////////////////


  // Disable animation
  document.getElementById('roundTokenGreen').style.display = 'none';
 /*  document.getElementById('roundTokenYellow').style.display = 'none'; */


}
/////////////////////////////////////////////////////////////////////////////////
function onGameContinue(event) {
  //event.preventDefault()
  if(event != undefined) {
    if (event.cancelable) event.preventDefault();
  }
  $(".bodyWrapper").css({display:"none"})
  $(".bodyWrapper.second-screen").css({display:"flex"})
  // Restart the counter
  isOnEndScreen = false
  isTimerStarted = false

  var bidAmt = Math.ceil(15/3)
  document.getElementById("0_0").innerHTML = "€" + bidAmt
  document.getElementById("1_0").innerHTML = "€" + bidAmt
  
  ////////////////////////////////////////
  updateAllPlayersStats()
  ////////////////////////////////////////
  // Set players Bid level
  player_0_Bid = false
  player_1_Bid = false
  touched = false

  // For waiting user event
  player1_active = false
  player2_active = false
  ////////////////////////////////////////
  pause = false
  clearInterval(downloadTimer)
  ///////////////////////////////////////
  // Show game count
  //console.log(levelCounter, " LEVEL")
  document.getElementById("greenRound").textContent = levelCounter + ' OF ' + maxLevel;
  document.getElementById("yellowRound").textContent = levelCounter + ' OF ' + maxLevel;

  // 31/3/24
  document.getElementById("counter_0").textContent = levelCounter + ' OF ' + maxLevel;
  document.getElementById("counter_1").textContent = levelCounter + ' OF ' + maxLevel;

  ResetPlayersArea()
  maxTime = 15
  secondHand.style.setProperty('--rotation', 270)
  ////////////////////////////////////////
}
/////////////////////////////////////////////////////////////////////////////////
function disableDefault(event) {
  //event.preventDefault()
  if (event.cancelable) event.preventDefault();
  // Make full screen
  var elem = document.getElementById('mainBody')
  if (elem.requestFullscreen) {
    isFullScreen = true
    elem.requestFullscreen(); /* Others */
  } else if (elem.webkitRequestFullscreen) { /* Safari */
    isFullScreen = true
    elem.webkitRequestFullscreen();
  } else if (elem.msRequestFullscreen) { /* IE11 */
    isFullScreen = true
    elem.msRequestFullscreen();
  } else {
    isFullScreen = false
  }
}
/////////////////////////////////////////////////////////////////////////////////
function updateAllPlayersStats() {
  document.getElementById("0").innerHTML = ""
  document.getElementById("1").innerHTML = ""
  document.getElementById("0").innerHTML = "€" + Number(fPlayer_0['total_amount'] - fPlayer_0['amount_spent'])
  document.getElementById("1").innerHTML = "€" + Number(fPlayer_1['total_amount'] - fPlayer_1['amount_spent'])
  document.getElementById("player0").innerHTML = "€" + Number(fPlayer_0["total_amount"] - fPlayer_0["amount_spent"])
  document.getElementById("player1").innerHTML = "€" + Number(fPlayer_1["total_amount"] - fPlayer_1["amount_spent"])
}
/////////////////////////////////////////////////////////////////////////////////
function onCloseModalTouch(event) {
  //event.preventDefault()
  if (event.cancelable) event.preventDefault();
  $('#myModal2').modal('hide');
  pause = false
  touched = false
  ResetPlayersArea()
  updateAllPlayersStats()
}
/////////////////////////////////////////////////////////////////////////////////
function DetectMobileType() {
  var OSType = null;
  if (navigator.userAgent.match(/iPhone/i)
      || navigator.userAgent.match(/iPad/i)
      || navigator.userAgent.match(/iPod/i)
    ) {
      OSType = "iPhone/iPad/iPod";
    } else if(navigator.userAgent.match(/Android/i)) {
      OSType = "Android";
    } else {
      OSType = false ;
    }
    return OSType;
  ////////////////////////////////////////////////////////////////////////////////////////////////////////////
}
/////////////////////////////////////////////////////////////////////////////////
function showOSInstruction(event) {
  var osType = DetectMobileType();
  document.getElementById('OSInfo').style.display = "block";
  //console.log(osType, " >>osType")
  if(osType == 'iPhone/iPad/iPod') {
    // Call Animate CC file for ios
    //console.log("Called init_OS")
    init_iOS();
    //showiOSAnimation();
    setTimeout(function() {
      // 1/5/24
      //document.getElementById("OSInfo").addEventListener('touchstart', quitOsInfo)
      
      //document.getElementById("OSInfo").addEventListener('click', quitOsInfo)
    }, 500)
    
  } else if(osType == 'Android') {
    // Call Animate CC file for android
    init_android()
    //showAndroidAnimation();
    setTimeout(function() {
      document.getElementById("OSInfo").addEventListener('touchstart', quitOsInfo)

      //document.getElementById("OSInfo").addEventListener('click', quitOsInfo)
    }, 500)
  } 
}
/////////////////////////////////////////////////////////////////////////////////
$(document).ready(function() {
  // blue : #29ABE2 - Green : #02FF00
  // Yellow : #FBB03C
  // Check for device
  var osType = DetectMobileType();
  //console.log('is full screen : ', isFullScreen, " :::: ", osType)
  if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone) {
    document.getElementById('OSInfo').style.display = "none";
    document.getElementById('goFullScreen').style.display = "none";
    document.getElementById('learnPlay').style.display = 'block';
    document.getElementById('learnPlay').style.top = '15vh !important;';
  } else {
    //document.getElementById('OSInfo').style.display = "block";
    //document.getElementById('OSInfo').style.display = "none";
    if(osType == 'iPhone/iPad/iPod') {
      // Call Animate CC file for ios
      //console.log("init")
      //init_iOS();
      //document.getElementById('infoText_iOS').innerHTML = "iOS Instructions";
    } else if(osType == 'Android') {
      //console.log("init_android")
      // Call Animate CC file for android
      //init_android()
      //document.getElementById('infoText_android').innerHTML = "Android Instructions";
    } else {
      document.getElementById('OSInfo').style.display = "none";
    }
  }


  // Disable animation
  document.getElementById('roundTokenGreen').style.display = 'none';
  /* document.getElementById('roundTokenYellow').style.display = 'none'; */


  // add event Listener
  document.getElementById("OSInfo").removeEventListener('touchstart', quitOsInfo)
  //document.getElementById("OSInfo").removeEventListener('click', quitOsInfo)

  // BIDDING SECTION BUTTON HANDLERS
  // Player 2 READY Events
  document.getElementById("readyGreenBtn").addEventListener('touchstart', onGreenTouchStart)
  document.getElementById("readyGreenBtn").addEventListener('mousedown', onGreenTouchStart)
  document.getElementById("readyGreenBtn").addEventListener('touchend', onGreenTouchEnd)
  document.getElementById("readyGreenBtn").addEventListener('mouseup', onGreenTouchEnd)

  // Player 1 READY Events
  document.getElementById("readyYellowBtn").addEventListener('touchstart', onYellowTouchStart)
  document.getElementById("readyYellowBtn").addEventListener('mousedown', onYellowTouchStart)
  document.getElementById("readyYellowBtn").addEventListener('touchend', onYellowTouchEnd)
  document.getElementById("readyYellowBtn").addEventListener('mouseup', onYellowTouchEnd)

  /////////////////////////////////////////////////////////////////////////////////
  // For Pause button on start
  document.getElementById('pauseGame').addEventListener('click', onPauseClickQuit)
  function onPauseClickQuit() {
    //console.log("CLICK")
    pause = true
    $(".counter-end-page-quit").css({display:"block"})

    // Disable screen content
    document.getElementById('readyGreenBtn').style.opacity = 0.2
    document.getElementById('greenRound').style.opacity = 0.2
    document.getElementById('greenReady').style.opacity = 0.2
    document.getElementById('player1').style.opacity = 0.2
    document.getElementById('readyYellowBtn').style.opacity = 0.2
    document.getElementById('yellowRound').style.opacity = 0.2
    document.getElementById('yellowReady').style.opacity = 0.2
    document.getElementById('player0').style.opacity = 0.2
  }
  /////////////////////////////////////////////////////////////////////////////////
  document.getElementById('quitControl').addEventListener('click', onQuitControlClick)
  function onQuitControlClick(event) {
    //console.log("outside click - ", event.target.id)
    pause = true
    // Enable screen content
    document.getElementById('readyGreenBtn').style.opacity = 1
    document.getElementById('greenRound').style.opacity = 1
    document.getElementById('greenReady').style.opacity = 1
    document.getElementById('player1').style.opacity = 1
    document.getElementById('readyYellowBtn').style.opacity = 1
    document.getElementById('yellowRound').style.opacity = 1
    document.getElementById('yellowReady').style.opacity = 1
    document.getElementById('player0').style.opacity = 1
    $(".counter-end-page-quit").css({display:"none"})
    if(event.target.id == 'quitGame') {
      onGameRestart(event)
    } 
  }
  /////////////////////////////////////////////////////////////////////////////////
  function onGreenTouchStart (event) {
    //event.preventDefault()
    if (event.cancelable) event.preventDefault();
    //console.log(event.touches.length, " on Yellow")
    player2_active = true
    if(event.touches != undefined) {
      if(event.touches.length == 1) {
        //document.getElementById('initMiddleContent').innerHTML = ""
          var controldiv = `<div class="readyBtn green" id="readyGreenBtn">
          <h1 id="greenRound" class="roundText"></h1>
          <span id="greenReady">READY</span>
        </div>
        <div class="readySection">
          <span id="player1" class="rotated">€3</span>
          <!--h1 class="roundText"></h1-->
          <div id="pauseGame" style="position: relative; z-index: 99; transform: scale(0.3);">
            <img src="img/floristry_btn_pause.png?version=1.0" style="z-index: 9999 !important; position: relative; text-align: center;" alt="" />
          </div>
          <span id="player0">€3</span>
        </div>
        <div class="readyBtn yellow" id="readyYellowBtn">
          <h1 id="yellowRound" class="roundText"></h1>
          <span id="yellowReady">READY</span>
        </div>
        <div id="quitControl" class="counter-end-page-quit" style="display: block; position: absolute; width: 100%; height: 100%; background: #00000080;">
          <div id="endControl_timer-quit">
            <div class="bigBtn" style="font-size: 3.5em; z-index: 999999 !important;">
              <a id="quitGame" class="countBtn">QUIT</a>
              <!-- <a id="resumeGame">CONT</a> -->
            </div>
          </div>
        </div>
        `
        //document.getElementById('initMiddleContent').innerHTML = controldiv
        $(".counter-end-page-quit").css({display:"none"})

        //document.getElementsByClassName("roundText")[0].textContent = levelCounter + ' OF ' + maxLevel;
        document.getElementById("greenRound").textContent = levelCounter + ' OF ' + maxLevel;
        document.getElementById("yellowRound").textContent = levelCounter + ' OF ' + maxLevel;

        // 31/3/24
        document.getElementById("counter_0").textContent = levelCounter + ' OF ' + maxLevel;
        document.getElementById("counter_1").textContent = levelCounter + ' OF ' + maxLevel;

        document.getElementById("player0").innerHTML = "€" + Number(fPlayer_0["total_amount"] - fPlayer_0["amount_spent"])
        document.getElementById("player1").innerHTML = "€" + Number(fPlayer_1["total_amount"] - fPlayer_1["amount_spent"])
      }
    }
    ///////////////////////////////////////////////////////////////////////////////////////////
    // Change the color
    document.getElementById("greenReady").style.color = "#bb0a0a"
    ///////////////////////////////////////////////////////////////////////////////////////////
    if(player1_active == true && player2_active == true && pause == false) {
      bothActive = true
      document.getElementById("greenReady").style.color = "#11681F"
      document.getElementById("yellowReady").style.color = "#11681F"
    }
  }
  /////////////////////////////////////////////////////////////////////////////////
  function onGreenTouchEnd(event) {
    //event.preventDefault()
    if (event.cancelable) event.preventDefault();

    if($(".counter-end-page-quit").css('display') == 'block') {
      $(".counter-end-page-quit").css({display:"none"})
      // Disable screen content
      return
    }

    setTimeout(function() {
      if(bothActive == false) {
        pause = false
        player2_active = false
      }
    }, 0)

    if(event.touches != undefined) {
      if(event.touches.length == 0) {
        //document.getElementById('initMiddleContent').innerHTML = ""
          var controldiv = `<div class="readyBtn green" id="readyGreenBtn">
          <h1 id="greenRound" class="roundText"></h1>
          <span id="greenReady">READY</span>
        </div>
        <div class="readySection">
          <span id="player1" class="rotated">€3</span>
          <div id="pauseGame" style="position: relative; z-index: 99; transform: scale(0.3);">
            <img src="img/floristry_btn_pause.png?version=1.0" style="z-index: 9999 !important; position: relative; text-align: center;" alt="" />
          </div>
          <span id="player0">€3</span>
        </div>
        <div class="readyBtn yellow" id="readyYellowBtn">
          <h1 id="yellowRound" class="roundText"></h1>
          <span id="yellowReady">READY</span>
        </div>
        <div id="quitControl" class="counter-end-page-quit" style="display: block; position: absolute; width: 100%; height: 100%; background: #00000080;">
          <div id="endControl_timer-quit">
            <div class="bigBtn" style="font-size: 3.5em; z-index: 999999 !important;">
              <a id="quitGame" class="countBtn">QUIT</a>
              <!-- <a id="resumeGame">CONT</a> -->
            </div>
          </div>
        </div>
        `
        //document.getElementById('initMiddleContent').innerHTML = controldiv
        $(".counter-end-page-quit").css({display:"none"})

        ///////////////////////////////////////////////////////////////////////////////////////////
        // Reset the color
        //document.getElementById("greenReady").style.color = "#ffffff"
        ///////////////////////////////////////////////////////////////////////////////////////////
      
       /*  document.getElementById("greenRound").textContent = ""; */
        /* document.getElementById("yellowRound").textContent = ""; */
        document.getElementById("greenRound").textContent = levelCounter + ' OF ' + maxLevel;
        document.getElementById("yellowRound").textContent = levelCounter + ' OF ' + maxLevel;

        document.getElementById("player0").innerHTML = "€" + Number(fPlayer_0["total_amount"] - fPlayer_0["amount_spent"])
        document.getElementById("player1").innerHTML = "€" + Number(fPlayer_1["total_amount"] - fPlayer_1["amount_spent"])

        ///////////////////////////////////////////////////////////////////////////////////////////
        // Check both users touch and redirect them to start of the game
        //if(player1_active == true && player2_active == true && pause == false) {
          pause = true
          document.getElementById("greenRound").textContent = levelCounter + ' OF ' + maxLevel;
          document.getElementById("yellowRound").textContent = levelCounter + ' OF ' + maxLevel;

          // 31/3/24
          document.getElementById("counter_0").textContent = levelCounter + ' OF ' + maxLevel;
          document.getElementById("counter_1").textContent = levelCounter + ' OF ' + maxLevel;


          // Change the color
          /* document.getElementById("greenReady").style.color = "#bb0a0a"
          document.getElementById("yellowReady").style.color = "#bb0a0a" */
          document.getElementById("greenReady").style.color = "#11681F"
          document.getElementById("yellowReady").style.color = "#11681F"

          var outTimer = setTimeout(function() {
            clearTimeout(outTimer)
            player1_active = false
            player2_active = false
            document.getElementById("greenReady").style.color = "#ffffff"
            document.getElementById("yellowReady").style.color = "#ffffff"
            bothActive = false
            onGoModalTouch(event)
          }, 10)
        //} else {
          //document.getElementById("greenReady").style.color = "#ffffff"
        //}
        ///////////////////////////////////////////////////////////////////////////////////////////
      } else {
        if(bothActive == false) {
          document.getElementById("greenReady").style.color = "#ffffff"
        } else {
          document.getElementById("greenReady").style.color = "#11681F"
        }
      }
    }
  }
  /////////////////////////////////////////////////////////////////////////////////
  // YELLOW
  function onYellowTouchStart (event) {
    ///////////////////////////////////////////////////////////////////////////////////////////
    //event.preventDefault()
    if (event.cancelable) event.preventDefault();
    player1_active = true
    if(event.touches != undefined) {
      if(event.touches.length == 1) {
        //document.getElementById('initMiddleContent').innerHTML = ""
          var controldiv = `<div class="readyBtn green" id="readyGreenBtn">
          <h1 id="greenRound" class="roundText"></h1>
          <span id="greenReady">READY</span>
        </div>
        <div class="readySection">
          <span id="player1" class="rotated">€3</span>
          <!--h1 class="roundText"></h1-->
          <div id="pauseGame" style="position: relative; z-index: 99; transform: scale(0.3);">
            <img src="img/floristry_btn_pause.png?version=1.0" style="z-index: 9999 !important; position: relative; text-align: center;" alt="" />
          </div>
          <span id="player0">€3</span>
        </div>
        <div class="readyBtn yellow" id="readyYellowBtn">
          <h1 id="yellowRound" class="roundText"></h1>
          <span id="yellowReady">READY</span>
        </div>
        <div id="quitControl" class="counter-end-page-quit" style="display: block; position: absolute; width: 100%; height: 100%; background: #00000080;">
          <div id="endControl_timer-quit">
            <div class="bigBtn" style="font-size: 3.5em; z-index: 999999 !important;">
              <a id="quitGame" class="countBtn">QUIT</a>
              <!-- <a id="resumeGame">CONT</a> -->
            </div>
          </div>
        </div>
        `
        //document.getElementById('initMiddleContent').innerHTML = controldiv
        $(".counter-end-page-quit").css({display:"none"})
        document.getElementById("greenRound").textContent = levelCounter + ' OF ' + maxLevel;
        document.getElementById("yellowRound").textContent = levelCounter + ' OF ' + maxLevel;


        // 31/3/24
        document.getElementById("counter_0").textContent = levelCounter + ' OF ' + maxLevel;
        document.getElementById("counter_1").textContent = levelCounter + ' OF ' + maxLevel;


        document.getElementById("player0").innerHTML = "€" + Number(fPlayer_0["total_amount"] - fPlayer_0["amount_spent"])
        document.getElementById("player1").innerHTML = "€" + Number(fPlayer_1["total_amount"] - fPlayer_1["amount_spent"])
      }
    }
    ///////////////////////////////////////////////////////////////////////////////////////////
    // Change the color
    document.getElementById("yellowReady").style.color = "#bb0a0a"
    ///////////////////////////////////////////////////////////////////////////////////////////
    if(player1_active == true && player2_active == true && pause == false) {
      bothActive = true
      document.getElementById("greenReady").style.color = "#11681F"
      document.getElementById("yellowReady").style.color = "#11681F"
    }
  }
  /////////////////////////////////////////////////////////////////////////////////
  function onYellowTouchEnd(event) {
    //event.preventDefault()
    if (event.cancelable) event.preventDefault();
    setTimeout(function() {
      if(bothActive == false) {
        pause = false
        player1_active = false
      }
    }, 0)

    if(event.touches != undefined) {
      if(event.touches.length == 0) {
        //document.getElementById('initMiddleContent').innerHTML = ""
          var controldiv = `<div class="readyBtn green" id="readyGreenBtn">
          <h1 id="greenRound" class="roundText"></h1>
          <span id="greenReady">READY</span>
        </div>
        <div class="readySection">
          <span id="player1" class="rotated">€3</span>
          <div id="pauseGame" style="position: relative; z-index: 99; transform: scale(0.3);">
            <img src="img/floristry_btn_pause.png?version=1.0" style="z-index: 9999 !important; position: relative; text-align: center;" alt="" />
          </div>
          <span id="player0"></span>
        </div>
        <div class="readyBtn yellow" id="readyYellowBtn">
          <h1 id="yellowRound" class="roundText"></h1>
          <span id="yellowReady">READY</span>
        </div>
        <div id="quitControl" class="counter-end-page-quit" style="display: block; position: absolute; width: 100%; height: 100%; background: #00000080;">
          <div id="endControl_timer-quit">
            <div class="bigBtn" style="font-size: 3.5em; z-index: 999999 !important;">
              <a id="quitGame" class="countBtn">QUIT</a>
              <!-- <a id="resumeGame">CONT</a> -->
            </div>
          </div>
        </div>
        `
        //document.getElementById('initMiddleContent').innerHTML = controldiv
        $(".counter-end-page-quit").css({display:"none"})

        ///////////////////////////////////////////////////////////////////////////////////////////
        // Reset the color
        //document.getElementById("yellowReady").style.color = "#ffffff"
        ///////////////////////////////////////////////////////////////////////////////////////////

        /* document.getElementById("greenRound").textContent = "";
        document.getElementById("yellowRound").textContent = ""; */
        document.getElementById("greenRound").textContent = levelCounter + ' OF ' + maxLevel;
        document.getElementById("yellowRound").textContent = levelCounter + ' OF ' + maxLevel;

        
        document.getElementById("player0").innerHTML = "€" + Number(fPlayer_0["total_amount"] - fPlayer_0["amount_spent"])
        document.getElementById("player1").innerHTML = "€" + Number(fPlayer_1["total_amount"] - fPlayer_1["amount_spent"])
      
        ///////////////////////////////////////////////////////////////////////////////////////////
        // Check both users touch and redirect them to start of the game
        if(player1_active == true && player2_active == true && pause == false) {
          pause = true
          document.getElementById("greenRound").textContent = levelCounter + ' OF ' + maxLevel;
          document.getElementById("yellowRound").textContent = levelCounter + ' OF ' + maxLevel;


          // 31/3/24
          document.getElementById("counter_0").textContent = levelCounter + ' OF ' + maxLevel;
          document.getElementById("counter_1").textContent = levelCounter + ' OF ' + maxLevel;

          // Change the color
          document.getElementById("greenReady").style.color = "#11681F"
          document.getElementById("yellowReady").style.color = "#11681F"
          var setoutTimer = setTimeout(function() {
            clearTimeout(setoutTimer)
            player1_active = false
            player2_active = false
            document.getElementById("greenReady").style.color = "#ffffff"
            document.getElementById("yellowReady").style.color = "#ffffff"
            bothActive = false
            onGoModalTouch(event)
          }, 10)
        } else {
          document.getElementById("yellowReady").style.color = "#FFFFFF"
        }
      } else {
        if(bothActive == false) {
          document.getElementById("yellowReady").style.color = "#ffffff"
        } else {
          document.getElementById("yellowReady").style.color = "#11681F"
        }
      }
    }
  }
  //////////////////////////////////////////////////////////////////////////////////////////////
  // 11/5
  // END SECTION BUTTON HANDLERS
  // Player 2 READY Events
  document.getElementById("readyGreenEndScoreBtn").addEventListener('touchstart', onEndScoreGreenTouchStart)
  document.getElementById("readyGreenEndScoreBtn").addEventListener('mousedown', onEndScoreGreenTouchStart)
  document.getElementById("readyGreenEndScoreBtn").addEventListener('touchend', onEndScoreGreenTouchEnd)
  document.getElementById("readyGreenEndScoreBtn").addEventListener('mouseup', onEndScoreGreenTouchEnd)

  // Player 1 READY Events
  document.getElementById("readyYellowEndScoreBtn").addEventListener('touchstart', onEndScoreYellowTouchStart)
  document.getElementById("readyYellowEndScoreBtn").addEventListener('mousedown', onEndScoreYellowTouchStart)
  document.getElementById("readyYellowEndScoreBtn").addEventListener('touchend', onEndScoreYellowTouchEnd)
  document.getElementById("readyYellowEndScoreBtn").addEventListener('mouseup', onEndScoreYellowTouchEnd)
  /////////////////////////////////////////////////////////////////////////////////
  function onEndScoreGreenTouchStart (event) {
    ///////////////////////////////////////////////////////////////////////////////////////////
    //event.preventDefault()
    if (event.cancelable) event.preventDefault();
    //console.log("onMouseDown")
    //console.log(event.touches.length, " on Yellow")
    player2_active = true
    if(event.touches != undefined) {
      if(event.touches.length == 1) {
        document.getElementById('initEndContent').innerHTML = ""
          var controldiv = `<div class="readyBtn green" id="readyGreenEndScoreBtn">
          <h1 id="greenRoundEnd" class="roundText"></h1>
          <span id="greenReadyEnd">READY</span>
        </div>
        <div class="readySection">
          <span id="1_1" class="rotated">€3</span>
          <span id="0_0_0">€3</span>
        </div>
        <div class="readyBtn yellow" id="readyYellowEndScoreBtn">
          <h1 id="yellowRoundEnd" class="roundText"></h1>
          <span id="yellowReadyEnd">READY</span>
        </div>
        `
        document.getElementById('initEndContent').innerHTML = controldiv

        //document.getElementsByClassName("roundText")[0].textContent = levelCounter + ' OF ' + maxLevel;
        /* document.getElementById("greenRoundEnd").textContent = "SCORING";
        document.getElementById("yellowRoundEnd").textContent = ""; */
        document.getElementById("greenRoundEnd").textContent = "SCORING";
        document.getElementById("yellowRoundEnd").textContent = "SCORING";

        document.getElementById("0_0_0").innerHTML = "€" + Number(fPlayer_0["total_amount"] - fPlayer_0["amount_spent"])
        document.getElementById("1_1").innerHTML = "€" + Number(fPlayer_1["total_amount"] - fPlayer_1["amount_spent"])
      }
    }
    ///////////////////////////////////////////////////////////////////////////////////////////
    // Change the color
    document.getElementById("greenReadyEnd").style.color = "#bb0a0a"
    ///////////////////////////////////////////////////////////////////////////////////////////
    // Player 2 READY Events
    document.getElementById("readyGreenEndScoreBtn").addEventListener('touchstart', onEndScoreGreenTouchStart)
    document.getElementById("readyGreenEndScoreBtn").addEventListener('mousedown', onEndScoreGreenTouchStart)
    document.getElementById("readyGreenEndScoreBtn").addEventListener('touchend', onEndScoreGreenTouchEnd)
    document.getElementById("readyGreenEndScoreBtn").addEventListener('mouseup', onEndScoreGreenTouchEnd)

    // Player 1 READY Events
    document.getElementById("readyYellowEndScoreBtn").addEventListener('touchstart', onEndScoreYellowTouchStart)
    document.getElementById("readyYellowEndScoreBtn").addEventListener('mousedown', onEndScoreYellowTouchStart)
    document.getElementById("readyYellowEndScoreBtn").addEventListener('touchend', onEndScoreYellowTouchEnd)
    document.getElementById("readyYellowEndScoreBtn").addEventListener('mouseup', onEndScoreYellowTouchEnd)
  }
  /////////////////////////////////////////////////////////////////////////////////
  function onEndScoreGreenTouchEnd(event) {
    //event.preventDefault()
    if (event.cancelable) event.preventDefault();
    //console.log(event.touches.length, " on green")
    setTimeout(function() {
      //if(player1_active == false) {
        player2_active = false
      //}
    }, 700)

    if(event.touches != undefined) {
      if(event.touches.length == 0) {
        document.getElementById('initEndContent').innerHTML = ""
          var controldiv = `<div class="readyBtn green" id="readyGreenEndScoreBtn">
          <h1 id="greenRoundEnd" class="roundText"></h1>
          <span id="greenReadyEnd">READY</span>
        </div>
        <div class="readySection">
          <span id="1_1" class="rotated">€3</span>
          <span id="0_0_0">€3</span>
        </div>
        <div class="readyBtn yellow" id="readyYellowEndScoreBtn">
          <h1 id="yellowRoundEnd" class="roundText"></h1>
          <span id="yellowReadyEnd">READY</span>
        </div>
        `
        document.getElementById('initEndContent').innerHTML = controldiv

        ///////////////////////////////////////////////////////////////////////////////////////////
        // Reset the color
        document.getElementById("greenReadyEnd").style.color = "#ffffff"
        ///////////////////////////////////////////////////////////////////////////////////////////
      
       /*  document.getElementById("greenRoundEnd").textContent = "";
        document.getElementById("yellowRoundEnd").textContent = ""; */
        document.getElementById("greenRoundEnd").textContent = "SCORING";
        document.getElementById("yellowRoundEnd").textContent = "SCORING";

        document.getElementById("0_0_0").innerHTML = "€" + Number(fPlayer_0["total_amount"] - fPlayer_0["amount_spent"])
        document.getElementById("1_1").innerHTML = "€" + Number(fPlayer_1["total_amount"] - fPlayer_1["amount_spent"])

        ///////////////////////////////////////////////////////////////////////////////////////////
        if(player1_active == true && player2_active == true) {
          document.getElementById("greenRoundEnd").textContent = "SCORING";
          document.getElementById("yellowRoundEnd").textContent = "SCORING";
          // Change the color
          document.getElementById("greenReadyEnd").style.color = "#bb0a0a"
          document.getElementById("yellowReadyEnd").style.color = "#bb0a0a"
    
          var outTimer = setTimeout(function() {
            clearTimeout(outTimer)
            player1_active = false
            player2_active = false
            document.getElementById("greenReadyEnd").style.color = "#ffffff"
            document.getElementById("yellowReadyEnd").style.color = "#ffffff"
            // 17/5/23
            //onGoScoreScreen(event)
            onGoFinalScoreScreen(event)
          }, 1000)
        }
        ///////////////////////////////////////////////////////////////////////////////////////////
        // Reset Event Listener
        document.getElementById("readyGreenEndScoreBtn").addEventListener('touchstart', onEndScoreGreenTouchStart)
        document.getElementById("readyGreenEndScoreBtn").addEventListener('mousedown', onEndScoreGreenTouchStart)
        document.getElementById("readyGreenEndScoreBtn").addEventListener('touchend', onEndScoreGreenTouchEnd)
        document.getElementById("readyGreenEndScoreBtn").addEventListener('mouseup', onEndScoreGreenTouchEnd)

        // Yellow
        document.getElementById("readyYellowEndScoreBtn").addEventListener('touchstart', onEndScoreYellowTouchStart)
        document.getElementById("readyYellowEndScoreBtn").addEventListener('mousedown', onEndScoreYellowTouchStart)
        document.getElementById("readyYellowEndScoreBtn").addEventListener('touchend', onEndScoreYellowTouchEnd)
        document.getElementById("readyYellowEndScoreBtn").addEventListener('mouseup', onEndScoreYellowTouchEnd)
        ///////////////////////////////////////////////////////////////////////////////////////////
      } else {
        document.getElementById("greenReadyEnd").style.color = "#ffffff"
      }
    }
  }
  /////////////////////////////////////////////////////////////////////////////////
  // YELLOW
  function onEndScoreYellowTouchStart (event) {
    ///////////////////////////////////////////////////////////////////////////////////////////
    //console.log(event.srcElement.id)
    //event.preventDefault()
    if (event.cancelable) event.preventDefault();
    //console.log("onMouseDown")
    player1_active = true
    if(event.touches != undefined) {
      if(event.touches.length == 1) {
        document.getElementById('initEndContent').innerHTML = ""
          var controldiv = `<div class="readyBtn green" id="readyGreenEndScoreBtn">
          <h1 id="greenRoundEnd" class="roundText"></h1>
          <span id="greenReadyEnd">READY</span>
        </div>
        <div class="readySection">
          <span id="1_1" class="rotated">€3</span>
          <span id="0_0_0">€3</span>
        </div>
        <div class="readyBtn yellow" id="readyYellowEndScoreBtn">
          <h1 id="yellowRoundEnd" class="roundText"></h1>
          <span id="yellowReadyEnd">READY</span>
        </div>
        `
        document.getElementById('initEndContent').innerHTML = controldiv
        document.getElementById("greenRoundEnd").textContent = "SCORING";
        document.getElementById("yellowRoundEnd").textContent = "SCORING";

        document.getElementById("0_0_0").innerHTML = "€" + Number(fPlayer_0["total_amount"] - fPlayer_0["amount_spent"])
        document.getElementById("1_1").innerHTML = "€" + Number(fPlayer_1["total_amount"] - fPlayer_1["amount_spent"])
      }
    }
    ///////////////////////////////////////////////////////////////////////////////////////////
    // Change the color
    document.getElementById("yellowReadyEnd").style.color = "#bb0a0a"
    ///////////////////////////////////////////////////////////////////////////////////////////
    // Player 2 READY Events
    document.getElementById("readyGreenEndScoreBtn").addEventListener('touchstart', onEndScoreGreenTouchStart)
    document.getElementById("readyGreenEndScoreBtn").addEventListener('mousedown', onEndScoreGreenTouchStart)
    document.getElementById("readyGreenEndScoreBtn").addEventListener('touchend', onEndScoreGreenTouchEnd)
    document.getElementById("readyGreenEndScoreBtn").addEventListener('mouseup', onEndScoreGreenTouchEnd)

    // Player 1 READY Events
    document.getElementById("readyYellowEndScoreBtn").addEventListener('touchstart', onEndScoreYellowTouchStart)
    document.getElementById("readyYellowEndScoreBtn").addEventListener('mousedown', onEndScoreYellowTouchStart)
    document.getElementById("readyYellowEndScoreBtn").addEventListener('touchend', onEndScoreYellowTouchEnd)
    document.getElementById("readyYellowEndScoreBtn").addEventListener('mouseup', onEndScoreYellowTouchEnd)
  }
  /////////////////////////////////////////////////////////////////////////////////
  function onEndScoreYellowTouchEnd(event) {
    //event.preventDefault()
    if (event.cancelable) event.preventDefault();
    setTimeout(function() {
      //if(player2_active == false) {
        player1_active = false
      //}
    }, 700)

    if(event.touches != undefined) {
      if(event.touches.length == 0) {
        document.getElementById('initEndContent').innerHTML = ""
          var controldiv = `<div class="readyBtn green" id="readyGreenEndScoreBtn">
          <h1 id="greenRoundEnd" class="roundText"></h1>
          <span id="greenReadyEnd">READY</span>
        </div>
        <div class="readySection">
          <span id="1_1" class="rotated">€3</span>
          <span id="0_0_0">€3</span>
        </div>
        <div class="readyBtn yellow" id="readyYellowEndScoreBtn">
          <h1 id="yellowRoundEnd" class="roundText"></h1>
          <span id="yellowReadyEnd">READY</span>
        </div>
        `
        document.getElementById('initEndContent').innerHTML = controldiv

        ///////////////////////////////////////////////////////////////////////////////////////////
        // Reset the color
        document.getElementById("yellowReadyEnd").style.color = "#ffffff"
        ///////////////////////////////////////////////////////////////////////////////////////////

        /* document.getElementById("greenRoundEnd").textContent = "";
        document.getElementById("yellowRoundEnd").textContent = ""; */

        document.getElementById("greenRoundEnd").textContent = "SCORING";
        document.getElementById("yellowRoundEnd").textContent = "SCORING";

        document.getElementById("0_0_0").innerHTML = "€" + Number(fPlayer_0["total_amount"] - fPlayer_0["amount_spent"])
        document.getElementById("1_1").innerHTML = "€" + Number(fPlayer_1["total_amount"] - fPlayer_1["amount_spent"])

        ///////////////////////////////////////////////////////////////////////////////////////////
        if(player1_active == true && player2_active == true) {
          document.getElementById("greenRoundEnd").textContent = "SCORING";
          document.getElementById("yellowRoundEnd").textContent = "SCORING";
          // Change the color
          document.getElementById("greenReadyEnd").style.color = "#bb0a0a"
          document.getElementById("yellowReadyEnd").style.color = "#bb0a0a"
          var setoutTimer = setTimeout(function() {
            clearTimeout(setoutTimer)
            player1_active = false
            player2_active = false
            document.getElementById("greenReadyEnd").style.color = "#ffffff"
            document.getElementById("yellowReadyEnd").style.color = "#ffffff"
            onGoFinalScoreScreen(event)
          }, 1000)
        }
        /////////////////////////////////////////////////////////////////////////////////////////////
        // Reset Event Listener
        // Yellow
        document.getElementById("readyYellowEndScoreBtn").addEventListener('touchstart', onEndScoreYellowTouchStart)
        document.getElementById("readyYellowEndScoreBtn").addEventListener('mousedown', onEndScoreYellowTouchStart)
        document.getElementById("readyYellowEndScoreBtn").addEventListener('touchend', onEndScoreYellowTouchEnd)
        document.getElementById("readyYellowEndScoreBtn").addEventListener('mouseup', onEndScoreYellowTouchEnd)

        // Green
        document.getElementById("readyGreenEndScoreBtn").addEventListener('touchstart', onEndScoreGreenTouchStart)
        document.getElementById("readyGreenEndScoreBtn").addEventListener('mousedown', onEndScoreGreenTouchStart)
        document.getElementById("readyGreenEndScoreBtn").addEventListener('touchend', onEndScoreGreenTouchEnd)
        document.getElementById("readyGreenEndScoreBtn").addEventListener('mouseup', onEndScoreGreenTouchEnd)
        ///////////////////////////////////////////////////////////////////////////////////////////
      } else {
        document.getElementById("yellowReadyEnd").style.color = "#ffffff"
      }
    }
  }
  //////////////////////////////////////////////////////////////////////////////////////////////
  //////////////////////////////////////////////////////////////////////////////////////////////
  function onGoScoreCountScreen(event) {
    //event.preventDefault()
    if (event.cancelable) event.preventDefault();
    ///////////////////////////
    $(".bodyWrapper").css({display:"none"})
    $(".bodyWrapper.score-page-count").css({display:"flex"})
    ///////////////////////////////
    // Reset the values
    var player_0_blance = fPlayer_0['total_amount'] - fPlayer_0['amount_spent']
    var player_1_blance = fPlayer_1['total_amount'] - fPlayer_1['amount_spent']
    var spentMost = Math.min(player_0_blance, player_1_blance)

    var player_0_points = (player_0_blance - spentMost)
    var player_1_points = (player_1_blance - spentMost)

    var player_0_score = getUserScore(player_0_points)
    var player_1_score = getUserScore(player_1_points)

    // Reset Img Counter
    imgCounter = 0
    player1_active = false
    player2_active = false

    document.getElementById("score_counter_0").innerHTML = player_0_count
    document.getElementById("score_counter_1").innerHTML = player_1_count

    document.getElementById("score_0").innerHTML = player_0_score + " + " + player_0_count
    document.getElementById("score_1").innerHTML = player_1_score + " + " + player_1_count

    // Show image with counter
    document.getElementById("score_counter_img").src = window.location.href + imgList[imgCounter]

    //////////////////////////////////////////////////////////////
    // Event Listener
    // Player 2
    document.getElementById('greenReadyScoreCounter').addEventListener('touchstart', onScoreCounterGreenTouch)
    document.getElementById('greenReadyScoreCounter').addEventListener('mousedown', onScoreCounterGreenTouch)
    document.getElementById('greenReadyScoreCounter').addEventListener('touchend', onScoreCounterGreenEnd)
    document.getElementById('greenReadyScoreCounter').addEventListener('mouseup', onScoreCounterGreenEnd)

    // Player 1
    document.getElementById('yellowReadyScoreCounter').addEventListener('touchstart', onScoreCounterYellowTouch)
    document.getElementById('yellowReadyScoreCounter').addEventListener('mousedown', onScoreCounterYellowTouch)
    document.getElementById('yellowReadyScoreCounter').addEventListener('touchend', onScoreCounterYellowEnd)
    document.getElementById('yellowReadyScoreCounter').addEventListener('mouseup', onScoreCounterYellowEnd)

    // For Player 2 Score count
    document.getElementById('player1_plus').addEventListener('touchstart', onScoreCounterGreenPlusTouch)
    document.getElementById('player1_minus').addEventListener('touchstart', onScoreCounterGreenMinusTouch)
     // For Player 1 Score count
     document.getElementById('player0_plus').addEventListener('touchstart', onScoreCounterYellowPlusTouch)
     document.getElementById('player0_minus').addEventListener('touchstart', onScoreCounterYellowMinusTouch)
  }
  //////////////////////////////////////////////////////////////////////////////////////////////
  function onScoreCounterGreenPlusTouch(event) {
    //event.preventDefault()
    if (event.cancelable) event.preventDefault();
    //console.log('onScoreCounterGreenPlusTouch')
    // Generate values based on increment and update the cell
    var player_0_blance = fPlayer_0['total_amount'] - fPlayer_0['amount_spent']
    var player_1_blance = fPlayer_1['total_amount'] - fPlayer_1['amount_spent']
    var spentMost = Math.min(player_0_blance, player_1_blance)
    var player_1_points = (player_1_blance - spentMost)
    var player_1_score = getUserScore(player_1_points)
    
    player_1_count++
    var player_1_score_count = getUserScore(player_1_count)
    document.getElementById("score_counter_1").innerHTML = player_1_count
    document.getElementById("score_1").innerHTML = player_1_score + " + " + player_1_score_count
  }
  //////////////////////////////////////////////////////////////////////////////////////////////
  function onScoreCounterGreenMinusTouch(event) {
    //event.preventDefault()
    if (event.cancelable) event.preventDefault();
    
    //console.log('onScoreCounterGreenPlusTouch')
    // Generate values based on increment and update the cell
    var player_0_blance = fPlayer_0['total_amount'] - fPlayer_0['amount_spent']
    var player_1_blance = fPlayer_1['total_amount'] - fPlayer_1['amount_spent']
    var spentMost = Math.min(player_0_blance, player_1_blance)
    var player_1_points = (player_1_blance - spentMost)
    var player_1_score = getUserScore(player_1_points)
    
    if(player_1_count > 0) {
      player_1_count--
    }
    var player_1_score_count = getUserScore(player_1_count)
    document.getElementById("score_counter_1").innerHTML = player_1_count
    document.getElementById("score_1").innerHTML = player_1_score + " + " + player_1_score_count
  }
  //////////////////////////////////////////////////////////////////////////////////////////////
  function onScoreCounterYellowPlusTouch(event) {
    //event.preventDefault()
    if (event.cancelable) event.preventDefault();
    //console.log('onScoreCounterGreenPlusTouch')
    // Generate values based on increment and update the cell
    var player_0_blance = fPlayer_0['total_amount'] - fPlayer_0['amount_spent']
    var player_1_blance = fPlayer_1['total_amount'] - fPlayer_1['amount_spent']
    var spentMost = Math.min(player_0_blance, player_1_blance)
    var player_0_points = (player_0_blance - spentMost)
    var player_0_score = getUserScore(player_0_points)
    
    player_0_count++
    var player_0_score_count = getUserScore(player_0_count)
    document.getElementById("score_counter_0").innerHTML = player_0_count
    document.getElementById("score_0").innerHTML = player_0_score + " + " + player_0_score_count
  }
  //////////////////////////////////////////////////////////////////////////////////////////////
  function onScoreCounterYellowMinusTouch(event) {
    //event.preventDefault()
    if (event.cancelable) event.preventDefault();
    //console.log('onScoreCounterGreenPlusTouch')
    // Generate values based on increment and update the cell
    var player_0_blance = fPlayer_0['total_amount'] - fPlayer_0['amount_spent']
    var player_1_blance = fPlayer_1['total_amount'] - fPlayer_1['amount_spent']
    var spentMost = Math.min(player_0_blance, player_1_blance)
    var player_0_points = (player_0_blance - spentMost)
    var player_0_score = getUserScore(player_0_points)
    
    if(player_0_count > 0) {
      player_0_count--
    }
    var player_0_score_count = getUserScore(player_0_count)
    document.getElementById("score_counter_0").innerHTML = player_0_count
    document.getElementById("score_0").innerHTML = player_0_score + " + " + player_0_score_count
  }

  //////////////////////////////////////////////////////////////////////////////////////////////
  function onScoreCounterGreenTouch(event) {
    //event.preventDefault()
    if (event.cancelable) event.preventDefault();
    //console.log("onScoreCounterGreenTouch")
    player2_active = true
    if(event.touches != undefined) {
      if(event.touches.length == 1) {
        document.getElementById('greenReadyScoreCounter').style.color = "#bb0a0a"

        // Hides the buttons & show the calculated items
        document.getElementById("player1_plus").style.display = "none"
        document.getElementById("player1_minus").style.display = "none"
        document.getElementById("score_counter_1").style.display = "none"
        // Calculate the overall value
        var player_0_blance = fPlayer_0['total_amount'] - fPlayer_0['amount_spent']
        var player_1_blance = fPlayer_1['total_amount'] - fPlayer_1['amount_spent']
        var spentMost = Math.min(player_0_blance, player_1_blance)
        var player_1_points = (player_1_blance - spentMost)
        var player_1_score = getUserScore(player_1_points)
        

        var player_1_score_count = getUserScore(player_1_count)
        document.getElementById("score_1").innerHTML = Number(player_1_score +  player_1_score_count)
      }
    }
    // Check and change the flowers based on count [6]
    if(player1_active == true && player2_active == true) {
      // Change the color
      document.getElementById('greenReadyScoreCounter').style.color = "#bb0a0a"
      // Hide the buttons & show the calculated items
      document.getElementById("player1_plus").style.display = "none"
      document.getElementById("player1_minus").style.display = "none"
      document.getElementById("score_counter_1").style.display = "none"
      // Calculate the overall value
      var player_0_blance = fPlayer_0['total_amount'] - fPlayer_0['amount_spent']
      var player_1_blance = fPlayer_1['total_amount'] - fPlayer_1['amount_spent']
      var spentMost = Math.min(player_0_blance, player_1_blance)
      var player_1_points = (player_1_blance - spentMost)
      var player_0_points = (player_0_blance - spentMost)
      var player_1_score = getUserScore(player_1_points)
      var player_0_score = getUserScore(player_0_points)

      var player_1_score_count = getUserScore(player_1_count)
      document.getElementById("score_1").innerHTML = Number(player_1_score +  player_1_score_count)



      document.getElementById('yellowReadyScoreCounter').style.color = "#bb0a0a"
      // Hide the buttons & show the calculated items
      document.getElementById("player0_plus").style.display = "none"
      document.getElementById("player0_minus").style.display = "none"
      document.getElementById("score_counter_0").style.display = "none"

      var player_0_score_count = getUserScore(player_0_count)
      document.getElementById("score_0").innerHTML = Number(player_0_score +  player_0_score_count)

      var setoutTimer = setTimeout(function() {
        clearTimeout(setoutTimer)
        player1_active = false
        player2_active = false

        document.getElementById('greenReadyScoreCounter').style.color = "#ffffff"
        // show the buttons & show the calculated items
        document.getElementById("player1_plus").style.display = "block"
        document.getElementById("player1_minus").style.display = "block"
        document.getElementById("score_counter_1").style.display = "block"
        document.getElementById("score_1").innerHTML = player_1_score + " + " + player_1_score_count


        document.getElementById('yellowReadyScoreCounter').style.color = "#ffffff"
        // Show the buttons & show the calculated items
        document.getElementById("player0_plus").style.display = "block"
        document.getElementById("player0_minus").style.display = "block"
        document.getElementById("score_counter_0").style.display = "block"
        document.getElementById("score_0").innerHTML = player_0_score + " + " + player_0_score_count

        // change the flower in middle
        if(imgCounter < imgList.length-1) {
          imgCounter++
          document.getElementById("score_counter_img").src = window.location.href + imgList[imgCounter]
        } else {
          // show final screen
          //console.log("Show final screen")
          //imgCounter = -1
          var endTimer = setTimeout(function() {
            clearTimeout(endTimer)
            onGoFinalScoreScreen(event)
          }, 200)
        }
      }, 1000)
    }
  }
  //////////////////////////////////////////////////////////////////////////////////////////////
  function onScoreCounterGreenEnd(event) {
    //event.preventDefault()
    if (event.cancelable) event.preventDefault();
    if(player1_active == false) {
      player2_active = false
    }
    if(event.touches != undefined) {
      if(event.touches.length == 0) {
        document.getElementById('greenReadyScoreCounter').style.color = "#ffffff"

        // show the buttons & show the calculated items
        document.getElementById("player1_plus").style.display = "block"
        document.getElementById("player1_minus").style.display = "block"
        document.getElementById("score_counter_1").style.display = "block"

        // defract overall values
        var player_0_blance = fPlayer_0['total_amount'] - fPlayer_0['amount_spent']
        var player_1_blance = fPlayer_1['total_amount'] - fPlayer_1['amount_spent']
        var spentMost = Math.min(player_0_blance, player_1_blance)
        var player_1_points = (player_1_blance - spentMost)
        var player_1_score = getUserScore(player_1_points)
        
        var player_1_score_count = getUserScore(player_1_count)
        document.getElementById("score_counter_1").innerHTML = player_1_count
        document.getElementById("score_1").innerHTML = player_1_score + " + " + player_1_score_count
      }
    }
  }
  //////////////////////////////////////////////////////////////////////////////////////////////
  function onScoreCounterYellowTouch(event) {
    //event.preventDefault()
    if (event.cancelable) event.preventDefault();
    //console.log("onScoreCounterGreenTouch")
    player1_active = true
    if(event.touches != undefined) {
      if(event.touches.length == 1) {
      document.getElementById('yellowReadyScoreCounter').style.color = "#bb0a0a"
      // Hides the buttons & show the calculated items
      document.getElementById("player0_plus").style.display = "none"
      document.getElementById("player0_minus").style.display = "none"
      document.getElementById("score_counter_0").style.display = "none"

      // Calculate the overall value
      var player_0_blance = fPlayer_0['total_amount'] - fPlayer_0['amount_spent']
      var player_1_blance = fPlayer_1['total_amount'] - fPlayer_1['amount_spent']
      var spentMost = Math.min(player_0_blance, player_1_blance)
      var player_0_points = (player_0_blance - spentMost)
      var player_1_points = (player_1_blance - spentMost)
      var player_0_score = getUserScore(player_0_points)
      var player_1_score = getUserScore(player_1_points)
      

      var player_0_score_count = getUserScore(player_0_count)
      var player_1_score_count = getUserScore(player_1_count)
      document.getElementById("score_0").innerHTML = Number(player_0_score +  player_0_score_count)
      }
    }

    if(player1_active == true && player2_active == true) {
      // Change the color
      document.getElementById('greenReadyScoreCounter').style.color = "#bb0a0a"
      // Hide the buttons & show the calculated items
      document.getElementById("player1_plus").style.display = "none"
      document.getElementById("player1_minus").style.display = "none"
      document.getElementById("score_counter_1").style.display = "none"
      // Calculate the overall value
      var player_0_blance = fPlayer_0['total_amount'] - fPlayer_0['amount_spent']
      var player_1_blance = fPlayer_1['total_amount'] - fPlayer_1['amount_spent']
      var spentMost = Math.min(player_0_blance, player_1_blance)
      var player_1_points = (player_1_blance - spentMost)
      var player_0_points = (player_0_blance - spentMost)
      var player_1_score = getUserScore(player_1_points)
      var player_0_score = getUserScore(player_0_points)

      var player_1_score_count = getUserScore(player_1_count)
      document.getElementById("score_1").innerHTML = Number(player_1_score +  player_1_score_count)
      

      document.getElementById('yellowReadyScoreCounter').style.color = "#bb0a0a"
      // Hide the buttons & show the calculated items
      document.getElementById("player0_plus").style.display = "none"
      document.getElementById("player0_minus").style.display = "none"
      document.getElementById("score_counter_0").style.display = "none"
      var player_0_score_count = getUserScore(player_0_count)
      document.getElementById("score_0").innerHTML = Number(player_0_score +  player_0_score_count)

      var setoutTimer = setTimeout(function() {
        clearTimeout(setoutTimer)
        player1_active = false
        player2_active = false

        document.getElementById('greenReadyScoreCounter').style.color = "#ffffff"
        // show the buttons & show the calculated items
        document.getElementById("player1_plus").style.display = "block"
        document.getElementById("player1_minus").style.display = "block"
        document.getElementById("score_counter_1").style.display = "block"
        document.getElementById("score_1").innerHTML = player_1_score + " + " + player_1_score_count

        document.getElementById('yellowReadyScoreCounter').style.color = "#ffffff"
        // Hide the buttons & show the calculated items
        document.getElementById("player0_plus").style.display = "block"
        document.getElementById("player0_minus").style.display = "block"
        document.getElementById("score_counter_0").style.display = "block"
        document.getElementById("score_0").innerHTML = player_0_score + " + " + player_0_score_count

        // change the flower in middle
        if(imgCounter < imgList.length-1) {
          imgCounter++
          document.getElementById("score_counter_img").src = window.location.href + imgList[imgCounter]
        } else {
          // show final screen
          //console.log("Show final screen")
          //imgCounter = -1
          var endTimer = setTimeout(function() {
            clearTimeout(endTimer)
            onGoFinalScoreScreen(event)
          }, 200)
        }
      }, 1000)
    }
  }
  //////////////////////////////////////////////////////////////////////////////////////////////
  function onScoreCounterYellowEnd(event) {
    //event.preventDefault()
    if (event.cancelable) event.preventDefault();
    if(player2_active == false) {
      player1_active = false
    }
    if(event.touches != undefined) {
      if(event.touches.length == 0) {
      document.getElementById('yellowReadyScoreCounter').style.color = "#ffffff"
      // show the buttons & show the calculated items
      document.getElementById("player0_plus").style.display = "block"
      document.getElementById("player0_minus").style.display = "block"
      document.getElementById("score_counter_0").style.display = "block"

      // defract overall values
      var player_0_blance = fPlayer_0['total_amount'] - fPlayer_0['amount_spent']
      var player_1_blance = fPlayer_1['total_amount'] - fPlayer_1['amount_spent']
      var spentMost = Math.min(player_0_blance, player_1_blance)
      var player_0_points = (player_0_blance - spentMost)
      var player_0_score = getUserScore(player_0_points)
      
      var player_0_score_count = getUserScore(player_0_count)
      document.getElementById("score_counter_0").innerHTML = player_0_count
      document.getElementById("score_0").innerHTML = player_0_score + " + " + player_0_score_count
      }
    }
  }

  ///////////////////////////////////////////////////////////////////////////////////////
  function onGoScoreScreen(event) {
    //event.preventDefault()
    if (event.cancelable) event.preventDefault();
    ///////////////////////////
    $(".bodyWrapper").css({display:"none"})
    $(".bodyWrapper.score-page").css({display:"flex"})
    ///////////////////////////
    // show the details
    var player_0_blance = fPlayer_0['total_amount'] - fPlayer_0['amount_spent']
    var player_1_blance = fPlayer_1['total_amount'] - fPlayer_1['amount_spent']
    var spentMost = Math.min(player_0_blance, player_1_blance)

    var player_0_points = (player_0_blance - spentMost)
    var player_1_points = (player_1_blance - spentMost)

    var player_0_score = getUserScore(player_0_points)
    var player_1_score = getUserScore(player_1_points)

    // Update DIV element with score points
    if(player_0_score > 0) {
      document.getElementById('score_0_0_0').innerHTML = player_0_score
    } else {
      document.getElementById('score_0_0_0').innerHTML = "0"
    }
    if(player_1_score > 0) {
      document.getElementById('score_1_1').innerHTML = player_1_score
    } else {
      document.getElementById('score_1_1').innerHTML = "0"
    }

    // Update DIV element with balance amount
    document.getElementById('player0_bal').innerHTML = "€" + player_0_blance
    document.getElementById('player1_bal').innerHTML = "€" + player_1_blance
    //////////////////////////////////////////////////////////////////////////
    // Add Listeners
    //////////////////////////////////////////////////////////////////////////////////////////////
    // 11/5
    // END SECTION BUTTON HANDLERS
    // Player 2 READY Events
    document.getElementById("readyGreenScoreBtn").addEventListener('touchstart', onScoreGreenTouchStart)
    document.getElementById("readyGreenScoreBtn").addEventListener('mousedown', onScoreGreenTouchStart)
    document.getElementById("readyGreenScoreBtn").addEventListener('touchend', onScoreGreenTouchEnd)
    document.getElementById("readyGreenScoreBtn").addEventListener('mouseup', onScoreGreenTouchEnd)

    // Player 1 READY Events
    document.getElementById("readyYellowScoreBtn").addEventListener('touchstart', onScoreYellowTouchStart)
    document.getElementById("readyYellowScoreBtn").addEventListener('mousedown', onScoreYellowTouchStart)
    document.getElementById("readyYellowScoreBtn").addEventListener('touchend', onScoreYellowTouchEnd)
    document.getElementById("readyYellowScoreBtn").addEventListener('mouseup', onScoreYellowTouchEnd)

    function onScoreGreenTouchStart (event) {
      ///////////////////////////////////////////////////////////////////////////////////////////
      //event.preventDefault()
      if (event.cancelable) event.preventDefault();
      //console.log("onMouseDown")
      //console.log(event.touches.length, " on Yellow")
      player2_active = true
      if(event.touches != undefined) {
        if(event.touches.length == 1) {
        }
      }
      ///////////////////////////////////////////////////////////////////////////////////////////
      // Change the color
      document.getElementById("greenReadyScore").style.color = "#bb0a0a"
      ///////////////////////////////////////////////////////////////////////////////////////////
      // Player 2 READY Events
      document.getElementById("readyGreenScoreBtn").addEventListener('touchstart', onScoreGreenTouchStart)
      document.getElementById("readyGreenScoreBtn").addEventListener('mousedown', onScoreGreenTouchStart)
      document.getElementById("readyGreenScoreBtn").addEventListener('touchend', onScoreGreenTouchEnd)
      document.getElementById("readyGreenScoreBtn").addEventListener('mouseup', onScoreGreenTouchEnd)

      // Player 1 READY Events
      document.getElementById("readyYellowScoreBtn").addEventListener('touchstart', onScoreYellowTouchStart)
      document.getElementById("readyYellowScoreBtn").addEventListener('mousedown', onScoreYellowTouchStart)
      document.getElementById("readyYellowScoreBtn").addEventListener('touchend', onScoreYellowTouchEnd)
      document.getElementById("readyYellowScoreBtn").addEventListener('mouseup', onScoreYellowTouchEnd)
    }
    //////////////////////////////////////////////////////////////////////////////////////////////
    function onScoreGreenTouchEnd(event) {
      //event.preventDefault()
      if (event.cancelable) event.preventDefault();
      //console.log(event.touches.length, " on green")
      setTimeout(function() {
        //if(player1_active == false) {
          player2_active = false
        //}
      }, 700)

      if(event.touches != undefined) {
        if(event.touches.length == 0) {
          // Reset the color
          document.getElementById("greenReadyScore").style.color = "#ffffff"
          ///////////////////////////////////////////////////////////////////////////////////////////
          if(player1_active == true && player2_active == true) {
            // Change the color
            document.getElementById('greenReadyScore').style.color = "#11681F"
            document.getElementById('yellowReadyScore').style.color = "#11681F"
            
            var outTimer = setTimeout(function() {
              clearTimeout(outTimer)
              player1_active = false
              player2_active = false
              document.getElementById("greenReadyScore").style.color = "#ffffff"
              document.getElementById("yellowReadyScore").style.color = "#ffffff"
              onGoFinalScoreScreen(event)
            }, 1000)
          }
          ///////////////////////////////////////////////////////////////////////////////////////////
          // Reset Event Listener
          document.getElementById("readyGreenScoreBtn").addEventListener('touchstart', onScoreGreenTouchStart)
          document.getElementById("readyGreenScoreBtn").addEventListener('mousedown', onScoreGreenTouchStart)
          document.getElementById("readyGreenScoreBtn").addEventListener('touchend', onScoreGreenTouchEnd)
          document.getElementById("readyGreenScoreBtn").addEventListener('mouseup', onScoreGreenTouchEnd)

          // Yellow
          document.getElementById("readyYellowScoreBtn").addEventListener('touchstart', onScoreYellowTouchStart)
          document.getElementById("readyYellowScoreBtn").addEventListener('mousedown', onScoreYellowTouchStart)
          document.getElementById("readyYellowScoreBtn").addEventListener('touchend', onScoreYellowTouchEnd)
          document.getElementById("readyYellowScoreBtn").addEventListener('mouseup', onScoreYellowTouchEnd)
          ///////////////////////////////////////////////////////////////////////////////////////////
        }
      }
    }
    //////////////////////////////////////////////////////////////////////////////////////////////
    // YELLOW
    function onScoreYellowTouchStart (event) {
      ///////////////////////////////////////////////////////////////////////////////////////////
      //console.log(event.srcElement.id)
      //event.preventDefault()
      if (event.cancelable) event.preventDefault();
      player1_active = true
      if(event.touches != undefined) {
        if(event.touches.length == 1) {
        }
      }
      ///////////////////////////////////////////////////////////////////////////////////////////
      // Change the color
      document.getElementById("yellowReadyScore").style.color = "#bb0a0a"
      ///////////////////////////////////////////////////////////////////////////////////////////
      // Player 2 READY Events
      document.getElementById("readyGreenScoreBtn").addEventListener('touchstart', onScoreGreenTouchStart)
      document.getElementById("readyGreenScoreBtn").addEventListener('mousedown', onScoreGreenTouchStart)
      document.getElementById("readyGreenScoreBtn").addEventListener('touchend', onScoreGreenTouchEnd)
      document.getElementById("readyGreenScoreBtn").addEventListener('mouseup', onScoreGreenTouchEnd)

      // Player 1 READY Events
      document.getElementById("readyYellowScoreBtn").addEventListener('touchstart', onScoreYellowTouchStart)
      document.getElementById("readyYellowScoreBtn").addEventListener('mousedown', onScoreYellowTouchStart)
      document.getElementById("readyYellowScoreBtn").addEventListener('touchend', onScoreYellowTouchEnd)
      document.getElementById("readyYellowScoreBtn").addEventListener('mouseup', onScoreYellowTouchEnd)
    }
    //////////////////////////////////////////////////////////////////////////////////////////////
    function onScoreYellowTouchEnd(event) {
      //event.preventDefault()
      if (event.cancelable) event.preventDefault();
      //console.log("onMouseUp")
      setTimeout(function() {
        //if(player2_active == false) {
          player1_active = false
        //}
      }, 700)

      if(event.touches != undefined) {
        if(event.touches.length == 0) {
          ///////////////////////////////////////////////////////////////////////////////////////////
          // Reset the color
          document.getElementById("yellowReadyScore").style.color = "#ffffff"
          ///////////////////////////////////////////////////////////////////////////////////////////
          //console.log(player1_active, " --- ", player2_active)
          if(player1_active == true && player2_active == true) {
            // Change the color
            /* document.getElementById('greenReadyScore').style.color = "#bb0a0a"
            document.getElementById('yellowReadyScore').style.color = "#bb0a0a" */
            document.getElementById('greenReadyScore').style.color = "#11681F"
            document.getElementById('yellowReadyScore').style.color = "#11681F"
            
            var setoutTimer = setTimeout(function() {
              clearTimeout(setoutTimer)
              player1_active = false
              player2_active = false
              document.getElementById("greenReadyScore").style.color = "#ffffff"
              document.getElementById("yellowReadyScore").style.color = "#ffffff"
              onGoFinalScoreScreen(event)
            }, 1000)
          }
          ///////////////////////////////////////////////////////////////////////////////////////////
          // Reset Event Listener
          // Yellow
          document.getElementById("readyYellowScoreBtn").addEventListener('touchstart', onScoreYellowTouchStart)
          document.getElementById("readyYellowScoreBtn").addEventListener('mousedown', onScoreYellowTouchStart)
          document.getElementById("readyYellowScoreBtn").addEventListener('touchend', onScoreYellowTouchEnd)
          document.getElementById("readyYellowScoreBtn").addEventListener('mouseup', onScoreYellowTouchEnd)

          // Green
          document.getElementById("readyGreenScoreBtn").addEventListener('touchstart', onScoreGreenTouchStart)
          document.getElementById("readyGreenScoreBtn").addEventListener('mousedown', onScoreGreenTouchStart)
          document.getElementById("readyGreenScoreBtn").addEventListener('touchend', onScoreGreenTouchEnd)
          document.getElementById("readyGreenScoreBtn").addEventListener('mouseup', onScoreGreenTouchEnd)
          ///////////////////////////////////////////////////////////////////////////////////////////
        }
      }
    }
  }
  //////////////////////////////////////////////////////////////////////////////////////////////
  function onGoModalTouch(event) {
    //event.preventDefault()
    if (event.cancelable) event.preventDefault();
    ///////////////////////////
    $(".bodyWrapper").css({display:"none"})
    $(".bodyWrapper.evenPage").css({display:"flex"})
    ///////////////////////////
    ResetPlayersArea()
    maxTime = 15
    secondHand.style.setProperty('--rotation', 270)

    // Reset All vars
    pause = false
    isTimerStarted = false


    ///////////////////////
    isOnEndScreen = false
    var bidAmt = Math.ceil(15/3)
    document.getElementById("0_0").innerHTML = "€" + bidAmt
    document.getElementById("1_0").innerHTML = "€" + bidAmt
    updateAllPlayersStats()
    // Set players Bid level
    player_0_Bid = false
    player_1_Bid = false
    touched = false
    // For waiting user event
    player1_active = false
    player2_active = false
    ////////////////////////////////////////
    // Delay the timer so that the screens get active for players
    var startupTimerOut = setTimeout(function() {
      clearTimeout(startupTimerOut)
      startBidTimer(event)
    }, 10)


  }
});
/////////////////////////////////////////////////////////////////////////////////
$(document).ready(function() {
  document.getElementById('goModal2').addEventListener('click', onSelectModalTouch)
  function onSelectModalTouch(event) {
    //event.preventDefault()
    if (event.cancelable) event.preventDefault();
  //$('.selectModal').on('click', function() {
    $('#myModal2').modal('hide');
    pause = false
    // Add calculation
    //console.log(selectBidPlayserID, " Select bid user")
    checkAndDeductMoney(selectBidPlayserID)
    $(".bodyWrapper").css({display:"none"})
    /////////////////////////////////////////////////////////////////////
    // Reset the player values
    document.getElementById("0_0_0").innerHTML = ""
    document.getElementById("1_1").innerHTML = ""
    document.getElementById("0_0_0").innerHTML = "€" + Number(fPlayer_0['total_amount'] - fPlayer_0['amount_spent']) 
    document.getElementById("1_1").innerHTML = "€" + Number(fPlayer_1['total_amount'] - fPlayer_1['amount_spent'])
    ResetPlayersArea()
    isTimerStarted = false
    isOnEndScreen = true
    //////////////////////////////////////////////////////////////////////////////////////////////////////
    // Hide the betInfo message
    document.getElementById('yellowBet').style.display = "none";
    document.getElementById('greenBet').style.display = "none";
    //////////////////////////////////////////////////////////////////////////////////////////////////////
    //console.log(levelCounter, " ====== ", maxLevel)
    if(levelCounter == maxLevel) {
    //if(levelCounter == 1) {
      // Send to final screen
      onGoFinalScoreScreen(event)
    } else {
      levelCounter++
      // Go to READY screen
      onGameContinue(event)
    }
  }
  //});
});
//////////////////////////////////////////////////////////////////////////////////////////////
function getUserScore(_userPoints) {
  var userScore = 0
  if( _userPoints > 11 ) {
    userScore = 10
  } else if (_userPoints > 8) {
    userScore = 6
  } else if (_userPoints > 5) {
    userScore = 3
  } else if (_userPoints > 2) {
    userScore = 1
  }
  return userScore
}
//////////////////////////////////////////////////////////////////////////////////////////////
function ResetPlayersArea() {
  // Reset back the color
  /* $(".bodyWrapper.evenPage .boxes.green .counterAmount").css({backgroundColor:"#00FF00"}) */
  $(".bodyWrapper.evenPage .boxes.green .counterAmount").css({backgroundColor:"#29ABE2"})
  $(".bodyWrapper.evenPage .boxes.yellow .counterAmount").css({backgroundColor:"#FBB03B"})
}

/////////////////////////////////////////////////////////////////////////////////
function showHowTo(event) {
  //window.open("https://www.uncommonspublishing.com/how-to-play/")
}
//function closeiOsInfo(event) {
function quitOsInfo(event) {
  //console.log(document.getElementById('OSInfo').style.display)
  document.getElementById('OSInfo').style.display = "none";

  setTimeout(function() {
    var osType = DetectMobileType();
    if(osType == 'iPhone/iPad/iPod') {
      HideiOSAnimation();
    } else if(osType == 'Android') {
      HideAndroidAnimation();
    } 

    // 1/5/24
    //document.getElementById("OSInfo").removeEventListener('touchstart', quitOsInfo)

    //document.getElementById("OSInfo").removeEventListener('click', quitOsInfo)
  }, 10)
}
/////////////////////////////////////////////////////////////////////////////////
function showCounterScreen(event) {
  //event.preventDefault()
  if (event.cancelable) event.preventDefault();
  
  //tapCount++
  //if(tapCount == 2) {
  setTimeout(function(){
  //if(event.touches[0].identifier == 0) {
    $(".bodyWrapper").css({display:"none"})
    $(".bodyWrapper.second-screen").css({display:"flex"})
    var elem = document.getElementById('mainBody')
    if (elem.requestFullscreen) {
      isFullScreen = true
      elem.requestFullscreen(); /* Others */
    } else if (elem.webkitRequestFullscreen) { /* Safari */
      isFullScreen = true
      elem.webkitRequestFullscreen();
    } else if (elem.msRequestFullscreen) { /* IE11 */
      isFullScreen = true
      elem.msRequestFullscreen();
    } else {
      isFullScreen = false
    }

  // Show game count
  document.getElementById("greenRound").textContent = levelCounter + ' OF ' + maxLevel;
  document.getElementById("yellowRound").textContent = levelCounter + ' OF ' + maxLevel;

  // 31/3/24
  document.getElementById("counter_0").textContent = levelCounter + ' OF ' + maxLevel;
  document.getElementById("counter_1").textContent = levelCounter + ' OF ' + maxLevel;

    ResetAllPropllersStats()
  }, 200)

  //}
}
//////////////////////////////////////////////////////////////////////////////////////////
function checkPlayerBidablStatus() {
  // For Player 2
  //var amountBid = Math.ceil(Number(maxTime/3))
  var amountBid = Math.ceil((maxTime/3) / (1000/10))
 
  var amount_bid_area = Number(fPlayer_1['total_amount'] - Number(fPlayer_1['amount_spent']))
  if(amount_bid_area >= amountBid) {
    /* $(".bodyWrapper.evenPage .boxes.green .counterAmount").css({backgroundColor:"#00FF00"}) */
    $(".bodyWrapper.evenPage .boxes.green .counterAmount").css({backgroundColor:"#29ABE2"})
  } else {
    /* $(".bodyWrapper.evenPage .boxes.green .counterAmount").css({backgroundColor:"#00FF0060"}) */
    $(".bodyWrapper.evenPage .boxes.green .counterAmount").css({backgroundColor:"#29ABE260"})
  }
  // For Player 1
  var amount_bid_area = Number(fPlayer_0['total_amount'] - Number(fPlayer_0['amount_spent']))
  if(amount_bid_area >= amountBid) {
    $(".bodyWrapper.evenPage .boxes.yellow .counterAmount").css({backgroundColor:"#FBB03B"})
  } else {
    $(".bodyWrapper.evenPage .boxes.yellow .counterAmount").css({backgroundColor:"#FBB03B60"})
  }
}
//////////////////////////////////////////////////////////////////////////////////////////
function ResetAllPropllersStats() {
  // Show All Players stats
  // Set default amount to Players
  document.getElementById("0").innerHTML = ""
  document.getElementById("0_0").innerHTML = ""
  document.getElementById("1").innerHTML = ""
  document.getElementById("1_0").innerHTML = ""
  document.getElementById("0").innerHTML = "€" + fPlayer_0['total_amount']
  document.getElementById("0_0").innerHTML = "€" + bidAmount
  document.getElementById("1").innerHTML = "€" + fPlayer_1['total_amount']
  document.getElementById("1_0").innerHTML = "€" + bidAmount
}
//////////////////////////////////////////////////////////////////////////////////////////////
function startBidTimer(event) {
  //console.log("Timer Started")

  if(isTimerStarted == false) {
    maxTime = 15 * (1000/10)
    var showTime = ''
    downloadTimer = setInterval(function() {
      
      if(pause == false) {
        if(isOnEndScreen) {
          clearInterval(downloadTimer);
        }
        if(maxTime <= 0){
          clearInterval(downloadTimer);
          //console.log("Timer End")
          $(".bodyWrapper").css({display:"none"})

          //console.log(levelCounter, " ==== ", maxLevel)

          if(levelCounter == maxLevel) {
          //if(levelCounter == 1) {
            // Send to final screen
            onGoFinalScoreScreen(event)
          } else {
            //levelCounter++
            if(levelCounter == maxLevel) {
              onGoFinalScoreScreen(event)
            } else {
              // Go to READY screen
              levelCounter++
              onGameContinue(event)
            }
          }
          // Reset the player values
          document.getElementById("0_0_0").innerHTML = ""
          document.getElementById("1_1").innerHTML = ""
          document.getElementById("0_0_0").innerHTML = "€" + Number(fPlayer_0['total_amount'] - fPlayer_0['amount_spent']) 
          document.getElementById("1_1").innerHTML = "€" + Number(fPlayer_1['total_amount'] - fPlayer_1['amount_spent'])
        } else {
          // Check user bid status
          checkPlayerBidablStatus()
          if(maxTime < 10) {
            showTime = "0" + maxTime
          } else {
            showTime = maxTime
          }
          var bidAmt = Math.ceil((maxTime/3) / (1000/10))
          document.getElementById("0_0").innerHTML = "€" + bidAmt
          document.getElementById("1_0").innerHTML = "€" + bidAmt
          // Set Analog timer rotation
          setRotation(secondHand, maxTime)
          maxTime -= 1;
        }
        isTimerStarted = true
      } else {
        //console.log("Clear ----- ")
        // Timer reset
        //isTimerStarted = false
        //clearInterval(downloadTimer);
      }
    }, (1000/100));
  }
}
///////////////////////////////////////////////////////////////////////////////////////////
function setRotation(element, rotationRatio) {
  element.style.setProperty('--rotation', (rotationRatio * 18/(1000/10)))
}
///////////////////////////////////////////////////////////////////////////////////////////
function setUserID(event, _id) {
  //event.preventDefault()
  if (event.cancelable) event.preventDefault();
  if($(".counter-end-page").css('display') == 'block') {
    onResumeClick(event)
  }
  if(pause == true) {return}
  /////////////////////////////////////////////////////////////////////////////////////////
  // Wait for timer to start
  if(isTimerStarted == false) { return }
  /////////////////////////////////////////////////////////////////////////////////////////
  // Checking if the player has been bid for this level
  if(_id == 0 && player_0_Bid == true) {return}
  if(_id == 1 && player_1_Bid == true) {return}
  /////////////////////////////////////////////////////////////////////////////////////////
  // increase the modal index
  $(".modal").css({zIndex:"1061"})
  $(".counterBox").css({zIndex:"999"})
  /////////////////////////////////////////////////////////////////////////////////////////
  // Checking Bid amount availability
  var amountBid = Math.ceil((maxTime/3) / (1000/10))
  switch(_id) {
    case 0: 
      if(touched == false) { 
        selectBidPlayserID = _id
        touched = true
        var amount_bid_area = Number(fPlayer_0['total_amount'] - Number(fPlayer_0['amount_spent']))
        if(amount_bid_area >= amountBid) {
          pause = true
          $(".bodyWrapper.evenPage .boxes.green .counterAmount").css({backgroundColor:"black"})
        } else {
          player_0_Bid = false
          pause = false
          return
        }
      }
      break;
    case 1:
      if(touched == false) {  
        selectBidPlayserID = _id
        touched = true
        var amount_bid_area = Number(fPlayer_1['total_amount'] - Number(fPlayer_1['amount_spent']))
        if(amount_bid_area >= amountBid) {
          pause = true
          $(".bodyWrapper.evenPage .boxes.yellow .counterAmount").css({backgroundColor:"black"})
        } else {
          player_1_Bid = false
          pause = false
          return
        }
      }
      break;
  }
  /////////////////////////////////////////////////////////////////////////////////////////
  if(pause) {
    // Show selection box
    $('#myModal2').modal('show');

    // Show betInfo
    switch(_id) {
      case 0:
        document.getElementById('greenBet').style.display = "flex";
        break;
      case 1:
        document.getElementById('yellowBet').style.display = "flex";
        break;   
    }

  } else {
    selectBidPlayserID = -1
    touched = false
    pause = false
    player_1_Bid = false
    player_0_Bid = false
  }
}
///////////////////////////////////////////////////////////////////////////////////////////
function checkAndDeductMoney(_id) {
  //console.log("deduct - " , _id)
  if(maxTime < 0) {return}
  var amountBid = Math.ceil((maxTime/3) / (1000/10))
  switch(_id) {
    case 0: 
      if(Number(fPlayer_0['total_amount']) >= Number(fPlayer_0['amount_spent']) + amountBid) {
        var amount_bid_area = Number(fPlayer_0['total_amount'] - Number(fPlayer_0['amount_spent']))
        if(amount_bid_area >= amountBid) {
          fPlayer_0['amount_spent'] += amountBid
          document.getElementById("0").innerHTML = "€" + (Number(fPlayer_0['total_amount']) - Number(fPlayer_0['amount_spent']))
          // Set Bid level
          player_0_Bid = true
        } else {
          //console.log("User 1 Can not bid")
        }
      }
      break;
    case 1:
      if(fPlayer_1['total_amount'] >= Number(fPlayer_1['amount_spent'] + amountBid)) {
        var amount_bid_area = Number(fPlayer_1['total_amount'] - Number(fPlayer_1['amount_spent']))
        if(amount_bid_area >= amountBid) {
          fPlayer_1['amount_spent'] += amountBid
          document.getElementById("1").innerHTML = "€" + (Number(fPlayer_1['total_amount']) - Number(fPlayer_1['amount_spent']))
          document.getElementById("1_0").innerHTML = "€" + amountBid
          // Set Bid level
          player_1_Bid = true
        } else {
         //console.log("User 2 Can not bid")
        }
      }
      break;
  }
  selectBidPlayserID = -1
}

//////////////////////////////////////////////////////////////////////////////////////////////
function onGoFinalScoreScreen(event) {
  //event.preventDefault()
  if (event.cancelable) event.preventDefault();
  ///////////////////////////
  $(".bodyWrapper").css({display:"none"})
  $(".bodyWrapper.final-screen").css({display:"flex"})
  ///////////////////////////
  var player_0_blance = fPlayer_0['total_amount'] - fPlayer_0['amount_spent']
  var player_1_blance = fPlayer_1['total_amount'] - fPlayer_1['amount_spent']
  var spentMost = Math.min(player_0_blance, player_1_blance)
  var player_0_points = (player_0_blance - spentMost)
  var player_1_points = (player_1_blance - spentMost)
  var player_0_score = getUserScore(player_0_points)
  var player_0_score_count = getUserScore(player_0_count)
  var player_1_score = getUserScore(player_1_points)
  var player_1_score_count = getUserScore(player_1_count)

  // Showing records
  document.getElementById("player1_final").innerHTML = "€" + Number(player_1_blance)
  document.getElementById("player0_final").innerHTML = "€" + Number(player_0_blance)
  // Show score
 /*  document.getElementById('roundTextGreen').innerHTML = "SCORE " + Number(player_1_score + player_1_score_count)
  document.getElementById('roundTextYellow').innerHTML = "SCORE " + Number(player_0_score + player_0_score_count) */


  // 2/5/24
  /* document.getElementById('roundTextGreen').innerHTML = "POINTS: " + Number(player_1_score + player_1_score_count)
  document.getElementById('roundTextYellow').innerHTML = "POINTS: " + Number(player_0_score + player_0_score_count) */

  // Score Points at end [New Scoring system]
  let player_0_points_final = Math.floor(Number(player_0_blance)/3)
  let player_1_points_final = Math.floor(Number(player_1_blance)/3)


  /* document.getElementById('roundTextGreen').innerHTML = "POINTS: " + Number(player_1_points_final)
  document.getElementById('roundTextYellow').innerHTML = "POINTS: " + Number(player_0_points_final) */

  document.getElementById('roundTextGreen').innerHTML = "POINTS"
  document.getElementById('roundTextYellow').innerHTML = "POINTS"

  document.getElementById('player1_final_points_score').innerHTML = Number(player_1_points_final)
  document.getElementById('player0_final_points_score').innerHTML = Number(player_0_points_final)


  // Show or Hide token message based on point scored
  let greenScore = Number(player_1_score + player_1_score_count)
  let yellowScore = Number(player_0_score + player_0_score_count)

  /* if(greenScore > 0) {
    document.getElementById('roundTokenGreen').style.display = 'block';
  } else {
    document.getElementById('roundTokenGreen').style.display = 'none';
  } */

  document.getElementById('roundTokenGreen').style.display = 'block';

  /* if(yellowScore > 0) {
    document.getElementById('roundTokenYellow').style.display = 'block';
  } else {
    document.getElementById('roundTokenYellow').style.display = 'none';
  } */

  
  // Add Listeners
  // Player 2
  document.getElementById('readyGreenFinalBtn').addEventListener('touchstart', onFinalGreenTouch)
  document.getElementById('readyGreenFinalBtn').addEventListener('mousedown', onFinalGreenTouch)
  document.getElementById('readyGreenFinalBtn').addEventListener('touchend', onFinalGreenEnd)
  document.getElementById('readyGreenFinalBtn').addEventListener('mouseup', onFinalGreenEnd)
  // Player 1
  document.getElementById('readyYellowFinalBtn').addEventListener('touchstart', onFinalYellowTouch)
  document.getElementById('readyYellowFinalBtn').addEventListener('mousedown', onFinalYellowTouch)
  document.getElementById('readyYellowFinalBtn').addEventListener('touchend', onFinalYellowEnd)
  document.getElementById('readyYellowFinalBtn').addEventListener('mouseup', onFinalYellowEnd)
}
///////////////////////////////////////////////////////////////////////////////////////

///////////////////////////////////////////////////////////////////////////////////////

function onFinalGreenTouch (event) {
  //event.preventDefault()
  if (event.cancelable) event.preventDefault();
  player2_active = true
  if(event.touches != undefined) {
    if(event.touches.length == 1) {
    }
  }
  ///////////////////////////////////////////////////////////////////////////////////////////
  // Change the color
  document.getElementById("greenReadyFinal").style.color = "#bb0a0a"
  ///////////////////////////////////////////////////////////////////////////////////////////
  if(player1_active == true && player2_active == true) {
    bothActive = true
    document.getElementById('greenReadyFinal').style.color = "#11681F"
    document.getElementById('yellowReadyFinal').style.color = "#11681F"
  }
}
function onFinalGreenEnd(event) {
  //event.preventDefault()
  if (event.cancelable) event.preventDefault();
  setTimeout(function() {
    if(bothActive == false) {
      player2_active = false
    }
  }, 0)

  if(event.touches != undefined) {
    if(event.touches.length == 0) {
      ///////////////////////////////////////////////////////////////////////////////////////////
      // Reset the color
      //document.getElementById("greenReadyFinal").style.color = "#ffffff"
      ///////////////////////////////////////////////////////////////////////////////////////////
      // Check both users touch and redirect them to start of the game
      if(player1_active == true && player2_active == true) {
        // Change the color
        document.getElementById("greenReadyFinal").style.color = "#11681F"
        document.getElementById("yellowReadyFinal").style.color = "#11681F"
        var outTimer = setTimeout(function() {
          clearTimeout(outTimer)
          player1_active = false
          player2_active = false
          document.getElementById("greenReadyFinal").style.color = "#ffffff"
          document.getElementById("yellowReadyFinal").style.color = "#ffffff"
          bothActive = false
          onGameRestart(event)
        }, 10)
      } else {
        document.getElementById("greenReadyFinal").style.color = "#ffffff"
      }
      ///////////////////////////////////////////////////////////////////////////////////////////
    } else {
      if(bothActive == false) {
        document.getElementById("greenReadyFinal").style.color = "#ffffff"
      } else {
        document.getElementById("greenReadyFinal").style.color = "#11681F"
      }
    }
  }
}
// YELLOW
function onFinalYellowTouch (event) {
  ///////////////////////////////////////////////////////////////////////////////////////////
  //event.preventDefault()
  if (event.cancelable) event.preventDefault();
  player1_active = true
  if(event.touches != undefined) {
    if(event.touches.length == 1) {
    }
  }
  ///////////////////////////////////////////////////////////////////////////////////////////
  // Change the color
  document.getElementById("yellowReadyFinal").style.color = "#bb0a0a"
  ///////////////////////////////////////////////////////////////////////////////////////////
  if(player1_active == true && player2_active == true && pause == false) {
    bothActive = true
    document.getElementById("greenReadyFinal").style.color = "#11681F"
    document.getElementById("yellowReadyFinal").style.color = "#11681F"
  }
}
function onFinalYellowEnd(event) {
  //event.preventDefault()
  if (event.cancelable) event.preventDefault();
  setTimeout(function() {
    if(bothActive == false) {
      player1_active = false
    }
  }, 0)

  if(event.touches != undefined) {
    if(event.touches.length == 0) {
      ///////////////////////////////////////////////////////////////////////////////////////////
      // Reset the color
      //document.getElementById("yellowReadyFinal").style.color = "#ffffff"
      ///////////////////////////////////////////////////////////////////////////////////////////
      // Check both users touch and redirect them to start of the game
      if(player1_active == true && player2_active == true) {
        // Change the color
        document.getElementById("greenReadyFinal").style.color = "#11681F"
        document.getElementById("yellowReadyFinal").style.color = "#11681F"
        var setoutTimer = setTimeout(function() {
          clearTimeout(setoutTimer)
          player1_active = false
          player2_active = false
          document.getElementById("greenReadyFinal").style.color = "#ffffff"
          document.getElementById("yellowReadyFinal").style.color = "#ffffff"
          bothActive = false
          onGameRestart(event)
        }, 10)
      } else {
        document.getElementById("yellowReadyFinal").style.color = "#FFFFFF"
      }
    } else {
      if(bothActive == false) {
        document.getElementById("yellowReadyFinal").style.color = "#ffffff"
      } else {
        document.getElementById("yellowReadyFinal").style.color = "#11681F"
      }
    }
  }
}




///////////////////////////////////////////////////////////////////////////////////////
// Previous RESTART CODE
/* function onFinalGreenTouch(event) {
  //event.preventDefault()
  if (event.cancelable) event.preventDefault();
  player2_active = true
  document.getElementById('greenReadyFinal').style.color = "#bb0a0a"
  if(event.touches != undefined) {
    if(event.touches.length == 1) {
      //document.getElementById('greenReadyFinal').style.color = "#bb0a0a"
      if(player1_active == true && player2_active == true) {
        // Change the color
        document.getElementById('greenReadyFinal').style.color = "#11681F"
        document.getElementById('yellowReadyFinal').style.color = "#11681F"
      }
    }
  }
}
//////////////////////////////////////////////////////////////////////////////////////////////
function onFinalGreenEnd(event) {
  //event.preventDefault()
  if (event.cancelable) event.preventDefault();
  setTimeout(function() {
    //if(player1_active == false) {
      player2_active = false
    //}
  }, 700)

  if(event.touches != undefined) {
    if(event.touches.length == 0) {
      //document.getElementById('greenReadyFinal').style.color = "#ffffff"
      if(player1_active == true && player2_active == true) {
        // Change the color
        document.getElementById('greenReadyFinal').style.color = "#11681F"
        document.getElementById('yellowReadyFinal').style.color = "#11681F"
        var setoutTimer = setTimeout(function() {
          clearTimeout(setoutTimer)
          player1_active = false
          player2_active = false
          // Restart the game
          var endTimer = setTimeout(function() {
            document.getElementById('greenReadyFinal').style.color = "#ffffff"
            document.getElementById('yellowReadyFinal').style.color = "#ffffff"
            clearTimeout(endTimer)
            onGameRestart(event)
          }, 100)
        }, 1000)
      } else {
        document.getElementById('greenReadyFinal').style.color = "#ffffff"
      }
    } else {
      document.getElementById('greenReadyFinal').style.color = "#ffffff"
    }
  }
}
//////////////////////////////////////////////////////////////////////////////////////////////
function onFinalYellowTouch(event) {
  //event.preventDefault()
  if (event.cancelable) event.preventDefault();

  player1_active = true
  document.getElementById('yellowReadyFinal').style.color = "#bb0a0a"
  if(event.touches != undefined) {
    if(event.touches.length == 1) {
      //document.getElementById('yellowReadyFinal').style.color = "#bb0a0a"
      if(player1_active == true && player2_active == true) {
        // Change the color
        document.getElementById('greenReadyFinal').style.color = "#11681F"
        document.getElementById('yellowReadyFinal').style.color = "#11681F"
      }
    }
  }
}
//////////////////////////////////////////////////////////////////////////////////////////////
function onFinalYellowEnd(event) {
  //event.preventDefault()
  if (event.cancelable) event.preventDefault();
  setTimeout(function() {
    //if(player2_active == false) {
      player1_active = false
    //}
  }, 700)

  if(event.touches != undefined) {
      //document.getElementById('yellowReadyFinal').style.color = "#ffffff"
      if(player1_active == true && player2_active == true) {
        // Change the color
        document.getElementById('greenReadyFinal').style.color = "#11681F"
        document.getElementById('yellowReadyFinal').style.color = "#11681F"
        var setoutTimer = setTimeout(function() {
          clearTimeout(setoutTimer)
          player1_active = false
          player2_active = false
          document.getElementById('greenReadyFinal').style.color = "#ffffff"
          document.getElementById('yellowReadyFinal').style.color = "#ffffff"
          // Restart the game
          var endTimer = setTimeout(function() {
            clearTimeout(endTimer)
            onGameRestart(event)
          }, 100)
        }, 1000)
      } else {
        document.getElementById('yellowReadyFinal').style.color = "#ffffff"
      }
    } else {
      document.getElementById('yellowReadyFinal').style.color = "#ffffff"
    }
} 
 */