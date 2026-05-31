const fs = require('fs');
const path = require('path');

const jsFile = path.join(__dirname, 'modules/chat/chat.js');
let js = fs.readFileSync(jsFile, 'utf8');

// 1. In renderMessages, move ${reactionsHtml} into the bubble
const searchRender = `                            `}
                        </div>
                        ${reactionsHtml}
                    <div class="msg-quick-actions">`;

const replaceRender = `                            `}
                            ${reactionsHtml}
                        </div>
                    <div class="msg-quick-actions">`;

if (js.includes(searchRender)) {
    js = js.replace(searchRender, replaceRender);
} else {
    // try alternative spacing
    const altSearch = `                            \`}
                        </div>
                        \${reactionsHtml}`;
    const altReplace = `                            \`}
                            \${reactionsHtml}
                        </div>`;
    js = js.replace(altSearch, altReplace);
}

// 2. In updateReactionsUI, append to .msg-bubble instead of wrap
const searchUpdate = `        // Remove old reactions
        const oldList = wrap.querySelector('.msg-reactions-list');
        if (oldList) oldList.remove();

        // Build new reactions HTML
        if (!reactions || reactions.length === 0) return;

        let html = '<div class="msg-reactions-list">';
        reactions.forEach(r => {
            const isVoted = myReactions && myReactions.includes(r.emoji);
            html += \`<div class="msg-reaction-badge \${isVoted ? 'voted' : ''}" data-msg-id="\${msgId}" data-emoji="\${r.emoji}">
                <span>\${r.emoji}</span><span>\${r.count}</span>
            </div>\`;
        });
        html += '</div>';

        wrap.insertAdjacentHTML('beforeend', html);`;

const replaceUpdate = `        // Remove old reactions
        const oldList = wrap.querySelector('.msg-reactions-list');
        if (oldList) oldList.remove();

        // Build new reactions HTML
        if (!reactions || reactions.length === 0) return;

        let html = '<div class="msg-reactions-list">';
        reactions.forEach(r => {
            const isVoted = myReactions && myReactions.includes(r.emoji);
            html += \`<div class="msg-reaction-badge \${isVoted ? 'voted' : ''}" data-msg-id="\${msgId}" data-emoji="\${r.emoji}">
                <span>\${r.emoji}</span><span>\${r.count}</span>
            </div>\`;
        });
        html += '</div>';

        const bubble = wrap.querySelector('.msg-bubble');
        if (bubble) bubble.insertAdjacentHTML('beforeend', html);
        else wrap.insertAdjacentHTML('beforeend', html);`;

if (js.includes(searchUpdate)) {
    js = js.replace(searchUpdate, replaceUpdate);
}

fs.writeFileSync(jsFile, js);

// 3. Update CSS
const cssFile = path.join(__dirname, 'modules/chat/chat.css');
let css = fs.readFileSync(cssFile, 'utf8');

const cssSearchOwnList = `.msg-bubble-wrap.own .msg-reactions-list {
    right: auto;
    left: -4px;
}`;

const cssReplaceOwnList = `.msg-bubble-wrap.own .msg-reactions-list {
    right: auto;
    left: 4px;
}`;

if (css.includes(cssSearchOwnList)) {
    css = css.replace(cssSearchOwnList, cssReplaceOwnList);
}

const cssSearchWrapHas = `.msg-bubble-wrap:has(.msg-reactions-list) {
    margin-bottom: 12px;
}`;

const cssReplaceWrapHas = `.msg-bubble-wrap:has(.msg-reactions-list) {
    margin-bottom: 0px;
}
.msg-bubble:has(.msg-reactions-list) {
    margin-bottom: 12px;
}`;

if (css.includes(cssSearchWrapHas)) {
    css = css.replace(cssSearchWrapHas, cssReplaceWrapHas);
} else if (!css.includes('.msg-bubble:has(.msg-reactions-list)')) {
    css += `\n.msg-bubble:has(.msg-reactions-list) { margin-bottom: 12px; }\n`;
}

// Ensure .msg-bubble is relative
if (!css.includes('position: relative;') && css.includes('.msg-bubble {') ) {
   // it usually is, but just checking
}

fs.writeFileSync(cssFile, css);
console.log("Fixed reaction position!");
