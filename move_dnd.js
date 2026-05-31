const fs = require('fs');
const path = require('path');

const jsFile = path.join(__dirname, 'modules/chat/chat.js');
let js = fs.readFileSync(jsFile, 'utf8');

// The drag & drop block currently at the end of the file
const dragDropBlockStart = "// Prevent default drag behaviors globally";
const deleteMsgStart = "async function deleteMessage(msgId) {";

// Find where drag & drop starts
const dragIndex = js.indexOf(dragDropBlockStart);
if (dragIndex !== -1) {
    const dragEndIndex = js.indexOf(deleteMsgStart);
    if (dragEndIndex !== -1) {
        const dragCode = js.substring(dragIndex, dragEndIndex).trim();
        
        // Remove from the end
        js = js.substring(0, dragIndex) + "\n\n" + js.substring(dragEndIndex);
        
        // Insert before })();
        const iifeEnd = "})();";
        const iifeIndex = js.lastIndexOf(iifeEnd);
        if (iifeIndex !== -1) {
            // Because $ is defined inside the IIFE, we can optionally revert document.getElementById back to $, 
            // but document.getElementById is fine. We just need selectedFiles to be in scope.
            js = js.substring(0, iifeIndex) + dragCode + "\n\n" + js.substring(iifeIndex);
            
            fs.writeFileSync(jsFile, js);
            console.log("Drag & Drop logic moved inside IIFE.");
        } else {
            console.log("Could not find IIFE end.");
        }
    } else {
        console.log("Could not find deleteMsgStart.");
    }
} else {
    console.log("Could not find dragDropBlockStart.");
}
