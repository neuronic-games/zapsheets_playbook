/////////////////////////////////////////////////////////////////////////////
/**
 * 
 * @param {*} text 
 * @param {*} elment 
 * @param {*} fSize 
 */
function adjustFontSize(text, elment, fSize) {
    elment.innerHTML = text;
    elment.style.whiteSpace = 'nowrap';
    elment.style.opacity = '0';
    elment.style.fontSize = fSize + 'vw'; // default font size
    let fontSize = fSize;
    
    setTimeout(function() {
        while (elment.offsetWidth > (elment.parentElement.offsetWidth)) {
            fontSize--;
            elment.style.fontSize = fontSize + 'vw';
        }
        elment.style.opacity = '1';
    }, 2500)
}
/////////////////////////////////////////////////////////////////////////////
/**
 * 
 * @param {*} text 
 * @param {*} elment 
 * @param {*} fSize 
 */
function adjustFontSizeInBreak(text, elment, fSize) {
    elment.innerHTML = text;
    elment.style.opacity = '0';
    elment.style.fontSize = fSize + 'vw'; // default font size
    let fontSize = fSize;
    
    setTimeout(function() {
        while (elment.offsetWidth > (elment.parentElement.offsetWidth)) {
            fontSize--;
            elment.style.fontSize = fontSize + 'vw';
        }
        elment.style.opacity = '1';
    }, 2500)
}
/////////////////////////////////////////////////////////////////////////////
/**
 * 
 * @param {*} text 
 * @param {*} tempElement 
 * @param {*} maxLines 
 * @param {*} type 
 */
function adjustFontSizeMultiple(text, tempElement, maxLines, type) {
    tempElement.style.visibility = 'hidden';
    tempElement.style.lineHeight = '1'; // Adjust based on your font
    tempElement.style.whiteSpace = 'normal'; // Allow text wrapping
    tempElement.textContent = text;
    let fontSize = 12; // Initial font size
    if(type == 'learnPlay') {
        if(text.length > 18) {
            fontSize = 5
        } else {
            fontSize = 6
        }
    } else if(type == 'showScore') {
        fontSize = 6
    }
    tempElement.style.fontSize = fontSize + 'vw';
    // Estimate lines by dividing element height by line height
    function getLineCount() {
        const lineHeight = parseFloat(getComputedStyle(tempElement).lineHeight);
        const elementHeight = tempElement.offsetHeight;
        return Math.round(elementHeight / lineHeight);
    };
    setTimeout(function() {
        // Reduce font size until text fits within maxLines and containerWidth
        while ((getLineCount() > maxLines || tempElement.scrollWidth > tempElement.parentElement.offsetWidth) && fontSize > 3) {
            fontSize--;
            tempElement.style.fontSize = fontSize + 'vw';
        }
        tempElement.style.visibility = 'visible';
    }, 10)
}
/////////////////////////////////////////////////////////////////////////////
/**
 * Scroll the loading log element to the bottom.
 */
function updateInfoTextView() {
    var el = document.getElementById('loadingText');
    if (el) el.scrollTop += 100;
}
/////////////////////////////////////////////////////////////////////////////
/**
 * Append a message to the loading log and auto-scroll to the bottom.
 * @param {string} msg - HTML string to append (may include <font>, <br>, etc.)
 */
function logLoadMsg(msg) {
    var el = document.getElementById('loadingText');
    if (!el) return;
    el.innerHTML += msg;
    el.scrollTop = el.scrollHeight;
}
/////////////////////////////////////////////////////////////////////////////
// Shared tag-image map — populated by loadTagsData(), consumed by applyTagReplacements()
var tagImageMap = {};
/////////////////////////////////////////////////////////////////////////////
/**
 * Replace all [TAG_NAME] tokens in text with their cached <img> elements.
 * Any tag present in tagImageMap is substituted; unknown tags are left as-is.
 * @param {string} text      - source text that may contain [TAG_NAME] tokens
 * @param {string} cssClass  - CSS class to apply to each <img>
 * @returns {string}
 */
function applyTagReplacements(text, cssClass) {
    if (!text) return text;
    return text.replace(/\[([A-Z0-9_]+)\]/g, function(match) {
        var imgPath = tagImageMap[match];
        if (imgPath) {
            return '<img class="' + cssClass + '" src="' + imgPath + '" loading="lazy">';
        }
        return match; // leave unknown tags as-is
    });
}
/////////////////////////////////////////////////////////////////////////////
/**
 * Load tags.json for the current sheet and populate tagImageMap dynamically.
 * Any [TAG_NAME] row in the sheet is supported — no hardcoded tag names.
 * @param {Function} [callback] - optional function to call when loading succeeds
 */
function loadTagsData(callback) {
    setTimeout(function() {
        var tagRequest = $.ajax({
            url: jasonPath + 'sheets/' + sheet_Id + '/tags.json?version=' + UIVersion,
            cache: true,
            type: 'GET',
            dataType: 'text',
            success: function(response) {
                if (response.length == 0) {
                    logLoadMsg('<font color="red">Error: Tags data not available.</font><br>');
                } else {
                    tagsDataList = [];
                    var mResponseSet = response.replace(/�/g, '');
                    var newTagData = eval(mResponseSet);
                    for (var i = 0; i < newTagData.length; i++) {
                        var rowStr = JSON.stringify(newTagData[i]);
                        if (isJSONData(rowStr) == false) {
                            logLoadMsg('<font color="red">Error: Tags Sheet : (Row: ' + i + ')</font><br>');
                        } else {
                            tagsDataList[i] = isJSONData(rowStr);
                        }
                    }
                    // Build tagImageMap — every [TAG_NAME] row becomes an entry
                    tagImageMap = {};
                    $.each(tagsDataList, function(idx, row) {
                        var tagName = row['Name'];   // e.g. '[BUG]', '[BUG_BOTTOM]'
                        var tagValue = row['Value'];
                        if (!tagName || !tagValue) return;
                        var imgPath = '';
                        if (tagValue.includes('https://drive.google.com')) {
                            var imgid = tagValue.split('https://drive.google.com')[1].split('/')[3];
                            imgPath = '../sheets/' + sheet_Id + '/cacheImages/' + imgid + '.png?version=' + UIVersion;
                        } else {
                            var parts = tagValue.split('/');
                            var imageName = parts[parts.length - 1].indexOf('?') !== -1
                                ? parts[parts.length - 1].split('?')[0]
                                : parts[parts.length - 1];
                            imgPath = '../sheets/' + sheet_Id + '/cacheImages/' + imageName + '?version=' + UIVersion;
                        }
                        tagImageMap[tagName] = imgPath;
                    });
                    if (typeof callback === 'function') callback();
                }
            },
            error: function() {
                logLoadMsg('<font color="red">Error: Missing Sheet : Tags</font><br>');
                var spinner = document.getElementById('spinnerBox');
                if (spinner) spinner.style.display = 'none';
            }
        });
        tagRequest.onreadystatechange = null;
        tagRequest.abort = null;
        tagRequest = null;
    }, 1000);
}