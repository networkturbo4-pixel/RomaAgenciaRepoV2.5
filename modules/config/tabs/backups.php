<div class="content-section">
    <div class="section-header">
        <h2 style="font-size: 1.25rem; font-weight: 600; color: var(--color-title); margin: 0;">Copias de Seguridad (Backups)</h2>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.25rem;">
            Administra las copias de seguridad de la base de datos y archivos del sistema. Las copias se guardan automáticamente en tu Google Drive configurado.
        </p>
    </div>

    <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap: 1.5rem;">
        
        <!-- MANUAL BACKUP -->
        <div class="form-group" style="background: var(--bg-color); padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                <div>
                    <h3 style="margin: 0; font-size: 1.1rem; color: var(--color-title);">Copia de Seguridad Manual</h3>
                    <p style="margin: 0.25rem 0 0 0; color: var(--text-muted); font-size: 0.85rem;">
                        Genera un archivo ZIP con tu copia de seguridad y súbelo a la carpeta "copia de seguridad" en Google Drive ahora mismo.
                    </p>
                    <div style="margin-top: 1rem;">
                        <label style="font-size: 0.85rem; font-weight: 500; color: var(--text-muted); display: block; margin-bottom: 0.5rem;">Tipo de Copia</label>
                        <select id="backup-type-select" style="padding: 0.5rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-surface); color: var(--color-title); outline: none;">
                            <option value="full">Completo (Archivos del Sistema + Base de Datos)</option>
                            <option value="db">Rápido (Solo Base de Datos)</option>
                        </select>
                    </div>
                </div>
            </div>
            <button type="button" class="btn btn-primary" id="btn-run-backup" style="width: 100%;">
                <i class="ph ph-play-circle"></i> Ejecutar Backup Ahora
            </button>
            
            <div id="backup-status" style="display: none; padding: 1rem; border-radius: var(--radius-sm); margin-top: 1rem; background: var(--bg-surface); border: 1px solid var(--border-color);">
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;" id="backup-spinner">
                    <i class="ph ph-spinner ph-spin" style="color: var(--primary-color);"></i>
                    <span style="font-weight: 500; font-size: 0.9rem;">Generando y subiendo copia. Puede tomar unos minutos...</span>
                </div>
                <pre id="backup-log" style="background: #1e1e1e; color: #d4d4d4; padding: 1rem; border-radius: 4px; font-size: 0.75rem; overflow-x: auto; max-height: 200px; overflow-y: auto; display: none;"></pre>
            </div>
        </div>

        <!-- SETTINGS -->
        <div class="form-group" style="background: var(--bg-color); padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
            <h3 style="margin: 0 0 0.5rem 0; font-size: 1.1rem; color: var(--color-title);">Configuración de Backups</h3>
            <p style="margin: 0 0 1rem 0; color: var(--text-muted); font-size: 0.85rem;">
                Configura el cron simulado y la contraseña de cifrado (opcional) para proteger tus archivos ZIP.
            </p>
            
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
                    <label style="font-size: 0.85rem; font-weight: 500; color: var(--text-muted); display: block; margin-bottom: 0.5rem;">Tipo de Copia Automática</label>
                    <select name="backup_auto_type" style="padding: 0.5rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-surface); color: var(--color-title); outline: none; width: 100%;">
                        <option value="db" <?php echo ($settings['backup_auto_type'] ?? '') === 'db' ? 'selected' : ''; ?>>Rápida (Solo Base de Datos)</option>
                        <option value="full" <?php echo ($settings['backup_auto_type'] ?? '') === 'full' ? 'selected' : ''; ?>>Completo (Archivos + Base de Datos)</option>
                    </select>
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <label style="font-size: 0.85rem; font-weight: 500; color: var(--text-muted); display: block; margin-bottom: 0.5rem;"><i class="ph ph-lock-key"></i> Contraseña de Cifrado ZIP</label>
                    <input type="password" name="backup_password" value="<?php echo htmlspecialchars($settings['backup_password'] ?? ''); ?>" placeholder="Ej. MiClaveSecreta123" style="padding: 0.5rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-surface); color: var(--color-title); outline: none; width: 100%;">
                    <small style="color: var(--text-muted); font-size: 0.75rem;">Si la defines, se usará para encriptar y desencriptar los backups.</small>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i class="ph ph-floppy-disk"></i> Guardar Configuración
                </button>
            </form>

            <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                <p style="margin: 0; color: var(--text-muted); font-size: 0.8rem;">
                    <strong>Última ejecución automática:</strong> 
                    <?php 
                        $last = $settings['last_backup_time'] ?? 0;
                        echo $last > 0 ? date('d/m/Y H:i:s', $last) : 'Nunca';
                    ?>
                </p>
            </div>
        </div>
    </div>

    <!-- RESTORE SECTION -->
    <div style="margin-top: 1.5rem; background: var(--bg-color); padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <div>
                <h3 style="margin: 0; font-size: 1.1rem; color: var(--color-title);"><i class="ph ph-clock-counter-clockwise"></i> Restauración a 1 Clic</h3>
                <p style="margin: 0.25rem 0 0 0; color: var(--text-muted); font-size: 0.85rem;">
                    Selecciona una copia de seguridad almacenada en tu Google Drive para restaurar la <strong>Base de Datos</strong>.
                </p>
            </div>
            <button class="btn btn-outline" id="btn-load-backups">
                <i class="ph ph-arrows-clockwise"></i> Actualizar Lista
            </button>
        </div>

        <div style="overflow-x: auto;">
            <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
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
                            Haz clic en "Actualizar Lista" para buscar las copias de seguridad en Google Drive.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // MANUAL BACKUP
    const btnRunBackup = document.getElementById('btn-run-backup');
    const backupStatus = document.getElementById('backup-status');
    const backupLog = document.getElementById('backup-log');
    const backupSpinner = document.getElementById('backup-spinner');

    if (btnRunBackup) {
        btnRunBackup.addEventListener('click', function() {
            if (!confirm('¿Estás seguro de que deseas ejecutar la copia de seguridad ahora? Esto puede tardar varios minutos.')) {
                return;
            }

            btnRunBackup.disabled = true;
            btnRunBackup.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Procesando...';
            
            backupStatus.style.display = 'block';
            backupSpinner.style.display = 'flex';
            backupLog.style.display = 'none';
            backupLog.innerHTML = '';

            const selectedType = document.getElementById('backup-type-select') ? document.getElementById('backup-type-select').value : 'full';

            fetch('ajax/ajax_run_backup.php?type=' + selectedType)
                .then(response => response.json())
                .then(data => {
                    btnRunBackup.disabled = false;
                    btnRunBackup.innerHTML = '<i class="ph ph-play-circle"></i> Ejecutar Backup Ahora';
                    backupSpinner.style.display = 'none';
                    backupLog.style.display = 'block';

                    if (data.log && Array.isArray(data.log)) {
                        backupLog.innerHTML = data.log.join('\n');
                    } else if (data.output) {
                        backupLog.innerHTML = data.output;
                    }

                    if (data.success) {
                        backupLog.innerHTML += '\n\n✅ Backup completado exitosamente.\n';
                        if (data.link) {
                            backupLog.innerHTML += 'Enlace: <a href="' + data.link + '" target="_blank" style="color: #60a5fa;">Ver en Google Drive</a>';
                        }
                        // Refresh backups list if loaded
                        document.getElementById('btn-load-backups').click();
                    } else {
                        backupLog.innerHTML += '\n\n❌ Hubo un error durante el backup.\n' + (data.error || 'Verifica los logs para más detalles.');
                        backupLog.style.color = '#ef4444';
                    }
                })
                .catch(error => {
                    btnRunBackup.disabled = false;
                    btnRunBackup.innerHTML = '<i class="ph ph-play-circle"></i> Ejecutar Backup Ahora';
                    backupSpinner.style.display = 'none';
                    backupLog.style.display = 'block';
                    backupLog.innerHTML = 'Error de red al intentar ejecutar el backup: ' + error.message;
                    backupLog.style.color = '#ef4444';
                });
        });
    }

    // LIST BACKUPS
    const btnLoadBackups = document.getElementById('btn-load-backups');
    const tableBody = document.getElementById('backups-table-body');

    btnLoadBackups.addEventListener('click', function() {
        btnLoadBackups.disabled = true;
        btnLoadBackups.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Buscando...';
        tableBody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding:1rem;"><i class="ph ph-spinner ph-spin"></i> Obteniendo archivos desde Google Drive...</td></tr>';

        fetch('ajax/ajax_list_backups.php')
            .then(res => res.json())
            .then(data => {
                btnLoadBackups.disabled = false;
                btnLoadBackups.innerHTML = '<i class="ph ph-arrows-clockwise"></i> Actualizar Lista';
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
                                        <i class="ph ph-warning-circle"></i> Restaurar BD
                                    </button>
                                </td>
                            </tr>
                        `;
                    });

                    // Bind restore buttons
                    document.querySelectorAll('.btn-restore').forEach(btn => {
                        btn.addEventListener('click', function() {
                            const fileId = this.getAttribute('data-id');
                            const fileName = this.getAttribute('data-name');
                            restoreBackup(fileId, fileName);
                        });
                    });

                } else if (data.success) {
                    tableBody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding:1rem;">No hay copias de seguridad en la carpeta.</td></tr>';
                } else {
                    tableBody.innerHTML = `<tr><td colspan="3" style="text-align:center; padding:1rem; color: #ef4444;">Error: ${data.error}</td></tr>`;
                }
            })
            .catch(err => {
                btnLoadBackups.disabled = false;
                btnLoadBackups.innerHTML = '<i class="ph ph-arrows-clockwise"></i> Actualizar Lista';
                tableBody.innerHTML = `<tr><td colspan="3" style="text-align:center; padding:1rem; color: #ef4444;">Error de conexión.</td></tr>`;
            });
    });

    function restoreBackup(fileId, fileName) {
        Swal.fire({
            title: '¿Restaurar Base de Datos?',
            html: `Estás a punto de sobreescribir toda tu base de datos actual con el contenido de:<br><strong>${fileName}</strong><br><br><span style="color:#ef4444; font-weight:bold;">¡Esta acción no se puede deshacer!</span>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, restaurar ahora',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Restaurando...',
                    text: 'Descargando archivo, desencriptando e importando la base de datos. Por favor, no cierres esta ventana.',
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
});
</script>
