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
    }, 10)
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
        console.log(elment.offsetWidth , '>', elment.parentElement.offsetWidth)
        while (elment.offsetWidth > (elment.parentElement.offsetWidth)) {
            fontSize--;
            elment.style.fontSize = fontSize + 'vw';
        }
        elment.style.opacity = '1';
    }, 10)
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
    } else if(type == 'playScore') {
        if(text.length > 12) {
            fontSize = 10
        } else {
            fontSize = 12
        }
        tempElement.style.lineHeight = '1.2'; 
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
 * 
 * @param {*} text 
 * @param {*} formatString 
 * @returns 
 */
function formatSheetIDValues(text, formatString, index) {
    let formattedText = []
    if(text.indexOf('[') != -1 || text.indexOf(']') != -1) {
        formattedText = ((text.split('['+formatString+']')[index]) || (text.split(' [ '+formatString+' ] ')[index]) || (text.split('[ '+formatString+' ]')[index]) || (text.split('['+formatString+' ]')[index]) || (text.split('[ '+formatString+'] ')[index]) || (text.split('[ '+formatString+']')[index]) || (text.split('[ '+formatString+' ]')[index]) || (text.split(' '+formatString+' ]')[index]) || (text.split(''+formatString+']')[index]) || (text.split('['+formatString+'')[index]) || (text.split(' [ '+formatString+' ')[index]) || (text.split('['+formatString+'')[index]) || (text.split('['+formatString+' ')[index]) || (text.split('[ '+formatString+'')[index]) || (text.split(''+formatString+' ]')[index])).trim();
    } else {
        formattedText = (text.split(''+formatString+'')[index]).trim();
    }
    return formattedText;
}
//////////////////////////////////////////////////////////////////////////////////
/**
 * 
 * @param {*} text 
 * @param {*} formatString 
 * @returns 
 */
function formatSheetIDToList(text, formatString) {
    let formatList = []
    if(text.indexOf('[') != -1 || text.indexOf(']') != -1) {
        formatList = ((text.split('['+formatString+']')) || (text.split(' [ '+formatString+' ] ')) || (text.split('[ '+formatString+' ]')) || (text.split('['+formatString+' ]')) || (text.split('[ '+formatString+'] ')) || (text.split('[ '+formatString+']')) || (text.split('[ '+formatString+' ]')) || (text.split(' '+formatString+' ]')) || (text.split(''+formatString+']')) || (text.split('['+formatString+'')) || (text.split(' [ '+formatString+' ')) || (text.split('['+formatString+'')) || (text.split('['+formatString+' ')) || (text.split('[ '+formatString+'')) || (text.split(''+formatString+' ]')));
    } else {
        formatList = (text.split(''+formatString+''));
    }
    return formatList;
}