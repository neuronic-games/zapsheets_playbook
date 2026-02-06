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
  // Preload Images
  preloader();
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
      machineFPS = fps;
      cancelAnimationFrame(loop)
      return;
    }
    requestAnimationFrame(loop);
  });
}
///////////////////////////////////////////////////////////////////////////////////////////
/**
 * // Preload Images
 */
function preloader() {
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
$(document).ready(function() {
  document.getElementById('useMode').style.display = 'block'
  // Get the deviceFPS
  getDeviceFPS();
  setTimeout(function() {
    if(window.navigator.onLine == false) {
      return
    } else {
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
  var isToggle = false
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
/**
 * 
 */
let portrait = window.matchMedia("(orientation: portrait)");

portrait.addEventListener("change", function(e) {
  if(DetectSpecificDevice() == 'desktop') {
    document.getElementById('useMode').style.display = 'none'
    if(window.orientation != 0) {
        pause = false;
        document.getElementById('useModeBG').style.display = 'none';
    }
    return
  }
  if(e.matches) {
      document.getElementById('useMode').style.display = 'none'
      pause = false;
  } else {
    document.getElementById('useMode').style.display = 'flex'
    document.getElementById('modeMsg').style.display = 'block'
    document.getElementById('modeMsg').innerHTML = "Portrait orientation is not supported when phone is rotated.<br>Rotate back to continue.."
    document.getElementById('modeMsg').style.fontSize = '6vh'
    document.getElementById('modeLogo').style.width = '60vh'
    pause = true
  }
})
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
///////////////////////////////////////////////////////////////////////////
