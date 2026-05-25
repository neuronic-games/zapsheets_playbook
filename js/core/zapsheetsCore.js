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