<div class="content-section">
    <div class="section-header" style="margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.25rem; font-weight: 600; color: var(--color-title); margin: 0;">
            <i class="ph ph-database" style="color: var(--primary-color);"></i> Copias de Seguridad y Restauración
        </h2>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.25rem;">
            Descarga copias de seguridad en formato ZIP al instante, sube archivos para restaurar tu base de datos o sincroniza automáticamente con Google Drive.
        </p>
    </div>

    <!-- ROW 1: DESCARGA DIRECTA & SUBIR/RESTAURAR -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
        
        <!-- CARD 1: DESCARGAR COPIAS DIRECTAS EN ZIP -->
        <div class="form-group" style="background: var(--bg-surface); padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                    <div style="background: color-mix(in srgb, var(--primary-color) 12%, transparent); color: var(--primary-color); width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                        <i class="ph ph-download-simple"></i>
                    </div>
                    <div>
                        <h3 style="margin: 0; font-size: 1.05rem; color: var(--color-title);">Descargar Copia de Seguridad (.ZIP)</h3>
                        <span style="font-size: 0.75rem; color: #10b981; font-weight: 500;">Descarga inmediata a tu equipo</span>
                    </div>
                </div>
                <p style="margin: 0 0 1.25rem 0; color: var(--text-muted); font-size: 0.85rem; line-height: 1.4;">
                    Genera un archivo comprimido <strong>.ZIP</strong> con la estructura completa y todos los datos de tu base de datos MySQL (121 tablas).
                </p>
            </div>

            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                <a href="ajax/ajax_download_backup.php?type=db" class="btn btn-primary" style="text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.75rem; font-weight: 600;">
                    <i class="ph ph-database"></i> Descargar Backup de Base de Datos (.ZIP)
                </a>
                <a href="ajax/ajax_download_backup.php?type=full" class="btn btn-outline" style="text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.75rem; font-weight: 500;">
                    <i class="ph ph-archive"></i> Descargar Backup Completo (Archivos + BD)
                </a>
            </div>
        </div>

        <!-- CARD 2: SUBIR Y RESTAURAR COPIA (UPLOAD & RESTORE) -->
        <div class="form-group" style="background: var(--bg-surface); padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                <div style="background: color-mix(in srgb, #f59e0b 15%, transparent); color: #d97706; width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                    <i class="ph ph-upload-simple"></i>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 1.05rem; color: var(--color-title);">Subir y Restaurar Copia (.ZIP / .SQL)</h3>
                    <span style="font-size: 0.75rem; color: #d97706; font-weight: 500;">Sobreescribe datos con respaldo</span>
                </div>
            </div>

            <!-- Upload Area -->
            <form id="form-upload-restore" enctype="multipart/form-data">
                <div id="dropzone-backup" style="border: 2px dashed var(--border-color); border-radius: var(--radius-sm); padding: 1.25rem; text-align: center; cursor: pointer; transition: all 0.2s ease; background: var(--bg-color);">
                    <i class="ph ph-file-zip" style="font-size: 2rem; color: var(--text-muted); display: block; margin-bottom: 0.5rem;"></i>
                    <p style="margin: 0; font-size: 0.85rem; font-weight: 500; color: var(--color-title);">
                        Haz clic o arrastra un archivo <strong>.ZIP</strong> o <strong>.SQL</strong> aquí
                    </p>
                    <small style="color: var(--text-muted); font-size: 0.75rem;">Máximo permitido por el servidor</small>
                    <input type="file" id="input-backup-file" name="backup_file" accept=".zip,.sql" style="display: none;">
                </div>

                <div id="selected-file-info" style="display: none; margin-top: 0.75rem; padding: 0.5rem 0.75rem; background: color-mix(in srgb, var(--primary-color) 8%, transparent); border-radius: var(--radius-sm); font-size: 0.8rem; display: flex; align-items: center; justify-content: space-between;">
                    <span id="file-name-display" style="font-weight: 500; color: var(--color-title); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 250px;"></span>
                    <button type="button" id="btn-remove-file" style="background: none; border: none; color: #ef4444; cursor: pointer; font-size: 1rem;"><i class="ph ph-x"></i></button>
                </div>

                <div style="margin-top: 0.75rem;">
                    <input type="password" id="input-restore-password" name="backup_password" placeholder="Contraseña de cifrado (si el ZIP la requiere)" style="padding: 0.5rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-surface); color: var(--color-title); outline: none; width: 100%; font-size: 0.8rem;">
                </div>

                <button type="submit" id="btn-submit-restore" class="btn btn-primary" style="width: 100%; margin-top: 0.75rem; background: #d97706; border-color: #d97706;" disabled>
                    <i class="ph ph-arrows-counter-clockwise"></i> Restaurar Copia Seleccionada
                </button>
            </form>
        </div>

    </div>

    <!-- ROW 2: GOOGLE DRIVE & AJUSTES DE FRECUENCIA -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
        
        <!-- MANUAL GOOGLE DRIVE BACKUP -->
        <div class="form-group" style="background: var(--bg-surface); padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                <div style="background: color-mix(in srgb, #3b82f6 15%, transparent); color: #3b82f6; width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                    <i class="ph ph-google-drive-logo"></i>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 1.05rem; color: var(--color-title);">Subida a Google Drive</h3>
                    <p style="margin: 0; color: var(--text-muted); font-size: 0.8rem;">Almacena copias en tu carpeta en la nube</p>
                </div>
            </div>

            <div style="margin-bottom: 1rem;">
                <label style="font-size: 0.85rem; font-weight: 500; color: var(--text-muted); display: block; margin-bottom: 0.5rem;">Tipo de Copia para Drive</label>
                <select id="backup-type-select" style="padding: 0.5rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-surface); color: var(--color-title); outline: none; width: 100%;">
                    <option value="db">Rápido (Solo Base de Datos)</option>
                    <option value="full">Completo (Archivos del Sistema + Base de Datos)</option>
                </select>
            </div>

            <button type="button" class="btn btn-outline" id="btn-run-backup" style="width: 100%;">
                <i class="ph ph-cloud-arrow-up"></i> Generar y Subir a Google Drive
            </button>
            
            <div id="backup-status" style="display: none; padding: 1rem; border-radius: var(--radius-sm); margin-top: 1rem; background: var(--bg-color); border: 1px solid var(--border-color);">
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;" id="backup-spinner">
                    <i class="ph ph-spinner ph-spin" style="color: var(--primary-color);"></i>
                    <span style="font-weight: 500; font-size: 0.9rem;">Generando y subiendo copia a Drive...</span>
                </div>
                <pre id="backup-log" style="background: #1e1e1e; color: #d4d4d4; padding: 1rem; border-radius: 4px; font-size: 0.75rem; overflow-x: auto; max-height: 180px; overflow-y: auto; display: none;"></pre>
            </div>
        </div>

        <!-- SETTINGS -->
        <div class="form-group" style="background: var(--bg-surface); padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                <div style="background: color-mix(in srgb, var(--primary-color) 12%, transparent); color: var(--primary-color); width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                    <i class="ph ph-gear"></i>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 1.05rem; color: var(--color-title);">Configuración Automática</h3>
                    <p style="margin: 0; color: var(--text-muted); font-size: 0.8rem;">Programación y seguridad de cifrado</p>
                </div>
            </div>
            
            <form action="" method="POST">
                <input type="hidden" name="action_type" value="backups">
                
                <div style="margin-bottom: 1rem;">
                    <label style="font-size: 0.85rem; font-weight: 500; color: var(--text-muted); display: block; margin-bottom: 0.5rem;">Frecuencia Automática</label>
                    <select name="backup_frequency" style="padding: 0.5rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-surface); color: var(--color-title); outline: none; width: 100%;">
                        <option value="disabled" <?php echo ($settings['backup_frequency'] ?? '') === 'disabled' ? 'selected' : ''; ?>>Desactivado</option>
                        <option value="daily" <?php echo ($settings['backup_frequency'] ?? '') === 'daily' ? 'selected' : ''; ?>>Diariamente (Cada 24 horas)</option>
                        <option value="weekly" <?php echo ($settings['backup_frequency'] ?? '') === 'weekly' ? 'selected' : ''; ?>>Semanalmente (Cada 7 días)</option>
                    </select>
                </div>

                <div style="margin-bottom: 1rem;">
                    <label style="font-size: 0.85rem; font-weight: 500; color: var(--text-muted); display: block; margin-bottom: 0.5rem;">Contraseña de Cifrado ZIP</label>
                    <input type="password" name="backup_password" value="<?php echo htmlspecialchars($settings['backup_password'] ?? ''); ?>" placeholder="Opcional: Clave para proteger los ZIPs" style="padding: 0.5rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-surface); color: var(--color-title); outline: none; width: 100%;">
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i class="ph ph-floppy-disk"></i> Guardar Ajustes
                </button>
            </form>

            <div style="margin-top: 1rem; padding-top: 0.75rem; border-top: 1px solid var(--border-color);">
                <p style="margin: 0; color: var(--text-muted); font-size: 0.8rem;">
                    <strong>Último backup automático:</strong> 
                    <?php 
                        $last = $settings['last_backup_time'] ?? 0;
                        echo $last > 0 ? date('d/m/Y H:i:s', $last) : 'Nunca';
                    ?>
                </p>
            </div>
        </div>
    </div>

    <!-- ROW 3: LISTA DE BACKUPS EN GOOGLE DRIVE -->
    <div style="background: var(--bg-surface); padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;">
            <div>
                <h3 style="margin: 0; font-size: 1.05rem; color: var(--color-title);"><i class="ph ph-cloud-check"></i> Copias Almacenadas en Google Drive</h3>
                <p style="margin: 0.25rem 0 0 0; color: var(--text-muted); font-size: 0.85rem;">
                    Selecciona una copia remota en la nube para restaurar la <strong>Base de Datos</strong>.
                </p>
            </div>
            <button class="btn btn-outline" id="btn-load-backups">
                <i class="ph ph-arrows-clockwise"></i> Actualizar Lista de Drive
            </button>
        </div>

        <div style="overflow-x: auto;">
            <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border-color); text-align: left;">
                        <th style="padding: 0.75rem;">Archivo</th>
                        <th style="padding: 0.75rem;">Fecha</th>
                        <th style="padding: 0.75rem;">Acción</th>
                    </tr>
                </thead>
                <tbody id="backups-table-body">
                    <tr>
                        <td colspan="3" style="padding: 1rem; text-align: center; color: var(--text-muted);">
                            Haz clic en "Actualizar Lista de Drive" para consultar los respaldos sincronizados.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ==========================================
    // 1. SUBIDA Y RESTAURACIÓN LOCAL (UPLOAD & RESTORE)
    // ==========================================
    const dropzone = document.getElementById('dropzone-backup');
    const inputFile = document.getElementById('input-backup-file');
    const fileInfo = document.getElementById('selected-file-info');
    const fileNameDisplay = document.getElementById('file-name-display');
    const btnRemoveFile = document.getElementById('btn-remove-file');
    const btnSubmitRestore = document.getElementById('btn-submit-restore');
    const formUploadRestore = document.getElementById('form-upload-restore');

    if (dropzone && inputFile) {
        dropzone.addEventListener('click', () => inputFile.click());

        dropzone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropzone.style.borderColor = 'var(--primary-color)';
            dropzone.style.background = 'color-mix(in srgb, var(--primary-color) 6%, transparent)';
        });

        dropzone.addEventListener('dragleave', () => {
            dropzone.style.borderColor = 'var(--border-color)';
            dropzone.style.background = 'var(--bg-color)';
        });

        dropzone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropzone.style.borderColor = 'var(--border-color)';
            dropzone.style.background = 'var(--bg-color)';
            if (e.dataTransfer.files.length > 0) {
                inputFile.files = e.dataTransfer.files;
                updateFileInfo();
            }
        });

        inputFile.addEventListener('change', updateFileInfo);

        btnRemoveFile.addEventListener('click', () => {
            inputFile.value = '';
            updateFileInfo();
        });

        function updateFileInfo() {
            if (inputFile.files.length > 0) {
                const f = inputFile.files[0];
                const sizeMb = (f.size / (1024 * 1024)).toFixed(2);
                fileNameDisplay.textContent = `${f.name} (${sizeMb} MB)`;
                fileInfo.style.display = 'flex';
                btnSubmitRestore.disabled = false;
            } else {
                fileNameDisplay.textContent = '';
                fileInfo.style.display = 'none';
                btnSubmitRestore.disabled = true;
            }
        }

        formUploadRestore.addEventListener('submit', function(e) {
            e.preventDefault();
            if (!inputFile.files.length) return;

            const selectedFile = inputFile.files[0];

            Swal.fire({
                title: '¿Restaurar Base de Datos?',
                html: `Estás a punto de reemplazar la base de datos actual con:<br><strong>${selectedFile.name}</strong><br><br><span style="color:#d97706; font-size: 0.85rem;">Te sugerimos haber descargado un backup preventivo antes de continuar.</span>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d97706',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Sí, restaurar ahora',
                cancelButtonText: 'Cancelar'
            }).then((res) => {
                if (res.isConfirmed) {
                    Swal.fire({
                        title: 'Restaurando Base de Datos...',
                        html: 'Subiendo archivo, verificando tablas e importando registros.<br><strong>Por favor no recargues ni cierres la página.</strong>',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();

                            const formData = new FormData(formUploadRestore);

                            fetch('ajax/ajax_upload_restore.php', {
                                method: 'POST',
                                body: formData
                            })
                            .then(r => r.json())
                            .then(data => {
                                if (data.success) {
                                    Swal.fire({
                                        title: '¡Restauración Exitosa!',
                                        text: data.message || 'La base de datos ha sido restaurada exitosamente.',
                                        icon: 'success'
                                    }).then(() => {
                                        window.location.reload();
                                    });
                                } else {
                                    Swal.fire('Error al Restaurar', data.error || 'Ocurrió un error inesperado.', 'error');
                                }
                            })
                            .catch(err => {
                                Swal.fire('Error de Conexión', 'No se pudo comunicar con el servidor: ' + err.message, 'error');
                            });
                        }
                    });
                }
            });
        });
    }

    // ==========================================
    // 2. BACKUP MANUAL GOOGLE DRIVE
    // ==========================================
    const btnRunBackup = document.getElementById('btn-run-backup');
    const backupStatus = document.getElementById('backup-status');
    const backupLog = document.getElementById('backup-log');
    const backupSpinner = document.getElementById('backup-spinner');

    if (btnRunBackup) {
        btnRunBackup.addEventListener('click', function() {
            if (!confirm('¿Deseas generar la copia y subirla a tu Google Drive ahora?')) return;

            btnRunBackup.disabled = true;
            btnRunBackup.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Procesando...';
            
            backupStatus.style.display = 'block';
            backupSpinner.style.display = 'flex';
            backupLog.style.display = 'none';
            backupLog.innerHTML = '';

            const selectedType = document.getElementById('backup-type-select') ? document.getElementById('backup-type-select').value : 'db';

            fetch('ajax/ajax_run_backup.php?type=' + selectedType)
                .then(response => response.json())
                .then(data => {
                    btnRunBackup.disabled = false;
                    btnRunBackup.innerHTML = '<i class="ph ph-cloud-arrow-up"></i> Generar y Subir a Google Drive';
                    backupSpinner.style.display = 'none';
                    backupLog.style.display = 'block';

                    if (data.log && Array.isArray(data.log)) {
                        backupLog.innerHTML = data.log.join('\n');
                    } else if (data.output) {
                        backupLog.innerHTML = data.output;
                    }

                    if (data.success) {
                        backupLog.innerHTML += '\n\n✅ Backup completado y subido a Google Drive exitosamente.\n';
                        if (data.link) {
                            backupLog.innerHTML += 'Enlace: <a href="' + data.link + '" target="_blank" style="color: #60a5fa;">Ver en Google Drive</a>';
                        }
                        if (document.getElementById('btn-load-backups')) {
                            document.getElementById('btn-load-backups').click();
                        }
                    } else {
                        backupLog.innerHTML += '\n\n❌ Hubo un error durante el backup.\n' + (data.error || 'Verifica los logs.');
                        backupLog.style.color = '#ef4444';
                    }
                })
                .catch(error => {
                    btnRunBackup.disabled = false;
                    btnRunBackup.innerHTML = '<i class="ph ph-cloud-arrow-up"></i> Generar y Subir a Google Drive';
                    backupSpinner.style.display = 'none';
                    backupLog.style.display = 'block';
                    backupLog.innerHTML = 'Error de red: ' + error.message;
                    backupLog.style.color = '#ef4444';
                });
        });
    }

    // ==========================================
    // 3. LISTAR Y RESTAURAR DESDE GOOGLE DRIVE
    // ==========================================
    const btnLoadBackups = document.getElementById('btn-load-backups');
    const tableBody = document.getElementById('backups-table-body');

    if (btnLoadBackups && tableBody) {
        btnLoadBackups.addEventListener('click', function() {
            btnLoadBackups.disabled = true;
            btnLoadBackups.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Consultando Drive...';
            tableBody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding:1rem;"><i class="ph ph-spinner ph-spin"></i> Obteniendo archivos desde Google Drive...</td></tr>';

            fetch('ajax/ajax_list_backups.php')
                .then(res => res.json())
                .then(data => {
                    btnLoadBackups.disabled = false;
                    btnLoadBackups.innerHTML = '<i class="ph ph-arrows-clockwise"></i> Actualizar Lista de Drive';
                    tableBody.innerHTML = '';

                    if (data.success && data.files.length > 0) {
                        data.files.forEach(f => {
                            const dateObj = new Date(f.createdTime);
                            const dateStr = dateObj.toLocaleString();
                            const isDbOnly = f.name.includes('_db_');
                            
                            tableBody.innerHTML += `
                                <tr style="border-bottom: 1px solid var(--border-color);">
                                    <td style="padding: 0.75rem;">
                                        <div style="display:flex; align-items:center; gap:0.5rem;">
                                            <i class="ph ph-file-zip" style="color: var(--primary-color); font-size: 1.25rem;"></i>
                                            <div>
                                                <a href="${f.webViewLink}" target="_blank" style="color: var(--color-title); text-decoration: none; font-weight: 500;">${f.name}</a>
                                                <div style="font-size:0.75rem; color:var(--text-muted);">${isDbOnly ? 'Solo BD' : 'Completo'}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="padding: 0.75rem;">${dateStr}</td>
                                    <td style="padding: 0.75rem;">
                                        <button class="btn btn-outline btn-restore" data-id="${f.id}" data-name="${f.name}" style="padding: 0.25rem 0.75rem; font-size: 0.8rem; color: #d97706; border-color: #d97706;">
                                            <i class="ph ph-arrows-counter-clockwise"></i> Restaurar
                                        </button>
                                    </td>
                                </tr>
                            `;
                        });

                        document.querySelectorAll('.btn-restore').forEach(btn => {
                            btn.addEventListener('click', function() {
                                const fileId = this.getAttribute('data-id');
                                const fileName = this.getAttribute('data-name');
                                restoreDriveBackup(fileId, fileName);
                            });
                        });

                    } else if (data.success) {
                        tableBody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding:1rem;">No se encontraron copias de seguridad en la carpeta de Google Drive.</td></tr>';
                    } else {
                        tableBody.innerHTML = `<tr><td colspan="3" style="text-align:center; padding:1rem; color: #ef4444;">Aviso: ${data.error}</td></tr>`;
                    }
                })
                .catch(err => {
                    btnLoadBackups.disabled = false;
                    btnLoadBackups.innerHTML = '<i class="ph ph-arrows-clockwise"></i> Actualizar Lista de Drive';
                    tableBody.innerHTML = `<tr><td colspan="3" style="text-align:center; padding:1rem; color: #ef4444;">Error al conectar con el servidor.</td></tr>`;
                });
        });

        function restoreDriveBackup(fileId, fileName) {
            Swal.fire({
                title: '¿Restaurar Base de Datos desde Drive?',
                html: `Estás a punto de sobreescribir tu base de datos actual con:<br><strong>${fileName}</strong><br><br><span style="color:#ef4444; font-weight:bold;">¡Esta acción no se puede deshacer!</span>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, restaurar ahora',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Restaurando desde Google Drive...',
                        text: 'Descargando e importando la base de datos. Por favor, no cierres esta ventana.',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                            
                            const fd = new URLSearchParams();
                            fd.append('fileId', fileId);
                            
                            fetch('ajax/ajax_restore_backup.php', {
                                method: 'POST',
                                body: fd
                            })
                            .then(res => res.json())
                            .then(data => {
                                if (data.success) {
                                    Swal.fire('¡Restaurado!', 'La base de datos ha sido recuperada exitosamente.', 'success')
                                    .then(() => {
                                        window.location.reload();
                                    });
                                } else {
                                    Swal.fire('Error', data.error || 'Ocurrió un problema durante la restauración.', 'error');
                                }
                            })
                            .catch(err => {
                                Swal.fire('Error', 'Error de conexión con el servidor.', 'error');
                            });
                        }
                    });
                }
            });
        }
    }
});
</script>
