<?php
// modules/config/tabs/updates.php
// Interfaz de Actualización del Sistema a 1 Clic

require_once __DIR__ . '/../../../includes/SystemUpdater.php';
$updater = new SystemUpdater($db ?? null);
$localInfo = $updater->getLocalInfo();
?>

<div class="content-section">
    <div class="section-header" style="margin-bottom: 1.5rem;">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h2 style="font-size: 1.25rem; font-weight: 600; color: var(--color-title); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="ph ph-rocket-launch" style="color: var(--primary-color);"></i> Actualización del Sistema a 1 Clic
                </h2>
                <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.25rem;">
                    Actualiza la plataforma desde GitHub con total seguridad: respaldo preventivo automático y preservación íntegra de tu base de datos y archivos.
                </p>
            </div>
            <div>
                <span style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.35rem 0.85rem; border-radius: 9999px; background: color-mix(in srgb, #10b981 12%, transparent); color: #10b981; font-weight: 600; font-size: 0.8rem; border: 1px solid color-mix(in srgb, #10b981 30%, transparent);">
                    <i class="ph ph-shield-check"></i> Base de Datos Protegida
                </span>
            </div>
        </div>
    </div>

    <!-- ROW 1: INFO ACTUAL & CONFIGURACIÓN REPOSITORIO -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
        
        <!-- CARD 1: ESTADO ACTUAL -->
        <div style="background: var(--bg-surface); padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                <div style="background: color-mix(in srgb, var(--primary-color) 12%, transparent); color: var(--primary-color); width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                    <i class="ph ph-info"></i>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 1.05rem; color: var(--color-title);">Versión Instalada</h3>
                    <span style="font-size: 0.75rem; color: var(--text-muted);">Información de la compilación activa</span>
                </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 0.75rem; font-size: 0.85rem;">
                <div style="display: flex; justify-content: space-between; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);">
                    <span style="color: var(--text-muted);">Versión del Sistema:</span>
                    <strong style="color: var(--color-title);" id="disp-version"><?php echo htmlspecialchars($localInfo['version']); ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);">
                    <span style="color: var(--text-muted);">Commit Activo:</span>
                    <span style="font-family: monospace; font-weight: 600; color: var(--primary-color); background: var(--bg-color); padding: 0.1rem 0.4rem; border-radius: 4px;" id="disp-commit">
                        <?php echo htmlspecialchars($localInfo['current_commit']); ?>
                    </span>
                </div>
                <div style="display: flex; justify-content: space-between; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);">
                    <span style="color: var(--text-muted);">Fecha del Commit:</span>
                    <span style="color: var(--color-title);" id="disp-commit-date"><?php echo htmlspecialchars($localInfo['current_commit_date']); ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);">
                    <span style="color: var(--text-muted);">Rama (Branch):</span>
                    <span style="color: var(--color-title);"><i class="ph ph-git-branch"></i> <?php echo htmlspecialchars($localInfo['branch']); ?></span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--text-muted);">Última Actualización:</span>
                    <span style="color: var(--color-title);" id="disp-last-update"><?php echo htmlspecialchars($localInfo['last_update'] ?: 'No registrada'); ?></span>
                </div>
            </div>
        </div>

        <!-- CARD 2: REPOSITORIO GITHUB -->
        <div style="background: var(--bg-surface); padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                <div style="background: color-mix(in srgb, #3b82f6 15%, transparent); color: #3b82f6; width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                    <i class="ph ph-github-logo"></i>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 1.05rem; color: var(--color-title);">Repositorio Remoto</h3>
                    <span style="font-size: 0.75rem; color: var(--text-muted);">Fuente oficial de actualizaciones</span>
                </div>
            </div>

            <form id="form-repo-config">
                <div style="margin-bottom: 0.75rem;">
                    <label style="font-size: 0.8rem; font-weight: 500; color: var(--text-muted); display: block; margin-bottom: 0.35rem;">URL de GitHub (.git)</label>
                    <input type="text" id="input-repo-url" name="repo_url" value="<?php echo htmlspecialchars($localInfo['repo_url']); ?>" style="padding: 0.5rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-surface); color: var(--color-title); outline: none; width: 100%; font-size: 0.8rem; font-family: monospace;" required>
                </div>

                <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem;">
                    <div style="flex: 1;">
                        <label style="font-size: 0.8rem; font-weight: 500; color: var(--text-muted); display: block; margin-bottom: 0.35rem;">Rama Principal</label>
                        <input type="text" id="input-repo-branch" name="branch" value="<?php echo htmlspecialchars($localInfo['branch']); ?>" style="padding: 0.5rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-surface); color: var(--color-title); outline: none; width: 100%; font-size: 0.8rem;" required>
                    </div>
                    <div style="display: flex; align-items: flex-end;">
                        <button type="button" id="btn-set-v25" class="btn btn-outline" style="padding: 0.5rem 0.75rem; font-size: 0.75rem; white-space: nowrap;" title="Vincular a RomaAgenciaRepoV2.5">
                            <i class="ph ph-link"></i> Usar Repo V2.5
                        </button>
                    </div>
                </div>

                <button type="submit" id="btn-save-repo" class="btn btn-outline" style="width: 100%; font-size: 0.85rem;">
                    <i class="ph ph-floppy-disk"></i> Guardar Fuente de Actualización
                </button>
            </form>
        </div>

    </div>

    <!-- ROW 2: ACCIÓN DE ACTUALIZACIÓN & CHANGELOG -->
    <div style="background: var(--bg-surface); padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); margin-bottom: 1.5rem;">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.25rem;">
            <div>
                <h3 style="margin: 0; font-size: 1.1rem; color: var(--color-title);">Centro de Actualización</h3>
                <p style="margin: 0.25rem 0 0 0; color: var(--text-muted); font-size: 0.85rem;">
                    Verifica si hay nuevas versiones publicadas en GitHub y aplícalas con un solo clic.
                </p>
            </div>
            <button type="button" id="btn-check-updates" class="btn btn-primary" style="padding: 0.65rem 1.25rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem;">
                <i class="ph ph-magnifying-glass"></i> Comprobar Actualizaciones
            </button>
        </div>

        <!-- Update Status Box (Dinámico) -->
        <div id="update-status-box" style="display: none; padding: 1.25rem; border-radius: var(--radius-sm); margin-bottom: 1.25rem; border: 1px solid var(--border-color); background: var(--bg-color);">
            <!-- Insertado por JavaScript -->
        </div>

        <!-- Terminal Console -->
        <div style="margin-top: 1rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                <label style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted); display: flex; align-items: center; gap: 0.35rem;">
                    <i class="ph ph-terminal-window"></i> Consola de Ejecución en Vivo
                </label>
                <button type="button" id="btn-clear-console" style="background: none; border: none; font-size: 0.75rem; color: var(--text-muted); cursor: pointer;">
                    Limpiar consola
                </button>
            </div>
            <div id="updater-console" style="background: #0f172a; color: #38bdf8; font-family: 'Consolas', 'Courier New', monospace; font-size: 0.78rem; padding: 1rem; border-radius: var(--radius-sm); max-height: 220px; overflow-y: auto; line-height: 1.6; border: 1px solid #1e293b;">
                <span style="color: #64748b;">[Sistema listo. Presiona "Comprobar Actualizaciones" para buscar cambios en GitHub.]</span>
            </div>
        </div>
    </div>

    <!-- ROW 3: GARANTÍA DE SEGURIDAD EXPLICATIVA -->
    <div style="background: color-mix(in srgb, var(--primary-color) 4%, var(--bg-surface)); border: 1px solid color-mix(in srgb, var(--primary-color) 20%, transparent); border-radius: var(--radius-md); padding: 1.25rem;">
        <div style="display: flex; align-items: flex-start; gap: 1rem;">
            <div style="font-size: 1.75rem; color: var(--primary-color); line-height: 1;">
                <i class="ph ph-shield-check-fill"></i>
            </div>
            <div>
                <h4 style="margin: 0 0 0.35rem 0; font-size: 0.95rem; color: var(--color-title); font-weight: 600;">
                    ¿Por qué es 100% seguro actualizar el sistema?
                </h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; font-size: 0.82rem; color: var(--text-muted); margin-top: 0.5rem;">
                    <div>
                        <strong style="color: var(--color-title); display: block; margin-bottom: 0.2rem;">1. Respaldo Automático Previo</strong>
                        Antes de cualquier cambio, se toma un snapshot completo de tu base de datos y se guarda en tu servidor.
                    </div>
                    <div>
                        <strong style="color: var(--color-title); display: block; margin-bottom: 0.2rem;">2. Base de Datos Intacta</strong>
                        La actualización descarga archivos del sistema (código PHP/CSS/JS). No toca registros, clientes, proyectos ni tareas.
                    </div>
                    <div>
                        <strong style="color: var(--color-title); display: block; margin-bottom: 0.2rem;">3. Migraciones No Destructivas</strong>
                        Si la nueva versión añade nuevas columnas o tablas, se crean de forma segura sin sobreescribir datos existentes.
                    </div>
                    <div>
                        <strong style="color: var(--color-title); display: block; margin-bottom: 0.2rem;">4. Archivos Subidos Protegidos</strong>
                        Tus imágenes, comprobantes, marcas y credenciales en <code>uploads/</code> y <code>database.php</code> permanecen intocables.
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const btnCheck = document.getElementById('btn-check-updates');
    const statusBox = document.getElementById('update-status-box');
    const consoleBox = document.getElementById('updater-console');
    const btnClearConsole = document.getElementById('btn-clear-console');
    const formRepo = document.getElementById('form-repo-config');
    const btnSetV25 = document.getElementById('btn-set-v25');

    function logConsole(msg, color = '#38bdf8') {
        const time = new Date().toLocaleTimeString();
        const line = document.createElement('div');
        line.style.color = color;
        line.textContent = `[${time}] ${msg}`;
        consoleBox.appendChild(line);
        consoleBox.scrollTop = consoleBox.scrollHeight;
    }

    if (btnClearConsole) {
        btnClearConsole.addEventListener('click', () => {
            consoleBox.innerHTML = '<span style="color: #64748b;">[Consola limpia]</span>';
        });
    }

    // Botón para asignar rápidamente el repo RomaAgenciaRepoV2.5
    if (btnSetV25) {
        btnSetV25.addEventListener('click', () => {
            document.getElementById('input-repo-url').value = 'https://github.com/networkturbo4-pixel/RomaAgenciaRepoV2.5.git';
            document.getElementById('input-repo-branch').value = 'main';
            logConsole('Configuración predefinida a: RomaAgenciaRepoV2.5. Haz clic en "Guardar Fuente" para aplicar.');
        });
    }

    // Guardar configuración del repositorio
    if (formRepo) {
        formRepo.addEventListener('submit', function(e) {
            e.preventDefault();
            const url = document.getElementById('input-repo-url').value.trim();
            const branch = document.getElementById('input-repo-branch').value.trim();

            const fd = new URLSearchParams();
            fd.append('action', 'save_repo');
            fd.append('repo_url', url);
            fd.append('branch', branch);

            logConsole(`Guardando configuración de repositorio: ${url} (${branch})...`);

            fetch('ajax/ajax_system_updater.php', {
                method: 'POST',
                body: fd
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Configuración Guardada', data.message, 'success');
                    logConsole('✅ Repositorio actualizado exitosamente.', '#10b981');
                } else {
                    Swal.fire('Error', data.error || 'No se pudo guardar la configuración.', 'error');
                    logConsole('❌ ' + (data.error || 'Error al guardar repositorio.'), '#ef4444');
                }
            })
            .catch(err => {
                Swal.fire('Error de Conexión', err.message, 'error');
                logConsole('❌ Error de conexión: ' + err.message, '#ef4444');
            });
        });
    }

    // Comprobar actualizaciones
    if (btnCheck) {
        btnCheck.addEventListener('click', function() {
            btnCheck.disabled = true;
            btnCheck.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Comprobando...';
            logConsole('Conectando con GitHub para verificar actualizaciones...');

            fetch('ajax/ajax_system_updater.php?action=check')
            .then(r => r.json())
            .then(res => {
                btnCheck.disabled = false;
                btnCheck.innerHTML = '<i class="ph ph-magnifying-glass"></i> Comprobar Actualizaciones';

                if (res.logs && Array.isArray(res.logs)) {
                    res.logs.forEach(l => logConsole(l));
                }

                if (!res.success) {
                    statusBox.style.display = 'block';
                    statusBox.innerHTML = `
                        <div style="display: flex; align-items: center; gap: 0.75rem; color: #ef4444;">
                            <i class="ph ph-warning-circle" style="font-size: 1.5rem;"></i>
                            <div>
                                <strong style="display: block;">Error al comprobar actualizaciones</strong>
                                <span style="font-size: 0.85rem;">${res.error || 'No se pudo conectar con el repositorio.'}</span>
                            </div>
                        </div>
                    `;
                    return;
                }

                const data = res.data;
                statusBox.style.display = 'block';

                if (data.has_updates) {
                    let commitsHtml = '';
                    if (data.new_commits && data.new_commits.length > 0) {
                        commitsHtml = `
                            <div style="margin-top: 0.75rem; background: var(--bg-surface); padding: 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                                <strong style="font-size: 0.8rem; color: var(--color-title); display: block; margin-bottom: 0.35rem;">Novedades y Cambios de la Nueva Versión:</strong>
                                <ul style="margin: 0; padding-left: 1.25rem; font-size: 0.8rem; color: var(--text-muted);">
                                    ${data.new_commits.map(c => `<li><code>${c.hash}</code> ${c.message} <span style="font-size: 0.7rem; color: var(--text-muted);">(${c.date})</span></li>`).join('')}
                                </ul>
                            </div>
                        `;
                    }

                    statusBox.innerHTML = `
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <div style="background: color-mix(in srgb, #f59e0b 20%, transparent); color: #d97706; width: 42px; height: 42px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem;">
                                    <i class="ph ph-arrow-circle-up"></i>
                                </div>
                                <div>
                                    <strong style="font-size: 1rem; color: var(--color-title); display: block;">¡Nueva Versión Disponible!</strong>
                                    <span style="font-size: 0.85rem; color: var(--text-muted);">${data.message}</span>
                                </div>
                            </div>
                            <button type="button" id="btn-run-update-now" class="btn btn-primary" style="background: #10b981; border-color: #10b981; font-weight: 700; padding: 0.75rem 1.5rem; font-size: 0.95rem; display: flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);">
                                <i class="ph ph-rocket-launch"></i> Actualizar Sistema Ahora (1 Clic)
                            </button>
                        </div>
                        ${commitsHtml}
                    `;

                    // Asignar evento al botón de actualización 1-clic
                    document.getElementById('btn-run-update-now').addEventListener('click', executeOneClickUpdate);

                } else {
                    statusBox.innerHTML = `
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <div style="background: color-mix(in srgb, #10b981 15%, transparent); color: #10b981; width: 42px; height: 42px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem;">
                                <i class="ph ph-check-circle"></i>
                            </div>
                            <div>
                                <strong style="font-size: 1rem; color: var(--color-title); display: block;">¡Tu sistema está al día!</strong>
                                <span style="font-size: 0.85rem; color: var(--text-muted);">${data.message} Commit: <code>${data.current_commit}</code></span>
                            </div>
                        </div>
                    `;
                }
            })
            .catch(err => {
                btnCheck.disabled = false;
                btnCheck.innerHTML = '<i class="ph ph-magnifying-glass"></i> Comprobar Actualizaciones';
                logConsole('❌ Error: ' + err.message, '#ef4444');
            });
        });
    }

    // Función de actualización a 1 clic
    function executeOneClickUpdate() {
        Swal.fire({
            title: '¿Actualizar Sistema Ahora?',
            html: `
                <div style="text-align: left; font-size: 0.85rem; line-height: 1.5;">
                    <p>El proceso realizará automáticamente:</p>
                    <ul style="padding-left: 1.25rem;">
                        <li><strong>1. Respaldo preventivo:</strong> Copia completa de la BD.</li>
                        <li><strong>2. Sincronización:</strong> Código actualizado desde GitHub.</li>
                        <li><strong>3. Migraciones:</strong> Nuevas columnas y tablas sin tocar datos.</li>
                    </ul>
                    <div style="padding: 0.5rem; background: color-mix(in srgb, #10b981 10%, transparent); border-radius: 6px; color: #10b981; font-weight: 500; margin-top: 0.5rem;">
                        Tus datos existentes y configuración están totalmente protegidos.
                    </div>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Sí, actualizar a 1 clic',
            cancelButtonText: 'Cancelar'
        }).then((res) => {
            if (res.isConfirmed) {
                Swal.fire({
                    title: 'Actualizando Sistema...',
                    html: 'Respaldando base de datos, sincronizando código y verificando estructura.<br><br><strong>Por favor espera un momento sin cerrar esta ventana.</strong>',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();

                        logConsole('Iniciando proceso de actualización a 1 clic...', '#f59e0b');

                        fetch('ajax/ajax_system_updater.php?action=update')
                        .then(r => r.json())
                        .then(data => {
                            if (data.log && Array.isArray(data.log)) {
                                data.log.forEach(l => logConsole(l, l.includes('ERROR') ? '#ef4444' : '#10b981'));
                            }

                            if (data.success) {
                                Swal.fire({
                                    title: '¡Actualización Exitosa!',
                                    html: `El sistema ha sido actualizado a la versión más reciente.<br><br><small>Respaldo de seguridad guardado: <strong>${data.safety_backup || ''}</strong></small>`,
                                    icon: 'success'
                                }).then(() => {
                                    window.location.reload();
                                });
                            } else {
                                Swal.fire('Error en la Actualización', data.error || 'Revisa la consola para más detalles.', 'error');
                            }
                        })
                        .catch(err => {
                            logConsole('❌ Error de conexión: ' + err.message, '#ef4444');
                            Swal.fire('Error de Red', err.message, 'error');
                        });
                    }
                });
            }
        });
    }
});
</script>
