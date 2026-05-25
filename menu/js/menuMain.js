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
  ////////////////////////////////
  // Get the deviceFPS
  getDeviceFPS();
  ////////////////////////////////
  //return;
  //////////////////////////////////////////////////////////////////////////////////////
  setTimeout(function() {
    if(window.navigator.onLine == false) {
      return
    } else {
      // Set buttons layer pos
      const detectDeviceType = () =>
        /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)
          ? 'Mobile'
          : 'Desktop';
        }
  }, 3000)
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