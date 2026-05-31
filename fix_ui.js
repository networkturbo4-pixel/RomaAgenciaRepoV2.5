const fs = require('fs');
const path = require('path');

const jsFile = path.join(__dirname, 'modules/chat/chat.js');
let js = fs.readFileSync(jsFile, 'utf8');

// 1. Fix Drag and Drop globally
const dragFix = `
// Prevent default drag behaviors globally to stop browser from opening files
['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    document.addEventListener(eventName, preventDefaults, false);
});
function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}
`;
if (!js.includes("['dragenter', 'dragover', 'dragleave', 'drop']")) {
    js = js.replace('// Drag & Drop for file attachments', dragFix + '\n// Drag & Drop for file attachments');
}

// 2. Fix Pin Modal Design
const oldPinModal = `Swal.fire({
                        title: 'Fijar mensaje',
                        text: '¿Por cuánto tiempo deseas fijarlo?',
                        input: 'select',
                        inputOptions: {
                            '1h': '1 hora',
                            '6h': '6 horas',
                            '24h': '24 horas',
                            '7d': '7 días',
                            'permanent': 'Permanente'
                        },
                        inputValue: 'permanent',
                        showCancelButton: true,
                        confirmButtonText: 'Fijar',
                        cancelButtonText: 'Cancelar'
                    })`;

const newPinModal = `Swal.fire({
                        title: 'Selecciona por cuánto tiempo quieres fijar el mensaje',
                        html: \`
                            <p style="text-align: left; color: #a0aeb6; font-size: 0.95rem; margin-bottom: 1.5rem; margin-top: 0;">Puedes desfijarlo en cualquier momento.</p>
                            <div class="pin-options" style="text-align: left; display: flex; flex-direction: column; gap: 1rem;">
                                <label class="pin-radio" style="display: flex; align-items: center; gap: 1rem; cursor: pointer;">
                                    <input type="radio" name="pin_dur" value="24h" style="width: 20px; height: 20px; accent-color: #25d366;">
                                    <span style="color: #e9edef; font-size: 1rem;">24 horas</span>
                                </label>
                                <label class="pin-radio" style="display: flex; align-items: center; gap: 1rem; cursor: pointer;">
                                    <input type="radio" name="pin_dur" value="7d" checked style="width: 20px; height: 20px; accent-color: #25d366;">
                                    <span style="color: #e9edef; font-size: 1rem;">7 días</span>
                                </label>
                                <label class="pin-radio" style="display: flex; align-items: center; gap: 1rem; cursor: pointer;">
                                    <input type="radio" name="pin_dur" value="30d" style="width: 20px; height: 20px; accent-color: #25d366;">
                                    <span style="color: #e9edef; font-size: 1rem;">30 días</span>
                                </label>
                            </div>
                        \`,
                        background: '#1f2c34', // WhatsApp dark bg
                        color: '#e9edef',
                        showCancelButton: true,
                        confirmButtonText: 'Fijar',
                        cancelButtonText: 'Cancelar',
                        buttonsStyling: false,
                        customClass: {
                            confirmButton: 'wa-btn wa-btn-primary',
                            cancelButton: 'wa-btn wa-btn-cancel',
                            popup: 'wa-popup',
                            title: 'wa-title'
                        },
                        preConfirm: () => {
                            const checked = document.querySelector('input[name="pin_dur"]:checked');
                            return checked ? checked.value : '7d';
                        }
                    })`;

js = js.replace(oldPinModal, newPinModal);
fs.writeFileSync(jsFile, js);

// 3. Pinned Message UI
const cssFile = path.join(__dirname, 'modules/chat/chat.css');
let css = fs.readFileSync(cssFile, 'utf8');

const bannerCss = `
/* Pinned Messages Banner (WhatsApp Style) */
.chat-pinned-bar {
    display: flex;
    align-items: center;
    background: var(--bg-surface);
    padding: 0.6rem 1rem;
    cursor: pointer;
    border-bottom: 1px solid var(--border-color);
    position: relative;
    gap: 0.8rem;
    transition: background 0.2s;
}
.chat-pinned-bar:hover { background: rgba(0,0,0,0.02); }
html.dark .chat-pinned-bar:hover, [data-theme='dark'] .chat-pinned-bar:hover { background: rgba(255,255,255,0.04); }

/* The left gray line indicator */
.chat-pinned-bar::before {
    content: '';
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    width: 3px;
    height: 60%;
    background: #8696a0; /* WhatsApp gray */
    border-radius: 4px;
}

/* Ensure content is pushed past the line */
.chat-pinned-bar .ph-push-pin {
    margin-left: 10px;
    color: #8696a0;
    font-size: 1.1rem;
}

.chat-pinned-bar #pinned-bar-text {
    color: var(--text-main);
    font-size: 0.9rem;
    font-weight: 400;
}

/* WhatsApp-style SweetAlert Buttons */
.wa-btn {
    padding: 0.6rem 1.5rem;
    border-radius: 24px;
    font-weight: 500;
    font-size: 0.95rem;
    border: none;
    cursor: pointer;
    transition: filter 0.2s;
}
.wa-btn:hover { filter: brightness(0.9); }
.wa-btn-primary { background: #00a884; color: #111b21; }
.wa-btn-cancel { background: transparent; color: #00a884; }
.wa-popup { border-radius: 12px; }
.wa-title { font-size: 1.1rem; text-align: left; }
`;

if (!css.includes('.wa-btn-primary')) {
    fs.appendFileSync(cssFile, bannerCss);
}

console.log('UI Fixes Applied');
