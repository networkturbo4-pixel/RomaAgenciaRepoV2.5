const fs = require('fs');
const path = require('path');

const jsFile = path.join(__dirname, 'modules/chat/chat.js');
let js = fs.readFileSync(jsFile, 'utf8');

const target1 = "if ($('btn-group-info')) $('btn-group-info').addEventListener('click', openInfoPanel);";
const repl1 = "if (document.getElementById('btn-group-info')) document.getElementById('btn-group-info').addEventListener('click', openInfoPanel);";

const target2 = "if ($('btn-close-info')) $('btn-close-info').addEventListener('click', closeInfoPanel);";
const repl2 = "if (document.getElementById('btn-close-info')) document.getElementById('btn-close-info').addEventListener('click', closeInfoPanel);";

const target3 = "const chatMsgs = $('chat-messages');";
const repl3 = "const chatMsgs = document.getElementById('chat-messages');";

js = js.replace(target1, repl1);
js = js.replace(target2, repl2);
js = js.replace(target3, repl3);

// Also inside openInfoPanel
const target4 = "const panel = $('chat-info-panel');";
const repl4 = "const panel = document.getElementById('chat-info-panel');";
js = js.replace(target4, repl4);

js = js.replace("$('chat-channel-name')", "document.getElementById('chat-channel-name')");
js = js.replace("$('info-panel-name')", "document.getElementById('info-panel-name')");
js = js.replace("$('chat-channel-meta')", "document.getElementById('chat-channel-meta')");
js = js.replace("$('info-panel-desc')", "document.getElementById('info-panel-desc')");
js = js.replace("$('info-panel-icon')", "document.getElementById('info-panel-icon')");
js = js.replace("$('info-panel-icon')", "document.getElementById('info-panel-icon')"); // multiple
js = js.replace("$('info-panel-icon')", "document.getElementById('info-panel-icon')");
js = js.replace("$('info-panel-icon')", "document.getElementById('info-panel-icon')");

js = js.replace("$('info-media-count')", "document.getElementById('info-media-count')");
js = js.replace("$('info-media-grid')", "document.getElementById('info-media-grid')");
js = js.replace("$('info-docs-count')", "document.getElementById('info-docs-count')");
js = js.replace("$('info-docs-list')", "document.getElementById('info-docs-list')");
js = js.replace("$('info-links-count')", "document.getElementById('info-links-count')");
js = js.replace("$('info-links-list')", "document.getElementById('info-links-list')");
js = js.replace("$('info-pinned-count')", "document.getElementById('info-pinned-count')");
js = js.replace("$('info-pinned-list')", "document.getElementById('info-pinned-list')");
js = js.replace("$('info-members-count')", "document.getElementById('info-members-count')");
js = js.replace("$('info-members-list')", "document.getElementById('info-members-list')");

js = js.replace("$('chat-info-panel')", "document.getElementById('chat-info-panel')");
js = js.replace("$('image-send-preview')", "document.getElementById('image-send-preview')");
js = js.replace("$('image-send-modal')", "document.getElementById('image-send-modal')");

fs.writeFileSync(jsFile, js);
console.log('Fixed $ undefined');
