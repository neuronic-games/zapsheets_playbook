/////////////////////////////////////////////////////////////////////////////////
// Get current json version
/////////////////////////////////////////////////////////////////////////////////
/**
 * 
 * @returns 
 */
function getBrowerType() {
  let browserType = ''
  if ((navigator.userAgent.indexOf("Opera") || navigator.userAgent.indexOf('OPR')) != -1) {
    browserType = 'Opera';
  } else if (navigator.userAgent.indexOf("Edg") != -1) {
    browserType = 'Edge';
  } else if (navigator.userAgent.indexOf("Chrome") != -1) {
    browserType = 'Chrome';
  } else if (navigator.userAgent.indexOf("Safari") != -1) {
    browserType = 'Safari';
  } else if (navigator.userAgent.indexOf("Firefox") != -1) {
    browserType = 'Firefox';
  } else if ((navigator.userAgent.indexOf("MSIE") != -1) || (!!document.documentMode == true)) //IF IE > 10
  {
    browserType = 'IE';
  } else {
    browserType = 'unknown';
  }
  return browserType;
}
//////////////////////////////////////////////////////////////////////////////////
/**
 * Document READY event
 */
$(document).ready(function() {
  portrait.addEventListener("change", function(e) {
    if(e.matches) {
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
  })
})
//////////////////////////////////////////////////////////////////////////////////

