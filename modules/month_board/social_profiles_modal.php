<!-- Modal Vista Previa de Perfiles Sociales — Redesign Premium -->
<div class="modal-overlay" id="socialProfilesPresentationModal" style="z-index: 1080; background: rgba(0,0,0,0);">
    <div class="spp-modal-wrap" id="sppModalWrap">
        
        <!-- Header -->
        <div class="spp-header">
            <div class="spp-header-left">
                <div class="spp-header-icon"><i class="ph ph-monitor"></i></div>
                <div>
                    <h3 class="spp-header-title">Vista Previa Social</h3>
                    <p class="spp-header-sub">Visualiza cómo se verá tu marca en redes</p>
                </div>
            </div>
            <div class="spp-header-right">
                <button class="spp-btn-save" onclick="saveSocialProfiles()">
                    <i class="ph ph-cloud-arrow-up"></i> Guardar
                </button>
                <button class="spp-btn-close" onclick="closeSocialProfilesModal()">
                    <i class="ph ph-x"></i>
                </button>
            </div>
        </div>

        <!-- Tab Bar -->
        <div class="spp-tabs-bar">
            <button class="spp-tab active" onclick="switchSPTab('facebook')" id="spp-tab-btn-facebook" data-platform="facebook">
                <i class="ph-fill ph-facebook-logo"></i> <span>Facebook</span>
            </button>
            <label class="spp-pres-toggle" title="Mostrar en Presentación">
                <input type="checkbox" id="spp-toggle-facebook" onchange="toggleSocialSlideInPresentation('facebook', this.checked)">
                <span class="spp-toggle-slider"></span>
                <i class="ph ph-presentation-chart spp-toggle-icon"></i>
            </label>

            <button class="spp-tab" onclick="switchSPTab('instagram')" id="spp-tab-btn-instagram" data-platform="instagram">
                <i class="ph-fill ph-instagram-logo"></i> <span>Instagram</span>
            </button>
            <label class="spp-pres-toggle" title="Mostrar en Presentación">
                <input type="checkbox" id="spp-toggle-instagram" onchange="toggleSocialSlideInPresentation('instagram', this.checked)">
                <span class="spp-toggle-slider"></span>
                <i class="ph ph-presentation-chart spp-toggle-icon"></i>
            </label>

            <button class="spp-tab" onclick="switchSPTab('tiktok')" id="spp-tab-btn-tiktok" data-platform="tiktok">
                <i class="ph-fill ph-tiktok-logo"></i> <span>TikTok</span>
            </button>
            <label class="spp-pres-toggle" title="Mostrar en Presentación">
                <input type="checkbox" id="spp-toggle-tiktok" onchange="toggleSocialSlideInPresentation('tiktok', this.checked)">
                <span class="spp-toggle-slider"></span>
                <i class="ph ph-presentation-chart spp-toggle-icon"></i>
            </label>

            <div class="spp-tab-indicator" id="sppTabIndicator"></div>
        </div>

        <!-- Body -->
        <div class="spp-body">

            <!-- ========== FACEBOOK ========== -->
            <div id="spp-mockup-facebook" class="spp-mockup spp-mockup-active">
                <div class="spp-fb-card">
                    <!-- Cover -->
                    <div class="spp-fb-cover" onclick="document.getElementById('spp-fb-cover-input').click()">
                        <img id="spp-fb-cover-img" src="" class="spp-fb-cover-img">
                        <div class="spp-upload-overlay"><i class="ph ph-camera"></i> Cambiar portada</div>
                        <input type="file" id="spp-fb-cover-input" accept="image/*" style="display:none;" onchange="loadSPPImage(this, 'spp-fb-cover-img')">
                    </div>
                    <!-- Profile Section -->
                    <div class="spp-fb-profile-section">
                        <div class="spp-fb-avatar" onclick="document.getElementById('spp-fb-logo-input').click()">
                            <img id="spp-fb-logo-img" src="" class="spp-fb-avatar-img">
                            <div class="spp-upload-overlay spp-upload-overlay-round"><i class="ph ph-camera"></i></div>
                            <input type="file" id="spp-fb-logo-input" accept="image/*" style="display:none;" onchange="loadSPPImage(this, 'spp-fb-logo-img')">
                        </div>
                        <div class="spp-fb-info">
                            <h2 contenteditable="true" id="spp-fb-name" class="spp-editable spp-fb-name">Nombre de Página</h2>
                            <p contenteditable="true" id="spp-fb-followers" class="spp-editable spp-fb-followers">10 mil seguidores • 120 seguidos</p>
                        </div>
                        <div class="spp-fb-actions">
                            <button class="spp-fb-btn spp-fb-btn-primary"><i class="ph ph-thumbs-up"></i> Me gusta</button>
                            <button class="spp-fb-btn spp-fb-btn-secondary"><i class="ph ph-messenger-logo"></i> Mensaje</button>
                        </div>
                    </div>
                    <!-- Nav -->
                    <div class="spp-fb-nav">
                        <div class="spp-fb-nav-item active">Publicaciones</div>
                        <div class="spp-fb-nav-item">Información</div>
                        <div class="spp-fb-nav-item">Menciones</div>
                        <div class="spp-fb-nav-item">Fotos</div>
                        <div class="spp-fb-nav-item">Seguidores</div>
                    </div>
                    <!-- Posts Area -->
                    <div class="spp-fb-feed">
                        <div class="spp-fb-post-card" onclick="document.getElementById('spp-fb-posts-input').click()">
                            <img id="spp-fb-posts-img" src="" class="spp-fb-post-img">
                            <div class="spp-upload-overlay spp-upload-overlay-square">
                                <i class="ph ph-image" style="font-size:2.5rem; margin-bottom: 0.5rem;"></i>
                                <span>Subir imagen de publicación</span>
                            </div>
                            <input type="file" id="spp-fb-posts-input" accept="image/*" style="display:none;" onchange="loadSPPImage(this, 'spp-fb-posts-img')">
                            <span id="spp-fb-posts-hint" class="spp-placeholder-hint">
                                <i class="ph ph-image"></i> Subir imagen (Posts)
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========== INSTAGRAM ========== -->
            <div id="spp-mockup-instagram" class="spp-mockup">
                <div class="spp-phone-frame">
                    <div class="spp-phone-notch"></div>
                    <div class="spp-phone-screen spp-ig-screen">
                        <!-- IG Header -->
                        <div class="spp-ig-header">
                            <i class="ph ph-lock" style="font-size: 0.8rem;"></i>
                            <div contenteditable="true" id="spp-ig-username" class="spp-editable spp-ig-username-text">usuario_ig</div>
                            <i class="ph ph-caret-down" style="font-size: 0.8rem;"></i>
                        </div>
                        <!-- Profile Row -->
                        <div class="spp-ig-profile-row">
                            <div class="spp-ig-avatar-wrap" onclick="document.getElementById('spp-ig-logo-input').click()">
                                <div class="spp-ig-avatar-ring">
                                    <img id="spp-ig-logo-img" src="" class="spp-ig-avatar-img">
                                    <div class="spp-upload-overlay spp-upload-overlay-round"><i class="ph ph-camera"></i></div>
                                    <input type="file" id="spp-ig-logo-input" accept="image/*" style="display:none;" onchange="loadSPPImage(this, 'spp-ig-logo-img')">
                                </div>
                            </div>
                            <div class="spp-ig-stats">
                                <div class="spp-ig-stat"><span class="spp-ig-stat-num">120</span><span class="spp-ig-stat-label">publicaciones</span></div>
                                <div class="spp-ig-stat"><span class="spp-ig-stat-num">10.5K</span><span class="spp-ig-stat-label">seguidores</span></div>
                                <div class="spp-ig-stat"><span class="spp-ig-stat-num">300</span><span class="spp-ig-stat-label">seguidos</span></div>
                            </div>
                        </div>
                        <!-- Bio -->
                        <div class="spp-ig-bio-section">
                            <div contenteditable="true" id="spp-ig-name" class="spp-editable spp-ig-bio-name">Nombre Real</div>
                            <div contenteditable="true" id="spp-ig-bio" class="spp-editable spp-ig-bio-text">Descripción de la biografía.<br>Puede tener múltiples líneas.<br>✨ Link aquí</div>
                            <div class="spp-ig-bio-link">linktr.ee/usuario</div>
                        </div>
                        <!-- Action Buttons -->
                        <div class="spp-ig-action-btns">
                            <button class="spp-ig-btn-follow">Seguir</button>
                            <button class="spp-ig-btn-msg">Mensaje</button>
                            <button class="spp-ig-btn-contact">Contacto</button>
                        </div>
                        <!-- Highlights -->
                        <div class="spp-ig-highlights">
                            <div class="spp-ig-hl" onclick="document.getElementById('spp-ig-hl1-input').click()">
                                <div class="spp-ig-hl-circle">
                                    <img id="spp-ig-hl1-img" src="" class="spp-ig-hl-img">
                                    <input type="file" id="spp-ig-hl1-input" accept="image/*" style="display:none;" onchange="loadSPPImage(this, 'spp-ig-hl1-img')">
                                </div>
                                <div contenteditable="true" id="spp-ig-hl1-text" class="spp-editable spp-ig-hl-label">Story 1</div>
                            </div>
                            <div class="spp-ig-hl" onclick="document.getElementById('spp-ig-hl2-input').click()">
                                <div class="spp-ig-hl-circle">
                                    <img id="spp-ig-hl2-img" src="" class="spp-ig-hl-img">
                                    <input type="file" id="spp-ig-hl2-input" accept="image/*" style="display:none;" onchange="loadSPPImage(this, 'spp-ig-hl2-img')">
                                </div>
                                <div contenteditable="true" id="spp-ig-hl2-text" class="spp-editable spp-ig-hl-label">Story 2</div>
                            </div>
                            <div class="spp-ig-hl" onclick="document.getElementById('spp-ig-hl3-input').click()">
                                <div class="spp-ig-hl-circle">
                                    <img id="spp-ig-hl3-img" src="" class="spp-ig-hl-img">
                                    <input type="file" id="spp-ig-hl3-input" accept="image/*" style="display:none;" onchange="loadSPPImage(this, 'spp-ig-hl3-img')">
                                </div>
                                <div contenteditable="true" id="spp-ig-hl3-text" class="spp-editable spp-ig-hl-label">Story 3</div>
                            </div>
                        </div>
                        <!-- Tabs -->
                        <div class="spp-ig-tabs">
                            <div class="spp-ig-tab active"><i class="ph-bold ph-squares-four"></i></div>
                            <div class="spp-ig-tab"><i class="ph ph-video"></i></div>
                            <div class="spp-ig-tab"><i class="ph ph-user-square"></i></div>
                        </div>
                        <!-- Grid -->
                        <div class="spp-ig-grid" onclick="document.getElementById('spp-ig-posts-input').click()">
                            <img id="spp-ig-posts-img" src="" class="spp-ig-grid-img">
                            <div class="spp-upload-overlay spp-upload-overlay-square">
                                <i class="ph ph-image" style="font-size: 2rem;"></i>
                                <span>Subir grilla de posts</span>
                            </div>
                            <input type="file" id="spp-ig-posts-input" accept="image/*" style="display:none;" onchange="loadSPPImage(this, 'spp-ig-posts-img')">
                            <div id="spp-ig-posts-hint" class="spp-placeholder-hint">
                                <i class="ph ph-image"></i> Subir imagen (Posts)
                            </div>
                        </div>
                    </div>
                    <div class="spp-phone-home-bar"></div>
                </div>
            </div>

            <!-- ========== TIKTOK ========== -->
            <div id="spp-mockup-tiktok" class="spp-mockup">
                <div class="spp-phone-frame spp-phone-frame-dark">
                    <div class="spp-phone-notch spp-phone-notch-dark"></div>
                    <div class="spp-phone-screen spp-tk-screen">
                        <!-- TK Header -->
                        <div class="spp-tk-header">
                            <div contenteditable="true" id="spp-tk-name" class="spp-editable spp-tk-header-name">Nombre Real</div>
                        </div>
                        <!-- Profile -->
                        <div class="spp-tk-profile">
                            <div class="spp-tk-avatar" onclick="document.getElementById('spp-tk-logo-input').click()">
                                <img id="spp-tk-logo-img" src="" class="spp-tk-avatar-img">
                                <div class="spp-upload-overlay spp-upload-overlay-round"><i class="ph ph-camera"></i></div>
                                <input type="file" id="spp-tk-logo-input" accept="image/*" style="display:none;" onchange="loadSPPImage(this, 'spp-tk-logo-img')">
                            </div>
                            <div contenteditable="true" id="spp-tk-username" class="spp-editable spp-tk-username">@usuario_tiktok</div>
                            <div class="spp-tk-stats">
                                <div class="spp-tk-stat"><span class="spp-tk-stat-num">120</span><span class="spp-tk-stat-label">Siguiendo</span></div>
                                <div class="spp-tk-stat"><span class="spp-tk-stat-num">10.5K</span><span class="spp-tk-stat-label">Seguidores</span></div>
                                <div class="spp-tk-stat"><span class="spp-tk-stat-num">500K</span><span class="spp-tk-stat-label">Me gusta</span></div>
                            </div>
                            <div class="spp-tk-action-btns">
                                <button class="spp-tk-btn-follow">Seguir</button>
                                <button class="spp-tk-btn-ig"><i class="ph ph-instagram-logo"></i></button>
                            </div>
                            <div contenteditable="true" id="spp-tk-bio" class="spp-editable spp-tk-bio">Descripción corta de tiktok.<br>Link abajo👇</div>
                            <div class="spp-tk-link"><i class="ph ph-link"></i> linktr.ee/usuario</div>
                        </div>
                        <!-- Tabs -->
                        <div class="spp-tk-tabs">
                            <div class="spp-tk-tab active"><i class="ph-bold ph-list"></i></div>
                            <div class="spp-tk-tab"><i class="ph ph-lock"></i></div>
                            <div class="spp-tk-tab"><i class="ph ph-bookmark-simple"></i></div>
                        </div>
                        <!-- Grid -->
                        <div class="spp-tk-grid" onclick="document.getElementById('spp-tk-posts-input').click()">
                            <img id="spp-tk-posts-img" src="" class="spp-tk-grid-img">
                            <div class="spp-upload-overlay spp-upload-overlay-square">
                                <i class="ph ph-image" style="font-size: 2rem;"></i>
                                <span>Subir grilla de videos</span>
                            </div>
                            <input type="file" id="spp-tk-posts-input" accept="image/*" style="display:none;" onchange="loadSPPImage(this, 'spp-tk-posts-img')">
                            <div id="spp-tk-posts-hint" class="spp-placeholder-hint">
                                <i class="ph ph-image"></i> Subir imagen (Posts)
                            </div>
                        </div>
                    </div>
                    <div class="spp-phone-home-bar spp-phone-home-bar-dark"></div>
                </div>
            </div>

        </div><!-- /spp-body -->
    </div><!-- /spp-modal-wrap -->
</div>

<style>
/* ===== MODAL CONTAINER ===== */
#socialProfilesPresentationModal {
    position: fixed; inset: 0; display: none; align-items: center; justify-content: center;
    z-index: 1080; transition: background 0.4s ease;
}
#socialProfilesPresentationModal.active { background: rgba(0,0,0,0.75) !important; backdrop-filter: blur(8px); }

.spp-modal-wrap {
    width: 92vw; max-width: 1100px; height: 88vh; display: flex; flex-direction: column;
    background: #111214; color: #e8e8e8; border-radius: 20px;
    box-shadow: 0 25px 80px rgba(0,0,0,0.7), 0 0 0 1px rgba(255,255,255,0.06);
    overflow: hidden; transform: scale(0.92) translateY(20px); opacity: 0;
    transition: transform 0.4s cubic-bezier(.22,1,.36,1), opacity 0.35s ease;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}
#socialProfilesPresentationModal.active .spp-modal-wrap { transform: scale(1) translateY(0); opacity: 1; }

/* ===== HEADER ===== */
.spp-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 1rem 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.06);
    background: rgba(255,255,255,0.02); flex-shrink: 0;
}
.spp-header-left { display: flex; align-items: center; gap: 0.75rem; }
.spp-header-icon {
    width: 40px; height: 40px; border-radius: 12px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem; color: #fff;
}
.spp-header-title { margin: 0; font-size: 1.05rem; font-weight: 700; color: #fff; }
.spp-header-sub { margin: 0; font-size: 0.78rem; color: #888; font-weight: 400; }
.spp-header-right { display: flex; align-items: center; gap: 0.6rem; }
.spp-btn-save {
    display: flex; align-items: center; gap: 6px; padding: 0.5rem 1.1rem;
    background: linear-gradient(135deg, #667eea, #764ba2); color: #fff;
    border: none; border-radius: 10px; font-weight: 600; font-size: 0.85rem;
    cursor: pointer; transition: all 0.25s; box-shadow: 0 4px 15px rgba(102,126,234,0.3);
}
.spp-btn-save:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(102,126,234,0.45); }
.spp-btn-close {
    width: 36px; height: 36px; border-radius: 10px; background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.08); color: #999; display: flex;
    align-items: center; justify-content: center; cursor: pointer; font-size: 1rem; transition: 0.2s;
}
.spp-btn-close:hover { background: rgba(255,255,255,0.12); color: #fff; }

/* ===== TABS BAR ===== */
.spp-tabs-bar {
    display: flex; gap: 0; padding: 0 1.5rem; position: relative;
    border-bottom: 1px solid rgba(255,255,255,0.06); flex-shrink: 0;
    background: rgba(255,255,255,0.02);
}
.spp-tab {
    display: flex; align-items: center; gap: 8px; padding: 0.9rem 1.3rem;
    background: none; border: none; color: #666; font-weight: 600;
    font-size: 0.9rem; cursor: pointer; transition: 0.3s; position: relative; z-index: 1;
}
.spp-tab i { font-size: 1.2rem; }
.spp-tab:hover { color: #aaa; }
.spp-tab.active { color: #fff; }
.spp-tab.active[data-platform="facebook"] i { color: #1877F2; }
.spp-tab.active[data-platform="instagram"] i { color: #E1306C; }
.spp-tab.active[data-platform="tiktok"] i { color: #fff; }
.spp-tab-indicator {
    position: absolute; bottom: 0; height: 3px; border-radius: 3px 3px 0 0;
    background: linear-gradient(90deg, #667eea, #764ba2);
    transition: left 0.35s cubic-bezier(.22,1,.36,1), width 0.35s cubic-bezier(.22,1,.36,1);
}

/* ===== PRESENTATION TOGGLE ===== */
.spp-pres-toggle {
    display: flex; align-items: center; gap: 6px; cursor: pointer;
    padding: 0 4px 0 0; position: relative; z-index: 2;
    border-right: 1px solid rgba(255,255,255,0.06); margin-right: 4px;
}
.spp-pres-toggle input { display: none; }
.spp-toggle-slider {
    width: 32px; height: 18px; background: rgba(255,255,255,0.1);
    border-radius: 20px; position: relative; transition: 0.3s;
    border: 1px solid rgba(255,255,255,0.08);
}
.spp-toggle-slider::after {
    content: ''; position: absolute; top: 2px; left: 2px;
    width: 12px; height: 12px; border-radius: 50%;
    background: #555; transition: 0.3s;
}
.spp-pres-toggle input:checked + .spp-toggle-slider {
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-color: rgba(102,126,234,0.4);
}
.spp-pres-toggle input:checked + .spp-toggle-slider::after {
    transform: translateX(14px); background: #fff;
}
.spp-toggle-icon {
    font-size: 0.85rem; color: #555; transition: 0.3s;
}
.spp-pres-toggle input:checked ~ .spp-toggle-icon {
    color: #667eea;
}
.spp-pres-toggle:hover .spp-toggle-slider { border-color: rgba(255,255,255,0.2); }
/* ===== BODY ===== */
.spp-body {
    flex: 1; overflow-y: auto; display: flex; align-items: flex-start;
    justify-content: center; padding: 2rem;
    background: radial-gradient(ellipse at 50% 0%, rgba(102,126,234,0.04) 0%, transparent 60%);
}
.spp-body::-webkit-scrollbar { width: 6px; }
.spp-body::-webkit-scrollbar-track { background: transparent; }
.spp-body::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 3px; }

/* ===== MOCKUPS (shared) ===== */
.spp-mockup { display: none; width: 100%; animation: sppFadeUp 0.4s ease forwards; }
.spp-mockup.spp-mockup-active { display: flex; justify-content: center; }
@keyframes sppFadeUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }

/* Editable Fields */
.spp-editable { outline: none; border-bottom: 1px dashed transparent; transition: 0.2s; cursor: text; }
.spp-editable:hover { background: rgba(255,255,255,0.05); border-radius: 4px; }
.spp-editable:focus { background: rgba(255,255,255,0.08); border-radius: 4px; border-bottom: 1px dashed rgba(255,255,255,0.3); }

/* Upload Overlays */
.spp-upload-overlay {
    position: absolute; inset: 0; display: flex; flex-direction: column;
    align-items: center; justify-content: center; gap: 4px;
    background: rgba(0,0,0,0.5); color: #fff; opacity: 0;
    transition: 0.3s; cursor: pointer; font-size: 0.85rem; font-weight: 500;
    backdrop-filter: blur(4px);
}
*:hover > .spp-upload-overlay { opacity: 1; }
.spp-upload-overlay-round { border-radius: 50%; }
.spp-upload-overlay-square { border-radius: 8px; }
.spp-upload-overlay i { font-size: 1.5rem; }

.spp-placeholder-hint {
    position: absolute; inset: 0; display: flex; flex-direction: column;
    align-items: center; justify-content: center; color: #888; gap: 8px; font-size: 0.9rem;
}
.spp-placeholder-hint i { font-size: 2rem; }

/* ===== FACEBOOK MOCKUP ===== */
.spp-fb-card {
    width: 100%; max-width: 680px; background: #fff; border-radius: 14px;
    overflow: hidden; box-shadow: 0 8px 30px rgba(0,0,0,0.3);
}
.spp-fb-cover {
    width: 100%; aspect-ratio: 2.7; background: linear-gradient(135deg, #e4e6eb, #d5d8de);
    position: relative; cursor: pointer; overflow: hidden;
}
.spp-fb-cover-img { width: 100%; height: 100%; object-fit: cover; display: none; }
.spp-fb-profile-section { padding: 0 1.5rem 1.2rem; position: relative; display: flex; align-items: flex-end; gap: 1rem; flex-wrap: wrap; }
.spp-fb-avatar {
    width: 120px; height: 120px; border-radius: 50%; background: #fff;
    border: 5px solid #fff; margin-top: -40px; cursor: pointer;
    position: relative; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    flex-shrink: 0;
}
.spp-fb-avatar-img { width: 100%; height: 100%; object-fit: cover; display: none; }
.spp-fb-info { flex: 1; min-width: 200px; padding-bottom: 6px; }
.spp-fb-name { margin: 0; font-size: 1.6rem; font-weight: 800; color: #050505; line-height: 1.2; }
.spp-fb-followers { margin: 4px 0 0; color: #65676B; font-size: 0.9rem; font-weight: 500; }
.spp-fb-actions { display: flex; gap: 8px; padding-bottom: 6px; }
.spp-fb-btn {
    border: none; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 600;
    font-size: 0.85rem; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: 0.2s;
}
.spp-fb-btn-primary { background: #1877F2; color: #fff; }
.spp-fb-btn-primary:hover { background: #166ad8; }
.spp-fb-btn-secondary { background: #e4e6eb; color: #050505; }
.spp-fb-btn-secondary:hover { background: #d8dadf; }
.spp-fb-nav {
    display: flex; border-top: 1px solid #ced0d4; padding: 0 1rem; overflow-x: auto;
}
.spp-fb-nav-item {
    padding: 0.85rem 1rem; color: #65676B; font-weight: 600; font-size: 0.9rem;
    cursor: pointer; white-space: nowrap; transition: 0.2s; position: relative;
}
.spp-fb-nav-item.active { color: #1877F2; }
.spp-fb-nav-item.active::after {
    content: ''; position: absolute; bottom: 0; left: 0; right: 0;
    height: 3px; background: #1877F2; border-radius: 3px 3px 0 0;
}
.spp-fb-feed { background: #f0f2f5; padding: 1rem; }
.spp-fb-post-card {
    background: #fff; border-radius: 10px; overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08); cursor: pointer;
    position: relative; min-height: 250px;
}
.spp-fb-post-img { width: 100%; display: none; }

/* Editable color fix for FB (white bg) */
#spp-mockup-facebook .spp-editable:hover { background: rgba(0,0,0,0.04); }
#spp-mockup-facebook .spp-editable:focus { background: rgba(0,0,0,0.06); border-bottom-color: rgba(0,0,0,0.2); }

/* ===== PHONE FRAME ===== */
.spp-phone-frame {
    width: 375px; border-radius: 40px; background: #1a1a1a;
    padding: 12px; box-shadow: 0 20px 60px rgba(0,0,0,0.5), 0 0 0 2px rgba(255,255,255,0.08);
    position: relative; display: flex; flex-direction: column; overflow: hidden;
}
.spp-phone-frame-dark { background: #000; }
.spp-phone-notch {
    width: 120px; height: 28px; background: #1a1a1a; border-radius: 0 0 16px 16px;
    margin: 0 auto -14px; position: relative; z-index: 10;
}
.spp-phone-notch-dark { background: #000; }
.spp-phone-screen {
    flex: 1; border-radius: 30px; overflow-y: auto; overflow-x: hidden;
}
.spp-phone-screen::-webkit-scrollbar { width: 0; }
.spp-phone-home-bar {
    width: 140px; height: 5px; background: rgba(255,255,255,0.25);
    border-radius: 10px; margin: 8px auto 4px;
}
.spp-phone-home-bar-dark { background: rgba(255,255,255,0.2); }

/* ===== INSTAGRAM ===== */
.spp-ig-screen { background: #fff; color: #262626; }
.spp-ig-header {
    display: flex; align-items: center; justify-content: center; gap: 4px;
    padding: 12px 16px; border-bottom: 1px solid #efefef;
}
.spp-ig-username-text { font-weight: 700; font-size: 1rem; color: #262626; }
.spp-ig-profile-row { display: flex; align-items: center; padding: 16px; gap: 16px; }
.spp-ig-avatar-wrap { cursor: pointer; flex-shrink: 0; }
.spp-ig-avatar-ring {
    width: 80px; height: 80px; border-radius: 50%;
    background: linear-gradient(135deg, #f09433, #e6683c, #dc2743, #cc2366, #bc1888);
    padding: 3px; position: relative;
}
.spp-ig-avatar-ring > img, .spp-ig-avatar-ring > div:first-of-type { border-radius: 50%; }
.spp-ig-avatar-img {
    width: 100%; height: 100%; object-fit: cover; border-radius: 50%;
    border: 3px solid #fff; display: none;
}
.spp-ig-stats { display: flex; gap: 16px; flex: 1; justify-content: center; }
.spp-ig-stat { display: flex; flex-direction: column; align-items: center; }
.spp-ig-stat-num { font-weight: 700; font-size: 1rem; color: #262626; }
.spp-ig-stat-label { font-size: 0.75rem; color: #8e8e8e; }
.spp-ig-bio-section { padding: 0 16px 8px; }
.spp-ig-bio-name { font-weight: 700; font-size: 0.9rem; color: #262626; }
.spp-ig-bio-text { font-size: 0.9rem; margin-top: 2px; color: #262626; line-height: 1.4; }
.spp-ig-bio-link { color: #00376b; font-size: 0.9rem; font-weight: 600; margin-top: 3px; }
.spp-ig-action-btns { display: flex; gap: 6px; padding: 8px 16px; }
.spp-ig-btn-follow {
    flex: 1.5; background: #0095f6; color: #fff; border: none; padding: 7px;
    border-radius: 8px; font-weight: 600; font-size: 0.85rem; cursor: default;
}
.spp-ig-btn-msg, .spp-ig-btn-contact {
    flex: 1; background: #efefef; border: none; padding: 7px;
    border-radius: 8px; font-weight: 600; font-size: 0.85rem; color: #262626; cursor: default;
}
.spp-ig-highlights { display: flex; gap: 14px; padding: 10px 16px; overflow-x: auto; }
.spp-ig-hl { display: flex; flex-direction: column; align-items: center; gap: 5px; cursor: pointer; }
.spp-ig-hl-circle {
    width: 60px; height: 60px; border-radius: 50%; background: #efefef;
    border: 1px solid #dbdbdb; overflow: hidden; position: relative;
}
.spp-ig-hl-img { width: 100%; height: 100%; object-fit: cover; display: none; }
.spp-ig-hl-label { font-size: 0.7rem; color: #262626; max-width: 64px; text-align: center; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.spp-ig-tabs { display: flex; border-top: 1px solid #efefef; }
.spp-ig-tab {
    flex: 1; padding: 10px 0; text-align: center; color: #c7c7c7; font-size: 1.1rem; cursor: default;
}
.spp-ig-tab.active { color: #262626; border-bottom: 1px solid #262626; }
.spp-ig-grid {
    flex: 1; min-height: 200px; background: #fafafa; cursor: pointer;
    position: relative; display: flex;
}
.spp-ig-grid-img { width: 100%; object-fit: cover; display: none; }

/* IG editable fix */
#spp-mockup-instagram .spp-editable:hover { background: rgba(0,0,0,0.04); }
#spp-mockup-instagram .spp-editable:focus { background: rgba(0,0,0,0.06); border-bottom-color: rgba(0,0,0,0.15); }

/* ===== TIKTOK ===== */
.spp-tk-screen { background: #fff; color: #161823; }
.spp-tk-header { display: flex; justify-content: center; padding: 12px 16px; border-bottom: 1px solid #f1f1f2; }
.spp-tk-header-name { font-weight: 700; font-size: 1.05rem; color: #161823; }
.spp-tk-profile { display: flex; flex-direction: column; align-items: center; padding: 20px 16px 12px; }
.spp-tk-avatar {
    width: 90px; height: 90px; border-radius: 50%; background: #f1f1f2;
    position: relative; cursor: pointer; overflow: hidden; margin-bottom: 10px;
}
.spp-tk-avatar-img { width: 100%; height: 100%; object-fit: cover; display: none; }
.spp-tk-username { font-weight: 600; font-size: 0.95rem; margin-bottom: 14px; color: #161823; }
.spp-tk-stats { display: flex; gap: 24px; text-align: center; margin-bottom: 14px; }
.spp-tk-stat { display: flex; flex-direction: column; }
.spp-tk-stat-num { font-weight: 700; font-size: 1.05rem; color: #161823; }
.spp-tk-stat-label { font-size: 0.75rem; color: #8a8b91; }
.spp-tk-action-btns { display: flex; gap: 6px; margin-bottom: 12px; }
.spp-tk-btn-follow {
    background: #fe2c55; color: #fff; border: none; padding: 9px 40px;
    border-radius: 4px; font-weight: 700; font-size: 0.95rem; cursor: default;
}
.spp-tk-btn-ig {
    background: #f1f1f2; border: none; padding: 9px 14px; border-radius: 4px;
    display: flex; align-items: center; justify-content: center; font-size: 1.1rem; cursor: default;
}
.spp-tk-bio { font-size: 0.88rem; text-align: center; line-height: 1.4; color: #161823; }
.spp-tk-link { font-size: 0.88rem; font-weight: 600; color: #161823; margin-top: 5px; display: flex; align-items: center; gap: 4px; }
.spp-tk-tabs { display: flex; border-top: 1px solid #f1f1f2; border-bottom: 1px solid #f1f1f2; }
.spp-tk-tab {
    flex: 1; padding: 10px 0; text-align: center; color: #b0b0b4; font-size: 1.1rem; cursor: default;
}
.spp-tk-tab.active { color: #161823; border-bottom: 2px solid #161823; }
.spp-tk-grid {
    flex: 1; min-height: 200px; background: #fff; cursor: pointer;
    position: relative; display: flex;
}
.spp-tk-grid-img { width: 100%; object-fit: cover; display: none; }

/* TK editable fix */
#spp-mockup-tiktok .spp-editable:hover { background: rgba(0,0,0,0.04); }
#spp-mockup-tiktok .spp-editable:focus { background: rgba(0,0,0,0.06); border-bottom-color: rgba(0,0,0,0.15); }
</style>

<script>
function openSocialProfilesModal() {
    const modal = document.getElementById('socialProfilesPresentationModal');
    modal.style.display = 'flex';
    // Trigger animation
    requestAnimationFrame(() => {
        modal.classList.add('active');
    });
    switchSPTab('facebook');
    loadSocialProfiles();
}

function closeSocialProfilesModal() {
    const modal = document.getElementById('socialProfilesPresentationModal');
    modal.classList.remove('active');
    setTimeout(() => { modal.style.display = 'none'; }, 400);
}

function switchSPTab(platform) {
    // Hide all mockups
    document.querySelectorAll('.spp-mockup').forEach(el => {
        el.classList.remove('spp-mockup-active');
    });
    
    // Show selected
    const mockup = document.getElementById('spp-mockup-' + platform);
    // Re-trigger animation
    mockup.style.animation = 'none';
    mockup.offsetHeight; // reflow
    mockup.style.animation = '';
    mockup.classList.add('spp-mockup-active');
    
    // Update tabs
    document.querySelectorAll('.spp-tab').forEach(el => el.classList.remove('active'));
    const btn = document.getElementById('spp-tab-btn-' + platform);
    btn.classList.add('active');
    
    // Move indicator
    const bar = document.querySelector('.spp-tabs-bar');
    const indicator = document.getElementById('sppTabIndicator');
    const barRect = bar.getBoundingClientRect();
    const btnRect = btn.getBoundingClientRect();
    indicator.style.left = (btnRect.left - barRect.left) + 'px';
    indicator.style.width = btnRect.width + 'px';
}

function loadSPPImage(input, imgId) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const reader = new FileReader();
        reader.onload = function(e) {
            const imgObj = new Image();
            imgObj.onload = function() {
                let width = imgObj.width;
                let height = imgObj.height;
                const maxSize = 1200;
                
                if (width > maxSize || height > maxSize) {
                    if (width > height) {
                        height = Math.round(height * (maxSize / width));
                        width = maxSize;
                    } else {
                        width = Math.round(width * (maxSize / height));
                        height = maxSize;
                    }
                }
                
                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(imgObj, 0, 0, width, height);
                const dataUrl = canvas.toDataURL('image/jpeg', 0.8);
                
                const img = document.getElementById(imgId);
                img.src = dataUrl;
                img.style.display = 'block';
                
                const hint = document.getElementById(imgId.replace('-img', '-hint'));
                if(hint) hint.style.display = 'none';
            };
            imgObj.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
}

function getSPPData() {
    return {
        fb: {
            cover: document.getElementById('spp-fb-cover-img').src,
            logo: document.getElementById('spp-fb-logo-img').src,
            name: document.getElementById('spp-fb-name').innerText,
            followers: document.getElementById('spp-fb-followers').innerText,
            posts: document.getElementById('spp-fb-posts-img').src
        },
        ig: {
            logo: document.getElementById('spp-ig-logo-img').src,
            username: document.getElementById('spp-ig-username').innerText,
            name: document.getElementById('spp-ig-name').innerText,
            bio: document.getElementById('spp-ig-bio').innerHTML,
            hl1Img: document.getElementById('spp-ig-hl1-img').src,
            hl1Text: document.getElementById('spp-ig-hl1-text').innerText,
            hl2Img: document.getElementById('spp-ig-hl2-img').src,
            hl2Text: document.getElementById('spp-ig-hl2-text').innerText,
            hl3Img: document.getElementById('spp-ig-hl3-img').src,
            hl3Text: document.getElementById('spp-ig-hl3-text').innerText,
            posts: document.getElementById('spp-ig-posts-img').src
        },
        tk: {
            logo: document.getElementById('spp-tk-logo-img').src,
            name: document.getElementById('spp-tk-name').innerText,
            username: document.getElementById('spp-tk-username').innerText,
            bio: document.getElementById('spp-tk-bio').innerHTML,
            posts: document.getElementById('spp-tk-posts-img').src
        }
    };
}

function setSPPData(data) {
    if(!data) return;
    try {
        if(data.fb) {
            if(data.fb.cover && data.fb.cover !== window.location.href) { document.getElementById('spp-fb-cover-img').src = data.fb.cover; document.getElementById('spp-fb-cover-img').style.display='block'; }
            if(data.fb.logo && data.fb.logo !== window.location.href) { document.getElementById('spp-fb-logo-img').src = data.fb.logo; document.getElementById('spp-fb-logo-img').style.display='block'; }
            if(data.fb.name) document.getElementById('spp-fb-name').innerText = data.fb.name;
            if(data.fb.followers) document.getElementById('spp-fb-followers').innerText = data.fb.followers;
            if(data.fb.posts && data.fb.posts !== window.location.href) { 
                document.getElementById('spp-fb-posts-img').src = data.fb.posts; 
                document.getElementById('spp-fb-posts-img').style.display='block';
                document.getElementById('spp-fb-posts-hint').style.display='none';
            }
        }
        if(data.ig) {
            if(data.ig.logo && data.ig.logo !== window.location.href) { document.getElementById('spp-ig-logo-img').src = data.ig.logo; document.getElementById('spp-ig-logo-img').style.display='block'; }
            if(data.ig.username) document.getElementById('spp-ig-username').innerText = data.ig.username;
            if(data.ig.name) document.getElementById('spp-ig-name').innerText = data.ig.name;
            if(data.ig.bio) document.getElementById('spp-ig-bio').innerHTML = data.ig.bio;
            if(data.ig.hl1Img && data.ig.hl1Img !== window.location.href) { document.getElementById('spp-ig-hl1-img').src = data.ig.hl1Img; document.getElementById('spp-ig-hl1-img').style.display='block'; }
            if(data.ig.hl1Text) document.getElementById('spp-ig-hl1-text').innerText = data.ig.hl1Text;
            if(data.ig.hl2Img && data.ig.hl2Img !== window.location.href) { document.getElementById('spp-ig-hl2-img').src = data.ig.hl2Img; document.getElementById('spp-ig-hl2-img').style.display='block'; }
            if(data.ig.hl2Text) document.getElementById('spp-ig-hl2-text').innerText = data.ig.hl2Text;
            if(data.ig.hl3Img && data.ig.hl3Img !== window.location.href) { document.getElementById('spp-ig-hl3-img').src = data.ig.hl3Img; document.getElementById('spp-ig-hl3-img').style.display='block'; }
            if(data.ig.hl3Text) document.getElementById('spp-ig-hl3-text').innerText = data.ig.hl3Text;
            if(data.ig.posts && data.ig.posts !== window.location.href) {
                document.getElementById('spp-ig-posts-img').src = data.ig.posts;
                document.getElementById('spp-ig-posts-img').style.display='block';
                document.getElementById('spp-ig-posts-hint').style.display='none';
            }
        }
        if(data.tk) {
            if(data.tk.logo && data.tk.logo !== window.location.href) { document.getElementById('spp-tk-logo-img').src = data.tk.logo; document.getElementById('spp-tk-logo-img').style.display='block'; }
            if(data.tk.name) document.getElementById('spp-tk-name').innerText = data.tk.name;
            if(data.tk.username) document.getElementById('spp-tk-username').innerText = data.tk.username;
            if(data.tk.bio) document.getElementById('spp-tk-bio').innerHTML = data.tk.bio;
            if(data.tk.posts && data.tk.posts !== window.location.href) {
                document.getElementById('spp-tk-posts-img').src = data.tk.posts;
                document.getElementById('spp-tk-posts-img').style.display='block';
                document.getElementById('spp-tk-posts-hint').style.display='none';
            }
        }
    } catch(e) { console.error(e); }
}

function saveSocialProfiles() {
    const btn = document.querySelector('.spp-btn-save');
    btn.innerHTML = '<i class="ph ph-spinner"></i> Guardando...';
    btn.style.pointerEvents = 'none';
    
    const data = getSPPData();
    const formData = new FormData();
    formData.append('month_id', new URLSearchParams(window.location.search).get('id'));
    formData.append('data', JSON.stringify(data));
    
    fetch('modules/month_board/ajax_save_social_profiles.php', {
        method: 'POST',
        body: formData
    }).then(r => r.json()).then(res => {
        if(res.success) {
            showToast('Perfiles guardados correctamente', 'success');
        } else {
            showToast(res.error || 'Error al guardar perfiles', 'error');
        }
    }).catch(e => {
        showToast('Error de conexión o imágenes muy pesadas', 'error');
    }).finally(() => {
        btn.innerHTML = '<i class="ph ph-cloud-arrow-up"></i> Guardar';
        btn.style.pointerEvents = '';
    });
}

function loadSocialProfiles() {
    const id = new URLSearchParams(window.location.search).get('id');
    fetch('modules/month_board/ajax_load_social_profiles.php?month_id=' + id)
    .then(r => r.json())
    .then(data => {
        if(Object.keys(data).length > 0) {
            setSPPData(data);
        }
    }).catch(e => console.error(e));
}
</script>
