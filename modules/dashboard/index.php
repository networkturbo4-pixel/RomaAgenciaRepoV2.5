<?php
// modules/dashboard/index.php
require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 style="font-size: 1.5rem; font-weight: 700;">Dashboard</h1>
    <button class="btn btn-primary">
        <i class="ph ph-plus"></i> Nuevo Proyecto
    </button>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="card stat-card">
        <div class="stat-icon" style="background-color: var(--primary-bg); color: var(--primary-contrast);">
            <i class="ph ph-users"></i>
        </div>
        <div class="stat-details">
            <h3>1,245</h3>
            <p>Usuarios Activos</p>
        </div>
    </div>
    <div class="card stat-card">
        <div class="stat-icon" style="background-color: var(--color-green-bg); color: var(--color-green);">
            <i class="ph ph-chart-line-up"></i>
        </div>
        <div class="stat-details">
            <h3>$45,231</h3>
            <p>Ingresos del Mes</p>
        </div>
    </div>
    <div class="card stat-card">
        <div class="stat-icon" style="background-color: var(--color-orange-bg); color: var(--color-orange);">
            <i class="ph ph-clock"></i>
        </div>
        <div class="stat-details">
            <h3>34</h3>
            <p>Tareas Pendientes</p>
        </div>
    </div>
    <div class="card stat-card">
        <div class="stat-icon" style="background-color: var(--color-red-bg); color: var(--color-red);">
            <i class="ph ph-warning-circle"></i>
        </div>
        <div class="stat-details">
            <h3>3</h3>
            <p>Alertas Críticas</p>
        </div>
    </div>
</div>

<!-- Main Content Area -->
<div class="card">
    <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: var(--space-4);">Actividad Reciente</h2>
    <div class="table-responsive">
        <table style="width: 100%; border-collapse: collapse; text-align: left;">
            <thead>
                <tr style="border-bottom: 2px solid var(--border-color); color: var(--text-muted);">
                    <th style="padding: var(--space-3) 0;">Usuario</th>
                    <th style="padding: var(--space-3) 0;">Acción</th>
                    <th style="padding: var(--space-3) 0;">Fecha</th>
                    <th style="padding: var(--space-3) 0;">Estado</th>
                </tr>
            </thead>
            <tbody>
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: var(--space-3) 0; font-weight: 500;">Juan Pérez</td>
                    <td style="padding: var(--space-3) 0; color: var(--text-muted);">Actualizó perfil</td>
                    <td style="padding: var(--space-3) 0; color: var(--text-muted);">Hace 2 min</td>
                    <td style="padding: var(--space-3) 0;"><span style="background: var(--color-green-bg); color: var(--color-green); padding: 0.25rem 0.5rem; border-radius: var(--radius-full); font-size: 0.75rem; font-weight: 600;">Completado</span></td>
                </tr>
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: var(--space-3) 0; font-weight: 500;">Ana Gómez</td>
                    <td style="padding: var(--space-3) 0; color: var(--text-muted);">Nuevo pago</td>
                    <td style="padding: var(--space-3) 0; color: var(--text-muted);">Hace 1 hora</td>
                    <td style="padding: var(--space-3) 0;"><span style="background: var(--color-green-bg); color: var(--color-green); padding: 0.25rem 0.5rem; border-radius: var(--radius-full); font-size: 0.75rem; font-weight: 600;">Completado</span></td>
                </tr>
                <tr>
                    <td style="padding: var(--space-3) 0; font-weight: 500;">Sistema</td>
                    <td style="padding: var(--space-3) 0; color: var(--text-muted);">Backup automático</td>
                    <td style="padding: var(--space-3) 0; color: var(--text-muted);">Ayer</td>
                    <td style="padding: var(--space-3) 0;"><span style="background: var(--color-orange-bg); color: var(--color-orange); padding: 0.25rem 0.5rem; border-radius: var(--radius-full); font-size: 0.75rem; font-weight: 600;">Pendiente</span></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
