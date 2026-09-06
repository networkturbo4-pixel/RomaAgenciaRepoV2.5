// assets/js/modules/rrhh.js

const RrhhModule = (function() {
    let employees = [];
    let currentFilter = 'Todos';
    let searchQuery = '';
    let searchTimeout = null;
    let currentEmpSalary = 0;
    let currentEmpId = 0;
    let currentEmployeePayments = [];

    const tbody = document.getElementById('employeesTableBody');
    const formModal = document.getElementById('modal-employee-form');
    const deleteModal = document.getElementById('modal-delete-employee');
    const paymentsModal = document.getElementById('modal-payments');

    function init() {
        setupEventListeners();
        fetchEmployees();
    }

    function setupEventListeners() {
        // Search Input
        const searchInput = document.getElementById('rrhhSearchInput');
        const clearBtn = document.getElementById('rrhhSearchClearBtn');

        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                searchQuery = e.target.value.trim();
                clearBtn.style.display = searchQuery !== '' ? 'flex' : 'none';
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(fetchEmployees, 300);
            });
        }

        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                if (searchInput) searchInput.value = '';
                searchQuery = '';
                clearBtn.style.display = 'none';
                fetchEmployees();
            });
        }
        
        // Filter Pills
        document.querySelectorAll('.rrhh-filter-pill').forEach(btn => {
            btn.addEventListener('click', (e) => {
                document.querySelectorAll('.rrhh-filter-pill').forEach(b => b.classList.remove('active'));
                const target = e.currentTarget;
                target.classList.add('active');
                currentFilter = target.dataset.status || 'Todos';
                fetchEmployees();
            });
        });

        // Payment Calculator Inputs
        const calcDays = document.getElementById('calc_days');
        const calcExtra = document.getElementById('calc_extra_hours');
        const calcBaseSalary = document.getElementById('calc_base_salary');
        const calcBonuses = document.getElementById('calc_bonuses');
        const calcDiscounts = document.getElementById('calc_discounts');

        if (calcDays && calcExtra && calcBaseSalary) {
            calcDays.addEventListener('input', updateCalculator);
            calcExtra.addEventListener('input', updateCalculator);
            if (calcBonuses) calcBonuses.addEventListener('input', updateCalculator);
            if (calcDiscounts) calcDiscounts.addEventListener('input', updateCalculator);
            calcBaseSalary.addEventListener('input', () => {
                currentEmpSalary = parseFloat(calcBaseSalary.value) || 0;
                updateCalculator();
            });
        }
        
        // Share History Button
        const btnShareHistory = document.getElementById('btn-share-history');
        if (btnShareHistory) {
            btnShareHistory.addEventListener('click', () => {
                if (currentEmpId > 0) {
                    const baseUrl = window.location.origin + window.location.pathname.replace(/\/index\.php.*$/, '').replace(/\/$/, '');
                    const shareLink = baseUrl + '/index.php?module=public&action=employee_history&id=' + currentEmpId;
                    navigator.clipboard.writeText(shareLink).then(() => {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                toast: true,
                                position: 'top-end',
                                icon: 'success',
                                title: 'Enlace de historial copiado',
                                showConfirmButton: false,
                                timer: 2500,
                                background: 'var(--bg-surface)',
                                color: 'var(--text-main)'
                            });
                        } else {
                            alert('Enlace copiado al portapapeles');
                        }
                    });
                }
            });
        }
    }

    function fetchEmployees() {
        const spinner = document.getElementById('rrhhSearchSpinner');
        if (spinner) spinner.style.display = 'block';

        fetch(`modules/admin/ajax_get_employees.php?q=${encodeURIComponent(searchQuery)}&status=${encodeURIComponent(currentFilter)}`)
            .then(res => res.json())
            .then(res => {
                if (spinner) spinner.style.display = 'none';
                if (res.success) {
                    employees = res.data;
                    
                    // Update KPI counters
                    if (res.counts) {
                        const totalBadge = document.getElementById('totalEmpBadge');
                        const kpiTotal = document.getElementById('kpiTotal');
                        const kpiActive = document.getElementById('kpiActive');
                        const kpiInactive = document.getElementById('kpiInactive');
                        const kpiPayroll = document.getElementById('kpiPayroll');

                        if (totalBadge) totalBadge.textContent = `${res.counts.total} empleados`;
                        if (kpiTotal) kpiTotal.textContent = res.counts.total;
                        if (kpiActive) kpiActive.textContent = res.counts.active;
                        if (kpiInactive) kpiInactive.textContent = res.counts.inactive;
                        if (kpiPayroll) kpiPayroll.textContent = `S/ ${parseFloat(res.counts.payroll).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                    }

                    renderTable(employees);
                } else {
                    console.error(res.error);
                }
            })
            .catch(err => {
                if (spinner) spinner.style.display = 'none';
                console.error(err);
            });
    }

    function renderTable(list) {
        if (!tbody) return;
        const container = document.querySelector('.rrhh-list-container');
        const emptyState = document.getElementById('rrhhEmptyState');
        const pill = document.getElementById('rrhhSearchResultPill');

        if (pill) pill.textContent = `Mostrando ${list.length} empleados`;

        if (list.length === 0) {
            if (container) container.style.display = 'none';
            if (emptyState) emptyState.style.display = 'flex';
            tbody.innerHTML = '';
            return;
        }

        if (container) container.style.display = 'block';
        if (emptyState) emptyState.style.display = 'none';
        tbody.innerHTML = '';

        list.forEach(emp => {
            const tr = document.createElement('tr');
            tr.className = 'emp-row-card';
            
            const st = (emp.status || 'Activo').toLowerCase();
            let iconClass = 'ph-check-circle';
            if (st === 'inactivo') iconClass = 'ph-x-circle';
            if (st === 'pendiente') iconClass = 'ph-dots-three-circle';

            let scheduleText = '';
            if (emp.work_start && emp.work_end) {
                scheduleText = `<span style="font-size:11px; color:var(--text-muted);"><i class="ph ph-clock"></i> ${emp.work_start.substring(0,5)} - ${emp.work_end.substring(0,5)}</span>`;
            }

            tr.innerHTML = `
                <td class="col-user" data-label="USUARIO">
                    <div class="emp-user-cell">
                        <div class="emp-avatar" style="background-color: ${emp.color};">
                            ${emp.initials}
                        </div>
                        <div class="emp-info-wrap">
                            <span class="emp-name-text">${emp.name}</span>
                            <span class="emp-email-text">${emp.email}</span>
                        </div>
                    </div>
                </td>
                <td class="col-role" data-label="ROL / DEPARTAMENTO">
                    <div class="emp-role-cell">
                        <span class="emp-role-title">${emp.role}</span>
                        <span class="emp-dept-title">${emp.department}</span>
                        ${scheduleText}
                    </div>
                </td>
                <td class="col-status" data-label="ESTADO">
                    <span class="status-badge status-${st}">
                        <i class="ph ${iconClass}"></i> ${emp.status}
                    </span>
                </td>
                <td class="col-salary" data-label="SALARIO / CONTRATACIÓN">
                    <div class="emp-salary-cell">
                        <span class="emp-salary-amount">S/ ${parseFloat(emp.salary).toFixed(2)}</span>
                        <span class="emp-hire-date">Ingreso: ${emp.hire_date}</span>
                    </div>
                </td>
                <td class="col-actions" data-label="ACCIONES" style="text-align: right;">
                    <div class="action-buttons-group">
                        <button class="action-btn-saas" title="Pagos y Boletas" onclick="RrhhModule.openPaymentsModal(${emp.id})">
                            <i class="ph ph-wallet"></i>
                        </button>
                        <button class="action-btn-saas" title="Editar Empleado" onclick="RrhhModule.openModal(${emp.id})">
                            <i class="ph ph-pencil-simple"></i>
                        </button>
                        <button class="action-btn-saas delete-btn" title="Eliminar" onclick="RrhhModule.confirmDelete(${emp.id})">
                            <i class="ph ph-trash"></i>
                        </button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    function openModal(id = 0) {
        document.getElementById('emp_form').reset();
        document.getElementById('emp_id').value = id;
        
        if (id === 0) {
            document.getElementById('modal-title-emp').textContent = 'Nuevo Empleado';
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('emp_hire_date').value = today;
            document.getElementById('emp_status').value = 'Activo';
        } else {
            document.getElementById('modal-title-emp').textContent = 'Editar Empleado';
            const emp = employees.find(e => e.id == id);
            if (emp) {
                document.getElementById('emp_name').value = emp.name || '';
                document.getElementById('emp_dni').value = emp.dni || '';
                document.getElementById('emp_email').value = emp.email || '';
                document.getElementById('emp_phone').value = emp.phone || '';
                document.getElementById('emp_role').value = emp.role || '';
                document.getElementById('emp_department').value = emp.department || '';
                document.getElementById('emp_status').value = emp.status || 'Activo';
                document.getElementById('emp_salary').value = emp.salary || '';
                document.getElementById('emp_hire_date').value = emp.hire_date || '';
                document.getElementById('emp_work_start').value = emp.work_start || '';
                document.getElementById('emp_work_end').value = emp.work_end || '';
            }
        }
        formModal.classList.add('active');
    }

    function closeModal() {
        formModal.classList.remove('active');
    }

    function saveEmployee() {
        const formData = new FormData(document.getElementById('emp_form'));
        const btn = document.getElementById('btnSaveEmp');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Guardando...';
        btn.disabled = true;
        
        fetch('modules/admin/ajax_save_employee.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeModal();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'Empleado guardado correctamente',
                        showConfirmButton: false,
                        timer: 2500,
                        background: 'var(--bg-surface)',
                        color: 'var(--text-main)'
                    });
                }
                fetchEmployees();
            } else {
                alert('Error: ' + (data.message || 'No se pudo guardar'));
            }
        })
        .catch(err => {
            console.error(err);
            alert('Error de conexión.');
        })
        .finally(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    }

    let deleteId = 0;
    function confirmDelete(id) {
        deleteId = id;
        deleteModal.classList.add('active');
    }

    function closeDeleteModal() {
        deleteModal.classList.remove('active');
        deleteId = 0;
    }

    function deleteEmployee() {
        if (deleteId === 0) return;
        const btn = document.getElementById('btnConfirmDeleteEmp');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Eliminando...';
        btn.disabled = true;
        
        fetch('modules/admin/ajax_delete_employee.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: deleteId })
        })
        .then(res => res.json())
        .then(data => {
            closeDeleteModal();
            if (data.success) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'Empleado eliminado correctamente',
                        showConfirmButton: false,
                        timer: 2500,
                        background: 'var(--bg-surface)',
                        color: 'var(--text-main)'
                    });
                }
                fetchEmployees();
            } else {
                alert('Error: ' + (data.message || 'No se pudo eliminar'));
            }
        })
        .catch(err => {
            console.error(err);
            alert('Error de conexión.');
            closeDeleteModal();
        })
        .finally(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    }

    // Payments Logic
    function openPaymentsModal(empId) {
        const emp = employees.find(e => e.id == empId);
        if (!emp) return;
        
        currentEmpId = emp.id;
        currentEmpSalary = parseFloat(emp.salary) || 0;
        
        document.getElementById('payments-emp-name').textContent = emp.name;
        document.getElementById('pay_employee_id').value = emp.id;
        
        cancelPaymentEdit();
        loadPaymentsHistory(emp.id);
        paymentsModal.classList.add('active');
    }

    function cancelPaymentEdit() {
        document.getElementById('payment_form').reset();
        document.getElementById('pay_id').value = "0";
        document.getElementById('calc_days').value = 0;
        document.getElementById('calc_extra_hours').value = 0;
        if (document.getElementById('calc_bonuses')) document.getElementById('calc_bonuses').value = 0;
        if (document.getElementById('calc_discounts')) document.getElementById('calc_discounts').value = 0;
        document.getElementById('calc_base_salary').value = currentEmpSalary.toFixed(2);
        document.getElementById('pay_date').value = new Date().toISOString().split('T')[0];
        document.querySelector('#payment_form button[type="submit"]').innerHTML = '<i class="ph ph-check"></i> Registrar Pago';
        document.getElementById('btn-cancel-edit').style.display = 'none';
        updateCalculator();
    }

    function updateCalculator() {
        const extraDays = parseFloat(document.getElementById('calc_days').value) || 0;
        const extraHours = parseFloat(document.getElementById('calc_extra_hours').value) || 0;
        const bonuses = document.getElementById('calc_bonuses') ? parseFloat(document.getElementById('calc_bonuses').value) || 0 : 0;
        const discounts = document.getElementById('calc_discounts') ? parseFloat(document.getElementById('calc_discounts').value) || 0 : 0;
        
        const dailyRate = currentEmpSalary / 30;
        const hourlyRate = dailyRate / 8;
        
        document.getElementById('lbl_daily_rate').textContent = 'S/ ' + dailyRate.toFixed(2);
        document.getElementById('lbl_hourly_rate').textContent = 'S/ ' + hourlyRate.toFixed(2);
        
        const extraAmount = (extraDays * dailyRate) + (extraHours * hourlyRate) + bonuses - discounts;
        const total = currentEmpSalary + extraAmount;
        
        document.getElementById('pay_extra_amount').value = extraAmount.toFixed(2);
        document.getElementById('pay_amount').value = total.toFixed(2);
    }

    function closePaymentsModal() {
        paymentsModal.classList.remove('active');
    }

    function loadPaymentsHistory(empId) {
        const tbody = document.getElementById('payments-history-tbody');
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:1.5rem;">Cargando...</td></tr>';
        
        fetch('index.php?module=admin&action=ajax_get_payments&employee_id=' + empId)
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    currentEmployeePayments = data.data;
                    if (data.data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:1.5rem; color:var(--text-muted);">No hay pagos registrados.</td></tr>';
                        return;
                    }
                    
                    tbody.innerHTML = '';
                    data.data.forEach(pay => {
                        const tr = document.createElement('tr');
                        
                        let actionsHtml = `
                            <button type="button" class="btn btn-outline" style="padding: 0.3rem 0.5rem; font-size: 0.8rem; border-color:transparent; color: var(--primary-color);" onclick="RrhhModule.editPayment(${pay.id})" title="Editar"><i class="ph ph-pencil-simple"></i></button>
                            <button type="button" class="btn btn-outline" style="padding: 0.3rem 0.5rem; font-size: 0.8rem; border-color:transparent; color: var(--danger-color);" onclick="RrhhModule.deletePayment(${pay.id})" title="Eliminar"><i class="ph ph-trash"></i></button>
                        `;

                        let st = (pay.status || 'Pagado').toLowerCase();
                        let badgeClass = st === 'pendiente' ? 'background: rgba(245, 158, 11, 0.15); color: #f59e0b;' : 'background: rgba(16, 185, 129, 0.15); color: #10b981;';
                        let icon = st === 'pendiente' ? 'ph-clock' : 'ph-check-circle';
                        let statusHtml = `<span style="display:inline-flex; align-items:center; gap:0.25rem; padding:0.25rem 0.6rem; border-radius:9999px; font-size:0.75rem; font-weight:600; ${badgeClass}"><i class="ph ${icon}"></i> ${pay.status || 'Pagado'}</span>`;
                        
                        tr.innerHTML = `
                            <td data-label="FECHA">${pay.payment_date}</td>
                            <td data-label="CONCEPTO">${pay.concept}</td>
                            <td data-label="MONTO" style="font-weight:700; color:var(--text-main);">S/ ${parseFloat(pay.amount).toFixed(2)}</td>
                            <td data-label="ESTADO">${statusHtml}</td>
                            <td data-label="ACCIONES" style="text-align: right;">
                                <div style="display:flex; justify-content:flex-end; gap:0.25rem;">
                                    ${actionsHtml}
                                </div>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });
                }
            })
            .catch(err => {
                console.error(err);
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; color:var(--danger-color);">Error al cargar historial.</td></tr>';
            });
    }

    function savePayment() {
        const formData = new FormData(document.getElementById('payment_form'));
        
        fetch('index.php?module=admin&action=ajax_save_payment', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'Pago registrado correctamente',
                        showConfirmButton: false,
                        timer: 2500,
                        background: 'var(--bg-surface)',
                        color: 'var(--text-main)'
                    });
                }
                cancelPaymentEdit();
                loadPaymentsHistory(currentEmpId);
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(err => console.error(err));
    }

    function editPayment(payId) {
        const pay = currentEmployeePayments.find(p => p.id == payId);
        if (!pay) return;
        
        document.getElementById('pay_id').value = pay.id;
        document.getElementById('pay_concept').value = pay.concept;
        document.getElementById('pay_date').value = pay.payment_date;
        document.getElementById('pay_status').value = pay.status;
        document.getElementById('pay_amount').value = parseFloat(pay.amount).toFixed(2);
        
        document.getElementById('calc_days').value = 0;
        document.getElementById('calc_extra_hours').value = 0;
        if (document.getElementById('calc_bonuses')) document.getElementById('calc_bonuses').value = 0;
        if (document.getElementById('calc_discounts')) document.getElementById('calc_discounts').value = 0;
        document.getElementById('calc_base_salary').value = parseFloat(pay.amount).toFixed(2);
        
        document.querySelector('#payment_form button[type="submit"]').innerHTML = '<i class="ph ph-pencil-simple"></i> Actualizar Pago';
        document.getElementById('btn-cancel-edit').style.display = 'block';
    }

    function deletePayment(payId) {
        if (!confirm('¿Estás seguro de que deseas eliminar este registro de pago?')) return;
        
        fetch('index.php?module=admin&action=ajax_delete_payment', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: payId })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'Pago eliminado',
                        showConfirmButton: false,
                        timer: 2000,
                        background: 'var(--bg-surface)',
                        color: 'var(--text-main)'
                    });
                }
                loadPaymentsHistory(currentEmpId);
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(err => console.error(err));
    }

    return {
        init: init,
        openModal: openModal,
        closeModal: closeModal,
        saveEmployee: saveEmployee,
        confirmDelete: confirmDelete,
        closeDeleteModal: closeDeleteModal,
        deleteEmployee: deleteEmployee,
        openPaymentsModal: openPaymentsModal,
        closePaymentsModal: closePaymentsModal,
        cancelPaymentEdit: cancelPaymentEdit,
        savePayment: savePayment,
        editPayment: editPayment,
        deletePayment: deletePayment
    };
})();

document.addEventListener('DOMContentLoaded', () => {
    RrhhModule.init();
});
