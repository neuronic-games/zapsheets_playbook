//////////////////////////////////////////////////////////////////////////////////
/**
 * 
 * @param {*} event 
 */
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
/**
 * 
 * @returns 
 */
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
}
/////////////////////////////////////////////////////////////////////////////////
/**
 * 
 */
$(document).ready(function() {
/* window.addEventListener('load', (event) => { */
  // Preload Images
  preloader();
  // Check the url param
  /* modeType = (getUrlVars()["mode"]) ? getUrlVars()["mode"].split('/')[0] : 0;
  //console.log(modeType, " >>>>>>>")
  if(modeType == 1) {
    document.getElementById('no_mode').style.display = 'none'
    document.getElementById('mode').style.display = 'block'
  } else {
    document.getElementById('no_mode').style.display = 'block'
    document.getElementById('mode').style.display = 'none'
  } */
  /////////////////////////////////////////////////////////////////////////////////
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
});

//////////////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 * @param {*} min 
 * @param {*} max 
 * @returns 
 */
function getRandomIntegerInclusive(min, max){
  min = Math.ceil(min)
  max = Math.floor(max)
  return Math.floor(Math.random() * (max - min + 1)) + min
}
//////////////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 */
function getDeviceFPS() {
  let prevTime = Date.now(),
  frames = 0;

  requestAnimationFrame(function loop() {
    const time = Date.now();
    frames++;
    if (time > prevTime + 1000) {
      let fps = Math.round( ( frames * 1000 ) / ( time - prevTime ) );
      prevTime = time;
      frames = 0;
      //console.info('FPS: ', fps);
      machineFPS = fps;
      cancelAnimationFrame(loop)
      return;
      //return fps
    }
    requestAnimationFrame(loop);
  });
}
///////////////////////////////////////////////////////////////////////////////////////////
/**
 * // Preload Images
 */
function preloader() {
		/* var img0 = new Image();
		var img1 = new Image();
		var img2 = new Image();
    var img3 = new Image();
		var img4 = new Image();
		var img5 = new Image();
    
		img0.src = imgList[0]
		img1.src = imgList[1]
		img2.src = imgList[2]
    img3.src = imgList[3]
		img4.src = imgList[4]
		img5.src = imgList[5] */
}

/////////////////////////////////////////////////////////////////////
/**
 * 
 */
function checkVersionStat() {

}
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
/////////////////////////////////////////////////////////////////////
/**
 * 
 */
/* window.addEventListener('load', (event) => { */
$(document).ready(function() {
  //console.log("LOADED - ", this)
  ///////////////////////////////////////////////////////////////////
  //console.log(navigator.language, " - AAAAA-- ")
  //document.getElementById("loadingText").innerHTML += 'App Version: ' + _version + "<br>";
  //document.getElementById('loadingScreen').style.display = 'none'
  document.getElementById('useMode').style.display = 'block'
  ////////////////////////////////
  // Get the deviceFPS
  getDeviceFPS();
  ////////////////////////////////
  //return;
  //////////////////////////////////////////////////////////////////////////////////////
  setTimeout(function() {
    // Hide preloading screen
    /* document.getElementById('useMode').style.display = 'none'
    document.getElementById('loadingScreen').style.display = 'none'
    $(".bodyWrapper.oddPage").css({display:"flex"})
    // Calling Periodically
    fetchAppDetailsPeriodically(event) */
    //fetchAppDetailsPeriodically(event)
    if(window.navigator.onLine == false) {
      return
    } else {
      /* document.getElementById('loadingScreen').style.display = 'none'
      checkingStepEnd() */


      // Set buttons layer pos
      const detectDeviceType = () =>
        /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)
          ? 'Mobile'
          : 'Desktop';
          if(detectDeviceType() == 'Desktop') {
            document.getElementById('bottomButtonLayer').style.height = '15vh; !important'
          } else {
            document.getElementById('bottomButtonLayer').style.height = '21vh; !important'
          }
        }
  }, 3000)
  /////////////////////////////////////////////////////////////////////////////////
  /**
   * 
   */
  function checkingStepEnd() {
    /* let stepTimer = setInterval(function() {
      //console.log('Callnng Timer')
      if(inSteps == false) {
        //console.log('TimerEnd')
        clearInterval(stepTimer)
        document.getElementById('useMode').style.display = 'none'
        //document.getElementById('loadingScreen').style.display = 'none'
        $(".bodyWrapper.oddPage").css({display:"flex"})
        // Calling Periodically
        fetchAppDetailsPeriodically(event)
      }
    }, 100) */
  }
  /////////////////////////////////////////////////////////////////////////////////
  var isToggle = false
  /* window.addEventListener('keydown', onKeyDownFunc)
  function onKeyDownFunc(event) {
    if(event.code == 'F3') {
      event.preventDefault();
    }
  }
  window.addEventListener('keyup', onKeyUpfunc)
  function onKeyUpfunc(event) {
    if(event.code == 'F3' && isToggle == false) {
      isToggle = true;
      showloader();
    } else if(event.code == 'F3' && isToggle == true) {
        isToggle = false;
        hideloader();
    }
  } */
  /////////////////////////////////////////////////////////////////////////////////
  /**
   * 
   */
  function showloader() {
    document.getElementById('loadingScreen').style.display = 'block'
  }
  /////////////////////////////////////////////////////////////////////////////////
  /**
   * 
   */
  function hideloader() {
    document.getElementById('loadingScreen').style.display = 'none'
  }
  ////////////////////////////////////////////////////////////////////////////////////
})

////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 */
function saveInfoToLog() {
  if(window.navigator.onLine == true) {
    deviceUID = md5(new DeviceUUID().get()).toString();
    // For Log system
    let currentDate = new Date();
    poll_time_string = moment(currentDate).format('MM/DD/YYYY HH:mm:ss').toLocaleString()

    document.getElementById("loadingText").innerHTML = 'App Version: ' + _version + '<br>';
    updateInfoTextView();

    document.getElementById("loadingText").innerHTML = 'Session Id: ' + deviceUID + '<br>';
    updateInfoTextView();

    document.getElementById("loadingText").innerHTML += "Checking server on " + moment(currentDate).format('YYYY/MM/DD HH:mm:ss') + "<br>"
    updateInfoTextView()
  }
}
////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 */
let portrait = window.matchMedia("(orientation: portrait)");
/* console.log(portrait.matches, " actual mode")
if(portrait.matches) {
  document.getElementById('useMode').style.display = 'none'
  document.getElementById('loadingScreen').style.display = 'flex'
} else {
  document.getElementById('useMode').style.display = 'flex'
  document.getElementById('modeMsg').innerHTML = "Portrait orientation is not supported When phone is rotated.<br>Rotate back to continue.."
  document.getElementById('modeMsg').style.display = 'block'
  document.getElementById('modeMsg').style.fontSize = '6vh'
  document.getElementById('modeLogo').style.width = '60vh'
  document.getElementById('loadingScreen').style.display = 'none'
} */
portrait.addEventListener("change", function(e) {
    if(e.matches) {
        //console.log("P mode")
        document.getElementById('useMode').style.display = 'none'
        pause = false;
    } else {
      //console.log("L mode")
      document.getElementById('useMode').style.display = 'flex'
      document.getElementById('modeMsg').style.display = 'block'
      document.getElementById('modeMsg').innerHTML = "Portrait orientation is not supported when phone is rotated.<br>Rotate back to continue.."
      document.getElementById('modeMsg').style.fontSize = '6vh'
      document.getElementById('modeLogo').style.width = '60vh'
      pause = true
    }
})
///////////////////////////////////////////////////////////////////////////