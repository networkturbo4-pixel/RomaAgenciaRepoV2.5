<?php
// modules/admin/rrhh.php
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?module=auth&action=login");
    exit();
}

require_once 'includes/header.php';
?>

<style>
/* RRHH Specific Styles */
.rrhh-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    background: var(--bg-surface);
    padding: 1rem;
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-color);
}

.search-box {
    position: relative;
    flex: 1;
    min-width: 250px;
    max-width: 400px;
}

.search-box i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
}

.search-box input {
    width: 100%;
    padding: 0.5rem 1rem 0.5rem 2.5rem;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-full);
    background: var(--bg-body);
    color: var(--text-main);
    font-size: 0.875rem;
    outline: none;
    transition: all 0.2s ease;
}

.search-box input:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.1);
}

.filters-group {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.filter-btn {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.4rem 1rem;
    border-radius: var(--radius-full);
    font-size: 0.85rem;
    font-weight: 600;
    border: 1px solid var(--border-color);
    background: var(--bg-surface);
    color: var(--text-muted);
    cursor: pointer;
    transition: all 0.2s ease;
}

.filter-btn:hover {
    background: var(--bg-body);
    color: var(--text-main);
}

.filter-btn.active {
    background: var(--text-main);
    color: var(--bg-surface);
    border-color: var(--text-main);
}

.filter-btn i {
    font-size: 1rem;
}

.user-cell {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.9rem;
    flex-shrink: 0;
}

.rrhh-user-info {
    display: flex;
    flex-direction: column;
}

.user-name {
    font-weight: 600;
    color: var(--text-main);
}

.user-email {
    font-size: 0.8rem;
    color: var(--text-muted);
}

.role-info {
    display: flex;
    flex-direction: column;
}

.role-title {
    font-weight: 600;
    color: var(--text-main);
}

.role-dept {
    font-size: 0.8rem;
    color: var(--text-muted);
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.75rem;
    border-radius: var(--radius-full);
    font-size: 0.75rem;
    font-weight: 600;
}

.status-activo {
    background: rgba(16, 185, 129, 0.15);
    color: var(--secondary-color);
}

.status-inactivo {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
}

.status-pendiente {
    background: rgba(245, 158, 11, 0.15);
    color: var(--warning-color);
}

.action-buttons-rrhh {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
}

.btn-action-icon {
    width: 32px;
    height: 32px;
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    transition: all 0.2s ease;
}

/* Payments Modal Layout */
.payments-container {
    display: flex;
    flex-wrap: wrap;
    gap: 2rem;
}
.payments-left {
    flex: 1;
    min-width: 300px;
}
.payments-right {
    flex: 2;
    min-width: 350px;
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
}

.btn-action-icon:hover {
    background: var(--bg-body);
    color: var(--primary-color);
}

.pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 0;
    border-top: 1px solid var(--border-color);
    margin-top: 1rem;
}

.pagination-info {
    font-size: 0.875rem;
    color: var(--text-muted);
}
</style>

<div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.5rem; display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); flex-wrap: wrap; gap: 1rem;">
    <div style="display: flex; align-items: center; gap: 1.25rem;">
        <div style="width: 56px; height: 56px; background: var(--bg-color); border-radius: 12px; display: flex; align-items: center; justify-content: center; border: 1px solid var(--border-color);">
            <i class="ph ph-users-three" style="font-size: 1.75rem; color: var(--primary-color);"></i>
        </div>
        <div>
            <h1 style="margin: 0; font-size: 1.5rem; font-weight: 700; color: var(--color-title);">Recursos Humanos</h1>
            <p style="margin: 0.25rem 0 0 0; color: var(--text-muted); font-size: 0.85rem;">Gestiona a los empleados, sus roles, salarios y registros de pagos.</p>
        </div>
    </div>
    <div style="display: flex; align-items: center;">
        <button class="btn btn-primary" onclick="RrhhModule.openModal(0)" style="white-space: nowrap; display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.2rem; border-radius: 8px;">
            <i class="ph ph-user-plus"></i> Nuevo Empleado
        </button>
    </div>
</div>

<div class="rrhh-toolbar">
    <div class="search-box">
        <i class="ph ph-magnifying-glass"></i>
        <input type="text" placeholder="Buscar por nombre, email o rol...">
    </div>
    <div class="filters-group">
        <button class="filter-btn active"><i class="ph ph-funnel"></i> Todos</button>
        <button class="filter-btn"><i class="ph ph-check-circle"></i> Activos</button>
        <button class="filter-btn"><i class="ph ph-x-circle"></i> Inactivos</button>
        <button class="filter-btn"><i class="ph ph-dots-three-circle"></i> Pendientes</button>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table" id="employees-table">
            <thead>
                <tr>
                    <th>USUARIO</th>
                    <th>ROL / DEPARTAMENTO</th>
                    <th>ESTADO</th>
                    <th>SALARIO / CONTRATACIÓN</th>
                    <th style="text-align: right;">ACCIONES</th>
                </tr>
            </thead>
            <tbody>
                <!-- Contenido generado dinámicamente vía JS -->
            </tbody>
        </table>
        
        <div class="pagination">
            <div class="pagination-info" id="pagination-info-text">Mostrando 0 empleados</div>
        </div>
    </div>
</div>

<!-- Modal: Formulario Empleado -->
<div class="modal-overlay" id="modal-employee-form">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title" id="modal-title-emp">Nuevo Empleado</h3>
            <button type="button" class="btn-close-circular" onclick="RrhhModule.closeModal()"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body">
            <form id="emp_form" onsubmit="event.preventDefault(); RrhhModule.saveEmployee();">
                <input type="hidden" id="emp_id" name="id" value="0">
                <div class="form-group mb-3">
                    <label class="form-label">Nombre Completo *</label>
                    <input type="text" class="form-control" id="emp_name" name="name" required>
                </div>
                <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">DNI *</label>
                        <input type="text" class="form-control" id="emp_dni" name="dni" required>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">Celular</label>
                        <input type="text" class="form-control" id="emp_phone" name="phone">
                    </div>
                </div>
                <div class="form-group mb-3">
                    <label class="form-label">Correo Electrónico *</label>
                    <input type="email" class="form-control" id="emp_email" name="email" required>
                </div>
                <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">Rol *</label>
                        <input type="text" class="form-control" id="emp_role" name="role" required>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">Departamento *</label>
                        <input type="text" class="form-control" id="emp_department" name="department" required>
                    </div>
                </div>
                <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">Salario (S/) *</label>
                        <input type="number" step="0.01" class="form-control" id="emp_salary" name="salary" required>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">Fecha Contratación *</label>
                        <input type="date" class="form-control" id="emp_hire_date" name="hire_date" required>
                    </div>
                </div>
                <div class="form-group mb-3">
                    <label class="form-label">Estado</label>
                    <select class="form-control" id="emp_status" name="status">
                        <option value="Activo">Activo</option>
                        <option value="Inactivo">Inactivo</option>
                        <option value="Pendiente">Pendiente</option>
                    </select>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="RrhhModule.closeModal()">Cancelar</button>
            <button type="submit" form="emp_form" class="btn btn-primary">Guardar</button>
        </div>
    </div>
</div>

<!-- Modal: Eliminar Empleado -->
<div class="modal-overlay" id="modal-delete-employee">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h3 class="modal-title" style="color: var(--danger-color);"><i class="ph ph-warning-circle"></i> Eliminar Empleado</h3>
            <button type="button" class="btn-close-circular" onclick="RrhhModule.closeDeleteModal()"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body">
            <p>¿Estás seguro de que deseas eliminar este empleado? Esta acción no se puede deshacer.</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="RrhhModule.closeDeleteModal()">Cancelar</button>
            <button type="button" class="btn btn-primary" style="background: var(--danger-color); border-color: var(--danger-color);" onclick="RrhhModule.deleteEmployee()">Eliminar</button>
        </div>
    </div>
</div>

<!-- Modal: Gestión de Pagos -->
<div class="modal-overlay" id="modal-payments">
    <div class="modal-content" style="max-width: 900px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="ph ph-wallet"></i> Gestión de Pagos - <span id="payments-emp-name"></span></h3>
            <button type="button" class="btn-close-circular" onclick="RrhhModule.closePaymentsModal()"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body">
            <div class="payments-container">
                <!-- Izquierda: Formulario de Registro -->
                <div class="payments-left">
                    <h4 style="font-size: 1rem; font-weight: 600; margin-bottom: 1rem; color: var(--text-main);">Registrar Nuevo Pago</h4>
                    <form id="payment_form" onsubmit="event.preventDefault(); RrhhModule.savePayment();">
                        <input type="hidden" id="pay_employee_id" name="employee_id" value="0">
                        <div class="form-group mb-3">
                            <label class="form-label">Concepto *</label>
                            <input type="text" class="form-control" id="pay_concept" name="concept" placeholder="Ej: Nómina Mayo" required>
                        </div>
                        <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
                            <div class="form-group" style="flex: 1;">
                                <label class="form-label">Fecha de Pago *</label>
                                <input type="date" class="form-control" id="pay_date" name="payment_date" required>
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label class="form-label">Monto (S/) *</label>
                                <input type="number" step="0.01" class="form-control" id="pay_amount" name="amount" required>
                            </div>
                        </div>
                        <div class="form-group mb-3">
                            <label class="form-label">Comprobante (Opcional)</label>
                            <input type="file" class="form-control" id="pay_voucher" name="voucher" accept=".pdf,.jpg,.jpeg,.png">
                            <small style="color: var(--text-muted); font-size: 0.75rem;">Sube un voucher en PDF, JPG o PNG.</small>
                        </div>
                        <button type="submit" form="payment_form" class="btn btn-primary w-100 mt-2"><i class="ph ph-check"></i> Registrar Pago</button>
                    </form>
                </div>
                
                <!-- Derecha: Historial -->
                <div class="payments-right">
                    <h4 style="font-size: 1rem; font-weight: 600; margin-bottom: 1rem; color: var(--text-main);">Historial de Pagos</h4>
                    <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                        <table class="table" style="margin: 0; min-width: 100%;">
                            <thead>
                                <tr>
                                    <th>FECHA</th>
                                    <th>CONCEPTO</th>
                                    <th>MONTO</th>
                                    <th style="text-align: right;">COMPROBANTE</th>
                                </tr>
                            </thead>
                            <tbody id="payments-history-tbody">
                                <!-- Cargado vía JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="RrhhModule.closePaymentsModal()">Cerrar</button>
        </div>
    </div>
</div>

<script src="assets/js/modules/rrhh.js?v=<?php echo time(); ?>"></script>
<?php require_once 'includes/footer.php'; ?>
